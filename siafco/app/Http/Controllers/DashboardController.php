<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\DigitalCredential;
use App\Models\Sector;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'metrics' => [
                'affiliates' => Affiliate::count(),
                'active' => Affiliate::where('status', 'activo')->count(),
                'pendingPayments' => AffiliationPayment::where('status', 'pendiente')->count(),
                'confirmedPayments' => AffiliationPayment::where('status', 'confirmado')->count(),
                'credentials' => DigitalCredential::count(),
                'sectors' => Sector::count(),
            ],
            'recentPayments' => AffiliationPayment::with('affiliate')->latest()->limit(6)->get(),
        ]);
    }
}
