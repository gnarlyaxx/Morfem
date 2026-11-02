<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Preprocessor.php';

$databaseConnection = Database::getConnection();
$nlpPreprocessor = new Preprocessor($databaseConnection);

$operationStatusMessage = null;

if (isset($_GET['action']) && $_GET['action'] === 'export_sql') {
    $tablesToExport = ['berita', 'token', 'katakunci', 'stopword'];
    $sqlOutput = "-- Morfem Search Engine Database Export\n";
    $sqlOutput .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
    $sqlOutput .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n";

    foreach ($tablesToExport as $tableName) {
        $createTableQuery = mysqli_query($databaseConnection, "SHOW CREATE TABLE `{$tableName}`");
        if ($createTableQuery && $createRow = mysqli_fetch_row($createTableQuery)) {
            $sqlOutput .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sqlOutput .= $createRow[1] . ";\n\n";

            $rowsQuery = mysqli_query($databaseConnection, "SELECT * FROM `{$tableName}`");
            if ($rowsQuery && mysqli_num_rows($rowsQuery) > 0) {
                $valuesBatch = [];
                while ($rowRecord = mysqli_fetch_row($rowsQuery)) {
                    $escapedValues = array_map(function ($columnValue) use ($databaseConnection) {
                        return $columnValue === null ? "NULL" : "'" . mysqli_real_escape_string($databaseConnection, $columnValue) . "'";
                    }, $rowRecord);
                    $valuesBatch[] = "(" . implode(", ", $escapedValues) . ")";
                    if (count($valuesBatch) >= 100) {
                        $sqlOutput .= "INSERT INTO `{$tableName}` VALUES \n" . implode(",\n", $valuesBatch) . ";\n";
                        $valuesBatch = [];
                    }
                }
                if (!empty($valuesBatch)) {
                    $sqlOutput .= "INSERT INTO `{$tableName}` VALUES \n" . implode(",\n", $valuesBatch) . ";\n";
                }
                $sqlOutput .= "\n";
            }
        }
    }
    $sqlOutput .= "COMMIT;\n";

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="morfem_export_' . date('Y-m-d_His') . '.sql"');
    header('Content-Length: ' . strlen($sqlOutput));
    echo $sqlOutput;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $documentTitle = trim($_POST['judul'] ?? '');
    $documentContent = trim($_POST['isi'] ?? '');
    $documentUrl = trim($_POST['url'] ?? '');

    if ($documentTitle !== '' && $documentContent !== '') {
        $maxIdentifierQuery = mysqli_query($databaseConnection, "SELECT MAX(id) as max_id FROM berita");
        $highestIdentifier = 0;
        if ($maxIdentifierQuery) {
            $identifierRow = mysqli_fetch_assoc($maxIdentifierQuery);
            $highestIdentifier = (int)($identifierRow['max_id'] ?? 0);
        }
        $newDocumentIdentifier = $highestIdentifier + 1;

        $insertArticleStatement = mysqli_prepare($databaseConnection, "INSERT INTO berita (id, judul, isi, url) VALUES (?, ?, ?, ?)");
        if ($insertArticleStatement) {
            mysqli_stmt_bind_param($insertArticleStatement, "isss", $newDocumentIdentifier, $documentTitle, $documentContent, $documentUrl);
            mysqli_stmt_execute($insertArticleStatement);
            mysqli_stmt_close($insertArticleStatement);

            $combinedArticleContent = $documentTitle . ' ' . $documentContent;
            $processedArticle = $nlpPreprocessor->process($combinedArticleContent);
            $extractedRootWords = $processedArticle['stemmed'];
            
            if (!empty($extractedRootWords)) {
                $wordOccurrenceCounts = array_count_values($extractedRootWords);
                $tokenInsertStatement = mysqli_prepare($databaseConnection, "INSERT INTO token (id, no, kode, kata, freq) VALUES (?, ?, '0', ?, ?)");
                $sequenceIndex = 1;
                $documentIdString = (string)$newDocumentIdentifier;
                
                foreach ($wordOccurrenceCounts as $stemmedWord => $occurrenceFrequency) {
                    $boundedWord = substr($stemmedWord, 0, 50);
                    $sequenceString = (string)$sequenceIndex;
                    $frequencyString = (string)$occurrenceFrequency;
                    
                    mysqli_stmt_bind_param($tokenInsertStatement, "ssss", $documentIdString, $sequenceString, $boundedWord, $frequencyString);
                    mysqli_stmt_execute($tokenInsertStatement);
                    $sequenceIndex++;
                }
                mysqli_stmt_close($tokenInsertStatement);
            }

            $operationStatusMessage = [
                'type' => 'success',
                'text' => "Dokumen #{$newDocumentIdentifier} berhasil ditambahkan dan langsung diindeks ke database."
            ];
        }
    }
}

if (isset($_GET['delete'])) {
    $targetDeleteId = (int)$_GET['delete'];
    if ($targetDeleteId > 0) {
        $deleteArticleStatement = mysqli_prepare($databaseConnection, "DELETE FROM berita WHERE id = ?");
        mysqli_stmt_bind_param($deleteArticleStatement, "i", $targetDeleteId);
        mysqli_stmt_execute($deleteArticleStatement);
        mysqli_stmt_close($deleteArticleStatement);

        $deleteTokenStatement = mysqli_prepare($databaseConnection, "DELETE FROM token WHERE id = ?");
        $deleteIdString = (string)$targetDeleteId;
        mysqli_stmt_bind_param($deleteTokenStatement, "s", $deleteIdString);
        mysqli_stmt_execute($deleteTokenStatement);
        mysqli_stmt_close($deleteTokenStatement);

        $operationStatusMessage = [
            'type' => 'success',
            'text' => "Dokumen #{$targetDeleteId} berhasil dihapus dari database."
        ];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_stopword') {
    $rawNewWord = trim($_POST['kata_stopword'] ?? '');
    $sanitizedWord = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $rawNewWord), 'UTF-8');

    if ($sanitizedWord !== '') {
        $checkExistingStmt = mysqli_prepare($databaseConnection, "SELECT stopword FROM stopword WHERE stopword = ?");
        mysqli_stmt_bind_param($checkExistingStmt, "s", $sanitizedWord);
        mysqli_stmt_execute($checkExistingStmt);
        mysqli_stmt_store_result($checkExistingStmt);

        if (mysqli_stmt_num_rows($checkExistingStmt) > 0) {
            $operationStatusMessage = [
                'type' => 'warning',
                'text' => "Kata stopword '{$sanitizedWord}' sudah terdaftar di database."
            ];
        } else {
            $insertWordStmt = mysqli_prepare($databaseConnection, "INSERT INTO stopword (stopword) VALUES (?)");
            mysqli_stmt_bind_param($insertWordStmt, "s", $sanitizedWord);
            mysqli_stmt_execute($insertWordStmt);
            mysqli_stmt_close($insertWordStmt);

            $operationStatusMessage = [
                'type' => 'success',
                'text' => "Kata stopword '{$sanitizedWord}' berhasil ditambahkan ke database. Anda dapat menjalankan Re-index di halaman NLP Pipeline agar seluruh korpus memperhitungkan stopword baru ini."
            ];
        }
        mysqli_stmt_close($checkExistingStmt);
    }
}

if (isset($_GET['delete_stopword'])) {
    $targetDeleteWord = trim($_GET['delete_stopword']);
    if ($targetDeleteWord !== '') {
        $deleteWordStmt = mysqli_prepare($databaseConnection, "DELETE FROM stopword WHERE stopword = ?");
        mysqli_stmt_bind_param($deleteWordStmt, "s", $targetDeleteWord);
        mysqli_stmt_execute($deleteWordStmt);
        mysqli_stmt_close($deleteWordStmt);

        $operationStatusMessage = [
            'type' => 'success',
            'text' => "Kata stopword '{$targetDeleteWord}' berhasil dihapus dari database."
        ];
    }
}

$retrievedCorpusDocuments = [];
$corpusDocumentsQuery = mysqli_query($databaseConnection, "SELECT b.id, b.judul, b.isi, b.url, COUNT(t.kata) as token_count 
                                                           FROM berita b 
                                                           LEFT JOIN token t ON b.id = t.id 
                                                           GROUP BY b.id 
                                                           ORDER BY b.id ASC");
if ($corpusDocumentsQuery) {
    while ($articleRecord = mysqli_fetch_assoc($corpusDocumentsQuery)) {
        $retrievedCorpusDocuments[] = $articleRecord;
    }
}

$retrievedStopwords = [];
$stopwordSearchFilter = trim($_GET['filter_stopword'] ?? '');
if ($stopwordSearchFilter !== '') {
    $searchPattern = '%' . $stopwordSearchFilter . '%';
    $searchStopwordStmt = mysqli_prepare($databaseConnection, "SELECT stopword FROM stopword WHERE stopword LIKE ? ORDER BY stopword ASC");
    mysqli_stmt_bind_param($searchStopwordStmt, "s", $searchPattern);
    mysqli_stmt_execute($searchStopwordStmt);
    $stopwordResult = mysqli_stmt_get_result($searchStopwordStmt);
    while ($record = mysqli_fetch_assoc($stopwordResult)) {
        $retrievedStopwords[] = $record['stopword'];
    }
    mysqli_stmt_close($searchStopwordStmt);
} else {
    $allStopwordsQuery = mysqli_query($databaseConnection, "SELECT stopword FROM stopword ORDER BY stopword ASC LIMIT 150");
    if ($allStopwordsQuery) {
        while ($record = mysqli_fetch_assoc($allStopwordsQuery)) {
            $retrievedStopwords[] = $record['stopword'];
        }
    }
}

$totalStopwordsCount = 0;
$countQuery = mysqli_query($databaseConnection, "SELECT COUNT(*) as total FROM stopword");
if ($countQuery && $countRow = mysqli_fetch_assoc($countQuery)) {
    $totalStopwordsCount = (int)$countRow['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Dokumen & Kata - Morfem</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .info-box-sync {
      border: 1px solid var(--border);
      background: var(--bg-card);
      border-left: 4px solid var(--primary);
      border-radius: var(--radius);
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      font-size: 0.88rem;
      line-height: 1.5;
    }
    .badge-word {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: var(--bg-subtle);
      border: 1px solid var(--border);
      padding: 0.2rem 0.55rem;
      border-radius: var(--radius);
      font-size: 0.82rem;
      font-family: ui-monospace, monospace;
      margin: 0.2rem;
    }
    .badge-word a {
      color: #dc2626;
      text-decoration: none;
      font-weight: 700;
      font-size: 0.95rem;
      line-height: 1;
    }
    .badge-word a:hover {
      color: #991b1b;
    }
    .word-cloud-container {
      max-height: 240px;
      overflow-y: auto;
      padding: 0.75rem;
      background: var(--bg-page);
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container">
    <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:0.25rem;">Kelola Dokumen & Kosakata</h1>
        <p style="color:var(--text-muted);font-size:0.92rem;">
          Pengelolaan korpus artikel berita dan kamus stopword secara otomatis ke database.
        </p>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="#form-tambah-dokumen" class="btn btn-primary">+ Tambah Dokumen</a>
        <a href="#section-stopword" class="btn btn-outline">+ Tambah Kata Stopword</a>
        <a href="dokumen.php?action=export_sql" class="btn btn-outline" style="border-color:#16a34a;color:#16a34a;">⬇ Export Database (.sql)</a>
      </div>
    </div>

    <!-- PANDUAN OTOMATISASI & SINKRONISASI HOSTING -->
    <div class="info-box-sync">
      <strong>💡 Mengapa dokumen kemarin tidak muncul setelah di-hosting?</strong><br>
      Database MySQL di <strong>localhost (komputer lokal)</strong> dan <strong>server hosting</strong> adalah dua sistem yang terpisah. 
      Saat Anda menambahkan artikel di localhost, artikel tersebut tersimpan di MySQL lokal XAMPP Anda.
      <ul style="margin:0.5rem 0 0.5rem 1.25rem;padding:0;">
        <li><strong>Jika membuka halaman ini di URL Hosting:</strong> Setiap dokumen atau kata baru yang Anda submit lewat form di bawah ini <em>akan langsung otomatis tersimpan</em> ke database server hosting secara realtime.</li>
        <li><strong>Sinkronisasi dari Localhost ke Hosting:</strong> Klik tombol <strong>Export Database (.sql)</strong> di atas untuk mengunduh dump database terbaru (termasuk seluruh artikel baru), lalu impor file tersebut melalui menu <em>phpMyAdmin</em> di cPanel hosting Anda.</li>
      </ul>
    </div>

    <?php if ($operationStatusMessage): ?>
      <div style="padding:0.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;background:<?= $operationStatusMessage['type'] === 'warning' ? '#fefce8' : '#f0fdf4' ?>;color:<?= $operationStatusMessage['type'] === 'warning' ? '#854d0e' : '#166534' ?>;border:1px solid <?= $operationStatusMessage['type'] === 'warning' ? '#fef08a' : '#bbf7d0' ?>;font-size:0.9rem;">
        <?= htmlspecialchars($operationStatusMessage['text']) ?>
      </div>
    <?php endif; ?>

    <!-- DAFTAR DOKUMEN KORPUS -->
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h2 class="card-title">Daftar Dokumen Korpus (<?= count($retrievedCorpusDocuments) ?>)</h2>
        <span style="font-size:0.82rem;color:var(--text-muted);">Tersimpan di tabel <code>berita</code> & <code>token</code></span>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:50px;">ID</th>
              <th>Judul</th>
              <th>Ringkasan Isi</th>
              <th>Token</th>
              <th style="width:80px;text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($retrievedCorpusDocuments)): ?>
              <tr>
                <td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem;">Tidak ada dokumen.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($retrievedCorpusDocuments as $documentRecord): ?>
                <tr>
                  <td><strong>#<?= $documentRecord['id'] ?></strong></td>
                  <td>
                    <strong><?= htmlspecialchars($documentRecord['judul']) ?></strong><br>
                    <?php if (!empty($documentRecord['url'])): ?>
                      <a href="<?= htmlspecialchars($documentRecord['url']) ?>" target="_blank" style="font-size:0.78rem;color:#15803d;"><?= htmlspecialchars(mb_substr($documentRecord['url'], 0, 45)) ?>...</a>
                    <?php endif; ?>
                  </td>
                  <td style="color:var(--text-muted);font-size:0.82rem;">
                    <?= htmlspecialchars(mb_substr($documentRecord['isi'], 0, 130)) ?>...
                  </td>
                  <td>
                    <span class="tag-badge"><?= $documentRecord['token_count'] ?> kata</span>
                  </td>
                  <td style="text-align:center;">
                    <a href="dokumen.php?delete=<?= $documentRecord['id'] ?>" class="btn btn-outline" style="color:#dc2626;padding:0.25rem 0.5rem;font-size:0.78rem;" onclick="return confirm('Hapus dokumen ini dari database?');">
                      Hapus
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- FORM TAMBAH DOKUMEN -->
    <div class="card" id="form-tambah-dokumen">
      <div class="card-header">
        <h2 class="card-title">Tambah Dokumen Baru (Auto-Index & Simpan ke DB)</h2>
      </div>
      <form action="dokumen.php" method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label class="form-label">Judul Dokumen:</label>
          <input type="text" name="judul" class="form-control" placeholder="Judul artikel atau berita..." required>
        </div>
        <div class="form-group">
          <label class="form-label">URL Rujukan (opsional):</label>
          <input type="url" name="url" class="form-control" placeholder="https://" value="https://">
        </div>
        <div class="form-group">
          <label class="form-label">Isi Teks Dokumen:</label>
          <textarea name="isi" class="form-control" rows="5" placeholder="Masukkan konten atau isi berita..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Simpan dan Indeks Otomatis</button>
      </form>
    </div>

    <!-- MANAJEMEN KATA STOPWORD -->
    <div class="card" id="section-stopword">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
        <div>
          <h2 class="card-title">Kelola Kamus Stopword (<?= $totalStopwordsCount ?> Kata)</h2>
          <p style="color:var(--text-muted);font-size:0.82rem;margin:0;">
            Kata-kata yang diabaikan (dieliminasi) dari pembobotan VSM Information Retrieval.
          </p>
        </div>
        <a href="pipeline.php" class="btn btn-outline" style="font-size:0.8rem;padding:0.35rem 0.75rem;">Re-index di NLP Pipeline &rarr;</a>
      </div>

      <!-- Form Tambah Stopword -->
      <form action="dokumen.php#section-stopword" method="POST" style="margin-bottom:1.25rem;">
        <input type="hidden" name="action" value="add_stopword">
        <label class="form-label">Tambah Kata Stopword Baru:</label>
        <div style="display:flex;gap:0.5rem;max-width:500px;">
          <input type="text" name="kata_stopword" class="form-control" placeholder="Contoh: adapun, yakni, semisal..." required pattern="[a-zA-Z0-9]+">
          <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Tambah Kata</button>
        </div>
      </form>

      <!-- Filter / Pencarian Stopword -->
      <form action="dokumen.php" method="GET" style="margin-bottom:0.75rem;display:flex;gap:0.5rem;max-width:400px;">
        <input type="text" name="filter_stopword" class="form-control" placeholder="Cari kata stopword..." value="<?= htmlspecialchars($stopwordSearchFilter) ?>">
        <button type="submit" class="btn btn-outline" style="white-space:nowrap;">Cari</button>
        <?php if ($stopwordSearchFilter !== ''): ?>
          <a href="dokumen.php#section-stopword" class="btn btn-outline" style="white-space:nowrap;">Reset</a>
        <?php endif; ?>
      </form>

      <!-- Daftar Stopword Tags -->
      <div class="word-cloud-container">
        <?php if (empty($retrievedStopwords)): ?>
          <p style="color:var(--text-muted);font-size:0.85rem;margin:0;">Kata stopword tidak ditemukan.</p>
        <?php else: ?>
          <?php foreach ($retrievedStopwords as $wordCandidate): ?>
            <span class="badge-word">
              <?= htmlspecialchars($wordCandidate) ?>
              <a href="dokumen.php?delete_stopword=<?= urlencode($wordCandidate) ?>#section-stopword" title="Hapus stopword '<?= htmlspecialchars($wordCandidate) ?>'" onclick="return confirm('Hapus kata \'<?= htmlspecialchars($wordCandidate) ?>\' dari daftar stopword?');">&times;</a>
            </span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <p style="color:var(--text-muted);font-size:0.78rem;margin-top:0.5rem;">
        Menampilkan <?= count($retrievedStopwords) ?> dari <?= $totalStopwordsCount ?> total kata stopword di database.
      </p>
    </div>

  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
