<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function enabled(): bool
    {
        return (bool) config('paperflow.turnstile.enabled')
            && filled(config('paperflow.turnstile.site_key'))
            && filled(config('paperflow.turnstile.secret_key'));
    }

    public function verify(Request $request, ?string $token): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token) || mb_strlen($token) > 2048) {
            return false;
        }

        $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('paperflow.turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        return $response->successful()
            && $response->json('success') === true
            && in_array($response->json('action'), [null, 'paper_submission'], true);
    }
}
