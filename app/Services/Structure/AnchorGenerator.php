<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Services\Structure\Contracts\AnchorGeneratorInterface;

final class AnchorGenerator implements AnchorGeneratorInterface
{
    private const string ANCHOR_PREFIX = '<!-- SECTION_ANCHOR_';
    private const string ANCHOR_SUFFIX = ' -->';
    private const int MAX_TITLE_LENGTH = 50;

    /**
     * @var array<string>
     */
    private array $usedAnchors = [];

    public function generate(string $sectionId, string $title): string
    {
        $baseAnchor = $this->createBaseAnchor($sectionId, $title);
        $uniqueAnchor = $this->ensureUnique($baseAnchor);

        $this->usedAnchors[] = $uniqueAnchor;

        return self::ANCHOR_PREFIX . $uniqueAnchor . self::ANCHOR_SUFFIX;
    }

    public function generateBatch(array $sections): array
    {
        $anchors = [];

        foreach ($sections as $sectionId => $title) {
            $anchors[$sectionId] = $this->generate($sectionId, $title);
        }

        return $anchors;
    }

    public function extractAnchorId(string $anchor): ?string
    {
        if (!$this->isValidAnchor($anchor)) {
            return null;
        }

        $start = strlen(self::ANCHOR_PREFIX);
        $end = strpos($anchor, self::ANCHOR_SUFFIX);

        if ($end === false) {
            return null;
        }

        return substr($anchor, $start, $end - $start);
    }

    public function isValidAnchor(string $anchor): bool
    {
        return str_starts_with($anchor, self::ANCHOR_PREFIX)
               && str_ends_with($anchor, self::ANCHOR_SUFFIX);
    }

    /**
     * @return array<string>
     */
    public function findAnchorsInText(string $text): array
    {
        $pattern = '/' . preg_quote(self::ANCHOR_PREFIX, '/') . '(.*?)' . preg_quote(self::ANCHOR_SUFFIX, '/') . '/';
        preg_match_all($pattern, $text, $matches);

        return $matches[0] ?? [];
    }

    public function replaceAnchor(string $text, string $anchorId, string $replacement): string
    {
        $anchor = self::ANCHOR_PREFIX . $anchorId . self::ANCHOR_SUFFIX;

        return str_replace($anchor, $replacement, $text);
    }

    public function insertAfterAnchor(string $text, string $anchorId, string $insertion): string
    {
        $anchor = self::ANCHOR_PREFIX . $anchorId . self::ANCHOR_SUFFIX;

        return str_replace($anchor, $anchor . "\n" . $insertion, $text);
    }

    public function removeAnchor(string $text, string $anchorId): string
    {
        $anchor = self::ANCHOR_PREFIX . $anchorId . self::ANCHOR_SUFFIX;

        return str_replace($anchor, '', $text);
    }

    public function resetUsedAnchors(): void
    {
        $this->usedAnchors = [];
    }

    /**
     * @return array<string>
     */
    public function getUsedAnchors(): array
    {
        return $this->usedAnchors;
    }

    private function createBaseAnchor(string $sectionId, string $title): string
    {
        // Создаем читаемый ID на основе заголовка
        $normalizedTitle = $this->normalizeTitle($title);

        // Комбинируем ID секции с нормализованным заголовком
        return $sectionId . '_' . $normalizedTitle;
    }

    private function normalizeTitle(string $title): string
    {
        // Удаляем HTML теги
        $title = strip_tags($title);

        // Ограничиваем длину
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $title = mb_substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        // Транслитерация кириллицы
        $title = $this->transliterate($title);

        // Приводим к snake_case
        $title = preg_replace('/[^\w\s-]/', '', $title) ?? '';
        $title = preg_replace('/[\s-]+/', '_', $title) ?? '';
        $title = trim($title, '_');
        $title = strtolower($title);

        // Если результат пустой, возвращаем fallback
        return empty($title) ? 'section' : $title;
    }

    private function transliterate(string $text): string
    {
        $transliterationMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];

        return strtr($text, $transliterationMap);
    }

    private function ensureUnique(string $baseAnchor): string
    {
        $anchor = $baseAnchor;
        $counter = 1;

        while (in_array($anchor, $this->usedAnchors, true)) {
            $anchor = $baseAnchor . '_' . $counter;
            ++$counter;
        }

        return $anchor;
    }
}
