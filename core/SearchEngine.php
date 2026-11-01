<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Preprocessor.php';

class SearchEngine {
    private mysqli $databaseConnection;
    private Preprocessor $nlpPreprocessor;

    public function __construct() {
        $this->databaseConnection = Database::getConnection();
        $this->nlpPreprocessor = new Preprocessor($this->databaseConnection);
    }

    public function getPreprocessor(): Preprocessor {
        return $this->nlpPreprocessor;
    }

    public function search(string $userSearchQuery, string $retrievalAlgorithm = 'cosine'): array {
        $computationStartTime = microtime(true);
        $preprocessingOutcome = $this->nlpPreprocessor->process($userSearchQuery);
        $queryStemmedTerms = $preprocessingOutcome['stemmed'];

        if (empty($queryStemmedTerms)) {
            return [
                'query' => $userSearchQuery,
                'method' => $retrievalAlgorithm,
                'terms' => [],
                'results' => [],
                'total_results' => 0,
                'elapsed_time' => 0,
                'processed' => $preprocessingOutcome
            ];
        }

        $this->persistQueryKeywords($queryStemmedTerms);

        $rankedSearchResults = match($retrievalAlgorithm) {
            'tfidf' => $this->executeTfIdfRetrieval($queryStemmedTerms),
            'jaccard' => $this->executeJaccardRetrieval($queryStemmedTerms),
            'dice' => $this->executeDiceRetrieval($queryStemmedTerms),
            default => $this->executeCosineRetrieval($queryStemmedTerms),
        };

        $executionDurationMs = round((microtime(true) - $computationStartTime) * 1000, 2);

        return [
            'query' => $userSearchQuery,
            'method' => $retrievalAlgorithm,
            'terms' => $queryStemmedTerms,
            'results' => $rankedSearchResults,
            'total_results' => count($rankedSearchResults),
            'elapsed_time' => $executionDurationMs,
            'processed' => $preprocessingOutcome
        ];
    }

    public function compare(string $userSearchQuery): array {
        $cosineEvaluation = $this->search($userSearchQuery, 'cosine');
        $tfIdfEvaluation = $this->search($userSearchQuery, 'tfidf');
        $jaccardEvaluation = $this->search($userSearchQuery, 'jaccard');

        return [
            'query' => $userSearchQuery,
            'terms' => $cosineEvaluation['terms'],
            'cosine' => $cosineEvaluation,
            'tfidf' => $tfIdfEvaluation,
            'jaccard' => $jaccardEvaluation,
            'processed' => $cosineEvaluation['processed']
        ];
    }

    private function persistQueryKeywords(array $queryKeywords): void {
        mysqli_query($this->databaseConnection, "DELETE FROM katakunci WHERE id = '0'");
        $keywordStatement = mysqli_prepare($this->databaseConnection, "INSERT INTO katakunci (id, kata) VALUES ('0', ?)");
        if ($keywordStatement) {
            foreach ($queryKeywords as $keywordTerm) {
                $truncatedKeyword = substr($keywordTerm, 0, 15);
                mysqli_stmt_bind_param($keywordStatement, "s", $truncatedKeyword);
                mysqli_stmt_execute($keywordStatement);
            }
            mysqli_stmt_close($keywordStatement);
        }
    }

    private function executeCosineRetrieval(array $queryTerms): array {
        $vectorCosineSql = "SELECT r.id, r.cosine as score, r.A, r.jmlkatakunci, r.B, r.jmlkatadok, r.C,
                                   b.judul, b.isi, b.url
                            FROM (
                                SELECT g.id, f.kata, f.A, f.jmlkatakunci, f.B, g.jmlkatadok, (g.jmlkatadok - f.A) AS C,
                                       (f.A / (SQRT(POW(f.A + f.B, 2)) * SQRT(POW(f.A + (g.jmlkatadok - f.A), 2)))) AS cosine
                                FROM (
                                    SELECT d.id, d.kata, d.A, (e.jmlkatakunci - d.A) AS B, e.jmlkatakunci
                                    FROM (
                                        SELECT y.id, y.kata, SUM(y.freq) AS A
                                        FROM (SELECT kata FROM katakunci WHERE id = '0') AS x
                                        JOIN (SELECT id, kata, CAST(freq AS UNSIGNED) AS freq FROM token WHERE id != '0') AS y
                                          ON x.kata = y.kata
                                        GROUP BY y.id
                                    ) AS d
                                    JOIN (SELECT COUNT(kata) AS jmlkatakunci FROM katakunci WHERE id = '0') AS e
                                ) AS f
                                JOIN (
                                    SELECT id, COUNT(kata) AS jmlkatadok 
                                    FROM token WHERE id != '0'
                                    GROUP BY id
                                ) AS g ON f.id = g.id
                            ) AS r
                            JOIN berita AS b ON r.id = b.id
                            WHERE r.cosine > 0
                            ORDER BY r.cosine DESC";

        return $this->formatVectorSpaceRecordset($vectorCosineSql, $queryTerms);
    }

    private function executeJaccardRetrieval(array $queryTerms): array {
        $jaccardSql = "SELECT r.id, r.jaccard as score, r.A, r.jmlkatakunci, r.B, r.jmlkatadok, r.C,
                              b.judul, b.isi, b.url
                       FROM (
                           SELECT g.id, f.kata, f.A, f.jmlkatakunci, f.B, g.jmlkatadok, (g.jmlkatadok - f.A) AS C,
                                  (f.A / (f.A + f.B + (g.jmlkatadok - f.A))) AS jaccard
                           FROM (
                               SELECT d.id, d.kata, d.A, (e.jmlkatakunci - d.A) AS B, e.jmlkatakunci
                               FROM (
                                   SELECT y.id, y.kata, SUM(y.freq) AS A
                                   FROM (SELECT kata FROM katakunci WHERE id = '0') AS x
                                   JOIN (SELECT id, kata, CAST(freq AS UNSIGNED) AS freq FROM token WHERE id != '0') AS y
                                     ON x.kata = y.kata
                                   GROUP BY y.id
                               ) AS d
                               JOIN (SELECT COUNT(kata) AS jmlkatakunci FROM katakunci WHERE id = '0') AS e
                           ) AS f
                           JOIN (
                               SELECT id, COUNT(kata) AS jmlkatadok 
                               FROM token WHERE id != '0'
                               GROUP BY id
                           ) AS g ON f.id = g.id
                       ) AS r
                       JOIN berita AS b ON r.id = b.id
                       WHERE r.jaccard > 0
                       ORDER BY r.jaccard DESC";

        return $this->formatVectorSpaceRecordset($jaccardSql, $queryTerms);
    }

    private function executeDiceRetrieval(array $queryTerms): array {
        $diceSql = "SELECT r.id, r.dice as score, r.A, r.jmlkatakunci, r.B, r.jmlkatadok, r.C,
                           b.judul, b.isi, b.url
                    FROM (
                        SELECT g.id, f.kata, f.A, f.jmlkatakunci, f.B, g.jmlkatadok, (g.jmlkatadok - f.A) AS C,
                               ((2 * f.A) / ((2 * f.A) + f.B + (g.jmlkatadok - f.A))) AS dice
                        FROM (
                            SELECT d.id, d.kata, d.A, (e.jmlkatakunci - d.A) AS B, e.jmlkatakunci
                            FROM (
                                SELECT y.id, y.kata, SUM(y.freq) AS A
                                FROM (SELECT kata FROM katakunci WHERE id = '0') AS x
                                JOIN (SELECT id, kata, CAST(freq AS UNSIGNED) AS freq FROM token WHERE id != '0') AS y
                                  ON x.kata = y.kata
                                GROUP BY y.id
                            ) AS d
                            JOIN (SELECT COUNT(kata) AS jmlkatakunci FROM katakunci WHERE id = '0') AS e
                        ) AS f
                        JOIN (
                            SELECT id, COUNT(kata) AS jmlkatadok 
                            FROM token WHERE id != '0'
                            GROUP BY id
                        ) AS g ON f.id = g.id
                    ) AS r
                    JOIN berita AS b ON r.id = b.id
                    WHERE r.dice > 0
                    ORDER BY r.dice DESC";

        return $this->formatVectorSpaceRecordset($diceSql, $queryTerms);
    }

    private function executeTfIdfRetrieval(array $queryTerms): array {
        $parameterHolders = implode(',', array_fill(0, count($queryTerms), '?'));
        
        $tfIdfSql = "SELECT v.id, SUM(v.tf_idf) as score, b.judul, b.isi, b.url
                     FROM (
                         SELECT g.id, g.kata, (g.tf * h.idf) AS tf_idf
                         FROM (
                             SELECT a.id, a.kata, (a.freq / b.jmlkata) AS tf
                             FROM (
                                 SELECT id, kata, SUM(CAST(freq AS UNSIGNED)) AS freq 
                                 FROM token 
                                 WHERE id != '0'
                                 GROUP BY id, kata
                             ) AS a
                             JOIN (
                                 SELECT id, SUM(CAST(freq AS UNSIGNED)) AS jmlkata 
                                 FROM token 
                                 WHERE id != '0'
                                 GROUP BY id
                             ) AS b ON a.id = b.id
                         ) AS g
                         JOIN (
                             SELECT kata, LOG10(f.jmldok / e.katajmldok) AS idf
                             FROM (
                                 SELECT kata, COUNT(DISTINCT id) AS katajmldok 
                                 FROM token 
                                 WHERE id != '0'
                                 GROUP BY kata
                             ) AS e
                             JOIN (
                                 SELECT COUNT(DISTINCT id) AS jmldok 
                                 FROM token 
                                 WHERE id != '0'
                             ) AS f
                         ) AS h ON g.kata = h.kata
                         WHERE g.kata IN ($parameterHolders)
                     ) AS v
                     JOIN berita AS b ON v.id = b.id
                     GROUP BY v.id
                     ORDER BY score DESC";

        $tfIdfStatement = mysqli_prepare($this->databaseConnection, $tfIdfSql);
        if (!$tfIdfStatement) {
            return [];
        }

        $typeSignatures = str_repeat('s', count($queryTerms));
        mysqli_stmt_bind_param($tfIdfStatement, $typeSignatures, ...$queryTerms);
        mysqli_stmt_execute($tfIdfStatement);
        $tfIdfRecordset = mysqli_stmt_get_result($tfIdfStatement);

        $retrievedDocumentCollection = [];
        while ($documentRow = mysqli_fetch_assoc($tfIdfRecordset)) {
            $numericScore = (float)$documentRow['score'];
            $retrievedDocumentCollection[] = [
                'id' => $documentRow['id'],
                'score' => $numericScore,
                'score_formatted' => number_format($numericScore, 4),
                'judul' => $documentRow['judul'],
                'isi' => $documentRow['isi'],
                'url' => $documentRow['url'],
                'snippet' => $this->generateMarkedSnippet($documentRow['isi'], $queryTerms),
                'details' => ['tf_idf' => $numericScore]
            ];
        }
        mysqli_stmt_close($tfIdfStatement);

        return $retrievedDocumentCollection;
    }

    private function formatVectorSpaceRecordset(string $sqlQuery, array $highlightTerms): array {
        $rawRecordset = mysqli_query($this->databaseConnection, $sqlQuery);
        if (!$rawRecordset) {
            return [];
        }

        $documentScoreList = [];
        while ($matchedRow = mysqli_fetch_assoc($rawRecordset)) {
            $similarityWeight = (float)$matchedRow['score'];
            $documentScoreList[] = [
                'id' => $matchedRow['id'],
                'score' => $similarityWeight,
                'score_formatted' => number_format($similarityWeight, 4),
                'score_percentage' => round($similarityWeight * 100, 1),
                'judul' => $matchedRow['judul'],
                'isi' => $matchedRow['isi'],
                'url' => $matchedRow['url'],
                'snippet' => $this->generateMarkedSnippet($matchedRow['isi'], $highlightTerms),
                'details' => [
                    'A' => $matchedRow['A'] ?? 0,
                    'B' => $matchedRow['B'] ?? 0,
                    'C' => $matchedRow['C'] ?? 0,
                    'jmlkatakunci' => $matchedRow['jmlkatakunci'] ?? 0,
                    'jmlkatadok' => $matchedRow['jmlkatadok'] ?? 0,
                ]
            ];
        }

        return $documentScoreList;
    }

    private function generateMarkedSnippet(string $fullTextContent, array $targetTerms, int $characterLimit = 220): string {
        $plainSnippet = strip_tags($fullTextContent);
        if (mb_strlen($plainSnippet) > $characterLimit) {
            $plainSnippet = mb_substr($plainSnippet, 0, $characterLimit) . '...';
        }

        foreach ($targetTerms as $targetWord) {
            if (strlen($targetWord) >= 2) {
                $plainSnippet = preg_replace('/(' . preg_quote($targetWord, '/') . ')/iu', '<mark class="highlight">$1</mark>', $plainSnippet);
            }
        }

        return $plainSnippet;
    }
}
