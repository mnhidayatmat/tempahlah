<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server half of the <x-btn-link> busy-state handshake.
 *
 * A link to a generated file never navigates the page away — an `attachment`
 * download leaves the document in place, and an inline PDF opens in a new tab.
 * So the browser fires no event the source page can use to stop a spinner.
 *
 * The client tags its request with `?_dl=<nonce>`; we echo that nonce straight
 * back as a cookie the moment we respond. The page polls for it, and clears the
 * spinner when it lands — i.e. when the bytes actually start coming.
 *
 * `dl_token` MUST stay out of Laravel's cookie encryption (see bootstrap/app.php)
 * or the browser will hold a ciphertext the page cannot compare against.
 */
class DownloadToken
{
    public const COOKIE = 'dl_token';

    /** Echo the request's download nonce back as a JS-readable cookie. */
    public static function attach(Request $request, Response $response): Response
    {
        $nonce = (string) $request->query('_dl', '');

        // Reflected into a Set-Cookie header, so accept only our own alphabet.
        if ($nonce === '' || preg_match('/^[A-Za-z0-9]{1,40}$/', $nonce) !== 1) {
            return $response;
        }

        $response->headers->setCookie(new Cookie(
            name: self::COOKIE,
            value: $nonce,
            expire: time() + 60,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: false,   // the page has to read it
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
