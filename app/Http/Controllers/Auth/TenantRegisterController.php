<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TenantRegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantRegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(TenantRegisterRequest $request, CreateTenantAndOwner $action): RedirectResponse
    {
        $tenant = $action->execute($request->validated());

        Auth::login($tenant->owner);
        event(new Registered($tenant->owner));

        $request->session()->put('current_tenant_public_id', $tenant->public_id);

        return redirect()->route('tenant.dashboard')
            ->with('status', __('Welcome! Your homestay account is ready.'));
    }
}
