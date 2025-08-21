<?php

declare(strict_types=1);

namespace App\Services\Structure\Contracts;

interface AnchorGeneratorInterface
{
    public function generate(string $sectionId, string $title): string;

    public function resetUsedAnchors(): void;
}
