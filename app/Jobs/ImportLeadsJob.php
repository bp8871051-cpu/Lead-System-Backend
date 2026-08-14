<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Services\LeadNormalizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importLogId;
    protected array $rows;
    protected array $mapping;

    public function __construct(int $importLogId, array $rows, array $mapping)
    {
        $this->importLogId = $importLogId;
        $this->rows = $rows;
        $this->mapping = $mapping;
    }

    public function handle(LeadNormalizerService $normalizer): void
    {
        $importLog = ImportLog::find($this->importLogId);
        if (!$importLog) return;

        $imported = 0;
        $duplicates = 0;
        $invalid = 0;

        foreach ($this->rows as $row) {
            $data = [];
            foreach ($this->mapping as $targetField => $sourceColumn) {
                if (isset($row[$sourceColumn])) {
                    $data[$targetField] = $row[$sourceColumn];
                }
            }

            if (empty($data['business_name'])) {
                $invalid++;
                continue;
            }

            try {
                $res = $normalizer->processAndSave($data, $importLog->company_id, 'excel_import');
                if ($res['is_duplicate']) {
                    $duplicates++;
                } else {
                    $imported++;
                }
            } catch (\Exception $e) {
                Log::warning("Import row error: " . $e->getMessage());
                $invalid++;
            }
        }

        $importLog->update([
            'imported_rows' => $imported,
            'duplicate_rows' => $duplicates,
            'invalid_rows' => $invalid,
            'status' => 'completed',
        ]);
    }
}
