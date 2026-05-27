@extends('layouts.app', ['title' => __('Login')])

@section('content')
<div class="hauz-card" style="max-width:420px; margin:0 auto; padding:32px;">
    <h1 style="font-family:var(--font-display); font-size:22px; font-weight:600; margin:0 0 24px; color:var(--ink);">
        {{ __('Login to your account') }}
    </h1>

    <form method="POST" action="{{ route('login') }}" style="display:flex; flex-direction:column; gap:16px;">
        @csrf

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Email') }}</label>
            <input name="email" type="email" required value="{{ old('email') }}" class="input">
            @error('email') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Password') }}</label>
            <input name="password" type="password" required class="input">
            @error('password') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--ink-2);">
            <input name="remember" type="checkbox" value="1" style="accent-color:var(--primary);">
            <span>{{ __('Remember me') }}</span>
        </label>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%; justify-content:center;">
            {{ __('Login') }}
        </button>

        <p style="text-align:center; font-size:13px; color:var(--ink-3); margin:0;">
            {{ __("Don't have an account?") }}
            <a href="{{ route('register') }}" style="color:var(--primary); text-decoration:none; font-weight:500;">{{ __('Sign up') }}</a>
        </p>
    </form>
</div>
@endsection
