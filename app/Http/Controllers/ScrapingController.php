<?php

namespace App\Http\Controllers;

use App\Models\ScrapingJob;
use App\Jobs\ScrapeLeadsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScrapingController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $jobs = ScrapingJob::where('company_id', $companyId)->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $jobs
        ]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'requested_count' => 'required|integer|min:1|max:1000',
            'rating_min' => 'nullable|numeric|min:0|max:5',
            'rating_max' => 'nullable|numeric|min:0|max:5',
            'website_filter' => 'nullable|string|in:all,has_website,no_website',
            'has_email_filter' => 'nullable|boolean',
            'has_phone_filter' => 'nullable|boolean',
            'engine' => 'nullable|string|in:auto,direct,apify',
        ]);

        $user = $request->user();

        // Generate unique job number
        $jobNumber = 'SCRAPE-' . strtoupper(Str::random(8));

        $job = ScrapingJob::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'job_number' => $jobNumber,
            'keyword' => $validated['keyword'],
            'location' => $validated['location'] ?? trim(($validated['city'] ?? '') . ' ' . ($validated['state'] ?? '') . ' ' . ($validated['country'] ?? '')),
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'requested_count' => $validated['requested_count'],
            'rating_min' => $validated['rating_min'] ?? null,
            'rating_max' => $validated['rating_max'] ?? null,
            'website_filter' => $validated['website_filter'] ?? 'all',
            'has_email_filter' => $validated['has_email_filter'] ?? false,
            'has_phone_filter' => $validated['has_phone_filter'] ?? false,
            'status' => 'queued',
        ]);

        // Process job synchronously / immediately so user doesn't wait for offline background queue workers
        try {
            ScrapeLeadsJob::dispatchSync($job->id, $validated);
        } catch (\Exception $e) {
            // Fallback dispatch if sync worker encounters async queue wrapper
            ScrapeLeadsJob::dispatch($job->id, $validated);
        }

        $job->refresh();

        return response()->json([
            'success' => true,
            'message' => "Scraping Job #{$jobNumber} executed! Saved {$job->leads_saved} leads.",
            'data' => $job
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $job = ScrapingJob::where('company_id', $companyId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $job
        ]);
    }
}
