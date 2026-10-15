<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Rental;

/**
 * Builds the WhatsApp / phone / email hand-over links that carry a
 * reservation summary (or a plain "talk to staff" request) to the rental team.
 */
class ReservationHandover
{
    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forBooking(Rental $rental, Booking $booking, string $locale): array
    {
        $message = $this->reservationMessage($booking, $locale);

        return $this->links($rental, $message, $this->subject('reservation', $locale).' '.$booking->reference);
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forStaff(Rental $rental, string $locale): array
    {
        return $this->links($rental, $this->subject('staff_message', $locale), $this->subject('staff', $locale));
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    private function links(Rental $rental, string $message, string $subject): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $rental->whatsapp);
        $phone = preg_replace('/[^\d+]/', '', (string) $rental->phone);

        return [
            'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message) : null,
            'phone_url' => $phone !== '' ? 'tel:'.$phone : null,
            'email_url' => filled($rental->email) ? 'mailto:'.$rental->email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        ];
    }

    private function reservationMessage(Booking $booking, string $locale): string
    {
        $booking->loadMissing('vehicle');

        $labels = match ($locale) {
            'en' => ['Hello, I would like to request a car rental.', 'Name', 'Pick-up', 'Return', 'Passengers', 'Units', 'Car', 'Driver', 'Special Request', 'Reference', 'With driver', 'Self-drive'],
            'ja' => ['こんにちは。レンタカーの予約をリクエストしたいです。', 'お名前', '受取日', '返却日', '乗車人数', '台数', '車両', 'ドライバー', 'ご要望', '予約番号', 'ドライバー付き', 'セルフドライブ'],
            default => ['Halo, saya ingin mengajukan sewa mobil.', 'Nama', 'Tanggal ambil', 'Tanggal kembali', 'Penumpang', 'Unit', 'Mobil', 'Sopir', 'Permintaan Khusus', 'Referensi', 'Dengan sopir', 'Lepas kunci'],
        };

        return implode("\n", [
            $labels[0],
            '',
            "{$labels[1]}: {$booking->customer_name}",
            "{$labels[2]}: ".$booking->pickup_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[3]}: ".$booking->return_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[4]}: {$booking->passengers}",
            "{$labels[5]}: {$booking->unit_count}",
            "{$labels[6]}: ".$booking->vehicle->translatedName($locale),
            "{$labels[7]}: ".($booking->with_driver ? $labels[10] : $labels[11]),
            "{$labels[8]}: ".($booking->notes ?: '-'),
            '',
            "{$labels[9]}: {$booking->reference}",
        ]);
    }

    private function subject(string $key, string $locale): string
    {
        return [
            'en' => ['reservation' => 'Car rental request', 'staff' => 'Question for the rental team', 'staff_message' => 'Hello, I would like to speak with the rental team.'],
            'ja' => ['reservation' => 'レンタカー予約リクエスト', 'staff' => 'スタッフへのご相談', 'staff_message' => 'こんにちは。レンタカーのスタッフとお話ししたいです。'],
            'id' => ['reservation' => 'Permintaan sewa mobil', 'staff' => 'Pertanyaan untuk tim rental', 'staff_message' => 'Halo, saya ingin bicara dengan tim rental.'],
        ][$locale][$key];
    }
}
