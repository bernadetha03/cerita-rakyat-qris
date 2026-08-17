# Website Cerita Rakyat + QRIS

Website penjualan buku digital tanpa login pengguna. Pengunjung memilih buku → sistem membuat QRIS → setelah pembayaran sukses, sistem membuat bukti transaksi dan membuka buku melalui access token unik.

## Teknologi
- PHP 8.4 (native, ringan)
- MySQL
- Bootstrap 5
- Midtrans Core API QRIS
- Dockerfile + render.yaml untuk deployment

## Fitur
- Katalog dan detail buku
- Tanpa registrasi/login pembeli
- QRIS only
- Webhook Midtrans + validasi SHA512
- Bukti transaksi: tanggal, waktu/jam, judul, harga, metode
- Link baca dengan access token
- Cek transaksi
- Dashboard admin sederhana
- Mode simulasi pembayaran untuk development

## Instalasi lokal
1. Buat database MySQL dan import `sql/schema.sql`.
2. Copy `.env.example` menjadi `.env`.
3. Sesuaikan konfigurasi DB.
4. Jalankan dari folder project:
   `php -S localhost:8080 -t public`
5. Buka `http://localhost:8080`.

Mode bawaan `PAYMENT_SIMULATION=true`, sehingga ko bisa menguji seluruh alur tanpa uang sungguhan.

## Midtrans QRIS
Isi `.env`:
- `MIDTRANS_SERVER_KEY=...`
- `MIDTRANS_IS_PRODUCTION=false` untuk Sandbox
- `PAYMENT_SIMULATION=false`

Atur Payment Notification URL di dashboard Midtrans menjadi:
`https://DOMAIN-KO/midtrans/webhook`

Saat sudah siap menerima transaksi nyata, ubah `MIDTRANS_IS_PRODUCTION=true` dan gunakan Production Server Key.

## Admin
Buka `/admin/login` dan gunakan `ADMIN_PASSWORD` dari `.env`.

## Catatan keamanan
- Jangan commit `.env` ke GitHub.
- Gunakan HTTPS di production.
- Link baca adalah access token karena pembeli tidak memiliki akun. Pembeli harus menyimpan link/kode transaksi.
- Untuk proteksi yang lebih kuat terhadap pembagian link, bisa ditambah batas perangkat/IP/masa aktif; tetapi itu memiliki tradeoff kenyamanan pengguna.
