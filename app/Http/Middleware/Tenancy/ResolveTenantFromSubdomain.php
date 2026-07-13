<?php

namespace App\Http\Middleware\Tenancy;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromSubdomain
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant_slug');

        if (! $slug) {
            abort(404);
        }

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->first();

        if (! $tenant) {
            abort(404);
        }

        // The clean subdomain (slug.tempahlah.com) is a Pro/Ultra perk. Free
        // tenants publish at the canonical path tempahlah.com/slug instead, so
        // their subdomain 404s — matching Tenant::publicUrl(), which only hands
        // a subdomain URL to plans holding the feature.
        if (! $tenant->hasFeature('subdomain_booking_page')) {
            abort(404);
        }

        $this->context->clear();
        $this->context->set($tenant);
        $request->attributes->set('subdomain_tenant', $tenant);

        return $next($request);
    }
}
