<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CleaningTask;
use App\Models\Expense;
use App\Models\LaundryTask;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /** Operational task-cost pseudo-categories folded into the ledger. */
    private const OPERATIONAL_CATEGORIES = [
        'cleaning'    => 'Cleaning',
        'laundry'     => 'Laundry',
        'maintenance' => 'Maintenance',
    ];

    /**
     * Unified spend ledger. Combines manually-entered expenses with the cost of
     * cleaning, laundry and maintenance tasks (sourced from Housekeeping,
     * read-only here) so a host sees ALL spend in one place. Filterable by
     * ?month=YYYY-MM and ?category=. All queries are tenant-scoped via the
     * BelongsToTenant global scope.
     */
    public function index(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        // 1. Manual expenses (editable).
        $items = [];
        foreach (Expense::query()->with('property:id,name')->get() as $e) {
            $items[] = (object) [
                'id'             => $e->id,
                'source'         => 'expense',
                'editable'       => true,
                'incurred_at'    => $e->incurred_at,
                'title'          => $e->title,
                'category'       => array_key_exists($e->category, Expense::CATEGORIES) ? $e->category : 'other',
                'category_label' => __(Expense::CATEGORIES[$e->category] ?? $e->category),
                'property_id'    => $e->property_id,
                'property_name'  => $e->property?->name,
                'paid_to'        => $e->paid_to,
                'description'    => $e->description,
                'amount'         => (float) $e->amount,
            ];
        }

        // 2. Operational task costs (read-only — edited in Housekeeping).
        //    Same date basis as the dashboard's monthly operating cost:
        //    cleaning by scheduled_at, laundry by pickup_at, maintenance by resolved_at.
        foreach (CleaningTask::query()->whereNotNull('cost')->with(['property:id,name', 'cleaner:id,name'])->get() as $t) {
            $items[] = $this->opItem('cleaning', $t->scheduled_at, __('Cleaning'), $t->property?->name, $t->cleaner?->name, (float) $t->cost);
        }
        foreach (LaundryTask::query()->whereNotNull('cost')->with(['property:id,name', 'vendor:id,name'])->get() as $t) {
            $items[] = $this->opItem('laundry', $t->pickup_at, __('Laundry'), $t->property?->name, $t->vendor?->name, (float) $t->cost);
        }
        foreach (MaintenanceTicket::query()->whereNotNull('cost')->whereNotNull('resolved_at')->with('property:id,name')->get() as $t) {
            $items[] = $this->opItem('maintenance', $t->resolved_at, $t->title ?: __('Maintenance'), $t->property?->name, null, (float) $t->cost);
        }

        // Newest first.
        usort($items, fn ($a, $b) => ($b->incurred_at?->getTimestamp() ?? 0) <=> ($a->incurred_at?->getTimestamp() ?? 0));
        $all = collect($items);

        // Per-month totals (newest first).
        $byMonth = [];
        foreach ($all as $e) {
            $k = $e->incurred_at?->format('Y-m');
            if (! $k) {
                continue;
            }
            $byMonth[$k] = ($byMonth[$k] ?? 0.0) + $e->amount;
        }
        krsort($byMonth);
        $months = [];
        foreach ($byMonth as $k => $sum) {
            $months[] = [
                'key'   => $k,
                'label' => Carbon::createFromFormat('Y-m', $k)->translatedFormat('F Y'),
                'total' => $sum,
            ];
        }

        // Category totals (all-time), for the summary breakdown + filter chips.
        $byCategory = [];
        foreach ($all as $e) {
            $byCategory[$e->category] = ($byCategory[$e->category] ?? 0.0) + $e->amount;
        }
        $byCategory = array_filter($byCategory, fn ($v) => $v > 0);
        arsort($byCategory);

        // Filters.
        $allCats = array_merge(array_keys(Expense::CATEGORIES), array_keys(self::OPERATIONAL_CATEGORIES));
        $month = (string) $request->query('month', '');
        $category = (string) $request->query('category', '');
        $rows = $all;
        if ($month !== '' && isset($byMonth[$month])) {
            $rows = $rows->filter(fn ($e) => $e->incurred_at?->format('Y-m') === $month);
        }
        if ($category !== '' && in_array($category, $allCats, true)) {
            $rows = $rows->filter(fn ($e) => $e->category === $category);
        }

        $thisMonthKey = Carbon::today()->format('Y-m');

        return view('tenant.expenses.index', [
            'expenses'         => $rows->values(),
            'properties'       => Property::query()->orderBy('name')->get(['id', 'name']),
            'months'           => $months,
            'byCategory'       => $byCategory,
            'categoryLabels'   => array_merge(Expense::CATEGORIES, self::OPERATIONAL_CATEGORIES),
            'grandTotal'       => (float) $all->sum('amount'),
            'thisMonthTotal'   => (float) ($byMonth[$thisMonthKey] ?? 0.0),
            'thisMonthLabel'   => Carbon::today()->translatedFormat('F Y'),
            'filteredTotal'    => (float) $rows->sum('amount'),
            'selectedMonth'    => ($month !== '' && isset($byMonth[$month])) ? $month : null,
            'selectedCategory' => ($category !== '' && in_array($category, $allCats, true)) ? $category : null,
        ]);
    }

    /** Normalize a cleaning/laundry/maintenance cost into a read-only ledger item. */
    private function opItem(string $category, ?Carbon $date, string $title, ?string $propertyName, ?string $paidTo, float $amount): object
    {
        return (object) [
            'id'             => null,
            'source'         => $category,
            'editable'       => false,
            'incurred_at'    => $date,
            'title'          => $title,
            'category'       => $category,
            'category_label' => __(self::OPERATIONAL_CATEGORIES[$category]),
            'property_id'    => null,
            'property_name'  => $propertyName,
            'paid_to'        => $paidTo,
            'description'    => null,
            'amount'         => $amount,
        ];
    }

    public function store(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $this->validateExpense($request);

        Expense::create(array_merge($validated, ['tenant_id' => $tenant->id]));

        return redirect()
            ->route('tenant.expenses.index')
            ->with('status', __('Expense added.'));
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::findOrFail($id);

        $expense->update($this->validateExpense($request));

        return redirect()
            ->route('tenant.expenses.index')
            ->with('status', __('Expense updated.'));
    }

    public function destroy(int $id)
    {
        Expense::findOrFail($id)->delete();

        return redirect()
            ->route('tenant.expenses.index')
            ->with('status', __('Expense removed.'));
    }

    /** @return array<string, mixed> */
    private function validateExpense(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'amount' => 'required|numeric|min:0|max:100000000',
            'incurred_at' => 'required|date',
            'property_id' => 'nullable|integer|exists:properties,id',
            'paid_to' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:2000',
        ]);

        return [
            'title' => $validated['title'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'incurred_at' => $validated['incurred_at'],
            'property_id' => $validated['property_id'] ?? null,
            'paid_to' => $validated['paid_to'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }
}
