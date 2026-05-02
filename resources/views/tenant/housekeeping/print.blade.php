<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Run sheet — {{ $today->format('d M Y') }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1c1c1c; line-height: 1.45; }
        h1 { font-size: 18pt; margin: 0 0 1mm; font-weight: 600; letter-spacing: -.02em; }
        h2 { font-size: 11pt; margin: 7mm 0 2mm; text-transform: uppercase; letter-spacing: .08em; color: #555; font-weight: 600; }
        .meta { font-size: 9pt; color: #777; margin-bottom: 5mm; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 2.5mm 3mm; background: #f6f5f1; font-size: 8pt; color: #555; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; text-align: left; }
        td { padding: 3mm; border-bottom: .5pt solid #eee; vertical-align: top; }
        td.mono { font-family: DejaVu Sans Mono, monospace; }
        .pill { display: inline-block; padding: 1mm 3mm; border-radius: 999px; font-size: 8pt; font-weight: 500; }
        .pill-pending { background: #f0ede5; color: #555; }
        .pill-progress { background: #fbeede; color: #b85e0c; }
        .pill-done { background: #def0e0; color: #1c6c2e; }
        .pill-issue { background: #f5d8d2; color: #b53d2e; }
        .check { width: 8mm; height: 8mm; border: .8pt solid #333; display: inline-block; vertical-align: middle; }
        .footer { margin-top: 12mm; padding-top: 3mm; border-top: .5pt solid #ddd; font-size: 8pt; color: #888; }
    </style>
</head>
<body>
    <h1>Run sheet · {{ $today->format('l, j F Y') }}</h1>
    <div class="meta">
        {{ $tenant->business_name ?? config('app.name') }}
        · {{ $cleaning->count() }} cleaning task{{ $cleaning->count() === 1 ? '' : 's' }}
        · {{ $laundry->count() }} laundry batch{{ $laundry->count() === 1 ? '' : 'es' }}
    </div>

    <h2>Cleaning</h2>
    @if ($cleaning->isEmpty())
        <p style="color: #888; font-size: 9pt;">No cleaning scheduled today.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 8mm;">✓</th>
                    <th style="width: 16mm;">Time</th>
                    <th>Property / Room</th>
                    <th>Type</th>
                    <th>Assignee</th>
                    <th>Status</th>
                    <th>Booking handoff</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cleaning as $t)
                    @php
                        $cls = match ($t->status) {
                            'pending' => 'pill-pending',
                            'in_progress' => 'pill-progress',
                            'completed' => 'pill-done',
                            'skipped' => 'pill-issue',
                            default => 'pill-pending',
                        };
                    @endphp
                    <tr>
                        <td><span class="check"></span></td>
                        <td class="mono">{{ $t->scheduled_at->format('H:i') }}</td>
                        <td>
                            <strong>{{ $t->property?->name ?? '—' }}</strong>
                            @if ($t->room)<br><span style="font-size: 9pt; color: #777;">{{ $t->room->name }}</span>@endif
                        </td>
                        <td>{{ ucfirst($t->type) }}</td>
                        <td>{{ $t->assignee?->name ?? '—' }}</td>
                        <td><span class="pill {{ $cls }}">{{ str_replace('_', ' ', ucfirst($t->status)) }}</span></td>
                        <td style="font-size: 9pt; color: #555;">
                            @if ($t->booking)
                                {{ $t->booking->guest?->name ?? 'Guest' }} arriving
                            @endif
                            @if ($t->notes)<br><em>"{{ $t->notes }}"</em>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Laundry</h2>
    @if ($laundry->isEmpty())
        <p style="color: #888; font-size: 9pt;">No active laundry batches.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 8mm;">✓</th>
                    <th>Property</th>
                    <th>Vendor</th>
                    <th>Items</th>
                    <th>Pickup</th>
                    <th>Expected return</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($laundry as $l)
                    <tr>
                        <td><span class="check"></span></td>
                        <td><strong>{{ $l->property?->name ?? '—' }}</strong></td>
                        <td>{{ $l->vendor_name ?? 'Self-service' }}</td>
                        <td class="mono">{{ $l->item_count }}</td>
                        <td class="mono">{{ optional($l->pickup_at)->format('d M H:i') ?? '—' }}</td>
                        <td class="mono">{{ optional($l->expected_return_at)->format('d M H:i') ?? '—' }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($l->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated {{ now()->format('d M Y, H:i') }} · {{ config('app.name') }}
    </div>
</body>
</html>
