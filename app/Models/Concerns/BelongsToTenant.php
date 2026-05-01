<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\BelongsToTenantScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new BelongsToTenantScope);

        static::creating(function ($model) {
            if ($model->tenant_id) {
                return;
            }

            $context = app(TenantContext::class);
            if ($context->has()) {
                $model->tenant_id = $context->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
