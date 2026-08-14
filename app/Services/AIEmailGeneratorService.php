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
     * Generate structured personalized email content and render full Blade HTML.
     */
    public function generateEmail(Lead $lead, array $options = []): array
    {
        $company = Company::find($lead->company_id);
        $settings = CompanySetting::where('company_id', $lead->company_id)->first();

        $tone = $options['tone'] ?? $company->company_tone ?? 'Professional';
        $ctaTarget = $options['cta'] ?? 'Book a 5-minute Strategy Call';

        $companyName = $company->name ?? 'Enterprise Digital Agency';
        $companyDesc = $company->description ?? '';
        $companyServices = is_array($company->services) ? implode(', ', $company->services) : ($company->services ?? 'Website Development, UI/UX Design, Lead Generation');
        $companyUsp = $company->usp ?? 'Custom digital growth solutions tailored to your business';

        $leadName = $lead->business_name;
        $contactName = $lead->contact_name;
        $leadCategory = $lead->category ?? 'Business';
        $leadCity = $lead->city ?? 'your city';
        $leadWebsiteStatus = $lead->website_status ?? 'no_website';

        $systemPrompt = "You are an elite enterprise B2B sales strategist for '{$companyName}'. Generate structured JSON content for a high-converting cold email proposal. Output ONLY a valid JSON object. Do not include markdown codeblocks or extra text.";

        $userPrompt = "Generate structured cold email content for:
Lead Business: {$leadName}
Contact Person: " . ($contactName ?? "{$leadName} Team") . "
Category: {$leadCategory}
Location: {$leadCity}
Website Status: {$leadWebsiteStatus}

Sender Company: {$companyName}
Company Description: {$companyDesc}
Services Offered: {$companyServices}
USP: {$companyUsp}
Desired CTA: {$ctaTarget}
Tone: {$tone}

Return JSON format ONLY:
{
  \"subject\": \"High-converting personalized subject line\",
  \"greeting\": \"Hi " . ($contactName ? $contactName : "{$leadName} Team") . ",\",
  \"introduction\": \"Short 2-line personalized introduction paragraph explaining why we are reaching out based on their business profile and location.\",
  \"opportunities\": [
    {
      \"title\": \"Web Applications & Website Development\",
      \"description\": \"A modern, high-speed responsive web experience built to capture high-intent leads in {$leadCity}.\"
    },
    {
      \"title\": \"UI/UX & Graphic Design\",
      \"description\": \"High-impact brand visuals and user-friendly design tailored for {$leadCategory} clients.\"
    },
    {
      \"title\": \"Digital Marketing & SEO\",
      \"description\": \"Improve local search visibility and capture high-intent customers automatically.\"
    }
  ],
  \"value_proposition\": \"We specialize in {$companyServices} tailored for businesses like yours.\",
  \"cta\": \"{$ctaTarget}: Would you be open to a quick 5-minute call next Tuesday to explore these opportunities?\"
}";

        $aiProvider = $settings->ai_provider ?? env('AI_PROVIDER', 'openrouter');
        $apiKey = $settings->ai_api_key ?? env('AI_API_KEY');

        $structuredData = null;

        if (!empty($apiKey)) {
            try {
                if ($aiProvider === 'openrouter' || $aiProvider === 'openai') {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model' => $settings->ai_model ?? env('AI_MODEL', 'google/gemini-2.5-flash'),
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.7,
                    ]);

                    if ($response->successful()) {
                        $content = $response->json('choices.0.message.content');
                        $cleanJson = preg_replace('/^```(json)?|```$/m', '', trim($content));
                        $decoded = json_decode($cleanJson, true);
                        if ($decoded && isset($decoded['subject']) && isset($decoded['introduction'])) {
                            $structuredData = $decoded;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('AI Generation call failed, switching to structured fallback generator', ['error' => $e->getMessage()]);
            }
        }

        if (!$structuredData) {
            $structuredData = $this->fallbackStructuredGenerator($lead, $company, $ctaTarget);
        }

        // Render full professional Blade HTML email
        $htmlContent = $this->renderer->renderCorporateEmail(
            $company,
            $lead,
            $structuredData,
            $options['sender_name'] ?? null,
            $options['sender_designation'] ?? null
        );

        return [
            'subject' => $structuredData['subject'],
            'body' => $htmlContent,
            'structured_data' => $structuredData,
        ];
    }

    /**
     * Fallback structured generator.
     */
    protected function fallbackStructuredGenerator(Lead $lead, ?Company $company, string $cta): array
    {
        $companyName = $company->name ?? 'Enterprise Digital Solutions';
        $leadName = $lead->business_name;
        $contactName = $lead->contact_name;
        $city = $lead->city ?? 'your region';
        $category = $lead->category ?? 'business';

        $greeting = !empty($contactName) ? "Hi {$contactName}," : "Hi {$leadName} Team,";

        if ($lead->website_status === 'no_website') {
            $subject = "Digital Growth & Website Development Proposal for {$leadName}";
            $intro = "We came across {$leadName} in {$city} and noticed that your business currently does not have an official website listed on Google. Having a strong digital presence is key to building customer trust and capturing leads.";
            $opportunities = [
                ['title' => 'Web Applications & Website Development', 'description' => "A high-speed, mobile-responsive website layout to establish brand authority in {$city}."],
                ['title' => 'UI/UX & Brand Identity Design', 'description' => "Professional visuals and logo branding to highlight your {$category} services."],
                ['title' => 'CRM & Lead Automation', 'description' => 'Automate customer inquiry responses and capture lead details instantly.']
            ];
        } else {
            $subject = "Expansion & Digital Automation Strategy for {$leadName}";
            $intro = "We came across {$leadName} and were impressed by your work as a leading {$category} business in {$city}. We wanted to reach out regarding optimizing your digital conversion channels.";
            $opportunities = [
                ['title' => 'Web Performance & Modern UX Redesign', 'description' => 'Upgrade your digital interface for maximum speed, mobile conversion, and usability.'],
                ['title' => 'Digital Marketing & SEO Capture', 'description' => "Rank higher on Google searches across {$city} to outpace local competitors."],
                ['title' => 'Lead Generation & CRM Workflows', 'description' => 'Streamline customer inquiries into an automated sales pipeline.']
            ];
        }

        $servicesList = is_array($company->services) ? implode(', ', array_slice($company->services, 0, 5)) : 'Website Development, UI/UX Design, Lead Generation';

        return [
            'subject' => $subject,
            'greeting' => $greeting,
            'introduction' => $intro,
            'opportunities' => $opportunities,
            'value_proposition' => "At {$companyName}, we specialize in {$servicesList} designed specifically to deliver measurable growth for businesses like yours.",
            'cta' => "Would you be open to a quick 5-minute call next Tuesday to discuss how these updates can boost your online presence?",
        ];
    }
}
