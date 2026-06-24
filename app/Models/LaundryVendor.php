<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryVendor extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'email', 'is_active',
        'bank_name', 'bank_account_no', 'bank_account_holder',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'bank_account_no' => 'encrypted',
    ];

    public function laundryTasks(): HasMany
    {
        return $this->hasMany(LaundryTask::class, 'vendor_id');
    }
}
