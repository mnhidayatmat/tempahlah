<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Laravel\Pennant\Feature;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $tenant?->loadMissing(['subscription', 'owner']);

        $properties = Property::query()
            ->with(['rooms:id,property_id,base_price'])
            ->orderByDesc('created_at')
            ->get();

        return view('tenant.settings.index', [
            'tenant'     => $tenant,
            'properties' => $properties,
        ]);
    }

    /**
     * Live availability check for the booking-page slug, called as the host
     * types. Advisory only — settings.update still enforces uniqueness +
     * reserved-slug + format server-side, so this can never be the sole guard.
     * Mirrors those exact rules so the live answer matches what save will do.
     */
    public function slugAvailable(Request $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $slug = strtolower(trim((string) $request->query('slug', '')));

        if ($slug === '') {
            return response()->json(['status' => 'invalid', 'message' => __('Enter a slug.')]);
        }
        if ($slug === $tenant->slug) {
            return response()->json(['status' => 'current', 'message' => __('This is your current address.')]);
        }
        if (strlen($slug) < 2 || strlen($slug) > 60 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return response()->json(['status' => 'invalid', 'message' => __('Use lowercase letters, numbers and single hyphens (2–60 characters).')]);
        }
        if (in_array($slug, CreateTenantAndOwner::reservedSlugs(), true)) {
            return response()->json(['status' => 'reserved', 'message' => __('That slug is reserved. Please pick another.')]);
        }

        // `tenants` is a cross-tenant table (no BelongsToTenant scope), so this
        // sees every tenant — a genuine platform-wide uniqueness check.
        $taken = Tenant::where('slug', $slug)->where('id', '!=', $tenant->id)->exists();

        return response()->json($taken
            ? ['status' => 'taken', 'message' => __('That slug is already taken. Please pick another.')]
            : ['status' => 'available', 'message' => __('Available — this address is free.')]);
    }

    public function update(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        // Automatic check-out reminders (auto WhatsApp) are a Pro feature. The
        // free-tenant form submits them disabled, but guard server-side too so a
        // crafted POST can never enable them.
        $canAutoRemind = $tenant->isPaid();

        $reservedSlugs = CreateTenantAndOwner::reservedSlugs();

        $validated = $request->validate([
            'business_name'   => 'required|string|max:120',
            'business_email'  => 'required|email|max:160',
            'business_phone'  => 'nullable|string|max:32',
            // The owner's display name lives on the User record, not the tenant,
            // so it's pulled out of $validated before the tenant fill() below.
            'owner_name'      => 'required|string|max:120',
            'ssm_number'      => 'nullable|string|max:32',
            'motac_license'   => 'nullable|string|max:64',
            'slug'            => [
                'required', 'string', 'min:2', 'max:60',
                // Lowercase letters, digits, hyphens. Hyphens can't be at the
                // edges and can't double up. Same shape DNS will tolerate.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tenants', 'slug')->ignore($tenant->id),
                Rule::notIn($reservedSlugs),
            ],
            'sst_registered'  => 'sometimes|boolean',
            'sst_rate'        => 'nullable|numeric|min:0|max:1',
            'default_locale'  => 'required|in:ms,en',
            'full_payment_days_before' => 'required|integer|min:0|max:60',
            'fee_payment_hours'        => 'required|integer|min:1|max:336',
            'cancel_balance_on'        => ['required', Rule::in([
                Tenant::CANCEL_BALANCE_DUE_DATE,
                Tenant::CANCEL_BALANCE_CHECK_IN,
            ])],
            'auto_cancel_unpaid_balance' => 'nullable|boolean',
            'deposit_is_security'      => 'nullable|boolean',
            'manual_payment_instructions' => 'nullable|string|max:2000',
            'refund_policy'            => 'nullable|string|max:2000',
            'checkout_reminder_enabled' => 'nullable|boolean',
            // Only required when the tenant can actually use it (Pro); a free
            // tenant's disabled field submits nothing, so don't demand it.
            'checkout_reminder_hours'  => ($canAutoRemind ? 'required' : 'nullable').'|integer|min:1|max:72',
            'checkout_reminder_message' => 'nullable|string|max:2000',
            'auto_housekeeping'        => 'nullable|boolean',
            'primary_color'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'slug.regex'   => __('Slug can only contain lowercase letters, numbers, and single hyphens (e.g. wafa-homestay).'),
            'slug.unique'  => __('That slug is already taken by another homestay. Please pick another.'),
            'slug.not_in'  => __('That slug is reserved (it conflicts with a system subdomain). Please pick another.'),
            'primary_color.regex'   => __('Pick a valid hex color (e.g. #2596c6).'),
            'secondary_color.regex' => __('Pick a valid hex color (e.g. #2cb8c4).'),
            'accent_color.regex'    => __('Pick a valid hex color (e.g. #e8b94a).'),
        ]);

        // Owner name updates the User record, not the tenant, so lift it out
        // before the tenant fill() below (owner_name is not a tenants column).
        $ownerName = trim($validated['owner_name']);
        unset($validated['owner_name']);

        // Store the business phone in E.164 — it drives the public page's
        // wa.me links and the WhatsApp sender. Unparseable input is kept as
        // typed rather than blanked.
        if (! empty($validated['business_phone'])) {
            $validated['business_phone'] = \App\Services\WhatsApp\PhoneNumber::normalize($validated['business_phone'])
                ?? $validated['business_phone'];
        }

        $validated['sst_registered'] = $request->boolean('sst_registered');
        $validated['auto_cancel_unpaid_balance'] = $request->boolean('auto_cancel_unpaid_balance');
        $validated['deposit_is_security'] = $request->boolean('deposit_is_security');
        if ($canAutoRemind) {
            $validated['checkout_reminder_enabled'] = $request->boolean('checkout_reminder_enabled');
        } else {
            // Free tenant — never write reminder settings. Leave any existing DB
            // values untouched; the dispatch command gates on paid tier too, so
            // a value left over from a prior Pro period will not fire.
            unset(
                $validated['checkout_reminder_enabled'],
                $validated['checkout_reminder_hours'],
                $validated['checkout_reminder_message'],
            );
        }
        // Auto-scheduling cleaning & laundry is a Pro (auto_operational_tasks)
        // feature. Free tenants submit it disabled; guard server-side too so a
        // crafted POST can't enable it. Leave any existing DB value untouched.
        if ($tenant->isPaid()) {
            $validated['auto_housekeeping'] = $request->boolean('auto_housekeeping');
        } else {
            unset($validated['auto_housekeeping']);
        }
        if (! $validated['sst_registered']) {
            $validated['sst_rate'] = 0;
        } elseif (empty($validated['sst_rate'])) {
            $validated['sst_rate'] = 0.08;
        }

        // Brand & theme is a Pro feature. Free tenants can't change the palette,
        // so drop the colour keys entirely (leaving any stored values untouched)
        // rather than trusting a directly-POSTed field.
        if (\Laravel\Pennant\Feature::active('brand_theme')) {
            foreach (['primary_color', 'secondary_color', 'accent_color'] as $key) {
                $validated[$key] = ! empty($validated[$key])
                    ? '#'.strtolower(ltrim($validated[$key], '#'))
                    : null;
            }
            // primary_color column is NOT NULL — fall back to the platform default
            // when the tenant clears it. secondary/accent stay nullable.
            $validated['primary_color'] ??= \App\Models\Tenant::THEME_DEFAULTS['primary'];
        } else {
            unset(
                $validated['primary_color'],
                $validated['secondary_color'],
                $validated['accent_color'],
            );
        }

        $oldSlug = $tenant->slug;
        $tenant->fill($validated)->save();

        // Owner's display name (on the User record). Only write when it actually
        // changed so we don't touch the row on every settings save.
        $owner = $tenant->owner;
        if ($owner && $ownerName !== '' && $owner->name !== $ownerName) {
            $owner->forceFill(['name' => $ownerName])->save();
        }

        $msg = $oldSlug !== $tenant->slug
            ? __('Settings saved. Your booking page is now :url — the old :old.tempahlah.com address no longer works.', [
                'url' => str_replace(['https://', 'http://'], '', $tenant->publicUrl()),
                'old' => $oldSlug,
              ])
            : __('Settings saved.');

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', $msg);
    }

    /**
     * Invoice & document branding — the logo, tagline, address, terms and the
     * bank/QR payment details that print on every invoice and receipt. Kept as
     * its own multipart form so the main (non-file) settings form stays simple.
     */
    public function updateBranding(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'invoice_tagline'     => 'nullable|string|max:160',
            'business_address'    => 'nullable|string|max:255',
            'bank_name'           => 'nullable|string|max:120',
            'bank_account_holder' => 'nullable|string|max:120',
            'bank_account_number' => 'nullable|string|max:60',
            'invoice_terms'       => 'nullable|string|max:2000',
            'logo'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'bank_qr'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'remove_logo'         => 'nullable|boolean',
            'remove_qr'           => 'nullable|boolean',
        ]);

        $disk = config('filesystems.default', 'spaces');

        // Bank details and the business address stay editable on every plan —
        // manualPayHow() reads them to tell a free tenant's guest where to send
        // the money. Only the fields that exist purely to dress an invoice or
        // receipt PDF (tagline, terms, logo, payment QR) are Pro-only.
        $canBrandDocuments = Feature::for($tenant)->active('invoice_documents');

        $updates = [
            'business_address'    => $validated['business_address'] ?? null,
            'bank_name'           => $validated['bank_name'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
        ];

        if ($canBrandDocuments) {
            $updates['invoice_tagline'] = $validated['invoice_tagline'] ?? null;
            $updates['invoice_terms'] = $validated['invoice_terms'] ?? null;
        }

        try {
            if ($canBrandDocuments) {
                // Logo — replace, remove, or leave as-is.
                if ($request->boolean('remove_logo')) {
                    $this->deleteBrandImage($tenant->logo_path, $disk);
                    $updates['logo_path'] = null;
                } elseif ($request->hasFile('logo')) {
                    $new = $this->storeBrandImage($request->file('logo'), $tenant->id, 'logo', 640, $disk);
                    $this->deleteBrandImage($tenant->logo_path, $disk);
                    $updates['logo_path'] = $new;
                }

                // Payment QR.
                if ($request->boolean('remove_qr')) {
                    $this->deleteBrandImage($tenant->bank_qr_path, $disk);
                    $updates['bank_qr_path'] = null;
                } elseif ($request->hasFile('bank_qr')) {
                    $new = $this->storeBrandImage($request->file('bank_qr'), $tenant->id, 'qr', 900, $disk);
                    $this->deleteBrandImage($tenant->bank_qr_path, $disk);
                    $updates['bank_qr_path'] = $new;
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Image upload failed: :err', ['err' => $e->getMessage()]));
        }

        $tenant->fill($updates)->save();

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Invoice & document branding saved.'));
    }

    /**
     * Render a live sample invoice (or ?type=receipt) with the tenant's current
     * branding so the host can see exactly how their documents look. Uses
     * throwaway in-memory models — nothing is persisted.
     */
    public function invoicePreview(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        // Renders a real invoice/receipt document — Pro only.
        abort_unless(Feature::for($tenant)->active('invoice_documents'), 403, 'Invoices and receipts are a Pro feature.');

        $type = $request->query('type') === 'receipt'
            ? \App\Models\Invoice::TYPE_RECEIPT
            : \App\Models\Invoice::TYPE_INVOICE;

        $property = new Property([
            'name'              => __('Deluxe Homestay'),
            'address_line1'     => $tenant->business_address ?: 'No. 1, Lorong Benar',
            'city'              => 'Kluang',
            'state'             => 'Johor',
            'postcode'          => '86000',
            'booking_fee_label' => __('Booking fee'),
        ]);

        $booking = new \App\Models\Booking([
            'reference'          => 'SAMPLE-0014',
            'check_in'           => now()->addDays(20)->startOfDay(),
            'check_out'          => now()->addDays(21)->startOfDay(),
            'nights'             => 1,
            'adults'             => 2,
            'children'           => 0,
            'base_amount'        => 799,
            'total_amount'       => 799,
            'booking_fee_amount' => 0,
            'sst_amount'         => 0,
            'tourism_tax_amount' => 0,
            'currency'           => 'MYR',
        ]);
        $booking->tenant_id = $tenant->id;
        $booking->setRelation('property', $property);
        $booking->setRelation('tenant', $tenant);

        $invoice = new \App\Models\Invoice([
            'invoice_number'     => $type === \App\Models\Invoice::TYPE_RECEIPT ? 'SAMPLE-RCP-14' : 'SAMPLE-INV-14',
            'document_type'      => $type,
            'locale'             => $tenant->default_locale,
            'issued_on'          => now(),
            'billed_to'          => ['name' => 'EPIC Society', 'email' => 'guest@example.com', 'phone' => '011-2624 1887'],
            'line_items'         => [[
                'description' => __('Homestay for :n night', ['n' => 1]),
                'quantity'    => 1,
                'unit_price'  => 799,
                'total'       => 799,
            ]],
            'subtotal'           => 799,
            'sst_amount'         => 0,
            'tourism_tax_amount' => 0,
            'discount_amount'    => 0,
            'total'              => 799,
            'currency'           => 'MYR',
        ]);
        $invoice->setRelation('booking', $booking);

        $payment = $type === \App\Models\Invoice::TYPE_RECEIPT
            ? new \App\Models\Payment(['amount' => 799, 'method' => 'manual', 'type' => 'full', 'paid_at' => now()])
            : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
            'invoice'  => $invoice,
            'tenant'   => $tenant,
            'template' => null,
            'booking'  => $booking,
            'payment'  => $payment,
        ]);

        return $pdf->stream('invoice-preview.pdf');
    }

    /** Resize + re-encode a brand image to PNG (keeps logo/QR transparency) and store it. */
    protected function storeBrandImage(UploadedFile $file, int $tenantId, string $kind, int $maxWidth, string $disk): string
    {
        $image = (new ImageManager(new GdDriver()))->decodeSplFileInfo($file);
        $image->scaleDown(width: $maxWidth);
        $binary = (string) $image->encode(new PngEncoder());

        $path = sprintf('tenants/%d/branding/%s-%s.png', $tenantId, $kind, Str::ulid());
        Storage::disk($disk)->put($path, $binary, 'public');

        return $path;
    }

    protected function deleteBrandImage(?string $path, string $disk): void
    {
        if (! $path) {
            return;
        }
        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
