<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePerson;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class MaintenancePersonController extends Controller
{
    public function store(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'bank_name' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:60',
            'bank_account_holder' => 'nullable|string|max:120',
        ]);

        MaintenancePerson::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_no' => $validated['bank_account_no'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            'is_active' =>true,
        ]);

        return redirect()
            ->route('tenant.directory.index', ['tab' => 'maintenance'])
            ->with('status', __('Maintenance person added.'));
    }

    public function update(Request $request, int $id)
    {
        $person = MaintenancePerson::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'bank_name' => 'nullable|string|max:120',
            'bank_account_no' => 'nullable|string|max:60',
            'bank_account_holder' => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $person->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_no' => $validated['bank_account_no'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            'is_active' =>(bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('tenant.directory.index', ['tab' => 'maintenance'])
            ->with('status', __('Maintenance person updated.'));
    }

    public function destroy(int $id)
    {
        MaintenancePerson::findOrFail($id)->delete();

        return redirect()
            ->route('tenant.directory.index', ['tab' => 'maintenance'])
            ->with('status', __('Maintenance person removed.'));
    }
}
