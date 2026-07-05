<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceListing;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [
            ['loc' => route('marketplace.search'), 'priority' => '1.0', 'freq' => 'daily'],
            ['loc' => route('hosts'), 'priority' => '0.7', 'freq' => 'monthly'],
        ];

        // Location pages for states / towns that actually have listings. Guard
        // against blank state/city (a listing may be published before the host
        // fills its address) so URL generation never gets an empty slug.
        $rows = MarketplaceListing::query()->published()->get(['state', 'city']);

        foreach ($rows->pluck('state')->filter(fn ($s) => filled($s))->unique() as $state) {
            $urls[] = ['loc' => route('marketplace.location.state', Str::slug($state)), 'priority' => '0.8', 'freq' => 'daily'];
        }
        $rows->filter(fn ($r) => filled($r->state) && filled($r->city))
            ->unique(fn ($r) => $r->state.'|'.$r->city)
            ->each(function ($r) use (&$urls) {
                $urls[] = ['loc' => route('marketplace.location.town', [Str::slug($r->state), Str::slug($r->city)]), 'priority' => '0.7', 'freq' => 'daily'];
            });

        // Each published listing detail page.
        MarketplaceListing::query()->published()->get(['slug'])
            ->each(function ($l) use (&$urls) {
                $urls[] = ['loc' => route('marketplace.show', $l->slug), 'priority' => '0.6', 'freq' => 'weekly'];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.htmlspecialchars($u['loc'], ENT_XML1)
                .'</loc><changefreq>'.$u['freq'].'</changefreq><priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
