<?php

namespace Database\Seeders;

use App\Models\Rental;
use App\Models\RentalKnowledgeItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoRentalSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@ftscarrental.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $rental = Rental::firstOrCreate(
            ['name' => 'FTS Car Rental AI'],
            [
                'slug' => Rental::generateUniqueSlug('FTS Car Rental AI'),
                'description' => 'Rental mobil di Bali dengan pengantaran gratis ke bandara dan hotel, pilihan lepas kunci atau dengan sopir, serta armada terawat yang siap jalan.',
                'translations' => [
                    'id' => [
                        'description' => 'Rental mobil di Bali dengan pengantaran gratis ke bandara dan hotel, pilihan lepas kunci atau dengan sopir, serta armada terawat yang siap jalan.',
                    ],
                    'en' => [
                        'description' => 'A Bali car rental with free airport and hotel delivery, self-drive or with a driver, and a well-maintained fleet that is ready to go.',
                    ],
                    'ja' => [
                        'description' => 'バリ島のレンタカー会社です。空港・ホテルへの無料送迎、セルフドライブとドライバー付きの両方に対応し、整備の行き届いた車両をご用意しています。',
                    ],
                ],
                'address' => 'Jl. Bypass Ngurah Rai No. 88, Sanur',
                'city' => 'Denpasar, Bali',
                'country' => 'Indonesia',
                'latitude' => -8.6905,
                'longitude' => 115.2430,
                'phone' => '+62 361 765432',
                'whatsapp' => '6281234567890',
                'email' => 'reservation@ftscarrental.test',
                'timezone' => 'Asia/Makassar',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'open_time' => '07:00',
                'close_time' => '21:00',
                'cover_path' => '/images/scenes/showroom.webp',
                'public_status' => 'published',
            ]
        );

        $rental->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        $sort = 0;
        foreach ($this->vehicleDefinitions() as $definition) {
            $images = $definition['images'];
            $totalUnits = $definition['total_units'];
            unset($definition['images'], $definition['total_units']);

            $vehicle = $rental->vehicles()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort++, 'is_active' => true]
            );

            $imageSort = 0;
            foreach ($images as $image) {
                $vehicle->images()->updateOrCreate(
                    ['image_url' => $image['url']],
                    [
                        'tags' => $image['tags'],
                        'alt_text' => $image['alt'],
                        'sort_order' => $imageSort++,
                    ]
                );
            }

            $this->seedInventory($vehicle, $totalUnits);
        }

        $this->seedKnowledgeBase($rental);

        $this->command?->info('Demo login: owner@ftscarrental.test — password: password');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vehicleDefinitions(): array
    {
        return [
            [
                'slug' => 'toyota-agya',
                'name' => 'Toyota Agya',
                'description' => 'Hatchback irit dan lincah, pas untuk keliling kota dan parkir di gang sempit. Cocok untuk 1–2 orang atau pasangan.',
                'translations' => [
                    'id' => ['name' => 'Toyota Agya', 'description' => 'Hatchback irit dan lincah, pas untuk keliling kota dan parkir di gang sempit. Cocok untuk 1–2 orang atau pasangan.'],
                    'en' => ['name' => 'Toyota Agya', 'description' => 'A fuel-efficient, nimble hatchback that is perfect for city driving and tight parking. Great for solo travellers or couples.'],
                    'ja' => ['name' => 'トヨタ アギヤ', 'description' => '燃費がよく小回りのきくハッチバックで、街乗りや狭い駐車場に最適です。お一人様やカップルにおすすめです。'],
                ],
                'model_year' => 2023,
                'seats' => 5,
                'luggage_capacity' => 2,
                'transmission' => 'automatic',
                'fuel_type' => 'petrol',
                'body_type' => 'hatchback',
                'insurance_included' => false,
                'driver_available' => true,
                'driver_price' => 300000,
                'base_price' => 350000,
                'features' => ['air_conditioning', 'bluetooth', 'usb_charging', 'rear_camera'],
                'total_units' => 8,
                'images' => [
                    ['url' => '/images/vehicles/toyota-agya-1.webp', 'tags' => ['exterior'], 'alt' => 'Toyota Agya tampak samping'],
                    ['url' => '/images/vehicles/toyota-agya-2.webp', 'tags' => ['exterior', 'road'], 'alt' => 'Toyota Agya di jalan'],
                ],
            ],
            [
                'slug' => 'toyota-avanza',
                'name' => 'Toyota Avanza',
                'description' => 'MPV keluarga yang lega dengan 7 kursi dan bagasi luas, andalan untuk liburan keluarga di Bali.',
                'translations' => [
                    'id' => ['name' => 'Toyota Avanza', 'description' => 'MPV keluarga yang lega dengan 7 kursi dan bagasi luas, andalan untuk liburan keluarga di Bali.'],
                    'en' => ['name' => 'Toyota Avanza', 'description' => 'A roomy family MPV with 7 seats and generous luggage space, the go-to choice for a family holiday in Bali.'],
                    'ja' => ['name' => 'トヨタ アバンザ', 'description' => '7人乗りで荷室も広いファミリー向けミニバン。バリ島での家族旅行の定番です。'],
                ],
                'model_year' => 2023,
                'seats' => 7,
                'luggage_capacity' => 3,
                'transmission' => 'automatic',
                'fuel_type' => 'petrol',
                'body_type' => 'mpv',
                'insurance_included' => false,
                'driver_available' => true,
                'driver_price' => 350000,
                'base_price' => 450000,
                'features' => ['air_conditioning', 'bluetooth', 'usb_charging', 'child_seat', 'third_row'],
                'total_units' => 10,
                'images' => [
                    ['url' => '/images/vehicles/toyota-avanza-1.webp', 'tags' => ['exterior'], 'alt' => 'Toyota Avanza tampak samping'],
                    ['url' => '/images/vehicles/toyota-avanza-2.webp', 'tags' => ['exterior', 'road'], 'alt' => 'Toyota Avanza di jalan'],
                ],
            ],
            [
                'slug' => 'honda-hr-v',
                'name' => 'Honda HR-V',
                'description' => 'SUV kompak yang stylish dengan posisi duduk tinggi dan kabin nyaman, nyaman untuk jalan menanjak ke Kintamani maupun Ubud.',
                'translations' => [
                    'id' => ['name' => 'Honda HR-V', 'description' => 'SUV kompak yang stylish dengan posisi duduk tinggi dan kabin nyaman, nyaman untuk jalan menanjak ke Kintamani maupun Ubud.'],
                    'en' => ['name' => 'Honda HR-V', 'description' => 'A stylish compact SUV with a high seating position and a comfortable cabin, great for the hill roads to Kintamani and Ubud.'],
                    'ja' => ['name' => 'ホンダ HR-V', 'description' => '視点が高く快適なキャビンを備えたスタイリッシュなコンパクトSUV。キンタマーニやウブドの山道にも最適です。'],
                ],
                'model_year' => 2023,
                'seats' => 5,
                'luggage_capacity' => 3,
                'transmission' => 'automatic',
                'fuel_type' => 'petrol',
                'body_type' => 'suv',
                'insurance_included' => true,
                'driver_available' => true,
                'driver_price' => 350000,
                'base_price' => 750000,
                'features' => ['air_conditioning', 'bluetooth', 'gps', 'usb_charging', 'sunroof', 'rear_camera', 'cruise_control'],
                'total_units' => 5,
                'images' => [
                    ['url' => '/images/vehicles/honda-hr-v-1.webp', 'tags' => ['exterior'], 'alt' => 'Honda HR-V tampak samping'],
                    ['url' => '/images/vehicles/honda-hr-v-2.webp', 'tags' => ['exterior', 'road'], 'alt' => 'Honda HR-V di jalan'],
                ],
            ],
            [
                'slug' => 'toyota-innova-zenix-hybrid',
                'name' => 'Toyota Innova Zenix Hybrid',
                'description' => 'MPV premium berteknologi hybrid yang senyap dan irit, dengan kabin luas untuk 7 penumpang dan bagasi besar.',
                'translations' => [
                    'id' => ['name' => 'Toyota Innova Zenix Hybrid', 'description' => 'MPV premium berteknologi hybrid yang senyap dan irit, dengan kabin luas untuk 7 penumpang dan bagasi besar.'],
                    'en' => ['name' => 'Toyota Innova Zenix Hybrid', 'description' => 'A premium hybrid MPV that is quiet and fuel-efficient, with a spacious cabin for 7 passengers and a large boot.'],
                    'ja' => ['name' => 'トヨタ イノーバ ゼニックス ハイブリッド', 'description' => '静かで燃費に優れたプレミアムハイブリッドミニバン。7名が快適に乗れる広い室内と大きなラゲッジを備えています。'],
                ],
                'model_year' => 2024,
                'seats' => 7,
                'luggage_capacity' => 4,
                'transmission' => 'automatic',
                'fuel_type' => 'hybrid',
                'body_type' => 'mpv',
                'insurance_included' => true,
                'driver_available' => true,
                'driver_price' => 400000,
                'base_price' => 950000,
                'features' => ['air_conditioning', 'bluetooth', 'gps', 'usb_charging', 'sunroof', 'rear_camera', 'cruise_control', 'leather_seats', 'third_row'],
                'total_units' => 4,
                'images' => [
                    ['url' => '/images/vehicles/toyota-innova-zenix-hybrid-1.webp', 'tags' => ['exterior'], 'alt' => 'Toyota Innova Zenix Hybrid tampak samping'],
                    ['url' => '/images/vehicles/toyota-innova-zenix-hybrid-2.webp', 'tags' => ['exterior', 'road'], 'alt' => 'Toyota Innova Zenix Hybrid di jalan'],
                ],
            ],
            [
                'slug' => 'mercedes-benz-e-class',
                'name' => 'Mercedes-Benz E-Class',
                'description' => 'Sedan mewah untuk tamu VIP, pernikahan, dan perjalanan bisnis, sangat disarankan dengan sopir berseragam.',
                'translations' => [
                    'id' => ['name' => 'Mercedes-Benz E-Class', 'description' => 'Sedan mewah untuk tamu VIP, pernikahan, dan perjalanan bisnis, sangat disarankan dengan sopir berseragam.'],
                    'en' => ['name' => 'Mercedes-Benz E-Class', 'description' => 'A luxury sedan for VIP guests, weddings and business travel, best enjoyed with a uniformed driver.'],
                    'ja' => ['name' => 'メルセデス・ベンツ Eクラス', 'description' => 'VIPのお客様、ウェディング、ビジネス出張向けのラグジュアリーセダン。制服姿のドライバー付きがおすすめです。'],
                ],
                'model_year' => 2022,
                'seats' => 5,
                'luggage_capacity' => 3,
                'transmission' => 'automatic',
                'fuel_type' => 'petrol',
                'body_type' => 'luxury',
                'insurance_included' => true,
                'driver_available' => true,
                'driver_price' => 500000,
                'base_price' => 2200000,
                'features' => ['air_conditioning', 'bluetooth', 'gps', 'usb_charging', 'sunroof', 'rear_camera', 'cruise_control', 'leather_seats'],
                'total_units' => 2,
                'images' => [
                    ['url' => '/images/vehicles/mercedes-benz-e-class-1.webp', 'tags' => ['exterior'], 'alt' => 'Mercedes-Benz E-Class tampak samping'],
                    ['url' => '/images/vehicles/mercedes-benz-e-class-2.webp', 'tags' => ['exterior', 'road'], 'alt' => 'Mercedes-Benz E-Class di jalan'],
                ],
            ],
            [
                'slug' => 'toyota-hiace-commuter',
                'name' => 'Toyota HiAce Commuter',
                'description' => 'Van 15 kursi untuk rombongan, keluarga besar, atau acara kantor, lengkap dengan AC dingin dan bagasi besar.',
                'translations' => [
                    'id' => ['name' => 'Toyota HiAce Commuter', 'description' => 'Van 15 kursi untuk rombongan, keluarga besar, atau acara kantor, lengkap dengan AC dingin dan bagasi besar.'],
                    'en' => ['name' => 'Toyota HiAce Commuter', 'description' => 'A 15-seat van for groups, large families or company outings, with strong air conditioning and a big luggage bay.'],
                    'ja' => ['name' => 'トヨタ ハイエース コミューター', 'description' => 'グループや大家族、社員旅行に最適な15人乗りバン。よく効くエアコンと大きな荷室を備えています。'],
                ],
                'model_year' => 2022,
                'seats' => 15,
                'luggage_capacity' => 8,
                'transmission' => 'manual',
                'fuel_type' => 'diesel',
                'body_type' => 'van',
                'insurance_included' => true,
                'driver_available' => true,
                'driver_price' => 450000,
                'base_price' => 1300000,
                'features' => ['air_conditioning', 'bluetooth', 'usb_charging', 'rear_camera'],
                'total_units' => 3,
                'images' => [
                    ['url' => '/images/vehicles/toyota-hiace-commuter-1.webp', 'tags' => ['exterior'], 'alt' => 'Toyota HiAce Commuter tampak samping'],
                    ['url' => '/images/vehicles/toyota-hiace-commuter-2.webp', 'tags' => ['exterior', 'road'], 'alt' => 'Toyota HiAce Commuter di jalan'],
                ],
            ],
        ];
    }

    private function seedInventory(Vehicle $vehicle, int $totalUnits): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < 120; $i++) {
            $date = $start->copy()->addDays($i);
            $isWeekend = $date->isFriday() || $date->isSaturday();
            $price = $isWeekend
                ? (float) $vehicle->base_price * 1.15
                : (float) $vehicle->base_price;

            // Deterministic pseudo-occupancy so the demo shows realistic
            // partial availability instead of every date being wide open.
            $booked = ($i * 7 + $vehicle->id * 3) % ($totalUnits + 2);
            $booked = min($booked, $totalUnits);

            // Match on the same value the date cast stores, so re-seeding
            // updates the existing day instead of colliding with it.
            VehicleInventory::updateOrCreate(
                ['vehicle_id' => $vehicle->id, 'rental_date' => $date],
                [
                    'total_units' => $totalUnits,
                    'booked_units' => $booked,
                    'price' => round($price, -3),
                ]
            );
        }
    }

    private function seedKnowledgeBase(Rental $rental): void
    {
        $items = [
            [
                'category' => RentalKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang FTS Car Rental AI',
                'body' => 'FTS Car Rental AI adalah rental mobil di Denpasar, Bali, dengan lebih dari 30 unit mulai dari hatchback hingga van 15 kursi. Kami melayani lepas kunci maupun dengan sopir, dengan pengantaran gratis ke Bandara Ngurah Rai dan hotel di area Denpasar, Kuta, Seminyak, Sanur, dan Nusa Dua.',
                'translations' => [
                    'en' => ['title' => 'About FTS Car Rental AI', 'body' => 'FTS Car Rental AI is a car rental company in Denpasar, Bali, with more than 30 vehicles from hatchbacks to 15-seat vans. We offer self-drive and chauffeured rentals, with free delivery to Ngurah Rai Airport and to hotels in Denpasar, Kuta, Seminyak, Sanur and Nusa Dua.'],
                    'ja' => ['title' => 'FTS Car Rental AI について', 'body' => 'FTS Car Rental AI はバリ島デンパサールのレンタカー会社で、ハッチバックから15人乗りバンまで30台以上を保有しています。セルフドライブとドライバー付きの両方に対応し、ングラライ空港およびデンパサール、クタ、スミニャック、サヌール、ヌサドゥアのホテルへ無料でお届けします。'],
                ],
                'tags' => ['overview', 'introduction', 'about'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Sewa dengan sopir',
                'image_url' => '/images/services/driver.webp',
                'body' => 'Sopir profesional berpengalaman dan paham rute Bali tersedia untuk semua mobil, mulai dari Rp 300.000 per hari (lihat tarif tiap mobil). Satu hari sewa dengan sopir mencakup 12 jam pemakaian; kelebihan jam dikenakan Rp 50.000 per jam. Bensin, tol, dan parkir ditanggung penyewa, dan untuk perjalanan menginap di luar kota biaya makan dan penginapan sopir ditanggung penyewa.',
                'translations' => [
                    'en' => ['title' => 'Rent with a driver', 'body' => 'An experienced professional driver who knows the roads of Bali is available for every car, from IDR 300,000 per day (see each car for its rate). A chauffeured day covers 12 hours; extra hours are IDR 50,000 each. Fuel, tolls and parking are paid by the renter, and for overnight trips the driver\'s meals and lodging are also covered by the renter.'],
                    'ja' => ['title' => 'ドライバー付きレンタル', 'body' => 'バリ島の道に詳しい経験豊富なプロのドライバーを、すべての車両でご利用いただけます（1日300,000 IDRから、料金は各車両をご確認ください）。ドライバー付きの1日は12時間で、超過は1時間50,000 IDRです。燃料・高速料金・駐車料金はお客様負担で、宿泊を伴う場合はドライバーの食事と宿泊費もお客様負担となります。'],
                ],
                'tags' => ['driver', 'sopir', 'chauffeur', 'with driver'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_DELIVERY,
                'title' => 'Antar-jemput bandara dan hotel',
                'image_url' => '/images/services/airport.webp',
                'body' => 'Pengantaran dan pengambilan mobil gratis di Bandara Internasional Ngurah Rai untuk sewa 3 hari atau lebih (di bawah itu Rp 100.000). Pengantaran ke hotel atau vila di Denpasar, Kuta, Seminyak, Sanur, dan Nusa Dua gratis; Canggu dan Ubud dikenakan Rp 100.000 per arah. Mohon pesan minimal 24 jam sebelumnya dan sertakan nomor penerbangan.',
                'translations' => [
                    'en' => ['title' => 'Airport and hotel delivery', 'body' => 'Free car delivery and pick-up at Ngurah Rai International Airport for rentals of 3 days or more (IDR 100,000 for shorter rentals). Delivery to hotels and villas in Denpasar, Kuta, Seminyak, Sanur and Nusa Dua is free; Canggu and Ubud are IDR 100,000 each way. Please book at least 24 hours ahead and share your flight number.'],
                    'ja' => ['title' => '空港・ホテルへのお届け', 'body' => '3日以上のレンタルでは、ングラライ国際空港での車両お届けと返却が無料です（それ未満は100,000 IDR）。デンパサール、クタ、スミニャック、サヌール、ヌサドゥアのホテル・ヴィラへのお届けは無料で、チャングーとウブドは片道100,000 IDRです。24時間前までに、フライト番号とあわせてご予約ください。'],
                ],
                'tags' => ['airport', 'bandara', 'delivery', 'antar', 'jemput', 'hotel', 'ngurah rai'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Kursi bayi dan perlengkapan tambahan',
                'image_url' => '/images/services/child-seat.webp',
                'body' => 'Kursi bayi/anak Rp 50.000 per hari, GPS portabel Rp 40.000 per hari, Wi-Fi portabel Rp 60.000 per hari, dan pemegang ponsel gratis. Perlengkapan tambahan harus diminta saat reservasi karena jumlahnya terbatas.',
                'translations' => [
                    'en' => ['title' => 'Child seats and extras', 'body' => 'Baby and child seats are IDR 50,000 per day, a portable GPS is IDR 40,000 per day, a portable Wi-Fi router is IDR 60,000 per day, and phone holders are free. Extras must be requested when you reserve because stock is limited.'],
                    'ja' => ['title' => 'チャイルドシートとオプション', 'body' => 'チャイルドシートは1日50,000 IDR、ポータブルGPSは1日40,000 IDR、ポータブルWi-Fiは1日60,000 IDRで、スマートフォンホルダーは無料です。数に限りがあるため、オプションはご予約時にお申し付けください。'],
                ],
                'tags' => ['child seat', 'kursi bayi', 'gps', 'wifi', 'extras', 'perlengkapan'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Asuransi dan perlindungan kerusakan',
                'image_url' => '/images/services/insurance.webp',
                'body' => 'Semua mobil dilindungi asuransi dasar (all-risk) dengan risiko sendiri (excess) Rp 3.000.000 per kejadian untuk mobil biasa dan Rp 10.000.000 untuk mobil premium. Asuransi dasar sudah termasuk pada HR-V, Innova Zenix Hybrid, E-Class, dan HiAce. Paket perlindungan penuh (CDW) Rp 75.000 per hari menurunkan risiko sendiri menjadi Rp 500.000. Ban, kaca, dan kerusakan akibat kelalaian tidak ditanggung.',
                'translations' => [
                    'en' => ['title' => 'Insurance and damage protection', 'body' => 'Every car is covered by basic all-risk insurance with an excess of IDR 3,000,000 per incident for standard cars and IDR 10,000,000 for premium cars. Basic insurance is already included on the HR-V, Innova Zenix Hybrid, E-Class and HiAce. A full-protection package (CDW) for IDR 75,000 per day lowers the excess to IDR 500,000. Tyres, glass and damage from negligence are not covered.'],
                    'ja' => ['title' => '保険と損害補償', 'body' => 'すべての車両に基本のオールリスク保険が付いており、免責額は一般車で1事故あたり3,000,000 IDR、プレミアム車で10,000,000 IDRです。HR-V、イノーバ ゼニックス ハイブリッド、Eクラス、ハイエースには基本保険が含まれています。フルプロテクション（CDW）は1日75,000 IDRで、免責額が500,000 IDRになります。タイヤ、ガラス、過失による損害は対象外です。'],
                ],
                'tags' => ['insurance', 'asuransi', 'cdw', 'damage', 'kerusakan', 'excess'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Bantuan darurat 24 jam',
                'image_url' => '/images/services/support.webp',
                'body' => 'Tim kami siap membantu 24 jam jika mobil mogok, ban bocor, atau terjadi kecelakaan. Hubungi nomor darurat yang tertera di kunci mobil; kami akan mengirim bantuan atau mobil pengganti untuk wilayah Bali, biasanya dalam 60–90 menit.',
                'translations' => [
                    'en' => ['title' => '24-hour roadside support', 'body' => 'Our team is on call 24 hours if the car breaks down, a tyre goes flat or there is an accident. Call the emergency number printed on your key tag and we will send help or a replacement car anywhere in Bali, usually within 60–90 minutes.'],
                    'ja' => ['title' => '24時間ロードサービス', 'body' => '故障、パンク、事故の際は24時間体制でサポートします。キータグに記載の緊急番号へご連絡いただければ、バリ島内であれば通常60〜90分以内に救援または代車を手配します。'],
                ],
                'tags' => ['support', 'breakdown', 'emergency', 'darurat', 'roadside', 'accident', 'mogok'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_PACKAGES,
                'title' => 'Paket wisata Bali dengan sopir',
                'image_url' => '/images/services/tour.webp',
                'body' => 'Paket 10 jam dengan sopir untuk rute populer seperti Ubud–Tegallalang–Tirta Empul, Uluwatu–Pantai Padang Padang, atau Kintamani–Batur. Tersedia dengan semua mobil; harga mengikuti tarif mobil ditambah sopir, dengan bensin, tol, dan parkir ditanggung penyewa. Sopir akan membantu mengatur urutan tujuan agar perjalanan efisien.',
                'translations' => [
                    'en' => ['title' => 'Bali tour packages with a driver', 'body' => 'A 10-hour chauffeured package for popular routes such as Ubud–Tegallalang–Tirta Empul, Uluwatu–Padang Padang Beach, or Kintamani–Batur. Available with every car; the price follows the car rate plus the driver, with fuel, tolls and parking paid by the renter. Your driver can help sequence the stops so the day runs smoothly.'],
                    'ja' => ['title' => 'ドライバー付きバリ島ツアープラン', 'body' => 'ウブド〜テガララン〜ティルタ・エンプル、ウルワツ〜パダンパダンビーチ、キンタマーニ〜バトゥールなど人気ルートを巡る10時間のドライバー付きプランです。全車両でご利用いただけ、料金は車両料金にドライバー料金を加えたもので、燃料・高速料金・駐車料金はお客様負担です。ドライバーが効率のよい訪問順をご案内します。'],
                ],
                'tags' => ['package', 'paket', 'tour', 'wisata', 'ubud', 'uluwatu', 'kintamani', 'route', 'rute'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_PACKAGES,
                'title' => 'Diskon sewa mingguan dan bulanan',
                'image_url' => '/images/services/monthly.webp',
                'body' => 'Sewa 7 hari atau lebih mendapat diskon 10% dari tarif harian, dan sewa 30 hari atau lebih diskon 25%. Diskon dihitung otomatis saat tim kami mengonfirmasi reservasi. Untuk sewa lebih dari 30 hari, silakan hubungi staf kami untuk penawaran khusus.',
                'translations' => [
                    'en' => ['title' => 'Weekly and monthly discounts', 'body' => 'Rentals of 7 days or more get 10% off the daily rate, and rentals of 30 days or more get 25% off. Discounts are applied when our team confirms the reservation. For rentals longer than 30 days, please contact our staff for a special quote.'],
                    'ja' => ['title' => '週割・月割プラン', 'body' => '7日以上のレンタルは日額料金から10%引き、30日以上は25%引きです。割引はスタッフが予約を確認する際に適用されます。30日を超える長期レンタルは、スタッフまでお問い合わせください。'],
                ],
                'tags' => ['discount', 'diskon', 'weekly', 'monthly', 'mingguan', 'bulanan', 'long term'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Syarat dan dokumen sewa',
                'body' => 'Penyewa minimal berusia 21 tahun dan memiliki SIM A yang masih berlaku minimal 1 tahun. Wisatawan asing wajib membawa paspor dan SIM Internasional (IDP) dengan SIM negara asal. WNI cukup menunjukkan KTP dan SIM A. Deposit Rp 1.000.000 untuk mobil biasa dan Rp 5.000.000 untuk mobil premium, dikembalikan saat mobil dikembalikan dalam kondisi baik.',
                'translations' => [
                    'en' => ['title' => 'Rental requirements and documents', 'body' => 'Renters must be at least 21 years old and hold a valid driving licence for at least 1 year. Foreign visitors must bring their passport and an International Driving Permit (IDP) with their home licence. Indonesian renters need an ID card (KTP) and a Class A licence (SIM A). A deposit of IDR 1,000,000 applies to standard cars and IDR 5,000,000 to premium cars, refunded when the car is returned in good condition.'],
                    'ja' => ['title' => 'ご利用条件と必要書類', 'body' => 'ご利用には21歳以上で、有効な運転免許を1年以上お持ちであることが必要です。外国人のお客様はパスポートと、母国の免許証に加えて国際運転免許証（IDP）をご持参ください。インドネシア国籍の方はKTPとSIM Aが必要です。保証金は一般車1,000,000 IDR、プレミアム車5,000,000 IDRで、車両を良好な状態で返却いただいた際に返金します。'],
                ],
                'tags' => ['requirements', 'syarat', 'documents', 'license', 'sim', 'idp', 'deposit', 'age', 'umur'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Jam sewa dan keterlambatan pengembalian',
                'body' => 'Satu hari sewa berarti 24 jam sejak waktu pengambilan. Konter kami buka pukul 07:00–21:00. Keterlambatan hingga 3 jam dikenakan 10% dari tarif harian per jam, dan lebih dari 3 jam dihitung satu hari penuh. Serah terima di luar jam konter (21:00–07:00) bisa diatur dengan biaya Rp 100.000.',
                'translations' => [
                    'en' => ['title' => 'Rental hours and late returns', 'body' => 'One rental day is 24 hours from the pick-up time. Our counter is open from 7:00 AM to 9:00 PM. Returns up to 3 hours late are charged 10% of the daily rate per hour, and anything beyond 3 hours counts as a full extra day. After-hours handover (9:00 PM to 7:00 AM) can be arranged for IDR 100,000.'],
                    'ja' => ['title' => 'レンタル時間と返却遅延', 'body' => 'レンタルの1日は受取時刻から24時間です。カウンターの営業時間は7:00〜21:00です。3時間までの遅延は1時間あたり日額の10%、3時間を超えると1日分が加算されます。営業時間外（21:00〜7:00）の受け渡しは100,000 IDRで手配できます。'],
                ],
                'tags' => ['hours', 'jam', 'late', 'terlambat', 'return', 'pengembalian', 'after hours'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Bahan bakar, tol, dan parkir',
                'body' => 'Mobil diserahkan dengan bensin penuh dan harus dikembalikan penuh (full-to-full). Jika kurang, kami mengisi dan menagih biaya bensin ditambah biaya layanan Rp 50.000. Tol, parkir, dan denda lalu lintas selama masa sewa sepenuhnya menjadi tanggung jawab penyewa. Jarak tempuh tanpa batas di dalam Bali.',
                'translations' => [
                    'en' => ['title' => 'Fuel, tolls and parking', 'body' => 'The car is handed over with a full tank and must be returned full (full-to-full). If it is short, we refuel and charge the fuel plus a IDR 50,000 service fee. Tolls, parking and any traffic fines during the rental are the renter\'s responsibility. Mileage is unlimited within Bali.'],
                    'ja' => ['title' => '燃料・高速料金・駐車料金', 'body' => '車両は満タンでお渡しし、満タンで返却いただきます（満タン返し）。不足している場合は当社で給油し、燃料代に手数料50,000 IDRを加えて請求します。レンタル期間中の高速料金、駐車料金、交通違反の罰金はお客様の負担です。バリ島内は走行距離無制限です。'],
                ],
                'tags' => ['fuel', 'bensin', 'toll', 'tol', 'parking', 'parkir', 'mileage', 'fine', 'denda'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Kebijakan pembatalan',
                'body' => 'Pembatalan gratis hingga 48 jam sebelum waktu pengambilan. Pembatalan dalam 48 jam terakhir dikenakan biaya 50% dari tarif satu hari. Tidak hadir tanpa pemberitahuan (no-show) dikenakan biaya satu hari penuh. Ubah tanggal gratis satu kali selama mobil masih tersedia.',
                'translations' => [
                    'en' => ['title' => 'Cancellation policy', 'body' => 'Free cancellation up to 48 hours before pick-up. Cancellations within 48 hours are charged 50% of one day\'s rate. A no-show is charged one full day. One free date change is allowed as long as the car is still available.'],
                    'ja' => ['title' => 'キャンセルポリシー', 'body' => '受取の48時間前まで無料でキャンセルいただけます。48時間以内のキャンセルは1日分の料金の50%が発生します。無連絡のキャンセル（ノーショー）は1日分の全額を請求します。車両に空きがある限り、日程変更は1回まで無料です。'],
                ],
                'tags' => ['cancellation', 'pembatalan', 'refund', 'no-show', 'reschedule'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Jika terjadi kecelakaan atau kerusakan',
                'body' => 'Hentikan mobil di tempat aman, jangan memindahkan kendaraan bila ada korban, dan segera hubungi bantuan darurat 24 jam kami. Laporkan kejadian dalam 1 jam dan jangan menandatangani apa pun dengan pihak lain tanpa konfirmasi tim kami. Kecelakaan akibat mengemudi di bawah pengaruh alkohol atau obat-obatan tidak ditanggung asuransi.',
                'translations' => [
                    'en' => ['title' => 'If there is an accident or damage', 'body' => 'Stop in a safe place, do not move the vehicle if anyone is injured, and call our 24-hour emergency support right away. Report the incident within 1 hour and do not sign anything with another party without confirming with our team. Accidents caused by driving under the influence of alcohol or drugs are not covered by insurance.'],
                    'ja' => ['title' => '事故・故障が起きたとき', 'body' => '安全な場所に停車し、負傷者がいる場合は車を動かさず、すぐに24時間の緊急サポートへご連絡ください。1時間以内にご報告いただき、当社に確認せずに相手方と書類に署名しないでください。飲酒や薬物の影響下での運転による事故は保険の対象外です。'],
                ],
                'tags' => ['accident', 'kecelakaan', 'damage', 'insurance', 'emergency', 'darurat'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bolehkah wisatawan asing menyetir sendiri?',
                'body' => 'Boleh, selama membawa paspor, SIM dari negara asal, dan SIM Internasional (IDP) yang berlaku. Mengemudi di Bali berada di lajur kiri dengan banyak sepeda motor, jadi kami menyarankan Anda memilih mobil otomatis atau menambah sopir jika belum terbiasa.',
                'translations' => [
                    'en' => ['title' => 'Can foreign visitors drive themselves?', 'body' => 'Yes, as long as you bring your passport, your home-country licence and a valid International Driving Permit (IDP). Driving in Bali is on the left with lots of motorbikes, so we suggest choosing an automatic or adding a driver if you are not used to it.'],
                    'ja' => ['title' => '外国人観光客は自分で運転できますか？', 'body' => 'パスポート、母国の運転免許証、有効な国際運転免許証（IDP）をお持ちであれば運転できます。バリ島は左側通行でバイクが多いため、不慣れな方はオートマチック車の選択やドライバーの追加をおすすめします。'],
                ],
                'tags' => ['foreign', 'tourist', 'self-drive', 'lepas kunci', 'idp', 'license'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bolehkah membawa mobil ke luar Bali?',
                'body' => 'Mobil dapat dibawa ke Jawa melalui kapal feri Gilimanuk–Ketapang dengan persetujuan tertulis dari kami minimal 3 hari sebelumnya dan biaya tambahan Rp 300.000 per hari. Perjalanan ke pulau lain seperti Lombok tidak diperbolehkan.',
                'translations' => [
                    'en' => ['title' => 'Can I take the car outside Bali?', 'body' => 'The car can go to Java via the Gilimanuk–Ketapang ferry with our written approval at least 3 days in advance and an extra fee of IDR 300,000 per day. Trips to other islands such as Lombok are not permitted.'],
                    'ja' => ['title' => 'バリ島の外へ車を持ち出せますか？', 'body' => 'ギリマヌク〜ケタパンのフェリーでジャワ島へ行くことは、3日前までの当社の書面による承認と、1日300,000 IDRの追加料金で可能です。ロンボクなど他の島への持ち出しはできません。'],
                ],
                'tags' => ['java', 'jawa', 'ferry', 'outside bali', 'luar bali', 'lombok'],
            ],
            [
                'category' => RentalKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bagaimana cara pembayaran?',
                'body' => 'Permintaan reservasi di website tidak memerlukan pembayaran. Setelah tim kami mengonfirmasi ketersediaan, Anda akan menerima instruksi pembayaran lewat WhatsApp atau email. Kami menerima transfer bank, kartu kredit, dan tunai saat serah terima; deposit dibayar saat pengambilan mobil.',
                'translations' => [
                    'en' => ['title' => 'How do I pay?', 'body' => 'A reservation request on the website needs no payment. Once our team confirms availability, you will receive payment instructions by WhatsApp or email. We accept bank transfer, credit card, and cash at handover; the deposit is paid when you collect the car.'],
                    'ja' => ['title' => 'お支払い方法は？', 'body' => 'ウェブサイトからの予約リクエストにお支払いは不要です。スタッフが空車を確認した後、WhatsAppまたはメールでお支払い方法をご案内します。銀行振込、クレジットカード、お受け渡し時の現金に対応しており、保証金は車両のお受け取り時にお支払いいただきます。'],
                ],
                'tags' => ['payment', 'pembayaran', 'transfer', 'credit card', 'cash', 'deposit'],
            ],
        ];

        $sort = 0;
        foreach ($items as $item) {
            $rental->knowledgeItems()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'category' => $item['category'],
                    'body' => $item['body'],
                    'translations' => $item['translations'],
                    'tags' => $item['tags'],
                    'image_url' => $item['image_url'] ?? null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
