<?php

return [
    'booking_details' => 'Reference :reference · :customer · :vehicle · :pickup → :return · Total :total',
    'new_booking' => [
        'subject' => 'New reservation request :reference',
        'intro' => 'A customer sent a reservation request. It holds the car for :expires hours; confirm or cancel it before then.',
        'action' => 'Review bookings',
    ],
    'new_handover' => [
        'subject' => 'A customer needs your help',
        'intro' => 'The AI Rental Agent handed a conversation over to the team.',
        'reason' => 'Reason: :reason',
        'action' => 'Open conversation',
    ],
    'customer_cancelled' => [
        'subject' => 'Booking :reference cancelled by the customer',
        'intro' => ':customer cancelled booking :reference. The car is available again.',
    ],
    'booking_status' => [
        'greeting' => 'Hello :name,',
        'subject' => [
            'pending' => 'We received your request :reference',
            'confirmed' => 'Your booking :reference is confirmed',
            'cancelled' => 'Your booking :reference was cancelled',
            'expired' => 'Your request :reference has expired',
        ],
        'body' => [
            'pending' => 'Thank you for choosing :rental. We received your reservation request and our team will confirm it within :hours hours.',
            'confirmed' => ':rental has confirmed your booking. We look forward to seeing you.',
            'cancelled' => 'Your booking with :rental has been cancelled.',
            'expired' => 'We could not confirm your request in time, so it has expired and the car was released. You are welcome to request again.',
        ],
        'contact' => 'Questions? Contact us: :phone',
    ],
];
