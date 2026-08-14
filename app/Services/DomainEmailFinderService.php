<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainEmailFinderService
{
    /**
     * Search and extract real email address for a business using its website and web search.
     */
    public function findEmailForLead(string $businessName, ?string $website, ?string $city = null): ?string
    {
        // 1. If website is available, scrape homepage and /contact pages
        if (!empty($website) && filter_var($website, FILTER_VALIDATE_URL)) {
            $email = $this->scrapeWebsiteEmails($website);
            if ($email) {
                return $email;
            }
        }

        // 2. Query DuckDuckGo HTML web search for business contact email
        try {
            $searchTerm = urlencode("\"{$businessName}\" " . ($city ? "\"{$city}\"" : "") . " email OR contact");
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout(8)->get("https://html.duckduckgo.com/html/?q={$searchTerm}");

            if ($response->successful()) {
                $html = $response->body();
                $foundEmails = $this->extractEmailsFromHtml($html);
                if (!empty($foundEmails)) {
                    return $foundEmails[0];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Web search email finder warning for {$businessName}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Scrape website homepage, /contact, and /about pages for email addresses.
     */
    public function scrapeWebsiteEmails(string $url): ?string
    {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) return null;

        $baseUrl = "https://" . preg_replace('/^www\./', '', $domain);
        $pagesToTry = [
            $url,
            $baseUrl . '/contact',
            $baseUrl . '/contact-us',
            $baseUrl . '/about',
            $baseUrl . '/about-us',
        ];

        $allFoundEmails = [];

        foreach (array_unique($pagesToTry) as $pageUrl) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'
                ])->timeout(6)->get($pageUrl);

                if ($response->successful()) {
                    $emails = $this->extractEmailsFromHtml($response->body(), $domain);
                    $allFoundEmails = array_merge($allFoundEmails, $emails);
                    if (!empty($allFoundEmails)) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Continue to next page
            }
        }

        $allFoundEmails = array_unique($allFoundEmails);

        if (empty($allFoundEmails)) {
            return null;
        }

        // Prioritize official business handles (info@, contact@, sales@, etc.)
        foreach ($allFoundEmails as $email) {
            if (preg_match('/^(info|contact|sales|hello|support|admin|office|enquiry|inquiry|help)@/i', $email)) {
                return strtolower($email);
            }
        }

        return strtolower($allFoundEmails[0]);
    }

    /**
     * Extract valid email addresses from HTML content using regex.
     */
    protected function extractEmailsFromHtml(string $html, ?string $targetDomain = null): array
    {
        // Regex to match email pattern
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}/i', $html, $matches);

        if (empty($matches[0])) {
            return [];
        }

        $ignoredDomains = ['sentry.io', 'wixpress.com', 'schema.org', 'bootstrap.com', 'jquery.com', 'example.com', 'domain.com', 'gravatar.com', 'facebook.com', 'twitter.com', 'instagram.com'];
        $ignoredExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js'];

        $validEmails = [];

        foreach ($matches[0] as $rawEmail) {
            $email = strtolower(trim($rawEmail));

            // Check file extension noise like image@2x.png
            $ext = strtolower(pathinfo($email, PATHINFO_EXTENSION));
            if (in_array($ext, $ignoredExtensions)) continue;

            $parts = explode('@', $email);
            if (count($parts) !== 2) continue;

            $emailDomain = $parts[1];

            // Filter out junk/framework domains
            $isIgnored = false;
            foreach ($ignoredDomains as $ig) {
                if (str_contains($emailDomain, $ig)) {
                    $isIgnored = true;
                    break;
                }
            }
            if ($isIgnored) continue;

            // Filter out invalid email characters
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            $validEmails[] = $email;
        }

        $validEmails = array_unique($validEmails);

        // If targetDomain is given, prioritize emails matching target domain
        if ($targetDomain) {
            $domainMatches = array_filter($validEmails, function ($e) use ($targetDomain) {
                return str_contains($e, $targetDomain);
            });
            if (!empty($domainMatches)) {
                return array_values($domainMatches);
            }
        }

        return array_values($validEmails);
    }
}
