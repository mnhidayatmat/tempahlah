@php
    use App\Models\Refund;
    $refunds = $booking->refunds ?? collect();

    $statusMeta = [
        Refund::STATUS_PENDING    => ['variant' => 'warn', 'label' => __('Pending')],
        Refund::STATUS_PROCESSING => ['variant' => 'info', 'label' => __('Processing')],
        Refund::STATUS_COMPLETED  => ['variant' => 'ok',   'label' => __('Completed')],
        Refund::STATUS_FAILED     => ['variant' => 'err',  'label' => __('Failed')],
        Refund::STATUS_CANCELLED  => ['variant' => 'ink-3','label' => __('Cancelled')],
    ];
    $methodLabels = [
        Refund::METHOD_DUITNOW              => __('DuitNow Transfer'),
        Refund::METHOD_BANK_TRANSFER        => __('Bank Transfer'),
        Refund::METHOD_EWALLET              => __('E-wallet'),
        Refund::METHOD_CASH                 => __('Cash'),
        Refund::METHOD_TOYYIBPAY_DASHBOARD  => __('Toyyibpay Dashboard'),
    ];
    $reasonLabels = [
        Refund::REASON_CHECKOUT_COMPLETE => __('Checkout — return deposit'),
        Refund::REASON_CANCELLATION      => __('Booking cancellation'),
        Refund::REASON_DAMAGE_DEDUCTION  => __('Damage deduction'),
        Refund::REASON_GOODWILL          => __('Goodwill'),
        Refund::REASON_OTHER             => __('Other'),
    ];
@endphp

<div class="hauz-card" style="padding: 18px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
        <div class="kicker">{{ __('Refunds') }}</div>
        <div style="font-size: 11px; color: var(--ink-3);">
            {{ __('Toyyibpay has no refund API — transfer manually + record below.') }}
        </div>
    </div>

    @if ($refunds->isEmpty())
        <div style="padding: 14px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 13px; color: var(--ink-3);">
            {{ __('No refunds yet. A refund is auto-created when you mark the guest as checked out.') }}
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap: 14px;">
            @foreach ($refunds as $r)
                @php
                    $meta = $statusMeta[$r->status] ?? ['variant' => 'ink-3', 'label' => ucfirst($r->status)];
                    $isOpen = $r->isOpen();
                @endphp
                <div x-data="{ edit: false }"
                     style="border: 1px solid var(--line); border-radius: var(--r-lg); overflow: hidden;
                            background: {{ $r->status === Refund::STATUS_COMPLETED ? 'var(--ok-tint)' : ($r->status === Refund::STATUS_FAILED ? 'var(--err-tint)' : 'var(--bg-elev)') }};">

                    {{-- Header row --}}
                    <div style="padding: 12px 14px; display:flex; align-items:center; justify-content:space-between; gap: 10px; flex-wrap: wrap;">
                        <div style="display:flex; align-items:center; gap: 10px; flex-wrap: wrap;">
                            <x-pill :variant="$meta['variant']" :dot="$isOpen">{{ $meta['label'] }}</x-pill>
                            <div>
                                <div class="mono" style="font-size: 16px; font-weight: 700;">RM {{ number_format((float) $r->amount, 2) }}</div>
                                <div style="font-size: 11px; color: var(--ink-3);">
                                    {{ $reasonLabels[$r->reason] ?? ucfirst($r->reason) }} ·
                                    {{ $r->requested_at?->format('d M Y · H:i') }}
                                </div>
                            </div>
                        </div>
                        @if ($isOpen)
                            <button type="button"
                                    class="btn btn-sm"
                                    @click="edit = !edit">
                                <span x-show="!edit">{{ __('Update') }}</span>
                                <span x-show="edit" x-cloak>{{ __('Close') }}</span>
                            </button>
                        @endif
                    </div>

                    {{-- Read-only summary line for completed/failed --}}
                    @if (! $isOpen)
                        <div style="padding: 8px 14px 12px; border-top: 1px solid var(--line); font-size: 12.5px; color: var(--ink-2); display:flex; flex-direction:column; gap: 4px;">
                            @if ($r->method)
                                <div><strong>{{ __('Method') }}:</strong> {{ $methodLabels[$r->method] ?? ucfirst($r->method) }}</div>
                            @endif
                            @if ($r->external_reference)
                                <div><strong>{{ __('Reference') }}:</strong> <span class="mono">{{ $r->external_reference }}</span></div>
                            @endif
                            @if ($r->processed_at)
                                <div><strong>{{ __('Processed') }}:</strong> {{ $r->processed_at->format('d M Y · H:i') }}
                                    @if ($r->processedBy) — {{ $r->processedBy->name }} @endif
                                </div>
                            @endif
                            @if ($r->failure_reason)
                                <div style="color: var(--err);"><strong>{{ __('Failure reason') }}:</strong> {{ $r->failure_reason }}</div>
                            @endif
                            @if ($r->notes)
                                <div style="color: var(--ink-3);">{{ $r->notes }}</div>
                            @endif
                        </div>
                    @endif

                    {{-- Edit form (only when refund is still open) --}}
                    @if ($isOpen)
                        <form method="POST"
                              action="{{ route('tenant.refunds.update', $r->id) }}"
                              x-show="edit"
                              x-cloak
                              x-transition
                              style="padding: 14px; border-top: 1px solid var(--line); background: var(--bg-sunk);">
                            @csrf
                            @method('PATCH')

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                                <label style="display:flex; flex-direction:column; gap: 4px;">
                                    <span style="font-size: 11px; color: var(--ink-3); font-weight: 600;">{{ __('Amount (RM)') }}</span>
                                    <input type="number" name="amount" step="0.01" min="0" max="999999.99"
                                           value="{{ old('amount', (float) $r->amount) }}"
                                           class="input mono" required>
                                </label>

                                <label style="display:flex; flex-direction:column; gap: 4px;">
                                    <span style="font-size: 11px; color: var(--ink-3); font-weight: 600;">{{ __('Method') }}</span>
                                    <select name="method" class="input">
                                        <option value="">— {{ __('select') }} —</option>
                                        @foreach ($methodLabels as $val => $lbl)
                                            <option value="{{ $val }}" @selected(old('method', $r->method) === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <label style="display:flex; flex-direction:column; gap: 4px; margin-bottom: 10px;">
                                <span style="font-size: 11px; color: var(--ink-3); font-weight: 600;">{{ __('Bank / DuitNow reference') }}</span>
                                <input type="text" name="external_reference" maxlength="120"
                                       value="{{ old('external_reference', $r->external_reference) }}"
                                       class="input mono"
                                       placeholder="{{ __('e.g. FPX2406021234567') }}">
                            </label>

                            <label style="display:flex; flex-direction:column; gap: 4px; margin-bottom: 14px;">
                                <span style="font-size: 11px; color: var(--ink-3); font-weight: 600;">{{ __('Notes (private)') }}</span>
                                <textarea name="notes" rows="2" maxlength="1000" class="input"
                                          placeholder="{{ __('Anything you want to remember about this refund…') }}">{{ old('notes', $r->notes) }}</textarea>
                            </label>

                            <div style="display:flex; gap: 8px; flex-wrap: wrap; align-items:center;">
                                @if ($r->status === Refund::STATUS_PENDING)
                                    <button type="submit" name="status" value="{{ Refund::STATUS_PROCESSING }}"
                                            class="btn btn-sm">
                                        {{ __('Mark as processing') }}
                                    </button>
                                @endif
                                <button type="submit" name="status" value="{{ Refund::STATUS_COMPLETED }}"
                                        class="btn btn-sm btn-primary"
                                        onclick="return confirm('{{ addslashes(__('Confirm you have sent RM :amt to the guest? This stamps the refund as completed.', ['amt' => number_format((float) $r->amount, 2)])) }}');">
                                    {{ __('Mark as completed') }}
                                </button>
                                <button type="submit" name="status" value="{{ Refund::STATUS_FAILED }}"
                                        class="btn btn-sm"
                                        style="color: var(--err); border-color: color-mix(in srgb, var(--err) 35%, transparent);"
                                        onclick="return confirm('{{ addslashes(__('Mark this refund as failed? The host can retry by creating a new refund.')) }}');">
                                    {{ __('Mark as failed') }}
                                </button>
                                <button type="submit" name="status" value="{{ Refund::STATUS_CANCELLED }}"
                                        class="btn btn-sm"
                                        style="color: var(--ink-3);"
                                        onclick="return confirm('{{ addslashes(__('Cancel this refund (keep the deposit)? Use for damage deductions or no-shows.')) }}');">
                                    {{ __('Cancel refund') }}
                                </button>
                                <button type="submit" name="status" value="{{ $r->status }}"
                                        class="btn btn-sm" style="margin-left:auto;">
                                    {{ __('Save changes') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Manual ad-hoc refund button — surfaces below the list. --}}
    <details style="margin-top: 14px;">
        <summary style="cursor: pointer; font-size: 12px; color: var(--ink-3); font-weight: 600;">
            + {{ __('Add ad-hoc refund') }}
        </summary>
        <form method="POST" action="{{ route('tenant.refunds.store', $booking->id) }}"
              style="margin-top: 10px; padding: 12px; background: var(--bg-sunk); border-radius: var(--r-md); display:flex; flex-direction:column; gap: 10px;">
            @csrf
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <label style="display:flex; flex-direction:column; gap: 4px;">
                    <span style="font-size: 11px; color: var(--ink-3); font-weight: 600;">{{ __('Amount (RM)') }}</span>
                    <input type="number" name="amount" step="0.01" min="0" max="999999.99" required class="input mono">
                </label>
                <label style="display:flex; flex-direction:column; gap: 4px;">
                    <span style="font-size: 11px; color: var(--ink-3); font-weight: 600;">{{ __('Reason') }}</span>
                    <select name="reason" class="input" required>
                        @foreach ($reasonLabels as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <textarea name="notes" rows="2" maxlength="1000" class="input"
                      placeholder="{{ __('Notes (optional)') }}"></textarea>
            <button type="submit" class="btn btn-sm btn-primary" style="align-self: flex-start;">
                {{ __('Create refund') }}
            </button>
        </form>
    </details>
</div>
