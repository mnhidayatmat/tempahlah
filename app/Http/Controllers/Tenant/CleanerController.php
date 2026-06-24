<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cleaner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CleanerController extends Controller
{
    public function index()
    {
        $cleaners = Cleaner::withCount('cleaningTasks')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('tenant.cleaners.index', [
            'cleaners' => $cleaners,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
        ]);

        Cleaner::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('tenant.cleaners.index')
            ->with('status', __('Cleaner added.'));
    }

    public function update(Request $request, int $id)
    {
        $cleaner = Cleaner::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'is_active' => 'nullable|boolean',
        ]);

        $cleaner->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('tenant.cleaners.index')
            ->with('status', __('Cleaner updated.'));
    }

    public function destroy(int $id)
    {
        Cleaner::findOrFail($id)->delete();

        return redirect()
            ->route('tenant.cleaners.index')
            ->with('status', __('Cleaner removed.'));
    }
}
