# CERITA RAKYAT + QRIS — DEPLOY RAILWAY 24/7

Project ini disiapkan untuk PHP 8.4 + Apache + MySQL + Midtrans QRIS.
Pembeli tidak perlu login. Setelah transaksi QRIS berhasil, pembeli memperoleh bukti transaksi dan link baca unik.

## Mode pembayaran
Awal/testing:
- PAYMENT_SIMULATION=true

Midtrans Sandbox/Production:
- PAYMENT_SIMULATION=false
- MIDTRANS_SERVER_KEY=Server Key milik Anda
- MIDTRANS_IS_PRODUCTION=false untuk Sandbox
- MIDTRANS_IS_PRODUCTION=true untuk Production

## Deploy lewat GitHub + Railway

1. Ekstrak ZIP.
2. Upload seluruh isi folder project ke repository GitHub. Jangan upload file `.env`.
3. Login ke Railway dan pilih New Project -> Deploy from GitHub repo.
4. Pilih repository project ini.
5. Di canvas project Railway, tambahkan database: New -> Database -> Add MySQL.
6. Buka service aplikasi -> Variables, tambahkan Reference Variables berikut:

   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

   Jika service database Anda tidak bernama `MySQL`, ganti kata `MySQL` di atas sesuai nama service database.

7. Tambahkan juga:

   APP_TIMEZONE=Asia/Jayapura
   APP_ENV=production
   PAYMENT_SIMULATION=true
   ADMIN_PASSWORD=password-admin-yang-kuat
   MIDTRANS_IS_PRODUCTION=false

   APP_URL tidak wajib diisi. Project membaca RAILWAY_PUBLIC_DOMAIN secara otomatis.

8. Deploy/Apply changes.
9. Setelah status deployment sukses, buka aplikasi -> Settings -> Networking -> Generate Domain.
10. Buka domain Railway. Contoh: https://nama-aplikasi.up.railway.app

Database otomatis membuat tabel dan data contoh ketika container pertama kali dijalankan. Tidak perlu import schema.sql secara manual di Railway.

## Supaya tidak tidur
Railway mempunyai fitur Serverless/App Sleeping yang dapat menidurkan service tidak aktif. Untuk kebutuhan benar-benar selalu aktif, jangan aktifkan Serverless pada service aplikasi. Pastikan akun/plan memiliki resource/credit yang cukup.

## Mengaktifkan Midtrans QRIS sungguhan

1. Daftar/siapkan merchant Midtrans.
2. Untuk uji Sandbox, salin Server Key Sandbox.
3. Di Variables Railway ubah:
   PAYMENT_SIMULATION=false
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxx
   MIDTRANS_IS_PRODUCTION=false
4. Deploy perubahan.
5. Di dashboard Midtrans, arahkan Payment Notification URL / webhook ke:
   https://DOMAIN-ANDA/midtrans/webhook
6. Uji transaksi Sandbox.
7. Setelah merchant Production aktif, gunakan Server Key Production dan ubah MIDTRANS_IS_PRODUCTION=true.

Jangan pernah masukkan MIDTRANS_SERVER_KEY ke GitHub.

## Admin
Buka:
https://DOMAIN-ANDA/admin/login

Password berasal dari environment variable ADMIN_PASSWORD.

## Health check
/health

## Menjalankan lokal
Buat `.env` dari `.env.example`, isi koneksi MySQL lokal lalu jalankan:
php -S 0.0.0.0:8080 -t public

Catatan: server PHP lokal tidak menjalankan docker-entrypoint sehingga tabel perlu di-import dari sql/schema.sql jika database masih kosong.
