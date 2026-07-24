<x-layouts.app title="Editorial Manual · Paperflow" heading="Editorial Guide">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'editorial'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-navy">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-primary text-xs font-black mb-2">Editorial Staff Role</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Editorial</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Complete guide for 16-point IEEE manuscript compliance inspection, author communication, automated revision feedback generator, and file versioning.</p>
            </div>

            <!-- Flowchart Overview -->
            <div class="rounded-2xl bg-warm/80 p-4 sm:p-5 border border-navy/10 text-xs sm:text-sm">
                <h3 class="font-extrabold text-navy mb-2 text-sm">🔄 Editor PIC Primary Workflow:</h3>
                <ol class="list-decimal pl-5 space-y-1.5 text-slate-800">
                    <li>Open <strong>Papers (`/papers`)</strong> &rarr; Select a paper assigned to you.</li>
                    <li>Inspect manuscript compliance using the <strong>16 IEEE Compliance Checklist</strong>.</li>
                    <li>If formatting errors exist: Click <code>⚡ Use Revision Template (Unchecked Items)</code> to build an auto-formatted message.</li>
                    <li>Send correction message to author via <strong>Email CC Chips</strong> or <strong>WhatsApp Direct Action</strong> (`wa.me`).</li>
                    <li>Update paper status to <strong>Request Author Revision</strong>.</li>
                    <li>After author submits revision: Re-verify checklist, confirm 100% valid, then click <strong>Send to Reviewer</strong>.</li>
                </ol>
            </div>

            <!-- Feature 1: Checklist 16 IEEE -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>16 IEEE Compliance Checklist Inspection</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Every paper features 16 standard IEEE formatting items. Each item includes a <strong>Guidance Accordion</strong> detailing exact compliance rules.
                </p>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">Inspection Steps:</strong>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Open <strong>Editorial Checklist</strong> card on paper details page.</li>
                        <li>Click <code>Guidance Accordion +</code> to read official per-item inspection instructions.</li>
                        <li>Check items meeting standard requirements, and enter specific item notes if needed.</li>
                        <li>Click <strong>Save Checklist</strong> to record progress.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 2: Auto-Generate Revision Template -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Automated Revision Drafts (`⚡ Unchecked Items`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Editors do not need to retype correction points manually!
                </p>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <strong class="text-navy block font-bold">How to Use:</strong>
                    <ol class="list-decimal pl-5 space-y-1">
                        <li>Leave non-compliant items unchecked.</li>
                        <li>Click <code>⚡ Use Revision Template (Unchecked Items)</code> at the bottom of the checklist form.</li>
                        <li>System automatically extracts titles and guidance notes from unchecked items and populates the <strong>Author Feedback</strong> box neatly.</li>
                    </ol>
                </div>
            </div>

            <!-- Feature 3: Author Communication (CC Email & WhatsApp) -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Author Communication (Email Chips &amp; WhatsApp Direct)</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1.5">
                        <li>
                            <strong>Two Note Types:</strong>
                            <br>&bull; 🔒 <em>Internal Notes:</em> Confidential for editorial/reviewer team (never sent to author).
                            <br>&bull; 📩 <em>Author Feedback:</em> Visible to author on portal token and sendable via Email/WhatsApp.
                        </li>
                        <li><strong>Interactive CC Email Chips:</strong> Type additional CC emails and press comma/Enter. Default conference CC addresses auto-populate as removable chips.</li>
                        <li><strong>WhatsApp Direct Action (`wa.me`):</strong> Clicking <code>📱 Send via WhatsApp ↗</code> automatically launches WhatsApp prefilled with the feedback message.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 4: Versioning File & Forward to Reviewer -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>4.</span> <span>File Versioning &amp; Forwarding to Reviewer</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>File Versioning:</strong> Editor can upload new file versions, download older files, inline preview DOCX/PDF, and mark final versions (`🏁 Final`).</li>
                        <li><strong>Send to Reviewer:</strong> Once all 16 checklist items are validly checked, the <strong>Send to Reviewer</strong> action becomes active to transfer the paper to the Reviewer PIC.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
