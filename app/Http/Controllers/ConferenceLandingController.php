<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Services\GoogleDriveStorage;
use Illuminate\View\View;

class ConferenceLandingController extends Controller
{
    public function __invoke(Conference $conference, GoogleDriveStorage $drive): View
    {
        $formAvailable = $conference->isSubmissionOpen() && $conference->publishedForm();

        return view('public.conference', [
            'conference' => $conference,
            'formAvailable' => (bool) $formAvailable,
            'driveReady' => $drive->connected($conference),
        ]);
    }
}
