<?php

namespace Tests\Feature;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Models\Tenant;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the onboarding quick-guide loop: the final "Add
 * homestay" CTA used to be an <a> that navigated while firing an async
 * keepalive POST to mark the tour complete. The create-page GET raced (and
 * often beat) that POST, so tour_completed_at stayed null and the tour
 * replayed from step 1 — forever. Finishing is now a synchronous form POST
 * that stamps the flag server-side, THEN redirects.
 */
class OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(AmenitySeeder::class);
    }

    private function freeTenant(): Tenant
    {
        return app(CreateTenantAndOwner::class)->execute([
            'name' => 'Host '.uniqid(),
            'email' => 'h'.uniqid().'@example.test',
            'phone' => '+60123456789',
            'password' => 'password123',
            'business_name' => 'Biz '.uniqid(),
            'locale' => 'ms',
        ]);
    }

    public function test_finish_stamps_flag_and_redirects_and_stops_looping(): void
    {
        $t = $this->freeTenant();
        $this->actingAs($t->owner);
        $this->withSession(['current_tenant_public_id' => $t->public_id]);

        // Fresh owner: tour is null and the dashboard renders the walkthrough.
        $this->assertNull($t->owner->tour_completed_at);
        $this->get('http://localhost/dashboard')
            ->assertOk()
            ->assertSee('onboardingTour(', false);

        // Finish → server stamps the flag, THEN redirects to create.
        $this->post('http://localhost/dashboard/onboarding/finish')
            ->assertRedirect(route('tenant.properties.create'));

        $this->assertNotNull($t->owner->fresh()->tour_completed_at, 'finish must persist tour_completed_at');

        // The create page (and every page thereafter) no longer shows the tour.
        $this->get('http://localhost/dashboard/properties/create')
            ->assertOk()
            ->assertDontSee('onboardingTour(', false);
        $this->get('http://localhost/dashboard')
            ->assertOk()
            ->assertDontSee('onboardingTour(', false);
    }

    public function test_finish_is_idempotent(): void
    {
        $t = $this->freeTenant();
        $this->actingAs($t->owner);
        $this->withSession(['current_tenant_public_id' => $t->public_id]);

        $this->post('http://localhost/dashboard/onboarding/finish')->assertRedirect();
        $firstStamp = $t->owner->fresh()->tour_completed_at;

        // Re-posting must not crash or move the original completion time.
        $this->post('http://localhost/dashboard/onboarding/finish')->assertRedirect();
        $this->assertEquals($firstStamp, $t->owner->fresh()->tour_completed_at);
    }
}
