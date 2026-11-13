<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $hotel = Tenant::hotel();

        return Inertia::render('Dashboard', [
            'staffCount' => $hotel?->users()->count() ?? 0,
        ]);
    }
}
