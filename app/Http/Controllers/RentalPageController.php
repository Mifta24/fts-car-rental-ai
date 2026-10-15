<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\RentalKnowledgeItem;
use App\Models\Vehicle;
use App\Services\Reservation\ReservationHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RentalPageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly ReservationHandover $handover) {}

    /**
     * What the AI rental agent says when the customer steps into the fleet scenes.
     * Every sentence is built from stored rental data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'vehicles' => 'Here is our fleet. We have :count cars, from :price per day. Open any of them and I will walk you through it, or ask me anything.',
            'vehicles_one' => 'Here is our car. We have one model, from :price per day. Open it and I will walk you through it, or ask me anything.',
            'seats_luggage' => 'It seats :seats passengers and takes :luggage bags.',
            'seats' => 'It seats :seats passengers.',
            'insurance' => 'Basic insurance is included.',
            'price' => 'Rates start from :price per day. Final availability and rates are confirmed by our team.',
            'services' => 'We have :count services and packages, including :examples. Choose one to read the details, or ask me anything.',
            'services_one' => 'We offer :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :rental.',
            'info_place' => 'We are located in :location.',
            'info_hours' => 'Our counter is open from :in to :out.',
            'info_more' => 'On screen you will find our address, rental terms and frequently asked questions — or just ask me.',
            'tour_vehicles' => 'This way — let me show you our fleet.',
            'tour_services' => 'Come, let me show you our rental services.',
            'tour_info' => 'Let me tell you more about renting with us.',
            'tour_staff' => 'Let me bring you to our team.',
            'tour_reservation' => 'Let me help you plan your rental.',
            'tour_showroom' => 'Let me walk you back to the showroom.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'vehicles' => 'Inilah armada kami. Ada :count mobil, mulai dari :price per hari. Buka salah satu, nanti saya jelaskan — atau tanyakan apa saja kepada saya.',
            'vehicles_one' => 'Inilah mobil kami. Ada satu model, mulai dari :price per hari. Buka mobilnya, nanti saya jelaskan — atau tanyakan apa saja kepada saya.',
            'seats_luggage' => 'Kapasitas :seats penumpang dan muat :luggage koper.',
            'seats' => 'Kapasitas :seats penumpang.',
            'insurance' => 'Sudah termasuk asuransi dasar.',
            'price' => 'Tarif mulai dari :price per hari. Ketersediaan dan tarif final dikonfirmasi oleh tim kami.',
            'services' => 'Kami memiliki :count layanan dan paket, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'services_one' => 'Kami memiliki :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :rental.',
            'info_place' => 'Kami berada di :location.',
            'info_hours' => 'Konter kami buka pukul :in sampai :out.',
            'info_more' => 'Di layar ini ada alamat, syarat sewa, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'tour_vehicles' => 'Mari, saya antar melihat armada kami.',
            'tour_services' => 'Mari, saya antar ke layanan rental kami.',
            'tour_info' => 'Mari, saya ceritakan lebih lanjut tentang sewa mobil di sini.',
            'tour_staff' => 'Mari, saya antar Anda bertemu tim kami.',
            'tour_reservation' => 'Mari, saya bantu rencanakan sewa Anda.',
            'tour_showroom' => 'Mari saya antar kembali ke showroom.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'vehicles' => 'こちらが保有車両です。:count台、1日 :price からご用意しています。車両を開いていただければ、ご案内いたします。ご質問もお気軽にどうぞ。',
            'vehicles_one' => 'こちらが車両です。1モデル、1日 :price からご用意しています。車両を開いていただければ、ご案内いたします。ご質問もお気軽にどうぞ。',
            'seats_luggage' => '乗車定員:seats名、荷物:luggage個まで積めます。',
            'seats' => '乗車定員は:seats名です。',
            'insurance' => '基本保険付きです。',
            'price' => '料金は1日 :price からです。空車状況と料金は、スタッフが最終確認いたします。',
            'services' => ':count件のサービス・プランをご用意しています。:examples などです。選ぶと詳細をご覧いただけます。ご質問もどうぞ。',
            'services_one' => ':examples をご用意しています。詳細をご覧ください。ご質問もどうぞ。',
            'info_welcome' => 'ようこそ、:rental へ。',
            'info_place' => '所在地は:locationです。',
            'info_hours' => '受付時間は:in〜:outです。',
            'info_more' => '画面に、所在地、ご利用条件、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'tour_vehicles' => 'こちらへどうぞ。車両をご案内します。',
            'tour_services' => 'こちらへどうぞ。レンタルサービスをご案内します。',
            'tour_info' => 'レンタカーについてご案内します。',
            'tour_staff' => 'スタッフのもとへご案内します。',
            'tour_reservation' => 'レンタルのご計画をお手伝いします。',
            'tour_showroom' => 'ショールームへご案内します。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Customer-facing names for the coded vehicle values stored in the database.
     *
     * @var array<string, array{transmission: array<string, string>, fuel: array<string, string>, body: array<string, string>, feature: array<string, string>}>
     */
    private const VEHICLE_TERMS = [
        'en' => [
            'transmission' => ['automatic' => 'Automatic', 'manual' => 'Manual'],
            'fuel' => ['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric'],
            'body' => ['hatchback' => 'Hatchback', 'sedan' => 'Sedan', 'mpv' => 'MPV', 'suv' => 'SUV', 'van' => 'Van', 'luxury' => 'Luxury', 'pickup' => 'Pickup'],
            'feature' => ['air_conditioning' => 'Air conditioning', 'bluetooth' => 'Bluetooth audio', 'gps' => 'GPS navigation', 'usb_charging' => 'USB charging', 'sunroof' => 'Sunroof', 'dashcam' => 'Dashcam', 'child_seat' => 'Child seat ready', 'rear_camera' => 'Reverse camera', 'cruise_control' => 'Cruise control', 'leather_seats' => 'Leather seats', 'wifi' => 'In-car Wi-Fi', 'third_row' => 'Third row seats'],
        ],
        'id' => [
            'transmission' => ['automatic' => 'Otomatis', 'manual' => 'Manual'],
            'fuel' => ['petrol' => 'Bensin', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Listrik'],
            'body' => ['hatchback' => 'Hatchback', 'sedan' => 'Sedan', 'mpv' => 'MPV', 'suv' => 'SUV', 'van' => 'Van', 'luxury' => 'Mewah', 'pickup' => 'Pickup'],
            'feature' => ['air_conditioning' => 'AC', 'bluetooth' => 'Audio Bluetooth', 'gps' => 'Navigasi GPS', 'usb_charging' => 'Pengisi daya USB', 'sunroof' => 'Sunroof', 'dashcam' => 'Dashcam', 'child_seat' => 'Siap kursi anak', 'rear_camera' => 'Kamera mundur', 'cruise_control' => 'Cruise control', 'leather_seats' => 'Jok kulit', 'wifi' => 'Wi-Fi di mobil', 'third_row' => 'Baris ketiga'],
        ],
        'ja' => [
            'transmission' => ['automatic' => 'オートマチック', 'manual' => 'マニュアル'],
            'fuel' => ['petrol' => 'ガソリン', 'diesel' => 'ディーゼル', 'hybrid' => 'ハイブリッド', 'electric' => '電気'],
            'body' => ['hatchback' => 'ハッチバック', 'sedan' => 'セダン', 'mpv' => 'ミニバン', 'suv' => 'SUV', 'van' => 'バン', 'luxury' => 'ラグジュアリー', 'pickup' => 'ピックアップ'],
            'feature' => ['air_conditioning' => 'エアコン', 'bluetooth' => 'Bluetoothオーディオ', 'gps' => 'GPSナビ', 'usb_charging' => 'USB充電', 'sunroof' => 'サンルーフ', 'dashcam' => 'ドライブレコーダー', 'child_seat' => 'チャイルドシート対応', 'rear_camera' => 'バックカメラ', 'cruise_control' => 'クルーズコントロール', 'leather_seats' => 'レザーシート', 'wifi' => '車内Wi-Fi', 'third_row' => '3列シート'],
        ],
    ];

    public function index(Request $request): View
    {
        $rentals = Rental::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($rentals->first()?->default_locale ?? 'id');

        return view('opening', [
            'rentals' => $rentals,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
        ]);
    }

    public function show(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.show', $this->stageData($rental, $locale, 'showroom'));
    }

    public function vehicles(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.vehicles-index', $this->stageData($rental, $locale, 'vehicles'));
    }

    public function vehicle(Request $request, string $rentalSlug, string $vehicleSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        $data = $this->stageData($rental, $locale, 'vehicle');
        $vehicles = $data['vehicles'];
        $index = $vehicles->search(fn (Vehicle $vehicle) => $vehicle->slug === $vehicleSlug);

        abort_if($index === false, 404);

        return view('rental.vehicle-detail', [
            ...$data,
            'vehicle' => $vehicles[$index],
            'vehicleIndex' => $index,
            'previousVehicle' => $vehicles[($index - 1 + $vehicles->count()) % $vehicles->count()],
            'nextVehicle' => $vehicles[($index + 1) % $vehicles->count()],
        ]);
    }

    public function services(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.services-index', $this->stageData($rental, $locale, 'services'));
    }

    public function service(Request $request, string $rentalSlug, int $serviceId): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        $data = $this->stageData($rental, $locale, 'service');
        $services = $data['services'];
        $index = $services->search(fn (RentalKnowledgeItem $item) => $item->id === $serviceId);

        abort_if($index === false, 404);

        return view('rental.service-detail', [
            ...$data,
            'service' => $services[$index],
            'serviceIndex' => $index,
            'previousService' => $services[($index - 1 + $services->count()) % $services->count()],
            'nextService' => $services[($index + 1) % $services->count()],
        ]);
    }

    public function info(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.info-index', $this->stageData($rental, $locale, 'info'));
    }

    public function staff(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.staff-index', $this->stageData($rental, $locale, 'staff'));
    }

    public function reservationScene(Request $request, string $rentalSlug): View
    {
        [$rental, $locale] = $this->resolveStage($request, $rentalSlug);

        return view('rental.reservation-index', [
            ...$this->stageData($rental, $locale, 'reservation'),
            'preselectedVehicle' => (string) $request->query('vehicle', ''),
            'prefill' => $this->reservationPrefill($request),
        ]);
    }

    /**
     * Dates and passenger count handed over from the showroom search strip. Only
     * well-formed values survive, so the wizard never gets arbitrary input.
     *
     * @return array{pickup_date: string, return_date: string, passengers: string}
     */
    private function reservationPrefill(Request $request): array
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) === 1 ? (string) $request->query($key) : '';
        $passengers = (int) $request->query('passengers', 0);

        return [
            'pickup_date' => $date('pickup_date'),
            'return_date' => $date('return_date'),
            'passengers' => $passengers >= 1 && $passengers <= 20 ? (string) $passengers : '',
        ];
    }

    /**
     * @return array{0: Rental, 1: string}
     */
    private function resolveStage(Request $request, string $rentalSlug): array
    {
        $rental = Rental::where('slug', $rentalSlug)->firstOrFail();

        abort_if(! $rental->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $rental->default_locale;

        return [$rental, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever scene the customer
     * has walked into.
     *
     * @return array<string, mixed>
     */
    private function stageData(Rental $rental, string $locale, string $scene): array
    {
        $vehicles = $rental->vehicles()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $services = $rental->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['services', 'packages', 'delivery'])
            ->orderBy('sort_order')->get()->values();

        $labels = $this->labels($locale);
        $showroom = $this->showroomLabels($locale);

        return [
            'rental' => $rental,
            'vehicles' => $vehicles,
            'locale' => $locale,
            'scene' => $scene,
            'backdrop' => $this->sceneBackdrop($scene),
            'menuItems' => $this->stageMenu($rental, $locale, $labels, $showroom),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'showroom' => $showroom,
            'site' => $this->siteLabels($locale),
            'vehicleTerms' => self::VEHICLE_TERMS[$locale],
            'wizard' => $this->wizardLabels($locale),
            'narration' => self::NARRATION[$locale],
            'vehicleNarrations' => $this->vehicleNarrations($rental, $vehicles, $locale),
            'staffLinks' => $this->handover->forStaff($rental, $locale),
            'today' => now($rental->timezone)->toDateString(),
            'infoItems' => $rental->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'services' => $services,
            'sceneNarrations' => $this->sceneNarrations($rental, $services, $locale),
        ];
    }

    /**
     * The agent mascot: the pose shown on the page (`character`) and the picture her
     * round avatars crop their face from.
     *
     * @return array{character: string, avatarImage: string, avatarZoom: string, avatarFocus: string}
     */
    private function sceneBackdrop(string $scene): array
    {
        $poses = [
            'showroom' => 'presenting', 'vehicles' => 'presenting', 'vehicle' => 'presenting', 'services' => 'key',
            'service' => 'key', 'info' => 'presenting', 'staff' => 'greeting', 'reservation' => 'tablet',
        ];

        return [
            'character' => asset('images/character/agent-'.($poses[$scene] ?? 'presenting').'.svg'),
            'avatarImage' => asset('images/character/agent-greeting.svg'),
            'avatarZoom' => '236%',
            'avatarFocus' => '50% 6%',
        ];
    }

    /**
     * The main menu. Sections that live on another page are marked so the
     * stage can fade out, as if the agent were walking the customer over.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $showroom
     * @return list<array{key: string, label: string, href: string, exit: bool, tour: ?string, topic: string}>
     */
    private function stageMenu(Rental $rental, string $locale, array $labels, array $showroom): array
    {
        $vehiclesUrl = route('rental.vehicles', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $tour = self::NARRATION[$locale];

        $servicesUrl = route('rental.services', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $infoUrl = route('rental.info', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $reservationUrl = route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale]);
        $staffUrl = route('rental.staff', ['rentalSlug' => $rental->slug, 'lang' => $locale]);

        return [
            ['key' => 'vehicles', 'label' => $labels['vehicles_heading'], 'href' => $vehiclesUrl, 'exit' => true, 'tour' => $tour['tour_vehicles'], 'topic' => $labels['menu_vehicles_q']],
            ['key' => 'services', 'label' => $labels['menu_services'], 'href' => $servicesUrl, 'exit' => true, 'tour' => $tour['tour_services'], 'topic' => $labels['menu_services_q']],
            ['key' => 'info', 'label' => $showroom['menu_info'], 'href' => $infoUrl, 'exit' => true, 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'label' => $showroom['reservation'], 'href' => $reservationUrl, 'exit' => true, 'tour' => $tour['tour_reservation'], 'topic' => $showroom['reservation_q']],
            ['key' => 'staff', 'label' => $labels['menu_staff'], 'href' => $staffUrl, 'exit' => true, 'tour' => $tour['tour_staff'], 'topic' => $labels['menu_staff_q']],
        ];
    }

    /**
     * Intros for the services list and the rental information scene, built
     * only from stored rental data.
     *
     * @param  Collection<int, RentalKnowledgeItem>  $services
     * @return array{services: ?string, info: string}
     */
    private function sceneNarrations(Rental $rental, Collection $services, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($services->isNotEmpty()) {
            $examples = $services->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $services->count(), $examples],
                $templates[$services->count() === 1 ? 'services_one' : 'services']
            );
        }

        $location = collect([$rental->city, $rental->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':rental', $rental->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($rental->open_time) && $time($rental->close_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($rental->open_time), $time($rental->close_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['services' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array{vehicles: ?string, vehicle: array<string, string>}
     */
    private function vehicleNarrations(Rental $rental, Collection $vehicles, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::VEHICLE_TERMS[$locale];
        $money = fn ($value) => $rental->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');

        if ($vehicles->isEmpty()) {
            return ['vehicles' => null, 'vehicle' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $vehicles->count(), $money($vehicles->min('base_price'))],
            $templates[$vehicles->count() === 1 ? 'vehicles_one' : 'vehicles']
        );

        $perVehicle = $vehicles->mapWithKeys(function ($vehicle) use ($templates, $terms, $money, $sentence, $locale) {
            $parts = [$vehicle->translatedName($locale).'.', filled($vehicle->translatedDescription($locale)) ? $sentence(trim($vehicle->translatedDescription($locale))) : null];

            $parts[] = $vehicle->luggage_capacity
                ? str_replace([':seats', ':luggage'], [(string) $vehicle->maxPassengers(), (string) $vehicle->luggage_capacity], $templates['seats_luggage'])
                : str_replace(':seats', (string) $vehicle->maxPassengers(), $templates['seats']);

            $parts[] = $sentence(implode(', ', array_filter([
                $terms['transmission'][$vehicle->transmission] ?? Str::headline((string) $vehicle->transmission),
                $terms['fuel'][$vehicle->fuel_type] ?? Str::headline((string) $vehicle->fuel_type),
            ])));

            if ($vehicle->insurance_included) {
                $parts[] = $templates['insurance'];
            }

            $parts[] = str_replace(':price', $money($vehicle->base_price), $templates['price']);

            return [$vehicle->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['vehicles' => $intro, 'vehicle' => $perVehicle];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to', 'tagline' => 'Your AI rental agent, ready to help you find the right car for the road ahead.',
                'enter' => 'Enter :name', 'empty' => 'The virtual showroom is being prepared. Please come back soon.',
                'loading' => 'Warming up the engine…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'vehicles' => 'Browse the fleet', 'services' => 'See services', 'reservation' => 'Plan a rental', 'staff' => 'Talk to staff',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'tagline' => 'AIレンタカーエージェントが、これからの旅にぴったりの一台をご案内します。',
                'enter' => ':name に入る', 'empty' => 'バーチャルショールームは準備中です。しばらくしてからお越しください。',
                'loading' => 'エンジンを温めています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'vehicles' => '車両を見る', 'services' => 'サービスを見る', 'reservation' => 'レンタルを計画する', 'staff' => 'スタッフに相談',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'tagline' => 'AI Rental Agent siap membantu Anda menemukan mobil yang tepat untuk perjalanan Anda.',
                'enter' => 'Masuk ke :name', 'empty' => 'Showroom virtual sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Memanaskan mesin…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'vehicles' => 'Jelajahi armada', 'services' => 'Lihat layanan', 'reservation' => 'Rencanakan sewa', 'staff' => 'Bicara dengan staf',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(string $locale): array
    {
        return [...ReservationController::MESSAGES[$locale], ...match ($locale) {
            'en' => [
                'title' => 'Rental request', 'intro' => 'A few quick steps. Final availability is confirmed by our team.',
                'step_of' => 'Step :current of :total', 'steps' => ['Dates', 'Passengers', 'Car', 'Your details', 'Summary'],
                'pickup_date' => 'Pick-up date', 'return_date' => 'Return date', 'days' => 'day(s)', 'passengers' => 'Passengers', 'units' => 'Number of cars',
                'choose_vehicle' => 'Choose a car', 'fits' => 'Seats up to :count', 'too_small' => 'Too small for your group',
                'with_driver' => 'Add a driver', 'name' => 'Full name', 'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Special request (optional)', 'special_placeholder' => 'e.g. child seat, airport pick-up at 07:30',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Submit rental request', 'sending' => 'Sending…',
                'checking' => 'Checking availability…', 'estimated_total' => 'Estimated total', 'vehicle' => 'Car', 'dates' => 'Dates', 'driver' => 'Driver',
                'driver_yes' => 'With driver', 'driver_no' => 'Self-drive',
                'contact' => 'Contact', 'disclaimer' => 'This is a rental request. Final availability and rates are confirmed by our staff. No payment is taken now.',
                'available_instead' => 'Available for your dates instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting confirmation from our team', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_rental' => 'Call us', 'email_rental' => 'Email us', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact our staff.',
                'error_generic' => 'Something went wrong. Please try again or contact our staff.', 'select_vehicle' => 'Please choose a car.',
                'contact_staff' => 'Contact our staff',
            ],
            'ja' => [
                'title' => 'レンタルのご予約リクエスト', 'intro' => 'かんたんな手順です。空車状況はスタッフが最終確認いたします。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', '乗車人数', '車両', 'ご連絡先', '確認'],
                'pickup_date' => '受取日', 'return_date' => '返却日', 'days' => '日', 'passengers' => '乗車人数', 'units' => '台数',
                'choose_vehicle' => '車両を選ぶ', 'fits' => '最大:count名乗車', 'too_small' => '人数に対応できません',
                'with_driver' => 'ドライバーを追加', 'name' => 'お名前', 'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => 'ご要望（任意）', 'special_placeholder' => '例：チャイルドシート、7:30に空港で受取',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => 'レンタルリクエストを送信', 'sending' => '送信中…',
                'checking' => '空車を確認中…', 'estimated_total' => '概算合計', 'vehicle' => '車両', 'dates' => '日程', 'driver' => 'ドライバー',
                'driver_yes' => 'ドライバー付き', 'driver_no' => 'セルフドライブ',
                'contact' => 'ご連絡先', 'disclaimer' => 'これはレンタルのリクエストです。空車状況と料金はスタッフが最終確認します。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日程で空いている車両：', 'done_title' => 'リクエストを受け付けました', 'reference' => '予約番号',
                'awaiting' => 'スタッフの確認待ち', 'done_hint' => 'リクエストをスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_rental' => '電話する', 'email_rental' => 'メールする', 'new_request' => '新しいリクエスト',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、スタッフにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、スタッフにご連絡ください。', 'select_vehicle' => '車両を選択してください。',
                'contact_staff' => 'スタッフに連絡',
            ],
            default => [
                'title' => 'Permintaan sewa', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim kami.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Penumpang', 'Mobil', 'Data Anda', 'Ringkasan'],
                'pickup_date' => 'Tanggal ambil', 'return_date' => 'Tanggal kembali', 'days' => 'hari', 'passengers' => 'Penumpang', 'units' => 'Jumlah mobil',
                'choose_vehicle' => 'Pilih mobil', 'fits' => 'Maks. :count penumpang', 'too_small' => 'Tidak cukup untuk rombongan Anda',
                'with_driver' => 'Tambah sopir', 'name' => 'Nama lengkap', 'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Permintaan khusus (opsional)', 'special_placeholder' => 'mis. kursi bayi, jemput di bandara pukul 07.30',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim permintaan sewa', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa ketersediaan…', 'estimated_total' => 'Perkiraan total', 'vehicle' => 'Mobil', 'dates' => 'Tanggal', 'driver' => 'Sopir',
                'driver_yes' => 'Dengan sopir', 'driver_no' => 'Lepas kunci',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah permintaan sewa. Ketersediaan dan tarif final dikonfirmasi oleh staf kami. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Tersedia di tanggal Anda:', 'done_title' => 'Permintaan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi tim kami', 'done_hint' => 'Kirim permintaan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_rental' => 'Telepon kami', 'email_rental' => 'Email kami', 'new_request' => 'Permintaan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi staf kami.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi staf kami.', 'select_vehicle' => 'Silakan pilih mobil.',
                'contact_staff' => 'Hubungi staf kami',
            ],
        }];
    }

    /**
     * Copy for the site shell and the page sections (header, hero, footer, steps, contact).
     *
     * @return array<string, string>
     */
    private function siteLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'skip' => 'Skip to content', 'menu' => 'Menu', 'nav_label' => 'Main', 'noscript' => 'Please enable JavaScript to use the AI Rental Agent.',
                'nav_home' => 'Home', 'nav_fleet' => 'Fleet', 'nav_services' => 'Services', 'nav_info' => 'Rental info', 'nav_reservation' => 'Reservation', 'nav_contact' => 'Contact', 'cta_rent' => 'Rent a car',
                'crumb_home' => 'Home', 'view_all' => 'View all',
                'hero_eyebrow' => 'Car rental in :city', 'hero_title' => 'The right car for your trip,', 'hero_title_em' => 'ready when you are.',
                'hero_text' => 'Browse the fleet, check real availability and daily rates, and send a rental request in minutes — with an AI agent and a human team behind it.',
                'hero_cta_fleet' => 'Browse the fleet', 'hero_cta_agent' => 'Ask the AI agent', 'hero_bubble' => 'Hi! How many people are travelling? I will find a car for you.',
                'badge_agent' => 'AI agent, 24/7', 'badge_price' => 'Transparent daily rates', 'badge_staff' => 'Real staff on standby',
                'fleet_title' => 'Popular cars', 'fleet_sub' => 'A few favourites from our fleet. Rates are per day.',
                'how_title' => 'How renting works', 'how_sub' => 'Three simple steps from choosing a car to getting the keys.',
                'step_1_title' => 'Pick a car and dates', 'step_1_text' => 'Browse the fleet or tell the AI agent your dates and passengers. Availability and the total price are checked live.',
                'step_2_title' => 'Send a rental request', 'step_2_text' => 'Fill in a short form. Nothing is charged now — it is a request, not a payment.',
                'step_3_title' => 'We confirm, you drive', 'step_3_text' => 'Our team confirms by WhatsApp or email, then hands over the keys at the counter or delivers the car to you.',
                'why_title' => 'Why rent with us', 'why_sub' => 'Everything you see about cars, prices and availability comes straight from our own records, never from guesswork.',
                'why_cta' => 'Find me a car',
                'why_1_title' => 'Live availability', 'why_1_text' => 'Prices and free cars are checked for your exact dates before anything is quoted.',
                'why_2_title' => 'AI agent, always on', 'why_2_text' => 'Ask in Indonesian, English or Japanese at any hour and get an answer from our approved information.',
                'why_3_title' => 'Humans when it matters', 'why_3_text' => 'Special requests, complaints and group trips are handed to our staff with the full context.',
                'why_4_title' => 'No payment upfront', 'why_4_text' => 'Sending a request costs nothing. Our team confirms the details before any payment.',
                'services_title' => 'Services and packages', 'services_sub' => 'Drivers, delivery, extras and more to make the trip easier.',
                'cta_title' => 'Not sure which car fits?', 'cta_text' => 'Tell our AI Rental Agent how many people are travelling and for how long. It will suggest the cars that really fit.', 'cta_button' => 'Chat with the AI agent',
                'page_fleet_lead' => 'Every car in our fleet, with seats, luggage space and daily rates.',
                'page_services_lead' => 'Services, packages and delivery options that come with your rental.',
                'page_info_lead' => 'Rental requirements, policies, opening hours and answers to common questions.',
                'page_contact_lead' => 'Talk to our AI agent any time, or reach the team directly.',
                'page_reserve_lead' => 'A few quick steps. Final availability is confirmed by our team.',
                'related_title' => 'More cars you may like', 'booking_title' => 'Rent this car',
                'booking_hint' => 'Choose your dates in the next step. The total price is calculated from live availability.',
                'reserve_aside_title' => 'What happens next', 'reserve_1' => 'We check availability for your exact dates.', 'reserve_2' => 'Our team confirms by WhatsApp or email.', 'reserve_3' => 'You collect the car or we deliver it. Payment is arranged then.', 'reserve_help' => 'Need help? Ask the agent',
                'contact_title' => 'Contact us', 'contact_chat' => 'Chat with the AI agent', 'contact_visit' => 'Visit our counter',
                'footer_links' => 'Explore', 'footer_contact' => 'Contact', 'footer_hours' => 'Counter hours',
            ],
            'ja' => [
                'skip' => '本文へスキップ', 'menu' => 'メニュー', 'nav_label' => 'メイン', 'noscript' => 'AIレンタカーエージェントを使うには、JavaScriptを有効にしてください。',
                'nav_home' => 'ホーム', 'nav_fleet' => '車両', 'nav_services' => 'サービス', 'nav_info' => 'レンタル情報', 'nav_reservation' => 'ご予約', 'nav_contact' => 'お問い合わせ', 'cta_rent' => '車を借りる',
                'crumb_home' => 'ホーム', 'view_all' => 'すべて見る',
                'hero_eyebrow' => ':cityのレンタカー', 'hero_title' => '旅にぴったりの一台を、', 'hero_title_em' => 'すぐにご用意。',
                'hero_text' => '車両を選び、空車状況と日額料金をその場で確認して、数分でレンタルのリクエストを送れます。AIエージェントとスタッフがサポートします。',
                'hero_cta_fleet' => '車両を見る', 'hero_cta_agent' => 'AIエージェントに聞く', 'hero_bubble' => 'こんにちは！何名でご利用ですか？ぴったりの車をお探しします。',
                'badge_agent' => 'AIエージェント24時間対応', 'badge_price' => '明瞭な日額料金', 'badge_staff' => 'スタッフも待機',
                'fleet_title' => '人気の車両', 'fleet_sub' => '当社の人気車種をご紹介します。料金は1日あたりです。',
                'how_title' => 'ご利用の流れ', 'how_sub' => '車選びから鍵の受け取りまで、3つのステップです。',
                'step_1_title' => '車と日程を選ぶ', 'step_1_text' => '車両を見るか、日程と人数をAIエージェントに伝えてください。空車状況と合計金額をリアルタイムで確認します。',
                'step_2_title' => 'リクエストを送る', 'step_2_text' => '短いフォームに入力するだけ。この時点でお支払いは発生しません。',
                'step_3_title' => '確認して出発', 'step_3_text' => 'スタッフがWhatsAppまたはメールで確認し、カウンターでの受け渡しまたはお届けを行います。',
                'why_title' => '選ばれる理由', 'why_sub' => '車両、料金、空車状況はすべて当社の記録に基づいており、推測では答えません。',
                'why_cta' => '車を探してもらう',
                'why_1_title' => 'リアルタイムの空車確認', 'why_1_text' => 'ご希望の日程で空車と料金を確認してからご案内します。',
                'why_2_title' => '24時間のAIエージェント', 'why_2_text' => '日本語・英語・インドネシア語で、いつでも承認済みの情報からお答えします。',
                'why_3_title' => '大切な場面は人が対応', 'why_3_text' => '特別なご要望、苦情、団体利用は、状況を共有したうえでスタッフが対応します。',
                'why_4_title' => '前払い不要', 'why_4_text' => 'リクエストの送信は無料です。お支払いは、内容を確認してからのご案内となります。',
                'services_title' => 'サービスとプラン', 'services_sub' => 'ドライバー、お届け、オプションなど、旅をより楽にするサービスです。',
                'cta_title' => 'どの車が合うか迷ったら', 'cta_text' => '人数と日数をAIレンタカーエージェントに伝えてください。本当に合う車をご提案します。', 'cta_button' => 'AIエージェントに相談',
                'page_fleet_lead' => '当社の全車両を、定員・荷物量・日額料金とともにご覧いただけます。',
                'page_services_lead' => 'レンタルに付いてくるサービス、プラン、お届けオプションです。',
                'page_info_lead' => 'ご利用条件、規定、受付時間、よくあるご質問をまとめています。',
                'page_contact_lead' => 'AIエージェントにいつでも相談でき、スタッフにも直接ご連絡いただけます。',
                'page_reserve_lead' => 'かんたんな手順です。空車状況はスタッフが最終確認いたします。',
                'related_title' => 'こちらの車両もおすすめ', 'booking_title' => 'この車を借りる',
                'booking_hint' => '日程は次のステップで選びます。合計金額はリアルタイムの空車状況から計算されます。',
                'reserve_aside_title' => 'このあとの流れ', 'reserve_1' => 'ご希望の日程で空車を確認します。', 'reserve_2' => 'スタッフがWhatsAppまたはメールで確認します。', 'reserve_3' => 'お受け取りまたはお届け。お支払いはその際にご案内します。', 'reserve_help' => 'ご不明ならエージェントへ',
                'contact_title' => 'お問い合わせ', 'contact_chat' => 'AIエージェントに相談', 'contact_visit' => 'カウンターへのご来店',
                'footer_links' => 'メニュー', 'footer_contact' => 'お問い合わせ', 'footer_hours' => '受付時間',
            ],
            default => [
                'skip' => 'Langsung ke konten', 'menu' => 'Menu', 'nav_label' => 'Utama', 'noscript' => 'Aktifkan JavaScript untuk menggunakan AI Rental Agent.',
                'nav_home' => 'Beranda', 'nav_fleet' => 'Armada', 'nav_services' => 'Layanan', 'nav_info' => 'Info sewa', 'nav_reservation' => 'Reservasi', 'nav_contact' => 'Kontak', 'cta_rent' => 'Sewa mobil',
                'crumb_home' => 'Beranda', 'view_all' => 'Lihat semua',
                'hero_eyebrow' => 'Rental mobil di :city', 'hero_title' => 'Mobil yang tepat untuk perjalanan Anda,', 'hero_title_em' => 'siap saat Anda butuh.',
                'hero_text' => 'Lihat armada, cek ketersediaan dan tarif harian yang nyata, lalu kirim permintaan sewa dalam hitungan menit — dibantu agen AI dan tim kami.',
                'hero_cta_fleet' => 'Lihat armada', 'hero_cta_agent' => 'Tanya agen AI', 'hero_bubble' => 'Halo! Berapa orang yang ikut? Saya carikan mobil yang pas.',
                'badge_agent' => 'Agen AI 24 jam', 'badge_price' => 'Tarif harian transparan', 'badge_staff' => 'Staf siaga membantu',
                'fleet_title' => 'Mobil favorit', 'fleet_sub' => 'Beberapa pilihan favorit dari armada kami. Tarif per hari.',
                'how_title' => 'Cara menyewa', 'how_sub' => 'Tiga langkah mudah dari memilih mobil sampai menerima kunci.',
                'step_1_title' => 'Pilih mobil dan tanggal', 'step_1_text' => 'Lihat armada atau sebutkan tanggal dan jumlah penumpang kepada agen AI. Ketersediaan dan total harga dicek langsung.',
                'step_2_title' => 'Kirim permintaan sewa', 'step_2_text' => 'Isi formulir singkat. Belum ada pembayaran — ini permintaan, bukan transaksi.',
                'step_3_title' => 'Kami konfirmasi, Anda berangkat', 'step_3_text' => 'Tim kami mengonfirmasi lewat WhatsApp atau email, lalu menyerahkan kunci di konter atau mengantar mobil ke Anda.',
                'why_title' => 'Kenapa menyewa di sini', 'why_sub' => 'Semua informasi mobil, harga, dan ketersediaan berasal langsung dari data kami, bukan tebakan.',
                'why_cta' => 'Carikan mobil untuk saya',
                'why_1_title' => 'Ketersediaan langsung', 'why_1_text' => 'Harga dan mobil yang kosong dicek untuk tanggal Anda sebelum ada penawaran.',
                'why_2_title' => 'Agen AI selalu siaga', 'why_2_text' => 'Tanya dalam bahasa Indonesia, Inggris, atau Jepang kapan saja dan dapat jawaban dari informasi resmi kami.',
                'why_3_title' => 'Manusia saat dibutuhkan', 'why_3_text' => 'Permintaan khusus, keluhan, dan perjalanan rombongan diteruskan ke staf lengkap dengan konteksnya.',
                'why_4_title' => 'Tanpa bayar di muka', 'why_4_text' => 'Mengirim permintaan gratis. Tim kami mengonfirmasi detailnya sebelum ada pembayaran.',
                'services_title' => 'Layanan dan paket', 'services_sub' => 'Sopir, pengantaran, perlengkapan tambahan, dan lainnya agar perjalanan lebih mudah.',
                'cta_title' => 'Bingung mobil mana yang cocok?', 'cta_text' => 'Ceritakan ke AI Rental Agent berapa orang dan berapa hari. Ia akan menyarankan mobil yang benar-benar muat.', 'cta_button' => 'Chat dengan agen AI',
                'page_fleet_lead' => 'Semua mobil di armada kami, lengkap dengan kursi, bagasi, dan tarif harian.',
                'page_services_lead' => 'Layanan, paket, dan opsi pengantaran yang menyertai sewa Anda.',
                'page_info_lead' => 'Syarat sewa, kebijakan, jam konter, dan jawaban untuk pertanyaan umum.',
                'page_contact_lead' => 'Ngobrol dengan agen AI kapan saja, atau hubungi tim kami langsung.',
                'page_reserve_lead' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim kami.',
                'related_title' => 'Mobil lain yang mungkin cocok', 'booking_title' => 'Sewa mobil ini',
                'booking_hint' => 'Pilih tanggal di langkah berikutnya. Total harga dihitung dari ketersediaan langsung.',
                'reserve_aside_title' => 'Apa yang terjadi selanjutnya', 'reserve_1' => 'Kami cek ketersediaan untuk tanggal Anda.', 'reserve_2' => 'Tim kami konfirmasi lewat WhatsApp atau email.', 'reserve_3' => 'Anda ambil mobil atau kami antar. Pembayaran diatur saat itu.', 'reserve_help' => 'Butuh bantuan? Tanya agen',
                'contact_title' => 'Hubungi kami', 'contact_chat' => 'Chat dengan agen AI', 'contact_visit' => 'Kunjungi konter kami',
                'footer_links' => 'Jelajahi', 'footer_contact' => 'Kontak', 'footer_hours' => 'Jam konter',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function showroomLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to your', 'showroom' => 'virtual showroom',
                'intro' => 'The right car, ready when you are. Let me take care of the details.',
                'home' => 'Showroom', 'explore' => 'Explore our rental', 'reservation' => 'Reservations',
                'search_title' => 'Find your car', 'search_cta' => 'Search cars', 'cat_services' => 'Service', 'cat_packages' => 'Package', 'cat_delivery' => 'Delivery', 'filter_all' => 'All', 'fleet_count' => ':count cars', 'details' => 'Details', 'rent' => 'Rent', 'per_day_short' => 'day',
                'reservation_intro' => 'Tell your AI Rental Agent your dates and number of passengers. We will help you find a car and submit a rental request.',
                'reservation_q' => 'I would like to rent a car. Please help me check availability.',
                'start_booking' => 'Plan my rental', 'available' => 'Here to help, 24/7',
                'assistant' => 'Your virtual rental agent', 'illustration' => 'AI illustration',
                'staff_intro' => 'Need a personal touch? Send a message and we will connect you with our rental team.',
                'empty' => 'Ask your AI Rental Agent for more information.', 'back' => 'Back to showroom',
                'hours_open' => 'Counter opens', 'hours_close' => 'Counter closes', 'location' => 'Find us',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'vehicles_empty' => 'Our fleet information will be available soon. Please ask our team.',
                'menu_info' => 'Rental information', 'info_about' => 'About us', 'info_policies' => 'Rental terms', 'info_faq' => 'Frequently asked questions', 'info_tab_about' => 'About', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Address', 'info_hours' => 'Counter hours', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about renting',
                'service_counter' => 'Service', 'prev_service' => 'Previous', 'next_service' => 'Next', 'ask_service' => 'Ask about this service', 'back_services' => 'All services', 'open_service' => 'Read more', 'ask_service_q' => 'Tell me more about :name.',
                'loading' => 'Warming up the engine…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'vehicle_scene' => 'Car details', 'vehicle_counter' => 'Car', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_vehicle' => 'Previous car', 'next_vehicle' => 'Next car',
                'ask_vehicle' => 'Ask about this car', 'reserve_vehicle' => 'Request rental', 'insurance_excluded' => 'Not included',
                'spec_title' => 'Specifications', 'spec_year' => 'Model year', 'spec_transmission' => 'Transmission', 'spec_fuel' => 'Fuel', 'spec_seats' => 'Seats', 'spec_luggage' => 'Luggage', 'spec_body' => 'Body type', 'spec_insurance' => 'Basic insurance', 'spec_driver' => 'Driver',
                'luggage_unit' => 'bags', 'driver_yes' => 'Available', 'driver_no' => 'Self-drive only', 'features' => 'Features',
                'availability_note' => 'Final availability and rates are confirmed by our staff.',
                'ask_vehicle_q' => 'Tell me more about the :name.',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'showroom' => 'バーチャルショールーム',
                'intro' => '最適な一台を、すぐにご用意します。細かな手続きはお任せください。',
                'home' => 'ショールーム', 'explore' => 'レンタルのご案内', 'reservation' => 'ご予約',
                'search_title' => '車を探す', 'search_cta' => '空車を検索', 'cat_services' => 'サービス', 'cat_packages' => 'プラン', 'cat_delivery' => 'お届け', 'filter_all' => 'すべて', 'fleet_count' => ':count台', 'details' => '詳細', 'rent' => '借りる', 'per_day_short' => '日',
                'reservation_intro' => 'ご希望の日程と乗車人数をAIレンタカーエージェントにお伝えください。空車確認とレンタルリクエストをお手伝いします。',
                'reservation_q' => 'レンタカーを借りたいです。空車を確認してください。',
                'start_booking' => 'レンタルを計画する', 'available' => '24時間お手伝いします',
                'assistant' => 'バーチャルレンタカーエージェント', 'illustration' => 'AIイラスト',
                'staff_intro' => 'スタッフがお手伝いします。メッセージを送ってご相談ください。',
                'empty' => '詳しくはAIレンタカーエージェントにお尋ねください。', 'back' => 'ショールームに戻る',
                'hours_open' => '受付開始', 'hours_close' => '受付終了', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'vehicles_empty' => '車両情報は準備中です。スタッフにお尋ねください。',
                'menu_info' => 'レンタル情報', 'info_about' => '会社について', 'info_policies' => 'ご利用条件', 'info_faq' => 'よくあるご質問', 'info_tab_about' => '会社について', 'info_tab_faq' => 'FAQ',
                'info_address' => '所在地', 'info_hours' => '受付時間', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => 'レンタルについて聞く',
                'service_counter' => 'サービス', 'prev_service' => '前へ', 'next_service' => '次へ', 'ask_service' => 'このサービスについて聞く', 'back_services' => 'サービス一覧', 'open_service' => '詳しく見る', 'ask_service_q' => ':name について詳しく教えてください。',
                'loading' => 'エンジンを温めています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'vehicle_scene' => '車両のご案内', 'vehicle_counter' => '車両', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_vehicle' => '前の車両', 'next_vehicle' => '次の車両',
                'ask_vehicle' => 'この車両について聞く', 'reserve_vehicle' => 'レンタルをリクエスト', 'insurance_excluded' => '含まれません',
                'spec_title' => '仕様', 'spec_year' => '年式', 'spec_transmission' => 'トランスミッション', 'spec_fuel' => '燃料', 'spec_seats' => '乗車定員', 'spec_luggage' => '荷物', 'spec_body' => 'ボディタイプ', 'spec_insurance' => '基本保険', 'spec_driver' => 'ドライバー',
                'luggage_unit' => '個', 'driver_yes' => '手配可', 'driver_no' => 'セルフドライブのみ', 'features' => '装備',
                'availability_note' => '空車状況と料金は、スタッフが最終確認いたします。',
                'ask_vehicle_q' => ':name について詳しく教えてください。',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'showroom' => 'showroom virtual Anda',
                'intro' => 'Mobil yang tepat, siap saat Anda butuh. Biarkan saya mengurus detailnya.',
                'home' => 'Beranda', 'explore' => 'Jelajahi rental kami', 'reservation' => 'Reservasi',
                'search_title' => 'Cari mobil Anda', 'search_cta' => 'Cari mobil', 'cat_services' => 'Layanan', 'cat_packages' => 'Paket', 'cat_delivery' => 'Pengantaran', 'filter_all' => 'Semua', 'fleet_count' => ':count mobil', 'details' => 'Detail', 'rent' => 'Sewa', 'per_day_short' => 'hari',
                'reservation_intro' => 'Ceritakan tanggal sewa dan jumlah penumpang kepada AI Rental Agent. Kami bantu cek ketersediaan mobil hingga pengajuan sewa.',
                'reservation_q' => 'Saya ingin menyewa mobil. Bantu saya cek ketersediaan.',
                'start_booking' => 'Rencanakan sewa', 'available' => 'Siap membantu, 24 jam',
                'assistant' => 'Agen rental virtual Anda', 'illustration' => 'Ilustrasi AI',
                'staff_intro' => 'Butuh bantuan langsung? Kirim pesan untuk terhubung dengan tim rental kami.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Rental Agent.', 'back' => 'Kembali ke showroom',
                'hours_open' => 'Konter buka', 'hours_close' => 'Konter tutup', 'location' => 'Lokasi kami',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'vehicles_empty' => 'Informasi armada segera tersedia. Silakan tanyakan kepada staf kami.',
                'menu_info' => 'Informasi rental', 'info_about' => 'Tentang kami', 'info_policies' => 'Syarat sewa', 'info_faq' => 'Pertanyaan yang sering diajukan', 'info_tab_about' => 'Tentang', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Alamat', 'info_hours' => 'Jam konter', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya soal sewa',
                'service_counter' => 'Layanan', 'prev_service' => 'Sebelumnya', 'next_service' => 'Berikutnya', 'ask_service' => 'Tanya tentang layanan ini', 'back_services' => 'Semua layanan', 'open_service' => 'Selengkapnya', 'ask_service_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Memanaskan mesin…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'vehicle_scene' => 'Detail mobil', 'vehicle_counter' => 'Mobil', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_vehicle' => 'Mobil sebelumnya', 'next_vehicle' => 'Mobil berikutnya',
                'ask_vehicle' => 'Tanya tentang mobil ini', 'reserve_vehicle' => 'Ajukan sewa', 'insurance_excluded' => 'Tidak termasuk',
                'spec_title' => 'Spesifikasi', 'spec_year' => 'Tahun', 'spec_transmission' => 'Transmisi', 'spec_fuel' => 'Bahan bakar', 'spec_seats' => 'Kursi', 'spec_luggage' => 'Bagasi', 'spec_body' => 'Tipe bodi', 'spec_insurance' => 'Asuransi dasar', 'spec_driver' => 'Sopir',
                'luggage_unit' => 'koper', 'driver_yes' => 'Tersedia', 'driver_no' => 'Lepas kunci saja', 'features' => 'Fitur',
                'availability_note' => 'Ketersediaan dan tarif final dikonfirmasi oleh staf kami.',
                'ask_vehicle_q' => 'Ceritakan lebih banyak tentang :name.',
            ],
        };
    }

    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'per_day' => '/ day',
                'ask_ai' => 'Ask the AI Rental Agent',
                'vehicles_heading' => 'Our Fleet',
                'chat_heading' => 'AI Rental Agent',
                'chat_subtitle' => 'Ask about cars, services, or your rental — available 24/7.',
                'chat_placeholder' => 'Type your message…',
                'chat_send' => 'Send',
                'chat_open' => 'Chat with the AI Rental Agent',
                'chat_close' => 'Close conversation',
                'chat_intro' => "Hi! I'm the AI Rental Agent here. Ask me about cars, services, or anything about your rental.",
                'insurance_included' => 'Insurance included',
                'max_passengers' => 'seats',
                'chat_no_insurance' => 'Insurance not included',
                'chat_day' => 'day(s)',
                'chat_no_availability' => 'No availability for those dates.',
                'chat_booking_received' => 'Rental request received',
                'chat_reference' => 'Ref',
                'handed_over' => 'A staff member has joined this conversation and will reply shortly.',
                'chat_status_sent' => 'Message sent',
                'chat_status_waiting' => 'Waiting for staff reply',
                'chat_status_replied' => 'Staff has replied',
                'chat_draft_title' => 'Unsaved message',
                'chat_draft_body' => 'This message has not been sent. Keep it as a draft?',
                'chat_draft_keep' => 'Keep typing',
                'chat_draft_discard' => 'Discard draft',
                'thinking' => 'Thinking…',
                'chat_error' => "I'm having trouble responding right now. Please try again or contact our staff.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
                'view_details' => 'View details',
                'vehicle_details_question' => 'Show me more details and photos of :vehicle',
                'book_now' => 'Rent this car',
                'menu_heading' => 'Start here',
                'menu_vehicles' => 'Find a car',
                'menu_vehicles_q' => 'I would like to see the available cars.',
                'menu_services' => 'Rental services',
                'menu_services_q' => 'What services and packages do you offer?',
                'menu_policies' => 'Terms & FAQ',
                'menu_policies_q' => 'What are the rental requirements, deposit, and cancellation policies?',
                'menu_staff' => 'Talk to staff',
                'menu_staff_q' => 'I would like to speak with the rental team.',
            ],
            'ja' => [
                'from' => '',
                'per_day' => '〜 / 日',
                'ask_ai' => 'AIレンタカーエージェントに聞く',
                'vehicles_heading' => '車両ラインナップ',
                'chat_heading' => 'AIレンタカーエージェント',
                'chat_subtitle' => '車両・サービス・レンタルについて24時間いつでもお尋ねください。',
                'chat_placeholder' => 'メッセージを入力…',
                'chat_send' => '送信',
                'chat_open' => 'AIエージェントに相談',
                'chat_close' => '会話を閉じる',
                'chat_intro' => 'こんにちは。AIレンタカーエージェントです。車両やサービス、レンタルについて何でもお尋ねください。',
                'insurance_included' => '保険付き',
                'max_passengers' => '名乗り',
                'chat_no_insurance' => '保険は含まれません',
                'chat_day' => '日',
                'chat_no_availability' => 'ご希望の日程には空車がありません。',
                'chat_booking_received' => 'レンタルリクエストを受け付けました',
                'chat_reference' => '受付番号',
                'handed_over' => 'スタッフがこの会話に参加しました。まもなく返信いたします。',
                'chat_status_sent' => 'メッセージを送信しました',
                'chat_status_waiting' => 'スタッフの返信を待っています',
                'chat_status_replied' => 'スタッフが返信しました',
                'chat_draft_title' => '未送信のメッセージ',
                'chat_draft_body' => 'このメッセージはまだ送信されていません。下書きとして残しますか？',
                'chat_draft_keep' => '入力を続ける',
                'chat_draft_discard' => '下書きを破棄',
                'thinking' => '入力中…',
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、スタッフにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
                'view_details' => '詳細を見る',
                'vehicle_details_question' => ':vehicle の詳細と写真を見せてください',
                'book_now' => 'この車を借りる',
                'menu_heading' => 'ここから始める',
                'menu_vehicles' => '車を探す',
                'menu_vehicles_q' => '空いている車を見せてください。',
                'menu_services' => 'レンタルサービス',
                'menu_services_q' => 'どんなサービスやプランがありますか？',
                'menu_policies' => '条件・よくある質問',
                'menu_policies_q' => '利用条件、保証金、キャンセルのポリシーを教えてください。',
                'menu_staff' => 'スタッフに相談',
                'menu_staff_q' => 'スタッフと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'per_day' => '/ hari',
                'ask_ai' => 'Tanya AI Rental Agent',
                'vehicles_heading' => 'Armada Kami',
                'chat_heading' => 'AI Rental Agent',
                'chat_subtitle' => 'Tanyakan mobil, layanan, atau rencana sewa Anda — siap 24 jam.',
                'chat_placeholder' => 'Tulis pesan Anda…',
                'chat_send' => 'Kirim',
                'chat_open' => 'Chat dengan AI Rental Agent',
                'chat_close' => 'Tutup percakapan',
                'chat_intro' => 'Halo! Saya AI Rental Agent di sini. Tanyakan apa saja soal mobil, layanan, atau rencana sewa Anda.',
                'insurance_included' => 'Termasuk asuransi',
                'max_passengers' => 'kursi',
                'chat_no_insurance' => 'Tanpa asuransi',
                'chat_day' => 'hari',
                'chat_no_availability' => 'Tidak ada mobil tersedia untuk tanggal tersebut.',
                'chat_booking_received' => 'Permintaan sewa diterima',
                'chat_reference' => 'Ref',
                'handed_over' => 'Staf kami telah bergabung dalam percakapan ini dan akan segera membalas.',
                'chat_status_sent' => 'Pesan terkirim',
                'chat_status_waiting' => 'Menunggu balasan staf',
                'chat_status_replied' => 'Staf telah membalas',
                'chat_draft_title' => 'Pesan belum dikirim',
                'chat_draft_body' => 'Pesan ini belum dikirim. Simpan sebagai draft?',
                'chat_draft_keep' => 'Lanjut mengetik',
                'chat_draft_discard' => 'Buang draft',
                'thinking' => 'Sedang mengetik…',
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi staf kami.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
                'view_details' => 'Lihat detail',
                'vehicle_details_question' => 'Tunjukkan detail dan foto lengkap :vehicle',
                'book_now' => 'Sewa mobil ini',
                'menu_heading' => 'Mulai dari sini',
                'menu_vehicles' => 'Cari mobil',
                'menu_vehicles_q' => 'Saya ingin melihat pilihan mobil yang tersedia.',
                'menu_services' => 'Layanan rental',
                'menu_services_q' => 'Apa saja layanan dan paket yang tersedia?',
                'menu_policies' => 'Syarat & FAQ',
                'menu_policies_q' => 'Apa syarat sewa, deposit, dan kebijakan pembatalan?',
                'menu_staff' => 'Bicara dengan staf',
                'menu_staff_q' => 'Saya ingin bicara dengan tim rental.',
            ],
        };
    }
}
