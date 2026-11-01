<?php
require_once __DIR__ . '/core/SearchEngine.php';

$rawComparisonQuery = trim($_GET['q'] ?? '');
$retrievalEngine = new SearchEngine();
$multiAlgorithmComparison = null;

if ($rawComparisonQuery !== '') {
    $multiAlgorithmComparison = $retrievalEngine->compare($rawComparisonQuery);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bandingkan Algoritma IR - Morfem</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container">
    <div style="margin-bottom: 1.5rem;">
      <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:0.25rem;">Komparasi Multi-Algoritma IR</h1>
      <p style="color:var(--text-muted);font-size:0.92rem;">
        Perbandingan ranking dokumen antara Cosine Similarity, TF-IDF Weighting, dan Jaccard Similarity.
      </p>

      <form action="compare.php" method="GET" style="margin-top:1rem;">
        <div class="search-input-box" style="max-width:600px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#64748b;margin-right:0.4rem;flex-shrink:0;">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" name="q" value="<?= htmlspecialchars($rawComparisonQuery) ?>" placeholder="Masukkan kata kunci komparasi..." required autocomplete="off">
          <button type="submit" class="search-btn">Bandingkan</button>
        </div>
      </form>
    </div>

    <?php if ($multiAlgorithmComparison): ?>
      <div style="padding:0.6rem 0.85rem;background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:0.85rem;">
        <strong>Query:</strong> "<em><?= htmlspecialchars($rawComparisonQuery) ?></em>" &bull;
        <strong>Stemmed Terms:</strong>
        <?php foreach ($multiAlgorithmComparison['terms'] as $stemmedKeyword): ?>
          <span style="background:#e2e8f0;padding:2px 6px;border-radius:3px;font-weight:600;"><?= htmlspecialchars($stemmedKeyword) ?></span>
        <?php endforeach; ?>
      </div>

      <div class="compare-grid">
        <div class="compare-col">
          <div class="compare-col-header">
            <span>1. Cosine Similarity</span>
            <span class="tag-badge"><?= count($multiAlgorithmComparison['cosine']['results']) ?> Dokumen</span>
          </div>
          <div class="compare-col-body">
            <?php if (empty($multiAlgorithmComparison['cosine']['results'])): ?>
              <p style="color:var(--text-muted);font-size:0.82rem;">Tidak ada hasil.</p>
            <?php else: ?>
              <?php foreach ($multiAlgorithmComparison['cosine']['results'] as $rankIndex => $rankedDocument): ?>
                <div class="compare-item">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.2rem;">
                    <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">#<?= $rankIndex + 1 ?> &bull; DOC #<?= $rankedDocument['id'] ?></span>
                    <span class="compare-item-score"><?= $rankedDocument['score_formatted'] ?> (<?= $rankedDocument['score_percentage'] ?>%)</span>
                  </div>
                  <div class="compare-item-title">
                    <a href="<?= htmlspecialchars($rankedDocument['url']) ?>" target="_blank"><?= htmlspecialchars($rankedDocument['judul']) ?></a>
                  </div>
                  <div style="font-size:0.78rem;color:var(--text-muted);"><?= mb_substr(strip_tags($rankedDocument['isi']), 0, 80) ?>...</div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="compare-col">
          <div class="compare-col-header">
            <span>2. TF-IDF Weighting</span>
            <span class="tag-badge"><?= count($multiAlgorithmComparison['tfidf']['results']) ?> Dokumen</span>
          </div>
          <div class="compare-col-body">
            <?php if (empty($multiAlgorithmComparison['tfidf']['results'])): ?>
              <p style="color:var(--text-muted);font-size:0.82rem;">Tidak ada hasil.</p>
            <?php else: ?>
              <?php foreach ($multiAlgorithmComparison['tfidf']['results'] as $rankIndex => $rankedDocument): ?>
                <div class="compare-item">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.2rem;">
                    <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">#<?= $rankIndex + 1 ?> &bull; DOC #<?= $rankedDocument['id'] ?></span>
                    <span class="compare-item-score"><?= $rankedDocument['score_formatted'] ?></span>
                  </div>
                  <div class="compare-item-title">
                    <a href="<?= htmlspecialchars($rankedDocument['url']) ?>" target="_blank"><?= htmlspecialchars($rankedDocument['judul']) ?></a>
                  </div>
                  <div style="font-size:0.78rem;color:var(--text-muted);"><?= mb_substr(strip_tags($rankedDocument['isi']), 0, 80) ?>...</div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="compare-col">
          <div class="compare-col-header">
            <span>3. Jaccard Similarity</span>
            <span class="tag-badge"><?= count($multiAlgorithmComparison['jaccard']['results']) ?> Dokumen</span>
          </div>
          <div class="compare-col-body">
            <?php if (empty($multiAlgorithmComparison['jaccard']['results'])): ?>
              <p style="color:var(--text-muted);font-size:0.82rem;">Tidak ada hasil.</p>
            <?php else: ?>
              <?php foreach ($multiAlgorithmComparison['jaccard']['results'] as $rankIndex => $rankedDocument): ?>
                <div class="compare-item">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.2rem;">
                    <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">#<?= $rankIndex + 1 ?> &bull; DOC #<?= $rankedDocument['id'] ?></span>
                    <span class="compare-item-score"><?= $rankedDocument['score_formatted'] ?> (<?= $rankedDocument['score_percentage'] ?>%)</span>
                  </div>
                  <div class="compare-item-title">
                    <a href="<?= htmlspecialchars($rankedDocument['url']) ?>" target="_blank"><?= htmlspecialchars($rankedDocument['judul']) ?></a>
                  </div>
                  <div style="font-size:0.78rem;color:var(--text-muted);"><?= mb_substr(strip_tags($rankedDocument['isi']), 0, 80) ?>...</div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
