@extends('layouts.app', ['title' => __('Sign up')])

@section('content')
<div class="hauz-card" style="max-width:520px; margin:0 auto; padding:32px;">
    <h1 style="font-family:var(--font-display); font-size:22px; font-weight:600; margin:0 0 4px; color:var(--ink);">
        {{ __('Create your homestay account') }}
    </h1>
    <p style="font-size:13px; color:var(--ink-3); margin:0 0 24px;">{{ __('Free forever. Upgrade anytime.') }}</p>

    <form method="POST" action="{{ route('register') }}" style="display:flex; flex-direction:column; gap:16px;">
        @csrf

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Your name') }}</label>
            <input name="name" type="text" required value="{{ old('name') }}" class="input">
            @error('name') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Business / homestay name') }}</label>
            <input name="business_name" type="text" required value="{{ old('business_name') }}" class="input">
            @error('business_name') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Email') }}</label>
            <input name="email" type="email" required value="{{ old('email') }}" class="input">
            @error('email') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Phone') }}</label>
            <input name="phone" type="tel" required value="{{ old('phone') }}" placeholder="+60123456789" class="input">
            @error('phone') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Password') }}</label>
            <input name="password" type="password" required class="input">
            @error('password') <p style="font-size:12px; color:var(--err); margin:6px 0 0;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="kicker" style="display:block; margin-bottom:6px;">{{ __('Confirm password') }}</label>
            <input name="password_confirmation" type="password" required class="input">
        </div>

        <label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; color:var(--ink-2);">
            <input name="terms" type="checkbox" value="1" required style="margin-top:2px; accent-color:var(--primary);">
            <span>{{ __('I agree to the Terms and PDPA Privacy Policy.') }}</span>
        </label>
        @error('terms') <p style="font-size:12px; color:var(--err); margin:0;">{{ $message }}</p> @enderror

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%; justify-content:center;">
            {{ __('Create account') }}
        </button>

        <p style="text-align:center; font-size:13px; color:var(--ink-3); margin:0;">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" style="color:var(--primary); text-decoration:none; font-weight:500;">{{ __('Login') }}</a>
        </p>
    </form>
</div>
@endsection
