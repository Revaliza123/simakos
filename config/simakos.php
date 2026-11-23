<?php

return [
    'admin' => [
        'name' => env('SIMAKOS_ADMIN_NAME'),
        'email' => env('SIMAKOS_ADMIN_EMAIL'),
        'password' => env('SIMAKOS_ADMIN_PASSWORD'),
    ],

    /*
    | Katalog fasilitas kamar: kunci => [label, ikon Material Symbols].
    | Dipakai di form tipe/unit kamar, kartu kamar, popup detail, landing page, dan portal penghuni.
    */
    'facilities' => [
        'ac' => ['AC', 'ac_unit'],
        'kipas' => ['Kipas angin', 'mode_fan'],
        'wifi' => ['WiFi', 'wifi'],
        'km_dalam' => ['Kamar mandi dalam', 'shower'],
        'km_luar' => ['Kamar mandi luar', 'bathroom'],
        'water_heater' => ['Water heater', 'hot_tub'],
        'kasur' => ['Kasur & bantal', 'bed'],
        'lemari' => ['Lemari pakaian', 'checkroom'],
        'meja' => ['Meja & kursi belajar', 'desk'],
        'tv' => ['TV', 'tv'],
        'kulkas' => ['Kulkas mini', 'kitchen'],
        'balkon' => ['Balkon pribadi', 'balcony'],
        'jendela' => ['Jendela ke luar', 'window'],
        'listrik' => ['Listrik termasuk', 'bolt'],
        'parkir' => ['Parkir motor', 'two_wheeler'],
        'laundry' => ['Laundry', 'local_laundry_service'],
        'dapur' => ['Dapur bersama', 'soup_kitchen'],
        'cctv' => ['CCTV & keamanan 24 jam', 'videocam'],
    ],

    /* Batas galeri foto per kamar. */
    'room_photos' => [
        'max_per_room' => 8,
        'max_kb' => 4096,
    ],

    /*
    | Lampiran pengumuman (gambar & video). Pastikan upload_max_filesize dan post_max_size
    | di php.ini (XAMPP default 40M) lebih besar dari batas video.
    */
    'announcement_media' => [
        'max_files' => 6,
        'image_max_kb' => 5120,
        'video_max_kb' => 30720,
    ],
];
