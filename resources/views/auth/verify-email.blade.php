@extends('layouts.app', ['title' => __('Verify your email')])

@section('content')
<div class="hauz-card" style="max-width:420px; margin:0 auto; padding:32px; text-align:center;">
    <h1 style="font-family:var(--font-display); font-size:20px; font-weight:600; margin:0 0 12px; color:var(--ink);">
        {{ __('Verify your email') }}
    </h1>
    <p style="font-size:13.5px; color:var(--ink-2); line-height:1.55; margin:0 0 20px;">
        {{ __('We sent a verification link to your email. Click it to activate your account.') }}
    </p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn btn-primary">{{ __('Resend email') }}</button>
    </form>
</div>
@endsection
