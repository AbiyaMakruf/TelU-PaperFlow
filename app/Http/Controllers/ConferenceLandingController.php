<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Services\ConferenceFileStorage;
use Illuminate\View\View;

class ConferenceLandingController extends Controller
{
    public function __invoke(Conference $conference, ConferenceFileStorage $storage): View
    {
        $formAvailable = $conference->isSubmissionOpen() && $conference->publishedForm();

        return view('public.conference', [
            'conference' => $conference,
            'formAvailable' => (bool) $formAvailable,
            'storageReady' => $storage->ready($conference),
        ]);
    }
}
