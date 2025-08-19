<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Elements;

class ListElement extends DocumentElement
{
    /**
     * @param array<string> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly string $listType = 'unordered',
        array $position = [],
        array $style = [],
        int $pageNumber = 1,
        array $metadata = [],
    ) {
        $content = implode("\n", $items);
        parent::__construct('list', $content, $position, $style, $pageNumber, $metadata);
    }

    public function serialize(): array
    {
        return array_merge($this->getBaseData(), [
            'items' => $this->items,
            'list_type' => $this->listType,
        ]);
    }

    public function getPlainText(): string
    {
        $marker = $this->listType === 'ordered' ? '1.' : '-';

        return implode("\n", array_map(
            fn (string $item) => $marker . ' ' . $item,
            $this->items,
        ));
    }

    /**
     * @return array<string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getListType(): string
    {
        return $this->listType;
    }
}
