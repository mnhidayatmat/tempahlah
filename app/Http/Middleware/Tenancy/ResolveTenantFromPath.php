<?php

namespace App\Http\Middleware\Tenancy;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for the canonical path-based public page
 * (tempahlah.com/{slug}). Works for every active tenant regardless of plan —
 * the clean subdomain is the Pro-only perk, not the path. Mirrors
 * ResolveTenantFromSubdomain but without the paid-tier gate.
 */
class ResolveTenantFromPath
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

        $this->context->clear();
        $this->context->set($tenant);
        // Same attribute the subdomain path uses, so TenantHomeController and
        // the public controllers read the tenant identically on both hosts.
        $request->attributes->set('subdomain_tenant', $tenant);

        return $next($request);
    }
}
