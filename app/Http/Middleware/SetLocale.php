<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected const SUPPORTED = ['ms', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // $request->session() throws on routes without StartSession middleware
        // (notably API + webhook routes), so probe first.
        $locale = ($request->hasSession() ? $request->session()->get('app_locale') : null)
            ?? $request->cookie('app_locale')
            ?? optional($request->user())->locale
            ?? config('app.locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
