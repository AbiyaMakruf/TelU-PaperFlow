<x-layouts.app title="Superadmin Manual · Paperflow" heading="Superadmin Guide">
    <div class="max-w-5xl space-y-6">
        @include('user-manual._header', ['activeRole' => 'superadmin'])

        <section class="card p-6 sm:p-8 space-y-6 border-l-4 border-l-rose-600">
            <div class="border-b border-navy/10 pb-4">
                <span class="badge badge-danger text-xs font-black mb-2">Superadmin Full Access</span>
                <h2 class="text-xl sm:text-2xl font-black text-navy">📖 User Manual: Superadmin</h2>
                <p class="text-xs sm:text-sm text-muted mt-1">Full operational guide for user management, conference provisioning, infrastructure monitoring, audit logs, and account impersonation.</p>
            </div>

            <!-- Feature 1: User Management -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>1.</span> <span>User Management (`/admin/users`)</span>
                </h3>
                <div class="bg-slate-50 p-4 rounded-xl border border-navy/10 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Username-Only Account Creation:</strong> Superadmin can create new user accounts using only <strong>Full Name</strong> and <strong>Username</strong>.</li>
                        <li><strong>Temporary Password (`user1234`):</strong> New accounts automatically receive temporary password `user1234`. On first login, the system prompts the user to enter their personal email and choose a new password (min 8 characters).</li>
                        <li><strong>Reset User Password:</strong> Reset forgotten user passwords back to default `user1234`.</li>
                        <li><strong>CLI Bootstrap Superadmin:</strong> Terminal command <code>php artisan paperflow:make-superadmin username --email=admin@example.com --name="Super Admin"</code>.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 2: Provisioning New Conference -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>2.</span> <span>Provisioning New Conferences (`/conferences/create`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    When Superadmin creates a new conference, the <code>ConferenceProvisioner</code> service automatically provisions default assets:
                </p>
                <div class="bg-amber-50/50 p-4 rounded-xl border border-orange/20 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Generates 16 standard IEEE Editorial Compliance Checklist items.</li>
                        <li>Generates standard IEEE Reviewer Checklist items.</li>
                        <li>Provides standard branded HTML email templates.</li>
                        <li>Publishes initial version 1 submission form.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 3: Monitoring & Audit Logs Hub -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>3.</span> <span>Operational Monitoring &amp; Audit Logs (`/monitoring`)</span>
                </h3>
                <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>System &amp; Storage Status:</strong> Displays Supabase PostgreSQL connection status (driver, host, latency, total record count per table), PHP `ext-zip` status, and file storage metrics.</li>
                        <li><strong>Failed Queue Jobs &amp; Error Logs:</strong> Review failed queued emails, Laravel error log tracebacks, and click <strong>Retry Job</strong>.</li>
                        <li><strong>Staff Activity Audit Logs:</strong> Review all critical data changes performed by staff across all conferences with an interactive JSON diff viewer.</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 4: User Impersonation -->
            <div class="space-y-3">
                <h3 class="text-lg font-black text-navy flex items-center gap-2">
                    <span>4.</span> <span>User Impersonation (`/admin/users/{user}/impersonate`)</span>
                </h3>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Superadmin can impersonate any user account to diagnose technical issues or view staff perspectives directly.
                </p>
                <div class="bg-rose-50/50 p-4 rounded-xl border border-rose-200 space-y-2 text-xs text-slate-800">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Go to Users list &rarr; Click <code>Impersonate</code> next to target user.</li>
                        <li>System switches securely to the target user's session. A yellow impersonation banner appears at the top of the screen.</li>
                        <li>Click <strong>Return to Superadmin Account</strong> on the top banner to return to your main account without entering passwords again.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
