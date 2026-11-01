<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Preprocessor.php';

$databaseConnection = Database::getConnection();
$nlpPreprocessor = new Preprocessor($databaseConnection);

$operationStatusMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
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
                'text' => "Dokumen #{$newDocumentIdentifier} berhasil ditambahkan dan diindeks."
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
            'text' => "Dokumen #{$targetDeleteId} berhasil dihapus."
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Dokumen - Morfem</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

  <?php include __DIR__ . '/navbar.php'; ?>

  <div class="container">
    <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:0.25rem;">Manajemen Korpus Berita</h1>
        <p style="color:var(--text-muted);font-size:0.92rem;">
          Pengelolaan dokumen sumber data pencarian.
        </p>
      </div>
      <div>
        <a href="#form-tambah" class="btn btn-primary">+ Tambah Dokumen</a>
      </div>
    </div>

    <?php if ($operationStatusMessage): ?>
      <div style="padding:0.75rem 1rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;font-size:0.9rem;">
        <?= htmlspecialchars($operationStatusMessage['text']) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Daftar Dokumen (<?= count($retrievedCorpusDocuments) ?>)</h2>
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
                    <a href="<?= htmlspecialchars($documentRecord['url']) ?>" target="_blank" style="font-size:0.78rem;color:#15803d;"><?= htmlspecialchars(mb_substr($documentRecord['url'], 0, 45)) ?>...</a>
                  </td>
                  <td style="color:var(--text-muted);font-size:0.82rem;">
                    <?= htmlspecialchars(mb_substr($documentRecord['isi'], 0, 130)) ?>...
                  </td>
                  <td>
                    <span class="tag-badge"><?= $documentRecord['token_count'] ?> kata</span>
                  </td>
                  <td style="text-align:center;">
                    <a href="dokumen.php?delete=<?= $documentRecord['id'] ?>" class="btn btn-outline" style="color:#dc2626;padding:0.25rem 0.5rem;font-size:0.78rem;" onclick="return confirm('Hapus dokumen ini?');">
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

    <div class="card" id="form-tambah">
      <div class="card-header">
        <h2 class="card-title">Tambah Dokumen Baru</h2>
      </div>
      <form action="dokumen.php" method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label class="form-label">Judul Dokumen:</label>
          <input type="text" name="judul" class="form-control" placeholder="Judul artikel atau berita..." required>
        </div>
        <div class="form-group">
          <label class="form-label">URL Rujukan:</label>
          <input type="url" name="url" class="form-control" placeholder="https://" value="https://">
        </div>
        <div class="form-group">
          <label class="form-label">Isi Teks Dokumen:</label>
          <textarea name="isi" class="form-control" placeholder="Isi berita..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Simpan dan Indeks</button>
      </form>
    </div>

  </div>

  <?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
