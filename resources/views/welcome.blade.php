@extends('layouts.app', ['title' => config('app.name')])

@section('content')
<section style="text-align:center; padding:64px 0;">
    <h1 class="display-1" style="margin:0 0 16px; color:var(--ink); text-wrap:balance;">{{ __('Run your homestay like a pro.') }}</h1>
    <p style="font-size:17px; color:var(--ink-2); max-width:560px; margin:0 auto 32px; line-height:1.55;">
        {{ __('Manage bookings, take payments, and grow your homestay business — built for Malaysia.') }}
    </p>

    <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg" style="text-decoration:none;">
            {{ __('Start free') }}
        </a>
        <a href="#pricing" class="btn btn-lg" style="text-decoration:none;">
            {{ __('See pricing') }}
        </a>
    </div>
</section>

<section id="pricing" style="display:grid; gap:24px; max-width:920px; margin:0 auto; padding:32px 0;" class="welcome-pricing">
    <div class="hauz-card" style="padding:32px;">
        <h2 style="font-size:22px; font-weight:600; margin:0 0 4px;">{{ __('Free') }}</h2>
        <p style="color:var(--ink-3); margin:0 0 24px;">{{ __('For solo operators starting out.') }}</p>
        <p style="font-family:var(--font-display); font-size:36px; font-weight:600; margin:0 0 24px; color:var(--ink);">
            RM 0<span style="font-size:16px; font-weight:400; color:var(--ink-3);">/{{ __('month') }}</span>
        </p>
        <ul style="list-style:none; padding:0; margin:0 0 16px; display:flex; flex-direction:column; gap:8px; color:var(--ink-2);">
            <li>✓ {{ __('1 property, up to 3 rooms') }}</li>
            <li>✓ {{ __('Manual payments') }}</li>
            <li>✓ {{ __('Google Calendar sync (1-way)') }}</li>
            <li>✓ {{ __('Up to 20 bookings/month') }}</li>
        </ul>
    </div>
    <div class="hauz-card" style="padding:32px; border:1.5px solid var(--primary); position:relative; box-shadow:var(--sh-pop);">
        <span class="pill" style="position:absolute; top:-10px; left:24px; background:var(--primary); color:var(--primary-ink); font-weight:600;">
            {{ __('Most popular') }}
        </span>
        <h2 style="font-size:22px; font-weight:600; margin:0 0 4px;">{{ __('Pro') }}</h2>
        <p style="color:var(--ink-3); margin:0 0 24px;">{{ __('For growing homestay businesses.') }}</p>
        <p style="font-family:var(--font-display); font-size:36px; font-weight:600; margin:0 0 24px; color:var(--ink);">
            RM 49<span style="font-size:16px; font-weight:400; color:var(--ink-3);">/{{ __('month') }}</span>
        </p>
        <ul style="list-style:none; padding:0; margin:0 0 16px; display:flex; flex-direction:column; gap:8px; color:var(--ink-2);">
            <li>✓ {{ __('Unlimited properties + rooms') }}</li>
            <li>✓ {{ __('Toyyibpay payment gateway') }}</li>
            <li>✓ {{ __('Auto-reminders + WhatsApp Business') }}</li>
            <li>✓ {{ __('Custom invoices, custom domain') }}</li>
            <li>✓ {{ __('Marketplace listing (3% commission)') }}</li>
            <li>✓ {{ __('Up to 5 staff (cleaner/laundry/manager)') }}</li>
            <li>✓ {{ __('7-day free trial — no card needed') }}</li>
        </ul>
    </div>
</section>

<style>
@media (min-width: 768px) {
    .welcome-pricing { grid-template-columns: 1fr 1fr; }
}
</style>
@endsection
