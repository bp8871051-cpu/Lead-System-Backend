<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $templates = EmailTemplate::where('company_id', $companyId)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'service' => 'nullable|string|max:255',
            'tone' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $companyId = $request->user()->company_id;

        if (!empty($validated['is_default'])) {
            EmailTemplate::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $template = EmailTemplate::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'service' => $validated['service'] ?? null,
            'tone' => $validated['tone'] ?? 'Professional',
            'variables' => ['{{business_name}}', '{{city}}', '{{category}}', '{{company_name}}', '{{contact_name}}'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email Template created successfully',
            'data' => $template
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $template = EmailTemplate::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'subject' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'service' => 'nullable|string|max:255',
            'tone' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            EmailTemplate::where('company_id', $companyId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Email Template updated successfully',
            'data' => $template
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $template = EmailTemplate::where('company_id', $companyId)->findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email Template deleted successfully'
        ]);
    }
}
