# Morfem — Information Retrieval Search Engine

> Morfem = unit linguistik terkecil yang bermakna. Nama ini mencerminkan inti sistem: memecah teks menjadi morfem dasar untuk pencarian yang akurat.

Proyek tugas mata kuliah **Natural Language** — Teknik Informatika.  
Dibangun di atas XAMPP (PHP + MySQL) dengan pipeline NLP Bahasa Indonesia.

---

## Fitur Utama

| Fitur | Deskripsi |
|---|---|
| 🔍 **Multi-Algoritma Search** | Cosine Similarity, TF-IDF, Jaccard, Dice — pilih per query |
| 🧠 **NLP Pipeline** | Case Folding → Cleansing → Tokenisasi → Stopword Removal → Sastrawi Stemming |
| 📊 **Tabel Perhitungan Matematis** | TF, IDF, bobot VSM, dot product, panjang vektor — full breakdown per query |
| ⚡ **Bandingkan Algoritma** | Side-by-side perbandingan 3 metode sekaligus untuk 1 query |
| 📁 **Kelola Dokumen** | CRUD korpus dengan auto-indexing saat dokumen ditambahkan |
| 🔁 **Re-Index Korpus** | 1-klik re-index seluruh koleksi dokumen |
| 🔗 **Auto Query Sync** | Halaman perhitungan matematis otomatis mengikuti query pencarian terakhir |

---

## Tech Stack

- **Backend:** PHP 8.x (XAMPP)
- **Database:** MySQL — tabel `berita`, `token`, `katakunci`, `stopword`
- **Stemmer:** [Sastrawi](https://github.com/sastrawi/sastrawi) (Bahasa Indonesia)
- **Frontend:** Vanilla HTML/CSS — flat minimalist, no framework
- **Retrieval Models:** Vector Space Model (VSM), Set-based (Jaccard, Dice)

---

## Struktur Proyek

```
Morfem/
│   ├── core/
│   │   ├── Database.php        # MySQLi singleton, UTF8MB4
│   │   ├── Preprocessor.php    # NLP pipeline + reindexAll()
│   │   └── SearchEngine.php    # Cosine / TF-IDF / Jaccard / Dice
│   ├── assets/
│   │   └── style.css           # Flat minimalist theme
│   ├── index.php               # Halaman pencarian utama
│   ├── search.php              # Hasil pencarian + skor
│   ├── compare.php             # Perbandingan 3 algoritma
│   ├── pipeline.php            # Simulator NLP + re-index
│   ├── dokumen.php             # CRUD manajemen korpus
│   ├── perhitungan.php         # Tabel matematis komprehensif
│   ├── navbar.php
│   └── footer.php
├── vendor/                     # Sastrawi via Composer
├── CHANGELOG.md
└── README.md
```

---

## Instalasi

### Prasyarat
- XAMPP (PHP 8.x + MySQL)
- Composer
- Git

### Langkah

```bash
# 1. Clone ke htdocs
git clone https://github.com/gnarlyaxx/Morfem.git C:/xampp/htdocs/Morfem

# 2. Install Sastrawi
cd C:/xampp/htdocs/Morfem
composer require sastrawi/sastrawi

# 3. Import database
# Buka phpMyAdmin → buat database 'morfem' → import SQL
```

### Konfigurasi Database

Edit `morfem/core/Database.php` jika kredensial berbeda dari default XAMPP:

```php
private static string $host = 'localhost';
private static string $user = 'root';
private static string $pass = '';          // default XAMPP: kosong
private static string $dbName = 'morfem';
```

### Akses

Buka browser → `http://localhost/morfem/`

---

## Alur NLP Pipeline

```
Input Query / Dokumen
        ↓
  Case Folding        → semua huruf kecil
        ↓
  Cleansing           → hapus tanda baca, angka, karakter non-alfabet
        ↓
  Tokenisasi          → pecah jadi array kata
        ↓
  Stopword Removal    → hapus kata umum (dari tabel stopword DB)
        ↓
  Sastrawi Stemming   → reduksi ke kata dasar Bahasa Indonesia
        ↓
  Term Frequency      → hitung frekuensi tiap token
```

---

## Formula Retrieval

### TF-IDF & Cosine Similarity (VSM)

$$IDF_t = \log_{10}\left(\frac{N}{DF_t}\right)$$

$$W_{q,t} = TF_{q,t} \times IDF_t \qquad W_{d,t} = TF_{d,t} \times IDF_t$$

$$\text{Cosine}(Q, D) = \frac{Q \cdot D}{|Q| \times |D|} = \frac{\sum W_{q,t} \times W_{d,t}}{\sqrt{\sum W_{q}^2} \times \sqrt{\sum W_{d}^2}}$$

### Jaccard & Dice (Set-based)

$$J(Q, D) = \frac{A}{A + B + C} \qquad D(Q, D) = \frac{2A}{2A + B + C}$$

> `A` = irisan kata query & dokumen, `B` = kata hanya di query, `C` = kata hanya di dokumen

---


Lihat [`CHANGELOG.md`](./CHANGELOG.md) untuk detail lengkap.

---

## Dibuat oleh

**Boby Harjuna Pangestu**  
Teknik Informatika — Mata Kuliah Natural Language

Powered by [Sastrawi](https://github.com/sastrawi/sastrawi) &bull; Vector Space Model &bull; Jaccard &bull; Dice
