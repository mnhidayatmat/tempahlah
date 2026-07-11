<?php

namespace Tests\Feature;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Models\Tenant;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Owner field on /dashboard/settings used to be a disabled input with no
 * name — so it could never be changed. It now edits the owner's User.name.
 */
class SettingsOwnerNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(AmenitySeeder::class);
    }

    private function tenant(): Tenant
    {
        return app(CreateTenantAndOwner::class)->execute([
            'name' => 'Old Owner Name',
            'email' => 'own'.uniqid().'@example.test',
            'phone' => '+60123456789',
            'password' => 'password123',
            'business_name' => 'Biz '.uniqid(),
            'locale' => 'en',
        ]);
    }

    private function basePayload(Tenant $t): array
    {
        return [
            'business_name' => $t->business_name,
            'business_email' => $t->business_email,
            'business_phone' => '+60123456789',
            'owner_name' => 'Old Owner Name',
            'slug' => $t->slug,
            'default_locale' => 'en',
            'full_payment_days_before' => 7,
            'fee_payment_hours' => 24,
            'cancel_balance_on' => Tenant::CANCEL_BALANCE_CHECK_IN,
            'checkout_reminder_hours' => 3,
        ];
    }

    public function test_owner_field_is_an_editable_input(): void
    {
        $t = $this->tenant();
        $this->actingAs($t->owner)->withSession(['current_tenant_public_id' => $t->public_id]);

        $this->get('http://localhost/dashboard/settings')
            ->assertOk()
            ->assertSee('name="owner_name"', false)
            ->assertDontSee('value="Old Owner Name" disabled', false);
    }

    public function test_owner_name_can_be_changed(): void
    {
        $t = $this->tenant();
        $this->actingAs($t->owner)->withSession(['current_tenant_public_id' => $t->public_id]);

        $this->patch('http://localhost/dashboard/settings', array_merge($this->basePayload($t), [
            'owner_name' => 'Siti Aminah',
        ]))->assertRedirect();

        $this->assertSame('Siti Aminah', $t->owner->fresh()->name);
        // Business fields untouched.
        $this->assertSame($t->business_name, $t->fresh()->business_name);
    }

    public function test_owner_name_is_required(): void
    {
        $t = $this->tenant();
        $this->actingAs($t->owner)->withSession(['current_tenant_public_id' => $t->public_id]);

        $this->patch('http://localhost/dashboard/settings', array_merge($this->basePayload($t), [
            'owner_name' => '',
        ]))->assertSessionHasErrors('owner_name');

        $this->assertSame('Old Owner Name', $t->owner->fresh()->name);
    }
}
