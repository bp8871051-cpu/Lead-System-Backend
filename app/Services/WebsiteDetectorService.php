<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WebsiteDetectorService
{
    /**
     * Inspect HTTP response for a website URL and determine status.
     * Returns: 'has_website', 'no_website', 'invalid', 'unreachable'
     */
    public function inspect(?string $url): string
    {
        if (empty($url)) {
            return 'no_website';
        }

        $url = trim($url);
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'invalid';
        }

        try {
            $response = Http::timeout(5)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) LeadSystemInspector/1.0')
                ->head($url);

            if ($response->successful() || $response->redirect()) {
                return 'has_website';
            }

            // Retry with GET if HEAD fails
            $getResp = Http::timeout(5)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) LeadSystemInspector/1.0')
                ->get($url);

            return ($getResp->successful() || $getResp->redirect()) ? 'has_website' : 'unreachable';
        } catch (\Exception $e) {
            return 'unreachable';
        }
    }
}
