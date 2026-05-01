@extends('layouts.app', ['title' => __('Subscription')])

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-lg shadow border border-slate-200 p-6">
    <h1 class="text-2xl font-semibold mb-2">{{ __('Subscription') }}</h1>
    <p class="text-slate-600 mb-4">{{ __('Upgrade to Pro for unlimited properties, payment gateway, marketplace listing and more.') }}</p>

    <div class="grid sm:grid-cols-2 gap-4">
        <div class="rounded-lg border border-slate-200 p-4">
            <p class="font-semibold">{{ __('Free') }}</p>
            <p class="text-2xl font-bold mt-2">RM 0</p>
            <p class="text-sm text-slate-500 mt-1">{{ __('Current plan') }}</p>
        </div>
        <div class="rounded-lg border-2 border-sky-500 p-4">
            <p class="font-semibold">{{ __('Pro') }}</p>
            <p class="text-2xl font-bold mt-2">RM 49<span class="text-sm font-normal">/{{ __('month') }}</span></p>
            <button class="mt-3 w-full rounded-md bg-sky-600 text-white py-2">{{ __('Start 7-day trial') }}</button>
        </div>
    </div>
</div>
@endsection
