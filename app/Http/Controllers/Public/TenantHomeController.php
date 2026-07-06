<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\Public\PublicHomeBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantHomeController extends Controller
{
    public function index(Request $request, PublicHomeBuilder $builder): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');

        // Remember a marketplace referral (?src=marketplace) so a booking made
        // in this session is attributed to the marketplace (3% commission).
        \App\Support\Marketplace\Attribution::capture($request, $tenant);

        $properties = Property::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Property::STATUS_ACTIVE)
            ->with([
                // pricingRules() relation needed on each room for PricingEngine.
                'rooms:id,property_id,base_price,max_adults,max_children,beds',
                'rooms.pricingRules',
                'photos:id,property_id,path,disk,is_hero,sort_order',
                'amenities:id,key,label_bm,label_en,icon,category,sort_order',
            ])
            ->orderBy('name')
            ->get();

        return view('public-tenant.home', $builder->build($tenant, $properties, $request));
    }
}
