@extends('layouts.app', ['title' => __('Dashboard')])

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow border border-slate-200 p-6">
        <h1 class="text-2xl font-semibold">{{ __('Welcome to :name', ['name' => app(\App\Support\Tenancy\TenantContext::class)->current()?->business_name]) }}</h1>
        <p class="text-slate-600 mt-2">{{ __('Plan: :plan', ['plan' => app(\App\Support\Tenancy\TenantContext::class)->current()?->subscription?->plan]) }}</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-slate-200 p-5">
            <p class="text-sm text-slate-500">{{ __('Properties') }}</p>
            <p class="text-2xl font-semibold mt-1">0</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-slate-200 p-5">
            <p class="text-sm text-slate-500">{{ __('Bookings this month') }}</p>
            <p class="text-2xl font-semibold mt-1">0</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-slate-200 p-5">
            <p class="text-sm text-slate-500">{{ __('Occupancy') }}</p>
            <p class="text-2xl font-semibold mt-1">0%</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-slate-200 p-5">
            <p class="text-sm text-slate-500">{{ __('Revenue (RM)') }}</p>
            <p class="text-2xl font-semibold mt-1">0.00</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold mb-3">{{ __('Get started') }}</h2>
        <ol class="list-decimal list-inside space-y-2 text-slate-700">
            <li>{{ __('Add your first homestay property') }}</li>
            <li>{{ __('Set up rooms and pricing') }}</li>
            <li>{{ __('Share your booking link or upgrade to publish on the marketplace') }}</li>
        </ol>
    </div>
</div>
@endsection
