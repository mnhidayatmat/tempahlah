<?php

namespace Tests\Feature;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live booking-page slug availability check (settings.slug-available). Mirrors
 * the server-side rules settings.update enforces, so the "taken?" answer the
 * host sees while typing matches what save will do.
 */
class SlugAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function tenant(string $biz): Tenant
    {
        return app(CreateTenantAndOwner::class)->execute([
            'name' => 'Owner', 'email' => 'o'.uniqid().'@example.test', 'phone' => '+60123456789',
            'password' => 'password123', 'business_name' => $biz, 'locale' => 'en',
        ]);
    }

    private function check(Tenant $t, string $slug): array
    {
        return $this->actingAs($t->owner)
            ->withSession(['current_tenant_public_id' => $t->public_id])
            ->getJson('http://localhost/dashboard/settings/slug-available?slug='.urlencode($slug))
            ->json();
    }

    public function test_slug_states(): void
    {
        $mine = $this->tenant('Alpha Homestay');   // slug: alpha-homestay
        $other = $this->tenant('Bravo Homestay');  // slug: bravo-homestay

        // My own current slug.
        $this->assertSame('current', $this->check($mine, $mine->slug)['status']);

        // Another tenant's slug → taken.
        $this->assertSame('taken', $this->check($mine, $other->slug)['status']);

        // A free slug → available.
        $this->assertSame('available', $this->check($mine, 'totally-free-slug-123')['status']);

        // Reserved system slug.
        $this->assertSame('reserved', $this->check($mine, 'admin')['status']);

        // Bad format.
        $this->assertSame('invalid', $this->check($mine, 'Has Spaces!')['status']);
        $this->assertSame('invalid', $this->check($mine, 'a')['status']); // too short
    }

    public function test_available_slug_then_actually_saves(): void
    {
        $t = $this->tenant('Gamma Homestay');
        $this->assertSame('available', $this->check($t, 'gamma-new-address')['status']);

        // And the real save accepts it (proves the live check agrees with update()).
        $this->actingAs($t->owner)->withSession(['current_tenant_public_id' => $t->public_id])
            ->patch('http://localhost/dashboard/settings', [
                'business_name' => $t->business_name,
                'business_email' => $t->business_email,
                'owner_name' => 'Owner',
                'slug' => 'gamma-new-address',
                'default_locale' => 'en',
                'full_payment_days_before' => 7,
                'fee_payment_hours' => 24,
                'cancel_balance_on' => Tenant::CANCEL_BALANCE_CHECK_IN,
                'checkout_reminder_hours' => 3,
            ])->assertRedirect();

        $this->assertSame('gamma-new-address', $t->fresh()->slug);
    }
}
