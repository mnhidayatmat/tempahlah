<?php

namespace App\Http\Middleware\Tenancy;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenant
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['tenant' => 'Tenant context required.']], 400);
            }

            return redirect()->route('tenant.onboard');
        }

        return $next($request);
    }
}
