@extends('layouts.app', ['title' => __('Verify your email')])

@section('content')
<div class="max-w-md mx-auto bg-white rounded-lg shadow border border-slate-200 p-6 text-center">
    <h1 class="text-xl font-semibold mb-3">{{ __('Verify your email') }}</h1>
    <p class="text-slate-600 mb-4">{{ __('We sent a verification link to your email. Click it to activate your account.') }}</p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="rounded-md bg-sky-600 text-white px-4 py-2">{{ __('Resend email') }}</button>
    </form>
</div>
@endsection
