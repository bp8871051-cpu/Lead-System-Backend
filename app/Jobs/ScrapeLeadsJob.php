<?php

namespace App\Jobs;

use App\Models\ScrapingJob;
use App\Models\ScrapingResult;
use App\Models\CompanySetting;
use App\Services\ApifyService;
use App\Services\DirectScraperService;
use App\Services\LeadNormalizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    protected int $scrapingJobId;
    protected array $criteria;

    public function __construct(int $scrapingJobId, array $criteria)
    {
        $this->scrapingJobId = $scrapingJobId;
        $this->criteria = $criteria;
    }

    public function handle(ApifyService $apifyService, DirectScraperService $directScraper, LeadNormalizerService $normalizer): void
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', 180);

        $jobRecord = ScrapingJob::find($this->scrapingJobId);
        if (!$jobRecord) return;

        $jobRecord->update(['status' => 'running', 'started_at' => now()]);

        $engine = $this->criteria['engine'] ?? 'direct';
        $companyId = $jobRecord->company_id;
        $settings = CompanySetting::where('company_id', $companyId)->first();
        $apifyToken = $settings->apify_api_token ?? config('services.apify.token', env('APIFY_API_TOKEN'));

        $items = [];
        $sourceType = 'google_maps';

        try {
            if ($engine === 'apify' && !empty($apifyToken) && !str_contains($apifyToken, 'YOUR_APIFY') && strlen($apifyToken) > 10) {
                try {
                    $sourceType = 'apify';
                    $runData = $apifyService->startScraping($companyId, $this->criteria);
                    $jobRecord->update([
                        'apify_run_id' => $runData['run_id'],
                        'status' => 'processing',
                    ]);

                    $completed = false;
                    $datasetId = $runData['dataset_id'];
                    $maxRetries = 15; // 15 x 3s = 45s

                    for ($i = 0; $i < $maxRetries; $i++) {
                        sleep(3);
                        $statusCheck = $apifyService->checkRunStatus($runData['run_id'], $apifyToken);

                        if ($statusCheck['status'] === 'SUCCEEDED') {
                            $completed = true;
                            $datasetId = $statusCheck['dataset_id'] ?? $datasetId;
                            break;
                        }

                        if (in_array($statusCheck['status'], ['FAILED', 'ABORTED', 'TIMED-OUT'])) {
                            throw new \Exception("Apify actor status: " . $statusCheck['status']);
                        }
                    }

                    $items = $apifyService->getDatasetItems($datasetId, $apifyToken);
                } catch (\Exception $apifyEx) {
                    Log::warning("Apify Scraper unavailable or token invalid, falling back to Direct Scraper Engine: " . $apifyEx->getMessage());
                    $sourceType = 'google_maps';
                    $items = $directScraper->scrape($this->criteria);
                }
            } else {
                // High-speed Direct Web Scraper Engine (Photon + Nominatim + Web Directory)
                $sourceType = 'google_maps';
                $items = $directScraper->scrape($this->criteria);
            }

            $jobRecord->update(['leads_found' => count($items)]);

            // Save raw result
            ScrapingResult::create([
                'scraping_job_id' => $jobRecord->id,
                'raw_data' => $items,
            ]);

            // Normalize & Store Leads
            $saved = 0;
            $duplicates = 0;
            $invalid = 0;

            foreach ($items as $item) {
                if (empty($item['title']) && empty($item['business_name']) && empty($item['name']) && empty($item['placeName'])) {
                    $invalid++;
                    continue;
                }

                $placeId = $item['placeId'] ?? ($item['id'] ?? null);
                $res = $normalizer->processAndSave($item, $companyId, $sourceType, $placeId);

                if (!empty($res['is_duplicate'])) {
                    $duplicates++;
                } else {
                    $saved++;
                }
            }

            $jobRecord->update([
                'status' => 'completed',
                'leads_saved' => $saved,
                'duplicates_found' => $duplicates,
                'invalid_count' => $invalid,
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("ScrapeLeadsJob failed: " . $e->getMessage(), ['job_id' => $this->scrapingJobId]);
            $jobRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
