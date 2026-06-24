<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cleaner;
use App\Models\LaundryVendor;
use App\Models\MaintenancePerson;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'cleaners');
        if (! in_array($tab, ['cleaners', 'vendors', 'maintenance'], true)) {
            $tab = 'cleaners';
        }

        $cleaners = Cleaner::withCount('cleaningTasks')
            ->orderByDesc('is_active')->orderBy('name')->get();

        $vendors = LaundryVendor::withCount('laundryTasks')
            ->orderByDesc('is_active')->orderBy('name')->get();

        $maintenancePersons = MaintenancePerson::query()
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('tenant.directory.index', [
            'tab' => $tab,
            'cleaners' => $cleaners,
            'vendors' => $vendors,
            'maintenancePersons' => $maintenancePersons,
        ]);
    }
}
