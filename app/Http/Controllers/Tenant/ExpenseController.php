<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Expense ledger. Lists the tenant's expenses (optionally filtered by
     * ?month=YYYY-MM and ?category=), with summary cards + a per-month
     * breakdown so a host can see spend at a glance. All queries are
     * tenant-scoped via the BelongsToTenant global scope.
     */
    public function index(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        // All expenses (for summaries + the month/category facets). Small table
        // per tenant, so an in-PHP roll-up is fine and DB-agnostic.
        $all = Expense::query()
            ->with('property:id,name')
            ->orderByDesc('incurred_at')
            ->orderByDesc('id')
            ->get();

        // Per-month totals (newest first), plus a running all-time total.
        $byMonth = [];
        foreach ($all as $e) {
            $k = $e->incurred_at?->format('Y-m');
            if (! $k) {
                continue;
            }
            $byMonth[$k] ??= 0.0;
            $byMonth[$k] += (float) $e->amount;
        }
        krsort($byMonth);
        $months = [];
        foreach ($byMonth as $k => $sum) {
            $months[] = [
                'key' => $k,
                'label' => Carbon::createFromFormat('Y-m', $k)->translatedFormat('F Y'),
                'total' => $sum,
            ];
        }

        // Category totals (all-time), for the summary breakdown.
        $byCategory = [];
        foreach (array_keys(Expense::CATEGORIES) as $cat) {
            $byCategory[$cat] = 0.0;
        }
        foreach ($all as $e) {
            $cat = array_key_exists($e->category, $byCategory) ? $e->category : 'other';
            $byCategory[$cat] += (float) $e->amount;
        }
        $byCategory = array_filter($byCategory, fn ($v) => $v > 0);
        arsort($byCategory);

        // Filters.
        $month = (string) $request->query('month', '');
        $category = (string) $request->query('category', '');
        $rows = $all;
        if ($month !== '' && isset($byMonth[$month])) {
            $rows = $rows->filter(fn ($e) => $e->incurred_at?->format('Y-m') === $month);
        }
        if ($category !== '' && array_key_exists($category, Expense::CATEGORIES)) {
            $rows = $rows->filter(fn ($e) => $e->category === $category);
        }

        $thisMonthKey = Carbon::today()->format('Y-m');

        return view('tenant.expenses.index', [
            'expenses' => $rows->values(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'months' => $months,
            'byCategory' => $byCategory,
            'grandTotal' => (float) $all->sum('amount'),
            'thisMonthTotal' => (float) ($byMonth[$thisMonthKey] ?? 0.0),
            'thisMonthLabel' => Carbon::today()->translatedFormat('F Y'),
            'filteredTotal' => (float) $rows->sum('amount'),
            'selectedMonth' => ($month !== '' && isset($byMonth[$month])) ? $month : null,
            'selectedCategory' => ($category !== '' && array_key_exists($category, Expense::CATEGORIES)) ? $category : null,
        ]);
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
