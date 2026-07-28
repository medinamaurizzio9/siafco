<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;

class VerificationController extends Controller
{
    public function show(string $token)
    {
        $affiliate = Affiliate::withTrashed()
            ->with('sector')
            ->where('verification_token', $token)
            ->firstOrFail();

        return view('verify.show', compact('affiliate'));
    }
}
