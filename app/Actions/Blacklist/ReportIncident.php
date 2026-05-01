<?php

namespace App\Actions\Blacklist;

use App\Models\Booking;
use App\Models\GuestBlacklistEntry;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportIncident
{
    public function execute(Booking $booking, User $reporter, array $data): IncidentReport
    {
        return DB::transaction(function () use ($booking, $reporter, $data) {
            $report = IncidentReport::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'property_id' => $booking->property_id,
                'reported_by_user_id' => $reporter->id,
                'guest_user_id' => $booking->guest_id,
                'category' => $data['category'],
                'severity' => $data['severity'] ?? 'low',
                'description' => $data['description'],
                'evidence_paths' => $data['evidence_paths'] ?? null,
                'damage_estimate' => $data['damage_estimate'] ?? null,
                'police_report_number' => $data['police_report_number'] ?? null,
                'escalate_to_blacklist' => (bool) ($data['escalate_to_blacklist'] ?? false),
            ]);

            if ($report->escalate_to_blacklist && $booking->guest_id) {
                $entry = GuestBlacklistEntry::create([
                    'guest_user_id' => $booking->guest_id,
                    'reported_by_tenant_id' => $booking->tenant_id,
                    'booking_id' => $booking->id,
                    'severity' => $data['blacklist_severity'] ?? GuestBlacklistEntry::SEVERITY_WARNING,
                    'reason_code' => $data['category'],
                    'description' => $data['description'],
                    'evidence_paths' => $data['evidence_paths'] ?? null,
                    'review_status' => GuestBlacklistEntry::STATUS_PENDING,
                ]);

                $report->update(['blacklist_entry_id' => $entry->id]);
            }

            return $report->fresh();
        });
    }
}
