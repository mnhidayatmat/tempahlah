<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
@php $isBM = app()->getLocale() === 'ms'; @endphp
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $isBM ? 'Cari homestay di Malaysia' : 'Find a homestay in Malaysia' }} · {{ config('app.name','Tempahlah') }}</title>
<meta name="description" content="{{ $isBM ? 'Homestay keluarga pilihan di seluruh Malaysia — tempah terus dengan tuan rumah, tiada orang tengah dan tiada komisen.' : 'Hand-picked family homestays across Malaysia — booked directly with the host, no middleman and no commission.' }}">
<link rel="canonical" href="{{ route('marketplace.search') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name','Tempahlah') }}">
<meta property="og:title" content="{{ config('app.name','Tempahlah') }} — {{ $isBM ? 'Homestay Malaysia' : 'Malaysia homestays' }}">
<meta property="og:description" content="{{ $isBM ? 'Homestay keluarga di seluruh Malaysia — tempah terus, tiada orang tengah.' : 'Family-run homestays across Malaysia — booked direct, no middleman.' }}">
<meta property="og:url" content="{{ route('marketplace.search') }}">
<meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#2596c6">
@include('partials.pwa')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ================= WARM PALETTE (scoped to public booking body) =================
   Drop this block into resources/css/booking-public.css to warm the whole
   public site, or keep it in the view's @push('head') to scope to the homepage. */
body{
  --bg:#fbfdfe; --bg-elev:#ffffff; --bg-sunk:#f1f6f9; --bg-tint:#e7eff4;
  --ink:#17272f; --ink-2:#45565f; --ink-3:#78878f; --ink-4:#a8b3ba;
  --line:#e6edf1; --line-2:#d8e2e8;
  --primary:#2596c6; --primary-ink:#ffffff; --primary-hover:#1f80ad; --primary-deep:#1a6a96;
  --primary-tint:#e4f2f8; --primary-edge:#bfe0ee;
  --accent:#e6a72e;
  --font-display:"Geist",ui-sans-serif,system-ui,sans-serif;
  --font-sans:"Geist",ui-sans-serif,system-ui,sans-serif;
}
*{box-sizing:border-box;}
html,body{margin:0;}
body{font-family:var(--font-sans);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;}
a{color:inherit;}
.eyebrow{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.16em;color:var(--primary);}

/* ---------------- header ---------------- */
.hdr{position:sticky;top:0;z-index:50;background:color-mix(in oklch,var(--bg) 82%,transparent);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid var(--line);padding:15px 40px;display:flex;align-items:center;justify-content:space-between;}
.hdr-logo{display:flex;align-items:center;gap:11px;text-decoration:none;color:inherit;}
.hdr-mark{width:38px;height:38px;border-radius:11px;background:linear-gradient(150deg,#3fcdda,#2596c6 55%,#186a93);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;box-shadow:0 4px 12px -3px rgba(31,128,173,.45);}
.hdr-mark svg{width:20px;height:20px;}
.hdr-name{font-size:15px;font-weight:700;letter-spacing:-.01em;line-height:1.05;}
.hdr-host{font-size:11px;color:var(--ink-3);letter-spacing:.01em;}
.hdr-nav{display:flex;align-items:center;gap:4px;}
.hdr-nav a{font-size:13.5px;color:var(--ink-2);text-decoration:none;padding:8px 13px;border-radius:9px;font-weight:500;transition:background .12s,color .12s;}
.hdr-nav a:hover{background:var(--bg-tint);color:var(--ink);}
.hdr-nav .sep{width:1px;height:18px;background:var(--line-2);margin:0 6px;}
.hdr-host-btn{background:var(--primary)!important;color:#fff!important;font-weight:600!important;box-shadow:0 4px 12px -4px rgba(189,92,59,.5);}
.hdr-host-btn:hover{background:var(--primary-hover)!important;}

/* ---------------- hero ---------------- */
.hero{position:relative;overflow:hidden;padding:60px 40px 36px;}
.hero-glow{position:absolute;pointer-events:none;border-radius:999px;filter:blur(100px);}
.hero-glow.a{top:-130px;left:50%;transform:translateX(-50%);width:640px;height:340px;background:var(--primary);opacity:.07;}
.hero-glow.b{top:30px;right:-90px;width:240px;height:240px;background:#2cb8c4;opacity:.06;}
.hero-inner{position:relative;max-width:820px;margin:0 auto;text-align:center;}
.hero h1{font-family:var(--font-display);font-size:46px;line-height:1.06;letter-spacing:-.03em;font-weight:600;margin:14px 0 0;text-wrap:balance;color:var(--ink);}
.hero h1 .accent{color:var(--primary);}
.hero-sub{font-size:16px;color:var(--ink-2);max-width:500px;margin:15px auto 0;line-height:1.55;text-wrap:pretty;}

/* search — slim, professional */
.search{max-width:780px;margin:26px auto 0;background:var(--bg-elev);border:1px solid var(--line-2);border-radius:11px;box-shadow:0 12px 30px -18px rgba(23,39,47,.28),0 1px 3px rgba(23,39,47,.05);display:grid;grid-template-columns:1.3fr 1.3fr 1fr 1fr auto;padding:4px;gap:0;align-items:stretch;}
.search-field{text-align:left;padding:4px 14px;border-radius:8px;display:flex;flex-direction:column;justify-content:center;gap:0;position:relative;transition:background .12s;cursor:text;}
.search-field .lbl{line-height:1.15;}
.search-field input,.search-field select{line-height:1.2;}
.search-field + .search-field::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:1px;background:var(--line);}
.search-field:hover{background:var(--bg-sunk);}
.search-field:hover + .search-field::before,.search-field:hover::before{opacity:0;}
.search-field .lbl{font-size:9.5px;text-transform:uppercase;letter-spacing:.12em;color:var(--ink-3);font-weight:700;}
.search-field input,.search-field select{border:0;background:transparent;padding:0;font:inherit;color:var(--ink);font-size:13.5px;font-weight:500;width:100%;outline:none;}
.search-field select{cursor:pointer;color:var(--ink);}
.search-field input::placeholder{color:var(--ink-4);font-weight:400;}
.search-btn{align-self:stretch;margin:0;border:0;border-radius:8px;background:linear-gradient(160deg,#2596c6 0%,#1a6a96 100%);color:#fff;font:inherit;font-size:13px;font-weight:600;padding:0 20px;display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:filter .12s,transform .06s;box-shadow:0 2px 8px -3px rgba(37,150,198,.5);}
.search-btn:hover{filter:brightness(1.06);}
.search-btn:active{transform:translateY(1px);}
.search-btn svg{width:15px;height:15px;}

/* reassurance strip */
.reassure{margin:20px auto 0;display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px 20px;color:var(--ink-3);font-size:13px;font-weight:500;}
.reassure span{display:inline-flex;align-items:center;gap:7px;}
.reassure svg{width:15px;height:15px;color:var(--primary);}
.reassure .dot{width:3px;height:3px;border-radius:999px;background:var(--ink-4);}

/* ---------------- destination cards (mobile only) ---------------- */
.dests-wrap{display:none;}
.dests-head{padding:0 18px;font-size:14px;font-weight:700;color:var(--ink);margin:0 0 11px;letter-spacing:-.01em;text-align:left;}
.dests{display:flex;gap:11px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch;padding:0 18px 4px;}
.dests::-webkit-scrollbar{display:none;}
.dest{flex-shrink:0;width:120px;height:84px;border-radius:15px;position:relative;overflow:hidden;text-decoration:none;box-shadow:0 5px 14px -7px rgba(23,39,47,.4);background:linear-gradient(150deg,#54c1d4,#2ea6c8 55%,#1f83ab);}
.dest img{width:100%;height:100%;object-fit:cover;display:block;}
.dest::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(6,22,30,.74),rgba(6,22,30,.04) 64%);}
.dest span{position:absolute;left:9px;right:8px;bottom:8px;z-index:1;color:#fff;font-size:12.5px;font-weight:600;letter-spacing:-.01em;line-height:1.15;text-shadow:0 1px 4px rgba(0,0,0,.45);display:flex;align-items:center;gap:4px;}
.dest span svg{width:12px;height:12px;flex-shrink:0;opacity:.9;}

/* ---------------- filter pills ---------------- */
.filters{max-width:1180px;margin:36px auto 0;padding:0 40px;display:flex;gap:9px;overflow-x:auto;scrollbar-width:none;}
.filters::-webkit-scrollbar{display:none;}
.pill{padding:9px 16px;border:1px solid var(--line-2);border-radius:999px;font-size:13.5px;font-weight:500;background:var(--bg-elev);color:var(--ink-2);white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:7px;transition:border-color .12s,color .12s,background .12s;flex-shrink:0;}
.pill svg{width:15px;height:15px;opacity:.75;}
.pill:hover{border-color:var(--primary-edge);color:var(--ink);}
.pill[data-active="true"]{background:var(--ink);color:#fff;border-color:var(--ink);}
.pill[data-active="true"] svg{opacity:1;}

/* ---------------- results header ---------------- */
/* wrap + shrink: the sort <select> sizes to its longest option ("Harga: rendah ke
   tinggi"), which pushed this row past a 360px phone and scrolled the whole page. */
.results-head{max-width:1180px;margin:26px auto 14px;padding:0 40px;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.results-title{font-family:var(--font-display);font-size:21px;font-weight:600;letter-spacing:-.02em;color:var(--ink);margin:0;}
.results-sub{font-size:13px;color:var(--ink-3);margin-top:3px;}
.sort{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-3);min-width:0;max-width:100%;}
.sort select{font:inherit;font-size:13.5px;font-weight:600;color:var(--ink);background:var(--bg-elev);border:1px solid var(--line-2);border-radius:10px;padding:8px 12px;cursor:pointer;outline:none;min-width:0;max-width:100%;}

/* ---------------- grid + cards ---------------- */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(276px,1fr));gap:26px 24px;max-width:1180px;margin:0 auto;padding:4px 40px 8px;}
.card{display:flex;flex-direction:column;gap:12px;text-decoration:none;color:inherit;}
.card-cover{aspect-ratio:20/17;border-radius:18px;position:relative;overflow:hidden;background:linear-gradient(150deg,#d3e9f1,#bfe0ee 55%,#8fc7dd);}
.card-cover img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease;}
.card:hover .card-cover img{transform:scale(1.045);}
/* no-photo fallback */
.card-fallback{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;background:linear-gradient(150deg,#54c1d4,#2ea6c8 55%,#1f83ab);}
.card-fallback svg{width:46px;height:46px;opacity:.55;stroke-width:1.5;}
.card-fallback::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 30% 25%,rgba(255,255,255,.18),transparent 55%);}
.card-tag{position:absolute;top:12px;left:12px;padding:5px 11px;border-radius:999px;background:rgba(255,255,255,.94);backdrop-filter:blur(6px);font-size:11.5px;font-weight:600;color:var(--ink);display:inline-flex;align-items:center;gap:5px;box-shadow:0 2px 8px rgba(60,35,15,.12);}
.card-tag svg{width:13px;height:13px;color:var(--primary);}
.card-feat{position:absolute;top:12px;right:56px;padding:5px 11px;border-radius:999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;box-shadow:0 3px 10px -2px rgba(31,128,173,.5);}
.card-fav{position:absolute;top:11px;right:11px;width:34px;height:34px;border-radius:999px;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;border:0;color:var(--ink-2);cursor:pointer;box-shadow:0 2px 8px rgba(60,35,15,.14);transition:color .12s,transform .1s;}
.card-fav:hover{color:var(--primary);transform:scale(1.08);}
.card-fav svg{width:16px;height:16px;}
.card-body{display:flex;flex-direction:column;gap:3px;}
.card-top{display:flex;justify-content:space-between;align-items:baseline;gap:10px;}
.card-title{font-size:16px;font-weight:600;margin:0;letter-spacing:-.015em;color:var(--ink);}
.card-rating{font-size:13.5px;font-weight:600;flex-shrink:0;display:inline-flex;align-items:center;gap:4px;color:var(--ink);}
.card-rating svg{width:13px;height:13px;color:var(--accent);}
.card-rating .rev{color:var(--ink-3);font-weight:400;}
.card-loc{font-size:13px;color:var(--ink-3);display:flex;align-items:center;gap:5px;}
.card-loc svg{width:13px;height:13px;}
.card-price{margin-top:6px;font-size:15px;color:var(--ink);}
.card-price b{font-family:var(--font-display);font-size:17px;font-weight:700;letter-spacing:-.01em;}
.card-price .per{font-size:13px;color:var(--ink-3);}

/* ---------------- trust band ---------------- */
.trust{max-width:1180px;margin:52px auto 0;padding:0 40px;}
.trust-inner{background:var(--bg-elev);border:1px solid var(--line-2);border-radius:22px;padding:30px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:20px;box-shadow:0 12px 32px -22px rgba(80,45,20,.2);}
.trust-item{text-align:center;position:relative;}
.trust-item + .trust-item::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:1px;background:var(--line);}
.trust-num{font-family:var(--font-display);font-size:30px;font-weight:700;letter-spacing:-.02em;color:var(--ink);line-height:1;}
.trust-num .star{color:var(--accent);font-size:.78em;margin-left:1px;}
.trust-label{font-size:12.5px;color:var(--ink-3);margin-top:8px;font-weight:500;}

/* ---------------- footer ---------------- */
.ft{margin-top:64px;padding:44px 40px 30px;border-top:1px solid var(--line);background:var(--bg-sunk);}
.ft-grid{max-width:1180px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;}
.ft-desc{font-size:13px;color:var(--ink-3);line-height:1.65;max-width:340px;margin:12px 0 0;}
.ft-badges{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;}
.ft-badge{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:600;color:var(--ink-2);background:var(--bg-elev);border:1px solid var(--line-2);border-radius:999px;padding:5px 11px;}
.ft-badge svg{width:13px;height:13px;color:var(--primary);}
.ft-col h4{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.13em;color:var(--ink-3);margin:0 0 14px;}
.ft-col ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;}
.ft-col a{font-size:13.5px;color:var(--ink-2);text-decoration:none;transition:color .12s;}
.ft-col a:hover{color:var(--primary);}
.ft-bar{max-width:1180px;margin:34px auto 0;padding-top:20px;border-top:1px solid var(--line);display:flex;justify-content:space-between;font-size:12px;color:var(--ink-3);}

/* ---------------- responsive ---------------- */
@media (max-width:820px){
  .hdr{padding:13px 18px;}
  .hdr-nav a:not(.hdr-host-btn){display:none;}
  .hdr-nav .sep{display:none;}
  .hero{padding:40px 18px 28px;}
  .hero h1{font-size:34px;}
  .hero-sub{font-size:15px;}
  .search{grid-template-columns:1fr;padding:6px;gap:0;}
  .search-field + .search-field::before{display:none;}
  .search-field{border-top:1px solid var(--line);border-radius:10px;padding:8px 14px;}
  .search-field:first-child{border-top:0;}
  /* 16px stops iOS Safari zooming the page when a field is focused. */
  .search-field input,.search-field select{font-size:16px;}
  .search-btn{margin:6px 0 0;padding:13px;}
  .reassure{display:none;}
  .dests-wrap{display:block;margin-top:22px;}
  .filters{margin-top:26px;padding:0 18px;}
  .results-head{padding:0 18px;margin-top:22px;}
  .grid{grid-template-columns:1fr;padding:4px 18px;gap:22px;}
  .card-cover{aspect-ratio:16/11;}
  .trust{padding:0 18px;margin-top:32px;}
  .trust-inner{grid-template-columns:1fr 1fr;gap:14px 16px;padding:16px 14px;border-radius:16px;}
  .trust-item:nth-child(3)::before,.trust-item:nth-child(2n+1)::before{display:none;}
  .trust-num{font-size:22px;}
  .trust-label{font-size:11px;margin-top:4px;}
  .ft{padding:34px 18px 26px;}
  .ft-grid{grid-template-columns:1fr 1fr;gap:28px;}
  .ft-bar{flex-direction:column;gap:8px;}
}
</style>
</head>
<body>
@php
    use Illuminate\Support\Facades\Storage;
    $localeKey = $isBM ? 'title_bm' : 'title_en';
    $states = ['Selangor','Kuala Lumpur','Pulau Pinang','Sabah','Sarawak','Johor','Pahang','Terengganu','Kelantan','Kedah','Perak','Negeri Sembilan','Melaka','Perlis','Putrajaya','Labuan'];
    $q = $filters['q'] ?? '';

    // Merge real listings (first) with showcase demos into one card list.
    // Carry the chosen dates onto each listing link so the booking form prefills.
    $carryDates = array_filter([
        'check_in'  => $filters['check_in'] ?? null,
        'check_out' => $filters['check_out'] ?? null,
    ]);
    $cards = [];
    foreach ($listings as $l) {
        $cards[] = [
            't'      => $l->{$localeKey} ?: $l->title_bm ?: $l->title_en,
            'city'   => $l->city,
            'state'  => $l->state,
            'rate'   => $l->base_price_min,
            'rating' => $l->rating_avg ? number_format((float) $l->rating_avg, 1) : null,
            'rev'    => $l->review_count,
            'feat'   => (bool) $l->is_featured,
            'tag'    => $l->house_type === 'per_room' ? ($isBM ? 'Bilik' : 'Room') : ($isBM ? 'Seluruh rumah' : 'Whole house'),
            'img'    => $l->hero_photo_path ? Storage::url($l->hero_photo_path) : null,
            'href'   => route('marketplace.show', ['listing' => $l] + $carryDates),
            'real'   => true,
        ];
    }
    foreach ($demos as $d) {
        $cards[] = $d + ['href' => '#', 'real' => false];
    }
    $displayCount = count($demos) ? ($total + count($demos)) : $listings->total();

    $tagIco  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>';
    $homeIco = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9.5 20v-5.5h5V20"/></svg>';
    $heart   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
    $pin     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
    $star    = '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 18l-6.3 3.2L7 14.2l-5-4.8 7-.9z"/></svg>';

    // Popular destination shortcuts (mobile). Each links to a filtered search —
    // states use ?state=, cities use ?city= (LIKE) — so tapping "Johor" lists
    // every Johor homestay. Photos share the same host as the demo cards so they
    // load reliably; the tile gradient shows if an image ever fails.
    $up = 'https://images.unsplash.com/';
    $destinations = [
        ['label' => 'Johor',             'q' => ['state' => 'Johor'],          'img' => $up.'photo-1571003123894-1f0594d2b5d9?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Kuantan',           'q' => ['city' => 'Kuantan'],         'img' => $up.'photo-1520250497591-112f2f40a3f4?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Melaka',            'q' => ['state' => 'Melaka'],         'img' => $up.'photo-1600585154340-be6161a56a0c?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Langkawi',          'q' => ['city' => 'Langkawi'],        'img' => $up.'photo-1505691938895-1758d7feb511?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Pulau Pinang',      'q' => ['state' => 'Pulau Pinang'],   'img' => $up.'photo-1568605114967-8130f3a36994?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Cameron Highlands', 'q' => ['city' => 'Cameron'],         'img' => $up.'photo-1449158743715-0a90ebb6d2d8?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Kuala Lumpur',      'q' => ['state' => 'Kuala Lumpur'],   'img' => $up.'photo-1518780664697-55e3ad937233?w=280&q=80&auto=format&fit=crop'],
        ['label' => 'Port Dickson',      'q' => ['city' => 'Port Dickson'],    'img' => $up.'photo-1564013799919-ab600027ffc6?w=280&q=80&auto=format&fit=crop'],
    ];
@endphp

<header class="hdr">
  <a href="{{ route('marketplace.search') }}" class="hdr-logo">
    <div class="hdr-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9.5 20v-5.5h5V20"/></svg></div>
    <div><div class="hdr-name">Tempahlah</div><div class="hdr-host">tempahlah.com</div></div>
  </a>
  <nav class="hdr-nav">
    <a href="{{ route('marketplace.search') }}">{{ $isBM ? 'Penginapan' : 'Stays' }}</a>
    <a href="{{ route('hosts') }}">{{ $isBM ? 'Untuk tuan rumah' : 'For hosts' }}</a>
    <span class="sep"></span>
    <a href="{{ route('locale.switch', $isBM ? 'en' : 'ms') }}">{{ $isBM ? 'BM' : 'EN' }} ▾</a>
    @auth
      <a href="{{ route('tenant.dashboard') }}">{{ $isBM ? 'Papan pemuka' : 'Dashboard' }}</a>
    @else
      <a href="{{ route('login') }}">{{ $isBM ? 'Log Masuk' : 'Log in' }}</a>
    @endauth
    <a href="{{ route('hosts') }}" class="hdr-host-btn">{{ $isBM ? 'Senaraikan Homestay' : 'List your homestay' }}</a>
  </nav>
</header>

<main>
  <section class="hero">
    <div class="hero-glow a"></div>
    <div class="hero-glow b"></div>
    <div class="hero-inner">
      <div class="eyebrow">{{ $isBM ? 'Terus dari tuan rumah · Tiada orang tengah' : 'Direct from hosts · No middleman' }}</div>
      <h1>{!! $isBM ? 'Menginap di tempat yang<br>terasa seperti <span class="accent">rumah</span>.' : 'Stay somewhere that<br>feels like <span class="accent">home</span>.' !!}</h1>
      <p class="hero-sub">{{ $isBM ? 'Homestay keluarga pilihan di seluruh Malaysia — tempah terus, dengan tuan rumah sebenar hanya sejauh WhatsApp.' : 'Hand-picked family homestays across Malaysia — booked direct, with a real human host just a WhatsApp away.' }}</p>

      <form class="search" method="GET" action="{{ route('marketplace.search') }}">
        <label class="search-field">
          <span class="lbl">{{ $isBM ? 'Di mana' : 'Where' }}</span>
          <select name="state" id="mp-state">
            <option value="">{{ $isBM ? 'Pilih negeri' : 'Select state' }}</option>
            @foreach ($states as $st)
              <option value="{{ $st }}" @selected(($filters['state'] ?? '') === $st)>{{ $st }}</option>
            @endforeach
          </select>
        </label>
        <label class="search-field">
          <span class="lbl">{{ $isBM ? 'Daerah' : 'District' }}</span>
          <select name="district" id="mp-district">
            <option value="">{{ $isBM ? 'Semua daerah' : 'All districts' }}</option>
            @foreach (($districtsByState[$filters['state'] ?? ''] ?? []) as $d)
              <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
            @endforeach
          </select>
        </label>
        <label class="search-field">
          <span class="lbl">{{ $isBM ? 'Daftar masuk' : 'Check-in' }}</span>
          <input type="date" name="check_in" id="mp-checkin" value="{{ $filters['check_in'] ?? '' }}" min="{{ now()->toDateString() }}">
        </label>
        <label class="search-field">
          <span class="lbl">{{ $isBM ? 'Daftar keluar' : 'Check-out' }}</span>
          <input type="date" name="check_out" id="mp-checkout" value="{{ $filters['check_out'] ?? '' }}" min="{{ $filters['check_in'] ?? now()->toDateString() }}">
        </label>
        <button class="search-btn" type="submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
          {{ $isBM ? 'Cari' : 'Search' }}
        </button>
      </form>
      <script>
        (function () {
          var MAP = @json($districtsByState);
          var st = document.getElementById('mp-state');
          var di = document.getElementById('mp-district');
          var ci = document.getElementById('mp-checkin');
          var co = document.getElementById('mp-checkout');
          var allLabel = @json($isBM ? 'Semua daerah' : 'All districts');
          if (st && di) {
            st.addEventListener('change', function () {
              var list = MAP[st.value] || [];
              var keep = di.value;
              di.innerHTML = '';
              var opt0 = document.createElement('option');
              opt0.value = ''; opt0.textContent = allLabel; di.appendChild(opt0);
              list.forEach(function (d) {
                var o = document.createElement('option');
                o.value = d; o.textContent = d;
                if (d === keep) o.selected = true;
                di.appendChild(o);
              });
            });
          }
          if (ci && co) {
            ci.addEventListener('change', function () {
              if (ci.value) {
                co.min = ci.value;
                if (co.value && co.value <= ci.value) co.value = '';
              }
            });
          }
        })();
      </script>

      <div class="reassure">
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/></svg>{{ $isBM ? 'Tuan rumah disahkan SSM' : 'SSM-verified hosts' }}</span>
        <span class="dot"></span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $isBM ? 'Balasan pantas di WhatsApp' : 'Fast WhatsApp replies' }}</span>
        <span class="dot"></span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>{{ $isBM ? 'Penginapan mesra halal' : 'Halal-friendly stays' }}</span>
      </div>
    </div>
  </section>

  <section class="dests-wrap">
    <div class="dests-head">{{ $isBM ? 'Destinasi popular' : 'Popular destinations' }}</div>
    <div class="dests">
      @foreach ($destinations as $d)
        <a href="{{ route('marketplace.search', $d['q']) }}" class="dest">
          @if (! empty($d['img']))<img src="{{ $d['img'] }}" alt="{{ $d['label'] }}" loading="lazy" onerror="this.remove()">@endif
          <span>{!! $pin !!}{{ $d['label'] }}</span>
        </a>
      @endforeach
    </div>
  </section>

  <section class="filters">
    <a href="{{ route('marketplace.search') }}" class="pill" data-active="{{ $q === '' ? 'true' : 'false' }}">{{ $isBM ? 'Semua penginapan' : 'All stays' }}</a>
    @foreach ([['pantai','Tepi pantai','Beachfront'],['tanah tinggi','Tanah tinggi','Highland'],['kampung','Kampung','Kampung'],['warisan','Warisan','Heritage'],['bandar','Bandar','City']] as $pill)
      <a href="{{ route('marketplace.search', ['q' => $pill[0]]) }}" class="pill" data-active="{{ $q === $pill[0] ? 'true' : 'false' }}">{{ $isBM ? $pill[1] : $pill[2] }}</a>
    @endforeach
  </section>

  <div class="results-head">
    <div>
      @php
          $locParts = array_filter([$filters['district'] ?? '', $filters['state'] ?? '']);
          $locLabel = implode(', ', $locParts);
      @endphp
      <h2 class="results-title">{{ $locLabel ? ($isBM ? 'Homestay di '.$locLabel : 'Homestays in '.$locLabel) : ($isBM ? 'Penginapan di seluruh Malaysia' : 'Homestays across Malaysia') }}</h2>
      <div class="results-sub">{{ $displayCount }} {{ $isBM ? 'homestay sedia untuk ditempah' : 'homestays ready to book' }}</div>
    </div>
    <div class="sort">
      {{ $isBM ? 'Susun' : 'Sort' }}
      <select onchange="if(this.value){window.location.href='{{ route('marketplace.search') }}?sort='+this.value}">
        <option value="">{{ $isBM ? 'Disyorkan' : 'Recommended' }}</option>
        <option value="price_low">{{ $isBM ? 'Harga: rendah ke tinggi' : 'Price: low to high' }}</option>
        <option value="rating">{{ $isBM ? 'Penarafan tertinggi' : 'Top rated' }}</option>
      </select>
    </div>
  </div>

  <section class="grid">
    @forelse ($cards as $c)
      <a href="{{ $c['href'] }}" class="card" @unless($c['real']) onclick="event.preventDefault()" @endunless>
        <div class="card-cover">
          @if (! empty($c['img']))
            <img src="{{ $c['img'] }}" alt="{{ $c['t'] }}" loading="lazy" onerror="this.remove()">
          @else
            <div class="card-fallback">{!! $homeIco !!}</div>
          @endif
          <span class="card-tag">{!! $tagIco !!}{{ $c['tag'] }}</span>
          @if (! empty($c['feat']))<span class="card-feat">{{ $isBM ? 'Pilihan' : 'Featured' }}</span>@endif
          <button type="button" class="card-fav" onclick="event.preventDefault()">{!! $heart !!}</button>
        </div>
        <div class="card-body">
          <div class="card-top">
            <h3 class="card-title">{{ $c['t'] }}</h3>
            @if (! empty($c['rating']))
              <span class="card-rating">{!! $star !!}{{ $c['rating'] }} @if (! empty($c['rev']))<span class="rev">({{ $c['rev'] }})</span>@endif</span>
            @endif
          </div>
          <div class="card-loc">{!! $pin !!}{{ $c['city'] }}@if ($c['state']), {{ $c['state'] }}@endif</div>
          @if (! empty($c['rate']))
            <div class="card-price"><b>RM {{ number_format((float) $c['rate'], 0) }}</b> <span class="per">/ {{ $isBM ? 'malam' : 'night' }}</span></div>
          @endif
        </div>
      </a>
    @empty
      <div style="grid-column:1/-1;text-align:center;padding:56px 24px;">
        <div style="font-size:34px;margin-bottom:10px;">🏡</div>
        <div style="font-family:var(--font-display);font-size:22px;margin-bottom:6px;">{{ $isBM ? 'Tiada homestay sepadan' : 'No homestays match' }}</div>
        <p style="color:var(--ink-3);font-size:13.5px;margin:0 auto 18px;max-width:420px;">{{ $isBM ? 'Cuba luaskan kawasan atau kata kunci anda.' : 'Try widening your area or keyword.' }}</p>
        <a href="{{ route('marketplace.search') }}" class="search-btn" style="display:inline-flex;">{{ $isBM ? 'Lihat semua' : 'See all' }}</a>
      </div>
    @endforelse
  </section>

  @if ($listings->hasPages())
    <div style="max-width:1180px;margin:8px auto 0;padding:0 40px;">{{ $listings->withQueryString()->links() }}</div>
  @endif

  <section class="trust">
    <div class="trust-inner">
      <div class="trust-item"><div class="trust-num">{{ $displayCount }}</div><div class="trust-label">{{ $isBM ? 'Homestay aktif' : 'Active stays' }}</div></div>
      <div class="trust-item"><div class="trust-num">4.8<span class="star">★</span></div><div class="trust-label">{{ $isBM ? 'Purata penarafan tetamu' : 'Average guest rating' }}</div></div>
      <div class="trust-item"><div class="trust-num">&lt;5 min</div><div class="trust-label">{{ $isBM ? 'Balasan WhatsApp biasa' : 'Typical WhatsApp reply' }}</div></div>
      <div class="trust-item"><div class="trust-num">0%</div><div class="trust-label">{{ $isBM ? 'Komisen tempahan terus' : 'Commission on direct' }}</div></div>
    </div>
  </section>
</main>

<footer class="ft">
  <div class="ft-grid">
    <div>
      <a href="{{ route('marketplace.search') }}" class="hdr-logo">
        <div class="hdr-mark" style="width:32px;height:32px;border-radius:9px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/><path d="M9.5 20v-5.5h5V20"/></svg></div>
        <div class="hdr-name">Tempahlah</div>
      </a>
      <p class="ft-desc">{{ $isBM ? 'Homestay milik keluarga di seluruh Malaysia. Berdaftar SSM, mematuhi cukai pelancongan, tempah terus — tiada yuran orang tengah.' : 'Family-owned homestays across Malaysia. SSM-registered, tourism-tax compliant, booked direct — no middleman fees.' }}</p>
      <div class="ft-badges">
        <span class="ft-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/></svg>{{ $isBM ? 'Disahkan SSM' : 'SSM verified' }}</span>
        <span class="ft-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>{{ $isBM ? 'Mesra halal' : 'Halal-friendly' }}</span>
      </div>
    </div>
    <div class="ft-col"><h4>{{ $isBM ? 'Penginapan' : 'Stays' }}</h4><ul>
      <li><a href="{{ route('marketplace.search', ['q' => 'pantai']) }}">{{ $isBM ? 'Tepi pantai' : 'Beachfront' }}</a></li>
      <li><a href="{{ route('marketplace.search', ['q' => 'tanah tinggi']) }}">{{ $isBM ? 'Tanah tinggi' : 'Highland' }}</a></li>
      <li><a href="{{ route('marketplace.search', ['q' => 'kampung']) }}">{{ $isBM ? 'Kampung' : 'Kampung' }}</a></li>
      <li><a href="{{ route('marketplace.search', ['q' => 'warisan']) }}">{{ $isBM ? 'Warisan' : 'Heritage' }}</a></li>
    </ul></div>
    <div class="ft-col"><h4>{{ $isBM ? 'Bantuan' : 'Help' }}</h4><ul>
      <li><a href="{{ route('hosts') }}">{{ $isBM ? 'Soalan lazim' : 'FAQ' }}</a></li>
      <li><a href="{{ route('hosts') }}">{{ $isBM ? 'Dasar pembatalan' : 'Cancellation policy' }}</a></li>
    </ul></div>
    <div class="ft-col"><h4>{{ $isBM ? 'Tuan rumah' : 'Hosts' }}</h4><ul>
      <li><a href="{{ route('hosts') }}">{{ $isBM ? 'Senaraikan homestay' : 'List your homestay' }}</a></li>
      <li><a href="{{ route('login') }}">{{ $isBM ? 'Log masuk tuan rumah' : 'Host login' }}</a></li>
    </ul></div>
  </div>
  <div class="ft-bar"><span>© {{ date('Y') }} Tempahlah · {{ $isBM ? 'Hak cipta terpelihara' : 'All rights reserved' }}</span><span>{{ $isBM ? 'Tempah terus, tiada orang tengah' : 'Booked direct, no middleman' }}</span></div>
</footer>
</body>
</html>
