<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Preprocessor.php';

$databaseConnection = Database::getConnection();
$nlpPreprocessor = new Preprocessor($databaseConnection);

$reindexNotification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reindex') {
    $executionStart = microtime(true);
    $reindexStatistics = $nlpPreprocessor->reindexAll();
    $elapsedSeconds = round((microtime(true) - $executionStart), 2);
    $reindexNotification = "Re-indexing selesai dalam {$elapsedSeconds} detik. {$reindexStatistics['total_docs']} dokumen diproses, {$reindexStatistics['total_tokens']} token disimpan, {$reindexStatistics['unique_terms']} kata unik diindeks.";
}

$sampleInputSentence = $_GET['demo_text'] ?? 'Pembelian roda kursi kantor di toko online berjalan sangat lancar dan memuaskan.';
$samplePipelineOutcome = $nlpPreprocessor->process($sampleInputSentence);

$totalCorpusDocuments = 0;
$totalCorpusTokens = 0;
$uniqueCorpusVocabularyCount = 0;

$documentCountStatement = mysqli_query($databaseConnection, "SELECT COUNT(*) as count FROM berita");
if ($documentCountStatement) { 
    $totalCorpusDocuments = (int)(mysqli_fetch_assoc($documentCountStatement)['count'] ?? 0); 
}

$tokenCountStatement = mysqli_query($databaseConnection, "SELECT COUNT(*) as count FROM token WHERE id != '0'");
if ($tokenCountStatement) { 
    $totalCorpusTokens = (int)(mysqli_fetch_assoc($tokenCountStatement)['count'] ?? 0); 
}

$vocabularyCountStatement = mysqli_query($databaseConnection, "SELECT COUNT(DISTINCT kata) as count FROM token WHERE id != '0'");
if ($vocabularyCountStatement) { 
    $uniqueCorpusVocabularyCount = (int)(mysqli_fetch_assoc($vocabularyCountStatement)['count'] ?? 0); 
}

$highestFrequencyTerms = [];
$topTermsStatement = mysqli_query($databaseConnection, "SELECT kata, SUM(CAST(freq AS UNSIGNED)) as total_frequency, COUNT(DISTINCT id) as document_frequency FROM token WHERE id != '0' GROUP BY kata ORDER BY total_frequency DESC LIMIT 10");
if ($topTermsStatement) {
    while ($termFrequencyRecord = mysqli_fetch_assoc($topTermsStatement)) {
        $highestFrequencyTerms[] = $termFrequencyRecord;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NLP Pipeline & Indexing - Morfem</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .step-box {
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.85rem;
      margin-bottom: 0.65rem;
    }
    .step-title {
      font-weight: 700;
      font-size: 0.85rem;
      color: var(--primary);
      margin-bottom: 0.25rem;
    }
    .token-badge {
      display: inline-block;
      background: #e2e8f0;
      color: #334155;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 0.8rem;
      margin: 2px;
      font-family: ui-monospace, monospace;
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container">
    <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:0.25rem;">NLP Pipeline & Indexing</h1>
        <p style="color:var(--text-muted);font-size:0.92rem;">
          Pembersihan teks, penghilangan stopword, dan stemming Sastrawi untuk korpus dokumen.
        </p>
      </div>

      <form method="POST" onsubmit="return confirm('Jalankan Re-Indexing korpus?');">
        <input type="hidden" name="action" value="reindex">
        <button type="submit" class="btn btn-primary">
          Re-Index Korpus
        </button>
      </form>
    </div>

    <?php if ($reindexNotification): ?>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:0.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;font-size:0.9rem;">
        <?= htmlspecialchars($reindexNotification) ?>
      </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-value"><?= $totalCorpusDocuments ?></div>
        <div class="stat-label">Total Dokumen</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $totalCorpusTokens ?></div>
        <div class="stat-label">Total Pasangan Token</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $uniqueCorpusVocabularyCount ?></div>
        <div class="stat-label">Kata Dasar Unik</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Simulator Preprocessing Kalimat</h2>
      </div>
      <form action="pipeline.php" method="GET" style="margin-bottom:1rem;">
        <div class="form-group">
          <div style="display:flex;gap:0.5rem;">
            <input type="text" name="demo_text" class="form-control" value="<?= htmlspecialchars($sampleInputSentence) ?>" required>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">Proses</button>
          </div>
        </div>
      </form>

      <div class="step-box">
        <div class="step-title">1. Teks Mentah (Raw Input)</div>
        <p style="font-size:0.9rem;"><?= htmlspecialchars($samplePipelineOutcome['raw']) ?></p>
      </div>

      <div class="step-box">
        <div class="step-title">2. Case Folding</div>
        <p style="font-size:0.9rem;"><?= htmlspecialchars($samplePipelineOutcome['case_folded']) ?></p>
      </div>

      <div class="step-box">
        <div class="step-title">3. Cleansing</div>
        <p style="font-size:0.9rem;"><?= htmlspecialchars($samplePipelineOutcome['cleaned']) ?></p>
      </div>

      <div class="step-box">
        <div class="step-title">4. Tokenisasi (<?= count($samplePipelineOutcome['tokens']) ?> Token)</div>
        <div>
          <?php foreach ($samplePipelineOutcome['tokens'] as $tokenItem): ?>
            <span class="token-badge"><?= htmlspecialchars($tokenItem) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="step-box">
        <div class="step-title">5. Stopword Filtering (<?= count($samplePipelineOutcome['filtered']) ?> Token)</div>
        <div>
          <?php foreach ($samplePipelineOutcome['filtered'] as $filteredTokenItem): ?>
            <span class="token-badge"><?= htmlspecialchars($filteredTokenItem) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="step-box" style="border-color:#93c5fd;background:#eff6ff;">
        <div class="step-title" style="color:#1d4ed8;">6. Stemming Sastrawi (<?= count($samplePipelineOutcome['stemmed']) ?> Kata Dasar)</div>
        <div>
          <?php foreach ($samplePipelineOutcome['stemmed'] as $stemmedTokenItem): ?>
            <span class="token-badge" style="background:#dbeafe;color:#1e40af;font-weight:600;"><?= htmlspecialchars($stemmedTokenItem) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Top 10 Kata Dasar Korpus</h2>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:50px;">No</th>
              <th>Kata Dasar</th>
              <th>Term Frequency</th>
              <th>Document Frequency</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($highestFrequencyTerms as $termIndex => $frequencyRow): ?>
              <tr>
                <td><?= $termIndex + 1 ?></td>
                <td><strong><?= htmlspecialchars($frequencyRow['kata']) ?></strong></td>
                <td><?= $frequencyRow['total_frequency'] ?>x</td>
                <td><?= $frequencyRow['document_frequency'] ?> dokumen</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
