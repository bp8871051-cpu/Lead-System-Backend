<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Facades\View;

class EmailTemplateRendererService
{
    /**
     * Render the fixed professional corporate HTML email template.
     */
    public function renderCorporateEmail(Company $company, Lead $lead, array $contentData, ?string $senderName = null, ?string $senderDesignation = null): string
    {
        // Fallback default services if company services are empty
        $defaultServices = [
            'Website Development',
            'Web Applications',
            'UI / UX Design',
            'Graphic Design',
            'Logo Design',
            'Branding',
            'Motion Graphics',
            'Animation',
            'Video Editing',
            'Digital Marketing',
            'SEO',
            'Social Media Marketing',
            'Lead Generation',
            'CRM Development',
            'Automation Solutions'
        ];

        $services = !empty($company->services) && is_array($company->services) ? $company->services : $defaultServices;

        $data = [
            'company_name' => !empty($company->name) ? $company->name : 'BLUEBOXX.DA PRIVATE LIMITED',
            'company_website' => !empty($company->website) ? $company->website : 'https://blueboxxda.com',
            'company_email' => !empty($company->email) ? $company->email : (!empty($company->default_sender_email) ? $company->default_sender_email : 'contact@blueboxxda.com'),
            'company_phone' => !empty($company->phone) ? $company->phone : '+91 98765 43210',
            'company_alternate_phone' => $company->alternate_phone ?? null,
            'company_address' => !empty($company->address) ? $company->address : 'BLUEBOXX.DA Tower, Tech Park Road',
            'primary_color' => $company->primary_color ?? '#4F46E5',
            'gst_number' => $company->gst_number ?? null,
            'cin_number' => $company->cin_number ?? null,
            'business_hours' => $company->business_hours ?? 'Mon - Fri (9:00 AM - 6:00 PM)',
            'privacy_policy_url' => $company->privacy_policy_url ?? $company->website,
            'terms_url' => $company->terms_url ?? $company->website,
            'services' => $services,

            // Lead info
            'business_name' => $lead->business_name ?? 'Business Partner',
            'contact_name' => $lead->contact_name ?? null,
            'lead_email' => $lead->email ?? null,

            // Sender info
            'sender_name' => $senderName ?? $company->default_sender_name ?? 'Blueboxx Outreach',
            'sender_designation' => $senderDesignation ?? $company->default_sender_designation ?? 'BLUEBOXX.DA PRIVATE LIMITED',

            // AI Content Data
            'subject' => $contentData['subject'] ?? "Cold Outreach & Business Growth Proposal for {$lead->business_name}",
            'introduction' => $contentData['introduction'] ?? "We identified key opportunities where an upgraded web experience could accelerate your digital growth.",
            'opportunities' => $contentData['opportunities'] ?? [
                ['title' => 'Web Applications & Website Development', 'description' => 'A modern, high-speed responsive website layout.'],
                ['title' => 'UI/UX & Graphic Design', 'description' => 'High-impact brand visuals and user-friendly design.'],
                ['title' => 'Digital Marketing & SEO', 'description' => 'Improve online visibility and capture high-intent leads.']
            ],
            'value_proposition' => $contentData['value_proposition'] ?? ($company->usp ?? "We specialize in Website Development, UI/UX Design, Lead Generation, and CRM Automation tailored for growing businesses."),
            'cta' => $contentData['cta'] ?? "Would you be open to a quick 5-minute call next Tuesday to discuss how these updates can boost your online presence?",
        ];

        return View::make('emails.professional-corporate', $data)->render();
    }
}
