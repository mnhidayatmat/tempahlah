<x-app-layout :title="__('Directory')">
    <div style="display:flex; flex-direction:column; gap: 20px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-end; justify-content:space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <div class="kicker">{{ __('Operations') }}</div>
                <div class="display-2" style="margin-top: 4px;">{{ __('Directory') }}</div>
                <div style="margin-top: 6px; color: var(--ink-3); font-size: 14px;">
                    {{ __('Your cleaners, laundry vendors and maintenance people — in one place.') }}
                </div>
            </div>
            <a href="{{ route('tenant.housekeeping.index') }}" class="btn btn-sm">{{ __('Back to Housekeeping') }}</a>
        </div>

        @if (session('status'))
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--ok); background: var(--ok-tint); color: var(--ok); font-size: 13px;">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="hauz-card" style="padding: 12px 16px; border-color: var(--err); background: var(--err-tint); color: var(--err); font-size: 13px;">
                <ul style="margin:0; padding-left: 18px;">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Tabs. "Laundry vendors" + counts overflow a 360px phone, so the strip
             scrolls itself rather than widening the page. --}}
        <style>
            .dir-tabs{ overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
            .dir-tabs::-webkit-scrollbar{ display:none; }
            .dir-tabs > a{ flex:none; white-space:nowrap; }
        </style>
        <div class="dir-tabs" style="display:flex; gap: 2px; border-bottom: .5px solid var(--line);">
            @foreach ([
                ['key' => 'cleaners',    'label' => __('Cleaners'),        'count' => $cleaners->count()],
                ['key' => 'vendors',     'label' => __('Laundry vendors'), 'count' => $vendors->count()],
                ['key' => 'maintenance', 'label' => __('Maintenance'),     'count' => $maintenancePersons->count()],
            ] as $t)
                @php $active = $tab === $t['key']; @endphp
                <a href="{{ route('tenant.directory.index', ['tab' => $t['key']]) }}" style="
                    padding: 10px 16px; text-decoration: none; font-size: 13px; font-weight: 500;
                    color: {{ $active ? 'var(--ink)' : 'var(--ink-3)' }};
                    border-bottom: 2px solid {{ $active ? 'var(--primary)' : 'transparent' }};
                    margin-bottom: -1px; display:inline-flex; align-items:center; gap: 6px;">
                    {{ $t['label'] }}
                    <span style="background: {{ $active ? 'var(--primary-tint)' : 'var(--bg-sunk)' }};
                                 color: {{ $active ? 'var(--primary)' : 'var(--ink-3)' }};
                                 padding: 1px 6px; border-radius: 999px; font-size: 10.5px; font-weight: 600;">
                        {{ $t['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        @if ($tab === 'cleaners')
            <x-directory.people-section
                :people="$cleaners"
                store-route="tenant.cleaners.store"
                update-route="tenant.cleaners.update"
                destroy-route="tenant.cleaners.destroy"
                :add-label="__('Register a cleaner')"
                :name-placeholder="__('e.g. Kak Minah')"
                :empty-text="__('No cleaners yet — add one above.')"
                count-attr="cleaning_tasks_count"
                :count-noun="__('task(s)')"
                :remove-confirm="__('Remove this cleaner?')"
                :remove-label="__('Remove cleaner')"/>
        @elseif ($tab === 'vendors')
            <x-directory.people-section
                :people="$vendors"
                store-route="tenant.laundry-vendors.store"
                update-route="tenant.laundry-vendors.update"
                destroy-route="tenant.laundry-vendors.destroy"
                :add-label="__('Register a laundry vendor')"
                :name-placeholder="__('e.g. Dobi Mesra')"
                :empty-text="__('No vendors yet — add one above.')"
                count-attr="laundry_tasks_count"
                :count-noun="__('batch(es)')"
                :remove-confirm="__('Remove this vendor?')"
                :remove-label="__('Remove vendor')"/>
        @else
            <x-directory.people-section
                :people="$maintenancePersons"
                store-route="tenant.maintenance-persons.store"
                update-route="tenant.maintenance-persons.update"
                destroy-route="tenant.maintenance-persons.destroy"
                :add-label="__('Register a maintenance person')"
                :name-placeholder="__('e.g. Pak Mat (plumber)')"
                :empty-text="__('No maintenance people yet — add one above.')"
                :remove-confirm="__('Remove this maintenance person?')"
                :remove-label="__('Remove person')"/>
        @endif
    </div>
</x-app-layout>
