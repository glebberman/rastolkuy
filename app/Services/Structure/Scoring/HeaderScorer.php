<?php

declare(strict_types=1);

namespace App\Services\Structure\Scoring;

use App\Services\Parser\Extractors\Elements\DocumentElement;
use App\Services\Structure\Scoring\DTOs\DocumentContext;
use App\Services\Structure\Scoring\DTOs\HeaderScore;

/**
 * ML-like header scoring system.
 *
 * Uses visual, textual, and semantic features to determine
 * if a document element is likely to be a header/section title.
 */
final class HeaderScorer
{
    private const VISUAL_WEIGHT = 0.4;

    private const TEXTUAL_WEIGHT = 0.3;

    private const SEMANTIC_WEIGHT = 0.3;

    // Visual scoring thresholds
    private const FONT_SIZE_MULTIPLIER_THRESHOLD = 1.15;

    private const LARGE_TOP_MARGIN_MULTIPLIER = 1.5;

    // Textual scoring thresholds
    private const MAX_HEADER_LENGTH = 150;

    private const MIN_UPPERCASE_RATIO = 0.5;

    // Semantic keywords for legal documents
    private const LEGAL_KEYWORDS = [
        'положения',
        'условия',
        'права',
        'обязанности',
        'термины',
        'порядок',
        'ответственность',
        'гарантии',
        'стороны',
        'предмет',
        'договор',
        'соглашение',
        'приложение',
        'заключение',
        'введение',
    ];

    // Numbering patterns for section titles
    private const NUMBERING_PATTERNS = [
        '/^\d+\.$/',                    // "1."
        '/^\d+\.\d+$/',                 // "1.1"
        '/^\d+\.\d+\.\d+$/',            // "1.1.1"
        '/^[IVXLCDM]+\.$/',             // "I.", "II."
        '/^[а-я]\)$/',                  // "а)", "б)"
        '/^[a-z]\)$/',                  // "a)", "b)"
    ];

    // Legal structure keywords
    private const STRUCTURE_KEYWORDS = [
        'глава',
        'раздел',
        'статья',
        'пункт',
        'подпункт',
        'параграф',
        'часть',
    ];

    public function scoreElement(
        DocumentElement $element,
        DocumentContext $context,
    ): HeaderScore {
        $visualScore = $this->scoreVisual($element, $context);
        $textualScore = $this->scoreTextual($element, $context);
        $semanticScore = $this->scoreSemantic($element, $context);

        $total = (
            $visualScore * self::VISUAL_WEIGHT +
            $textualScore * self::TEXTUAL_WEIGHT +
            $semanticScore * self::SEMANTIC_WEIGHT
        );

        $reason = $this->generateReason($visualScore, $textualScore, $semanticScore, $context);

        return new HeaderScore(
            total: $total,
            visual: $visualScore,
            textual: $textualScore,
            semantic: $semanticScore,
            reason: $reason,
        );
    }

    /**
     * Score visual features of the element.
     *
     * Checks: font size, bold/italic, margins, position.
     * Returns 0.0-1.0 score.
     */
    private function scoreVisual(DocumentElement $element, DocumentContext $context): float
    {
        // If no visual data available, return neutral score
        if (!$context->hasVisualData) {
            return 0.5;
        }

        $score = 0.0;
        $features = 0;

        // Check font size (larger than average)
        if ($element->fontSize !== null && $context->averageFontSize > 0) {
            $features++;
            $fontRatio = $element->fontSize / $context->averageFontSize;

            if ($fontRatio >= self::FONT_SIZE_MULTIPLIER_THRESHOLD) {
                $score += min(1.0, ($fontRatio - 1.0) * 2); // Scale: 1.15x = 0.3, 1.5x = 1.0
            }
        }

        // Check bold text
        if ($element->isBold !== null) {
            $features++;

            if ($element->isBold) {
                $score += 1.0;
            }
        }

        // Check italic (headers are usually not italic, but can be)
        if ($element->isItalic !== null) {
            $features++;

            if ($element->isItalic) {
                $score += 0.3; // Slight bonus, not strong indicator
            }
        }

        // Check top margin (headers usually have larger top margins)
        if ($element->topMargin !== null) {
            $features++;
            $avgTopMargin = $this->calculateAverageTopMargin($context);

            if ($avgTopMargin > 0 && $element->topMargin >= $avgTopMargin * self::LARGE_TOP_MARGIN_MULTIPLIER) {
                $score += 0.8;
            }
        }

        // Check left margin (headers usually align to left or have consistent indentation)
        if ($element->leftMargin !== null) {
            $features++;

            if ($element->leftMargin <= 10) { // Close to left edge
                $score += 0.6;
            }
        }

        // Normalize score by number of features checked
        return $features > 0 ? min(1.0, $score / $features) : 0.5;
    }

    /**
     * Score textual features of the element.
     *
     * Checks: length, capitalization, punctuation, formatting.
     * Returns 0.0-1.0 score.
     */
    private function scoreTextual(DocumentElement $element, DocumentContext $context): float
    {
        $content = trim($element->content);
        $score = 0.0;
        $features = 0;

        // Check length (headers are usually short)
        $features++;
        $length = mb_strlen($content);

        if ($length > 0) {
            // Scale: 0-50 chars = 1.0, 50-150 chars = 0.5-1.0, >150 = 0
            if ($length <= 50) {
                $score += 1.0;
            } elseif ($length <= self::MAX_HEADER_LENGTH) {
                $score += 1.0 - (($length - 50) / 100) * 0.5;
            }
            // else: length > MAX_HEADER_LENGTH, no score added
        }

        // Check if ends with period (headers usually don't, except numbering)
        $features++;

        if (!preg_match('/\.$/', $content) || preg_match('/^\d+\.$/', $content)) {
            $score += 0.8;
        }

        // Check capitalization (first letter should be uppercase)
        $features++;

        if (preg_match('/^[A-ZА-Я\d]/u', $content)) {
            $score += 1.0;
        }

        // Check for punctuation in middle (headers usually have minimal punctuation)
        $features++;
        $middleContent = mb_substr($content, 1, -1);
        $punctuationCount = preg_match_all('/[,;:!?]/', $middleContent);

        if ($punctuationCount === 0) {
            $score += 1.0;
        } elseif ($punctuationCount === 1) {
            $score += 0.5;
        }

        // Check uppercase ratio (HEADERS CAN BE ALL CAPS)
        $features++;
        $uppercaseRatio = $this->calculateUppercaseRatio($content);

        if ($uppercaseRatio >= self::MIN_UPPERCASE_RATIO) {
            $score += $uppercaseRatio; // Full caps = 1.0
        }

        // Normalize score by number of features checked (features is always > 0 here)
        return min(1.0, $score / $features);
    }

    /**
     * Score semantic features of the element.
     *
     * Checks: legal keywords, numbering, structure keywords, position in document.
     * Returns 0.0-1.0 score.
     */
    private function scoreSemantic(DocumentElement $element, DocumentContext $context): float
    {
        $content = mb_strtolower(trim($element->content));
        $score = 0.0;
        $features = 0;

        // Check for legal keywords
        $features++;
        $keywordMatches = 0;

        foreach (self::LEGAL_KEYWORDS as $keyword) {
            if (str_contains($content, $keyword)) {
                $keywordMatches++;
            }
        }

        if ($keywordMatches > 0) {
            $score += min(1.0, $keywordMatches * 0.3);
        }

        // Check for numbering patterns
        $features++;
        $firstWord = preg_split('/\s+/u', $content)[0] ?? '';

        foreach (self::NUMBERING_PATTERNS as $pattern) {
            if (preg_match($pattern, $firstWord)) {
                $score += 1.0;
                break;
            }
        }

        // Check for structure keywords
        $features++;

        foreach (self::STRUCTURE_KEYWORDS as $keyword) {
            if (str_contains($content, $keyword)) {
                $score += 1.0;
                break;
            }
        }

        // Check position in document (introduction/conclusion keywords)
        $features++;
        $elementIndex = $this->findElementIndex($element, $context);

        if ($elementIndex !== null) {
            $relativePosition = $elementIndex / max(1, $context->totalElements);

            // "Введение" usually at the start
            if ($relativePosition < 0.1 && str_contains($content, 'введение')) {
                $score += 1.0;
            }

            // "Заключение" usually at the end
            if ($relativePosition > 0.8 && str_contains($content, 'заключение')) {
                $score += 1.0;
            }
        }

        // Normalize score by number of features checked (features is always > 0 here)
        return min(1.0, $score / $features);
    }

    private function calculateAverageTopMargin(DocumentContext $context): float
    {
        $margins = [];

        foreach ($context->allElements as $element) {
            if ($element->topMargin !== null) {
                $margins[] = $element->topMargin;
            }
        }

        return !empty($margins) ? array_sum($margins) / count($margins) : 0.0;
    }

    private function calculateUppercaseRatio(string $content): float
    {
        $letters = preg_replace('/[^a-zA-Zа-яА-Я]/u', '', $content);

        if ($letters === null || $letters === '') {
            return 0.0;
        }

        $letterCount = mb_strlen($letters);

        $uppercaseLetters = preg_replace('/[^A-ZА-Я]/u', '', $content);
        $uppercaseCount = $uppercaseLetters !== null ? mb_strlen($uppercaseLetters) : 0;

        return $uppercaseCount / $letterCount;
    }

    private function findElementIndex(DocumentElement $element, DocumentContext $context): ?int
    {
        foreach ($context->allElements as $index => $contextElement) {
            if ($contextElement === $element) {
                return $index;
            }
        }

        return null;
    }

    private function generateReason(
        float $visualScore,
        float $textualScore,
        float $semanticScore,
        DocumentContext $context,
    ): string {
        $reasons = [];

        if ($visualScore >= 0.7) {
            $reasons[] = 'strong visual indicators';
        } elseif ($visualScore >= 0.5) {
            $reasons[] = 'some visual indicators';
        }

        if ($textualScore >= 0.7) {
            $reasons[] = 'header-like text structure';
        }

        if ($semanticScore >= 0.7) {
            $reasons[] = 'legal/structural keywords';
        }

        if (empty($reasons)) {
            return 'no strong header indicators';
        }

        return implode(', ', $reasons);
    }
}
