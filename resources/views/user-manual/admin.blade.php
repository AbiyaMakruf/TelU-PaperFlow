<x-layouts.app title="Conference Admin Manual · Paperflow" heading="Conference Admin Guide">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'admin'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-orange">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-warning text-xs font-black mb-2">Conference Admin Staff Role</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Conference Admin</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Complete guide for conference setup, custom form builder, file storage providers, email templates, team management, and paper bulk actions.</p>
            </div>

            <!-- Feature 1: Workspace Selector -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>GCP-Style Active Workspace Selector</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Conference Admins can switch active conferences via the workspace selector in the header or mobile drawer. All paper views, editor performance statistics, and email logs will automatically be scoped to the active workspace.
                </p>
            </div>

            <!-- Feature 2: Form Builder -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Custom Form Builder (`/conferences/{id}/form`)</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Automatic Core Fields:</strong> Paper ID, paper title, corresponding author name/email/phone, co-authors data, and editable manuscript file are provided automatically by the system.</li>
                        <li><strong>Add Custom Fields:</strong> Add conference-specific fields (text, number, date, dropdown, radio, checkbox, textarea).</li>
                        <li><strong>Publish Form:</strong> Once draft layout is complete, click <strong>Publish Form</strong> to make it live on the public submission URL.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 3: Storage Provider Configuration -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>File Storage (`/conferences/{id}/drive`)</span>
                </h3>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Supabase Storage (Private Default):</strong> Stores files in private Supabase buckets secured with Signed URLs.</li>
                        <li><strong>Google Drive OAuth:</strong> Connects official conference Google Drive folders using OAuth2 authentication. Laravel manages authorization and download streaming securely.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 4: Custom Checklist IEEE & Email Templates -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>4.</span> <span>IEEE Checklist &amp; Conference Email Templates</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Editor &amp; Reviewer IEEE Checklists (`/conferences/{id}/checklists`):</strong> Add, edit, or toggle standard IEEE compliance inspection items.</li>
                        <li><strong>Branded Email Templates (`/conferences/{id}/email-templates`):</strong> Edit official HTML email templates, configure conference Default CC addresses, view live-preview templates, and send test emails.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 5: Manage Team & Bulk Actions -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>5.</span> <span>Manage Team Members &amp; Paper Bulk Actions (`/papers`)</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Team Management (`/conferences/{id}/members`):</strong> Add staff to the conference with Admin, Editorial, Reviewer, or Viewer roles.</li>
                        <li><strong>Bulk Assignment:</strong> Select multiple papers in the paper list to assign Editor PIC, Reviewer PIC, manuscript format, or deadline in bulk.</li>
                        <li><strong>Bulk Status &amp; Download ZIP:</strong> Update status in bulk (Validate, Reject, Withdraw) or download all selected author editable manuscripts in a single ZIP package named by Paper ID.</li>
                        <li><strong>Export Summary Reports:</strong> Download full submission summary reports to CSV, Excel, or printable PDF format.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
