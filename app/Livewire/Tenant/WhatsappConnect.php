<?php

namespace App\Livewire\Tenant;

use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\Sidecar\SidecarClient;
use App\Services\WhatsApp\WhatsappMessenger;
use App\Support\Tenancy\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tenant-facing WhatsApp connect panel.
 *
 * Lives at /dashboard/integrations/whatsapp. Polls every 3s for state
 * updates pushed by the sidecar via webhook into whatsapp_sessions.
 */
class WhatsappConnect extends Component
{
    public ?int $tenantId = null;

    // Test send form.
    public string $testPhone = '';
    public string $testName = '';
    public ?string $flash = null;
    public ?string $flashKind = null; // 'ok' | 'err'

    public function mount(): void
    {
        $this->tenantId = app(TenantContext::class)->current()?->id;
    }

    #[Computed]
    public function session(): WhatsappSession
    {
        return WhatsappSession::firstOrCreate(
            ['tenant_id' => $this->tenantId],
            ['status' => WhatsappSession::STATUS_DISCONNECTED]
        );
    }

    #[Computed]
    public function sidecarReachable(): bool
    {
        return app(SidecarClient::class)->isReachable();
    }

    #[Computed]
    public function recentMessages()
    {
        return WhatsappMessage::query()
            ->where('direction', WhatsappMessage::DIRECTION_OUT)
            ->latest()
            ->limit(8)
            ->get();
    }

    public function start(SidecarClient $sidecar): void
    {
        try {
            $sidecar->start($this->tenantId);
            $this->session()->update([
                'status' => WhatsappSession::STATUS_CONNECTING,
                'last_error' => null,
            ]);
            $this->flashOk(__('Starting session. Scan the QR within ~60 seconds.'));
        } catch (\Throwable $e) {
            $this->flashErr(__('Could not reach the WhatsApp sidecar: :err', ['err' => $e->getMessage()]));
        }
    }

    public function disconnect(SidecarClient $sidecar): void
    {
        try {
            $sidecar->logout($this->tenantId);
        } catch (\Throwable) {
            // Sidecar might already be gone; we still wipe local state.
        }

        $this->session()->update([
            'status' => WhatsappSession::STATUS_DISCONNECTED,
            'qr_payload' => null,
            'phone_e164' => null,
            'push_name' => null,
            'disconnected_at' => now(),
            'last_error' => null,
        ]);
        $this->flashOk(__('WhatsApp disconnected. You can reconnect anytime.'));
    }

    public function sendTest(): void
    {
        $this->validate([
            'testPhone' => 'required|string|max:24',
            'testName'  => 'nullable|string|max:60',
        ]);

        $normalized = PhoneNumber::normalize($this->testPhone);
        if (! $normalized) {
            $this->flashErr(__('Phone number could not be parsed. Use +60123456789.'));
            return;
        }

        $tenant = app(TenantContext::class)->current();
        $msg = WhatsappMessenger::dispatchTest($tenant, $normalized, $this->testName ?: null);

        if (! $msg) {
            $this->flashErr(__('Session is not connected.'));
            return;
        }

        $this->reset(['testPhone', 'testName']);
        $this->flashOk(__('Test message queued. Delivery state will show in the table.'));
    }

    public function refresh(): void
    {
        // No-op: the polling rerender will pick up new state from the DB.
    }

    public function render()
    {
        return view('livewire.tenant.whatsapp-connect');
    }

    protected function flashOk(string $msg): void
    {
        $this->flash = $msg;
        $this->flashKind = 'ok';
    }

    protected function flashErr(string $msg): void
    {
        $this->flash = $msg;
        $this->flashKind = 'err';
    }
}
