<?php

namespace App\Http\Middleware\Tenancy;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tenantPublicId = $request->session()->get('current_tenant_public_id')
            ?? $request->header('X-Tenant')
            ?? $user->tenantMemberships()->where('status', 'active')->first()?->tenant?->public_id;

        if (! $tenantPublicId) {
            return $next($request);
        }

        $tenant = Tenant::where('public_id', $tenantPublicId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id)->where('tenant_users.status', 'active'))
            ->first();

        if (! $tenant) {
            throw new AuthenticationException('User does not belong to the requested tenant.');
        }

        if (! $tenant->isActive()) {
            abort(403, 'Tenant suspended.');
        }

        $this->context->set($tenant);
        $request->session()->put('current_tenant_public_id', $tenant->public_id);

        return $next($request);
    }
}
