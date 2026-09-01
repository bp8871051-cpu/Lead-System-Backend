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
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class EmailOutreachService
{
    /**
     * Send email to lead using Gmail SMTP with STARTTLS (Port 587) with high-deliverability headers and multipart MIME.
     *
     * @param Lead $lead
     * @param string $subject
     * @param string $body
     * @param int|null $campaignId
     * @param string $provider
     * @return array
     */
    public function sendLeadEmail(Lead $lead, string $subject, string $body, ?int $campaignId = null, string $provider = 'smtp'): array
    {
        $recipientEmail = trim($lead->email ?? '');

        // 1. Validate Recipient Email
        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid recipient email: Lead has no valid primary email address',
            ];
        }

        // 2. Check Suppression List
        try {
            $suppressed = SuppressionList::where('company_id', $lead->company_id)
                ->where('email', strtolower($recipientEmail))
                ->exists();

            if ($suppressed) {
                try {
                    EmailLog::create([
                        'company_id' => $lead->company_id,
                        'lead_id' => $lead->id,
                        'campaign_id' => $campaignId,
                        'subject' => $subject,
                        'provider' => 'smtp',
                        'sender_email' => 'suppressed@system',
                        'recipient_email' => $recipientEmail,
                        'status' => 'failed',
                        'error_message' => 'Email is on Company Suppression List',
                    ]);
                } catch (\Throwable $e) {}

                return [
                    'success' => false,
                    'error' => 'Recipient is suppressed from outreach',
                ];
            }
        } catch (\Throwable $e) {
            // DB lookup optional fallback
        }

        $company = null;
        $settings = null;
        try {
            $company = Company::find($lead->company_id);
            $settings = CompanySetting::where('company_id', $lead->company_id)->first();
        } catch (\Throwable $e) {}

        // 3. Resolve Gmail SMTP Configuration
        $smtpHost = $settings->smtp_host ?? config('mail.mailers.smtp.host') ?: env('SMTP_HOST', 'smtp.gmail.com');
        if (empty($smtpHost)) {
            $smtpHost = 'smtp.gmail.com';
        }
        $smtpPort = (int) ($settings->smtp_port ?? config('mail.mailers.smtp.port') ?: env('SMTP_PORT', 587));
        $smtpEncryption = $settings->smtp_encryption ?? config('mail.mailers.smtp.encryption') ?: env('SMTP_ENCRYPTION', 'tls');
        
        $smtpUsername = trim($settings->smtp_username ?? config('mail.mailers.smtp.username') ?: env('SMTP_USER', env('MAIL_USERNAME', '')));
        $smtpPassword = $settings->smtp_password ?? config('mail.mailers.smtp.password') ?: env('SMTP_PASSWORD', env('MAIL_PASSWORD', ''));

        // Handle masked password string from DB
        if (!empty($smtpPassword) && str_contains($smtpPassword, '••••')) {
            $smtpPassword = config('mail.mailers.smtp.password') ?: env('SMTP_PASSWORD', env('MAIL_PASSWORD', ''));
        }

        // Strip any spaces or newlines from 16-character Google App Password
        if (!empty($smtpPassword)) {
            $smtpPassword = str_replace([' ', "\r", "\n", "\t"], '', trim($smtpPassword));
        }

        // For strict SPF/DKIM alignment on Gmail SMTP: From address MUST match authenticated Gmail account
        $senderEmail = !empty($smtpUsername) ? $smtpUsername : ($settings->smtp_from_email ?? env('SMTP_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'info.blueboxx@gmail.com')));
        
        $senderName = $settings->smtp_from_name 
            ?? config('mail.from.name')
            ?? env('SMTP_FROM_NAME', env('MAIL_FROM_NAME', $company->default_sender_name ?? 'Sumedh | Blueboxx'));

        $replyToEmail = $senderEmail;

        // 4. Pre-send Verification for Required Credentials
        if (empty($smtpHost)) {
            return [
                'success' => false,
                'error' => 'Missing SMTP_HOST: Gmail SMTP host is not configured.',
            ];
        }

        if (empty($smtpUsername)) {
            return [
                'success' => false,
                'error' => 'Missing SMTP_USER: Gmail SMTP username/email is required.',
            ];
        }

        if (empty($smtpPassword) || $smtpPassword === 'GOOGLE_APP_PASSWORD') {
            return [
                'success' => false,
                'error' => 'Gmail SMTP authentication failed. Please check the Gmail address and Google App Password. (Missing valid 16-character App Password).',
            ];
        }

        try {
            // Build authenticated Symfony Mailer with STARTTLS (Port 587)
            $isSsl = ($smtpPort === 465);
            $transport = new EsmtpTransport($smtpHost, $smtpPort, $isSsl);
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);

            // Convert raw text into clean semantic HTML if no HTML tags are present
            if (!preg_match('/<[a-z][\s\S]*>/i', $body)) {
                $formattedParagraphs = '';
                $paragraphs = preg_split("/\r\n\r\n|\n\n/", trim($body));
                foreach ($paragraphs as $para) {
                    $cleanPara = nl2br(htmlspecialchars(trim($para)));
                    if (!empty($cleanPara)) {
                        $formattedParagraphs .= "<p style=\"margin: 0 0 14px 0; font-size: 14.5px; line-height: 1.65; color: #1e293b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;\">{$cleanPara}</p>";
                    }
                }
                $body = "<div style=\"max-width: 600px; margin: 0 auto; padding: 12px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14.5px; line-height: 1.65; color: #1e293b;\">{$formattedParagraphs}</div>";
            }

            // Ensure clean CAN-SPAM compliant opt-out footer
            $companyName = $company->name ?? 'BLUEBOXX.DA PRIVATE LIMITED';
            $companyAddress = $company->address ?? '';
            $finalHtmlBody = $this->ensureComplianceFooter($body, $senderEmail, $companyName, $companyAddress);
            
            // Generate clean plain-text alternative (Crucial for spam filter pass / Multipart MIME)
            $plainTextBody = $this->htmlToPlainText($finalHtmlBody);

            // RFC 5322 Message-ID domain resolution
            $senderDomain = substr(strrchr($senderEmail, "@"), 1) ?: 'gmail.com';
            $messageIdToken = bin2hex(random_bytes(12)) . '@' . $senderDomain;

            // Construct Multipart (HTML + Plain-Text) Peer-to-Peer Email message
            $emailMessage = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($senderEmail, $senderName))
                ->to(new \Symfony\Component\Mime\Address($recipientEmail))
                ->replyTo(new \Symfony\Component\Mime\Address($replyToEmail, $senderName))
                ->subject($subject)
                ->html($finalHtmlBody)
                ->text($plainTextBody)
                ->date(new \DateTimeImmutable());

            // Add standard RFC-compliant personal headers (avoiding bulk mailer flags)
            $headers = $emailMessage->getHeaders();
            $headers->addIdHeader('Message-ID', $messageIdToken);
            $headers->addTextHeader('X-Priority', '3'); // Normal priority (standard for human emails)

            // Send directly through Gmail SMTP transport
            $symfonyMailer = new \Symfony\Component\Mailer\Mailer($transport);
            $symfonyMailer->send($emailMessage);

            $messageId = 'gmail-smtp-' . $messageIdToken;
            Log::info('Email dispatched successfully with Inbox Deliverability headers', [
                'recipient' => $recipientEmail,
                'message_id' => $messageId,
            ]);

            $logId = null;
            try {
                $leadId = ($lead->exists && !empty($lead->id)) ? $lead->id : null;

                // Log successful email in DB
                $log = EmailLog::create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $leadId,
                    'campaign_id' => $campaignId,
                    'subject' => $subject,
                    'provider' => 'smtp',
                    'sender_email' => $senderEmail,
                    'recipient_email' => $recipientEmail,
                    'status' => 'sent',
                    'message_id' => $messageId,
                    'sent_at' => now(),
                ]);
                $logId = $log->id;

                // Update lead status if lead exists in DB
                if ($lead->exists && !empty($lead->id)) {
                    $lead->lead_status = 'email_sent';
                    $lead->outreach_status = 'sent';
                    $lead->save();

                    // Activity Log
                    ActivityLog::create([
                        'company_id' => $lead->company_id,
                        'lead_id' => $lead->id,
                        'description' => "Outreach email sent to {$recipientEmail}: '{$subject}'",
                        'action_type' => 'email_sent',
                        'meta' => ['message_id' => $messageId, 'provider' => 'smtp']
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not write email log to DB: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => 'Email sent successfully via Gmail SMTP to recipient inbox!',
                'message_id' => $messageId,
                'log_id' => $logId,
            ];
        } catch (\Throwable $e) {
            $rawError = $e->getMessage();
            $classifiedError = $this->classifySmtpError($rawError);

            // Clean log without sensitive credentials
            Log::error($classifiedError['log_title'], [
                'recipient' => $recipientEmail,
                'error_type' => $classifiedError['type'],
                'detail' => $classifiedError['user_message']
            ]);

            try {
                $leadId = ($lead->exists && !empty($lead->id)) ? $lead->id : null;

                // Record failed email log in DB
                EmailLog::create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $leadId,
                    'campaign_id' => $campaignId,
                    'subject' => $subject,
                    'provider' => 'smtp',
                    'sender_email' => $senderEmail,
                    'recipient_email' => $recipientEmail,
                    'status' => 'failed',
                    'error_message' => $classifiedError['user_message'],
                ]);

                if ($lead->exists && !empty($lead->id)) {
                    $lead->outreach_status = 'failed';
                    $lead->save();
                }
            } catch (\Throwable $dbErr) {
                // Ignore DB logging failure on error
            }

            return [
                'success' => false,
                'error' => $classifiedError['user_message'],
            ];
        }
    }

    /**
     * Verify SMTP connection and credentials before sending.
     *
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @throws \Exception
     */
    public function verifySmtpConnection(string $host, int $port, string $username, string $password): void
    {
        try {
            $isSsl = ($port === 465);
            $transport = new EsmtpTransport($host, $port, $isSsl);
            $transport->setUsername($username);
            $transport->setPassword($password);
            $transport->start();
            $transport->stop();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Ensure CAN-SPAM and GDPR compliant opt-out footer is attached cleanly.
     *
     * @param string $body
     * @param string $senderEmail
     * @param string $companyName
     * @param string $companyAddress
     * @return string
     */
    protected function ensureComplianceFooter(string $body, string $senderEmail, string $companyName, string $companyAddress): string
    {
        // If unsubscribe link already exists, don't duplicate
        if (stripos($body, 'unsubscribe') !== false || stripos($body, 'opt-out') !== false) {
            return $body;
        }

        $footerHtml = '
        <div style="margin-top: 28px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 11px; color: #94a3b8; line-height: 1.5;">
            <p style="margin: 0 0 4px 0;">You received this message regarding digital solutions for your business. If you prefer not to receive future emails, you can <a href="mailto:' . htmlspecialchars($senderEmail) . '?subject=Unsubscribe" style="color: #64748b; text-decoration: underline;">unsubscribe here</a>.</p>
            <p style="margin: 0;">' . htmlspecialchars($companyName) . (!empty($companyAddress) ? ' • ' . htmlspecialchars($companyAddress) : '') . '</p>
        </div>';

        return $body . $footerHtml;
    }

    /**
     * Convert HTML email content to clean plain text for multipart/alternative MIME.
     *
     * @param string $html
     * @return string
     */
    public function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<li[^>]*>/i', "• ", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<\/tr>/i', "\n", $text);
        $text = preg_replace('/<td[^>]*>/i', " ", $text);

        // Strip HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean redundant blank lines
        $text = preg_replace("/[\r\n]{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Parse SMTP exception message into clean, actionable errors without sensitive data leaks.
     *
     * @param string $message
     * @return array
     */
    protected function classifySmtpError(string $message): array
    {
        $lower = strtolower($message);

        // Authentication failure (Bad credentials, 535, 5.7.8, Username and Password not accepted)
        if (str_contains($lower, '535') || 
            str_contains($lower, '5.7.8') || 
            str_contains($lower, 'authentication') || 
            str_contains($lower, 'badcredentials') || 
            str_contains($lower, 'username and password not accepted') ||
            str_contains($lower, 'application-specific password')) {
            return [
                'type' => 'auth_failed',
                'log_title' => 'Gmail SMTP authentication failed',
                'user_message' => 'Gmail SMTP authentication failed. Please check the Gmail address and Google App Password.'
            ];
        }

        // Connection failure (Timeout, unreachable, connection refused)
        if (str_contains($lower, 'connection could not be established') || 
            str_contains($lower, 'connection timed out') || 
            str_contains($lower, 'failed to connect') || 
            str_contains($lower, 'network is unreachable') ||
            str_contains($lower, 'operation timed out')) {
            return [
                'type' => 'connection_failed',
                'log_title' => 'SMTP connection failed',
                'user_message' => 'SMTP connection failed: Unable to connect to smtp.gmail.com on port 587 (STARTTLS). Check network or firewall settings.'
            ];
        }

        // Rate limit / daily sending quota
        if (str_contains($lower, 'daily user sending limit') || 
            str_contains($lower, 'rate limit') || 
            str_contains($lower, '550') ||
            str_contains($lower, 'too many')) {
            return [
                'type' => 'rate_limit',
                'log_title' => 'Gmail sending/rate-limit failure',
                'user_message' => 'Gmail sending/rate-limit failure: Google sending quota or rate limit exceeded for this account.'
            ];
        }

        return [
            'type' => 'general_error',
            'log_title' => 'SMTP dispatch error',
            'user_message' => 'Gmail SMTP error: ' . strip_tags($message)
        ];
    }
}

