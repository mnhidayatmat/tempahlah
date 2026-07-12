<?php

/*
|--------------------------------------------------------------------------
| Malaysian districts (daerah) by state
|--------------------------------------------------------------------------
| Used by the marketplace search to cascade a district dropdown off the
| selected state (Negeri Johor → Kluang, Batu Pahat, …). Keys match the
| state values in the marketplace search's $states list. Values are the
| official administrative districts, alphabetical. Districts map to a
| property's `city` at filter time (LIKE), so a listing in "Kluang" matches.
*/

return [

    'Johor' => [
        'Batu Pahat', 'Johor Bahru', 'Kluang', 'Kota Tinggi', 'Kulai',
        'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak',
    ],

    'Kedah' => [
        'Baling', 'Bandar Baharu', 'Kota Setar', 'Kuala Muda', 'Kubang Pasu',
        'Kulim', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena', 'Sik', 'Yan',
    ],

    'Kelantan' => [
        'Bachok', 'Gua Musang', 'Jeli', 'Kota Bharu', 'Kuala Krai', 'Machang',
        'Pasir Mas', 'Pasir Puteh', 'Tanah Merah', 'Tumpat',
    ],

    'Melaka' => [
        'Alor Gajah', 'Jasin', 'Melaka Tengah',
    ],

    'Negeri Sembilan' => [
        'Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau',
        'Seremban', 'Tampin',
    ],

    'Pahang' => [
        'Bentong', 'Bera', 'Cameron Highlands', 'Jerantut', 'Kuantan', 'Lipis',
        'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh',
    ],

    'Perak' => [
        'Bagan Datuk', 'Batang Padang', 'Hilir Perak', 'Hulu Perak', 'Kampar',
        'Kerian', 'Kinta', 'Kuala Kangsar', 'Larut, Matang dan Selama',
        'Manjung', 'Muallim', 'Perak Tengah',
    ],

    'Perlis' => [
        'Arau', 'Kangar', 'Padang Besar',
    ],

    'Pulau Pinang' => [
        'Barat Daya', 'Seberang Perai Selatan', 'Seberang Perai Tengah',
        'Seberang Perai Utara', 'Timur Laut (George Town)',
    ],

    'Sabah' => [
        'Beaufort', 'Keningau', 'Kota Belud', 'Kota Kinabalu', 'Kudat',
        'Lahad Datu', 'Papar', 'Penampang', 'Ranau', 'Sandakan', 'Semporna',
        'Tawau', 'Tuaran',
    ],

    'Sarawak' => [
        'Betong', 'Bintulu', 'Kapit', 'Kuching', 'Limbang', 'Miri', 'Mukah',
        'Samarahan', 'Sarikei', 'Sibu', 'Sri Aman', 'Serian',
    ],

    'Selangor' => [
        'Gombak', 'Hulu Langat', 'Hulu Selangor', 'Klang', 'Kuala Langat',
        'Kuala Selangor', 'Petaling', 'Sabak Bernam', 'Sepang',
    ],

    'Terengganu' => [
        'Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman', 'Kuala Nerus',
        'Kuala Terengganu', 'Marang', 'Setiu',
    ],

    'Kuala Lumpur' => [
        'Kuala Lumpur',
    ],

    'Putrajaya' => [
        'Putrajaya',
    ],

    'Labuan' => [
        'Labuan',
    ],
];
