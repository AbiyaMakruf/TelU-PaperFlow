<x-layouts.app title="My Profile" heading="My Profile">
    <div class="mx-auto max-w-4xl space-y-8">
        <div>
            <p class="eyebrow">Personal Identity &amp; Account Settings</p>
            <h1 class="page-title">Profile &amp; Account Settings</h1>
            <p class="page-subtitle">Manage your editorial identity details, username, email address with OTP verification, and account password.</p>
        </div>


        <!-- CARD 1: EDITORIAL IDENTITY INFORMATION -->
        <div class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy">
                    Editorial Identity Information
                </h2>
                <p class="text-xs text-muted mt-0.5">This identity is used in automated email signatures and editorial WhatsApp links.</p>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <label class="sm:col-span-2">
                    <span class="form-label">Full Name *</span>
                    <input class="form-input" name="name" value="{{ old('name', $user->name) }}" required placeholder="e.g. Dr. Budi Santoso, M.T.">
                </label>

                <label>
                    <span class="form-label">Publication Role / Job Title</span>
                    <input class="form-input" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="e.g. Publication Chair / Editor">
                </label>

                <label>
                    <span class="form-label">Institution / Affiliation</span>
                    <input class="form-input" name="affiliation" value="{{ old('affiliation', $user->affiliation) }}" placeholder="e.g. Telkom University">
                </label>

                <label>
                    <span class="form-label">WhatsApp Country Code</span>
                    <select class="form-input" name="whatsapp_country_code">
                        <option value="">Select Country Code...</option>
                        @foreach($countryCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('whatsapp_country_code', $user->whatsapp_country_code) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="form-label">WhatsApp Number</span>
                    <input class="form-input" name="whatsapp_number" value="{{ old('whatsapp_number', $user->whatsapp_number) }}" placeholder="81234567890">
                </label>

                <div class="sm:col-span-2 pt-2">
                    <button class="btn btn-primary w-full sm:w-auto">Save Profile Information</button>
                </div>
            </form>
        </div>

        <!-- CARD 2: USERNAME SETTINGS (WITH AVAILABILITY CHECK & CONFIRMATION MODAL) -->
        <div x-data="{
            username: '{{ old('username', $user->username) }}',
            initialUsername: '{{ $user->username }}',
            checking: false,
            available: null,
            checkMessage: '',
            showConfirmModal: false,
            async checkAvailability() {
                if (!this.username || this.username.length < 3) {
                    this.available = false;
                    this.checkMessage = 'Username must be at least 3 characters.';
                    return;
                }
                this.checking = true;
                this.checkMessage = '';
                try {
                    const res = await fetch('{{ route('profile.username.check') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ username: this.username })
                    });
                    const data = await res.json();
                    this.available = data.available;
                    this.checkMessage = data.message;
                } catch (e) {
                    this.available = false;
                    this.checkMessage = 'Failed to check username availability.';
                } finally {
                    this.checking = false;
                }
            },
            async trySubmit() {
                if (this.username === this.initialUsername) return;
                if (this.available !== true) {
                    await this.checkAvailability();
                }
                if (this.available === true) {
                    this.showConfirmModal = true;
                }
            }
        }" class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy">
                    Username Settings
                </h2>
                <p class="text-xs text-muted mt-0.5">Your username is used to log in to the Paperflow system.</p>
            </div>

            <form id="usernameForm" method="POST" action="{{ route('profile.username.update') }}" @submit.prevent="trySubmit()" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <span class="form-label">Account Username *</span>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mt-1">
                        <input class="form-input flex-1 font-mono font-bold text-navy" 
                               name="username" 
                               x-model="username" 
                               @input="available = null; checkMessage = ''"
                               required 
                               minlength="3" 
                               placeholder="your_username">
                        <button type="button" 
                                @click="checkAvailability()" 
                                :disabled="checking || !username || username === initialUsername"
                                class="btn btn-secondary text-xs shrink-0 py-2.5">
                            <span x-show="!checking">Check Availability</span>
                            <span x-show="checking">Checking...</span>
                        </button>
                    </div>

                    <!-- Availability Status Badge -->
                    <div class="mt-2 text-xs font-bold">
                        <template x-if="username === initialUsername">
                            <span class="text-slate-500">This is your current username.</span>
                        </template>
                        <template x-if="username !== initialUsername && checkMessage">
                            <span :class="available ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-rose-700 bg-rose-50 border-rose-200'" class="px-2.5 py-1 rounded-lg border inline-block">
                                <span x-text="available ? 'Available: ' : 'Unavailable: '"></span>
                                <span x-text="checkMessage"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            :disabled="username === initialUsername || available === false" 
                            class="btn btn-primary w-full sm:w-auto">
                        Change Username
                    </button>
                </div>
            </form>

            <!-- CONFIRMATION POPUP MODAL FOR USERNAME CHANGE -->
            <div x-show="showConfirmModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy/60 backdrop-blur-sm p-4">
                <div class="card w-full max-w-md p-6 bg-white shadow-2xl space-y-4 text-left" @click.outside="showConfirmModal = false">
                    <div class="border-b border-navy/10 pb-3">
                        <h3 class="text-base font-black text-navy">Confirm Username Change</h3>
                        <p class="text-xs text-muted">This action requires your confirmation</p>
                    </div>

                    <p class="text-sm text-slate-700 leading-relaxed">
                        Are you sure you want to change your username from <strong class="text-navy font-mono" x-text="initialUsername"></strong> to <strong class="text-emerald-700 font-mono" x-text="username"></strong>?
                    </p>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900 font-medium">
                        <strong>Note:</strong> After updating, you must use this new username (<strong><span x-text="username"></span></strong>) to log in to your Paperflow account next time.
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="showConfirmModal = false" class="btn btn-secondary text-xs">Cancel</button>
                        <button type="button" @click="$req = document.getElementById('usernameForm'); $req.submit()" class="btn btn-primary text-xs">Yes, Change Username</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 3: EMAIL ADDRESS SETTINGS (WITH 4-DIGIT ENGLISH OTP VERIFICATION) -->
        <div x-data="{
            showOtpForm: {{ session('otp_sent') ? 'true' : 'false' }},
            pendingEmail: '{{ session('pending_new_email', '') }}'
        }" class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy">
                    Email Address Settings
                </h2>
                <p class="text-xs text-muted mt-0.5">Your email address is used for system notifications and account password recovery.</p>
            </div>

            <div class="mb-5 rounded-xl bg-slate-50 border border-slate-200 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                <div>
                    <span class="text-slate-500 font-medium block">Current Email Address:</span>
                    <span class="text-base font-black text-navy mt-0.5 block font-mono">{{ $user->email }}</span>
                </div>
                <span class="badge badge-success text-[11px] px-3 py-1 font-bold">Verified</span>
            </div>

            <!-- REQUEST OTP FORM -->
            <form method="POST" action="{{ route('profile.email.request-otp') }}" class="space-y-4 max-w-md">
                @csrf
                <div>
                    <label class="form-label">New Email Address *</label>
                    <input type="email" class="form-input" name="new_email" value="{{ old('new_email', session('pending_new_email')) }}" required placeholder="new_email@example.com">
                </div>

                <div class="pt-1">
                    <button class="btn btn-primary w-full sm:w-auto">
                        Send OTP Code to New Email
                    </button>
                </div>
            </form>

            <!-- OTP VERIFICATION MODAL / SECTION -->
            <template x-if="showOtpForm || '{{ session('otp_sent') }}'">
                <div class="mt-6 border-t border-navy/10 pt-6">
                    <div class="rounded-2xl border-2 border-orange-400/40 bg-orange-50/60 p-5 sm:p-6 space-y-4">
                        <div>
                            <h3 class="text-base font-black text-navy">Enter 4-Digit Verification Code (OTP)</h3>
                            <p class="text-xs text-slate-600">A 4-digit verification code in English has been sent to your new email: <strong class="text-navy font-mono" x-text="pendingEmail || '{{ session('pending_new_email') }}'"></strong></p>
                        </div>

                        <form method="POST" action="{{ route('profile.email.verify-otp') }}" class="space-y-4 max-w-sm">
                            @csrf
                            <div>
                                <span class="form-label text-xs">Verification Code (4-Digit OTP):</span>
                                <input name="otp" 
                                       type="text" 
                                       maxlength="4" 
                                       required 
                                       pattern="[0-9]{4}" 
                                       class="form-input mt-1 text-center font-mono text-2xl font-black tracking-[0.5em] text-navy uppercase bg-white border-2 border-orange-300 focus:border-orange-500" 
                                       placeholder="0000">
                                <span class="text-[11px] text-muted block mt-1">Code is valid for 15 minutes. Please check your Inbox or Spam folder.</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="submit" class="btn btn-primary text-xs font-bold py-2.5">
                                    Verify &amp; Save New Email
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>

        <!-- CARD 4: CHANGE PASSWORD -->
        <div class="card p-6 sm:p-7">
            <div class="border-b border-navy/10 pb-4 mb-6">
                <h2 class="text-lg font-black text-navy">
                    Change Password
                </h2>
                <p class="text-xs text-muted mt-0.5">Periodically update your account password to maintain account security.</p>
            </div>

            <form method="POST" action="{{ route('profile.password.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <div class="sm:col-span-2">
                    <span class="form-label">Current Password (Old Password) *</span>
                    <x-input-password name="old_password" required placeholder="Enter current password" />
                </div>

                <div>
                    <span class="form-label">New Password *</span>
                    <x-input-password name="new_password" required minlength="8" placeholder="Minimum 8 characters" />
                </div>

                <div>
                    <span class="form-label">Confirm New Password *</span>
                    <x-input-password name="new_password_confirmation" required minlength="8" placeholder="Retype new password" />
                </div>

                <div class="sm:col-span-2 pt-2">
                    <button class="btn btn-primary w-full sm:w-auto">Save New Password</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
