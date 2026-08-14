<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Company;
use App\Models\ActivityLog;
use Illuminate\Support\Str;

class LeadNormalizerService
{
    /**
     * Normalize and save/update lead, checking for duplicates.
     */
    public function processAndSave(array $data, int $companyId, string $source = 'manual', ?string $sourceId = null): array
    {
        $normalized = $this->normalizeData($data);
        $normalized['company_id'] = $companyId;
        $normalized['source'] = $source;
        $normalized['source_id'] = $sourceId ?? ($data['source_id'] ?? null);

        // Check website status
        if (empty($normalized['website'])) {
            $normalized['website_status'] = 'no_website';
        } else {
            $normalized['website_status'] = $this->determineWebsiteStatus($normalized['website']);
        }

        // Email status
        $normalized['email_status'] = !empty($normalized['email']) ? 'available' : 'missing';
        $normalized['phone_status'] = !empty($normalized['phone']) ? 'available' : 'missing';

        // Check for duplicates
        $existingLead = $this->findDuplicate($companyId, $normalized);

        if ($existingLead) {
            // Update missing fields
            $updatedFields = [];
            foreach (['contact_name', 'email', 'secondary_email', 'phone', 'secondary_phone', 'whatsapp_number', 'website', 'address', 'city', 'state', 'country', 'postal_code', 'google_maps_url', 'google_rating', 'review_count'] as $field) {
                if (empty($existingLead->$field) && !empty($normalized[$field])) {
                    $existingLead->$field = $normalized[$field];
                    $updatedFields[] = $field;
                }
            }

            if (!empty($normalized['website_status']) && $existingLead->website_status === 'unknown') {
                $existingLead->website_status = $normalized['website_status'];
            }

            $existingLead->save();

            // Log activity
            ActivityLog::create([
                'company_id' => $companyId,
                'lead_id' => $existingLead->id,
                'description' => "Duplicate lead merged from {$source}",
                'action_type' => 'lead_merged',
                'meta' => ['updated_fields' => $updatedFields]
            ]);

            return [
                'status' => 'merged',
                'lead' => $existingLead,
                'is_duplicate' => true,
            ];
        }

        // Create new lead
        $lead = Lead::create($normalized);

        // Log activity
        ActivityLog::create([
            'company_id' => $companyId,
            'lead_id' => $lead->id,
            'description' => "Lead created from {$source}",
            'action_type' => 'lead_created',
            'meta' => ['source' => $source]
        ]);

        return [
            'status' => 'created',
            'lead' => $lead,
            'is_duplicate' => false,
        ];
    }

    /**
     * Normalize lead fields.
     */
    public function normalizeData(array $raw): array
    {
        $businessName = trim($raw['business_name'] ?? $raw['title'] ?? $raw['name'] ?? 'Unnamed Business');
        $contactName = trim($raw['contact_name'] ?? $raw['owner_name'] ?? '');
        $category = trim($raw['category'] ?? $raw['categoryName'] ?? '');
        $email = $this->cleanEmail($raw['email'] ?? $raw['primary_email'] ?? null);
        $secondaryEmail = $this->cleanEmail($raw['secondary_email'] ?? null);
        $phone = $this->cleanPhone($raw['phone'] ?? $raw['primary_phone'] ?? $raw['phoneNumber'] ?? null);
        $secondaryPhone = $this->cleanPhone($raw['secondary_phone'] ?? null);
        $whatsapp = $this->cleanPhone($raw['whatsapp_number'] ?? null);
        $website = $this->cleanWebsite($raw['website'] ?? null);

        $address = trim($raw['address'] ?? $raw['street'] ?? '');
        $city = trim($raw['city'] ?? '');
        $state = trim($raw['state'] ?? '');
        $country = trim($raw['country'] ?? '');
        $postalCode = trim($raw['postal_code'] ?? $raw['zip'] ?? '');

        $googleRating = isset($raw['google_rating']) ? (float)$raw['google_rating'] : (isset($raw['totalScore']) ? (float)$raw['totalScore'] : null);
        $reviewCount = isset($raw['review_count']) ? (int)$raw['review_count'] : (isset($raw['reviewsCount']) ? (int)$raw['reviewsCount'] : 0);
        $googleMapsUrl = $raw['google_maps_url'] ?? $raw['url'] ?? null;

        $latitude = isset($raw['latitude']) ? (float)$raw['latitude'] : (isset($raw['location']['lat']) ? (float)$raw['location']['lat'] : null);
        $longitude = isset($raw['longitude']) ? (float)$raw['longitude'] : (isset($raw['location']['lng']) ? (float)$raw['location']['lng'] : null);

        return [
            'business_name' => $businessName,
            'contact_name' => $contactName,
            'category' => $category,
            'email' => $email,
            'secondary_email' => $secondaryEmail,
            'phone' => $phone,
            'secondary_phone' => $secondaryPhone,
            'whatsapp_number' => $whatsapp,
            'website' => $website,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'postal_code' => $postalCode,
            'google_maps_url' => $googleMapsUrl,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'google_rating' => $googleRating,
            'review_count' => $reviewCount,
            'tags' => $raw['tags'] ?? [],
            'notes' => $raw['notes'] ?? null,
            'lead_status' => $raw['lead_status'] ?? 'new',
        ];
    }

    /**
     * Find existing lead using multi-attribute checking.
     */
    public function findDuplicate(int $companyId, array $data): ?Lead
    {
        // 1. Google Place / Source ID check
        if (!empty($data['source_id'])) {
            $match = Lead::where('company_id', $companyId)
                ->where('source_id', $data['source_id'])
                ->first();
            if ($match) return $match;
        }

        // 2. Normalized Email check
        if (!empty($data['email'])) {
            $match = Lead::where('company_id', $companyId)
                ->where(function ($q) use ($data) {
                    $q->where('email', $data['email'])
                      ->orWhere('secondary_email', $data['email']);
                })->first();
            if ($match) return $match;
        }

        // 3. Normalized Phone check
        if (!empty($data['phone'])) {
            $match = Lead::where('company_id', $companyId)
                ->where(function ($q) use ($data) {
                    $q->where('phone', $data['phone'])
                      ->orWhere('secondary_phone', $data['phone']);
                })->first();
            if ($match) return $match;
        }

        // 4. Website domain check
        if (!empty($data['website'])) {
            $domain = parse_url($data['website'], PHP_URL_HOST);
            if ($domain) {
                $match = Lead::where('company_id', $companyId)
                    ->where('website', 'LIKE', '%' . $domain . '%')
                    ->first();
                if ($match) return $match;
            }
        }

        // 5. Business Name + City check
        if (!empty($data['business_name']) && !empty($data['city'])) {
            $match = Lead::where('company_id', $companyId)
                ->where('business_name', 'LIKE', $data['business_name'])
                ->where('city', 'LIKE', $data['city'])
                ->first();
            if ($match) return $match;
        }

        return null;
    }

    protected function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function cleanPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        // Keep digits and leading +
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));
        return strlen($cleaned) >= 7 ? $cleaned : null;
    }

    protected function cleanWebsite(?string $website): ?string
    {
        if (!$website) return null;
        $website = trim($website);
        if (!preg_match('~^https?://~i', $website)) {
            $website = 'https://' . $website;
        }
        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    protected function determineWebsiteStatus(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'invalid';
        }
        return 'has_website';
    }
}
