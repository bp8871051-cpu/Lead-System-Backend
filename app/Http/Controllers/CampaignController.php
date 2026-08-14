<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Lead;
use App\Jobs\ProcessCampaignJob;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $campaigns = Campaign::where('company_id', $companyId)
            ->with(['template'])
            ->withCount(['campaignLeads'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'service' => 'nullable|string|max:255',
            'email_template_id' => 'nullable|exists:email_templates,id',
            'subject' => 'nullable|string|max:255',
            'daily_sending_limit' => 'nullable|integer|min:1|max:1000',
            'sending_provider' => 'nullable|in:brevo,smtp',
            'scheduled_at' => 'nullable|date',
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'integer',
        ]);

        $companyId = $request->user()->company_id;

        $campaign = Campaign::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'service' => $validated['service'] ?? null,
            'email_template_id' => $validated['email_template_id'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'daily_sending_limit' => $validated['daily_sending_limit'] ?? 100,
            'sending_provider' => $validated['sending_provider'] ?? 'brevo',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'status' => 'draft',
            'total_leads' => count($validated['lead_ids']),
        ]);

        // Attach leads
        foreach ($validated['lead_ids'] as $leadId) {
            CampaignLead::create([
                'campaign_id' => $campaign->id,
                'lead_id' => $leadId,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully with ' . count($validated['lead_ids']) . ' leads.',
            'data' => $campaign->load('template')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $campaign = Campaign::where('company_id', $companyId)
            ->with(['template', 'campaignLeads.lead'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $campaign
        ]);
    }

    public function start(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $campaign = Campaign::where('company_id', $companyId)->findOrFail($id);

        $campaign->update(['status' => 'running']);

        // Dispatch Process Campaign Job
        ProcessCampaignJob::dispatch($campaign->id);

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$campaign->name}' started. Queue jobs dispatched.",
            'data' => $campaign
        ]);
    }

    public function pause(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $campaign = Campaign::where('company_id', $companyId)->findOrFail($id);

        $campaign->update(['status' => 'paused']);

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$campaign->name}' paused.",
            'data' => $campaign
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $campaign = Campaign::where('company_id', $companyId)->findOrFail($id);

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully'
        ]);
    }
}
