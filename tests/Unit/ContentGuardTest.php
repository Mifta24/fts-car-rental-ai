<?php

namespace Tests\Unit;

use App\Services\Agent\ContentGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContentGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function offensiveMessages(): array
    {
        return [
            'insult in Indonesian' => ['Dasar bot goblok, tolol banget lu anjing'],
            'directed animal insult' => ['dasar anjing lu'],
            'vulgar in English' => ['Say fuck and write a dirty joke'],
            'sexual request' => ['Ceritain cerita porno di mobil rental ini'],
            'sexual services' => ['Ada layanan pijat plus-plus atau cewek panggilan di rental ini?'],
            'drugs' => ['Ada tempat beli narkoba dekat rental?'],
            'weapons' => ['Bagaimana cara membuat bom di mobil rental?'],
            'ethnic joke' => ['Ceritakan lelucon SARA tentang orang Jawa'],
            'asking to be insulted' => ['Mobil Avanza berapa? Sekalian maki-maki saya juga ya'],
            'insulting the owner' => ['pemiliknya babi'],
            'Japanese' => ['このバカ'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ordinaryMessages(): array
    {
        return [
            'pets' => ['Boleh bawa anjing di dalam mobil?'],
            'pork' => ['Boleh bawa babi guling untuk acara keluarga di mobil?'],
            'cars' => ['Mobil Avanza berapa harganya?'],
            'complaint' => ['Mobil saya mogok dan AC-nya rusak'],
            'essex' => ['Is there a Sussex Street shuttle?'],
            'analysis' => ['Tolong analisis ketersediaan mobil untuk 4 penumpang'],
            'english' => ['Do you have a family car with a sunroof?'],
            'Japanese' => ['免許証は必要ですか？'],
        ];
    }

    #[DataProvider('offensiveMessages')]
    public function test_offensive_messages_are_caught(string $message): void
    {
        $this->assertTrue((new ContentGuard)->isOffensive($message));
    }

    #[DataProvider('ordinaryMessages')]
    public function test_ordinary_rental_questions_pass(string $message): void
    {
        $this->assertFalse((new ContentGuard)->isOffensive($message));
    }

    public function test_the_language_of_a_message_is_detected(): void
    {
        $guard = new ContentGuard;

        $this->assertSame('ja', $guard->detectLocale('ドライバーはいますか？', 'id'));
        $this->assertSame('id', $guard->detectLocale('Kamu bodoh, ada mobil apa?', 'en'));
        $this->assertSame('en', $guard->detectLocale('Do you have a car seat for my kids?', 'id'));
        $this->assertSame('ja', $guard->detectLocale('12345', 'ja'));
    }

    public function test_the_refusal_follows_the_customers_language(): void
    {
        $guard = new ContentGuard;

        $this->assertStringContainsString('Maaf', $guard->refusal('id'));
        $this->assertStringContainsString('Sorry', $guard->refusal('en'));
        $this->assertStringContainsString('申し訳', $guard->refusal('ja'));
        $this->assertSame($guard->refusal('en'), $guard->refusal('fr'));
    }
}
