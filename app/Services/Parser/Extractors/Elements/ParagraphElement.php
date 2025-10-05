<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Elements;

class ParagraphElement extends DocumentElement
{
    public function __construct(
        string $content,
        array $position = [],
        array $style = [],
        int $pageNumber = 1,
        array $metadata = [],
        ?float $fontSize = null,
        ?bool $isBold = null,
        ?bool $isItalic = null,
        ?float $topMargin = null,
        ?float $leftMargin = null,
    ) {
        parent::__construct('paragraph', $content, $position, $style, $pageNumber, $metadata, $fontSize, $isBold, $isItalic, $topMargin, $leftMargin);
    }

    public function serialize(): array
    {
        return $this->getBaseData();
    }

    public function getPlainText(): string
    {
        return $this->content;
    }
}
