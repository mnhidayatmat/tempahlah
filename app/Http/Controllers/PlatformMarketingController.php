<?php

namespace App\Http\Controllers;

use App\Jobs\SendMarketingCampaign;
use App\Mail\MarketingCampaignMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Platform Admin → Email marketing. Campaigns Tempahlah sends to its hosts
 * (e.g. free → Pro upgrade pitches): compose (Markdown + personalization
 * tokens), pick an audience by effective tier, test-send to yourself, then
 * send — with a per-recipient delivery log and a PDPA unsubscribe.
 *
 * Trust model: only is_platform_admin users reach any of this (platform.admin
 * middleware); the unsubscribe endpoint is public but signed.
 */
class PlatformMarketingController extends Controller
{
    /** Prefilled free → Pro pitch shown on the compose form (BM primary). */
    public const DEFAULT_SUBJECT = 'Naik taraf ke Pro — cuba 7 hari percuma 🚀';

    public const DEFAULT_BODY = <<<'MD'
Salam {name},

Terima kasih kerana menggunakan Tempahlah untuk **{business_name}**! 🙏

Tahukah anda, dengan pelan **Pro (RM49/bulan)** anda boleh:

- 🤖 **AI Agent WhatsApp 24/7** — balas pertanyaan tetamu, bagi sebut harga, semak kekosongan secara automatik
- 💳 **Gateway bayaran sendiri** (SecurePay / Toyyibpay / Billplz) — tetamu bayar online, tempahan disahkan automatik
- 📅 **Sync kalendar Airbnb & Booking.com** — tiada lagi double-booking
- 🧾 **Invois & resit PDF berjenama** — dihantar automatik ke email + WhatsApp tetamu
- 🏠 Sehingga **3 homestay** + subdomain sendiri ({business_name} di nama-anda.tempahlah.com)

Cuba **percuma selama 7 hari** — batal bila-bila masa sebelum hari ke-7, tiada caj.

[Naik taraf sekarang]({upgrade_url})

Ada soalan? Balas sahaja email ini.

Terima kasih,
Pasukan Tempahlah
MD;

    public function index()
    {
        return view('platform.marketing.index', [
            'campaigns' => MarketingCampaign::query()->latestFirst()->paginate(15),
            // The automated new-host drip, with per-step delivery counts.
            'onboardingSteps' => \App\Models\OnboardingEmail::query()
                ->withCount([
                    'sends as sent_total' => fn ($q) => $q->where('status', 'sent'),
                    'sends as skipped_total' => fn ($q) => $q->where('status', 'skipped'),
                    'sends as failed_total' => fn ($q) => $q->where('status', 'failed'),
                ])
                ->orderBy('day_offset')
                ->get(),
        ]);
    }

    /* ── Onboarding series (automated new-host drip) ─────────────────────── */

    /** Blank new-step form. step_no is system-assigned on save (immutable). */
    public function createOnboarding()
    {
        // Suggest the next day slot so a new step naturally lands after the last.
        $nextDay = (int) (\App\Models\OnboardingEmail::max('day_offset') ?? 0) + 3;

        return view('platform.marketing.onboarding-form', [
            'step' => null,
            'suggestedDay' => min($nextDay, 60),
        ]);
    }

    public function storeOnboarding(Request $request)
    {
        $validated = $this->validatedOnboarding($request);

        $step = \App\Models\OnboardingEmail::create($validated + [
            // step_no is a stable internal id, not a display order — the series
            // is sorted by day_offset. Assign the next free number to stay unique.
            'step_no' => (int) (\App\Models\OnboardingEmail::max('step_no') ?? 0) + 1,
            'enabled' => $request->boolean('enabled'),
            'skip_if_paid' => $request->boolean('skip_if_paid'),
        ]);

        return redirect()->route('platform.marketing.index')
            ->with('status', __('Onboarding step :n added (day +:d).', ['n' => $step->step_no, 'd' => $step->day_offset]));
    }

    public function editOnboarding(\App\Models\OnboardingEmail $step)
    {
        return view('platform.marketing.onboarding-form', ['step' => $step]);
    }

    public function updateOnboarding(Request $request, \App\Models\OnboardingEmail $step)
    {
        $validated = $this->validatedOnboarding($request);

        $step->update($validated + [
            'enabled' => $request->boolean('enabled'),
            'skip_if_paid' => $request->boolean('skip_if_paid'),
        ]);

        return redirect()->route('platform.marketing.index')
            ->with('status', __('Onboarding step :n updated.', ['n' => $step->step_no]));
    }

    /**
     * Delete an onboarding step. Its per-tenant send log rows cascade away
     * (FK cascadeOnDelete) — those are just history, and every remaining step
     * keeps its own idempotency, so the drip is unaffected for other steps.
     */
    public function destroyOnboarding(\App\Models\OnboardingEmail $step)
    {
        $n = $step->step_no;
        $step->delete();

        return redirect()->route('platform.marketing.index')
            ->with('status', __('Onboarding step :n deleted.', ['n' => $n]));
    }

    /**
     * Only the three text fields — the two checkboxes are read separately via
     * $request->boolean() so an unchecked box reliably writes false (an absent
     * checkbox key would otherwise slip through, and array-union `+` keeps the
     * left value, so mixing them here would drop the boolean).
     */
    protected function validatedOnboarding(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body_md' => ['required', 'string', 'max:20000'],
            'day_offset' => ['required', 'integer', 'min:0', 'max:60'],
        ]);
    }

    /** Send one onboarding step to the signed-in admin only. */
    public function testOnboarding(Request $request, \App\Models\OnboardingEmail $step)
    {
        $admin = $request->user();
        $sampleTenant = Tenant::query()->with('owner')->first();

        if (! $sampleTenant) {
            return back()->with('error', __('No tenant exists to sample the tokens from.'));
        }

        try {
            Mail::to($admin->email)->send(new \App\Mail\OnboardingEmailMail(
                step: $step,
                tenant: $sampleTenant,
                recipientName: $admin->name ?? 'Admin',
                isTest: true,
            ));
        } catch (\Throwable $e) {
            return back()->with('error', __('Test send failed: :err', ['err' => mb_substr($e->getMessage(), 0, 200)]));
        }

        return back()->with('status', __('Test of step :n sent to :email.', ['n' => $step->step_no, 'email' => $admin->email]));
    }

    public function create()
    {
        return view('platform.marketing.form', [
            'campaign' => null,
            'defaults' => ['subject' => self::DEFAULT_SUBJECT, 'body_md' => self::DEFAULT_BODY, 'audience' => 'free'],
            'audienceCounts' => $this->audienceCounts(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $campaign = MarketingCampaign::create($validated + [
            'status' => MarketingCampaign::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('platform.marketing.show', $campaign)
            ->with('status', __('Campaign draft saved. Send yourself a test before sending for real.'));
    }

    public function show(MarketingCampaign $campaign)
    {
        return view('platform.marketing.show', [
            'campaign' => $campaign,
            'audienceCount' => $campaign->isDraft() ? $campaign->resolveAudience()->count() : null,
            'recipients' => $campaign->recipients()
                ->with('tenant:id,business_name')
                ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'skipped' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
                ->orderBy('id')
                ->paginate(50),
        ]);
    }

    public function edit(MarketingCampaign $campaign)
    {
        abort_unless($campaign->isDraft(), 403, 'Only drafts can be edited.');

        return view('platform.marketing.form', [
            'campaign' => $campaign,
            'defaults' => $campaign->only(['subject', 'body_md', 'audience']),
            'audienceCounts' => $this->audienceCounts(),
        ]);
    }

    public function update(Request $request, MarketingCampaign $campaign)
    {
        abort_unless($campaign->isDraft(), 403, 'Only drafts can be edited.');

        $campaign->update($this->validated($request));

        return redirect()->route('platform.marketing.show', $campaign)
            ->with('status', __('Campaign updated.'));
    }

    /** Send the campaign to the signed-in admin only. */
    public function sendTest(Request $request, MarketingCampaign $campaign)
    {
        $admin = $request->user();

        try {
            Mail::to($admin->email)->send(new MarketingCampaignMail(
                campaign: $campaign,
                recipientName: $admin->name ?? 'Admin',
                businessName: 'Contoh Homestay',
                // Signed against a real tenant id so the unsubscribe link in the
                // test renders; the admin obviously shouldn't click it.
                tenantId: (int) (Tenant::value('id') ?? 0),
                isTest: true,
                bookingUrl: rtrim((string) config('app.url'), '/').'/contoh-homestay',
            ));
        } catch (\Throwable $e) {
            return back()->with('error', __('Test send failed: :err', ['err' => mb_substr($e->getMessage(), 0, 200)]));
        }

        $campaign->update(['test_sent_at' => now()]);

        return back()->with('status', __('Test sent to :email.', ['email' => $admin->email]));
    }

    /**
     * Materialize the audience into the delivery log and queue the send.
     * Recipients are frozen at this moment — a tenant upgrading a minute
     * later still receives it (their log row already exists).
     */
    public function send(MarketingCampaign $campaign)
    {
        abort_unless($campaign->isDraft(), 403, 'Campaign has already been sent or queued.');

        $tenants = $campaign->resolveAudience();

        $rows = 0;
        foreach ($tenants as $tenant) {
            $email = MarketingCampaign::emailFor($tenant);
            if (! $email) {
                continue; // unreachable tenant — no owner email, no business email
            }

            MarketingCampaignRecipient::firstOrCreate(
                ['campaign_id' => $campaign->id, 'tenant_id' => $tenant->id],
                ['email' => $email, 'name' => $tenant->owner?->name ?: $tenant->business_name],
            );
            $rows++;
        }

        if ($rows === 0) {
            return back()->with('error', __('No reachable recipients in this audience — nothing to send.'));
        }

        $campaign->update([
            'status' => MarketingCampaign::STATUS_QUEUED,
            'queued_at' => now(),
            'recipients_total' => $rows,
        ]);

        SendMarketingCampaign::dispatch($campaign->id);

        return redirect()->route('platform.marketing.show', $campaign)
            ->with('status', __('Sending to :n recipient(s) — the delivery log below updates as emails go out.', ['n' => $rows]));
    }

    /** Stop a queued/sending campaign at the next recipient. */
    public function cancel(MarketingCampaign $campaign)
    {
        abort_unless($campaign->isRunning(), 403, 'Only a queued or sending campaign can be cancelled.');

        $campaign->update(['status' => MarketingCampaign::STATUS_CANCELLED]);

        return back()->with('status', __('Campaign cancelled — recipients already emailed keep their copy.'));
    }

    public function destroy(MarketingCampaign $campaign)
    {
        abort_unless($campaign->isDraft() || $campaign->status === MarketingCampaign::STATUS_CANCELLED, 403, 'Only drafts and cancelled campaigns can be deleted.');

        $campaign->delete();

        return redirect()->route('platform.marketing.index')
            ->with('status', __('Campaign deleted.'));
    }

    /**
     * Public, signature-verified opt-out. Idempotent; never requires login
     * (the host clicks it from their inbox).
     */
    public function unsubscribe(Request $request, Tenant $tenant)
    {
        abort_unless($request->hasValidSignature(), 403);

        if ($tenant->marketing_opt_out_at === null) {
            $tenant->forceFill(['marketing_opt_out_at' => now()])->save();
        }

        return view('marketing.unsubscribed', ['tenant' => $tenant]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body_md' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::in(array_keys(MarketingCampaign::AUDIENCES))],
        ]);
    }

    /** How many reachable tenants each audience currently holds. */
    protected function audienceCounts(): array
    {
        $tenants = Tenant::query()
            ->with(['subscription', 'owner:id,name,email'])
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('marketing_opt_out_at')
            ->get()
            ->filter(fn (Tenant $t) => MarketingCampaign::emailFor($t) !== null);

        $byTier = $tenants->groupBy(fn (Tenant $t) => $t->planKey());

        return [
            'free' => $byTier->get('free', collect())->count(),
            'pro' => $byTier->get('pro', collect())->count(),
            'ultra' => $byTier->get('ultra', collect())->count(),
            'paid' => $byTier->get('pro', collect())->count() + $byTier->get('ultra', collect())->count(),
            'all' => $tenants->count(),
        ];
    }
}
