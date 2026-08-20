@props(['status' => null, 'submission' => null])
@php
    $sub = $submission ?? ($status instanceof \App\Models\Submission ? $status : null);
    $statusEnum = $sub ? $sub->status : ($status instanceof \App\Enums\SubmissionStatus ? $status : \App\Enums\SubmissionStatus::tryFrom((string)$status));
    $isOverdue = $sub?->isOverdue() ?? false;
    
    $label = $statusEnum?->label() ?? ucfirst((string) ($status ?? 'Unknown'));

    $badgeClass = match($statusEnum) {
        \App\Enums\SubmissionStatus::Submitted => 'bg-sky-100 text-sky-800 border-sky-300',
        \App\Enums\SubmissionStatus::NeedsAuthorCorrection => 'bg-amber-100 text-amber-900 border-amber-300',
        \App\Enums\SubmissionStatus::ReadyForAssignment => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        \App\Enums\SubmissionStatus::EditorialReview => 'bg-blue-100 text-blue-800 border-blue-300',
        \App\Enums\SubmissionStatus::WaitingAuthorRevision => 'bg-amber-100 text-amber-900 border-amber-300',
        \App\Enums\SubmissionStatus::ReviewerReview => 'bg-purple-100 text-purple-800 border-purple-300',
        \App\Enums\SubmissionStatus::ReviewerChangesRequested => 'bg-orange-100 text-orange-900 border-orange-300',
        \App\Enums\SubmissionStatus::ReadyForEdas => 'bg-teal-100 text-teal-800 border-teal-300',
        \App\Enums\SubmissionStatus::EdasFixRequired => 'bg-rose-100 text-rose-800 border-rose-300',
        \App\Enums\SubmissionStatus::Done => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        \App\Enums\SubmissionStatus::Withdrawn => 'bg-slate-100 text-slate-700 border-slate-300',
        \App\Enums\SubmissionStatus::Rejected => 'bg-red-100 text-red-800 border-red-300',
        default => 'bg-slate-100 text-slate-700 border-slate-300',
    };
@endphp

@if($isOverdue)
    <span class="inline-flex flex-wrap items-center gap-1.5">
        <span class="inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white border border-rose-700 shadow-2xs animate-pulse" title="Paper review deadline has passed">
            ⚠️ OVERDUE
        </span>
        <span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-extrabold border shadow-2xs {$badgeClass}"]) }}>{{ $label }}</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-extrabold border shadow-2xs {$badgeClass}"]) }}>{{ $label }}</span>
@endif
