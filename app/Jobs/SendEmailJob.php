<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\CampaignLead;
use App\Models\Campaign;
use App\Services\EmailOutreachService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    protected int $leadId;
    protected string $subject;
    protected string $body;
    protected ?int $campaignId;
    protected string $provider;

    public function __construct(int $leadId, string $subject, string $body, ?int $campaignId = null, string $provider = 'smtp')
    {
        $this->leadId = $leadId;
        $this->subject = $subject;
        $this->body = $body;
        $this->campaignId = $campaignId;
        $this->provider = $provider;
    }

    public function handle(EmailOutreachService $outreachService): void
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) return;

        $res = $outreachService->sendLeadEmail($lead, $this->subject, $this->body, $this->campaignId, $this->provider);

        if ($this->campaignId) {
            $campaignLead = CampaignLead::where('campaign_id', $this->campaignId)
                ->where('lead_id', $this->leadId)
                ->first();

            if ($campaignLead) {
                if ($res['success']) {
                    $campaignLead->update(['status' => 'sent', 'sent_at' => now()]);
                    Campaign::where('id', $this->campaignId)->increment('sent_count');
                } else {
                    $campaignLead->update(['status' => 'failed', 'error_message' => $res['error'] ?? 'Failed']);
                    Campaign::where('id', $this->campaignId)->increment('failed_count');
                }
            }
        }
    }
}
