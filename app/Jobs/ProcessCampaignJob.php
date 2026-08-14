<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\EmailTemplate;
use App\Services\AIEmailGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $campaignId;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(AIEmailGeneratorService $aiGenerator): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== 'running') return;

        $template = EmailTemplate::find($campaign->email_template_id);
        $pendingLeads = CampaignLead::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->take($campaign->daily_sending_limit)
            ->get();

        if ($pendingLeads->isEmpty()) {
            $campaign->update(['status' => 'completed']);
            return;
        }

        foreach ($pendingLeads as $cLead) {
            $lead = $cLead->lead;
            if (!$lead || empty($lead->email)) {
                $cLead->update(['status' => 'failed', 'error_message' => 'No lead email']);
                continue;
            }

            // Subject & Body generation
            if ($template) {
                $subject = str_replace(
                    ['{{business_name}}', '{{city}}', '{{category}}', '{{company_name}}'],
                    [$lead->business_name, $lead->city ?? '', $lead->category ?? '', $campaign->company->name ?? ''],
                    $template->subject
                );
                $body = str_replace(
                    ['{{business_name}}', '{{city}}', '{{category}}', '{{company_name}}'],
                    [$lead->business_name, $lead->city ?? '', $lead->category ?? '', $campaign->company->name ?? ''],
                    $template->body
                );
            } else {
                $ai = $aiGenerator->generateEmail($lead, ['service' => $campaign->service]);
                $subject = $ai['subject'];
                $body = $ai['body'];
            }

            $cLead->update(['status' => 'queued']);
            SendEmailJob::dispatch($lead->id, $subject, $body, $campaign->id, $campaign->sending_provider);
        }
    }
}
