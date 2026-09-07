<x-layouts.app :title="'Email Broadcast Center · '.$activeConference->name">
    <div class="w-full space-y-6" x-data="broadcastCenter({
        initialSegment: '{{ $initialSegment }}',
        audienceUrl: '{{ route('conferences.broadcast.audience', $activeConference) }}',
        testSendUrl: '{{ route('conferences.broadcast.test', $activeConference) }}',
        csrfToken: '{{ csrf_token() }}',
        conferenceName: '{{ addslashes($activeConference->name) }}',
        defaultTestEmail: '{{ addslashes(auth()->user()->email ?? '') }}'
    })" x-init="init()">
        <x-conference-header :conference="$activeConference" active="broadcast" />

        <!-- Header Card -->
        <div class="card p-6 bg-white border border-slate-200 text-navy shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="space-y-1.5 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="badge bg-orange text-white text-xs font-black uppercase tracking-wider px-2.5 py-1">Mass Outreach</span>
                        <span class="badge bg-navy/10 text-navy text-xs font-bold px-2.5 py-1">📌 {{ $activeConference->name }}</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-navy">Conference Email Broadcast Center</h1>
                    <p class="text-xs text-slate-600 leading-relaxed max-w-3xl">
                        Send targeted, personalized notification and reminder emails to paper authors. Supports dual inputs (copy-pasting Paper IDs &amp; CSV upload), flexible recipient scopes, Google Form payment links, and queued delivery.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-xs">
                        <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-slate-600 font-medium">Database Pool:</span>
                        <span class="font-extrabold text-navy">{{ $stats['total_papers'] }} papers</span>
                        <span class="text-slate-300">|</span>
                        <span class="text-emerald-700 font-bold">{{ $stats['submitted_papers'] }} submitted</span>
                        <span class="text-slate-300">|</span>
                        <span class="text-rose-700 font-bold">{{ $stats['missing_papers'] }} missing</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <form id="broadcast-form" method="POST" action="{{ route('conferences.broadcast.send', $activeConference) }}" @submit="confirmSend($event)">
            @csrf
            <input type="hidden" name="segment" :value="segment">
            <input type="hidden" name="manuscript_filter" :value="manuscriptFilter">
            <input type="hidden" name="custom_paper_ids" :value="customPaperIds">
            <input type="hidden" name="recipient_scope" :value="recipientScope">

            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Left Column: Step 1 (Audience Selection) & Step 2 (Message & Templates) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- STEP 1: TARGET AUDIENCE -->
                    <div class="card p-6 bg-white border border-slate-200 shadow-sm space-y-5">
                        <div class="flex items-center justify-between border-b border-navy/10 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="size-6 rounded-full bg-navy text-white text-xs font-black flex items-center justify-center">1</span>
                                <h2 class="text-sm font-black text-navy uppercase tracking-wider">Select Target Audience</h2>
                            </div>
                            <span class="text-xs font-bold text-muted" x-text="isLoadingAudience ? 'Refreshing...' : audienceSummaryText"></span>
                        </div>

                        <!-- Target Group / Segment Dropdown -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Target Group / Segment</label>
                            <select x-model="segment" @change="fetchAudience()" class="form-select text-xs w-full bg-white font-semibold">
                                <option value="all">All Papers (Entire Conference Pool)</option>
                                <option value="missing_edas">Missing in Paperflow (From EDAS)</option>
                                <option value="submitted">Submitted in Paperflow</option>
                                <option value="custom">Secretariat List (Custom Paper IDs / CSV)</option>
                            </select>
                        </div>

                        <!-- Secretariat List Panel (Copy-Paste / CSV) -->
                        <div x-show="segment === 'custom'" x-cloak class="p-4 bg-amber-50/70 border border-amber-200 rounded-2xl space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-black text-amber-950 flex items-center gap-1.5">
                                    <span>📋 Input Secretariat Paper IDs</span>
                                </h3>
                                <span class="text-[11px] text-amber-800">Direct paste or file upload</span>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-amber-900">
                                    Option A: Paste Paper IDs (from Excel, WhatsApp, etc.)
                                </label>
                                <textarea x-model="customPaperIds" @input.debounce.500ms="fetchAudience()" rows="3" placeholder="e.g. 1570123456, 1570789012 or paste a column from Excel..." class="form-input text-xs font-mono w-full bg-white"></textarea>
                                <p class="text-[11px] text-amber-700">Accepts comma, newline, tab, semicolon, or space-separated Paper IDs.</p>
                            </div>

                            <div class="border-t border-amber-200/80 pt-3 space-y-2">
                                <label class="block text-xs font-bold text-amber-900">
                                    Option B: Upload CSV or TXT File
                                </label>
                                <div class="flex items-center gap-3">
                                    <input type="file" accept=".csv,.txt" @change="handleFileUpload($event)" class="text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy/80 cursor-pointer">
                                    <span x-show="uploadedFileName" class="text-xs font-bold text-emerald-700" x-text="'📎 ' + uploadedFileName"></span>
                                </div>
                                <p class="text-[11px] text-amber-700">Auto-detects <code>#</code>, <code>Paper ID</code>, <code>Paper Code</code>, or uses the first column.</p>
                            </div>
                        </div>

                        <!-- Cross-Filters & Recipient Scope (Both as styled Radio Button Groups) -->
                        <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                            <!-- Manuscript Status Filter Radio -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Manuscript Upload Status</label>
                                <div class="space-y-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="radio" name="manuscript_radio" value="all" x-model="manuscriptFilter" @change="fetchAudience()" class="text-orange focus:ring-orange">
                                        <span class="font-bold text-navy">All matching papers</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="radio" name="manuscript_radio" value="only_uploaded" x-model="manuscriptFilter" @change="fetchAudience()" class="text-orange focus:ring-orange">
                                        <span class="font-bold text-navy">Only if ALREADY uploaded</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="radio" name="manuscript_radio" value="only_missing" x-model="manuscriptFilter" @change="fetchAudience()" class="text-orange focus:ring-orange">
                                        <span class="font-bold text-navy">Only if NOT YET uploaded</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Recipient Scope Toggle -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Recipient Scope</label>
                                <div class="space-y-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="radio" name="scope_radio" value="corresponding_only" x-model="recipientScope" @change="fetchAudience()" class="text-orange focus:ring-orange">
                                        <span class="font-bold text-navy">First / Corresponding Author Only</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer text-xs">
                                        <input type="radio" name="scope_radio" value="all_authors" x-model="recipientScope" @change="fetchAudience()" class="text-orange focus:ring-orange">
                                        <span class="font-bold text-navy">All Authors (Co-authors included)</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Live Audience Match Summary & Exclusion Toggle -->
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-navy">Target Audience Match:</span>
                                    <span class="badge bg-navy text-white text-xs font-bold px-2 py-0.5" x-text="matchedPapersCount + ' Papers'"></span>
                                    <span class="badge bg-emerald-700 text-white text-xs font-bold px-2 py-0.5" x-text="totalRecipientsCount + ' Email Recipients'"></span>
                                </div>
                                <button type="button" @click="showPaperList = !showPaperList" class="text-xs font-bold text-orange hover:underline flex items-center gap-1">
                                    <span x-text="showPaperList ? 'Hide Paper List ▲' : 'Inspect & Exclude Papers ▼'"></span>
                                </button>
                            </div>

                            <!-- Expandable Paper Items Table (Horizontally contained) -->
                            <div x-show="showPaperList" x-cloak class="max-h-64 overflow-y-auto overflow-x-auto border border-slate-200 rounded-xl bg-white">
                                <table class="w-full table-fixed text-left text-xs">
                                    <thead class="bg-slate-100 text-slate-700 font-bold sticky top-0">
                                        <tr>
                                            <th class="p-2 w-10 text-center">
                                                <input type="checkbox" :checked="isAllSelected()" @change="toggleSelectAll($event)">
                                            </th>
                                            <th class="p-2 w-32">Paper ID</th>
                                            <th class="p-2">Paper Title</th>
                                            <th class="p-2 w-48 sm:w-60">Authors &amp; Recipients</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                        <template x-for="paper in papers" :key="paper.key">
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="p-2 text-center">
                                                    <input type="checkbox" name="selected_keys[]" :value="paper.key" :checked="selectedKeys.includes(paper.key)" @change="togglePaper(paper.key)">
                                                </td>
                                                <td class="p-2 font-mono font-bold text-navy truncate" :title="paper.paper_id" x-text="paper.paper_id"></td>
                                                <td class="p-2 truncate" :title="paper.paper_title" x-text="paper.paper_title"></td>
                                                <td class="p-2 truncate">
                                                    <div class="space-y-0.5 min-w-0">
                                                        <p class="font-bold text-slate-800 truncate" :title="paper.first_author_name" x-text="paper.first_author_name"></p>
                                                        <p class="text-[11px] text-slate-500 font-mono truncate" :title="paper.recipients.join(', ')" x-text="paper.recipients.join(', ') || 'No valid email'"></p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="papers.length === 0">
                                            <td colspan="4" class="p-4 text-center text-xs text-muted">No papers match the current selection criteria.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: MESSAGE CONTENT & TEMPLATES -->
                    <div class="card p-6 bg-white border border-slate-200 shadow-sm space-y-5">
                        <div class="flex items-center justify-between border-b border-navy/10 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="size-6 rounded-full bg-navy text-white text-xs font-black flex items-center justify-center">2</span>
                                <h2 class="text-sm font-black text-navy uppercase tracking-wider">Email Template &amp; Content</h2>
                            </div>
                            <span class="text-xs font-bold text-muted">Presets &amp; Customization</span>
                        </div>

                        <!-- Quick Template Preset Buttons -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700">Quick Template Presets (Academic English)</label>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <button type="button" @click="applyTemplate('unregistered')" class="btn border border-slate-200 bg-slate-50 hover:bg-orange/10 hover:border-orange text-navy text-xs font-bold py-2 px-2.5 text-left transition rounded-xl flex items-center gap-1.5">
                                    <span>💳</span>
                                    <span class="truncate">1. Unpaid / Registration</span>
                                </button>
                                <button type="button" @click="applyTemplate('missing_manuscript')" class="btn border border-slate-200 bg-slate-50 hover:bg-orange/10 hover:border-orange text-navy text-xs font-bold py-2 px-2.5 text-left transition rounded-xl flex items-center gap-1.5">
                                    <span>📄</span>
                                    <span class="truncate">2. Manuscript Reminder</span>
                                </button>
                                <button type="button" @click="applyTemplate('paid_no_manuscript')" class="btn border border-slate-200 bg-slate-50 hover:bg-orange/10 hover:border-orange text-navy text-xs font-bold py-2 px-2.5 text-left transition rounded-xl flex items-center gap-1.5">
                                    <span>⚠️</span>
                                    <span class="truncate">3. Paid, No Manuscript</span>
                                </button>
                                <button type="button" @click="applyTemplate('uploaded_no_payment')" class="btn border border-slate-200 bg-slate-50 hover:bg-orange/10 hover:border-orange text-navy text-xs font-bold py-2 px-2.5 text-left transition rounded-xl flex items-center gap-1.5">
                                    <span>⏳</span>
                                    <span class="truncate">4. Uploaded, No Payment</span>
                                </button>
                                <button type="button" @click="applyTemplate('announcement')" class="btn border border-slate-200 bg-slate-50 hover:bg-orange/10 hover:border-orange text-navy text-xs font-bold py-2 px-2.5 text-left transition rounded-xl flex items-center gap-1.5">
                                    <span>📢</span>
                                    <span class="truncate">5. Custom Announcement</span>
                                </button>
                            </div>
                        </div>

                        <!-- Google Form / Payment Link Input -->
                        <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl space-y-1.5">
                            <label class="block text-xs font-extrabold text-blue-950 flex items-center justify-between">
                                <span>🔗 Payment / Registration Google Form Link</span>
                                <span class="font-mono text-[10px] text-blue-700 font-normal">Replaces @{{payment_link}}</span>
                            </label>
                            <input type="url" name="payment_link" x-model="paymentLink" placeholder="https://forms.google.com/..." class="form-input text-xs w-full bg-white">
                            <p class="text-[11px] text-blue-700">Enter the committee registration Google Form or payment portal link.</p>
                        </div>

                        <!-- Dynamic Placeholder Chips -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Click tag to insert into email:</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" @click="insertTag('@{{author_name}}')" class="badge bg-slate-100 hover:bg-orange/20 text-navy font-mono text-[11px] py-1 px-2 cursor-pointer transition">@{{author_name}}</button>
                                <button type="button" @click="insertTag('@{{paper_id}}')" class="badge bg-slate-100 hover:bg-orange/20 text-navy font-mono text-[11px] py-1 px-2 cursor-pointer transition">@{{paper_id}}</button>
                                <button type="button" @click="insertTag('@{{paper_title}}')" class="badge bg-slate-100 hover:bg-orange/20 text-navy font-mono text-[11px] py-1 px-2 cursor-pointer transition">@{{paper_title}}</button>
                                <button type="button" @click="insertTag('@{{conference_name}}')" class="badge bg-slate-100 hover:bg-orange/20 text-navy font-mono text-[11px] py-1 px-2 cursor-pointer transition">@{{conference_name}}</button>
                                <button type="button" @click="insertTag('@{{portal_url}}')" class="badge bg-slate-100 hover:bg-orange/20 text-navy font-mono text-[11px] py-1 px-2 cursor-pointer transition">@{{portal_url}}</button>
                                <button type="button" @click="insertTag('@{{payment_link}}')" class="badge bg-slate-100 hover:bg-orange/20 text-navy font-mono text-[11px] py-1 px-2 cursor-pointer transition">@{{payment_link}}</button>
                            </div>
                        </div>

                        <!-- Email Subject -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700">Email Subject</label>
                            <input type="text" name="subject" x-model="subject" required class="form-input text-xs w-full font-semibold">
                        </div>

                        <!-- Email Body -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700">Email Message Body</label>
                            <textarea id="email-body-input" name="body" x-model="body" rows="12" required class="form-input text-xs font-mono leading-relaxed w-full"></textarea>
                            <p class="text-[11px] text-muted">Personalized tags will be automatically replaced with the respective paper and author information upon dispatch.</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Step 3 (Live Preview, Test Send & Launch) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="card p-6 bg-white border border-slate-200 shadow-sm space-y-5 sticky top-6">
                        <div class="flex items-center justify-between border-b border-navy/10 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="size-6 rounded-full bg-navy text-white text-xs font-black flex items-center justify-center">3</span>
                                <h2 class="text-sm font-black text-navy uppercase tracking-wider">Live Preview &amp; Blast</h2>
                            </div>
                            <span class="badge bg-orange/10 text-orange text-xs font-extrabold">Instant Render</span>
                        </div>

                        <!-- Live Preview Frame (No action button at the end) -->
                        <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50/50 shadow-inner">
                            <div class="bg-navy p-3.5 text-white space-y-1">
                                <div class="flex items-center justify-between text-[11px] text-slate-300">
                                    <span>From: <strong>{{ $activeConference->email_sender_name ?: $activeConference->name }}</strong></span>
                                    <span>Sample View</span>
                                </div>
                                <h3 class="text-xs font-extrabold text-white truncate" x-text="renderedPreviewSubject || 'Subject Preview'"></h3>
                            </div>

                            <div class="p-4 bg-white min-h-[300px] text-xs text-slate-800 space-y-3 leading-relaxed whitespace-pre-line font-sans" x-text="renderedPreviewBody || 'Email body will render here...'">
                            </div>
                        </div>

                        <!-- Test Send Input & Button -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5">
                            <label class="block text-xs font-extrabold text-navy">
                                ✉️ Send Test Email
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="email" x-model="testEmail" placeholder="Enter destination email..." class="form-input text-xs w-full bg-white">
                                <button type="button" @click="sendTestEmail()" :disabled="isSendingTest" class="btn btn-secondary py-2 px-3 text-xs font-extrabold shrink-0 text-navy hover:text-orange transition shadow-2xs">
                                    <span x-show="!isSendingTest">Send Test</span>
                                    <span x-show="isSendingTest" x-cloak>Sending...</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500">You can customize the destination address above to test delivery in any inbox.</p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3 pt-1">
                            <!-- Mass Blast Confirmation Trigger Button -->
                            <button type="button" @click="openConfirmModal()" :disabled="totalRecipientsCount === 0 || isLoadingAudience" class="btn btn-primary w-full py-3.5 text-xs font-black shadow-md flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-700 text-white transition">
                                <span>🚀 Queue Broadcast to <strong x-text="totalRecipientsCount"></strong> Recipient(s)</span>
                            </button>

                            <p class="text-[11px] text-center text-slate-500 leading-tight">
                                Emails will be processed smoothly via Paperflow's background queue to respect SMTP rate limits.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy/60 backdrop-blur-xs p-4">
                <div @click.away="showModal = false" class="card bg-white max-w-md w-full p-6 space-y-5 shadow-2xl border border-navy/10 rounded-2xl">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 text-lg">
                            ⚠️
                        </div>
                        <div>
                            <h3 class="text-base font-black text-navy">Confirm Email Broadcast</h3>
                            <p class="text-xs text-muted">Please review before queuing mass emails</p>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl space-y-2 text-xs border border-slate-200">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Target Conference:</span>
                            <strong class="text-navy">{{ $activeConference->name }}</strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Matched Papers:</span>
                            <strong class="text-navy" x-text="matchedPapersCount + ' papers'"></strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Total Recipients:</span>
                            <strong class="text-rose-600 font-extrabold" x-text="totalRecipientsCount + ' email addresses'"></strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Recipient Scope:</span>
                            <strong class="text-navy" x-text="recipientScope === 'all_authors' ? 'All Authors' : 'First Author Only'"></strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Subject:</span>
                            <strong class="text-navy truncate max-w-[200px]" x-text="subject"></strong>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="showModal = false" class="btn btn-secondary text-xs font-bold py-2 px-4">
                            Cancel
                        </button>
                        <button type="button" @click="submitBroadcast()" class="btn bg-rose-600 hover:bg-rose-700 text-white text-xs font-black py-2 px-5 shadow-md flex items-center gap-2">
                            <span>🚀 Confirm &amp; Queue Broadcast</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Alpine.js Broadcast Center Component Logic -->
    <script>
@verbatim
        function broadcastCenter(config) {
            return {
                segment: config.initialSegment || 'all',
                manuscriptFilter: 'all',
                recipientScope: 'corresponding_only',
                customPaperIds: '',
                uploadedFileName: '',
                uploadedFile: null,
                paymentLink: '',
                testEmail: config.defaultTestEmail || '',
                subject: '',
                body: '',
                papers: [],
                selectedKeys: [],
                matchedPapersCount: 0,
                totalRecipientsCount: 0,
                isLoadingAudience: false,
                showPaperList: false,
                showModal: false,
                isSendingTest: false,

                templates: {
                    unregistered: {
                        subject: 'Important: Complete Registration & Payment for Paper {{paper_id}} - {{conference_name}}',
                        body: "Dear {{author_name}},\n\nThank you for submitting your manuscript to {{conference_name}}.\n\nOur records indicate that the conference registration and payment for your paper have not yet been finalized:\n\n• Paper ID: {{paper_id}}\n• Paper Title: {{paper_title}}\n\nTo ensure your paper is included in the conference schedule and proceedings, please complete your registration and submit proof of payment via the link below as soon as possible:\n\nPayment & Registration Link:\n{{payment_link}}\n\nIf you have already made the payment, please ensure your payment slip has been submitted through the form above.\n\nThank you for your cooperation.\n\nBest regards,\nOrganizing Committee\n{{conference_name}}"
                    },
                    missing_manuscript: {
                        subject: 'Action Required: Submit Editable Manuscript for Paper {{paper_id}} - {{conference_name}}',
                        body: "Dear {{author_name}},\n\nWe would like to remind you that the editable manuscript (.docx / camera-ready file) for your accepted paper has not yet been received in our system:\n\n• Paper ID: {{paper_id}}\n• Paper Title: {{paper_title}}\n\nPlease access the submission portal and upload your latest editable manuscript to proceed with the editorial verification:\n\nSubmission Portal:\n{{portal_url}}\n\nPlease complete your upload before the upcoming deadline to prevent delays in the publication process.\n\nSincerely,\nEditorial & Publication Committee\n{{conference_name}}"
                    },
                    paid_no_manuscript: {
                        subject: 'Payment Confirmed: Please Upload Your Editable Manuscript for Paper {{paper_id}}',
                        body: "Dear {{author_name}},\n\nThank you for completing the registration payment for your paper in {{conference_name}}:\n\n• Paper ID: {{paper_id}}\n• Paper Title: {{paper_title}}\n\nWhile your payment has been confirmed, we have not yet received your final editable manuscript file (.docx / .zip). To proceed with formatting review and EDAS finalization, please submit your file via the link below:\n\nSubmission Portal:\n{{portal_url}}\n\nThank you for your prompt attention.\n\nWarm regards,\nEditorial Committee\n{{conference_name}}"
                    },
                    uploaded_no_payment: {
                        subject: 'Manuscript Received: Pending Registration Payment for Paper {{paper_id}} - {{conference_name}}',
                        body: "Dear {{author_name}},\n\nWe have successfully received the editable manuscript for your paper:\n\n• Paper ID: {{paper_id}}\n• Paper Title: {{paper_title}}\n\nHowever, according to our secretariat records, the registration fee for this paper remains unpaid. Please finalize your registration payment and submit the payment slip via the following link:\n\nRegistration & Payment Form:\n{{payment_link}}\n\nKindly note that only registered and paid papers will be scheduled for presentation and included in the final proceedings.\n\nBest regards,\nOrganizing Committee\n{{conference_name}}"
                    },
                    announcement: {
                        subject: 'Important Announcement regarding Paper {{paper_id}} - {{conference_name}}',
                        body: "Dear {{author_name}},\n\nWe are writing to share an important announcement regarding {{conference_name}} and your submission:\n\n• Paper ID: {{paper_id}}\n• Paper Title: {{paper_title}}\n\n[Please insert your announcement message, presentation schedule, or certificate information here]\n\nYou may check the latest status of your submission through the author portal:\n{{portal_url}}\n\nShould you have any questions, please do not hesitate to contact our secretariat.\n\nBest regards,\nSecretariat\n{{conference_name}}"
                    }
                },

                init() {
                    this.applyTemplate('unregistered');
                    this.fetchAudience();
                },

                setSegment(seg) {
                    this.segment = seg;
                    this.fetchAudience();
                },

                applyTemplate(key) {
                    if (this.templates[key]) {
                        this.subject = this.templates[key].subject;
                        this.body = this.templates[key].body;
                    }
                },

                insertTag(tag) {
                    const el = document.getElementById('email-body-input');
                    if (!el) {
                        this.body += ' ' + tag;
                        return;
                    }
                    const start = el.selectionStart;
                    const end = el.selectionEnd;
                    this.body = this.body.substring(0, start) + tag + this.body.substring(end);
                    this.$nextTick(() => {
                        el.focus();
                        el.setSelectionRange(start + tag.length, start + tag.length);
                    });
                },

                get audienceSummaryText() {
                    return `${this.matchedPapersCount} paper(s) matched (${this.totalRecipientsCount} recipient(s))`;
                },

                get renderedPreviewSubject() {
                    return this.subject
                        .replace(/\{\{conference\}\}/g, config.conferenceName)
                        .replace(/\{\{conference_name\}\}/g, config.conferenceName)
                        .replace(/\{\{paper_id\}\}/g, '#1570123456')
                        .replace(/\{\{paper_code\}\}/g, '#1570123456')
                        .replace(/\{\{paper_title\}\}/g, 'Design and Implementation of Edge Computing Architecture')
                        .replace(/\{\{author_name\}\}/g, 'Prof. Jane Doe');
                },

                get renderedPreviewBody() {
                    const payLink = this.paymentLink || 'https://forms.google.com/sample-conference-registration';
                    return this.body
                        .replace(/\{\{conference\}\}/g, config.conferenceName)
                        .replace(/\{\{conference_name\}\}/g, config.conferenceName)
                        .replace(/\{\{paper_id\}\}/g, '#1570123456')
                        .replace(/\{\{paper_code\}\}/g, '#1570123456')
                        .replace(/\{\{paper_title\}\}/g, 'Design and Implementation of Edge Computing Architecture')
                        .replace(/\{\{author_name\}\}/g, 'Prof. Jane Doe')
                        .replace(/\{\{portal_url\}\}/g, 'https://paperflow.id/submission/access/demo-token')
                        .replace(/\{\{payment_link\}\}/g, payLink);
                },

                handleFileUpload(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    this.uploadedFileName = file.name;
                    this.uploadedFile = file;
                    this.fetchAudience();
                },

                fetchAudience() {
                    this.isLoadingAudience = true;
                    const formData = new FormData();
                    formData.append('_token', config.csrfToken);
                    formData.append('segment', this.segment);
                    formData.append('manuscript_filter', this.manuscriptFilter);
                    formData.append('recipient_scope', this.recipientScope);
                    formData.append('custom_paper_ids', this.customPaperIds);
                    if (this.uploadedFile) {
                        formData.append('csv_file', this.uploadedFile);
                    }

                    fetch(config.audienceUrl, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.isLoadingAudience = false;
                        if (data.success) {
                            this.papers = data.papers || [];
                            this.matchedPapersCount = data.paper_count || 0;
                            this.totalRecipientsCount = data.recipient_count || 0;
                            this.selectedKeys = this.papers.map(p => p.key);
                        }
                    })
                    .catch(() => {
                        this.isLoadingAudience = false;
                    });
                },

                isAllSelected() {
                    return this.papers.length > 0 && this.selectedKeys.length === this.papers.length;
                },

                toggleSelectAll(e) {
                    if (e.target.checked) {
                        this.selectedKeys = this.papers.map(p => p.key);
                    } else {
                        this.selectedKeys = [];
                    }
                    this.recalcRecipientCount();
                },

                togglePaper(key) {
                    const idx = this.selectedKeys.indexOf(key);
                    if (idx > -1) {
                        this.selectedKeys.splice(idx, 1);
                    } else {
                        this.selectedKeys.push(key);
                    }
                    this.recalcRecipientCount();
                },

                recalcRecipientCount() {
                    let total = 0;
                    this.papers.forEach(p => {
                        if (this.selectedKeys.includes(p.key)) {
                            total += (p.recipients ? p.recipients.length : 0);
                        }
                    });
                    this.totalRecipientsCount = total;
                },

                sendTestEmail() {
                    if (!this.subject || !this.body) {
                        alert('Please provide a subject and message body first.');
                        return;
                    }
                    if (!this.testEmail) {
                        alert('Please provide a destination email address.');
                        return;
                    }
                    this.isSendingTest = true;
                    const payload = {
                        _token: config.csrfToken,
                        test_email: this.testEmail,
                        subject: this.subject,
                        body: this.body,
                        payment_link: this.paymentLink
                    };

                    fetch(config.testSendUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.isSendingTest = false;
                        alert(data.message || 'Test email queued successfully.');
                    })
                    .catch(() => {
                        this.isSendingTest = false;
                        alert('Failed to send test email. Please check server logs.');
                    });
                },

                openConfirmModal() {
                    if (this.totalRecipientsCount === 0) {
                        alert('No recipients are currently selected.');
                        return;
                    }
                    this.showModal = true;
                },

                submitBroadcast() {
                    this.showModal = false;
                    document.getElementById('broadcast-form').submit();
                },

                confirmSend(e) {
                    // Prevent normal submit so it only triggers through submitBroadcast modal
                    if (!this.showModal && e.submitter) {
                        e.preventDefault();
                        this.openConfirmModal();
                    }
                }
            };
        }
    @endverbatim
</script>
</x-layouts.app>
