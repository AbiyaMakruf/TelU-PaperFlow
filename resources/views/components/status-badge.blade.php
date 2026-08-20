@props(['status' => null, 'submission' => null])
@php
    $sub = $submission ?? ($status instanceof \App\Models\Submission ? $status : null);
    $statusEnum = $sub ? $sub->status : ($status instanceof \App\Enums\SubmissionStatus ? $status : \App\Enums\SubmissionStatus::tryFrom((string)$status));
    $isOverdue = $sub?->isOverdue() ?? false;
    
    $color = $statusEnum?->color() ?? 'primary';
    $label = $statusEnum?->label() ?? ucfirst((string) ($status ?? 'Unknown'));
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-1.5']) }}>
    @if($isOverdue)
        <span class="badge badge-overdue text-[10px] px-2 py-0.5" title="Paper review deadline has passed">
            ⚠️ OVERDUE
        </span>
    @endif
    <span class="badge badge-{{ $color }}">{{ $label }}</span>
</span>
