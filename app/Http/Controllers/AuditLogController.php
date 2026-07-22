<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class AuditLogController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('admin.monitoring.index', ['tab' => 'audit']);
    }
}
