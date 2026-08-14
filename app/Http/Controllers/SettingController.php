<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Services\EmailOutreachService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(Request $request)
    {
        $companyId = $request->user()->company_id;
        $settings = CompanySetting::firstOrCreate(['company_id' => $companyId]);

        return response()->json([
            'success' => true,
            'data' => [
                'apify_api_token' => $settings->apify_api_token ? '••••••••' . substr($settings->apify_api_token, -4) : '',
                'apify_actor_id' => $settings->apify_actor_id ?? 'compass/google-maps-extractor',
                'ai_provider' => $settings->ai_provider ?? 'openrouter',
                'ai_api_key' => $settings->ai_api_key ? '••••••••' . substr($settings->ai_api_key, -4) : '',
                'ai_model' => $settings->ai_model ?? 'google/gemini-2.5-flash',
                'ai_temperature' => $settings->ai_temperature ?? 0.7,
                'brevo_api_key' => $settings->brevo_api_key ? '••••••••' . substr($settings->brevo_api_key, -4) : '',
                'smtp_host' => $settings->smtp_host ?? 'smtp.brevo.com',
                'smtp_port' => $settings->smtp_port ?? 587,
                'smtp_username' => $settings->smtp_username ?? '',
                'smtp_password' => $settings->smtp_password ? '••••••••' : '',
                'smtp_encryption' => $settings->smtp_encryption ?? 'tls',
                'smtp_from_email' => $settings->smtp_from_email ?? 'outreach@leadsystem.com',
                'smtp_from_name' => $settings->smtp_from_name ?? 'LeadSystem CRM',
            ]
        ]);
    }

    public function update(Request $request)
    {
        $companyId = $request->user()->company_id;
        $settings = CompanySetting::firstOrCreate(['company_id' => $companyId]);

        $validated = $request->validate([
            'apify_api_token' => 'nullable|string',
            'apify_actor_id' => 'nullable|string',
            'ai_provider' => 'nullable|string',
            'ai_api_key' => 'nullable|string',
            'ai_model' => 'nullable|string',
            'ai_temperature' => 'nullable|numeric|min:0|max:1',
            'brevo_api_key' => 'nullable|string',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_encryption' => 'nullable|string',
            'smtp_from_email' => 'nullable|email',
            'smtp_from_name' => 'nullable|string',
        ]);

        // Don't overwrite with masked string
        foreach (['apify_api_token', 'ai_api_key', 'brevo_api_key', 'smtp_password'] as $secret) {
            if (isset($validated[$secret]) && str_contains($validated[$secret], '••••')) {
                unset($validated[$secret]);
            }
        }

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Admin Settings updated successfully'
        ]);
    }

    public function testEmail(Request $request, EmailOutreachService $outreach)
    {
        $request->validate([
            'test_email' => 'required|email',
            'provider' => 'nullable|in:brevo,smtp',
        ]);

        $companyId = $request->user()->company_id;

        // Fetch existing lead or create temporary lead instance without invalid ID 0
        $tempLead = Lead::where('company_id', $companyId)->first();
        if (!$tempLead) {
            $tempLead = new Lead([
                'company_id' => $companyId,
                'business_name' => 'Test Email Recipient',
                'email' => $request->test_email,
            ]);
        } else {
            $tempLead = clone $tempLead;
            $tempLead->email = $request->test_email;
        }

        $res = $outreach->sendLeadEmail(
            $tempLead,
            'LeadSystem CRM - Test Email Dispatch',
            "Hello!\n\nThis is a test email sent from LeadSystem CRM to verify your SMTP / Brevo integration.\n\nYour mail server is working perfectly!",
            null,
            $request->input('provider', 'brevo')
        );

        if (!$res['success']) {
            return response()->json([
                'success' => false,
                'message' => $res['error'] ?? 'Test email dispatch failed.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Test email dispatched successfully to {$request->test_email}! Check your inbox."
        ]);
    }
}
