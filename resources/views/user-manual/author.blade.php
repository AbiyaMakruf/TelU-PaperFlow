<x-layouts.public title="Author Guide · Paperflow">
    <div class="mx-auto max-w-5xl space-y-8">
        <!-- Navigation Header for Author Manual Page -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-navy/10 pb-5">
            <div>
                <p class="eyebrow !text-orange">Public Author Documentation Center</p>
                <h1 class="text-3xl sm:text-4xl font-black text-navy leading-tight">Author User Manual</h1>
                <p class="text-sm text-muted mt-1">Official guide for manuscript submission, Live Checklist monitoring, and revision uploads in Paperflow.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('home') }}" class="btn btn-secondary text-xs font-extrabold">&larr; Home</a>
                @auth
                    <a href="{{ route('user-manual.index') }}" class="btn btn-primary text-xs font-extrabold">Staff Manual &rarr;</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary text-xs font-extrabold">Staff Login &rarr;</a>
                @endauth
            </div>
        </div>

        <!-- Main Step by Step Manual Sections -->
        <section class="space-y-6">
            <!-- Summary Step Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-5 border-l-4 border-l-orange space-y-2">
                    <span class="badge badge-primary text-[10px] font-black">Step 1</span>
                    <h3 class="font-black text-navy text-base">Submission Form</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Fill in Paper ID, author &amp; co-authors data, and upload editable manuscript files (DOCX / LaTeX ZIP).</p>
                </div>
                <div class="card p-5 border-l-4 border-l-navy space-y-2">
                    <span class="badge badge-warning text-[10px] font-black">Step 2</span>
                    <h3 class="font-black text-navy text-base">Private Portal Token</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Receive a private portal link via email to monitor real-time paper status progression.</p>
                </div>
                <div class="card p-5 border-l-4 border-l-emerald-500 space-y-2">
                    <span class="badge badge-success text-[10px] font-black">Step 3</span>
                    <h3 class="font-black text-navy text-base">Live Checklist &amp; Revision</h3>
                    <p class="text-xs text-slate-600 leading-relaxed">Inspect Editor IEEE checklist results, upload new revision manuscripts, and attach optional PDF response forms.</p>
                </div>
            </div>

            <!-- Detailed Step 1 -->
            <div class="card p-6 sm:p-8 space-y-4">
                <div class="border-b border-navy/10 pb-3 flex items-center justify-between">
                    <h2 class="text-xl font-black text-navy flex items-center gap-2">
                        <span class="size-7 rounded-full bg-orange text-white text-xs grid place-items-center font-black">1</span>
                        <span>New Manuscript Submission (Public Submission Form)</span>
                    </h2>
                    <span class="badge badge-primary text-xs font-bold">Initial Step</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    Every hosted conference has a public submission page located at URL format: <code>/{conference-slug}/submit</code>.
                </p>
                <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-navy/10 space-y-3 text-xs sm:text-sm text-slate-800">
                    <strong class="text-navy block font-bold text-sm">📋 Submission Form Guidelines:</strong>
                    <ul class="list-disc pl-5 space-y-2">
                        <li><strong>Conference Paper ID:</strong> Enter your official paper ID number from the conference registration system (e.g. EDAS ID or registration code).</li>
                        <li><strong>Corresponding Author Data:</strong> Enter Full Name, Active Email, and Phone/WhatsApp number (use international country code selector).</li>
                        <li><strong>Co-Authors Data:</strong> If paper is co-authored, click <em>+ Add Co-Author</em> and enter Name, Email, and Affiliation for each.</li>
                        <li><strong>Upload Editable Manuscript File:</strong> Upload Microsoft Word (<code>.docx</code>) manuscript or LaTeX source archive (<code>.zip</code>). Maximum size follows conference configuration (default 10 MB).</li>
                        <li><strong>Security Captcha Verification:</strong> Complete Cloudflare Turnstile verification if enabled by committee.</li>
                    </ul>
                </div>
            </div>

            <!-- Detailed Step 2 -->
            <div class="card p-6 sm:p-8 space-y-4">
                <div class="border-b border-navy/10 pb-3 flex items-center justify-between">
                    <h2 class="text-xl font-black text-navy flex items-center gap-2">
                        <span class="size-7 rounded-full bg-navy text-white text-xs grid place-items-center font-black">2</span>
                        <span>Accessing Author Private Portal (Token Portal)</span>
                    </h2>
                    <span class="badge badge-warning text-xs font-bold">Passwordless</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    After successful submission, you do not need to register a password-protected account! The system automatically issues a **Private Portal Token** sent via Email &amp; WhatsApp. URL format: <code>/submission/access/{token}</code>.
                </p>
                <div class="bg-amber-50/60 p-4 sm:p-5 rounded-2xl border border-orange/20 space-y-3 text-xs sm:text-sm text-slate-800">
                    <strong class="text-navy block font-bold text-sm">🔑 Author Portal Features:</strong>
                    <ul class="list-disc pl-5 space-y-2">
                        <li><strong>Progress Timeline Monitoring:</strong> Real-time paper progress tracking (Status: <em>Submitted &rarr; Validated &rarr; Editorial Review &rarr; Reviewer Review &rarr; Ready for EDAS &rarr; Done</em>).</li>
                        <li><strong>Update Submission Details:</strong> Edit paper title, email, phone number, or co-author list in case of typos.</li>
                        <li><strong>Download Document Versions:</strong> Inspect and download previous manuscript file versions.</li>
                    </ul>
                </div>
            </div>

            <!-- Detailed Step 3 -->
            <div class="card p-6 sm:p-8 space-y-4">
                <div class="border-b border-navy/10 pb-3 flex items-center justify-between">
                    <h2 class="text-xl font-black text-navy flex items-center gap-2">
                        <span class="size-7 rounded-full bg-emerald-600 text-white text-xs grid place-items-center font-black">3</span>
                        <span>Live Checklist Monitoring &amp; Revision Uploads</span>
                    </h2>
                    <span class="badge badge-success text-xs font-bold">Revision Process</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                    When the Editorial Team requests manuscript revisions, you will receive an email notification. Upon opening the portal, the Live IEEE Compliance Checklist Card will automatically appear.
                </p>
                <div class="bg-emerald-50/50 p-4 sm:p-5 rounded-2xl border border-emerald-200 space-y-3 text-xs sm:text-sm text-slate-800">
                    <strong class="text-navy block font-bold text-sm">📝 Steps to Submit Manuscript Revision:</strong>
                    <ol class="list-decimal pl-5 space-y-2">
                        <li>Review unchecked checklist items marked by the Editor as requiring attention.</li>
                        <li>Open the manuscript on your computer and apply formatting corrections per Editor instructions.</li>
                        <li>Open the Revision Upload section on your Author Portal.</li>
                        <li>Select the latest editable manuscript file (<code>.docx</code> / <code>.zip</code>).</li>
                        <li><em>(Highly Recommended)</em> Attach a <strong>Revision Guidance / Response PDF</strong> explaining your adjustments.</li>
                        <li>Click <strong>Submit Manuscript Revision</strong>. Status will automatically return to the Editorial Team for re-checking.</li>
                    </ol>
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
