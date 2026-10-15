# FTS Car Rental AI

Situs rental mobil interaktif dengan **AI Rental Agent**. Pelanggan menjelajahi rental seperti berjalan di showroom (showroom, armada, layanan, informasi sewa, reservasi, konter staf) sambil mengobrol dengan agen AI. Agen menjawab dari data rental yang terkontrol, mengecek ketersediaan mobil dan harga per hari, membuat permintaan sewa, dan menyerahkan percakapan ke staf bila perlu. Staf mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa perusahaan rental. Setiap rental punya halaman sendiri di `/{rentalSlug}`.

## Fitur

### Sisi pelanggan
- **Halaman bertema "stage"**: showroom, armada, detail mobil, layanan, informasi sewa, konter staf, dan reservasi, dengan narrator dan efek suara (tombol bisa dimatikan).
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Rental Agent**
  - Tahu di halaman mana pelanggan berada (mobil atau layanan yang sedang dilihat, form reservasi yang sedang diisi), jadi bisa menjawab "mobil ini".
  - Menjawab dalam bahasa yang ditulis pelanggan.
  - Menampilkan kartu mobil langsung di dalam chat.
- **Reservasi**: quote harga per mobil dan tanggal (tarif harian, opsi sopir, jumlah unit), lalu kirim permintaan sewa (status awal `pending`). Nomor referensi tampil seperti pelat nomor.

### Sisi admin (`/admin`)
- Dashboard ringkasan.
- CRUD **armada** (spesifikasi: kursi, bagasi, transmisi, bahan bakar, tipe bodi, fitur, tarif sopir, gambar, dan inventori harian).
- CRUD **knowledge items**, yaitu basis pengetahuan yang menjadi sumber jawaban agen (syarat sewa, layanan, paket, pengantaran, FAQ).
- Daftar **booking** dan ubah statusnya (`pending`, `confirmed`, `cancelled`).
- **Handover**: percakapan yang diserahkan agen ke staf. Staf bisa membalas langsung ke pelanggan dan menandainya selesai.
- Peran pengguna per rental: `owner` dan `staff`.

## Cara kerja AI Rental Agent

Kode ada di [app/Services/Agent/](app/Services/Agent).

- **[AgentService](app/Services/Agent/AgentService.php)** menjalankan satu giliran percakapan. Ia memanggil endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama yang di-host sendiri) dan menjalankan loop tool-calling (maksimal 6 putaran per giliran). Riwayat percakapan disimpan di database. Request dikirim dengan `reasoning_effort=none` agar model tidak melakukan fase berpikir panjang. Seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[RentalAgentTools](app/Services/Agent/RentalAgentTools.php)** berisi alat yang bisa dipanggil model. Semua fakta rental, mobil, harga, dan ketersediaan harus lewat sini, karena model sendiri tidak dipercaya menyimpan data apa pun.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base rental |
  | `search_vehicles` | Mencari mobil sesuai tanggal ambil/kembali dan jumlah penumpang, lengkap dengan ketersediaan dan total harga |
  | `get_vehicle_detail` | Detail satu mobil |
  | `check_availability` | Cek ketersediaan dan harga mobil untuk tanggal tertentu (opsional dengan sopir) |
  | `create_booking_request` | Membuat permintaan sewa |
  | `request_human_handover` | Menyerahkan percakapan ke staf |

- **[ContentGuard](app/Services/Agent/ContentGuard.php)** adalah pengaman deterministik di kode. Pesan yang kasar, bersifat seksual, atau ilegal (Indonesia, Inggris, Jepang) dijawab dengan balasan baku tanpa sampai ke model. Balasan model yang masih memuat kata-kata tersebut juga diganti. Pola dibuat sempit supaya pertanyaan rental yang wajar tidak ikut terblokir.
- **Harga tanpa sumber ditolak**: balasan yang memuat angka harga atau placeholder yang tidak berasal dari tool akan ditantang, supaya model tidak mengarang harga.
- **Handover**: alasan yang didukung adalah `special_request`, `complaint`, `corporate_booking`, `negotiated_rate`, `unusual_cancellation`, `payment_issue`, `breakdown_incident`, dan `low_confidence`.

## Teknologi

- PHP 8.3+ (dikembangkan di 8.4), Laravel 13
- SQLite sebagai database bawaan. Session, cache, dan queue memakai driver `database`
- Vite 8 dan Tailwind CSS 4 untuk frontend, dengan JavaScript vanilla (tanpa framework) di `resources/js/`
- LLM lokal lewat HTTP (endpoint kompatibel OpenAI)
- PHPUnit 12 untuk tes, Laravel Pint untuk format kode

## Memulai

### Prasyarat
PHP 8.3+ dengan ekstensi SQLite, Composer, dan Node.js 20.19+ (atau 22.12+) dengan npm. Untuk fitur chat AI, Anda juga perlu server LLM yang kompatibel dengan OpenAI (lihat bagian berikutnya).

### Instalasi

```bash
composer setup
```

Perintah itu menjalankan `composer install`, menyalin `.env.example` ke `.env`, membuat `APP_KEY`, menjalankan migrasi, lalu `npm install` dan `npm run build`.

Isi data demo (hanya jalan di environment `local` dan `testing`):

```bash
php artisan db:seed
```

Seeder membuat rental demo **FTS Car Rental AI** (slug `fts-car-rental-ai`, berlokasi di Bali) dengan enam mobil (Toyota Agya, Avanza, Innova Zenix Hybrid, Honda HR-V, Mercedes-Benz E-Class, dan Toyota HiAce), inventori 120 hari, serta knowledge items untuk layanan, paket, syarat sewa, dan FAQ.

### Menjalankan

```bash
composer dev
```

Perintah ini menjalankan semua proses development lewat `php artisan dev`. Daftar prosesnya bisa dilihat dengan `php artisan dev:list`.

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Rental demo | `http://localhost:8000/fts-car-rental-ai` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya untuk lokal, jangan dipakai di produksi):

```
email    : owner@ftscarrental.test
password : password
```

### Aset gambar

Latar tiap scene ada di `public/images/scenes/`, foto mobil demo di `public/images/vehicles/`, dan kartu layanan di `public/images/services/`. Semuanya ilustrasi vektor yang dibuat khusus untuk demo, jadi bebas diganti dengan foto asli (nama berkas yang sama, atau ubah `image_url` lewat panel admin). Sosok agen berupa potongan transparan di `public/images/character/`.

### Konfigurasi LLM

Atur di `.env`:

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=    # kosongkan jika server tidak memakai autentikasi
LOCAL_LLM_MODEL=      # nama model yang dimuat di server
```

Model harus mendukung function calling. Tanpa konfigurasi ini, halaman rental tetap jalan tetapi chat agen tidak bisa menjawab.

Kalau perlu, ubah juga `APP_NAME`, `APP_URL`, dan `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

### Mencoba agen dari terminal

```bash
php artisan agent:chat fts-car-rental-ai --locale=id
```

Pilihan `--locale` adalah `id`, `en`, atau `ja`. Cara ini berguna untuk menguji loop tool-calling tanpa membuka browser.

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{rentalSlug}` | Showroom rental |
| GET | `/{rentalSlug}/vehicles`, `/vehicles/{vehicleSlug}` | Daftar dan detail mobil |
| GET | `/{rentalSlug}/services`, `/services/{id}` | Layanan, paket, dan pengantaran |
| GET | `/{rentalSlug}/info`, `/staff`, `/reservation` | Info, konter staf, reservasi |
| POST | `/{rentalSlug}/reservation/quote` | Hitung harga |
| POST | `/{rentalSlug}/reservation` | Kirim permintaan sewa |
| POST | `/{rentalSlug}/agent/start` | Mulai percakapan |
| POST | `/{rentalSlug}/agent/message` | Kirim pesan ke agen |
| GET | `/{rentalSlug}/agent/history` | Riwayat percakapan |

Endpoint agen dan reservasi dibatasi laju (rate limit). Pembatas agen didefinisikan di [AppServiceProvider](app/Providers/AppServiceProvider.php), dan login admin dibatasi 5 percobaan per menit.

## Struktur proyek

```
app/
  Console/Commands/     agent:chat
  Http/Controllers/     halaman pelanggan, chat, reservasi, dan Admin/
  Models/               Rental, Vehicle, Booking, Conversation, HandoverRequest, ...
  Services/Agent/       AgentService, RentalAgentTools, ContentGuard
  Services/Reservation/ ReservationService, ReservationHandover
database/
  migrations/           skema (rental, armada, knowledge, percakapan, booking, handover)
  seeders/              DemoRentalSeeder
resources/
  js/                   stage, narrator, agent, reservation, sound
  views/                halaman pelanggan (rental/), admin (admin/), komponen
tests/
  Feature/ dan Unit/
fts_car_rental_ai_documentation_v2/   dokumen konsep dan spesifikasi produk
```

## Pengujian

```bash
composer test
```

Atau jalankan satu berkas:

```bash
php artisan test --compact tests/Feature/AgentChatTest.php
```

Cakupan tes: akses admin, chat agen, tool booking, permintaan reservasi, halaman showroom, dan `ContentGuard`.

## Gaya kode

```bash
vendor/bin/pint --dirty
```

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan rental Anda sendiri.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`. Pada setup saat ini endpoint LLM diakses lewat Tailscale.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons agen melebihi 85 detik.

## Lisensi

Project ini dibangun di atas [Laravel](https://laravel.com), yang berlisensi [MIT](https://opensource.org/licenses/MIT).
