<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\GeneratedEmail;
use App\Models\EmailLog;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $companyId = $user ? $user->company_id : 1;

        $totalLeads = Lead::where('company_id', $companyId)->count();
        $newLeads = Lead::where('company_id', $companyId)->where('lead_status', 'new')->count();
        $savedLeads = $totalLeads;
        $leadsWithEmail = Lead::where('company_id', $companyId)->whereNotNull('email')->where('email', '!=', '')->count();
        $leadsWithMobile = Lead::where('company_id', $companyId)->whereNotNull('phone')->where('phone', '!=', '')->count();
        $leadsWithWebsite = Lead::where('company_id', $companyId)->where('website_status', 'has_website')->count();
        $leadsNoWebsite = Lead::where('company_id', $companyId)->where('website_status', 'no_website')->count();

        $emailsGenerated = GeneratedEmail::where('company_id', $companyId)->count();
        $emailsSent = EmailLog::where('company_id', $companyId)->where('status', 'sent')->count();
        $emailsFailed = EmailLog::where('company_id', $companyId)->where('status', 'failed')->count();
        $emailsReplied = EmailLog::where('company_id', $companyId)->where('status', 'replied')->count();
        $convertedLeads = Lead::where('company_id', $companyId)->where('lead_status', 'converted')->count();

        $responseRate = $emailsSent > 0 ? round(($emailsReplied / $emailsSent) * 100, 1) : 0;
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        // Lead Growth (Last 7 Days)
        $sevenDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = Lead::where('company_id', $companyId)
                ->whereDate('created_at', $date)
                ->count();
            $sevenDays[] = [
                'day' => now()->subDays($i)->format('D'),
                'date' => $date,
                'count' => $count,
            ];
        }

        // Lead Source Distribution
        $sourceCounts = Lead::where('company_id', $companyId)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->toArray();

        $leadSources = [
            ['name' => 'Apify Scraper', 'value' => $sourceCounts['apify'] ?? 0, 'color' => '#6366f1'],
            ['name' => 'Google Maps', 'value' => $sourceCounts['google_maps'] ?? 0, 'color' => '#3b82f6'],
            ['name' => 'Excel Import', 'value' => $sourceCounts['excel_import'] ?? 0, 'color' => '#10b981'],
            ['name' => 'Manual Entry', 'value' => $sourceCounts['manual'] ?? 0, 'color' => '#f59e0b'],
        ];

        // Website Status Distribution
        $websiteBreakdown = [
            ['name' => 'Has Website', 'value' => $leadsWithWebsite, 'color' => '#10b981'],
            ['name' => 'No Website', 'value' => $leadsNoWebsite, 'color' => '#ef4444'],
            ['name' => 'Invalid / Unreachable', 'value' => Lead::where('company_id', $companyId)->whereIn('website_status', ['invalid', 'unreachable'])->count(), 'color' => '#f59e0b'],
        ];

        // Email Funnel Breakdown
        $emailBreakdown = [
            ['status' => 'Generated', 'count' => $emailsGenerated, 'color' => '#8b5cf6'],
            ['status' => 'Sent', 'count' => $emailsSent, 'color' => '#3b82f6'],
            ['status' => 'Failed', 'count' => $emailsFailed, 'color' => '#ef4444'],
            ['status' => 'Replied', 'count' => $emailsReplied, 'color' => '#10b981'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_leads' => $totalLeads,
                    'new_leads' => $newLeads,
                    'saved_leads' => $savedLeads,
                    'leads_with_email' => $leadsWithEmail,
                    'leads_with_mobile' => $leadsWithMobile,
                    'leads_with_website' => $leadsWithWebsite,
                    'leads_no_website' => $leadsNoWebsite,
                    'emails_generated' => $emailsGenerated,
                    'emails_sent' => $emailsSent,
                    'emails_failed' => $emailsFailed,
                    'response_rate' => $responseRate,
                    'conversion_rate' => $conversionRate,
                ],
                'growth' => $sevenDays,
                'sources' => $leadSources,
                'website_breakdown' => $websiteBreakdown,
                'email_breakdown' => $emailBreakdown,
            ]
        ]);
    }
}
