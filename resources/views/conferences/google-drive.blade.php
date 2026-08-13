<x-layouts.app :title="'Google Drive · '.$conference->name" :heading="$conference->name">
    <div class="w-full space-y-6">
        <x-conference-header :conference="$conference" active="storage" />

        @if($errors->any())<div class="mt-6 rounded-xl border border-danger/20 bg-danger/8 p-4 text-sm text-danger">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div class="mt-6 rounded-xl border border-success/20 bg-success/8 p-4 text-sm text-success">{{ session('success') }}</div>@endif

        <section class="card mt-6 p-6">
            <h2 class="font-extrabold text-navy">Default Storage Provider</h2>
            <form method="POST" action="{{ route('conferences.storage-provider.update', $conference) }}" class="mt-4">
                @csrf
                @method('PUT')
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="check-row rounded-xl border border-navy/10 p-4">
                        <input type="radio" name="storage_provider" value="supabase" @checked($conference->storage_provider === 'supabase')>
                        <span><strong class="block text-navy">Supabase Storage</strong><small class="text-muted">Default private storage proxy server.</small></span>
                    </label>
                    <label class="check-row rounded-xl border border-navy/10 p-4">
                        <input type="radio" name="storage_provider" value="google_drive" @checked($conference->storage_provider === 'google_drive')>
                        <span><strong class="block text-navy">Google Drive</strong><small class="text-muted">Use authorized Google Drive folder.</small></span>
                    </label>
                </div>
                <button class="btn btn-primary mt-4" type="submit">Save Selection</button>
            </form>
        </section>

        <section class="card mt-6 p-6">
            <h2 class="font-extrabold text-navy">Google Drive Connection</h2>
            <dl class="grid gap-5 text-sm sm:grid-cols-2">
                <div><dt class="text-muted">OAuth Configuration</dt><dd class="mt-1 font-black {{ $drive->configured() ? 'text-success' : 'text-danger' }}">{{ $drive->configured() ? 'Configured' : 'Incomplete' }}</dd></div>
                <div><dt class="text-muted">Connection Status</dt><dd class="mt-1 font-black {{ $drive->connected($conference) ? 'text-success' : 'text-muted' }}">{{ $drive->connected($conference) ? 'Connected' : 'Not Connected' }}</dd></div>
                <div><dt class="text-muted">Target Folder Name</dt><dd class="mt-1 font-bold">{{ $drive->folderName($conference) }}</dd></div>
                <div><dt class="text-muted">Redirect URI</dt><dd class="mt-1 break-all font-mono text-xs text-navy font-bold">{{ $drive->redirectUri() }}</dd></div>
                @if($conference->google_drive_connected_at)<div><dt class="text-muted">Connected since</dt><dd class="mt-1 font-bold">{{ $conference->google_drive_connected_at->format('d M Y H:i') }}</dd></div>@endif
            </dl>

            @if(!$drive->configured())
                <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50/80 p-4 text-xs text-amber-900 space-y-1.5 font-medium">
                    <p class="font-bold text-amber-950 flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Google OAuth Credentials Required in .env</span>
                    </p>
                    <p>The <strong>Connect Google Drive</strong> button is disabled because Google OAuth credentials are not fully set in your server's <code>.env</code> file. Add the following credentials:</p>
                    <ul class="list-disc list-inside font-mono text-[11px] text-amber-950 space-y-0.5 pt-1">
                        <li>GOOGLE_CLIENT_ID=your-google-oauth-client-id</li>
                        <li>GOOGLE_CLIENT_SECRET=your-google-oauth-client-secret</li>
                        <li>GOOGLE_REDIRECT_URI={{ $drive->redirectUri() }}</li>
                    </ul>
                    <p class="pt-1 text-[11px]">Ensure <code>{{ $drive->redirectUri() }}</code> is also added under <strong>Authorized redirect URIs</strong> in your Google Cloud Console OAuth Client settings.</p>
                </div>
            @endif

            <div class="mt-7 flex flex-wrap gap-3 border-t border-navy/10 pt-6">
                @if($drive->connected($conference))
                    <form method="POST" action="{{ route('conferences.drive.disconnect', $conference) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-secondary" type="submit">Disconnect</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('conferences.drive.connect', $conference) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit" @disabled(!$drive->configured()) title="{{ !$drive->configured() ? 'Google OAuth credentials (GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET) are missing in server .env' : 'Connect Google Drive' }}">Connect Google Drive</button>
                    </form>
                @endif
            </div>
        </section>

        <!-- Storage Migration Tool -->
        <section class="card mt-6 p-6">
            <h2 class="font-extrabold text-navy">File Storage Migration Tool</h2>
            <p class="mt-1 text-xs text-muted">Transfer all existing files from the previous provider to the currently active storage provider.</p>
            <form method="POST" action="{{ route('conferences.storage-provider.migrate', $conference) }}" class="mt-4 flex flex-wrap items-center gap-3">
                @csrf
                <select name="target_provider" class="form-input text-xs w-auto" required>
                    <option value="supabase">Migrate to Supabase Storage</option>
                    <option value="google_drive" @selected($conference->usesGoogleDrive())>Migrate to Google Drive</option>
                </select>
                <button class="btn btn-secondary text-xs" type="submit" onclick="return confirm('Are you sure you want to migrate all files for this conference?')">Run File Migration</button>
            </form>
        </section>

        <div class="mt-5 rounded-xl bg-navy/5 p-5 text-sm leading-6 text-muted">
            Create exactly one folder in Google Drive named <strong class="text-navy">{{ $drive->folderName($conference) }}</strong>. Paperflow will locate that folder upon authorization and use the paper code to organize files.
        </div>
    </div>
</x-layouts.app>
