@extends('layouts.app', ['title' => __('Find a homestay')])

@section('content')
<div class="grid lg:grid-cols-[280px_1fr] gap-6">
    <aside class="bg-white rounded-lg shadow border border-slate-200 p-4 h-fit">
        <h2 class="font-semibold mb-3">{{ __('Filters') }}</h2>
        <form method="GET" class="space-y-3 text-sm">
            <div>
                <label class="block mb-1">{{ __('Search') }}</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-md border-slate-300">
            </div>
            <div>
                <label class="block mb-1">{{ __('City') }}</label>
                <input name="city" value="{{ $filters['city'] ?? '' }}" class="w-full rounded-md border-slate-300">
            </div>
            <div>
                <label class="block mb-1">{{ __('State') }}</label>
                <select name="state" class="w-full rounded-md border-slate-300">
                    <option value="">— {{ __('Any') }} —</option>
                    @foreach (['Selangor','Kuala Lumpur','Penang','Sabah','Sarawak','Johor','Pahang','Terengganu','Kelantan','Kedah','Perak','Negeri Sembilan','Melaka','Perlis','Putrajaya','Labuan'] as $st)
                        <option value="{{ $st }}" @selected(($filters['state'] ?? '') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input name="min_price" placeholder="Min RM" value="{{ $filters['min_price'] ?? '' }}" class="rounded-md border-slate-300">
                <input name="max_price" placeholder="Max RM" value="{{ $filters['max_price'] ?? '' }}" class="rounded-md border-slate-300">
            </div>
            <button class="w-full rounded-md bg-sky-600 text-white py-2">{{ __('Apply filters') }}</button>
        </form>
    </aside>

    <section>
        <h1 class="text-2xl font-semibold mb-4">{{ __('Find your homestay') }}</h1>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($listings as $listing)
                <a href="{{ route('marketplace.show', $listing) }}" class="bg-white rounded-lg shadow border border-slate-200 overflow-hidden hover:shadow-md">
                    <div class="aspect-[4/3] bg-slate-100 relative">
                        @if ($listing->hero_photo_path)
                            <img src="{{ Storage::url($listing->hero_photo_path) }}" class="w-full h-full object-cover" alt="">
                        @endif
                        @if ($listing->is_featured)
                            <span class="absolute top-2 left-2 bg-amber-500 text-white text-xs px-2 py-1 rounded">{{ __('Featured') }}</span>
                        @endif
                    </div>
                    <div class="p-3">
                        <h3 class="font-semibold">{{ $listing->title_bm }}</h3>
                        <p class="text-sm text-slate-500">{{ $listing->city }}, {{ $listing->state }}</p>
                        <p class="mt-2 text-sm">
                            @if ($listing->rating_avg)
                                ⭐ {{ number_format($listing->rating_avg, 1) }} ({{ $listing->review_count }})
                            @endif
                        </p>
                        @if ($listing->base_price_min)
                            <p class="mt-1 font-semibold">{{ __('From') }} RM {{ number_format($listing->base_price_min, 0) }}/{{ __('night') }}</p>
                        @endif
                    </div>
                </a>
            @empty
                <p class="col-span-full text-center text-slate-500 py-12">{{ __('No homestays match your filters.') }}</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $listings->links() }}</div>
    </section>
</div>
@endsection
