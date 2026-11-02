<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Changelog - Morfem Search Engine</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .changelog-entry {
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem 1.25rem;
      margin-bottom: 1rem;
      background: var(--bg-page);
    }
    .version-tag {
      display: inline-block;
      background: var(--bg-subtle);
      border: 1px solid var(--border);
      color: var(--primary);
      font-weight: 700;
      font-size: 0.85rem;
      font-family: ui-monospace, monospace;
      padding: 0.15rem 0.5rem;
      border-radius: var(--radius);
    }
    .release-date {
      color: var(--text-light);
      font-size: 0.8rem;
      margin-left: 0.4rem;
    }
    .change-list {
      margin-top: 0.5rem;
      padding-left: 1.2rem;
      color: var(--text-main);
      font-size: 0.85rem;
    }
    .change-list li {
      margin-bottom: 0.2rem;
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container" style="max-width: 800px;">
    <div style="margin-bottom: 1.25rem;">
      <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:0.2rem;">Riwayat Pembaruan (Changelog)</h1>
      <p style="color:var(--text-muted);font-size:0.85rem;">
        Catatan rilis versi sistem pencarian <strong>Morfem</strong>.
      </p>
    </div>

    <!-- ENTRY v1.3.0 -->
    <div class="changelog-entry">
      <div>
        <span class="version-tag">v1.3.0</span>
        <span class="release-date">&bull; 2026-11-02</span>
      </div>
      <h3 style="font-size:0.95rem;margin-top:0.4rem;font-weight:700;">Manajemen Kata Baru, Export Database, &amp; Sinkronisasi Hosting</h3>
      <ul class="change-list">
        <li><strong>Added:</strong> Fitur manajemen kata baru (Stopword) untuk menambah, memfilter, dan menghapus kata stopword langsung ke database MySQL.</li>
        <li><strong>Added:</strong> Fitur 1-klik unduh backup database (.sql) langsung dari web untuk sinkronisasi dari localhost ke hosting.</li>
        <li><strong>Added:</strong> Panduan auto-save dan penjelasan sinkronisasi database server localhost vs hosting.</li>
        <li><strong>Added:</strong> Dukungan Environment Variables (DB_HOST, DB_USER, DB_PASS, DB_NAME) pada Database.php.</li>
        <li><strong>Changed:</strong> Label navigasi diperbarui menjadi 'Kelola Dokumen &amp; Kata'.</li>
      </ul>
    </div>

    <!-- ENTRY v1.1.0 -->
    <div class="changelog-entry">
      <div>
        <span class="version-tag">v1.1.0</span>
        <span class="release-date">&bull; 2026-11-01</span>
      </div>
      <h3 style="font-size:0.95rem;margin-top:0.4rem;font-weight:700;">Pembersihan Modul Legacy & Restrukturisasi Desain</h3>
      <ul class="change-list">
        <li><strong>Removed:</strong> Menghapus menu dan akses modul bawaan dosen (akses root langsung diarahkan ke Morfem).</li>
        <li><strong>Changed:</strong> Redesain antarmuka ke tema minimalis (menghilangkan border-radius pill dan gradien generic).</li>
        <li><strong>Changed:</strong> Penyesuaian nama penyusun pada footer menjadi <strong>Boby Harjuna Pangestu</strong>.</li>
      </ul>
    </div>

    <!-- ENTRY v1.0.1 -->
    <div class="changelog-entry">
      <div>
        <span class="version-tag">v1.0.1</span>
        <span class="release-date">&bull; 2026-11-01</span>
      </div>
      <h3 style="font-size:0.95rem;margin-top:0.4rem;font-weight:700;">Penyesuaian Standar Kode</h3>
      <ul class="change-list">
        <li><strong>Changed:</strong> Refaktor penamaan variabel ambigu menjadi terminologi domain eksplisit.</li>
        <li><strong>Changed:</strong> Penghapusan komentar redundant yang menduplikasi kode.</li>
      </ul>
    </div>

    <!-- ENTRY v1.0.0 -->
    <div class="changelog-entry">
      <div>
        <span class="version-tag">v1.0.0</span>
        <span class="release-date">&bull; 2026-11-01</span>
      </div>
      <h3 style="font-size:0.95rem;margin-top:0.4rem;font-weight:700;">Rilis Perdana Sistem Temu Kembali Informasi Morfem</h3>
      <ul class="change-list">
        <li><strong>Added:</strong> Inisialisasi arsitektur search engine terpadu berbasis Natural Language Processing.</li>
        <li><strong>Added:</strong> Pipeline NLP otomatis (Case Folding, Cleansing, Tokenizing, Stopword Removal, dan Stemming Sastrawi).</li>
        <li><strong>Added:</strong> Dukungan 4 metode pembobotan dan kemiripan: VSM Cosine Similarity, TF-IDF, Jaccard Similarity, dan Dice.</li>
        <li><strong>Added:</strong> Halaman komparasi multi-algoritma berdampingan.</li>
        <li><strong>Added:</strong> Dashboard 1-klik re-indexing korpus dokumen.</li>
        <li><strong>Added:</strong> Modul manajemen korpus berita (CRUD) dengan auto-indexing instan.</li>
      </ul>
    </div>
  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
