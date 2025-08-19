<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Elements;

class TextElement extends DocumentElement
{
    public function __construct(
        string $content,
        array $position = [],
        array $style = [],
        int $pageNumber = 1,
        array $metadata = [],
    ) {
        parent::__construct('text', $content, $position, $style, $pageNumber, $metadata);
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
