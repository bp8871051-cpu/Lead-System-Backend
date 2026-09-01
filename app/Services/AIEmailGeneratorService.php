<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIEmailGeneratorService
{
    protected EmailTemplateRendererService $renderer;

    public function __construct(EmailTemplateRendererService $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Generate structured personalized email content with dynamic spintax anti-fingerprinting.
     */
    public function generateEmail(Lead $lead, array $options = []): array
    {
        $company = Company::find($lead->company_id);
        $settings = CompanySetting::where('company_id', $lead->company_id)->first();

        $tone = $options['tone'] ?? $company->company_tone ?? 'Professional';
        $ctaTarget = $options['cta'] ?? null;

        $companyName = !empty($company->name) ? $company->name : 'BLUEBOXX.DA PRIVATE LIMITED';
        $leadName = $lead->business_name ?? 'Business Partner';
        $contactName = $lead->contact_name;
        $leadCategory = $lead->category ?? 'Business';
        $leadCity = $lead->city ?? 'your city';
        $leadWebsiteStatus = $lead->website_status ?? 'no_website';

        $aiProvider = $settings->ai_provider ?? env('AI_PROVIDER', 'openai');
        $apiKey = $settings->ai_api_key ?? env('AI_API_KEY');

        $structuredData = null;

        // Try AI if valid key exists
        if (!empty($apiKey) && !str_contains($apiKey, 'YOUR_KEY')) {
            try {
                $endpoint = ($aiProvider === 'openrouter') ? 'https://openrouter.ai/api/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
                $model = ($aiProvider === 'openrouter') ? ($settings->ai_model ?? env('AI_MODEL', 'google/gemini-2.5-flash')) : 'gpt-4o-mini';

                $systemPrompt = "You are a professional B2B outreach consultant. Write a short, highly personalized 1-on-1 email for {$leadName} in {$leadCity}.
CRITICAL ANTI-SPAM DELIVERABILITY RULES:
- Subject must be short (2-5 words), natural, and lowercase/sentence case (e.g. 'quick note for {$leadName}' or '{$leadName} / {$leadCity}').
- Zero spam keywords: never use 'Cold outreach', 'Free', 'Guaranteed', 'Proposal', '100%'.
- Keep email brief (under 120 words total), conversational, and polite.
- Output ONLY valid JSON: {\"subject\": \"...\", \"greeting\": \"...\", \"introduction\": \"...\", \"opportunities\": [{\"title\": \"...\", \"description\": \"...\"}], \"value_proposition\": \"...\", \"cta\": \"...\"}";

                $userPrompt = "Generate unique 1-to-1 inquiry for {$leadName} ({$leadCategory} in {$leadCity}). Website status: {$leadWebsiteStatus}. Sender: {$companyName}.";

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . trim($apiKey),
                    'Content-Type' => 'application/json',
                ])->timeout(8)->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.85,
                ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    $cleanJson = preg_replace('/^```(json)?|```$/m', '', trim($content));
                    $decoded = json_decode($cleanJson, true);
                    if ($decoded && isset($decoded['subject']) && isset($decoded['introduction']) && !empty($decoded['opportunities'])) {
                        $structuredData = $decoded;
                    }
                }
            } catch (\Exception $e) {
                Log::info('AI Provider unavailable, using Dynamic Human Spintax Engine: ' . $e->getMessage());
            }
        }

        // Always fallback to Dynamic Anti-Fingerprint Spintax Generator
        if (!$structuredData) {
            $structuredData = $this->generateDynamicSpintaxEmail($lead, $company, $ctaTarget);
        }

        // Render clean, modern Blade HTML email
        $htmlContent = $this->renderer->renderCorporateEmail(
            $company ?? new Company([
                'name' => 'BLUEBOXX.DA PRIVATE LIMITED',
                'address' => 'BLUEBOXX.DA Tower, Tech Park Road',
                'website' => 'https://blueboxxda.com',
                'email' => 'info.blueboxx@gmail.com',
                'phone' => '+91 90235 12853',
                'alternate_phone' => '+91 63525 24266',
            ]),
            $lead,
            $structuredData,
            $options['sender_name'] ?? 'Sumedh Agrawal',
            $options['sender_designation'] ?? 'Business Development'
        );

        return [
            'subject' => $structuredData['subject'],
            'body' => $htmlContent,
            'structured_data' => $structuredData,
        ];
    }

    /**
     * Dynamic Human Spintax & Variation Generator.
     * Guarantees that every single email has a completely unique fingerprint and natural human phrasing.
     */
    public function generateDynamicSpintaxEmail(Lead $lead, ?Company $company, ?string $customCta = null): array
    {
        $companyName = !empty($company->name) ? $company->name : 'Blueboxx Solutions';
        $leadName = $lead->business_name ?? 'Business Partner';
        $contactName = $lead->contact_name;
        $city = !empty($lead->city) ? $lead->city : 'your area';
        $category = !empty($lead->category) ? $lead->category : 'business';
        $hasNoWebsite = ($lead->website_status === 'no_website' || empty($lead->website));

        // 1. Dynamic Natural Subject Lines (No spam triggers, diverse wording)
        $subjectTemplates = [
            "quick thought for {$leadName}",
            "note regarding {$leadName}",
            "{$leadName} / {$city} inquiry",
            "connecting with {$leadName}",
            "question about {$leadName}",
            "idea for {$leadName} online setup",
            "{$leadName} - digital channels",
            "feedback for {$leadName}",
        ];
        if (!empty($contactName)) {
            $subjectTemplates[] = "note for {$contactName} ({$leadName})";
            $subjectTemplates[] = "quick note for {$contactName}";
        }
        $subject = $subjectTemplates[array_rand($subjectTemplates)];

        // 2. Dynamic Human Greetings
        if (!empty($contactName)) {
            $greetings = ["Hi {$contactName},", "Hello {$contactName},", "Hi {$contactName}, hope all is well.", "Good day {$contactName},"];
        } else {
            $greetings = ["Hi {$leadName} team,", "Hello {$leadName} team,", "Hi there,", "Good day,"];
        }
        $greeting = $greetings[array_rand($greetings)];

        // 3. Dynamic Multi-Angle Introductions
        if ($hasNoWebsite) {
            $introVariations = [
                "I was looking into established {$category} services across {$city} and came across {$leadName}. I noticed that you don't currently have an active website set up to capture incoming customer search traffic.",
                "While researching {$category} businesses in {$city}, I found {$leadName}. Having a dedicated mobile-friendly website makes a significant difference in how local clients discover and trust your services.",
                "I recently came across {$leadName} in {$city} and wanted to reach out briefly. Building a dedicated online presence and direct WhatsApp inquiry channel would help you capture more clients looking for {$category} services.",
                "I hope you're having a productive week. I noticed {$leadName} while exploring local {$category} providers in {$city} and saw an opportunity to help you launch a modern web presence.",
                "Reaching out briefly regarding {$leadName}. Many {$category} businesses in {$city} lose potential customers because their online presence isn't directly searchable on Google Maps and mobile browsers."
            ];
        } else {
            $introVariations = [
                "I was researching reputable {$category} businesses in {$city} and came across {$leadName}. I wanted to share a couple of observations on how your current digital setup could capture more customer inquiries.",
                "Hope your week is going well. I came across {$leadName} in {$city} and was impressed by your local presence. I'm reaching out with a few ideas on improving customer conversion and lead automation.",
                "I came across {$leadName} while looking at leading {$category} providers in {$city}. I wanted to reach out regarding a few quick ways to upgrade your web experience and streamline incoming customer inquiries.",
                "Reaching out with a quick thought for {$leadName}. We've been working with several {$category} companies in {$city} to modernize their client booking and online inquiry systems."
            ];
        }
        $introduction = $introVariations[array_rand($introVariations)];

        // 4. Dynamic Opportunities Pool (Pick 2-3 unique items)
        if ($hasNoWebsite) {
            $allOpps = [
                [
                    'title' => 'Fast Mobile Website',
                    'description' => "A lightweight, modern web page allowing clients in {$city} to easily view your services and pricing."
                ],
                [
                    'title' => 'Instant WhatsApp & Phone Routing',
                    'description' => 'A 1-click inquiry button sending customer requests straight to your phone so you never miss a lead.'
                ],
                [
                    'title' => 'Local Google Maps Optimization',
                    'description' => "Ensuring {$leadName} ranks prominently when customers search for {$category} near {$city}."
                ],
                [
                    'title' => 'Professional Visual Branding',
                    'description' => "Clean logo, company deck, and modern graphics tailored specifically for your {$category} market."
                ]
            ];
        } else {
            $allOpps = [
                [
                    'title' => 'Speed & Mobile Optimization',
                    'description' => "Faster load times and responsive design to prevent potential clients in {$city} from dropping off."
                ],
                [
                    'title' => 'Automated Lead & Inquiry Flow',
                    'description' => 'Automate follow-ups and instantly organize incoming client inquiries into an easy dashboard.'
                ],
                [
                    'title' => 'Modern UI/UX Redesign',
                    'description' => "An updated visual layout designed to convert casual website visitors into confirmed customers."
                ],
                [
                    'title' => 'Local Search Visibility (SEO)',
                    'description' => "Rank at the top of local search results for high-intent {$category} queries in {$city}."
                ]
            ];
        }

        shuffle($allOpps);
        $opportunities = array_slice($allOpps, 0, 3);

        // 5. Dynamic Value Proposition
        $valueProps = [
            "At {$companyName}, we build custom web platforms, automated lead systems, and visual branding tailored for growing businesses.",
            "Our team at {$companyName} helps local companies modernize their digital workflows and consistently capture high-intent customer leads.",
            "At {$companyName}, we specialize in delivering clean web applications, custom CRM automation, and high-converting digital setups."
        ];
        $valueProp = $valueProps[array_rand($valueProps)];

        // 6. Dynamic Conversational CTA
        if (!empty($customCta)) {
            $cta = $customCta;
        } else {
            $ctas = [
                "Would you be open to a brief 5-minute call next week to discuss this?",
                "Are you available for a quick 5-minute chat on Tuesday or Wednesday?",
                "Would it make sense to connect for a quick 5-minute phone call this week?",
                "If you're interested, let me know when might be a convenient time for a quick 5-minute chat."
            ];
            $cta = $ctas[array_rand($ctas)];
        }

        return [
            'subject' => $subject,
            'greeting' => $greeting,
            'introduction' => $introduction,
            'opportunities' => $opportunities,
            'value_proposition' => $valueProp,
            'cta' => $cta,
        ];
    }
}
