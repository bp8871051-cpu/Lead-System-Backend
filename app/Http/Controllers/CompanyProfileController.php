<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company;

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    public function update(Request $request)
    {
        $company = $request->user()->company;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string',
            'primary_color' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'industry' => 'nullable|string|max:255',
            'services' => 'nullable|array',
            'products' => 'nullable|array',
            'website' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'alternate_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:100',
            'cin_number' => 'nullable|string|max:100',
            'business_hours' => 'nullable|string|max:255',
            'privacy_policy_url' => 'nullable|string|max:255',
            'terms_url' => 'nullable|string|max:255',
            'target_audience' => 'nullable|string',
            'target_industries' => 'nullable|array',
            'target_locations' => 'nullable|array',
            'usp' => 'nullable|string',
            'company_tone' => 'nullable|string|max:100',
            'email_signature' => 'nullable|string',
            'default_sender_name' => 'nullable|string|max:255',
            'default_sender_designation' => 'nullable|string|max:255',
            'default_sender_email' => 'nullable|email|max:255',
            'social_links' => 'nullable|array',
        ]);

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company profile updated successfully',
            'data' => $company
        ]);
    }
}
