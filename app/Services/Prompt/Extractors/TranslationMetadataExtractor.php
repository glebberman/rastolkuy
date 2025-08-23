<?php

declare(strict_types=1);

namespace App\Services\Prompt\Extractors;

final readonly class TranslationMetadataExtractor extends BaseMetadataExtractor
{
    public function extract(array $data): array
    {
        $metadata = $this->extractCommonMetadata($data);

        // Специфичные для translation метаданные
        $metadata['translation_quality'] = $this->extractTranslationQuality($data);
        $metadata['sections_count'] = $this->extractSectionsCount($data);
        $metadata['terms_preserved'] = $this->extractPreservedTerms($data);
        $metadata['key_concepts'] = $this->extractKeyConcepts($data);
        $metadata['complexity_metrics'] = $this->extractComplexityMetrics($data);

        return $metadata;
    }

    public function getSchemaType(): string
    {
        return 'translation';
    }

    private function extractTranslationQuality(array $data): array
    {
        $quality = [];

        if (isset($data['translation_quality']) && is_array($data['translation_quality'])) {
            $qualityData = $data['translation_quality'];

            $quality['clarity_score'] = isset($qualityData['clarity_score']) && is_numeric($qualityData['clarity_score'])
                ? (float) $qualityData['clarity_score']
                : null;

            $quality['completeness_score'] = isset($qualityData['completeness_score']) && is_numeric($qualityData['completeness_score'])
                ? (float) $qualityData['completeness_score']
                : null;

            $quality['readability_level'] = $qualityData['readability_level'] ?? null;

            // Вычисляем общий счет качества
            if ($quality['clarity_score'] !== null && $quality['completeness_score'] !== null) {
                $quality['overall_score'] = ($quality['clarity_score'] + $quality['completeness_score']) / 2;
            }
        }

        return $quality;
    }

    private function extractSectionsCount(array $data): array
    {
        $sectionData = [];

        if (isset($data['section_translations']) && is_array($data['section_translations'])) {
            $sections = $data['section_translations'];
            $sectionData['total_sections'] = count($sections);

            $withSummary = 0;
            $totalContentLength = 0;

            foreach ($sections as $section) {
                if (isset($section['summary']) && !empty($section['summary'])) {
                    ++$withSummary;
                }

                if (isset($section['translated_content']) && is_string($section['translated_content'])) {
                    $totalContentLength += mb_strlen($section['translated_content']);
                }
            }

            $sectionData['sections_with_summary'] = $withSummary;
            $sectionData['average_content_length'] = $sectionData['total_sections'] > 0
                ? round($totalContentLength / $sectionData['total_sections'])
                : 0;
        }

        return $sectionData;
    }

    private function extractPreservedTerms(array $data): array
    {
        $termsData = [];

        if (isset($data['legal_terms_preserved']) && is_array($data['legal_terms_preserved'])) {
            $terms = $data['legal_terms_preserved'];
            $termsData['total_terms'] = count($terms);

            $termsWithExplanation = 0;
            $termsWithContext = 0;

            foreach ($terms as $term) {
                if (isset($term['explanation']) && !empty($term['explanation'])) {
                    ++$termsWithExplanation;
                }

                if (isset($term['context']) && !empty($term['context'])) {
                    ++$termsWithContext;
                }
            }

            $termsData['terms_with_explanation'] = $termsWithExplanation;
            $termsData['terms_with_context'] = $termsWithContext;
        }

        return $termsData;
    }

    private function extractKeyConcepts(array $data): array
    {
        $conceptsData = [];

        if (isset($data['key_concepts']) && is_array($data['key_concepts'])) {
            $concepts = $data['key_concepts'];
            $conceptsData['total_concepts'] = count($concepts);

            $importanceLevels = ['high' => 0, 'medium' => 0, 'low' => 0];

            foreach ($concepts as $concept) {
                $importance = $concept['importance'] ?? 'medium';

                if (isset($importanceLevels[$importance])) {
                    ++$importanceLevels[$importance];
                }
            }

            $conceptsData['importance_distribution'] = $importanceLevels;
        }

        return $conceptsData;
    }

    private function extractComplexityMetrics(array $data): array
    {
        $complexity = [];

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadata = $data['metadata'];

            $complexity['original_length'] = $metadata['original_length'] ?? null;
            $complexity['simplified_length'] = $metadata['simplified_length'] ?? null;
            $complexity['complexity_reduction'] = $metadata['complexity_reduction'] ?? null;

            // Вычисляем коэффициент сжатия если есть данные
            if (isset($complexity['original_length'], $complexity['simplified_length'])
                && $complexity['original_length'] > 0) {
                $complexity['compression_ratio'] = round($complexity['simplified_length'] / $complexity['original_length'], 2);
            }
        }

        return $complexity;
    }
}
