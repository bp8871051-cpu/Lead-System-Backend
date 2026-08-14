<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\ActivityLog;
use App\Services\LeadNormalizerService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    protected LeadNormalizerService $normalizer;

    public function __construct(LeadNormalizerService $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = Lead::where('company_id', $companyId);

        // Server-side search across multiple fields
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'LIKE', "%{$search}%")
                  ->orWhere('contact_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%");
            });
        }

        // Specific Filters
        if ($websiteStatus = $request->input('website_status')) {
            $query->where('website_status', $websiteStatus);
        }

        if ($hasWebsite = $request->input('has_website')) {
            if ($hasWebsite === 'yes') {
                $query->where('website_status', 'has_website');
            } elseif ($hasWebsite === 'no') {
                $query->where('website_status', 'no_website');
            }
        }

        if ($emailStatus = $request->input('email_status')) {
            $query->where('email_status', $emailStatus);
        }

        if ($hasEmail = $request->input('has_email')) {
            if ($hasEmail === 'yes') {
                $query->whereNotNull('email')->where('email', '!=', '');
            } elseif ($hasEmail === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('email')->orWhere('email', '');
                });
            }
        }

        if ($hasPhone = $request->input('has_phone')) {
            if ($hasPhone === 'yes') {
                $query->whereNotNull('phone')->where('phone', '!=', '');
            } elseif ($hasPhone === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('phone')->orWhere('phone', '');
                });
            }
        }

        if ($leadStatus = $request->input('lead_status')) {
            $query->where('lead_status', $leadStatus);
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        if ($category = $request->input('category')) {
            $query->where('category', 'LIKE', "%{$category}%");
        }

        if ($city = $request->input('city')) {
            $query->where('city', 'LIKE', "%{$city}%");
        }

        if ($state = $request->input('state')) {
            $query->where('state', 'LIKE', "%{$state}%");
        }

        if ($ratingMin = $request->input('rating_min')) {
            $query->where('google_rating', '>=', (float)$ratingMin);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $allowedSorts = ['business_name', 'google_rating', 'review_count', 'city', 'created_at', 'lead_status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDir) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = (int)$request->input('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $leads = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $leads
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'google_rating' => 'nullable|numeric|min:0|max:5',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $res = $this->normalizer->processAndSave($validated, $companyId, 'manual');

        return response()->json([
            'success' => true,
            'message' => $res['is_duplicate'] ? 'Lead data merged into existing record' : 'Lead created successfully',
            'data' => $res['lead']
        ], $res['is_duplicate'] ? 200 : 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->with(['notes.user', 'activityLogs.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $lead
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'business_name' => 'sometimes|required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'website_status' => 'nullable|in:has_website,no_website,invalid,unreachable,unknown',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'google_rating' => 'nullable|numeric|min:0|max:5',
            'lead_status' => 'nullable|string',
            'email_status' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        if (isset($validated['email'])) {
            $validated['email_status'] = !empty($validated['email']) ? 'available' : 'missing';
        }
        if (isset($validated['phone'])) {
            $validated['phone_status'] = !empty($validated['phone']) ? 'available' : 'missing';
        }

        $lead->update($validated);

        ActivityLog::create([
            'company_id' => $companyId,
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'description' => "Lead profile updated",
            'action_type' => 'lead_updated',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
            'data' => $lead
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $lead->delete(); // Soft delete

        ActivityLog::create([
            'company_id' => $companyId,
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'description' => "Lead moved to trash",
            'action_type' => 'lead_deleted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead moved to trash successfully'
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'integer',
        ]);

        $companyId = $request->user()->company_id;
        $count = Lead::where('company_id', $companyId)
            ->whereIn('id', $request->lead_ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} leads moved to trash"
        ]);
    }

    public function trash(Request $request)
    {
        $companyId = $request->user()->company_id;
        $trashed = Lead::onlyTrashed()
            ->where('company_id', $companyId)
            ->latest('deleted_at')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $trashed
        ]);
    }

    public function restore(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $lead = Lead::onlyTrashed()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $lead->restore();

        return response()->json([
            'success' => true,
            'message' => 'Lead restored successfully',
            'data' => $lead
        ]);
    }

    public function forceDelete(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $lead = Lead::onlyTrashed()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $lead->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Lead permanently deleted'
        ]);
    }

    public function addNote(Request $request, $id)
    {
        $request->validate(['note' => 'required|string']);

        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'note' => $request->note,
        ]);

        ActivityLog::create([
            'company_id' => $companyId,
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'description' => "Note added: " . \Illuminate\Support\Str::limit($request->note, 40),
            'action_type' => 'note_added',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully',
            'data' => $note->load('user')
        ]);
    }

    public function enrichEmail(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->findOrFail($id);

        $finder = new \App\Services\DomainEmailFinderService();
        $foundEmail = $finder->findEmailForLead($lead->business_name, $lead->website, $lead->city);

        if ($foundEmail) {
            $lead->email = $foundEmail;
            $lead->email_status = 'available';
            $lead->save();

            return response()->json([
                'success' => true,
                'message' => "Real Email ID discovered & saved: {$foundEmail}",
                'data' => $lead
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "No public email address found on website/web search for {$lead->business_name}. You can enter it manually."
        ], 404);
    }

    public function bulkEnrichEmails(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'integer',
        ]);

        $companyId = $request->user()->company_id;
        $leads = Lead::where('company_id', $companyId)->whereIn('id', $request->lead_ids)->get();

        $finder = new \App\Services\DomainEmailFinderService();
        $foundCount = 0;

        foreach ($leads as $lead) {
            $foundEmail = $finder->findEmailForLead($lead->business_name, $lead->website, $lead->city);
            if ($foundEmail) {
                $lead->email = $foundEmail;
                $lead->email_status = 'available';
                $lead->save();
                $foundCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Domain Web Scraper extracted real emails for {$foundCount} leads!",
            'data' => ['found_count' => $foundCount]
        ]);
    }
}
