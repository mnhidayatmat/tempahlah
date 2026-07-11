<?php

namespace Tests\Feature;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Billing\PlanLimits;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enforcement of the Free-tier caps (config/homestay.php → free_tier_limits):
 * 1 property, 3 rooms/property, 20 bookings/month. Paid / trialing tenants are
 * unlimited. Fully network-free.
 */
class PlanLimitsTest extends TestCase
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
            'locale' => 'en',
        ]);
    }

    private function comp(Tenant $tenant): void
    {
        $tenant->subscription->update(['comped_at' => now()]);
        $tenant->refresh();
        $this->assertTrue($tenant->isPaid());
    }

    private function actAs(Tenant $tenant): void
    {
        $this->actingAs($tenant->owner);
        $this->withSession(['current_tenant_public_id' => $tenant->public_id]);
    }

    private function postProperty(string $name, string $mode = 'whole_house', int $bedrooms = 1)
    {
        return $this->post('http://localhost/dashboard/properties', [
            'name' => $name,
            'address_line1' => '1 Jalan Test',
            'pricing_mode' => $mode,
            'bedrooms' => $bedrooms,
            'base_price' => 150,
        ]);
    }

    // ---- Property cap: Free = 1 ------------------------------------------
    public function test_free_property_cap_enforced(): void
    {
        $t = $this->freeTenant();
        $this->actAs($t);

        $this->postProperty('Home One')->assertRedirect();
        $this->assertSame(1, PlanLimits::propertyCount($t));

        // Second property blocked with an upsell flash, nothing created.
        $this->postProperty('Home Two')->assertSessionHas('error');
        $this->assertSame(1, Property::where('tenant_id', $t->id)->count(), 'free tenant must be capped at 1 property');
    }

    public function test_paid_tenant_unlimited_properties(): void
    {
        $t = $this->freeTenant();
        $this->comp($t);
        $this->actAs($t);

        $this->postProperty('Home One')->assertRedirect();
        $this->postProperty('Home Two')->assertRedirect();
        $this->assertSame(2, Property::where('tenant_id', $t->id)->count());
    }

    // ---- Room cap: Free = 3 rooms/property -------------------------------
    public function test_free_room_cap_enforced(): void
    {
        $t = $this->freeTenant();
        $this->actAs($t);

        // Per-room with 4 bedrooms => 4 rooms => blocked, nothing created.
        $this->postProperty('Too Many', 'per_room', 4)->assertSessionHas('error');
        $this->assertSame(0, Property::where('tenant_id', $t->id)->count());

        // Exactly 3 is allowed.
        $this->postProperty('Just Right', 'per_room', 3)->assertRedirect();
        $p = Property::where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame(3, $p->rooms()->count());
    }

    public function test_paid_tenant_unlimited_rooms(): void
    {
        $t = $this->freeTenant();
        $this->comp($t);
        $this->actAs($t);

        $this->postProperty('Big House', 'per_room', 8)->assertRedirect();
        $p = Property::where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame(8, $p->rooms()->count());
    }

    // ---- Booking cap: Free = N/month -------------------------------------
    public function test_free_monthly_booking_cap_enforced(): void
    {
        config(['homestay.free_tier_limits.bookings_per_month' => 1]);

        $t = $this->freeTenant();
        $this->actAs($t);
        $this->postProperty('Stay Home')->assertRedirect();
        $room = Property::where('tenant_id', $t->id)->firstOrFail()->rooms()->firstOrFail();

        $payload = fn (int $offset) => [
            'room_id' => $room->id,
            'check_in' => now()->addDays($offset)->toDateString(),
            'check_out' => now()->addDays($offset + 2)->toDateString(),
            'guest_name' => 'Guest '.$offset,
            'adults' => 2,
            'channel' => Booking::CHANNEL_DIRECT,
            'deposit_amount' => 100,
        ];

        // First booking OK.
        $this->post('http://localhost/dashboard/bookings', $payload(5))->assertRedirect();
        $this->assertSame(1, Booking::where('tenant_id', $t->id)->count());

        // Second booking this month blocked with upsell, nothing created.
        $this->post('http://localhost/dashboard/bookings', $payload(20))->assertSessionHas('error');
        $this->assertSame(1, Booking::where('tenant_id', $t->id)->count(), 'free tenant capped at monthly booking limit');

        // A cancelled booking must not consume quota.
        Booking::where('tenant_id', $t->id)->firstOrFail()->update(['status' => Booking::STATUS_CANCELLED]);
        $this->assertTrue(PlanLimits::canAddBooking($t->refresh()), 'cancelled booking should free the slot');
    }

    public function test_paid_tenant_unlimited_bookings(): void
    {
        config(['homestay.free_tier_limits.bookings_per_month' => 1]);

        $t = $this->freeTenant();
        $this->comp($t);
        $this->assertTrue(PlanLimits::canAddBooking($t), 'paid tenant is never booking-capped');
    }

    /**
     * Guest-facing public booking page: within the cap a guest can book; once
     * the free host is over their monthly cap the guest is turned away with a
     * neutral "contact the host" message (no plan upsell exposed to guests).
     */
    public function test_public_booking_respects_cap_with_guest_message(): void
    {
        config(['homestay.free_tier_limits.bookings_per_month' => 1]);

        $t = $this->freeTenant();
        $this->actAs($t);
        $this->postProperty('Public Stay')->assertRedirect();
        $property = Property::where('tenant_id', $t->id)->firstOrFail();
        $room = $property->rooms()->firstOrFail();
        $host = 'http://'.$t->slug.'.localhost';

        $book = fn (int $o) => $this->post($host.'/book', [
            'property_id' => $property->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays($o)->toDateString(),
            'check_out' => now()->addDays($o + 2)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'guest_name' => 'Public Guest',
            'guest_email' => 'pg'.$o.'@example.test',
            'guest_phone' => '0198887777',
            'guest_country' => 'MY',
            'payment_method' => 'manual',
        ]);

        // First public booking succeeds.
        $book(5);
        $this->assertSame(1, Booking::where('tenant_id', $t->id)->count());

        // Over cap: guest turned away with the neutral message, nothing created.
        $book(20)->assertSessionHas('booking_error');
        $this->assertSame(1, Booking::where('tenant_id', $t->id)->count());
    }
}
