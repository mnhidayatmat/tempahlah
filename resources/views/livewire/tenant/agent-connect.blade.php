@php
    $unlocked = $this->unlocked;
    $waConnected = $this->whatsappConnected;
    $providers = $this->availableProviders;
    $models = $this->modelsForProvider;
    $usage = $this->usageToday;
    $convos = $this->recentConversations;
    $usagePct = $usage['cap'] > 0 ? min(100, (int) round($usage['replies'] * 100 / $usage['cap'])) : 0;
@endphp

<div wire:poll.5s style="display:flex; flex-direction:column; gap: 20px;">

    {{-- Ships the .bs-spinner styles used by the wire:loading states below. --}}
    <x-busy-ui />

    <style>
        /* Auto-growing textareas so long training answers show in full without
           dragging the corner. field-sizing handles it natively (Chrome/Safari/
           Edge); the Alpine fallback below covers browsers without it. */
        .autogrow {
            field-sizing: content;
            min-height: 104px;
            max-height: 420px;
            overflow-y: auto;
            line-height: 1.5;
            resize: vertical;
        }
        .qa-card {
            border: 1px solid var(--line);
            border-radius: var(--r-md);
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: var(--bg-elev);
        }
        .qa-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 999px;
            background: var(--bg-sunk); color: var(--ink-2);
            font-size: 11px; font-weight: 700; flex-shrink: 0;
        }
        .qa-field-label { font-size: 11.5px; color: var(--ink-3); font-weight: 600; }
    </style>

    {{-- Grow any .autogrow textarea to fit its content. Runs on load, on input,
         and after every Livewire DOM update (covers Refresh / Add). No-op where
         the browser already supports CSS field-sizing. --}}
    <script>
        (function () {
            if (window.__qaAutogrow) return;
            window.__qaAutogrow = true;
            var native = window.CSS && CSS.supports && CSS.supports('field-sizing', 'content');
            function fit(el) {
                if (native) return;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 420) + 'px';
            }
            window.__qaFitAll = function () {
                document.querySelectorAll('textarea.autogrow').forEach(fit);
            };
            document.addEventListener('input', function (e) {
                if (e.target.matches && e.target.matches('textarea.autogrow')) fit(e.target);
            });
            document.addEventListener('DOMContentLoaded', window.__qaFitAll);
            document.addEventListener('livewire:initialized', function () {
                window.__qaFitAll();
                if (window.Livewire && Livewire.hook) {
                    Livewire.hook('morph.updated', function () { setTimeout(window.__qaFitAll, 0); });
                }
            });
            setTimeout(window.__qaFitAll, 0);
        })();
    </script>

    {{-- Pro gate ----------------------------------------------------- --}}
    @if (! $unlocked)
        <x-pro-lock
            feature="ai_agent"
            :title="__('AI Agent')"
            :reason="__('Upgrade to Pro to let an AI assistant reply to WhatsApp enquiries on your behalf — availability checks, photos, location, and price quotes from your live data.')"
            :cta="__('Unlock — RM49/mo')" />
    @else

    {{-- Flash -------------------------------------------------------- --}}
    @if ($flash)
        <div class="hauz-card"
             style="padding: 10px 14px; font-size: 13px;
                    border-color: var({{ $flashKind === 'err' ? '--err' : '--ok' }});
                    background: var({{ $flashKind === 'err' ? '--err-tint' : '--ok-tint' }});
                    color: var({{ $flashKind === 'err' ? '--err' : '--ok' }});">
            {{ $flash }}
        </div>
    @endif

    {{-- WhatsApp prerequisite --------------------------------------- --}}
    @unless ($waConnected)
        <div class="hauz-card"
             style="padding: 14px 18px; border-color: var(--warn);
                    background: var(--warn-tint); color: var(--ink-2); font-size: 13px;">
            <strong style="color: var(--warn);">⚠️ {{ __('WhatsApp not connected') }}</strong>
            <div style="margin-top: 4px;">
                {{ __('The AI agent replies through your WhatsApp session. Please connect WhatsApp first.') }}
            </div>
            <a href="{{ route('tenant.integrations.show', 'whatsapp') }}"
               class="btn btn-sm" style="margin-top: 10px;">
                {{ __('Connect WhatsApp') }}
            </a>
        </div>
    @endunless

    {{-- ── Master switch + status ────────────────────────────────── --}}
    <div class="hauz-card" style="padding: 18px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 16px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 0;">
                <div style="display:flex; align-items:center; gap: 8px; margin-bottom: 4px;">
                    <h3 class="display-3" style="margin: 0;">{{ __('AI agent') }}</h3>
                    @if ($enabled && $waConnected)
                        <x-pill variant="ok" :dot="true">{{ __('Active') }}</x-pill>
                    @elseif ($enabled)
                        <x-pill variant="warn">{{ __('Waiting on WhatsApp') }}</x-pill>
                    @else
                        <x-pill>{{ __('Off') }}</x-pill>
                    @endif
                </div>
                <p style="margin: 0; color: var(--ink-3); font-size: 13px; max-width: 540px;">
                    {{ __('Auto-reply to guest enquiries using AI. Answers come from your real availability, prices, photos and location data.') }}
                </p>
            </div>
            <label style="display:flex; align-items:center; gap: 10px; cursor: pointer;">
                <input type="checkbox" wire:model.live="enabled" style="width: 18px; height: 18px;">
                <span style="font-weight: 600; font-size: 14px;">{{ __('Enable AI agent') }}</span>
            </label>
        </div>
    </div>

    {{-- ── Usage meter ──────────────────────────────────────────── --}}
    <div class="hauz-card" style="padding: 16px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:baseline; gap: 12px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Today (resets at midnight MYT)') }}</div>
                <div style="font-family: var(--font-mono); font-size: 22px; font-weight: 600; color: var(--ink); margin-top: 4px;">
                    {{ $usage['replies'] }} <span style="color: var(--ink-3); font-size: 14px;">/ {{ $usage['cap'] }} {{ __('replies') }}</span>
                </div>
            </div>
            <div style="font-size: 12px; color: var(--ink-3);">
                <span style="font-family: var(--font-mono);">{{ $usage['inbounds'] }}</span> {{ __('inbound') }} ·
                <span style="font-family: var(--font-mono);">{{ $usage['tools'] }}</span> {{ __('tool calls') }}
            </div>
        </div>
        <div style="margin-top: 10px; height: 6px; background: var(--bg-sunk); border-radius: 999px; overflow: hidden;">
            <div style="width: {{ $usagePct }}%; height: 100%; background: var(--primary);"></div>
        </div>
    </div>

    {{-- ── Settings form ────────────────────────────────────────── --}}
    <form wire:submit.prevent="save" style="display:flex; flex-direction:column; gap: 16px;">
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Model') }}</h4>

            @if (empty($providers))
                <div style="padding: 10px 12px; background: var(--err-tint); color: var(--err); border-radius: var(--r-md); font-size: 12.5px;">
                    {{ __('No AI providers configured on this server. Contact your administrator.') }}
                </div>
            @else
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <label style="display:flex; flex-direction:column; gap: 4px;">
                        <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Provider') }}</span>
                        <select class="input" wire:model.live="llmProvider">
                            @foreach ($providers as $key => $info)
                                <option value="{{ $key }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="display:flex; flex-direction:column; gap: 4px;">
                        <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Model') }}</span>
                        <select class="input" wire:model="llmModel">
                            @foreach ($models as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            @endif
        </div>

        {{-- Persona + language --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Voice') }}</h4>

            <div>
                <div style="font-size: 12.5px; color: var(--ink-3); margin-bottom: 6px;">{{ __('Persona') }}</div>
                <div style="display:flex; gap: 6px; flex-wrap: wrap;">
                    @foreach (['friendly' => '🤗 ' . __('Friendly'), 'formal' => '🎩 ' . __('Formal'), 'concise' => '⚡ ' . __('Concise')] as $key => $label)
                        <button type="button" wire:click="$set('persona', '{{ $key }}')"
                                class="btn btn-sm"
                                style="{{ $persona === $key ? 'background: var(--primary); color: var(--ink-on-primary);' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <label style="display:flex; flex-direction:column; gap: 4px;">
                <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Reply language') }}</span>
                <select class="input" wire:model="replyLanguages" style="max-width: 280px;">
                    <option value="auto">{{ __('Auto-detect from each message') }}</option>
                    <option value="ms">{{ __('Always Bahasa Malaysia') }}</option>
                    <option value="en">{{ __('Always English') }}</option>
                </select>
            </label>
        </div>

        {{-- Greeting + signature --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Greeting & signature') }}</h4>
            <p style="margin: 0; font-size: 12px; color: var(--ink-3);">
                {{ __('Tokens: ') }}<code>@{{tenant_name}}</code> · <code>@{{property_name}}</code>
            </p>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label style="display:flex; flex-direction:column; gap: 4px;">
                    <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Greeting — Bahasa Malaysia') }}</span>
                    <textarea class="input autogrow" style="width:100%; min-height: 80px;" wire:model="greetingBm">{{ $greetingBm }}</textarea>
                </label>
                <label style="display:flex; flex-direction:column; gap: 4px;">
                    <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Greeting — English') }}</span>
                    <textarea class="input autogrow" style="width:100%; min-height: 80px;" wire:model="greetingEn">{{ $greetingEn }}</textarea>
                </label>
            </div>
            <label style="display:flex; flex-direction:column; gap: 4px;">
                <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Signature (appended to every reply)') }}</span>
                <input class="input" type="text" wire:model="signature" placeholder="— Aisha & team">
            </label>
        </div>

        {{-- Business hours --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Business hours') }}</h4>
            <label style="display:flex; align-items:center; gap: 8px;">
                <input type="checkbox" wire:model.live="useBusinessHours">
                <span style="font-size: 13px;">{{ __('Only reply during business hours (otherwise auto-reply with the out-of-hours message)') }}</span>
            </label>
            @if ($useBusinessHours)
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; max-width: 360px;">
                    <label style="display:flex; flex-direction:column; gap: 4px;">
                        <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Open') }}</span>
                        <input class="input" type="time" wire:model="businessHoursStart">
                    </label>
                    <label style="display:flex; flex-direction:column; gap: 4px;">
                        <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Close') }}</span>
                        <input class="input" type="time" wire:model="businessHoursEnd">
                    </label>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <label style="display:flex; flex-direction:column; gap: 4px;">
                        <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Out-of-hours — BM') }}</span>
                        <textarea class="input autogrow" style="width:100%; min-height: 80px;" wire:model="outOfHoursBm">{{ $outOfHoursBm }}</textarea>
                    </label>
                    <label style="display:flex; flex-direction:column; gap: 4px;">
                        <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Out-of-hours — EN') }}</span>
                        <textarea class="input autogrow" style="width:100%; min-height: 80px;" wire:model="outOfHoursEn">{{ $outOfHoursEn }}</textarea>
                    </label>
                </div>
            @endif
        </div>

        {{-- Escalation + handoff --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Escalation') }}</h4>
            <label style="display:flex; flex-direction:column; gap: 4px;">
                <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Trigger keywords (comma-separated)') }}</span>
                <input class="input" type="text" wire:model="escalationKeywords"
                       placeholder="manager, owner, complaint, refund, tuan rumah, aduan">
            </label>
            <label style="display:flex; flex-direction:column; gap: 4px;">
                <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Notify me at (WhatsApp number)') }}</span>
                <input class="input" type="tel" inputmode="tel" wire:model="handoffPhone" placeholder="+60123456789" data-phone-input style="max-width: 280px;">
                <span style="font-size: 11.5px; color: var(--ink-3);">{{ __('Optional. We will ping you when the AI escalates.') }}</span>
            </label>
        </div>

        {{-- Knowledge base --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Custom knowledge') }}</h4>
            <p style="margin: 0; font-size: 12px; color: var(--ink-3); line-height: 1.5;">
                {{ __('Free-text notes the AI will use verbatim — e.g. parking instructions, halal certification, surau location, late check-in policy. Keep it under 4,000 characters.') }}
            </p>
            <textarea class="input autogrow" style="width:100%; min-height: 140px;" wire:model="customKnowledge"
                      maxlength="4000">{{ $customKnowledge }}</textarea>
        </div>

        {{-- Learned from conversations ---------------------------------- --}}
        @php $learned = $this->learnedSuggestions; @endphp
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px; flex-wrap: wrap;">
                <div style="flex:1; min-width: 0;">
                    <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Learned from your conversations') }}</h4>
                    <p style="margin: 0; font-size: 12px; color: var(--ink-3); line-height: 1.5; max-width: 620px;">
                        {{ __('Each week the agent reads your recent WhatsApp chats and suggests answers to add — questions guests keep asking, and ones it couldn\'t answer. Nothing is used until you approve it here.') }}
                    </p>
                </div>
                <button type="button" class="btn btn-sm" wire:click="scanNow"
                        wire:loading.attr="disabled" wire:target="scanNow">
                    <span wire:loading.remove wire:target="scanNow">↻ {{ __('Scan chats now') }}</span>
                    <span wire:loading wire:target="scanNow">
                        <span class="bs-spinner bs-spinner--inline" aria-hidden="true"></span>{{ __('Scanning…') }}
                    </span>
                </button>
            </div>

            @if ($learned->isEmpty())
                <div style="padding: 16px; text-align:center; color: var(--ink-3); font-size: 13px; background: var(--bg-sunk); border-radius: var(--r-md);">
                    {{ __('No suggestions right now. As guests chat with your agent, useful ones will show up here.') }}
                </div>
            @else
                <div style="display:flex; flex-direction:column; gap: 12px;">
                    @foreach ($learned as $s)
                        <div wire:key="learned-{{ $s->id }}" style="border: .5px solid var(--line); border-radius: var(--r-md); padding: 12px 14px; display:flex; flex-direction:column; gap: 8px;">
                            <div style="display:flex; align-items:center; gap: 8px; flex-wrap: wrap;">
                                @if ($s->kind === \App\Models\AgentLearnedFaq::KIND_GAP)
                                    <span class="pill pill-warn" style="height: 20px; font-size: 11px;">{{ __('Needs your answer') }}</span>
                                @else
                                    <span class="pill pill-ok" style="height: 20px; font-size: 11px;">{{ __('Suggested answer') }}</span>
                                @endif
                                <strong style="font-size: 13.5px; color: var(--ink);">{{ $s->question }}</strong>
                            </div>

                            @if (!empty($s->example_phrases))
                                <div style="font-size: 11.5px; color: var(--ink-3);">
                                    {{ __('Guest asked:') }} “{{ $s->example_phrases[0] }}”
                                </div>
                            @endif

                            <textarea class="input autogrow" style="width:100%; min-height: 72px; font-size: 13px;"
                                      wire:model="learnedDraft.{{ $s->id }}"
                                      placeholder="{{ $s->kind === \App\Models\AgentLearnedFaq::KIND_GAP ? __('Type the answer you\'d like the agent to give…') : __('Edit the answer if needed…') }}"
                                      maxlength="2000"></textarea>

                            <div style="display:flex; gap: 8px; justify-content:flex-end;">
                                <button type="button" class="btn btn-sm" wire:click="dismissLearned({{ $s->id }})"
                                        style="color: var(--ink-3);">{{ __('Dismiss') }}</button>
                                <button type="button" class="btn btn-sm btn-primary" wire:click="approveLearned({{ $s->id }})"
                                        wire:loading.attr="disabled" wire:target="approveLearned({{ $s->id }})">
                                    {{ __('Approve & use') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Training Q&A ------------------------------------------------ --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap: 12px; flex-wrap: wrap;">
                <div style="flex:1; min-width: 0;">
                    <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Training Q&A') }}</h4>
                    <p style="margin: 0; font-size: 12px; color: var(--ink-3); line-height: 1.5; max-width: 620px;">
                        {{ __('These are the exact answers the AI gives to common guest questions. We auto-generated a starter set from your homestay info — refine any answer, add your own, or remove what you don\'t need. Edited or hand-written answers are marked "Edited" and are kept when you refresh the defaults.') }}
                    </p>
                </div>
                <button type="button" class="btn btn-sm" wire:click="regenerateQa"
                        wire:loading.attr="disabled" wire:target="regenerateQa"
                        wire:confirm="{{ __('Refresh the default answers from your latest homestay info? Your edited questions will be kept.') }}">
                    <span wire:loading.remove wire:target="regenerateQa">↻ {{ __('Refresh from my info') }}</span>
                    <span wire:loading wire:target="regenerateQa">
                        <span class="bs-spinner bs-spinner--inline" aria-hidden="true"></span>{{ __('Refreshing…') }}
                    </span>
                </button>
            </div>

            @if (empty($trainingQa))
                <div style="padding: 16px; text-align:center; color: var(--ink-3); font-size: 13px; background: var(--bg-sunk); border-radius: var(--r-md);">
                    {{ __('No trained answers yet. Add one below, or refresh from your info.') }}
                </div>
            @else
                <div style="display:flex; flex-direction:column; gap: 12px;">
                    @foreach ($trainingQa as $i => $pair)
                        <div wire:key="qa-{{ $i }}" class="qa-card">
                            <div style="display:flex; align-items:center; gap: 8px;">
                                <span class="qa-num">{{ $i + 1 }}</span>
                                @if (($pair['source'] ?? 'auto') === 'custom')
                                    <x-pill variant="info">{{ __('Edited') }}</x-pill>
                                @else
                                    <x-pill>{{ __('Auto') }}</x-pill>
                                @endif
                                <button type="button" class="btn btn-sm"
                                        wire:click="removeQa({{ $i }})"
                                        style="color: var(--err); padding: 2px 8px; margin-left: auto;"
                                        title="{{ __('Remove') }}">✕ {{ __('Remove') }}</button>
                            </div>
                            <label style="display:flex; flex-direction:column; gap: 4px;">
                                <span class="qa-field-label">{{ __('Question guests ask') }}</span>
                                <input class="input" type="text" style="width:100%;" wire:model.blur="trainingQa.{{ $i }}.q"
                                       maxlength="400" placeholder="{{ __('e.g. What time is check-in?') }}">
                            </label>
                            <label style="display:flex; flex-direction:column; gap: 4px;">
                                <span class="qa-field-label">{{ __('Answer the AI should give') }}</span>
                                <textarea class="input autogrow" style="width:100%;" wire:model.blur="trainingQa.{{ $i }}.a"
                                          maxlength="2000"
                                          placeholder="{{ __('Type the full answer here — it grows as you write.') }}">{{ $pair['a'] ?? '' }}</textarea>
                                <span style="font-size: 10.5px; color: var(--ink-4); text-align: right;">
                                    {{ mb_strlen((string) ($pair['a'] ?? '')) }} / 2000
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <button type="button" class="btn btn-sm" wire:click="addQa">+ {{ __('Add question') }}</button>
                <span style="font-size: 11.5px; color: var(--ink-3); margin-left: 8px;">
                    {{ count($trainingQa) }} / {{ \App\Services\Agent\TrainingQaGenerator::MAX_PAIRS }}
                </span>
            </div>
        </div>

        {{-- Limits --}}
        <div class="hauz-card" style="padding: 18px 20px; display:flex; flex-direction:column; gap: 14px;">
            <h4 style="margin: 0 0 4px; font-size: 15px;">{{ __('Safety') }}</h4>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label style="display:flex; flex-direction:column; gap: 4px;">
                    <span style="font-size: 12.5px; color: var(--ink-3);">{{ __('Daily reply cap') }}</span>
                    <input class="input" type="number" min="1" max="{{ config('agent.platform_max_cap') }}" wire:model="dailyCap">
                </label>
                <label style="display:flex; align-items:center; gap: 8px; margin-top: 22px;">
                    <input type="checkbox" wire:model="sendPhotosEnabled">
                    <span style="font-size: 13px;">{{ __('Allow sending photo images on request') }}</span>
                </label>
            </div>
        </div>

        <div style="display:flex; gap: 10px; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Save settings') }}</span>
                <span wire:loading wire:target="save">
                    <span class="bs-spinner bs-spinner--inline" aria-hidden="true"></span>{{ __('Saving…') }}
                </span>
            </button>
        </div>
    </form>

    {{-- ── Test playground ─────────────────────────────────────── --}}
    <div class="hauz-card" style="padding: 18px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap: 12px; flex-wrap: wrap;">
            <h4 style="margin: 0; font-size: 15px;">{{ __('Test playground') }}</h4>
            <span style="font-size: 11.5px; color: var(--ink-3);">{{ __('Simulates an inbound guest message. No WhatsApp send.') }}</span>
        </div>
        <div style="display:flex; gap: 8px; margin-top: 10px;">
            <input class="input" type="text" wire:model="testMessage"
                   placeholder="{{ __('e.g. Is your homestay available 25–28 June for 4 pax?') }}"
                   style="flex: 1;">
            {{-- wire:target pins the loading state to this action, so the root's
                 wire:poll.5s and the wire:model.live fields don't trip it. --}}
            <button type="button" class="btn btn-primary" wire:click="runTest"
                    wire:loading.attr="disabled" wire:target="runTest"
                    @disabled(empty($providers))>
                <span wire:loading.remove wire:target="runTest">{{ __('Test') }}</span>
                <span wire:loading wire:target="runTest">
                    <span class="bs-spinner bs-spinner--inline" aria-hidden="true"></span>{{ __('Thinking…') }}
                </span>
            </button>
        </div>
        @if ($testReply)
            <div style="margin-top: 12px; padding: 12px 14px; background: var(--bg-sunk); border-radius: var(--r-md); font-size: 13px; line-height: 1.55; white-space: pre-wrap;">
                {{ $testReply }}
            </div>
        @endif
    </div>

    {{-- ── Live conversations ─────────────────────────────────── --}}
    <div class="hauz-card" style="padding: 18px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap: 12px; margin-bottom: 12px;">
            <h4 style="margin: 0; font-size: 15px;">{{ __('Live conversations') }}</h4>
            <span style="font-size: 11.5px; color: var(--ink-3);">{{ __('Most recent 8') }}</span>
        </div>

        @if ($convos->isEmpty())
            <div style="padding: 18px; text-align: center; color: var(--ink-3); font-size: 13px;">
                {{ __('No conversations yet. When a guest WhatsApps you, the agent will show up here.') }}
            </div>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; color: var(--ink-3); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 6px 8px; border-bottom: 1px solid var(--line);">{{ __('Guest') }}</th>
                        <th style="padding: 6px 8px; border-bottom: 1px solid var(--line);">{{ __('Status') }}</th>
                        <th style="padding: 6px 8px; border-bottom: 1px solid var(--line);">{{ __('Last inbound') }}</th>
                        <th style="padding: 6px 8px; border-bottom: 1px solid var(--line); text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($convos as $c)
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid var(--line); font-family: var(--font-mono);">
                                {{ $c->guest_phone }}
                                @if ($c->guest_name)
                                    <div style="font-family: var(--font-body); color: var(--ink-3); font-size: 11.5px;">{{ $c->guest_name }}</div>
                                @endif
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid var(--line);">
                                @if ($c->status === \App\Models\AgentConversation::STATUS_ACTIVE)
                                    <x-pill variant="ok" :dot="true">{{ __('Active') }}</x-pill>
                                @elseif ($c->status === \App\Models\AgentConversation::STATUS_ESCALATED)
                                    <x-pill variant="warn">{{ __('Escalated') }}</x-pill>
                                @else
                                    <x-pill>{{ $c->status }}</x-pill>
                                @endif
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid var(--line); color: var(--ink-3);">
                                {{ optional($c->last_inbound_at)->diffForHumans() ?? '—' }}
                            </td>
                            <td style="padding: 8px; border-bottom: 1px solid var(--line); text-align: right;">
                                @if ($c->status === \App\Models\AgentConversation::STATUS_ACTIVE)
                                    <button class="btn btn-sm" wire:click="takeOver({{ $c->id }})"
                                            wire:confirm="{{ __('Mute the AI on this conversation?') }}">
                                        {{ __('Take over') }}
                                    </button>
                                @else
                                    <button class="btn btn-sm" wire:click="reactivate({{ $c->id }})">
                                        {{ __('Re-activate AI') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif
</div>
