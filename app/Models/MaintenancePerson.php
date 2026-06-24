<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePerson extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    // Laravel would pluralise the model to "maintenance_people"; pin the table.
    protected $table = 'maintenance_persons';

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'email', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
