<?php

declare(strict_types=1);

namespace App\Services\Structure\Contracts;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Structure\DTOs\DocumentSection;

interface SectionDetectorInterface
{
    /**
     * @return array<DocumentSection>
     */
    public function detectSections(ExtractedDocument $document): array;
}
