<?php

namespace App\Services\Properties;

use App\Models\Property;
use Illuminate\Support\Carbon;

/**
 * Writes a guest-facing homestay description (BM + EN) from the facts the
 * host entered on the create form — name, location, rooms, capacity, price,
 * facilities, check-in/out times. Used when the host leaves the description
 * blank, so a new tenant is never blocked on writing prose.
 *
 * Same grounding rule as TrainingQaGenerator: only say what the data says —
 * never fabricate a fact that wasn't provided.
 */
class DescriptionGenerator
{
    /**
     * @return array{en: string, bm: string}
     */
    public function generate(Property $property): array
    {
        $property->loadMissing(['rooms', 'amenities']);

        $rooms = $property->rooms;
        $wholeHouse = $property->pricing_mode !== 'per_room';
        $bedrooms = $wholeHouse
            ? max(1, (int) ($rooms->first()->beds ?? 1))
            : max(1, $rooms->count());
        $bathrooms = (int) ($property->bathrooms ?? 0);
        $toilets = (int) ($property->toilets ?? 0);

        $maxGuests = $wholeHouse
            ? (int) ($rooms->first()->max_adults ?? 0) + (int) ($rooms->first()->max_children ?? 0)
            : (int) $rooms->sum(fn ($r) => (int) $r->max_adults + (int) $r->max_children);

        $rate = (float) ($rooms->min('base_price') ?? 0);
        $rateLabel = $rate > 0 ? 'RM'.number_format($rate, fmod($rate, 1.0) > 0 ? 2 : 0) : null;

        $place = trim(collect([$property->city, $property->state])->filter()->unique()->implode(', '));

        $amenitiesEn = $property->amenities->sortBy('sort_order')->take(6)->pluck('label_en')->filter()->values()->all();
        $amenitiesBm = $property->amenities->sortBy('sort_order')->take(6)->pluck('label_bm')->filter()->values()->all();

        $checkIn = $this->time($property->check_in_time);
        $checkOut = $this->time($property->check_out_time);

        return [
            'en' => $this->english($property->name, $place, $wholeHouse, $bedrooms, $bathrooms, $toilets, $maxGuests, $rateLabel, $amenitiesEn, $checkIn, $checkOut),
            'bm' => $this->malay($property->name, $place, $wholeHouse, $bedrooms, $bathrooms, $toilets, $maxGuests, $rateLabel, $amenitiesBm, $checkIn, $checkOut),
        ];
    }

    private function english(string $name, string $place, bool $wholeHouse, int $bedrooms, int $bathrooms, int $toilets, int $maxGuests, ?string $rate, array $amenities, ?array $checkIn, ?array $checkOut): string
    {
        $s1 = $name.' is a '.$bedrooms.'-bedroom homestay'.($place !== '' ? ' in '.$place : '').'.';

        if ($wholeHouse) {
            $s2 = 'You book the whole house to yourselves'
                .($maxGuests > 0 ? ' — comfortable for up to '.$maxGuests.' guests' : '')
                .($rate ? ', at a flat '.$rate.' per night regardless of group size' : '')
                .'.';
        } else {
            $s2 = 'Each of the '.$bedrooms.' rooms books separately'
                .($rate ? ', from '.$rate.' per night' : '')
                .($maxGuests > 0 ? ' (sleeps up to '.$maxGuests.' guests in total)' : '')
                .'.';
        }

        $facts = [];
        if ($bathrooms > 0) {
            $facts[] = $bathrooms.' '.($bathrooms === 1 ? 'bathroom' : 'bathrooms');
        }
        if ($toilets > 0) {
            $facts[] = $toilets.' separate '.($toilets === 1 ? 'toilet' : 'toilets');
        }
        $s3 = $facts !== [] ? 'The house has '.$this->join($facts, 'and').'.' : '';

        $s4 = $amenities !== [] ? 'Facilities include '.$this->join($amenities, 'and').'.' : '';

        $s5 = '';
        if ($checkIn || $checkOut) {
            $bits = [];
            if ($checkIn) {
                $bits[] = 'Check-in is from '.$checkIn['en'];
            }
            if ($checkOut) {
                $bits[] = ($checkIn ? 'check-out by ' : 'Check-out is by ').$checkOut['en'];
            }
            $s5 = implode(', ', $bits).'.';
        }

        $s6 = 'Book direct with the host — no middleman, no commission.';

        return $this->paragraphs([$s1, $s2, $s3], [$s4, $s5, $s6]);
    }

    private function malay(string $name, string $place, bool $wholeHouse, int $bedrooms, int $bathrooms, int $toilets, int $maxGuests, ?string $rate, array $amenities, ?array $checkIn, ?array $checkOut): string
    {
        $s1 = $name.' ialah homestay '.$bedrooms.' bilik tidur'.($place !== '' ? ' di '.$place : '').'.';

        if ($wholeHouse) {
            $s2 = 'Anda tempah seluruh rumah untuk kumpulan anda sendiri'
                .($maxGuests > 0 ? ' — selesa untuk sehingga '.$maxGuests.' tetamu' : '')
                .($rate ? ', pada kadar tetap '.$rate.' semalam tanpa mengira bilangan tetamu' : '')
                .'.';
        } else {
            $s2 = 'Setiap satu daripada '.$bedrooms.' bilik boleh ditempah secara berasingan'
                .($rate ? ', bermula '.$rate.' semalam' : '')
                .($maxGuests > 0 ? ' (memuatkan sehingga '.$maxGuests.' tetamu keseluruhannya)' : '')
                .'.';
        }

        $facts = [];
        if ($bathrooms > 0) {
            $facts[] = $bathrooms.' bilik air';
        }
        if ($toilets > 0) {
            $facts[] = $toilets.' tandas berasingan';
        }
        $s3 = $facts !== [] ? 'Rumah ini mempunyai '.$this->join($facts, 'dan').'.' : '';

        $s4 = $amenities !== [] ? 'Kemudahan termasuk '.$this->join($amenities, 'dan').'.' : '';

        $s5 = '';
        if ($checkIn || $checkOut) {
            $bits = [];
            if ($checkIn) {
                $bits[] = 'Daftar masuk dari '.$checkIn['bm'];
            }
            if ($checkOut) {
                $bits[] = ($checkIn ? 'daftar keluar sebelum ' : 'Daftar keluar sebelum ').$checkOut['bm'];
            }
            $s5 = implode(', ', $bits).'.';
        }

        $s6 = 'Tempah terus dengan tuan rumah — tanpa orang tengah, tanpa komisen.';

        return $this->paragraphs([$s1, $s2, $s3], [$s4, $s5, $s6]);
    }

    /**
     * "3:00 PM" / "3:00 petang" from a "15:00[:00]" column value.
     *
     * @return array{en: string, bm: string}|null
     */
    private function time(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            $t = Carbon::createFromFormat('H:i', substr($value, 0, 5));
        } catch (\Throwable) {
            return null;
        }

        $hour = (int) $t->format('G');
        $bmPeriod = match (true) {
            $hour < 12 => 'pagi',
            $hour < 15 => 'tengah hari',
            $hour < 19 => 'petang',
            default => 'malam',
        };

        return [
            'en' => $t->format('g:i A'),
            'bm' => $t->format('g:i').' '.$bmPeriod,
        ];
    }

    /** "a, b and c" — Oxford-free list join. */
    private function join(array $items, string $conjunction): string
    {
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }
        $last = array_pop($items);

        return implode(', ', $items).' '.$conjunction.' '.$last;
    }

    /** Assemble non-empty sentences into up to two paragraphs. */
    private function paragraphs(array $first, array $second): string
    {
        $p1 = implode(' ', array_filter($first));
        $p2 = implode(' ', array_filter($second));

        return trim($p1.($p2 !== '' ? "\n\n".$p2 : ''));
    }
}
