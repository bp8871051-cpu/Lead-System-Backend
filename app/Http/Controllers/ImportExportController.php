<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\ImportLog;
use App\Jobs\ImportLeadsJob;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        // Read headers & sample rows
        $rows = [];
        if (($handle = fopen($path, 'r')) !== FALSE) {
            $header = fgetcsv($handle, 1000, ',');
            $sampleCount = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE && $sampleCount < 5) {
                if (count($header) === count($data)) {
                    $rows[] = array_combine($header, $data);
                }
                $sampleCount++;
            }
            fclose($handle);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'headers' => $header ?? [],
                'sample_rows' => $rows,
                'suggested_mapping' => [
                    'business_name' => $this->matchHeader($header, ['business_name', 'company', 'name', 'title']),
                    'contact_name' => $this->matchHeader($header, ['contact_name', 'owner', 'contact', 'full_name']),
                    'email' => $this->matchHeader($header, ['email', 'primary_email', 'email_address']),
                    'phone' => $this->matchHeader($header, ['phone', 'mobile', 'phone_number', 'contact_no']),
                    'website' => $this->matchHeader($header, ['website', 'url', 'site', 'domain']),
                    'category' => $this->matchHeader($header, ['category', 'industry', 'type']),
                    'city' => $this->matchHeader($header, ['city', 'location', 'town']),
                    'state' => $this->matchHeader($header, ['state', 'province']),
                    'country' => $this->matchHeader($header, ['country']),
                ]
            ]
        ]);
    }

    public function processImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'mapping' => 'required|array',
        ]);

        $companyId = $request->user()->company_id;
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $path = $file->getRealPath();

        $rows = [];
        if (($handle = fopen($path, 'r')) !== FALSE) {
            $header = fgetcsv($handle, 1000, ',');
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($header) === count($data)) {
                    $rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }

        $importLog = ImportLog::create([
            'company_id' => $companyId,
            'user_id' => $request->user()->id,
            'filename' => $filename,
            'total_rows' => count($rows),
            'status' => 'processing',
        ]);

        // Dispatch Import Leads Job
        ImportLeadsJob::dispatch($importLog->id, $rows, $request->mapping);

        return response()->json([
            'success' => true,
            'message' => "Import initialized for " . count($rows) . " rows.",
            'data' => $importLog
        ]);
    }

    public function export(Request $request)
    {
        $companyId = $request->user()->company_id;
        $type = $request->input('type', 'all'); // all, no_website, with_email, with_phone, selected

        $query = Lead::where('company_id', $companyId);

        if ($type === 'no_website') {
            $query->where('website_status', 'no_website');
        } elseif ($type === 'with_email') {
            $query->whereNotNull('email')->where('email', '!=', '');
        } elseif ($type === 'with_phone') {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        } elseif ($type === 'selected' && $request->has('lead_ids')) {
            $ids = explode(',', $request->input('lead_ids'));
            $query->whereIn('id', $ids);
        }

        $leads = $query->get();

        $response = new StreamedResponse(function () use ($leads) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Business Name', 'Contact Name', 'Category', 'Email', 'Phone',
                'Website', 'Website Status', 'Address', 'City', 'State', 'Country',
                'Google Rating', 'Review Count', 'Source', 'Lead Status', 'Email Status'
            ]);

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $lead->id,
                    $lead->business_name,
                    $lead->contact_name,
                    $lead->category,
                    $lead->email,
                    $lead->phone,
                    $lead->website,
                    $lead->website_status,
                    $lead->address,
                    $lead->city,
                    $lead->state,
                    $lead->country,
                    $lead->google_rating,
                    $lead->review_count,
                    $lead->source,
                    $lead->lead_status,
                    $lead->email_status,
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="leads_export_' . date('Y-m-d_H-i') . '.csv"');

        return $response;
    }

    protected function matchHeader(?array $headers, array $candidates): ?string
    {
        if (!$headers) return null;
        foreach ($headers as $h) {
            $clean = strtolower(trim($h));
            if (in_array($clean, $candidates)) {
                return $h;
            }
        }
        return null;
    }
}
