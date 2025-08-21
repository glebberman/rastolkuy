<?php

declare(strict_types=1);

namespace App\Services\Structure\DTOs;

final readonly class StructureAnalysisResult
{
    /**
     * @param array<DocumentSection> $sections
     * @param array<string, mixed> $statistics
     * @param array<string, mixed> $metadata
     * @param array<string> $warnings
     */
    public function __construct(
        public string $documentId,
        public array $sections,
        public float $analysisTime,
        public float $averageConfidence,
        public array $statistics,
        public array $metadata = [],
        public array $warnings = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return !empty($this->sections);
    }

    public function getSectionsCount(): int
    {
        return count($this->sections);
    }

    public function getTotalSubsectionsCount(): int
    {
        $count = 0;

        foreach ($this->sections as $section) {
            $count += $section->getSubsectionCount();
            $count += count($section->getAllSubsections());
        }

        return $count;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getWarningsCount(): int
    {
        return count($this->warnings);
    }

    /**
     * @return array<DocumentSection>
     */
    public function getSectionsByLevel(int $level): array
    {
        $result = [];

        foreach ($this->sections as $section) {
            if ($section->level === $level) {
                $result[] = $section;
            }

            foreach ($section->getAllSubsections() as $subsection) {
                if ($subsection->level === $level) {
                    $result[] = $subsection;
                }
            }
        }

        return $result;
    }

    /**
     * @return array<DocumentSection>
     */
    public function getAllSections(): array
    {
        $result = $this->sections;

        foreach ($this->sections as $section) {
            array_push($result, ...$section->getAllSubsections());
        }

        return $result;
    }

    public function findSectionById(string $id): ?DocumentSection
    {
        foreach ($this->getAllSections() as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getAllAnchors(): array
    {
        return array_map(
            fn (DocumentSection $section) => $section->anchor,
            $this->getAllSections(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'document_id' => $this->documentId,
            'analysis_time' => $this->analysisTime,
            'average_confidence' => $this->averageConfidence,
            'sections_count' => $this->getSectionsCount(),
            'total_subsections_count' => $this->getTotalSubsectionsCount(),
            'sections' => array_map(
                fn (DocumentSection $section) => $section->serialize(),
                $this->sections,
            ),
            'statistics' => $this->statistics,
            'metadata' => $this->metadata,
            'warnings' => $this->warnings,
            'anchors' => $this->getAllAnchors(),
        ];
    }
}
