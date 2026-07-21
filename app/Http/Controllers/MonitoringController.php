<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(): View
    {
        $failedJobs = DB::table('failed_jobs')->latest('failed_at')->paginate(20);
        $errors = collect(file_exists(storage_path('logs/laravel.log')) ? file(storage_path('logs/laravel.log')) : [])
            ->filter(fn ($line) => str_contains($line, '.ERROR:'))->take(-30)->reverse()->map(fn ($line) => Str::limit(trim($line), 500));

        return view('operations.monitoring', compact('failedJobs', 'errors'));
    }

    public function retry(string $uuid): RedirectResponse
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->exists() ?: abort(404);
        \Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('success', 'Job dimasukkan kembali ke antrean.');
    }
}
