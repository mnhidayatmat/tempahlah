<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Services\WhatsApp\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Name your homestay" — the one-step onboarding an authenticated but
 * tenant-less user lands on (chiefly Google sign-ups). We deliberately do
 * NOT auto-generate the business name from the Google profile / email:
 * the host types the name guests will see. Manual /register already
 * collects it in its own form, so those users never reach here.
 */
class CreateHomestayController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        // Already has a workspace (e.g. double back-button) → straight in.
        if ($this->activeTenantPublicId($request) !== null) {
            return redirect()->route('tenant.dashboard');
        }

        return view('onboarding.homestay');
    }

    public function store(Request $request, CreateTenantAndOwner $action): RedirectResponse
    {
        // Guard against a double submit racing a tenant into existence twice.
        if (($existing = $this->activeTenantPublicId($request)) !== null) {
            $request->session()->put('current_tenant_public_id', $existing);

            return redirect()->route('tenant.dashboard');
        }

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => PhoneNumber::normalize($request->input('phone')) ?? $request->input('phone'),
            ]);
        }

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'phone'         => ['nullable', 'string', 'regex:/^\+?[0-9\-\s]{8,20}$/'],
        ]);

        $user = $request->user();

        $tenant = $action->execute([
            // The user already exists (created at Google sign-in); CreateTenantAndOwner
            // resolves them by email via firstOrCreate, so these creation
            // attributes are only a defensive fallback and won't overwrite.
            'name'          => $user->name ?: Str::before($user->email, '@'),
            'email'         => $user->email,
            'phone'         => $validated['phone'] ?? $user->phone,
            'password'      => Hash::make(Str::random(40)),
            'locale'        => $user->locale ?? app()->getLocale(),
            'business_name' => $validated['business_name'],
        ]);

        // Keep the owner's own phone in sync if they supplied one here.
        if (! empty($validated['phone']) && $user->phone !== $validated['phone']) {
            $user->forceFill(['phone' => $validated['phone']])->save();
        }

        $request->session()->put('current_tenant_public_id', $tenant->public_id);

        return redirect()->route('tenant.dashboard')
            ->with('status', __('Welcome! Your homestay ":name" is ready.', ['name' => $tenant->business_name]));
    }

    /**
     * The public_id of the first active tenant this user belongs to, or null.
     */
    protected function activeTenantPublicId(Request $request): ?string
    {
        $membership = TenantUser::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->with('tenant:id,public_id,status')
            ->first();

        return $membership?->tenant && $membership->tenant->status === 'active'
            ? $membership->tenant->public_id
            : null;
    }
}
