<?php
session_start();
require_once __DIR__ . '/core/SearchEngine.php';

$rawSearchQuery = trim($_GET['q'] ?? '');
if ($rawSearchQuery !== '') {
    $_SESSION['last_query'] = $rawSearchQuery;
}
$retrievalMethod = $_GET['method'] ?? 'cosine';
$validMethods = ['cosine', 'tfidf', 'jaccard', 'dice'];
if (!in_array($retrievalMethod, $validMethods)) {
    $retrievalMethod = 'cosine';
}

$retrievalEngine = new SearchEngine();
$searchOutput = null;

if ($rawSearchQuery !== '') {
    $searchOutput = $retrievalEngine->search($rawSearchQuery, $retrievalMethod);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($rawSearchQuery) ?> - Morfem</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .math-drawer {
      margin-top: 0.5rem;
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.5rem 0.75rem;
      font-size: 0.8rem;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      color: #334155;
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container">
    <div class="results-header">
      <form action="search.php" method="GET" class="results-search-bar">
        <div class="search-input-box">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#64748b;margin-right:0.4rem;flex-shrink:0;">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" name="q" value="<?= htmlspecialchars($rawSearchQuery) ?>" placeholder="Cari dokumen..." required autocomplete="off">
          <button type="submit" class="search-btn">Cari</button>
        </div>
      </form>

      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-top:0.5rem;">
        <div class="meta-stats">
          <span>Metode:</span>
          <a href="search.php?q=<?= urlencode($rawSearchQuery) ?>&method=cosine" class="tag-badge" style="<?= $retrievalMethod == 'cosine' ? 'background:#1e3a8a;color:#fff;border-color:#1e3a8a;' : '' ?>">Cosine</a>
          <a href="search.php?q=<?= urlencode($rawSearchQuery) ?>&method=tfidf" class="tag-badge" style="<?= $retrievalMethod == 'tfidf' ? 'background:#1e3a8a;color:#fff;border-color:#1e3a8a;' : '' ?>">TF-IDF</a>
          <a href="search.php?q=<?= urlencode($rawSearchQuery) ?>&method=jaccard" class="tag-badge" style="<?= $retrievalMethod == 'jaccard' ? 'background:#1e3a8a;color:#fff;border-color:#1e3a8a;' : '' ?>">Jaccard</a>
          <a href="search.php?q=<?= urlencode($rawSearchQuery) ?>&method=dice" class="tag-badge" style="<?= $retrievalMethod == 'dice' ? 'background:#1e3a8a;color:#fff;border-color:#1e3a8a;' : '' ?>">Dice</a>
          <span style="margin-left:0.5rem;">&bull;</span>
          <a href="compare.php?q=<?= urlencode($rawSearchQuery) ?>" style="font-weight:600;font-size:0.85rem;">Bandingkan Algoritma &rarr;</a>
          <span style="margin-left:0.5rem;">&bull;</span>
          <a href="perhitungan.php?q=<?= urlencode($rawSearchQuery) ?>" style="font-weight:600;font-size:0.85rem;">Perhitungan Matematis &rarr;</a>
        </div>

        <?php if ($searchOutput): ?>
          <div style="font-size:0.82rem;color:var(--text-muted);">
            Ditemukan <strong><?= $searchOutput['total_results'] ?></strong> hasil (<?= $searchOutput['elapsed_time'] ?> ms)
          </div>
        <?php endif; ?>
      </div>

      <?php if ($searchOutput && !empty($searchOutput['terms'])): ?>
        <div style="margin-top:0.75rem;padding:0.5rem 0.75rem;background:#f1f5f9;border-radius:var(--radius-sm);font-size:0.82rem;color:#475569;">
          <strong>Query Stemming:</strong> 
          <?php foreach ($searchOutput['terms'] as $stemmedTerm): ?>
            <span style="background:#e2e8f0;padding:2px 6px;border-radius:3px;font-weight:600;"><?= htmlspecialchars($stemmedTerm) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($searchOutput && !empty($searchOutput['results'])): ?>
      <div class="result-list">
        <?php foreach ($searchOutput['results'] as $rankedDocument): ?>
          <div class="result-card">
            <a href="<?= htmlspecialchars($rankedDocument['url']) ?>" target="_blank" rel="noopener noreferrer" class="result-url">
              <?= htmlspecialchars($rankedDocument['url']) ?>
            </a>
            <h2 class="result-title">
              <a href="<?= htmlspecialchars($rankedDocument['url']) ?>" target="_blank" rel="noopener noreferrer">
                <?= htmlspecialchars($rankedDocument['judul']) ?>
              </a>
            </h2>
            <div class="result-snippet">
              <?= $rankedDocument['snippet'] ?>
            </div>
            <div class="result-footer">
              <div>
                ID Dokumen: <strong>#<?= $rankedDocument['id'] ?></strong>
              </div>
              <div class="score-badge">
                <span>Skor <?= strtoupper($retrievalMethod) ?>:</span>
                <strong><?= $rankedDocument['score_formatted'] ?></strong>
                <?php if (isset($rankedDocument['score_percentage'])): ?>
                  <span>(<?= $rankedDocument['score_percentage'] ?>%)</span>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!empty($rankedDocument['details']) && $retrievalMethod !== 'tfidf'): ?>
              <details style="margin-top:0.5rem;font-size:0.78rem;color:var(--text-muted);cursor:pointer;">
                <summary>Parameter Perhitungan</summary>
                <div class="math-drawer">
                  A = <?= $rankedDocument['details']['A'] ?> |
                  B = <?= $rankedDocument['details']['B'] ?> |
                  C = <?= $rankedDocument['details']['C'] ?> |
                  Total Katakunci = <?= $rankedDocument['details']['jmlkatakunci'] ?> |
                  Total Kata Dokumen = <?= $rankedDocument['details']['jmlkatadok'] ?>
                </div>
              </details>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($rawSearchQuery !== ''): ?>
      <div class="card" style="text-align:center;padding:2.5rem 1.5rem;">
        <h3 style="margin-bottom:0.5rem;">Tidak ada dokumen yang cocok</h3>
        <p style="color:var(--text-muted);font-size:0.9rem;">
          Tidak ditemukan dokumen yang memuat kata kunci "<strong><?= htmlspecialchars($rawSearchQuery) ?></strong>" dengan metode <?= strtoupper($retrievalMethod) ?>.
        </p>
      </div>
    <?php endif; ?>

  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
