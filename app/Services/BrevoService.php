<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    /**
     * Send transactional email using Brevo API v3.
     */
    public function sendEmail(int $companyId, string $toEmail, string $toName, string $subject, string $htmlContent, ?string $fromEmail = null, ?string $fromName = null): array
    {
        $settings = CompanySetting::where('company_id', $companyId)->first();
        $apiKey = $settings->brevo_api_key ?? env('BREVO_API_KEY');

        if (empty($apiKey)) {
            throw new \Exception('Brevo API key is not configured in Admin Settings or environment.');
        }

        $senderEmail = $fromEmail ?? $settings->smtp_from_email ?? env('MAIL_FROM_ADDRESS', 'outreach@leadsystem.com');
        $senderName = $fromName ?? $settings->smtp_from_name ?? env('MAIL_FROM_NAME', 'Lead System Outreach');

        $payload = [
            'sender' => [
                'name' => $senderName,
                'email' => $senderEmail,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => $toName ?: $toEmail,
                ]
            ],
            'subject' => $subject,
            'htmlContent' => nl2br(e($htmlContent)),
        ];

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(15)->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (!$response->successful()) {
            Log::error('Brevo API dispatch failed', ['body' => $response->body()]);
            throw new \Exception('Brevo API error: ' . ($response->json('message') ?? $response->body()));
        }

        return [
            'success' => true,
            'message_id' => $response->json('messageId') ?? 'brevo-' . uniqid(),
        ];
    }
}
