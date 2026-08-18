<?php

namespace App\Services;

use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\Submission;

class DuplicateSubmissionDetector
{
    public function check(Conference $conference, string $title, string $authorEmail, ?string $fileHash = null): ?string
    {
        $normalizedTitle = mb_strtolower(trim(preg_replace('/\s+/', ' ', $title)));

        // 1. Check exact or close title match within the conference
        $existingSubmissions = Submission::query()
            ->where('conference_id', $conference->id)
            ->get();

        foreach ($existingSubmissions as $existing) {
            $existingTitle = mb_strtolower(trim(preg_replace('/\s+/', ' ', $existing->title)));

            if ($normalizedTitle === $existingTitle) {
                return "Paper title is identical to submission {$existing->paper_code}.";
            }

            // String similarity > 85%
            similar_text($normalizedTitle, $existingTitle, $percent);
            if ($percent >= 85.0) {
                return 'Paper title is highly similar ('.round($percent, 1)."%) to submission {$existing->paper_code}.";
            }

            // Same author email and title similarity > 70%
            if (mb_strtolower($authorEmail) === mb_strtolower($existing->corresponding_author_email)) {
                if ($percent >= 70.0) {
                    return "Corresponding author email ({$authorEmail}) already has a similar submission {$existing->paper_code}.";
                }
            }
        }

        // 2. Check file checksum / hash match
        if ($fileHash) {
            $matchingFile = FileVersion::query()
                ->where('file_hash', $fileHash)
                ->whereHas('submission', fn ($q) => $q->where('conference_id', $conference->id))
                ->with('submission')
                ->first();

            if ($matchingFile && $matchingFile->submission) {
                return "The uploaded manuscript file is identical (matching checksum hash) to submission {$matchingFile->submission->paper_code}.";
            }
        }

        return null;
    }
}
