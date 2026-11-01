# Changelog

All notable changes to the **Morfem** Search Engine project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.2.1] - 2026-11-01
### Fixed
- Ganti semua raw LaTeX string (`$TF_q$`, `\log_{10}`, dll.) di `perhitungan.php` dengan HTML entities (`<sub>`, `<sup>`, `&times;`, `&radic;`, dll.) agar tampil benar di browser tanpa MathJax/KaTeX.

## [v1.2.0] - 2026-11-01
### Added
- Modul halaman baru `morfem/perhitungan.php` untuk menampilkan tabel perhitungan matematis komprehensif Information Retrieval (TF, IDF, Bobot VSM $W_q$ & $W_d$, Panjang Vektor $|Q|$ & $|D|$, Dot Product, Cosine, Jaccard, dan Dice Similarity).
- Simulator query berorientasi matematika dengan pemecahan vektor langkah demi langkah.

### Removed
- Menu Changelog pada navigasi utama, digantikan oleh menu **Perhitungan Matematis**.

## [v1.1.0] - 2026-11-01
### Removed
- Akses dan tautan modul bawaan dosen (root `index.php` kini langsung mengalihkan ke antarmuka utama Morfem).

### Changed
- Perombakan tema dan visual antarmuka di `assets/style.css` menjadi tampilan flat, clean academic & developer utility (menghilangkan border-radius pill dan gradien generic).
- Pembaruan identitas penyusun di footer menjadi **Boby Harjuna Pangestu**.

## [v1.0.1] - 2026-11-01
### Changed
- Refaktor penamaan variabel ambigu (`data`, `res`, `result`, `item`, `row`) menjadi terminologi domain yang eksplisit di seluruh modul.
- Pembersihan komentar redundan yang menduplikasi penjelasan sintaks kode.
- Penyesuaian antarmuka UI di `assets/style.css` menjadi tampilan minimalis berbasis layout presisi, tanpa efek gradien generic.

## [v1.0.0] - 2026-11-01
### Added
- Inisialisasi arsitektur search engine Morfem (Information Retrieval & NLP).
- Modul `morfem/core/Database.php` dengan koneksi aman dan sanitasi MySQLi.
- Modul `morfem/core/Preprocessor.php` untuk pipeline NLP otomatis (Case folding, cleansing, tokenizing, stopword removal, dan Sastrawi stemming).
- Modul `morfem/core/SearchEngine.php` dengan dukungan VSM Cosine Similarity, TF-IDF Weighting, Jaccard Similarity, dan Dice Similarity.
- Antarmuka pencarian di `morfem/index.php`.
- Halaman hasil pencarian `morfem/search.php` dengan keyword highlighting, skor relevansi, dan rincian parameter matematis.
- Halaman komparasi multi-algoritma berdampingan di `morfem/compare.php`.
- Dashboard pipeline & re-indexing korpus 1-klik di `morfem/pipeline.php`.
- Manajemen korpus berita (CRUD) dengan auto-indexing di `morfem/dokumen.php`.
