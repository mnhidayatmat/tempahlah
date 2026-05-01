<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // The Livewire component does the heavy lifting; this controller
        // simply renders the page that mounts it. Kept thin so the view
        // can be cached and Livewire can wire:poll for fresh stats.
        return view('tenant.dashboard');
    }
}
