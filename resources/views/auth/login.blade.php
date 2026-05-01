@extends('layouts.app', ['title' => __('Login')])

@section('content')
<div class="max-w-md mx-auto bg-white rounded-lg shadow border border-slate-200 p-6">
    <h1 class="text-2xl font-semibold mb-6">{{ __('Login to your account') }}</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Email') }}</label>
            <input name="email" type="email" required value="{{ old('email') }}"
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Password') }}</label>
            <input name="password" type="password" required
                   class="w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input name="remember" type="checkbox" value="1" class="rounded border-slate-300">
            <span>{{ __('Remember me') }}</span>
        </label>

        <button type="submit" class="w-full rounded-md bg-sky-600 text-white py-2.5 font-medium hover:bg-sky-700">
            {{ __('Login') }}
        </button>

        <p class="text-center text-sm text-slate-600">
            {{ __("Don't have an account?") }} <a href="{{ route('register') }}" class="text-sky-600">{{ __('Sign up') }}</a>
        </p>
    </form>
</div>
@endsection
