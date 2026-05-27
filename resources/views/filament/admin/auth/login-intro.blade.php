{{-- Injected above the Filament login form via PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE --}}
<div class="hms-login-intro">
    <span class="hms-login-intro-pill">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        {{ __('Super-admin only') }}
    </span>
    <h1 class="hms-login-intro-title">{{ __('Welcome back, admin') }}</h1>
    <p class="hms-login-intro-sub">
        {{ __('Sign in to review tenant KYC, blacklist appeals, disputes and platform health.') }}
    </p>
    <div class="hms-login-intro-divider"></div>
</div>
