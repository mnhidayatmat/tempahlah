<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TenantRegisterRequest;
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

        // NOTE: we deliberately do NOT fire Illuminate\Auth\Events\Registered
        // here. User implements MustVerifyEmail, so that event triggers the
        // framework's SendEmailVerificationNotification listener, which builds
        // a link via route('verification.verify') — a route that does not
        // exist in this app. It threw RouteNotFoundException on every signup.
        // Email verification is not part of v1 (the owner is marked verified
        // in CreateTenantAndOwner); re-introduce the event only alongside a
        // complete verification flow.

        $request->session()->put('current_tenant_public_id', $tenant->public_id);

        return redirect()->route('tenant.dashboard')
            ->with('status', __('Welcome! Your homestay account is ready.'))
            // Fires the Meta Pixel CompleteRegistration conversion once, on the
            // dashboard page this redirect lands on (see layouts/app.blade.php).
            ->with('fb_track', 'CompleteRegistration');
    }
}
