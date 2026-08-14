<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyService
{
    /**
     * Start a Google Maps / Places scraping task via Apify actor.
     */
    public function startScraping(int $companyId, array $criteria): array
    {
        $settings = CompanySetting::where('company_id', $companyId)->first();

        $apiToken = $settings->apify_api_token ?? config('services.apify.token', env('APIFY_API_TOKEN'));
        $actorId = $settings->apify_actor_id ?? config('services.apify.actor_id', env('APIFY_ACTOR_ID', 'compass/google-maps-extractor'));

        if (empty($apiToken)) {
            throw new \Exception('Apify API token is not configured in Admin Settings or .env file.');
        }

        // Prepare Apify actor payload
        $searchQuery = trim(($criteria['keyword'] ?? '') . ' in ' . ($criteria['location'] ?? $criteria['city'] ?? ''));
        $maxItems = (int)($criteria['requested_count'] ?? 50);

        $payload = [
            'searchStringsArray' => [$searchQuery],
            'maxCrawledPlacesPerSearch' => $maxItems,
            'language' => 'en',
            'scrapeReviewerName' => false,
            'skipClosedPlaces' => true,
        ];

        // Trigger Apify Actor run
        $url = "https://api.apify.com/v2/acts/" . urlencode($actorId) . "/runs?token=" . urlencode($apiToken);

        $response = Http::timeout(30)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Apify API error', ['body' => $response->body()]);
            throw new \Exception('Failed to launch Apify scraper: ' . ($response->json('error.message') ?? $response->body()));
        }

        $runData = $response->json('data');

        return [
            'run_id' => $runData['id'],
            'dataset_id' => $runData['defaultDatasetId'],
            'status' => $runData['status'],
        ];
    }

    /**
     * Fetch dataset results from a completed Apify run.
     */
    public function getDatasetItems(string $datasetId, ?string $apiToken = null): array
    {
        if (empty($apiToken)) {
            $apiToken = env('APIFY_API_TOKEN');
        }

        $url = "https://api.apify.com/v2/datasets/{$datasetId}/items?token={$apiToken}&format=json";

        $response = Http::timeout(60)->get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch dataset items from Apify: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Check Apify run status.
     */
    public function checkRunStatus(string $runId, ?string $apiToken = null): array
    {
        if (empty($apiToken)) {
            $apiToken = env('APIFY_API_TOKEN');
        }

        $url = "https://api.apify.com/v2/actor-runs/{$runId}?token={$apiToken}";

        $response = Http::timeout(15)->get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to check Apify run status');
        }

        $data = $response->json('data');

        return [
            'status' => $data['status'], // READY, RUNNING, SUCCEEDED, FAILED, TIMED-OUT
            'dataset_id' => $data['defaultDatasetId'] ?? null,
            'finished_at' => $data['finishedAt'] ?? null,
        ];
    }
}
