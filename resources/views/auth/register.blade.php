@extends('layouts.app', ['title' => __('Sign up')])

@section('content')
<div class="max-w-lg mx-auto bg-white rounded-lg shadow border border-slate-200 p-6">
    <h1 class="text-2xl font-semibold mb-1">{{ __('Create your homestay account') }}</h1>
    <p class="text-slate-600 text-sm mb-6">{{ __('Free forever. Upgrade anytime.') }}</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Your name') }}</label>
            <input name="name" type="text" required value="{{ old('name') }}"
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Business / homestay name') }}</label>
            <input name="business_name" type="text" required value="{{ old('business_name') }}"
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('business_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Email') }}</label>
            <input name="email" type="email" required value="{{ old('email') }}"
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Phone') }}</label>
            <input name="phone" type="tel" required value="{{ old('phone') }}" placeholder="+60123456789"
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Password') }}</label>
            <input name="password" type="password" required
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Confirm password') }}</label>
            <input name="password_confirmation" type="password" required
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
        </div>

        <label class="flex items-start gap-2 text-sm">
            <input name="terms" type="checkbox" value="1" required class="mt-0.5 rounded border-slate-300">
            <span>{{ __('I agree to the Terms and PDPA Privacy Policy.') }}</span>
        </label>
        @error('terms') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

        <button type="submit" class="w-full rounded-md bg-sky-600 text-white py-2.5 font-medium hover:bg-sky-700">
            {{ __('Create account') }}
        </button>

        <p class="text-center text-sm text-slate-600">
            {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="text-sky-600">{{ __('Login') }}</a>
        </p>
    </form>
</div>
@endsection
