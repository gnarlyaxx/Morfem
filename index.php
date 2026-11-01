<?php
require_once __DIR__ . '/core/Database.php';

$databaseConnection = Database::getConnection();

$totalDocumentCount = 0;
$totalIndexedVocabularyCount = 0;

$documentCountRecordset = mysqli_query($databaseConnection, "SELECT COUNT(*) as count FROM berita");
if ($documentCountRecordset) {
    $documentCountRow = mysqli_fetch_assoc($documentCountRecordset);
    $totalDocumentCount = (int)($documentCountRow['count'] ?? 0);
}

$tokenCountRecordset = mysqli_query($databaseConnection, "SELECT COUNT(DISTINCT kata) as count FROM token WHERE id != '0'");
if ($tokenCountRecordset) {
    $tokenCountRow = mysqli_fetch_assoc($tokenCountRecordset);
    $totalIndexedVocabularyCount = (int)($tokenCountRow['count'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Morfem - Search Engine NLP & Information Retrieval</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="hero-wrapper">
    <div class="hero-logo">Morfem</div>
    <p class="hero-sub">
      Mesin pencari dokumen berita berbasis Natural Language Processing dan model ruang vektor.
    </p>

    <form action="search.php" method="GET" class="search-form-hero">
      <div class="search-input-box">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#64748b;margin-right:0.4rem;flex-shrink:0;">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="q" placeholder="Ketik kata kunci pencarian (misal: kursi roda, kantor)..." autofocus required autocomplete="off">
        <button type="submit" class="search-btn">Cari</button>
      </div>

      <div class="method-pills">
        <label class="method-pill">
          <input type="radio" name="method" value="cosine" checked>
          <span>Cosine Similarity</span>
        </label>
        <label class="method-pill">
          <input type="radio" name="method" value="tfidf">
          <span>TF-IDF Weighting</span>
        </label>
        <label class="method-pill">
          <input type="radio" name="method" value="jaccard">
          <span>Jaccard Similarity</span>
        </label>
        <label class="method-pill">
          <input type="radio" name="method" value="dice">
          <span>Dice Similarity</span>
        </label>
      </div>
    </form>

    <div style="margin-top: 1.5rem; display: flex; gap: 0.4rem; flex-wrap: wrap; justify-content: center; align-items: center; font-size: 0.85rem; color: var(--text-muted);">
      <span>Contoh Query:</span>
      <a href="search.php?q=kursi+roda&method=cosine" class="tag-badge">kursi roda</a>
      <a href="search.php?q=roda+kursi+kantor&method=cosine" class="tag-badge">roda kursi kantor</a>
      <a href="search.php?q=kursi+kamar+mandi&method=cosine" class="tag-badge">kursi kamar mandi</a>
      <a href="search.php?q=jual+alat+medis&method=cosine" class="tag-badge">jual alat medis</a>
    </div>

    <div style="margin-top: 3rem; display: flex; gap: 1.5rem; color: var(--text-muted); font-size: 0.88rem;">
      <div>
        <strong style="color:var(--text-main); font-size:1rem;"><?= $totalDocumentCount ?></strong> Dokumen Berita
      </div>
      <div>&bull;</div>
      <div>
        <strong style="color:var(--text-main); font-size:1rem;"><?= $totalIndexedVocabularyCount ?></strong> Kosa Kata Terindeks
      </div>
      <div>&bull;</div>
      <div>
        <a href="compare.php" style="color:var(--primary); font-weight:600;">Uji Banding Algoritma &rarr;</a>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
