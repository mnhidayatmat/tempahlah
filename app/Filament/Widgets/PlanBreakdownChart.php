<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\ChartWidget;

/**
 * Visual split of the subscriber base for the super-admin dashboard:
 * Free vs Active paid vs Trial vs Past due vs Cancelled.
 */
class PlanBreakdownChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Plan distribution';

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = Subscription::query()
            ->selectRaw('plan, status, COUNT(*) as c')
            ->groupBy('plan', 'status')
            ->get();

        $count = fn (?string $plan, ?string $status) => (int) $rows
            ->when($plan !== null, fn ($r) => $r->where('plan', $plan))
            ->when($status !== null, fn ($r) => $r->where('status', $status))
            ->sum('c');

        $segments = [
            ['Free',       $count(Subscription::PLAN_FREE, null),                    '#9ca3af'],
            ['Active paid', $count(Subscription::PLAN_PAID, Subscription::STATUS_ACTIVE), '#3f8b6a'],
            ['On trial',   $count(null, Subscription::STATUS_TRIALING),             '#2cb8c4'],
            ['Past due',   $count(null, Subscription::STATUS_PAST_DUE),             '#e8b94a'],
            ['Cancelled',  $count(null, Subscription::STATUS_CANCELLED),            '#b94a3a'],
        ];

        // Drop empty segments so the doughnut only shows what actually exists.
        $segments = array_values(array_filter($segments, fn ($s) => $s[1] > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn ($s) => $s[1], $segments),
                'backgroundColor' => array_map(fn ($s) => $s[2], $segments),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn ($s) => $s[0], $segments),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
