<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Services\ConferenceMailer;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionImportController extends Controller
{
    /**
     * Preview uploaded CSV file, extract headers, auto-detect column mappings, and return preview payload.
     */
    public function preview(Request $request, Conference $conference): JsonResponse
    {
        $this->authorize('update', $conference);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'tsv'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file format. Please upload a CSV (.csv) file.',
            ], 422);
        }

        // Store file temporarily
        $tempPath = $file->store('csv-imports', 'local');
        $fullPath = Storage::disk('local')->path($tempPath);

        $headers = [];
        $sampleRows = [];
        $totalRows = 0;

        if (($handle = fopen($fullPath, 'r')) !== false) {
            // Detect delimiter (comma, semicolon, tab)
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            // Read headers (remove BOM if present)
            $headerRow = fgetcsv($handle, 0, $delimiter);
            if ($headerRow) {
                if (isset($headerRow[0])) {
                    $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerRow[0]);
                }
                $headers = array_map(fn ($h) => trim((string) $h), $headerRow);
            }

            // Read sample rows and count total rows
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count(array_filter($row)) === 0) {
                    continue; // Skip empty lines
                }
                $totalRows++;
                if (count($sampleRows) < 3) {
                    $rowCombined = [];
                    foreach ($headers as $idx => $headerName) {
                        $rowCombined[$headerName] = trim((string) ($row[$idx] ?? ''));
                    }
                    $sampleRows[] = $rowCombined;
                }
            }
            fclose($handle);
        }

        if (empty($headers)) {
            Storage::disk('local')->delete($tempPath);

            return response()->json([
                'success' => false,
                'message' => 'Unable to read header row from the uploaded CSV file.',
            ], 422);
        }

        $savedMapping = $conference->googleFormMapping();

        $detectedMapping = [
            'paper_id_column' => $this->autoMatchColumn($headers, [$savedMapping['paper_id_column'] ?? null, 'ID Papers (#)', 'Paper ID', 'ID Paper', 'Paper Code', 'Code', 'ID']),
            'title_column' => $this->autoMatchColumn($headers, [$savedMapping['title_column'] ?? null, "Paper's Title", 'Paper Title', 'Title', 'Judul']),
            'author_name_column' => $this->autoMatchColumn($headers, [$savedMapping['author_name_column'] ?? null, "Registered Author's Name", 'Author Name', 'Registered Author', 'Corresponding Author', 'Nama']),
            'author_email_column' => $this->autoMatchColumn($headers, [$savedMapping['author_email_column'] ?? null, "Registered Author's Email Address", 'Author Email', 'Registered Author Email', 'Email Address', 'Email']),
            'author_phone_column' => $this->autoMatchColumn($headers, [$savedMapping['author_phone_column'] ?? null, "Registered Author's Phone Number", 'Author Phone', 'Registered Author Phone', 'Phone Number', 'Phone', 'WhatsApp', 'No HP']),
            'manuscript_file_column' => $this->autoMatchColumn($headers, [$savedMapping['manuscript_file_column'] ?? null, 'Upload the Manuscript Source', 'Manuscript Source', 'Manuscript', 'File Link', 'Editable Manuscript', 'Upload Manuscript', 'File']),
        ];

        return response()->json([
            'success' => true,
            'temp_file_id' => $tempPath,
            'headers' => $headers,
            'detected_mapping' => $detectedMapping,
            'sample_rows' => $sampleRows,
            'total_rows' => $totalRows,
        ]);
    }

    /**
     * Process confirmed CSV import file, smart deduplicate, and upsert papers.
     */
    public function process(Request $request, Conference $conference, SubmissionWorkflow $workflow, ConferenceMailer $mailer): JsonResponse
    {
        $this->authorize('update', $conference);

        $request->validate([
            'temp_file_id' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'mapping.paper_id_column' => ['required', 'string'],
            'mapping.title_column' => ['required', 'string'],
            'mapping.author_name_column' => ['required', 'string'],
            'mapping.author_email_column' => ['required', 'string'],
        ]);

        $tempPath = $request->input('temp_file_id');
        if (! Storage::disk('local')->exists($tempPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Temporary import file expired or missing. Please upload your CSV file again.',
            ], 422);
        }

        $fullPath = Storage::disk('local')->path($tempPath);
        $userMapping = $request->input('mapping');

        $stats = $this->executeImportLoop($conference, $fullPath, $userMapping, $workflow, $mailer);

        // Delete temporary file
        Storage::disk('local')->delete($tempPath);

        return response()->json([
            'success' => true,
            'message' => "CSV Import Completed: {$stats['new']} new papers created, {$stats['updated']} existing papers updated with new file versions, {$stats['skipped']} skipped.",
            'stats' => $stats,
        ]);
    }

    /**
     * Directly import CSV file with auto-detected or provided mapping.
     *
     * @return array{new: int, updated: int, skipped: int}
     */
    public function importFileDirectly(Conference $conference, string $fullPath, ?array $userMapping = null, ?SubmissionWorkflow $workflow = null, ?ConferenceMailer $mailer = null): array
    {
        $workflow = $workflow ?? app(SubmissionWorkflow::class);
        $mailer = $mailer ?? app(ConferenceMailer::class);

        $headers = [];
        if (($handle = fopen($fullPath, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            $headerRow = fgetcsv($handle, 0, $delimiter);
            if ($headerRow) {
                if (isset($headerRow[0])) {
                    $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerRow[0]);
                }
                $headers = array_map(fn ($h) => trim((string) $h), $headerRow);
            }
            fclose($handle);
        }

        if (empty($headers)) {
            return ['new' => 0, 'updated' => 0, 'skipped' => 0];
        }

        if (! $userMapping) {
            $savedMapping = $conference->googleFormMapping();
            $userMapping = [
                'paper_id_column' => $this->autoMatchColumn($headers, [$savedMapping['paper_id_column'] ?? null, 'ID Papers (#)', 'Paper ID', 'ID Paper', 'Paper Code', 'Code', 'ID']),
                'title_column' => $this->autoMatchColumn($headers, [$savedMapping['title_column'] ?? null, "Paper's Title", 'Paper Title', 'Title', 'Judul']),
                'author_name_column' => $this->autoMatchColumn($headers, [$savedMapping['author_name_column'] ?? null, "Registered Author's Name", 'Author Name', 'Registered Author', 'Corresponding Author', 'Nama']),
                'author_email_column' => $this->autoMatchColumn($headers, [$savedMapping['author_email_column'] ?? null, "Registered Author's Email Address", 'Author Email', 'Registered Author Email', 'Email Address', 'Email']),
                'author_phone_column' => $this->autoMatchColumn($headers, [$savedMapping['author_phone_column'] ?? null, "Registered Author's Phone Number", 'Author Phone', 'Registered Author Phone', 'Phone Number', 'Phone', 'WhatsApp', 'No HP']),
                'manuscript_file_column' => $this->autoMatchColumn($headers, [$savedMapping['manuscript_file_column'] ?? null, 'Upload the Manuscript Source', 'Manuscript Source', 'Manuscript', 'File Link', 'Editable Manuscript', 'Upload Manuscript', 'File']),
            ];
        }

        return $this->executeImportLoop($conference, $fullPath, $userMapping, $workflow, $mailer);
    }

    /**
     * Core CSV parsing and deduplication import loop.
     *
     * @return array{new: int, updated: int, skipped: int}
     */
    private function executeImportLoop(Conference $conference, string $fullPath, array $userMapping, SubmissionWorkflow $workflow, ConferenceMailer $mailer): array
    {
        $paperIdCol = $userMapping['paper_id_column'] ?? null;
        $titleCol = $userMapping['title_column'] ?? null;
        $nameCol = $userMapping['author_name_column'] ?? null;
        $emailCol = $userMapping['author_email_column'] ?? null;
        $phoneCol = $userMapping['author_phone_column'] ?? null;
        $fileCol = $userMapping['manuscript_file_column'] ?? null;

        $newCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        if (! $paperIdCol || ! $titleCol || ! $nameCol || ! $emailCol) {
            return ['new' => 0, 'updated' => 0, 'skipped' => 0];
        }

        if (($handle = fopen($fullPath, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            $headerRow = fgetcsv($handle, 0, $delimiter);
            if (isset($headerRow[0])) {
                $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerRow[0]);
            }
            $headers = array_map(fn ($h) => trim((string) $h), $headerRow);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count(array_filter($row)) === 0) {
                    continue;
                }

                $rowData = [];
                foreach ($headers as $idx => $headerName) {
                    $rowData[$headerName] = trim((string) ($row[$idx] ?? ''));
                }

                $paperId = $rowData[$paperIdCol] ?? null;
                $title = $rowData[$titleCol] ?? null;
                $authorName = $rowData[$nameCol] ?? null;
                $authorEmail = $rowData[$emailCol] ?? null;
                $authorPhone = $phoneCol ? ($rowData[$phoneCol] ?? null) : null;
                $manuscriptUrl = $fileCol ? ($rowData[$fileCol] ?? null) : null;

                if (blank($title) || blank($authorEmail)) {
                    $skippedCount++;

                    continue;
                }

                if (blank($paperId)) {
                    $paperId = 'PAPER-'.str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT);
                }

                $manuscriptFormat = str_contains(strtolower((string) $manuscriptUrl), '.zip') ? 'zip' : 'docx';

                // Look up existing submission (Smart Deduplication)
                $existing = Submission::query()
                    ->where('conference_id', $conference->id)
                    ->where(function ($query) use ($paperId, $title, $authorEmail) {
                        $query->where('paper_id', $paperId)
                            ->orWhere(function ($q) use ($title, $authorEmail) {
                                $q->where('title', $title)
                                    ->where('corresponding_author_email', $authorEmail);
                            });
                    })
                    ->first();

                if ($existing) {
                    // Update existing paper safely without deleting review cycles or internal notes
                    $isDuplicateFlag = ($existing->paper_id !== $paperId);
                    $duplicateNotes = $isDuplicateFlag
                        ? "Imported CSV row with different Paper ID ({$paperId}) under existing paper {$existing->paper_id}."
                        : $existing->duplicate_notes;

                    $existing->update([
                        'corresponding_author_name' => $authorName ?: $existing->corresponding_author_name,
                        'corresponding_author_phone' => $authorPhone ?: $existing->corresponding_author_phone,
                        'is_flagged_duplicate' => $existing->is_flagged_duplicate || $isDuplicateFlag,
                        'duplicate_notes' => $duplicateNotes,
                    ]);

                    $fileUpdated = false;
                    if (filled($manuscriptUrl)) {
                        $highestVersion = $existing->files()->where('file_category', 'editable_manuscript')->max('version_number') ?: 0;
                        $nextVersion = $highestVersion + 1;

                        $alreadyUploaded = $existing->files()
                            ->where('file_category', 'editable_manuscript')
                            ->where('external_url', (string) $manuscriptUrl)
                            ->exists();

                        if (! $alreadyUploaded) {
                            FileVersion::create([
                                'submission_id' => $existing->id,
                                'version_number' => $nextVersion,
                                'label' => "Editable Manuscript (v{$nextVersion})",
                                'source' => 'author',
                                'disk' => 'google_drive',
                                'storage_path' => (string) $manuscriptUrl,
                                'external_url' => (string) $manuscriptUrl,
                                'original_name' => $existing->paper_id.'-manuscript-v'.$nextVersion.'.'.$manuscriptFormat,
                                'file_category' => 'editable_manuscript',
                                'mime_type' => $manuscriptFormat === 'zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'size' => 0,
                                'is_final' => false,
                                'uploaded_by' => null,
                            ]);
                            $fileUpdated = true;
                        }
                    }

                    if ($fileUpdated || $isDuplicateFlag) {
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }
                } else {
                    // Create brand-new paper submission
                    $submission = Submission::create([
                        'conference_id' => $conference->id,
                        'form_version_id' => $conference->activeFormVersion?->id,
                        'paper_id' => $paperId,
                        'paper_code' => $paperId,
                        'manuscript_format' => $manuscriptFormat,
                        'title' => $title,
                        'corresponding_author_name' => $authorName ?: 'Author',
                        'corresponding_author_email' => $authorEmail,
                        'corresponding_author_phone' => $authorPhone ?: '-',
                        'status' => SubmissionStatus::Submitted,
                        'submission_source' => 'google_form',
                        'submitted_at' => now(),
                        'answers' => [
                            'csv_imported_at' => now()->toDateTimeString(),
                        ],
                    ]);

                    SubmissionAuthor::create([
                        'submission_id' => $submission->id,
                        'name' => $authorName ?: 'Author',
                        'email' => $authorEmail,
                        'phone' => $authorPhone ?: '-',
                        'is_corresponding' => true,
                        'sort_order' => 1,
                    ]);

                    if (filled($manuscriptUrl)) {
                        FileVersion::create([
                            'submission_id' => $submission->id,
                            'version_number' => 1,
                            'label' => 'Editable Manuscript (v1)',
                            'source' => 'author',
                            'disk' => 'google_drive',
                            'storage_path' => (string) $manuscriptUrl,
                            'external_url' => (string) $manuscriptUrl,
                            'original_name' => $submission->paper_id.'-manuscript-v1.'.$manuscriptFormat,
                            'file_category' => 'editable_manuscript',
                            'mime_type' => $manuscriptFormat === 'zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'size' => 0,
                            'is_final' => false,
                            'uploaded_by' => null,
                        ]);
                    }

                    $newCount++;
                }
            }
            fclose($handle);
        }

        return ['new' => $newCount, 'updated' => $updatedCount, 'skipped' => $skippedCount];
    }

    /**
     * Helper to fuzzy match column names from header list.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $candidates
     */
    private function autoMatchColumn(array $headers, array $candidates): ?string
    {
        $candidates = array_values(array_filter($candidates));
        foreach ($candidates as $cand) {
            foreach ($headers as $h) {
                if (strtolower(trim($h)) === strtolower(trim((string) $cand))) {
                    return $h;
                }
            }
        }

        // Partial match
        foreach ($candidates as $cand) {
            foreach ($headers as $h) {
                if (str_contains(strtolower(trim($h)), strtolower(trim((string) $cand))) || str_contains(strtolower(trim((string) $cand)), strtolower(trim($h)))) {
                    return $h;
                }
            }
        }

        return $headers[0] ?? null;
    }
}
