<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Comprehensive amenity master list for Malaysian homestays.
     * Categorised so the property form can group them. Icons are emoji
     * glyphs that render natively without an icon font dependency.
     */
    public function run(): void
    {
        $i = 0;
        $rows = [];

        $add = function (string $key, string $bm, string $en, string $cat, string $icon) use (&$rows, &$i) {
            $rows[] = compact('key', 'bm', 'en', 'cat', 'icon') + ['sort' => $i++];
        };

        // ── Essentials ────────────────────────────────────────────────
        $add('wifi',          'Wi-Fi percuma',           'Free Wi-Fi',             'essential', '📶');
        $add('aircond',       'Penyaman udara',          'Air conditioning',       'essential', '❄️');
        $add('ceiling_fan',   'Kipas siling',            'Ceiling fan',            'essential', '🪭');
        $add('hot_shower',    'Air panas',               'Hot shower',             'essential', '🚿');
        $add('water_heater',  'Pemanas air',             'Water heater',           'essential', '🔥');
        $add('tv',            'TV',                      'TV',                     'essential', '📺');
        $add('astro',         'Astro / Android TV',      'Astro / Android TV',     'essential', '🛰️');
        $add('parking_free',  'Letak kereta percuma',    'Free parking',           'essential', '🅿️');
        $add('washing_machine', 'Mesin basuh',           'Washing machine',        'essential', '🧺');
        $add('iron',          'Seterika',                'Iron',                   'essential', '🧷');
        $add('hair_dryer',    'Pengering rambut',        'Hair dryer',             'essential', '💨');
        $add('towels',        'Tuala disediakan',        'Towels provided',        'essential', '🛏️');
        $add('toiletries',    'Sabun & syampu',          'Soap & shampoo',         'essential', '🧴');

        // ── Kitchen ───────────────────────────────────────────────────
        $add('kitchen_full',  'Dapur lengkap',           'Full kitchen',           'kitchen', '🍳');
        $add('kitchenette',   'Dapur kecil',             'Kitchenette',            'kitchen', '🍴');
        $add('fridge',        'Peti sejuk',              'Refrigerator',           'kitchen', '🧊');
        $add('microwave',     'Ketuhar gelombang mikro', 'Microwave',              'kitchen', '🌡️');
        $add('stove_gas',     'Dapur gas',               'Gas stove',              'kitchen', '🔥');
        $add('rice_cooker',   'Periuk nasi',             'Rice cooker',            'kitchen', '🍚');
        $add('kettle',        'Cerek elektrik',          'Electric kettle',        'kitchen', '☕');
        $add('toaster',       'Pembakar roti',           'Toaster',                'kitchen', '🍞');
        $add('water_dispenser', 'Pendispens air',        'Water dispenser',        'kitchen', '💧');

        // ── Entertainment ─────────────────────────────────────────────
        $add('karaoke',       'Karaoke',                 'Karaoke set',            'entertainment', '🎤');
        $add('snooker',       'Meja snooker / biliard',  'Snooker / pool table',   'entertainment', '🎱');
        $add('table_tennis',  'Ping pong',               'Table tennis',           'entertainment', '🏓');
        $add('foosball',      'Foosball',                'Foosball',               'entertainment', '⚽');
        $add('board_games',   'Permainan papan',         'Board games',            'entertainment', '🎲');
        $add('game_console',  'Konsol permainan',        'Game console',           'entertainment', '🎮');
        $add('books',         'Buku & majalah',          'Books & magazines',      'entertainment', '📚');

        // ── Outdoor ───────────────────────────────────────────────────
        $add('pool_private',  'Kolam renang persendirian', 'Private pool',         'outdoor', '🏊');
        $add('pool_shared',   'Kolam renang berkongsi',  'Shared pool',            'outdoor', '🏖️');
        $add('bbq_pit',       'Tempat BBQ',              'BBQ pit',                'outdoor', '🍖');
        $add('outdoor_dining', 'Tempat makan luar',      'Outdoor dining',         'outdoor', '🍽️');
        $add('garden',        'Taman',                   'Garden',                 'outdoor', '🌿');
        $add('fire_pit',      'Unggun api',              'Fire pit',               'outdoor', '🔥');
        $add('hammock',       'Buaian / hammock',        'Hammock',                'outdoor', '🛌');
        $add('beach_access',  'Akses pantai',            'Beach access',           'outdoor', '🏝️');
        $add('river_access',  'Akses sungai',            'River access',           'outdoor', '🏞️');
        $add('balcony',       'Balkoni',                 'Balcony',                'outdoor', '🪟');

        // ── Family & accessibility ────────────────────────────────────
        $add('kid_friendly',  'Mesra kanak-kanak',       'Kid-friendly',           'family', '🧒');
        $add('high_chair',    'Kerusi tinggi bayi',      'High chair',             'family', '🪑');
        $add('baby_cot',      'Katil bayi',              'Baby cot',               'family', '🍼');
        $add('pets_allowed',  'Haiwan peliharaan dibenarkan', 'Pets allowed',      'family', '🐾');
        $add('wheelchair',    'Mesra kerusi roda',       'Wheelchair accessible',  'family', '♿');
        $add('ground_floor',  'Tingkat bawah',           'Ground floor unit',      'family', '🏠');

        // ── Cultural (Malaysia) ───────────────────────────────────────
        $add('halal',         'Mesra halal',             'Halal-friendly',         'cultural', '🕌');
        $add('surau',         'Surau / bilik solat',     'Surau / prayer room',    'cultural', '🤲');
        $add('quran',         'Al-Quran disediakan',     'Quran provided',         'cultural', '📖');
        $add('kiblat',        'Penanda kiblat',          'Kiblat direction marker','cultural', '🧭');
        $add('prayer_mat',    'Sejadah',                 'Prayer mat',             'cultural', '🪶');

        // ── Safety ────────────────────────────────────────────────────
        $add('smoke_detector', 'Pengesan asap',          'Smoke detector',         'safety', '🚨');
        $add('first_aid',     'Peti pertolongan cemas',  'First aid kit',          'safety', '⛑️');
        $add('fire_extinguisher', 'Pemadam api',         'Fire extinguisher',      'safety', '🧯');
        $add('cctv_common',   'CCTV kawasan umum',       'CCTV common areas',      'safety', '📹');
        $add('smoke_free',    'Tiada merokok',           'Smoke-free',             'safety', '🚭');
        $add('security_guard', 'Pengawal keselamatan',   'Security guard',         'safety', '👮');

        // ── Workspace ─────────────────────────────────────────────────
        $add('work_desk',     'Meja kerja',              'Work desk',              'workspace', '🖥️');
        $add('quiet_workspace', 'Ruang kerja tenang',    'Quiet workspace',        'workspace', '🤫');

        foreach ($rows as $r) {
            Amenity::updateOrCreate(
                ['key' => $r['key']],
                [
                    'label_bm'   => $r['bm'],
                    'label_en'   => $r['en'],
                    'category'   => $r['cat'],
                    'icon'       => $r['icon'],
                    'sort_order' => $r['sort'],
                ],
            );
        }
    }
}
