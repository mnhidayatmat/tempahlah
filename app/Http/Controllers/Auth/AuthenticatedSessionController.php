<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        // Always issue the long-lived "remember me" cookie (~5 years). This is
        // what keeps hosts signed in across PWA closes — a plain session cookie
        // gets evicted when the installed app is closed (notably on iOS), so we
        // don't rely on the checkbox. The recaller cookie re-authenticates
        // transparently; SetTenantContext falls back to the user's first active
        // tenant when the regenerated session has no current_tenant_public_id.
        $remember = true;

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate();

        $firstTenant = $user->tenants()->wherePivot('status', 'active')->first();
        if ($firstTenant) {
            $request->session()->put('current_tenant_public_id', $firstTenant->public_id);

            return redirect()->intended(route('tenant.dashboard'));
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
