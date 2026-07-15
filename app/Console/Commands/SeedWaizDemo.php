<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\CleaningTask;
use App\Models\Expense;
use App\Models\LaundryTask;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed (or remove) DEMO data into an EXISTING second homestay — "Waiz Homestay" —
 * so the owner can see how the multi-property dashboard / reports / housekeeping
 * behave. Everything is fake and fully reversible.
 *
 *   php artisan demo:waiz                    # seed into the Waiz property of the Wafa tenant
 *   php artisan demo:waiz --property=9       # seed into a specific property id
 *   php artisan demo:waiz --remove           # delete ONLY the seeded rows (keeps the property + rooms)
 *
 * Safety:
 *  - It seeds into a property that already exists — it NEVER creates or deletes
 *    the property or its rooms, so your real homestay setup is untouched.
 *  - Every seeded booking is PAST-dated (checked out / cancelled), so it can
 *    never block a real date on the public calendar or marketplace, and never
 *    shows as an "upcoming" stay. (That means "Expected Payments" for Waiz stays
 *    0 — deliberate; we don't create fake future bookings on a live listing.)
 *  - Every seeded row carries a marker (booking reference `WAIZ-…` + meta, and a
 *    `[WAIZ-DEMO-SEED]` note on standalone maintenance/expenses), so --remove
 *    wipes exactly what it created and nothing else.
 *  - Direct create() only — no observers dispatch jobs, so no emails / WhatsApp /
 *    calendar side effects fire.
 */
class SeedWaizDemo extends Command
{
    protected $signature = 'demo:waiz
        {--tenant= : Tenant id (default: the Wafa tenant, else the first tenant)}
        {--property= : Property id to seed into (default: the tenant\'s "Waiz…" property)}
        {--remove : Remove ONLY the seeded demo rows (keeps the property + rooms)}';

    protected $description = 'Seed or remove fake data in an existing "Waiz Homestay" property for a multi-property demo';

    private const MARKER = '[WAIZ-DEMO-SEED]';

    public function handle(): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            $this->error('No tenant found. Pass --tenant=<id>.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set($tenant);

        $property = $this->resolveProperty($tenant);
        if (! $property) {
            $this->error("No Waiz property found for tenant #{$tenant->id}. Create it first, or pass --property=<id>.");

            return self::FAILURE;
        }

        return $this->option('remove')
            ? $this->remove($tenant, $property)
            : $this->seed($tenant, $property);
    }

    private function resolveTenant(): ?Tenant
    {
        if ($id = $this->option('tenant')) {
            return Tenant::query()->find((int) $id);
        }

        return Tenant::query()->where('business_name', 'like', '%Wafa%')->first()
            ?? Tenant::query()->orderBy('id')->first();
    }

    private function resolveProperty(Tenant $tenant): ?Property
    {
        $q = Property::withoutGlobalScopes()->where('tenant_id', $tenant->id);

        if ($id = $this->option('property')) {
            return (clone $q)->where('id', (int) $id)->first();
        }

        return (clone $q)->where('name', 'like', 'Waiz%')->orderBy('id')->first();
    }

    /** Ids of bookings this command seeded into the property (marker-matched). */
    private function seededBookingIds(int $propertyId): \Illuminate\Support\Collection
    {
        return Booking::withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->where('reference', 'like', 'WAIZ-%')
            ->pluck('id');
    }

    private function seed(Tenant $tenant, Property $property): int
    {
        if ($this->seededBookingIds($property->id)->isNotEmpty()) {
            $this->warn("Demo data already seeded into '{$property->name}' (#{$property->id}). Run --remove first to re-seed.");

            return self::SUCCESS;
        }

        $rooms = Room::withoutGlobalScopes()->where('property_id', $property->id)->get();
        if ($rooms->isEmpty()) {
            $this->error("Property '{$property->name}' has no rooms — add a room first.");

            return self::FAILURE;
        }

        mt_srand(20260715);
        $tz = config('homestay.timezone', 'Asia/Kuala_Lumpur');
        $today = Carbon::now($tz)->startOfDay();

        $counts = ['bookings' => 0, 'payments' => 0, 'cleaning' => 0, 'laundry' => 0, 'maintenance' => 0, 'expenses' => 0];

        DB::transaction(function () use ($tenant, $property, $rooms, $today, &$counts) {
            $names = ['Amir Hakim', 'Nurul Aina', 'Faizal Rahman', 'Siti Zubaidah', 'Danish Haiqal',
                'Wan Nabila', 'Hafiz Iskandar', 'Puteri Sofea', 'Zulkifli Omar', 'Alya Batrisyia',
                'Rizwan Md Yusof', 'Farah Adriana', 'Khairul Anuar', 'Mira Kamaruddin', 'Syafiq Danial',
                'Liyana Hakimi', 'Azlan Shah', 'Nadia Iman', 'Haziq Firdaus', 'Elina Roslan',
                'Zainal Abidin', 'Camelia Yusri'];

            // All PAST check-in offsets (days before today), spread over ~7 months
            // up to a few days ago — so nothing blocks current/future availability.
            $offsets = [-208, -195, -181, -170, -158, -142, -129, -118, -104, -96,
                -83, -74, -61, -52, -40, -33, -26, -19, -13, -9, -6, -3];

            foreach ($offsets as $i => $off) {
                $room = $rooms[$i % $rooms->count()];
                $checkIn = $today->copy()->addDays($off);
                $nights = 1 + ($i % 4);
                $checkOut = $checkIn->copy()->addDays($nights);
                // Guard: keep every stay strictly in the past.
                if ($checkOut->gte($today)) {
                    $checkOut = $today->copy()->subDay();
                    $checkIn = $checkOut->copy()->subDays($nights);
                }

                $nightly = (float) ($room->base_price ?: 200) + (($i % 3) * 20);
                $base = $nightly * $nights;
                $fee = 100.0;
                $total = $base + $fee;

                // Mostly checked-out; a couple cancelled / no-show for realism.
                $status = match ($i % 10) {
                    3 => Booking::STATUS_CANCELLED,
                    7 => Booking::STATUS_NO_SHOW,
                    default => Booking::STATUS_CHECKED_OUT,
                };
                $completed = $status === Booking::STATUS_CHECKED_OUT;
                $channel = [Booking::CHANNEL_DIRECT, Booking::CHANNEL_DIRECT, Booking::CHANNEL_WALK_IN, Booking::CHANNEL_BOOKING][$i % 4];

                $booking = Booking::create([
                    'tenant_id' => $tenant->id,
                    'property_id' => $property->id,
                    'room_id' => $room->id,
                    'reference' => 'WAIZ-'.$checkIn->format('ymd').'-'.strtoupper(Str::random(4)),
                    'channel' => $channel,
                    'status' => $status,
                    'check_in' => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                    'nights' => $nights,
                    'adults' => 2 + ($i % 3),
                    'children' => $i % 2,
                    'currency' => 'MYR',
                    'base_amount' => $base,
                    'sst_amount' => 0,
                    'tourism_tax_amount' => 0,
                    'booking_fee_amount' => $fee,
                    'discount_amount' => 0,
                    'total_amount' => $total,
                    'deposit_pct' => round($fee / $total * 100, 2),
                    'deposit_amount' => $fee,
                    'deposit_paid_at' => $completed ? $checkIn->copy()->subDays(5)->setTime(10, 0) : null,
                    'balance_paid_at' => $completed ? $checkIn->copy()->setTime(15, 0) : null,
                    'cancelled_at' => $status === Booking::STATUS_CANCELLED ? $checkIn->copy()->subDays(2) : null,
                    'checked_out_at' => $completed ? $checkOut->copy()->setTime(12, 0) : null,
                    'is_foreigner' => false,
                    'meta' => ['demo_seed' => 'waiz'],
                ]);
                $counts['bookings']++;

                BookingGuest::create([
                    'booking_id' => $booking->id,
                    'full_name' => $names[$i % count($names)],
                    'phone' => '01'.(($i % 8) + 1).'-'.str_pad((string) (1000000 + $i * 137), 7, '0', STR_PAD_LEFT),
                ]);

                if ($completed) {
                    // Full payment (deposit + balance), paid_at drives the income chart.
                    Payment::create([
                        'tenant_id' => $tenant->id, 'booking_id' => $booking->id,
                        'type' => Payment::TYPE_DEPOSIT, 'method' => Payment::METHOD_MANUAL,
                        'amount' => $fee, 'status' => Payment::STATUS_SUCCEEDED,
                        'paid_at' => $checkIn->copy()->subDays(5)->setTime(10, 0),
                    ]);
                    Payment::create([
                        'tenant_id' => $tenant->id, 'booking_id' => $booking->id,
                        'type' => Payment::TYPE_BALANCE, 'method' => Payment::METHOD_MANUAL,
                        'amount' => $total - $fee, 'status' => Payment::STATUS_SUCCEEDED,
                        'paid_at' => $checkIn->copy()->setTime(15, 0),
                    ]);
                    $counts['payments'] += 2;

                    CleaningTask::create([
                        'tenant_id' => $tenant->id, 'property_id' => $property->id,
                        'room_id' => $room->id, 'booking_id' => $booking->id,
                        'type' => CleaningTask::TYPE_FULL, 'status' => CleaningTask::STATUS_COMPLETED,
                        'cleaners_required' => 1, 'duration_minutes' => 240, 'auto_generated' => true,
                        'cost' => 60 + (($i % 3) * 25), // 60 / 85 / 110
                        'scheduled_at' => $checkOut->copy()->setTime(12, 30),
                        'started_at' => $checkOut->copy()->setTime(12, 30),
                        'completed_at' => $checkOut->copy()->setTime(16, 30),
                    ]);
                    $counts['cleaning']++;

                    LaundryTask::create([
                        'tenant_id' => $tenant->id, 'property_id' => $property->id,
                        'booking_id' => $booking->id, 'status' => LaundryTask::STATUS_RETURNED,
                        'cost' => 130, 'item_count' => 6 + ($i % 8),
                        'pickup_at' => $checkOut->copy()->setTime(14, 0),
                        'picked_up_at' => $checkOut->copy()->setTime(14, 0),
                        'expected_return_at' => $checkOut->copy()->addDay()->setTime(12, 0),
                        'returned_at' => $checkOut->copy()->addDay()->setTime(11, 0),
                    ]);
                    $counts['laundry']++;
                }
            }

            // Standalone maintenance tickets (marker in description for removal).
            $tickets = [
                ['Baiki penghawa dingin bilik utama', MaintenanceTicket::PRIORITY_HIGH, MaintenanceTicket::STATUS_RESOLVED, 220, -20],
                ['Tukar water heater rosak', MaintenanceTicket::PRIORITY_URGENT, MaintenanceTicket::STATUS_RESOLVED, 380, -6],
                ['Paip sinki bocor di dapur', MaintenanceTicket::PRIORITY_MEDIUM, MaintenanceTicket::STATUS_IN_PROGRESS, null, -3],
                ['Cat semula dinding ruang tamu', MaintenanceTicket::PRIORITY_LOW, MaintenanceTicket::STATUS_OPEN, null, -1],
            ];
            foreach ($tickets as [$title, $priority, $tstatus, $cost, $dayOff]) {
                $when = $today->copy()->addDays($dayOff);
                MaintenanceTicket::create([
                    'tenant_id' => $tenant->id, 'property_id' => $property->id,
                    'title' => $title, 'description' => 'Data contoh untuk demo. '.self::MARKER,
                    'priority' => $priority, 'status' => $tstatus, 'cost' => $cost,
                    'scheduled_at' => $when->copy()->setTime(9, 0),
                    'resolved_at' => $tstatus === MaintenanceTicket::STATUS_RESOLVED ? $when->copy()->setTime(15, 0) : null,
                ]);
                $counts['maintenance']++;
            }

            // Expense ledger (marker in description for removal).
            $expenses = [
                ['supplies', 'Sabun, tuala & keperluan tetamu', 85, -2],
                ['utility', 'Bil elektrik & air', 340, -4],
                ['utility', 'Bil internet (Unifi)', 129, -8],
                ['furniture', 'Set sofa ruang tamu', 1250, -70],
                ['upgrade', 'Naik taraf penghawa dingin (2 unit)', 900, -95],
                ['repair', 'Alat ganti paip & tandas', 175, -30],
                ['supplies', 'Detergen & pencuci', 60, -35],
                ['toilet', 'Pemegang tandas & cermin', 95, -60],
            ];
            foreach ($expenses as [$cat, $title, $amount, $dayOff]) {
                Expense::create([
                    'tenant_id' => $tenant->id, 'property_id' => $property->id,
                    'category' => $cat, 'title' => $title, 'description' => self::MARKER,
                    'amount' => $amount, 'incurred_at' => $today->copy()->addDays($dayOff)->toDateString(),
                ]);
                $counts['expenses']++;
            }
        });

        $this->info("✓ Seeded demo data into '{$property->name}' (#{$property->id}) — tenant #{$tenant->id} {$tenant->business_name}.");
        $this->table(['Type', 'Rows'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line('  See it in your dashboard, reports and housekeeping alongside your real homestay.');
        $this->line('  (All stays are past-dated, so nothing blocks your real calendar; Expected Payments for Waiz stays 0 by design.)');
        $this->line('  Remove everything later:  php artisan demo:waiz --remove'.($this->option('property') ? ' --property='.$property->id : ''));

        return self::SUCCESS;
    }

    private function remove(Tenant $tenant, Property $property): int
    {
        $bookingIds = $this->seededBookingIds($property->id);

        $removed = ['bookings' => 0, 'maintenance' => 0, 'expenses' => 0];

        DB::transaction(function () use ($property, $bookingIds, &$removed) {
            if ($bookingIds->isNotEmpty()) {
                DB::table('payments')->whereIn('booking_id', $bookingIds)->delete();
                DB::table('booking_guests')->whereIn('booking_id', $bookingIds)->delete();
                DB::table('cleaning_tasks')->whereIn('booking_id', $bookingIds)->delete();
                DB::table('laundry_tasks')->whereIn('booking_id', $bookingIds)->delete();
                $removed['bookings'] = DB::table('bookings')->whereIn('id', $bookingIds)->delete();
            }
            // Standalone rows: marker-matched, scoped to this property only.
            $removed['maintenance'] = DB::table('maintenance_tickets')
                ->where('property_id', $property->id)
                ->where('description', 'like', '%'.self::MARKER.'%')->delete();
            $removed['expenses'] = DB::table('expenses')
                ->where('property_id', $property->id)
                ->where('description', 'like', '%'.self::MARKER.'%')->delete();
        });

        if (array_sum($removed) === 0) {
            $this->warn("No seeded demo rows found in '{$property->name}' (#{$property->id}).");

            return self::SUCCESS;
        }

        $this->info("✓ Removed seeded demo rows from '{$property->name}' (#{$property->id}): "
            ."{$removed['bookings']} bookings, {$removed['maintenance']} maintenance, {$removed['expenses']} expenses "
            .'(+ their payments/guests/cleaning/laundry). The property, rooms and your real data are untouched.');

        return self::SUCCESS;
    }
}
