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
        ]);
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
