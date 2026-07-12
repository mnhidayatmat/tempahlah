<?php

namespace App\Services\Onboarding;

use App\Actions\Payments\CreateGatewayBill;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;

/**
 * First-run setup checklist for a new tenant.
 *
 * The onboarding tour (partials/onboarding-tour.blade.php) explains where each
 * screen *is*. This answers the different question a brand-new host actually
 * has: what still has to be true before I can take a single ringgit?
 *
 * Every step is derived from live state — nothing is stored, so a step can
 * never be "ticked" while the underlying thing is broken, and un-doing the work
 * (archiving the last property, disconnecting WhatsApp) correctly un-ticks it.
 * That makes this double as a config diagnostic, not just a welcome mat.
 *
 * All queries ride the BelongsToTenant global scope, so this must be called
 * inside a resolved tenant context (it is — the dashboard is behind
 * RequireTenant).
 */
class SetupChecklist
{
    /**
     * @return array{
     *   steps: list<array{key:string,title:string,body:string,done:bool,cta:?string,route:?string}>,
     *   done: int, total: int, complete: bool, public_url: string
     * }
     */
    public function for(Tenant $tenant): array
    {
        $steps = [
            $this->addProperty(),
            $this->priceARoom(),
            $this->publishProperty(),
            $this->paymentSetUp($tenant),
        ];

        // WhatsApp connect is a paid-tier capability (whatsapp_business =
        // isPaid). Free hosts can't use it, so it must not sit in their
        // "finish these to start taking bookings" list as an unreachable step.
        if ($tenant->isPaid()) {
            $steps[] = $this->connectWhatsapp($tenant);
        }

        $steps[] = $this->firstBooking($tenant);

        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            'complete' => $done === count($steps),
            'public_url' => $tenant->publicUrl(),
        ];
    }

    private function addProperty(): array
    {
        return [
            'key' => 'property',
            'title' => __('Add your homestay'),
            'body' => __('Name it, set the address, and upload a few photos.'),
            'done' => Property::query()->exists(),
            'cta' => __('Add homestay'),
            'route' => route('tenant.properties.create'),
        ];
    }

    /**
     * A room with a zero/blank nightly rate can't be quoted, so an unpriced room
     * does not count as done.
     */
    private function priceARoom(): array
    {
        return [
            'key' => 'room',
            'title' => __('Add a room and set a nightly rate'),
            'body' => __('Guests can only be quoted for a room that has a price.'),
            'done' => Room::query()->where('base_price', '>', 0)->exists(),
            'cta' => __('Manage rooms'),
            'route' => route('tenant.properties.index'),
        ];
    }

    /**
     * A `draft` property is invisible on the public booking page — the single
     * most common reason a new host says "my link shows nothing".
     */
    private function publishProperty(): array
    {
        return [
            'key' => 'publish',
            'title' => __('Publish your homestay'),
            'body' => __('A draft homestay is hidden from your booking page. Set it to Active.'),
            'done' => Property::query()->where('status', Property::STATUS_ACTIVE)->exists(),
            'cta' => __('Review status'),
            'route' => route('tenant.properties.index'),
        ];
    }

    /**
     * Two ways to be payable: an online gateway resolves for this tenant, or the
     * host has written down where to bank-transfer. Manual payment is always
     * offered on the public page, but it's useless if the guest is never told
     * which account to pay into — so bank details are what "done" means here.
     */
    private function paymentSetUp(Tenant $tenant): array
    {
        $gateway = app(CreateGatewayBill::class)->resolveProvider($tenant->id);
        $manual = $tenant->hasBankDetails() || $tenant->manualPaymentInstructions() !== null;

        return [
            'key' => 'payment',
            'title' => __('Tell guests how to pay you'),
            'body' => $gateway
                ? __('Online payments run through :gateway.', ['gateway' => CreateGatewayBill::displayName($gateway)])
                : __('Add your bank details for transfers, or connect an online payment gateway.'),
            'done' => $gateway !== null || $manual,
            'cta' => __('Payment settings'),
            'route' => route('tenant.settings.index'),
        ];
    }

    /**
     * Deliberately blunt copy. Guest email is genuinely dead while AWS SES sits
     * in sandbox mode, so an unconnected WhatsApp means the host's guests
     * receive nothing at all — no invoice, no receipt, no reminder.
     */
    private function connectWhatsapp(Tenant $tenant): array
    {
        $connected = (bool) $tenant->whatsappSession?->isConnected();

        return [
            'key' => 'whatsapp',
            'title' => __('Connect WhatsApp'),
            'body' => __('Scan a QR code once. Invoices, receipts and reminders reach your guests here.'),
            'done' => $connected,
            'cta' => __('Connect'),
            'route' => route('tenant.integrations.show', 'whatsapp'),
        ];
    }

    /**
     * Not a task the host performs in the app — it ticks itself the moment a
     * real booking lands, which is the only honest proof the link works.
     */
    private function firstBooking(Tenant $tenant): array
    {
        return [
            'key' => 'booking',
            'title' => __('Share your booking link'),
            'body' => __('Send :url to your guests. This ticks itself on your first booking.', [
                'url' => preg_replace('#^https?://#', '', $tenant->publicUrl()),
            ]),
            'done' => Booking::query()->exists(),
            'cta' => __('Open booking page'),
            'route' => $tenant->publicUrl(),
        ];
    }
}
