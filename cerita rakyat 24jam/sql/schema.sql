CREATE TABLE IF NOT EXISTS books (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 slug VARCHAR(180) NOT NULL UNIQUE,
 synopsis TEXT NOT NULL,
 price DECIMAL(12,0) NOT NULL,
 cover_url VARCHAR(500) NOT NULL,
 content_html LONGTEXT NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id VARCHAR(80) NOT NULL UNIQUE,
 book_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(12,0) NOT NULL,
 payment_method VARCHAR(30) NOT NULL DEFAULT 'QRIS',
 payment_status ENUM('pending','paid','expired','error') NOT NULL DEFAULT 'pending',
 qr_url TEXT NULL,
 access_token CHAR(48) NOT NULL UNIQUE,
 gateway_response LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 paid_at DATETIME NULL,
 CONSTRAINT fk_trx_book FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO books(title,slug,synopsis,price,cover_url,content_html) VALUES
('Legenda Danau Sentani','legenda-danau-sentani','Contoh buku digital cerita rakyat dari Papua. Isi ini dapat diganti melalui database sesuai naskah yang dimiliki.',15000,'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80','<h2>Legenda Danau Sentani</h2><p>Dahulu kala, di tanah Papua yang hijau dan dikelilingi pegunungan, hiduplah masyarakat yang menjaga alam sebagai bagian dari kehidupan mereka.</p><p>Ini adalah konten contoh. Ganti bagian <strong>content_html</strong> pada tabel <strong>books</strong> dengan naskah cerita rakyat sendiri.</p><h3>Pesan Moral</h3><p>Alam, adat, dan kebersamaan adalah warisan yang perlu dijaga oleh setiap generasi.</p>'),
('Kisah dari Lembah Keerom','kisah-dari-lembah-keerom','Contoh kedua untuk menunjukkan katalog multi-buku dan alur pembelian terpisah.',20000,'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=900&q=80','<h2>Kisah dari Lembah Keerom</h2><p>Di sebuah lembah yang subur, masyarakat hidup berdampingan dengan hutan, sungai, dan kebun yang menjadi sumber kehidupan.</p><p>Naskah ini hanya contoh dan dapat diganti.</p>');
