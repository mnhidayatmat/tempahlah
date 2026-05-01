@extends('layouts.app', ['title' => $listing->title_bm])

@section('content')
<article class="max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold mb-1">{{ $listing->title_bm }}</h1>
    <p class="text-slate-600">{{ $listing->city }}, {{ $listing->state }}</p>

    @if ($listing->rating_avg)
        <p class="mt-2">⭐ {{ number_format($listing->rating_avg, 1) }} · {{ $listing->review_count }} {{ __('reviews') }}</p>
    @endif

    <div class="grid md:grid-cols-2 gap-2 mt-4">
        @if ($listing->hero_photo_path)
            <div class="aspect-[4/3] bg-slate-100 rounded-lg overflow-hidden">
                <img src="{{ Storage::url($listing->hero_photo_path) }}" class="w-full h-full object-cover" alt="">
            </div>
        @endif
        @foreach ($listing->property->photos->take(4) as $photo)
            <div class="aspect-[4/3] bg-slate-100 rounded-lg overflow-hidden">
                <img src="{{ Storage::url($photo->path) }}" class="w-full h-full object-cover" alt="">
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-[1fr_380px] gap-6 mt-6">
        <div>
            <section class="bg-white rounded-lg shadow border border-slate-200 p-6">
                <h2 class="text-xl font-semibold mb-3">{{ __('About this homestay') }}</h2>
                <p class="text-slate-700 whitespace-pre-line">{{ $listing->property->description_bm ?? $listing->property->description_en }}</p>
            </section>

            <section class="bg-white rounded-lg shadow border border-slate-200 p-6 mt-4">
                <h2 class="text-xl font-semibold mb-3">{{ __('Rooms') }}</h2>
                <div class="space-y-3">
                    @foreach ($listing->property->rooms as $room)
                        <div class="border border-slate-200 rounded-md p-3 flex justify-between">
                            <div>
                                <p class="font-medium">{{ $room->name }}</p>
                                <p class="text-sm text-slate-500">{{ $room->max_adults }} {{ __('adults') }} · {{ $room->beds }} {{ __('beds') }}</p>
                            </div>
                            <p class="font-semibold">RM {{ number_format($room->base_price, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="bg-white rounded-lg shadow border border-slate-200 p-6 h-fit sticky top-4">
            <h3 class="font-semibold mb-3">{{ __('Book your stay') }}</h3>
            <p class="text-sm text-slate-600 mb-3">{{ __('Continue to booking — you will need to verify your phone with OTP.') }}</p>
            <a href="#" class="block w-full text-center rounded-md bg-sky-600 text-white py-2.5 font-medium hover:bg-sky-700">
                {{ __('Reserve') }}
            </a>
        </aside>
    </div>
</article>
@endsection
