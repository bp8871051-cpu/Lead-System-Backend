<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Models\Lead;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Enterprise Company
        $company = Company::create([
            'name' => 'BLUEBOXX.DA PRIVATE LIMITED',
            'description' => 'Premier Digital Agency & Software Engineering Firm specializing in Web Development, Custom CRM software, and AI Workflow Automation for growing businesses.',
            'industry' => 'Software & Digital Services',
            'services' => ['Website Development', 'Custom CRM Software', 'AI Automation', 'Digital Marketing'],
            'products' => ['Blueboxx CRM Suite', 'LeadFlow AI Engine'],
            'website' => 'https://blueboxxda.com',
            'phone' => '+91 98765 43210',
            'email' => 'contact@blueboxxda.com',
            'address' => 'BLUEBOXX.DA Tower, Tech Park Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'India',
            'target_audience' => 'Small and medium businesses, local service providers, restaurants, fitness centers, and real estate agencies looking to digitalize operations and capture more leads online.',
            'target_industries' => ['Restaurants', 'Gyms & Fitness', 'Real Estate', 'Healthcare', 'Legal Services'],
            'target_locations' => ['Ahmedabad', 'Gandhinagar', 'Surat', 'Vadodara'],
            'usp' => 'We build custom, high-converting digital solutions that turn local traffic into repeat paying customers with guaranteed fast delivery.',
            'company_tone' => 'Professional',
            'email_signature' => "Best regards,\nBlueboxx Outreach\nBLUEBOXX.DA PRIVATE LIMITED\nhttps://blueboxxda.com\n+91 98765 43210",
            'default_sender_name' => 'Blueboxx Outreach',
            'default_sender_designation' => 'BLUEBOXX.DA PRIVATE LIMITED',
            'default_sender_email' => 'contact@blueboxxda.com',
        ]);

        // 2. Create Admin User
        User::create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin@blueboxx.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '+91 98765 43210',
        ]);

        // 3. Create Settings
        CompanySetting::create([
            'company_id' => $company->id,
            'apify_actor_id' => 'compass/google-maps-extractor',
            'ai_provider' => 'openrouter',
            'ai_model' => 'google/gemini-2.5-flash',
            'ai_temperature' => 0.7,
            'smtp_host' => 'smtp.brevo.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_from_email' => 'outreach@blueboxx.io',
            'smtp_from_name' => 'Blueboxx Outreach',
        ]);

        // 4. Create Sample Email Templates
        EmailTemplate::create([
            'company_id' => $company->id,
            'name' => 'Website Development Outreach',
            'subject' => 'Unlocking digital customers for {{business_name}} in {{city}}',
            'body' => "Hello {{business_name}} team,\n\nI was searching for top {{category}} businesses in {{city}} and came across {{business_name}}.\n\nNoticeably, your business currently doesn't have an active web presence listed on Google. In {{city}}, over 75% of customers research online before visiting in person.\n\nAt Blueboxx Solutions, we build mobile-ready, beautiful website portals tailored for {{category}} companies. Our USP: Custom, affordable web development with complete automated lead management.\n\nWould you be open to a 5-minute call this week to see how a professional website can increase your inquiries?\n\nBest regards,\nBlueboxx Team",
            'service' => 'Website Development',
            'tone' => 'Professional',
            'variables' => ['{{business_name}}', '{{city}}', '{{category}}'],
            'is_default' => true,
        ]);

        EmailTemplate::create([
            'company_id' => $company->id,
            'name' => 'CRM & AI Automation Offer',
            'subject' => 'Automate lead follow-ups for {{business_name}}',
            'body' => "Hi {{business_name}} Team,\n\nCongrats on your stellar rating in {{city}}!\n\nWe help growing {{category}} providers streamline customer management with custom CRM software and AI lead response systems. Imagine replying to every new phone call or message within 60 seconds automatically.\n\nLet us know if you'd like a quick demo of how our CRM system works.\n\nBest,\nBlueboxx Solutions Team",
            'service' => 'Custom CRM Software',
            'tone' => 'Persuasive',
            'variables' => ['{{business_name}}', '{{city}}', '{{category}}'],
            'is_default' => false,
        ]);

        // 5. Seed Realistic Sample Leads
        $sampleLeads = [
            [
                'business_name' => 'Flavors of Gujarat Restaurant',
                'contact_name' => 'Rajesh Patel',
                'category' => 'Restaurant',
                'email' => 'info@flavorsofgujarat.com',
                'phone' => '+91 98250 11223',
                'website' => null,
                'website_status' => 'no_website',
                'address' => 'CG Road, Navrangpura',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
                'google_rating' => 4.6,
                'review_count' => 312,
                'source' => 'google_maps',
                'lead_status' => 'new',
            ],
            [
                'business_name' => 'FitLife Gym & Health Club',
                'contact_name' => 'Vikram Shah',
                'category' => 'Gym',
                'email' => 'contact@fitlifegym.in',
                'phone' => '+91 98791 44556',
                'website' => 'https://fitlifegym.in',
                'website_status' => 'has_website',
                'address' => 'SG Highway, Bodakdev',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
                'google_rating' => 4.8,
                'review_count' => 520,
                'source' => 'apify',
                'lead_status' => 'contacted',
            ],
            [
                'business_name' => 'Heritage Crafts & Textiles',
                'contact_name' => 'Meera Joshi',
                'category' => 'Retail',
                'email' => null,
                'phone' => '+91 94265 88990',
                'website' => null,
                'website_status' => 'no_website',
                'address' => 'Law Garden Night Market',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
                'google_rating' => 4.4,
                'review_count' => 185,
                'source' => 'google_maps',
                'lead_status' => 'new',
            ],
            [
                'business_name' => 'Apex Real Estate Advisory',
                'contact_name' => 'Amitabh Mehta',
                'category' => 'Real Estate Agency',
                'email' => 'sales@apexrealty.co.in',
                'phone' => '+91 98980 77665',
                'website' => 'http://broken-link-apex-realty-test.com',
                'website_status' => 'unreachable',
                'address' => 'Ring Road, Athwa',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'country' => 'India',
                'google_rating' => 4.3,
                'review_count' => 94,
                'source' => 'apify',
                'lead_status' => 'email_generated',
            ],
            [
                'business_name' => 'Green Valley Organic Cafe',
                'contact_name' => 'Priya Desai',
                'category' => 'Cafe',
                'email' => 'hello@greenvalleycafe.org',
                'phone' => '+91 97129 33445',
                'website' => null,
                'website_status' => 'no_website',
                'address' => 'Kudasan Cross Road',
                'city' => 'Gandhinagar',
                'state' => 'Gujarat',
                'country' => 'India',
                'google_rating' => 4.7,
                'review_count' => 240,
                'source' => 'excel_import',
                'lead_status' => 'new',
            ],
        ];

        foreach ($sampleLeads as $leadData) {
            $leadData['company_id'] = $company->id;
            $leadData['email_status'] = !empty($leadData['email']) ? 'available' : 'missing';
            $leadData['phone_status'] = !empty($leadData['phone']) ? 'available' : 'missing';
            Lead::create($leadData);
        }
    }
}
