<?php
class Preprocessor {
    private ?object $sastrawiStemmer = null;
    private array $stopwordLookup = [];
    private mysqli $databaseConnection;

    public function __construct(mysqli $databaseConnection) {
        $this->databaseConnection = $databaseConnection;
        $this->loadStopwordDictionary();
        $this->initializeStemmer();
    }

    private function loadStopwordDictionary(): void {
        $stopwordQuery = mysqli_query($this->databaseConnection, "SELECT stopword FROM stopword");
        if ($stopwordQuery) {
            while ($stopwordRecord = mysqli_fetch_assoc($stopwordQuery)) {
                $normalizedStopword = trim(mb_strtolower($stopwordRecord['stopword']));
                if ($normalizedStopword !== '') {
                    $this->stopwordLookup[$normalizedStopword] = true;
                }
            }
        }
    }

    private function initializeStemmer(): void {
        $composerAutoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (file_exists($composerAutoloadPath)) {
            require_once $composerAutoloadPath;
            if (class_exists('Sastrawi\Stemmer\StemmerFactory')) {
                try {
                    $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
                    $this->sastrawiStemmer = $stemmerFactory->createStemmer();
                } catch (\Throwable $stemmerException) {
                    $this->sastrawiStemmer = null;
                }
            }
        }
    }

    public function caseFold(string $rawText): string {
        return mb_strtolower($rawText, 'UTF-8');
    }

    public function cleanPunctuation(string $rawText): string {
        $alphanumericOnly = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $rawText);
        return trim(preg_replace('/\s+/', ' ', $alphanumericOnly));
    }

    public function tokenize(string $normalizedText): array {
        if (trim($normalizedText) === '') {
            return [];
        }
        $extractedTokens = preg_split('/\s+/', $normalizedText);
        return array_values(array_filter($extractedTokens, fn($tokenCandidate) => strlen($tokenCandidate) > 0));
    }

    public function filterStopwords(array $unfilteredTokens): array {
        $filteredTokens = [];
        foreach ($unfilteredTokens as $tokenCandidate) {
            $trimmedCandidate = trim($tokenCandidate);
            if ($trimmedCandidate !== '' && !isset($this->stopwordLookup[$trimmedCandidate]) && strlen($trimmedCandidate) > 1) {
                $filteredTokens[] = $trimmedCandidate;
            }
        }
        return $filteredTokens;
    }

    public function stemTokens(array $meaningfulTokens): array {
        if (empty($meaningfulTokens)) {
            return [];
        }

        if ($this->sastrawiStemmer !== null) {
            $tokenString = implode(' ', $meaningfulTokens);
            try {
                $stemmedString = $this->sastrawiStemmer->stem($tokenString);
                return preg_split('/\s+/', trim($stemmedString));
            } catch (\Throwable $stemmerException) {
                return $meaningfulTokens;
            }
        }

        return $meaningfulTokens;
    }

    public function process(string $inputText): array {
        $caseFoldedText = $this->caseFold($inputText);
        $sanitizedText = $this->cleanPunctuation($caseFoldedText);
        $tokenList = $this->tokenize($sanitizedText);
        $stopwordFilteredTokens = $this->filterStopwords($tokenList);
        $rootWordTokens = $this->stemTokens($stopwordFilteredTokens);

        return [
            'raw' => $inputText,
            'case_folded' => $caseFoldedText,
            'cleaned' => $sanitizedText,
            'tokens' => $tokenList,
            'filtered' => $stopwordFilteredTokens,
            'stemmed' => $rootWordTokens
        ];
    }

    public function reindexAll(): array {
        mysqli_query($this->databaseConnection, "TRUNCATE TABLE token");

        $corpusQuery = mysqli_query($this->databaseConnection, "SELECT id, judul, isi FROM berita ORDER BY id ASC");
        $processedDocumentCount = 0;
        $persistedTokenCount = 0;
        $uniqueVocabularyTerms = [];

        if ($corpusQuery) {
            $tokenInsertStatement = mysqli_prepare($this->databaseConnection, "INSERT INTO token (id, no, kode, kata, freq) VALUES (?, ?, '0', ?, ?)");

            while ($articleRecord = mysqli_fetch_assoc($corpusQuery)) {
                $processedDocumentCount++;
                $documentIdentifier = (string)$articleRecord['id'];
                
                $combinedDocumentContent = $articleRecord['judul'] . ' ' . $articleRecord['isi'];
                $preprocessingResult = $this->process($combinedDocumentContent);
                $documentRootTokens = $preprocessingResult['stemmed'];

                if (empty($documentRootTokens)) {
                    continue;
                }

                $tokenFrequencies = array_count_values($documentRootTokens);

                $termSequence = 1;
                foreach ($tokenFrequencies as $corpusTerm => $termFrequency) {
                    $truncatedTerm = substr($corpusTerm, 0, 50);
                    $sequenceString = (string)$termSequence;
                    $frequencyString = (string)$termFrequency;

                    mysqli_stmt_bind_param($tokenInsertStatement, "ssss", $documentIdentifier, $sequenceString, $truncatedTerm, $frequencyString);
                    mysqli_stmt_execute($tokenInsertStatement);

                    $persistedTokenCount++;
                    $uniqueVocabularyTerms[$truncatedTerm] = true;
                    $termSequence++;
                }
            }

            mysqli_stmt_close($tokenInsertStatement);
        }

        return [
            'status' => 'success',
            'total_docs' => $processedDocumentCount,
            'total_tokens' => $persistedTokenCount,
            'unique_terms' => count($uniqueVocabularyTerms)
        ];
    }
}
