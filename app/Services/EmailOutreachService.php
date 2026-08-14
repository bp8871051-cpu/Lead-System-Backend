<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\EmailLog;
use App\Models\SuppressionList;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailOutreachService
{
    protected BrevoService $brevoService;

    public function __construct(BrevoService $brevoService)
    {
        $this->brevoService = $brevoService;
    }

    /**
     * Send email to lead with suppression list check and status updates.
     */
    public function sendLeadEmail(Lead $lead, string $subject, string $body, ?int $campaignId = null, string $provider = 'brevo'): array
    {
        $recipientEmail = $lead->email;
        if (empty($recipientEmail)) {
            return [
                'success' => false,
                'error' => 'Lead has no primary email address',
            ];
        }

        // Check Suppression List
        $suppressed = SuppressionList::where('company_id', $lead->company_id)
            ->where('email', strtolower($recipientEmail))
            ->exists();

        if ($suppressed) {
            EmailLog::create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'campaign_id' => $campaignId,
                'subject' => $subject,
                'provider' => $provider,
                'sender_email' => 'suppressed@system',
                'recipient_email' => $recipientEmail,
                'status' => 'failed',
                'error_message' => 'Email is on Company Suppression List',
            ]);

            return [
                'success' => false,
                'error' => 'Recipient is suppressed from outreach',
            ];
        }

        $company = Company::find($lead->company_id);
        $settings = CompanySetting::where('company_id', $lead->company_id)->first();

        $senderName = $settings->smtp_from_name ?? $company->default_sender_name ?? env('MAIL_FROM_NAME', 'Lead System CRM');
        $senderEmail = $settings->smtp_from_email ?? $company->default_sender_email ?? env('MAIL_FROM_ADDRESS', 'outreach@leadsystem.com');

        $brevoApiKey = $settings->brevo_api_key ?? env('BREVO_API_KEY');
        $smtpUsername = $settings->smtp_username ?? env('MAIL_USERNAME');
        $smtpPassword = $settings->smtp_password ?? env('MAIL_PASSWORD');
        $smtpHost = $settings->smtp_host ?? env('MAIL_HOST', 'smtp.brevo.com');
        $smtpPort = $settings->smtp_port ?? env('MAIL_PORT', 587);
        $smtpEncryption = $settings->smtp_encryption ?? env('MAIL_ENCRYPTION', 'tls');

        try {
            $messageId = null;

            // Option 1: Brevo API Dispatch
            if ($provider === 'brevo' && !empty($brevoApiKey) && !str_contains($brevoApiKey, '••••')) {
                $result = $this->brevoService->sendEmail(
                    $lead->company_id,
                    $recipientEmail,
                    $lead->contact_name ?? $lead->business_name,
                    $subject,
                    $body,
                    $senderEmail,
                    $senderName
                );
                $messageId = $result['message_id'];
            }
            // Option 2: Configured SMTP Server Dispatch
            elseif (!empty($smtpUsername) && !empty($smtpPassword) && !str_contains($smtpPassword, '••••')) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $smtpHost,
                    'mail.mailers.smtp.port' => (int)$smtpPort,
                    'mail.mailers.smtp.encryption' => $smtpEncryption,
                    'mail.mailers.smtp.username' => $smtpUsername,
                    'mail.mailers.smtp.password' => $smtpPassword,
                    'mail.from.address' => $senderEmail,
                    'mail.from.name' => $senderName,
                ]);

                Mail::html($body, function ($message) use ($recipientEmail, $subject, $senderEmail, $senderName) {
                    $message->to($recipientEmail)
                            ->from($senderEmail, $senderName)
                            ->subject($subject);
                });

                $messageId = 'smtp-' . uniqid();
            }
            // Option 3: Fallback check if Brevo Key is set in .env or Settings
            elseif (!empty($brevoApiKey) && !str_contains($brevoApiKey, '••••')) {
                $result = $this->brevoService->sendEmail(
                    $lead->company_id,
                    $recipientEmail,
                    $lead->contact_name ?? $lead->business_name,
                    $subject,
                    $body,
                    $senderEmail,
                    $senderName
                );
                $messageId = $result['message_id'];
            }
            else {
                throw new \Exception("Email delivery failed: Neither Brevo API key nor SMTP Username/Password are configured in Settings. Please add your SMTP or Brevo credentials in Settings to send real emails to inboxes.");
            }

            $leadId = ($lead->exists && !empty($lead->id)) ? $lead->id : null;

            // Log email in DB
            $log = EmailLog::create([
                'company_id' => $lead->company_id,
                'lead_id' => $leadId,
                'campaign_id' => $campaignId,
                'subject' => $subject,
                'provider' => $provider,
                'sender_email' => $senderEmail,
                'recipient_email' => $recipientEmail,
                'status' => 'sent',
                'message_id' => $messageId,
                'sent_at' => now(),
            ]);

            // Update lead status if lead exists in DB
            if ($lead->exists && !empty($lead->id)) {
                $lead->lead_status = 'email_sent';
                $lead->outreach_status = 'sent';
                $lead->save();

                // Activity Log
                ActivityLog::create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $lead->id,
                    'description' => "Outreach email sent: '{$subject}'",
                    'action_type' => 'email_sent',
                    'meta' => ['message_id' => $messageId, 'provider' => $provider]
                ]);
            }

            return [
                'success' => true,
                'message' => 'Real email dispatched successfully to recipient inbox!',
                'message_id' => $messageId,
                'log_id' => $log->id,
            ];
        } catch (\Exception $e) {
            Log::error('Email dispatch failed', ['lead_id' => $lead->id ?? null, 'error' => $e->getMessage()]);

            $leadId = ($lead->exists && !empty($lead->id)) ? $lead->id : null;

            EmailLog::create([
                'company_id' => $lead->company_id,
                'lead_id' => $leadId,
                'campaign_id' => $campaignId,
                'subject' => $subject,
                'provider' => $provider,
                'sender_email' => $senderEmail,
                'recipient_email' => $recipientEmail,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($lead->exists && !empty($lead->id)) {
                $lead->outreach_status = 'failed';
                $lead->save();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
