@extends('layouts.app', ['title' => config('app.name')])

@section('content')
<section class="text-center py-16">
    <h1 class="text-4xl sm:text-5xl font-bold text-slate-900 mb-4">{{ __('Run your homestay like a pro.') }}</h1>
    <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-8">
        {{ __('Manage bookings, take payments, and grow your homestay business — built for Malaysia.') }}
    </p>

    <div class="flex justify-center gap-3">
        <a href="{{ route('register') }}" class="rounded-md bg-sky-600 text-white px-5 py-3 font-medium hover:bg-sky-700">
            {{ __('Start free') }}
        </a>
        <a href="#pricing" class="rounded-md border border-slate-300 px-5 py-3 font-medium hover:bg-slate-100">
            {{ __('See pricing') }}
        </a>
    </div>
</section>

<section id="pricing" class="grid md:grid-cols-2 gap-6 max-w-4xl mx-auto py-12">
    <div class="bg-white rounded-xl shadow border border-slate-200 p-8">
        <h2 class="text-2xl font-semibold mb-1">{{ __('Free') }}</h2>
        <p class="text-slate-600 mb-6">{{ __('For solo operators starting out.') }}</p>
        <p class="text-4xl font-bold mb-6">RM 0<span class="text-base font-normal text-slate-500">/{{ __('month') }}</span></p>
        <ul class="space-y-2 text-slate-700 mb-6">
            <li>✓ {{ __('1 property, up to 3 rooms') }}</li>
            <li>✓ {{ __('Manual payments') }}</li>
            <li>✓ {{ __('Google Calendar sync (1-way)') }}</li>
            <li>✓ {{ __('Up to 20 bookings/month') }}</li>
        </ul>
    </div>
    <div class="bg-white rounded-xl shadow border-2 border-sky-500 p-8 relative">
        <span class="absolute -top-3 left-6 bg-sky-500 text-white text-xs font-semibold px-2 py-1 rounded">{{ __('Most popular') }}</span>
        <h2 class="text-2xl font-semibold mb-1">{{ __('Pro') }}</h2>
        <p class="text-slate-600 mb-6">{{ __('For growing homestay businesses.') }}</p>
        <p class="text-4xl font-bold mb-6">RM 49<span class="text-base font-normal text-slate-500">/{{ __('month') }}</span></p>
        <ul class="space-y-2 text-slate-700 mb-6">
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
@endsection
