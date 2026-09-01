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
    public function processAndSave(array $data, int $companyId, string $source = 'google_maps', ?string $sourceId = null): array
    {
        $normalized = $this->normalizeData($data);
        $normalized['company_id'] = $companyId;
        $validSources = ['apify', 'google_maps', 'excel_import', 'manual'];
        $normalized['source'] = in_array($source, $validSources) ? $source : 'google_maps';
        $normalized['source_id'] = !empty($sourceId) ? substr((string)$sourceId, 0, 255) : ($normalized['source_id'] ?? null);

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
            foreach (['contact_name', 'email', 'secondary_email', 'phone', 'secondary_phone', 'whatsapp_number', 'website', 'address', 'city', 'state', 'country', 'postal_code', 'google_maps_url', 'google_rating', 'review_count', 'latitude', 'longitude'] as $field) {
                if (empty($existingLead->$field) && !empty($normalized[$field])) {
                    $existingLead->$field = $normalized[$field];
                    $updatedFields[] = $field;
                }
            }

            if (!empty($normalized['website_status']) && ($existingLead->website_status === 'unknown' || empty($existingLead->website_status))) {
                $existingLead->website_status = $normalized['website_status'];
            }

            if (!empty($normalized['email']) && $existingLead->email_status === 'missing') {
                $existingLead->email_status = 'available';
            }

            if (!empty($normalized['phone']) && $existingLead->phone_status === 'missing') {
                $existingLead->phone_status = 'available';
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
     * Normalize lead fields from various scrapers (Apify, Overpass, Nominatim, CSV).
     */
    public function normalizeData(array $raw): array
    {
        $businessName = trim($raw['business_name'] ?? $raw['title'] ?? $raw['name'] ?? $raw['placeName'] ?? 'Unnamed Business');
        $contactName = trim($raw['contact_name'] ?? $raw['owner_name'] ?? $raw['contactPerson'] ?? '');
        $category = trim($raw['category'] ?? $raw['categoryName'] ?? $raw['primaryCategory'] ?? (is_array($raw['categories'] ?? null) ? implode(', ', $raw['categories']) : ''));

        $email = $this->cleanEmail($raw['email'] ?? $raw['primary_email'] ?? (is_array($raw['emails'] ?? null) ? ($raw['emails'][0] ?? null) : null));
        $secondaryEmail = $this->cleanEmail($raw['secondary_email'] ?? (is_array($raw['emails'] ?? null) ? ($raw['emails'][1] ?? null) : null));
        
        $phone = $this->cleanPhone($raw['phone'] ?? $raw['primary_phone'] ?? $raw['phoneNumber'] ?? $raw['phoneUnformatted'] ?? $raw['internationalPhoneNumber'] ?? null);
        $secondaryPhone = $this->cleanPhone($raw['secondary_phone'] ?? $raw['additionalPhone'] ?? null);
        $whatsapp = $this->cleanPhone($raw['whatsapp_number'] ?? $raw['whatsapp'] ?? $phone);
        $website = $this->cleanWebsite($raw['website'] ?? $raw['url'] ?? $raw['domain'] ?? null);

        $address = trim($raw['address'] ?? $raw['street'] ?? $raw['formattedAddress'] ?? $raw['display_name'] ?? '');
        $city = trim($raw['city'] ?? '');
        $state = trim($raw['state'] ?? '');
        $country = trim($raw['country'] ?? $raw['countryCode'] ?? '');
        $postalCode = trim($raw['postal_code'] ?? $raw['postalCode'] ?? $raw['zip'] ?? '');

        // Ratings
        $rawRating = $raw['google_rating'] ?? $raw['totalScore'] ?? $raw['rating'] ?? $raw['stars'] ?? null;
        $googleRating = $rawRating !== null && is_numeric($rawRating) ? round(min(5.0, max(0.0, (float)$rawRating)), 2) : null;

        $rawReviews = $raw['review_count'] ?? $raw['reviewsCount'] ?? $raw['userRatingsTotal'] ?? $raw['reviews'] ?? 0;
        $reviewCount = is_numeric($rawReviews) ? (int)$rawReviews : 0;

        $googleMapsUrl = $raw['google_maps_url'] ?? $raw['url'] ?? $raw['googleUrl'] ?? $raw['placeUrl'] ?? null;

        // Geocoordinates
        $latitude = null;
        $longitude = null;
        if (isset($raw['latitude']) && is_numeric($raw['latitude'])) {
            $latitude = (float)$raw['latitude'];
        } elseif (isset($raw['location']['lat']) && is_numeric($raw['location']['lat'])) {
            $latitude = (float)$raw['location']['lat'];
        } elseif (isset($raw['lat']) && is_numeric($raw['lat'])) {
            $latitude = (float)$raw['lat'];
        }

        if (isset($raw['longitude']) && is_numeric($raw['longitude'])) {
            $longitude = (float)$raw['longitude'];
        } elseif (isset($raw['location']['lng']) && is_numeric($raw['location']['lng'])) {
            $longitude = (float)$raw['location']['lng'];
        } elseif (isset($raw['lon']) && is_numeric($raw['lon'])) {
            $longitude = (float)$raw['lon'];
        }

        $sourceId = $raw['source_id'] ?? $raw['placeId'] ?? $raw['cid'] ?? $raw['id'] ?? null;

        return [
            'business_name' => substr($businessName, 0, 255),
            'contact_name' => !empty($contactName) ? substr($contactName, 0, 255) : null,
            'category' => !empty($category) ? substr($category, 0, 255) : null,
            'email' => !empty($email) ? substr($email, 0, 255) : null,
            'secondary_email' => !empty($secondaryEmail) ? substr($secondaryEmail, 0, 255) : null,
            'phone' => !empty($phone) ? substr($phone, 0, 50) : null,
            'secondary_phone' => !empty($secondaryPhone) ? substr($secondaryPhone, 0, 50) : null,
            'whatsapp_number' => !empty($whatsapp) ? substr($whatsapp, 0, 50) : null,
            'website' => !empty($website) ? substr($website, 0, 255) : null,
            'address' => !empty($address) ? substr($address, 0, 500) : null,
            'city' => !empty($city) ? substr($city, 0, 100) : null,
            'state' => !empty($state) ? substr($state, 0, 100) : null,
            'country' => !empty($country) ? substr($country, 0, 100) : null,
            'postal_code' => !empty($postalCode) ? substr($postalCode, 0, 30) : null,
            'google_maps_url' => !empty($googleMapsUrl) ? substr($googleMapsUrl, 0, 1000) : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'google_rating' => $googleRating,
            'review_count' => $reviewCount,
            'source_id' => !empty($sourceId) ? substr((string)$sourceId, 0, 255) : null,
            'tags' => is_array($raw['tags'] ?? null) ? $raw['tags'] : [],
            'notes' => $raw['notes'] ?? null,
            'lead_status' => in_array($raw['lead_status'] ?? '', ['new', 'contacted', 'email_generated', 'email_sent', 'replied', 'interested', 'follow_up', 'converted', 'not_interested', 'closed']) ? $raw['lead_status'] : 'new',
            'outreach_status' => 'pending',
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
            if ($domain && strlen($domain) > 4) {
                $cleanDomain = preg_replace('/^www\./', '', $domain);
                $match = Lead::where('company_id', $companyId)
                    ->where('website', 'LIKE', '%' . $cleanDomain . '%')
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

    public function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function cleanPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));
        return strlen($cleaned) >= 7 ? $cleaned : null;
    }

    public function cleanWebsite(?string $website): ?string
    {
        if (!$website) return null;
        $website = trim($website);
        if (!preg_match('~^https?://~i', $website)) {
            $website = 'https://' . $website;
        }
        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    public function determineWebsiteStatus(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'invalid';
        }
        return 'has_website';
    }
}

