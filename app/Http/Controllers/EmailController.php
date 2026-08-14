<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Company;
use App\Models\GeneratedEmail;
use App\Models\EmailLog;
use App\Services\AIEmailGeneratorService;
use App\Services\EmailOutreachService;
use App\Services\EmailTemplateRendererService;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    protected AIEmailGeneratorService $aiGenerator;
    protected EmailOutreachService $outreachService;
    protected EmailTemplateRendererService $renderer;

    public function __construct(
        AIEmailGeneratorService $aiGenerator,
        EmailOutreachService $outreachService,
        EmailTemplateRendererService $renderer
    ) {
        $this->aiGenerator = $aiGenerator;
        $this->outreachService = $outreachService;
        $this->renderer = $renderer;
    }

    public function generate(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer',
            'service' => 'nullable|string',
            'tone' => 'nullable|string',
            'length' => 'nullable|string',
            'cta' => 'nullable|string',
            'sender_name' => 'nullable|string',
            'sender_designation' => 'nullable|string',
        ]);

        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->findOrFail($request->lead_id);

        $aiResult = $this->aiGenerator->generateEmail($lead, [
            'service' => $request->service,
            'tone' => $request->tone,
            'length' => $request->length,
            'cta' => $request->cta,
            'sender_name' => $request->sender_name,
            'sender_designation' => $request->sender_designation,
        ]);

        // Record generated draft
        $generated = GeneratedEmail::create([
            'company_id' => $companyId,
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'subject' => $aiResult['subject'],
            'body' => $aiResult['body'],
            'tone' => $request->tone ?? 'Professional',
            'length' => $request->length ?? 'Medium',
            'cta' => $request->cta ?? 'Book a Call',
            'service_offered' => $request->service,
            'status' => 'approved',
        ]);

        $lead->update(['lead_status' => 'email_generated']);

        return response()->json([
            'success' => true,
            'message' => 'Fixed corporate HTML email generated successfully',
            'data' => [
                'id' => $generated->id,
                'lead_id' => $lead->id,
                'subject' => $generated->subject,
                'body' => $generated->body,
                'structured_data' => $aiResult['structured_data'] ?? null,
                'lead' => $lead,
            ]
        ]);
    }

    /**
     * Re-render Blade HTML email when user edits structured email fields.
     */
    public function render(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer',
            'subject' => 'required|string',
            'introduction' => 'required|string',
            'opportunities' => 'nullable|array',
            'value_proposition' => 'nullable|string',
            'cta' => 'required|string',
            'sender_name' => 'nullable|string',
            'sender_designation' => 'nullable|string',
        ]);

        $companyId = $request->user()->company_id;
        $company = Company::findOrFail($companyId);
        $lead = Lead::where('company_id', $companyId)->findOrFail($request->lead_id);

        $contentData = [
            'subject' => $request->subject,
            'introduction' => $request->introduction,
            'opportunities' => $request->opportunities ?? [],
            'value_proposition' => $request->value_proposition,
            'cta' => $request->cta,
        ];

        $htmlContent = $this->renderer->renderCorporateEmail(
            $company,
            $lead,
            $contentData,
            $request->sender_name,
            $request->sender_designation
        );

        return response()->json([
            'success' => true,
            'html' => $htmlContent
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer',
            'subject' => 'required|string',
            'body' => 'required|string',
            'provider' => 'nullable|in:brevo,smtp',
        ]);

        $companyId = $request->user()->company_id;
        $lead = Lead::where('company_id', $companyId)->findOrFail($request->lead_id);
        $provider = $request->input('provider', 'smtp');

        $result = $this->outreachService->sendLeadEmail(
            $lead,
            $request->subject,
            $request->body,
            null,
            $provider
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Corporate HTML email sent successfully to ' . ($lead->email ?? $lead->business_name),
            'data' => $result
        ]);
    }

    public function bulkSend(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'integer',
            'subject' => 'required|string',
            'body' => 'required|string',
            'provider' => 'nullable|in:brevo,smtp',
        ]);

        $companyId = $request->user()->company_id;
        $company = Company::findOrFail($companyId);
        $provider = $request->input('provider', 'smtp');
        $leads = Lead::where('company_id', $companyId)->whereIn('id', $request->lead_ids)->get();

        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($leads as $lead) {
            if (empty($lead->email)) {
                $failedCount++;
                $errors[] = "{$lead->business_name}: Missing email address";
                continue;
            }

            // Render corporate email for each individual lead if structured or template variable replacement
            $personalizedSubject = str_replace(
                ['{{business_name}}', '{{city}}', '{{category}}'],
                [$lead->business_name, $lead->city ?? 'your area', $lead->category ?? 'business'],
                $request->subject
            );

            $personalizedBody = str_replace(
                ['{{business_name}}', '{{contact_name}}', '{{city}}', '{{category}}'],
                [$lead->business_name, $lead->contact_name ?? "{$lead->business_name} Team", $lead->city ?? 'your area', $lead->category ?? 'business'],
                $request->body
            );

            $res = $this->outreachService->sendLeadEmail(
                $lead,
                $personalizedSubject,
                $personalizedBody,
                null,
                $provider
            );

            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
                $errors[] = "{$lead->business_name}: " . ($res['error'] ?? 'Send failed');
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk outreach complete: {$sentCount} sent, {$failedCount} failed.",
            'data' => [
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'errors' => $errors,
            ]
        ]);
    }

    public function logs(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = EmailLog::where('company_id', $companyId)->with(['lead', 'campaign'])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                  ->orWhere('recipient_email', 'LIKE', "%{$search}%");
            });
        }

        $logs = $query->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
