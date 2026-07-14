@php
    // Meta (Facebook) Pixel — renders nothing until a Pixel ID is set. Resolves
    // the super-admin UI value first (Platform Admin → Settings, stored in
    // platform_settings), then falls back to FACEBOOK_PIXEL_ID in .env — same
    // precedence as the Stripe keys. rescue() so a DB hiccup on the public
    // landing page falls back to config instead of 500-ing.
    $fbPixelId = rescue(fn () => \App\Models\PlatformSetting::get('facebook_pixel.id'), null, false)
        ?: config('services.facebook_pixel.id');
@endphp
@if($fbPixelId)
    <!-- Meta Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @js($fbPixelId));
        fbq('track', 'PageView');
        @isset($fbEvent)
        fbq('track', @js($fbEvent));
        @endisset
    </script>
    <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id={{ $fbPixelId }}&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
@endif
