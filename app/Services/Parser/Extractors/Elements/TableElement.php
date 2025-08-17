<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Elements;

class TableElement extends DocumentElement
{
    /**
     * @param array<array<string>> $rows
     * @param array<string>|null $headers
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?array $headers = null,
        array $position = [],
        array $style = [],
        int $pageNumber = 1,
        array $metadata = [],
    ) {
        $content = $this->formatTableAsText($rows, $headers);
        parent::__construct('table', $content, $position, $style, $pageNumber, $metadata);
    }

    public function serialize(): array
    {
        return array_merge($this->getBaseData(), [
            'rows' => $this->rows,
            'headers' => $this->headers,
            'row_count' => count($this->rows),
            'column_count' => !empty($this->rows) ? count($this->rows[0]) : 0,
        ]);
    }

    public function getPlainText(): string
    {
        return $this->content;
    }

    /**
     * @return array<array<string>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string>|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function getRowCount(): int
    {
        return count($this->rows);
    }

    public function getColumnCount(): int
    {
        return !empty($this->rows) ? count($this->rows[0]) : 0;
    }

    /**
     * @param array<array<string>> $rows
     * @param array<string>|null $headers
     */
    private function formatTableAsText(array $rows, ?array $headers): string
    {
        $result = [];

        if ($headers !== null) {
            $result[] = '| ' . implode(' | ', $headers) . ' |';
            $result[] = '|' . str_repeat('---|', count($headers));
        }

        foreach ($rows as $row) {
            $result[] = '| ' . implode(' | ', $row) . ' |';
        }

        return implode("\n", $result);
    }
}
