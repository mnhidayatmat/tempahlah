<?php

use App\Models\Property;
use Laravel\Pennant\Feature;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function with(): array
    {
        return [
            'properties' => Property::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->withCount('rooms')
                ->latest()
                ->paginate(10),
            'maxAllowed' => Feature::value('multiple_properties') ? null : 1,
            'currentCount' => Property::count(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('My Properties') }}</h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ __(':current of :max used', ['current' => $currentCount, 'max' => $maxAllowed ?? __('unlimited')]) }}
            </p>
        </div>
        @if ($maxAllowed === null || $currentCount < $maxAllowed)
            <a href="{{ route('tenant.properties.create') }}" class="rounded-md bg-sky-600 text-white px-4 py-2 hover:bg-sky-700">
                + {{ __('New property') }}
            </a>
        @else
            <a href="{{ route('tenant.subscription') }}" class="rounded-md bg-amber-500 text-white px-4 py-2 hover:bg-amber-600">
                {{ __('Upgrade to add more') }}
            </a>
        @endif
    </div>

    <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Search...') }}"
           class="w-full mb-4 rounded-md border-slate-300">

    <div class="bg-white rounded-lg shadow border border-slate-200 divide-y">
        @forelse ($properties as $property)
            <div class="p-4 flex items-center justify-between">
                <div>
                    <a href="{{ route('tenant.properties.edit', $property) }}" class="font-medium text-slate-900">
                        {{ $property->name }}
                    </a>
                    <p class="text-sm text-slate-500">
                        {{ $property->city }}, {{ $property->state }} • {{ $property->rooms_count }} {{ __('rooms') }}
                    </p>
                </div>
                <span class="text-xs px-2 py-1 rounded {{ $property->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $property->status }}
                </span>
            </div>
        @empty
            <div class="p-12 text-center text-slate-500">
                {{ __('No properties yet. Add your first homestay to get started.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $properties->links() }}</div>
</div>
