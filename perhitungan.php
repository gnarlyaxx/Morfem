<?php
session_start();
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Preprocessor.php';

$databaseConnection = Database::getConnection();
$nlpPreprocessor = new Preprocessor($databaseConnection);

// Ambil query dari URL, fallback ke session (query terakhir dari pencarian), fallback ke default
$simulatedQueryText = trim($_GET['q'] ?? $_SESSION['last_query'] ?? 'kursi roda');
$preprocessingBreakdown = $nlpPreprocessor->process($simulatedQueryText);
$queryStemmedTokens = $preprocessingBreakdown['stemmed'];

// Ambil daftar dokumen korpus
$corpusDocuments = [];
$corpusDocumentQuery = mysqli_query($databaseConnection, "SELECT id, judul FROM berita ORDER BY id ASC");
if ($corpusDocumentQuery) {
    while ($documentRecord = mysqli_fetch_assoc($corpusDocumentQuery)) {
        $corpusDocuments[$documentRecord['id']] = $documentRecord['judul'];
    }
}
$totalDocumentCount = count($corpusDocuments);

// Ambil seluruh vocabulary terindeks beserta frekuensi dokumennya
$vocabularyDocumentFrequencies = [];
$vocabularyTotalOccurrences = [];
$termFrequencyMatrix = []; // [kata][doc_id] => frekuensi

$tokenRecordset = mysqli_query($databaseConnection, "SELECT id, kata, CAST(freq AS UNSIGNED) as frequency FROM token WHERE id != '0'");
if ($tokenRecordset) {
    while ($tokenRecord = mysqli_fetch_assoc($tokenRecordset)) {
        $termIdentifier = $tokenRecord['kata'];
        $documentIdentifier = $tokenRecord['id'];
        $frequencyValue = (int)$tokenRecord['frequency'];

        if (!isset($termFrequencyMatrix[$termIdentifier])) {
            $termFrequencyMatrix[$termIdentifier] = [];
            $vocabularyDocumentFrequencies[$termIdentifier] = 0;
            $vocabularyTotalOccurrences[$termIdentifier] = 0;
        }

        $termFrequencyMatrix[$termIdentifier][$documentIdentifier] = $frequencyValue;
        $vocabularyDocumentFrequencies[$termIdentifier]++;
        $vocabularyTotalOccurrences[$termIdentifier] += $frequencyValue;
    }
}

// Hitung IDF untuk setiap kata: IDF = log10(N / DF)
$inverseDocumentFrequencies = [];
foreach ($vocabularyDocumentFrequencies as $vocabularyTerm => $documentFrequency) {
    if ($documentFrequency > 0 && $totalDocumentCount > 0) {
        $inverseDocumentFrequencies[$vocabularyTerm] = log10($totalDocumentCount / $documentFrequency);
    } else {
        $inverseDocumentFrequencies[$vocabularyTerm] = 0.0;
    }
}

// Perhitungan Vektor Bobot Query
$queryTermFrequencies = array_count_values($queryStemmedTokens);
$queryWeightVector = [];
$queryVectorSquaredSum = 0.0;

foreach ($queryTermFrequencies as $queryTerm => $queryFrequency) {
    $termIdf = $inverseDocumentFrequencies[$queryTerm] ?? 0.0;
    $computedQueryWeight = $queryFrequency * $termIdf;
    $queryWeightVector[$queryTerm] = $computedQueryWeight;
    $queryVectorSquaredSum += pow($computedQueryWeight, 2);
}
$queryVectorLength = sqrt($queryVectorSquaredSum);

// Perhitungan Matematis per Dokumen untuk Query
$mathematicalEvaluationPerDocument = [];

foreach ($corpusDocuments as $documentId => $documentTitle) {
    $overlapCountA = 0;
    $dotProductSum = 0.0;
    $documentVectorSquaredSum = 0.0;
    $totalDistinctWordsInDoc = 0;

    // Hitung bobot seluruh kata di dokumen ini untuk panjang vektor |D|
    foreach ($termFrequencyMatrix as $vocabularyTerm => $documentFrequenciesByTerm) {
        if (isset($documentFrequenciesByTerm[$documentId])) {
            $termFreqInDoc = $documentFrequenciesByTerm[$documentId];
            $totalDistinctWordsInDoc++;
            $termIdf = $inverseDocumentFrequencies[$vocabularyTerm] ?? 0.0;
            $documentTermWeight = $termFreqInDoc * $termIdf;
            $documentVectorSquaredSum += pow($documentTermWeight, 2);

            // Cek jika term ini ada di query
            if (isset($queryWeightVector[$vocabularyTerm])) {
                $overlapCountA += $termFreqInDoc;
                $dotProductSum += ($queryWeightVector[$vocabularyTerm] * $documentTermWeight);
            }
        }
    }

    $documentVectorLength = sqrt($documentVectorSquaredSum);
    $totalQueryTermsCount = count($queryStemmedTokens);

    $disjointQueryTermsB = max(0, $totalQueryTermsCount - $overlapCountA);
    $disjointDocTermsC = max(0, $totalDistinctWordsInDoc - $overlapCountA);

    // 1. TF-IDF Cosine
    $cosineSimilarityScore = ($queryVectorLength > 0 && $documentVectorLength > 0) 
        ? ($dotProductSum / ($queryVectorLength * $documentVectorLength)) 
        : 0.0;

    // 2. Jaccard Similarity: A / (A + B + C)
    $jaccardDenominator = $overlapCountA + $disjointQueryTermsB + $disjointDocTermsC;
    $jaccardSimilarityScore = ($jaccardDenominator > 0) ? ($overlapCountA / $jaccardDenominator) : 0.0;

    // 3. Dice Similarity: 2A / (2A + B + C)
    $diceDenominator = (2 * $overlapCountA) + $disjointQueryTermsB + $disjointDocTermsC;
    $diceSimilarityScore = ($diceDenominator > 0) ? ((2 * $overlapCountA) / $diceDenominator) : 0.0;

    if ($overlapCountA > 0 || $cosineSimilarityScore > 0) {
        $mathematicalEvaluationPerDocument[$documentId] = [
            'document_id' => $documentId,
            'title' => $documentTitle,
            'overlap_A' => $overlapCountA,
            'disjoint_query_B' => $disjointQueryTermsB,
            'disjoint_doc_C' => $disjointDocTermsC,
            'dot_product' => $dotProductSum,
            'doc_vector_length' => $documentVectorLength,
            'cosine_score' => $cosineSimilarityScore,
            'jaccard_score' => $jaccardSimilarityScore,
            'dice_score' => $diceSimilarityScore,
        ];
    }
}

// Urutkan evaluasi matematis berdasarkan Cosine DESC
uasort($mathematicalEvaluationPerDocument, fn($alphaDoc, $betaDoc) => $betaDoc['cosine_score'] <=> $alphaDoc['cosine_score']);

// Urutkan vocabulary untuk matriks global
arsort($vocabularyTotalOccurrences);
$activeVocabularyTerms = array_slice(array_keys($vocabularyTotalOccurrences), 0, 30);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tabel Perhitungan Matematis Komprehensif - Morfem</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .formula-box {
      background: var(--bg-subtle);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 0.75rem 1rem;
      font-size: 0.82rem;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      color: var(--text-main);
      margin-bottom: 1.25rem;
      line-height: 1.6;
    }
    .math-matrix-table th, .math-matrix-table td {
      font-size: 0.76rem;
      padding: 0.35rem 0.5rem;
      font-family: ui-monospace, monospace;
      text-align: right;
    }
    .math-matrix-table th:first-child, .math-matrix-table td:first-child {
      text-align: left;
      font-family: inherit;
      font-weight: 600;
    }
    .section-headline {
      font-size: 1.15rem;
      font-weight: 800;
      margin-top: 1.75rem;
      margin-bottom: 0.5rem;
      border-bottom: 1px solid var(--border);
      padding-bottom: 0.35rem;
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container">
    <div style="margin-bottom: 1.25rem;">
      <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:0.25rem;">Tabel Perhitungan Matematis Komprehensif</h1>
      <p style="color:var(--text-muted);font-size:0.88rem;">
        Formulasi matematis, matriks Term Frequency (TF), Inverse Document Frequency (IDF), bobot ruang vektor (VSM), dan kalkulasi similarity retrieval.
      </p>
    </div>

    <!-- INFO QUERY AKTIF -->
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-header">
        <h2 class="card-title">Analisis Query Aktif</h2>
      </div>
      <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
        <span style="font-size:0.85rem;color:var(--text-muted);">Query dari pencarian:</span>
        <span style="font-size:1rem;font-weight:700;background:#f1f5f9;padding:0.3rem 0.75rem;border-radius:var(--radius-sm);border:1px solid var(--border);">
          <?= htmlspecialchars($simulatedQueryText) ?>
        </span>
        <a href="index.php" style="font-size:0.82rem;color:var(--text-muted);">Ganti query? Cari ulang &rarr;</a>
      </div>
    </div>

    <!-- RUMUS ACUAN -->
    <div class="formula-box">
      <strong>Formulasi Matematis Information Retrieval:</strong><br>
      &bull; <strong>TF (Term Frequency):</strong> Frekuensi kemunculan kata <em>t</em> dalam dokumen <em>d</em>.<br>
      &bull; <strong>IDF (Inverse Document Frequency):</strong> log<sub>10</sub>(N &divide; DF<sub>t</sub>) &mdash; N = <?= $totalDocumentCount ?> total dokumen.<br>
      &bull; <strong>Bobot VSM:</strong> W<sub>d,t</sub> = TF<sub>d,t</sub> &times; IDF<sub>t</sub><br>
      &bull; <strong>Cosine Similarity:</strong> Cosine(Q,D) = (Q &middot; D) &divide; (|Q| &times; |D|) = &Sigma;(W<sub>q,t</sub> &times; W<sub>d,t</sub>) &divide; (&radic;&Sigma;W<sub>q</sub><sup>2</sup> &times; &radic;&Sigma;W<sub>d</sub><sup>2</sup>)<br>
      &bull; <strong>Jaccard:</strong> J(Q,D) = A &divide; (A + B + C) &nbsp;&nbsp;&bull;&nbsp;&nbsp; <strong>Dice:</strong> D(Q,D) = 2A &divide; (2A + B + C)
    </div>

    <!-- TABEL 1: PEMBOBOTAN QUERY -->
    <h2 class="section-headline">1. Pembobotan Vektor Query: "<em><?= htmlspecialchars($simulatedQueryText) ?></em>"</h2>
    <div class="table-responsive" style="margin-bottom: 1.5rem;">
      <table class="data-table math-matrix-table">
        <thead>
          <tr>
            <th>Kata Dasar (Term Query)</th>
            <th>Frekuensi Query (TF<sub>q</sub>)</th>
            <th>Document Frequency (DF<sub>t</sub>)</th>
            <th>Nilai IDF = log<sub>10</sub>(N/DF)</th>
            <th>Bobot Query W<sub>q</sub> = TF<sub>q</sub> &times; IDF</th>
            <th>Kuadrat Bobot (W<sub>q</sub><sup>2</sup>)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($queryTermFrequencies as $queryTerm => $frequencyCount): 
            $calculatedIdf = $inverseDocumentFrequencies[$queryTerm] ?? 0.0;
            $calculatedWeight = $queryWeightVector[$queryTerm] ?? 0.0;
            $termDocFreq = $vocabularyDocumentFrequencies[$queryTerm] ?? 0;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($queryTerm) ?></strong></td>
              <td><?= $frequencyCount ?></td>
              <td><?= $termDocFreq ?> dokumen</td>
              <td><?= number_format($calculatedIdf, 4) ?></td>
              <td><?= number_format($calculatedWeight, 4) ?></td>
              <td><?= number_format(pow($calculatedWeight, 2), 4) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:bold;background:var(--bg-subtle);">
            <td colspan="4">Panjang Vektor Query |Q| = &radic;(&Sigma;W<sub>q</sub><sup>2</sup>)</td>
            <td colspan="2" style="text-align:right;color:var(--primary);"><?= number_format($queryVectorLength, 4) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- TABEL 2: PERHITUNGAN SIMILARITY QUERY TERHADAP DOKUMEN -->
    <h2 class="section-headline">2. Hasil Kalkulasi Similarity VSM, Cosine, Jaccard, dan Dice</h2>
    <div class="table-responsive" style="margin-bottom: 1.5rem;">
      <table class="data-table math-matrix-table">
        <thead>
          <tr>
            <th style="width:50px;">Doc ID</th>
            <th style="text-align:left;">Judul Dokumen</th>
            <th>Irisan (A)</th>
            <th>Dot Product (Q &middot; D)</th>
            <th>Panjang Dokumen (|D|)</th>
            <th>Skor Cosine</th>
            <th>Skor Jaccard</th>
            <th>Skor Dice</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mathematicalEvaluationPerDocument)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:var(--text-muted);padding:1.5rem;">Tidak ada dokumen dengan irisan kata kunci ini.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($mathematicalEvaluationPerDocument as $evaluationRow): ?>
              <tr>
                <td><strong>#<?= $evaluationRow['document_id'] ?></strong></td>
                <td style="text-align:left;"><?= htmlspecialchars(mb_substr($evaluationRow['title'], 0, 40)) ?>...</td>
                <td><?= $evaluationRow['overlap_A'] ?> kata</td>
                <td><?= number_format($evaluationRow['dot_product'], 4) ?></td>
                <td><?= number_format($evaluationRow['doc_vector_length'], 4) ?></td>
                <td style="font-weight:700;color:var(--primary);"><?= number_format($evaluationRow['cosine_score'], 4) ?></td>
                <td><?= number_format($evaluationRow['jaccard_score'], 4) ?></td>
                <td><?= number_format($evaluationRow['dice_score'], 4) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- TABEL 3: MATRIKS TF-IDF KORPUS UTAMA (SAMPLE 30 TERM TERATAS) -->
    <h2 class="section-headline">3. Matriks Global Term Frequency (TF) & IDF Korpus (Top 30 Term)</h2>
    <div class="table-responsive" style="margin-bottom: 1.5rem;">
      <table class="data-table math-matrix-table">
        <thead>
          <tr>
            <th>Kata Dasar (Term)</th>
            <th>DF</th>
            <th>IDF</th>
            <?php foreach (array_keys($corpusDocuments) as $documentIdentifier): ?>
              <th>D<?= $documentIdentifier ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeVocabularyTerms as $activeTerm): 
            $documentFreq = $vocabularyDocumentFrequencies[$activeTerm] ?? 0;
            $termInverseDocFreq = $inverseDocumentFrequencies[$activeTerm] ?? 0.0;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($activeTerm) ?></strong></td>
              <td><?= $documentFreq ?></td>
              <td><?= number_format($termInverseDocFreq, 3) ?></td>
              <?php foreach (array_keys($corpusDocuments) as $documentIdentifier): 
                $termFrequencyInDoc = $termFrequencyMatrix[$activeTerm][$documentIdentifier] ?? 0;
              ?>
                <td style="<?= $termFrequencyInDoc > 0 ? 'font-weight:700;color:var(--text-main);background:#fef9c3;' : 'color:#cbd5e1;' ?>">
                  <?= $termFrequencyInDoc ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
