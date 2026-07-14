<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report — {{ $periodStart->format('M Y') }} to {{ $periodEnd->format('M Y') }}</title>
    <style>
        @page { margin: 28mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1c1c1c; line-height: 1.45; }
        h1 { font-size: 22pt; margin: 0 0 4mm; font-weight: 600; letter-spacing: -.02em; }
        h2 { font-size: 12pt; margin: 8mm 0 3mm; text-transform: uppercase; letter-spacing: .08em; color: #555; font-weight: 600; }
        .meta { font-size: 9pt; color: #777; margin-bottom: 6mm; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .kpis td { border: .5pt solid #ddd; padding: 5mm; vertical-align: top; width: 25%; }
        .kpi-label { font-size: 8pt; color: #777; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 2mm; }
        .kpi-value { font-size: 16pt; font-weight: 600; }
        .kpi-delta { font-size: 8.5pt; color: #444; margin-top: 1mm; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        table.data th, table.data td { padding: 2.5mm 3mm; border-bottom: .5pt solid #eee; text-align: left; font-size: 9pt; }
        table.data th { background: #f6f5f1; font-size: 8pt; color: #555; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; }
        table.data td.num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .footer { margin-top: 12mm; padding-top: 4mm; border-top: .5pt solid #ddd; font-size: 8pt; color: #888; }
        .bar { display: inline-block; height: 4mm; background: #2d4a3a; border-radius: 1mm; vertical-align: middle; margin-right: 2mm; }
    </style>
</head>
<body>
    <h1>{{ $tenant->business_name ?? config('app.name') }}</h1>
    <div class="meta">
        Performance report · {{ $periodStart->format('M Y') }} → {{ $periodEnd->format('M Y') }} (trailing 12 months)
        · Generated {{ $generatedAt->format('d M Y, H:i') }}
    </div>

    <h2>Headline KPIs</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Gross sales</div>
                <div class="kpi-value">RM {{ number_format($totalRevenue, 0) }}</div>
                <div class="kpi-delta">{{ $revDelta !== null ? (($revDelta >= 0 ? '+' : '').round($revDelta * 100).'%').' vs prior 12 mo' : 'no prior data' }}</div>
            </td>
            <td>
                <div class="kpi-label">Occupancy avg</div>
                <div class="kpi-value">{{ number_format($occupancyAvg * 100, 1) }}%</div>
                <div class="kpi-delta">{{ $occDelta !== null ? (($occDelta >= 0 ? '+' : '').round($occDelta * 100).'pp') : 'no prior data' }}</div>
            </td>
            <td>
                <div class="kpi-label">ADR</div>
                <div class="kpi-value">RM {{ number_format($adr, 0) }}</div>
                <div class="kpi-delta">{{ $adrDelta !== null ? (($adrDelta >= 0 ? '+' : '').round($adrDelta * 100).'%') : 'no prior data' }}</div>
            </td>
            <td>
                <div class="kpi-label">RevPAR</div>
                <div class="kpi-value">RM {{ number_format($revPAR, 0) }}</div>
                <div class="kpi-delta">RM/available room/night</div>
            </td>
        </tr>
    </table>

    <h2>Monthly profit &amp; loss</h2>
    <p style="color:#888; font-size:9pt; margin-top:-4px;">Sales = billed to guests · Revenue = sales − SST &amp; tourism tax · Profit = revenue − expenses (RM).</p>
    <table class="data">
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Sales</th>
                <th class="num">Revenue</th>
                <th class="num">Expenses</th>
                <th class="num">Profit</th>
                <th class="num">Occupancy</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($monthly as $m)
                <tr>
                    <td>{{ $m['label'] }}</td>
                    <td class="num">{{ number_format($m['sales'], 0) }}</td>
                    <td class="num">{{ number_format($m['netRevenue'], 0) }}</td>
                    <td class="num">{{ number_format($m['expenses'], 0) }}</td>
                    <td class="num">{{ number_format($m['profit'], 0) }}</td>
                    <td class="num">{{ number_format($m['occupancy'] * 100, 0) }}%</td>
                </tr>
            @endforeach
            <tr style="font-weight:bold; border-top:2px solid #ccc;">
                <td>Total</td>
                <td class="num">{{ number_format($totalRevenue, 0) }}</td>
                <td class="num">{{ number_format($totalNetRevenue, 0) }}</td>
                <td class="num">{{ number_format($totalExpenses, 0) }}</td>
                <td class="num">{{ number_format($totalProfit, 0) }}</td>
                <td class="num">{{ number_format($occupancyAvg * 100, 0) }}%</td>
            </tr>
        </tbody>
    </table>

    <h2>Expenses breakdown</h2>
    @if ($totalExpenses <= 0)
        <p style="color: #888; font-size: 9pt;">No expenses recorded in this period.</p>
    @else
        <table class="data">
            <thead><tr><th>Category</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
            <tbody>
                @php
                    $expenseRows = [
                        'Cleaning' => (float) $expenseBreakdown['cleaning'],
                        'Laundry' => (float) $expenseBreakdown['laundry'],
                        'Maintenance' => (float) $expenseBreakdown['maintenance'],
                        'Other expenses' => (float) $expenseBreakdown['other'],
                    ];
                @endphp
                @foreach ($expenseRows as $name => $value)
                    <tr>
                        <td>{{ $name }}</td>
                        <td class="num">RM {{ number_format($value, 0) }}</td>
                        <td class="num">{{ number_format(($value / max(1, $totalExpenses)) * 100, 0) }}%</td>
                    </tr>
                @endforeach
                <tr style="font-weight:bold; border-top:2px solid #ccc;">
                    <td>Total</td>
                    <td class="num">RM {{ number_format($totalExpenses, 0) }}</td>
                    <td class="num">100%</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Revenue by property</h2>
    @if ($properties->isEmpty())
        <p style="color: #888; font-size: 9pt;">No revenue in this period.</p>
    @else
        <table class="data">
            <thead><tr><th>Property</th><th class="num">Stays</th><th class="num">Nights</th><th class="num">Revenue</th><th class="num">ADR</th></tr></thead>
            <tbody>
                @foreach ($properties as $p)
                    <tr>
                        <td>{{ $p['name'] }}</td>
                        <td class="num">{{ $p['stays'] }}</td>
                        <td class="num">{{ $p['nights'] }}</td>
                        <td class="num">RM {{ number_format($p['rev'], 0) }}</td>
                        <td class="num">RM {{ $p['adr'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Channel mix</h2>
    @if ($channels->isEmpty())
        <p style="color: #888; font-size: 9pt;">No bookings in this period.</p>
    @else
        <table class="data">
            <thead><tr><th>Channel</th><th class="num">Revenue</th><th class="num">Share</th></tr></thead>
            <tbody>
                @foreach ($channels as $name => $value)
                    <tr>
                        <td>{{ ucfirst((string) $name) }}</td>
                        <td class="num">RM {{ number_format($value, 0) }}</td>
                        <td class="num">{{ number_format(($value / $channelTotal) * 100, 0) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Confidential · {{ $tenant->business_name ?? config('app.name') }} · Generated by Tempahlah
    </div>
</body>
</html>
