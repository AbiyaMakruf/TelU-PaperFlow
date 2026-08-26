<?php

namespace App\Services;

use App\Models\Submission;

class WorkflowEmailContent
{
    /** @param array<string, string|null> $additionalRows */
    public static function paperCard(Submission $submission, array $additionalRows = []): string
    {
        return self::card('Paper Details', [
            'Paper ID' => $submission->paper_code,
            'Paper Title' => $submission->title,
            ...$additionalRows,
        ]);
    }

    /** @param array<string, string|null> $rows */
    public static function card(string $heading, array $rows): string
    {
        $content = collect($rows)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => '<tr><td style="padding:6px 0;font-weight:700;color:#0f172a;width:135px;vertical-align:top;">'.e($label).'</td><td style="padding:6px 0;color:#1e293b;font-weight:600;vertical-align:top;">'.nl2br(e((string) $value)).'</td></tr>')
            ->implode('');

        return '<div style="margin:20px 0;padding:18px 20px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;"><div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin-bottom:12px;">'.e($heading).'</div><table border="0" cellpadding="0" cellspacing="0" style="width:100%;font-size:13.5px;color:#334155;line-height:1.6;">'.$content.'</table></div>';
    }

    /** @param list<string> $items */
    public static function listCard(string $heading, array $items): string
    {
        $items = collect($items)->filter()->map(fn ($item) => '<li style="margin:0 0 7px;">'.nl2br(e((string) $item)).'</li>')->implode('');

        if ($items === '') {
            return '';
        }

        return '<div style="margin:20px 0;padding:16px 20px;background-color:#fff7ed;border:1px solid #fed7aa;border-radius:10px;color:#7c2d12;"><div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">'.e($heading).'</div><ul style="margin:0;padding-left:20px;font-size:13.5px;line-height:1.6;">'.$items.'</ul></div>';
    }
}
