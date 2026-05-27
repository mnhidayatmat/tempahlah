<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['ms', 'en'], true)) {
            abort(404);
        }

        $request->session()->put('app_locale', $locale);

        // Persist on the user record only when the table actually has a `locale` column.
        // The `users` table does; `super_admins` doesn't.
        $user = $request->user() ?? auth()->guard('super_admin')->user();
        if ($user && Schema::hasColumn($user->getTable(), 'locale')) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back()->withCookie(Cookie::make('app_locale', $locale, 60 * 24 * 365));
    }
}
