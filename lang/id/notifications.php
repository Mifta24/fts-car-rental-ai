<?php

return [
    'booking_details' => 'Referensi :reference · :customer · :vehicle · :pickup → :return · Total :total',
    'new_booking' => [
        'subject' => 'Permintaan sewa baru :reference',
        'intro' => 'Ada pelanggan yang mengirim permintaan sewa. Mobil ditahan selama :expires jam; konfirmasi atau batalkan sebelum itu.',
        'action' => 'Lihat booking',
    ],
    'new_handover' => [
        'subject' => 'Pelanggan membutuhkan bantuan Anda',
        'intro' => 'AI Rental Agent menyerahkan sebuah percakapan ke tim.',
        'reason' => 'Alasan: :reason',
        'action' => 'Buka percakapan',
    ],
    'customer_cancelled' => [
        'subject' => 'Booking :reference dibatalkan pelanggan',
        'intro' => ':customer membatalkan booking :reference. Mobil tersedia kembali.',
    ],
    'booking_status' => [
        'greeting' => 'Halo :name,',
        'subject' => [
            'pending' => 'Permintaan :reference kami terima',
            'confirmed' => 'Booking :reference Anda dikonfirmasi',
            'cancelled' => 'Booking :reference Anda dibatalkan',
            'expired' => 'Permintaan :reference Anda telah kedaluwarsa',
        ],
        'body' => [
            'pending' => 'Terima kasih telah memilih :rental. Permintaan sewa Anda sudah kami terima dan tim kami akan mengonfirmasinya dalam :hours jam.',
            'confirmed' => ':rental telah mengonfirmasi booking Anda. Kami menantikan kedatangan Anda.',
            'cancelled' => 'Booking Anda di :rental telah dibatalkan.',
            'expired' => 'Kami tidak dapat mengonfirmasi permintaan Anda tepat waktu, sehingga permintaan kedaluwarsa dan mobil dilepas kembali. Silakan ajukan lagi.',
        ],
        'contact' => 'Ada pertanyaan? Hubungi kami: :phone',
    ],
];
