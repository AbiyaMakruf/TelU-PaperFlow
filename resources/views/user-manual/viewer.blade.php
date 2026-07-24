<x-layouts.app title="Viewer Manual · Paperflow" heading="Viewer Guide">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'viewer'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-slate-600">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-neutral text-xs font-black mb-2">Viewer Staff Role (Read-Only)</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Viewer</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Guide for inspecting conference telemetry progress, submission statistics, read-only paper lists, and staff PIC workload matrices.</p>
            </div>

            <!-- Feature 1: Dashboard Telemetry -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>Dashboard Telemetry Monitoring (`/dashboard`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Viewers can monitor real-time manuscript telemetry on active conference dashboards:
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Top Statistic Cards:</strong> Displays Total Papers, New Submissions, Editorial Review, Reviewer Review, Ready for EDAS, and Done counters.</li>
                        <li><strong>Graphs &amp; Format Distribution:</strong> Inspect percentage of Word (DOCX) vs LaTeX (ZIP) submissions.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 2: Workload Summary Matrix -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Staff PIC Workload Matrix</span>
                </h3>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Staff PIC Table:</strong> Displays active manuscript counts handled by each Editor and Reviewer.</li>
                        <li><strong>Staff Contact Modal:</strong> Click staff name to view contact info modal (Email, WhatsApp, Affiliation, and Conference Roles).</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 3: Read-Only Paper List -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Read-Only Paper Search &amp; Filter (`/papers`)</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Paper Search:</strong> Search manuscripts by Paper ID, Title, or Corresponding Author Name.</li>
                        <li><strong>Read-Only Access:</strong> Viewers can inspect status and paper details without edit or status transition permissions.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
