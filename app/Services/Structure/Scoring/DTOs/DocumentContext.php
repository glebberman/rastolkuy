<?php

declare(strict_types=1);

namespace App\Services\Structure\Scoring\DTOs;

use App\Services\Parser\Extractors\Elements\DocumentElement;

/**
 * Document context for header scoring.
 *
 * Provides aggregate statistics and metadata about the document
 * to help make informed scoring decisions.
 */
final readonly class DocumentContext
{
    /**
     * @param array<DocumentElement> $allElements
     */
    public function __construct(
        public array $allElements,
        public float $averageFontSize,
        public float $mostCommonFontSize,
        public bool $hasVisualData,
        public int $totalElements,
    ) {
    }

    /**
     * Create context from document elements.
     *
     * @param array<DocumentElement> $elements
     */
    public static function fromElements(array $elements): self
    {
        $fontSizes = [];
        $hasVisualData = false;

        foreach ($elements as $element) {
            if ($element->fontSize !== null) {
                $fontSizes[] = $element->fontSize;
                $hasVisualData = true;
            }
        }

        $averageFontSize = !empty($fontSizes) ? array_sum($fontSizes) / count($fontSizes) : 12.0;
        $mostCommonFontSize = !empty($fontSizes) ? self::findMostCommon($fontSizes) : 12.0;

        return new self(
            allElements: $elements,
            averageFontSize: $averageFontSize,
            mostCommonFontSize: $mostCommonFontSize,
            hasVisualData: $hasVisualData,
            totalElements: count($elements),
        );
    }

    /**
     * Find most common value in array.
     *
     * @param array<float> $values
     */
    private static function findMostCommon(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        $frequency = [];

        foreach ($values as $value) {
            $rounded = round($value, 1);
            $frequency[$rounded] = ($frequency[$rounded] ?? 0) + 1;
        }

        arsort($frequency);

        return (float) array_key_first($frequency);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'average_font_size' => round($this->averageFontSize, 2),
            'most_common_font_size' => round($this->mostCommonFontSize, 2),
            'has_visual_data' => $this->hasVisualData,
            'total_elements' => $this->totalElements,
        ];
    }
}
