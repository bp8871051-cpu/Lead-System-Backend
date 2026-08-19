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
     * Generate structured personalized email content and render the exact preserved Blade HTML email.
     */
    public function generateEmail(Lead $lead, array $options = []): array
    {
        $company = Company::find($lead->company_id);
        $settings = CompanySetting::where('company_id', $lead->company_id)->first();

        $tone = $options['tone'] ?? $company->company_tone ?? 'Professional';
        $ctaTarget = $options['cta'] ?? 'Would you be open to a quick 5-minute call next Tuesday to discuss how these updates can boost your online presence?';

        $companyName = !empty($company->name) ? $company->name : 'BLUEBOXX.DA PRIVATE LIMITED';
        $companyDesc = $company->description ?? 'Premier Digital Agency & Software Engineering Firm specializing in Web Development, Custom CRM software, and AI Workflow Automation.';
        $companyServices = 'Website Development, Custom CRM Software, AI Automation, Digital Marketing';
        $companyUsp = 'Custom digital growth solutions tailored to deliver measurable growth for businesses like yours.';

        $leadName = $lead->business_name ?? 'Business Partner';
        $contactName = $lead->contact_name;
        $leadCategory = $lead->category ?? 'Business';
        $leadCity = $lead->city ?? 'your city';
        $leadWebsiteStatus = $lead->website_status ?? 'no_website';

        $systemPrompt = "You are an AI email generation engine for '{$companyName}'.
Your job is to dynamically analyze the lead information and generate structured JSON content for the exact reference email template.
CRITICAL RULES:
- Subject line must be professional, personalized, and specific to {$leadName}.
- Always generate EXACTLY 3 numbered service recommendations tailored to {$leadName} in {$leadCity} ({$leadCategory}).
- Do NOT invent facts, revenues, employee counts, or fake reviews.
- CTA must request a quick 5-minute call next Tuesday.
Output ONLY a valid JSON object without markdown codeblocks or extra text.";

        $userPrompt = "Generate structured cold email content for:
Lead Business: {$leadName}
Contact Person: " . ($contactName ?? "{$leadName} Team") . "
Category: {$leadCategory}
Location: {$leadCity}
Website Status: {$leadWebsiteStatus}

Sender Company: {$companyName}
Company Description: {$companyDesc}
Services Offered: {$companyServices}
Tone: {$tone}

Return JSON format ONLY:
{
  \"subject\": \"Digital Growth & Website Development Proposal for {$leadName}\",
  \"greeting\": \"Hi " . ($contactName ? $contactName : "{$leadName} Team") . ",\",
  \"introduction\": \"Personalized 2-line opening paragraph explaining why we are reaching out based on {$leadName}'s business profile and location in {$leadCity}.\",
  \"opportunities\": [
    {
      \"title\": \"Web Applications & Website Development\",
      \"description\": \"High-speed, mobile-responsive website to establish brand authority in {$leadCity}.\"
    },
    {
      \"title\": \"UI/UX & Brand Identity Design\",
      \"description\": \"Professional visual and logo branding to highlight your {$leadCategory} services.\"
    },
    {
      \"title\": \"CRM & Lead Automation\",
      \"description\": \"Automate customer inquiry responses and capture lead details instantly.\"
    }
  ],
  \"value_proposition\": \"At {$companyName}, we specialize in Website Development, Custom CRM Software, AI Automation, Digital Marketing designed specifically to deliver measurable growth for businesses like yours.\",
  \"cta\": \"{$ctaTarget}\"
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
                        if ($decoded && isset($decoded['subject']) && isset($decoded['introduction']) && !empty($decoded['opportunities'])) {
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

        // Render full exact reference Blade HTML email
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
            $options['sender_designation'] ?? 'BLUEBOXX.DA PRIVATE LIMITED'
        );

        return [
            'subject' => $structuredData['subject'],
            'body' => $htmlContent,
            'structured_data' => $structuredData,
        ];
    }

    /**
     * Fallback structured generator preserving exact reference content structure.
     */
    protected function fallbackStructuredGenerator(Lead $lead, ?Company $company, string $cta): array
    {
        $companyName = !empty($company->name) ? $company->name : 'BLUEBOXX.DA PRIVATE LIMITED';
        $leadName = $lead->business_name ?? 'Business Partner';
        $contactName = $lead->contact_name;
        $city = $lead->city ?? 'your city';
        $category = $lead->category ?? 'business';

        $greeting = !empty($contactName) ? "Hi {$contactName}," : "Hi {$leadName} Team,";

        if ($lead->website_status === 'no_website') {
            $subject = "Digital Growth & Website Development Proposal for {$leadName}";
            $intro = "We came across {$leadName} in {$city} and noticed that your business currently does not have an official website listed on Google. Having a strong digital presence is key to building customer trust and capturing leads.";
            $opportunities = [
                ['title' => 'Web Applications & Website Development', 'description' => "High-speed, mobile-responsive website to establish brand authority in {$city}."],
                ['title' => 'UI/UX & Brand Identity Design', 'description' => "Professional visual and logo branding to highlight your {$category} services."],
                ['title' => 'CRM & Lead Automation', 'description' => 'Automate customer inquiry responses and capture lead details instantly.']
            ];
        } else {
            $subject = "Expansion & Digital Automation Strategy for {$leadName}";
            $intro = "We came across {$leadName} and were impressed by your work as a leading {$category} business in {$city}. We wanted to reach out regarding optimizing your digital conversion channels.";
            $opportunities = [
                ['title' => 'Web Applications & Website Development', 'description' => "High-speed, mobile-responsive web experience built to capture high-intent customers in {$city}."],
                ['title' => 'UI/UX & Brand Identity Design', 'description' => "High-impact brand visuals and user-friendly interface tailored for {$category} clients."],
                ['title' => 'CRM & Lead Automation', 'description' => 'Automate customer inquiry responses and streamline your sales pipeline.']
            ];
        }

        return [
            'subject' => $subject,
            'greeting' => $greeting,
            'introduction' => $intro,
            'opportunities' => $opportunities,
            'value_proposition' => "At {$companyName}, we specialize in Website Development, Custom CRM Software, AI Automation, Digital Marketing designed specifically to deliver measurable growth for businesses like yours.",
            'cta' => "Would you be open to a quick 5-minute call next Tuesday to discuss how these updates can boost your online presence?",
        ];
    }
}
