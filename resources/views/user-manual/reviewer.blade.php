<x-layouts.app title="Reviewer Manual · Paperflow" heading="Reviewer Guide">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'reviewer'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-amber-500">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-warning text-xs font-black mb-2">Reviewer Staff Role</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Reviewer</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Guide for manuscript inspection, IEEE PDF eXpress verification, EDAS error logging with quick preset buttons, and final EDAS approval.</p>
            </div>

            <!-- Flowchart Overview -->
            <div class="rounded-2xl bg-warm/80 p-4 sm:p-5 border border-navy/10 text-xs sm:text-sm">
                <h3 class="font-extrabold text-navy mb-2 text-sm">🔄 Reviewer PIC Primary Workflow:</h3>
                <ol class="list-decimal pl-5 space-y-1.5 text-slate-800">
                    <li>Open <strong>Papers (`/papers`)</strong> &rarr; Select a paper with status <em>Reviewer Review</em>.</li>
                    <li>Inspect manuscript quality and review the Editor's 16 IEEE checklist results.</li>
                    <li>Verify manuscript compliance on <strong>IEEE PDF eXpress</strong> and update status (`Pending`, `Passed`, `Failed`).</li>
                    <li>If EDAS issues exist: Use <strong>Quick Error Preset Buttons</strong> or write custom EDAS error notes.</li>
                    <li>If corrections needed: Click <strong>Return to Editorial</strong>.</li>
                    <li>If paper is valid: Click <strong>Approve &amp; Ready for EDAS</strong>.</li>
                    <li>After Editor completes EDAS upload: Verify EDAS reference and click <strong>Approve EDAS &amp; Mark Completed (Done)</strong>.</li>
                </ol>
            </div>

            <!-- Feature 1: IEEE PDF eXpress -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>IEEE PDF eXpress Status Management</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Reviewers are responsible for verifying official IEEE PDF eXpress certification.
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">PDF eXpress Status Options:</strong>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>🟡 <strong>Pending:</strong> Manuscript is queued for verification or pending inspection.</li>
                        <li>🟢 <strong>✓ Passed / Done:</strong> Manuscript has passed IEEE PDF eXpress compatibility verification.</li>
                        <li>🔴 <strong>✕ Failed / Error:</strong> Manuscript failed PDF eXpress verification (unembedded fonts, incorrect margins, etc.).</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 2: EDAS Error Notes & Presets -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>EDAS Error Notes &amp; Quick Preset Buttons</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    To accelerate logging EDAS upload issues, Reviewers can click quick preset buttons:
                </p>
                <div class="bg-rose-50/50 p-4 rounded-xl border border-rose-200 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">EDAS Error Preset Buttons:</strong>
                    <ul class="list-disc pl-5 space-y-1.5">
                        <li><code>+ Page Size US Letter</code>: Adds paper size error message (not A4 size).</li>
                        <li><code>+ Min 5 Pages</code>: Adds minimum length error message (less than 5 filled pages).</li>
                        <li><code>+ Doubleblind Author Visible</code>: Adds visible author identity error message for double-blind conferences.</li>
                        <li><code>+ IEEE Copyright Missing</code>: Adds missing IEEE copyright form error message.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 3: Reviewer Actions & Final EDAS Approval -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Final EDAS Approval &amp; Completion</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Approve &amp; Ready for EDAS:</strong> Advances paper status to <em>Ready for EDAS</em> so the Editor can perform final upload to EDAS.</li>
                        <li><strong>Approve EDAS &amp; Mark Completed:</strong> After Editor records EDAS ID reference, Reviewer verifies the upload. Click <strong>Approve EDAS &amp; Mark Completed</strong> to conclude paper workflow to <strong>Done</strong>.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
