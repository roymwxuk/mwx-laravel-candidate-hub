<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\View\View;

class PositionController extends Controller
{
    /**
     * The job description. Not personal data, so every signed-in user
     * (interviewers included) may read it.
     */
    public function show(Position $position): View
    {
        return view('positions.show', [
            'position' => $position,
            'applicants' => $position->candidates()->count(),
        ]);
    }
}
