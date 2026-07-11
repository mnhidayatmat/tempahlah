<?php

namespace Tests\Feature;

use App\Actions\Tenancy\CreateTenantAndOwner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression guard for the signup-blocking bug: registration used to fire
 * Illuminate\Auth\Events\Registered for a MustVerifyEmail user, whose
 * framework listener builds a link via route('verification.verify') — a route
 * this app never defined — so POST /register threw RouteNotFoundException for
 * every new host.
 *
 * These tests are fully network-free (no HTTP, no DNS): they drive the tenant
 * factory action directly and exercise the exact crash mechanism.
 */
class RegistrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
    }

    private function makeOwner()
    {
        return app(CreateTenantAndOwner::class)->execute([
            'name' => 'Reg Host',
            'email' => 'reg-owner@example.test',
            'phone' => '+60123456789',
            'password' => 'password123',
            'business_name' => 'Reg Biz',
            'locale' => 'en',
        ])->owner;
    }

    /** The verification.verify route genuinely does not exist — that's why we mark owners verified. */
    public function test_verification_verify_route_is_absent(): void
    {
        $this->assertFalse(
            Route::has('verification.verify'),
            'If this route now exists, wire a full verification flow before re-enabling the Registered event on signup.'
        );
    }

    /** New owners are created with a verified email, so the email-verification path can never fire. */
    public function test_new_owner_email_is_verified_on_creation(): void
    {
        $owner = $this->makeOwner();

        $this->assertInstanceOf(MustVerifyEmail::class, $owner);
        $this->assertTrue($owner->hasVerifiedEmail(), 'a freshly registered owner must be verified');
    }

    /**
     * The exact original crash: firing Registered for a new owner. With the
     * owner verified, the framework's SendEmailVerificationNotification listener
     * early-returns, so this must NOT throw and must send no notification —
     * even if a future change re-introduces the event dispatch.
     */
    public function test_firing_registered_for_new_owner_does_not_crash_or_notify(): void
    {
        Notification::fake();
        $owner = $this->makeOwner();

        // Would have thrown RouteNotFoundException before the fix.
        event(new Registered($owner));

        Notification::assertNothingSent();
        $this->assertTrue(true, 'Registered fired without a RouteNotFoundException');
    }
}
