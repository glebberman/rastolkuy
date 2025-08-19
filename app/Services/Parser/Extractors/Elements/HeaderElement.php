<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Elements;

class HeaderElement extends DocumentElement
{
    public function __construct(
        string $content,
        public readonly int $level,
        array $position = [],
        array $style = [],
        int $pageNumber = 1,
        array $metadata = [],
    ) {
        parent::__construct('header', $content, $position, $style, $pageNumber, $metadata);
    }

    public function serialize(): array
    {
        return array_merge($this->getBaseData(), [
            'level' => $this->level,
        ]);
    }

    public function getPlainText(): string
    {
        return $this->content;
    }

    public function getLevel(): int
    {
        return $this->level;
    }
}
