<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Validation\InputValidator;
use Illuminate\Support\Facades\Config;

final class AnchorGenerator implements AnchorGeneratorInterface
{
    private readonly string $anchorPrefix;

    private readonly string $anchorSuffix;

    private readonly int $maxTitleLength;

    private readonly bool $transliterationEnabled;

    private readonly bool $normalizeCaseEnabled;

    /**
     * @var array<string>
     */
    private array $usedAnchors = [];

    public function __construct()
    {
        /** @var array<string, mixed> $config */
        $config = Config::get('structure_analysis.anchor_generation', []);
        
        // Безопасное извлечение строковых значений
        /** @var string $prefix */
        $prefix = $config['prefix'] ?? '<!-- SECTION_ANCHOR_';
        /** @var string $suffix */
        $suffix = $config['suffix'] ?? ' -->';
        /** @var int $maxLength */
        $maxLength = $config['max_title_length'] ?? 50;
        
        $this->anchorPrefix = is_string($prefix) ? $prefix : '<!-- SECTION_ANCHOR_';
        $this->anchorSuffix = is_string($suffix) ? $suffix : ' -->';
        $this->maxTitleLength = is_int($maxLength) ? $maxLength : 50;
        $this->transliterationEnabled = (bool) ($config['transliteration'] ?? true);
        $this->normalizeCaseEnabled = (bool) ($config['normalize_case'] ?? true);
    }

    public function generate(string $sectionId, string $title): string
    {
        // Валидация входных данных
        InputValidator::validateAnchorId($sectionId);

        // Валидируем заголовок только если он не пустой (пустые заголовки обрабатываются особо)
        if (!empty(trim($title))) {
            InputValidator::validateSectionTitle($title);
        }

        $baseAnchor = $this->createBaseAnchor($sectionId, $title);
        $uniqueAnchor = $this->ensureUnique($baseAnchor);

        $this->usedAnchors[] = $uniqueAnchor;

        return $this->anchorPrefix . $uniqueAnchor . $this->anchorSuffix;
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

        $start = strlen($this->anchorPrefix);
        $end = strpos($anchor, $this->anchorSuffix);

        if ($end === false) {
            return null;
        }

        return substr($anchor, $start, $end - $start);
    }

    public function isValidAnchor(string $anchor): bool
    {
        return str_starts_with($anchor, $this->anchorPrefix)
               && str_ends_with($anchor, $this->anchorSuffix);
    }

    /**
     * @return array<string>
     */
    public function findAnchorsInText(string $text): array
    {
        // Валидация размера текста для поиска
        InputValidator::validateSearchText($text);

        $pattern = '/' . preg_quote($this->anchorPrefix, '/') . '(.*?)' . preg_quote($this->anchorSuffix, '/') . '/';
        preg_match_all($pattern, $text, $matches);

        return $matches[0] ?? [];
    }

    public function replaceAnchor(string $text, string $anchorId, string $replacement): string
    {
        InputValidator::validateAnchorId($anchorId);
        InputValidator::validateSearchText($text);

        $anchor = $this->anchorPrefix . $anchorId . $this->anchorSuffix;

        return str_replace($anchor, $replacement, $text);
    }

    public function insertAfterAnchor(string $text, string $anchorId, string $insertion): string
    {
        InputValidator::validateAnchorId($anchorId);
        InputValidator::validateSearchText($text);

        $anchor = $this->anchorPrefix . $anchorId . $this->anchorSuffix;

        return str_replace($anchor, $anchor . "\n" . $insertion, $text);
    }

    public function removeAnchor(string $text, string $anchorId): string
    {
        InputValidator::validateAnchorId($anchorId);
        InputValidator::validateSearchText($text);

        $anchor = $this->anchorPrefix . $anchorId . $this->anchorSuffix;

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
        if (mb_strlen($title) > $this->maxTitleLength) {
            $title = mb_substr($title, 0, $this->maxTitleLength);
        }

        // Транслитерация кириллицы (если включена)
        if ($this->transliterationEnabled) {
            $title = $this->transliterate($title);
        }

        // Удаляем специальные символы, оставляем только буквы, цифры, пробелы и дефисы
        $title = preg_replace('/[^\w\s-]/u', '', $title) ?? '';
        
        // Заменяем пробелы и дефисы на подчеркивания
        $title = preg_replace('/[\s-]+/', '_', $title) ?? '';
        $title = trim($title, '_');

        // Нормализация регистра (если включена)
        if ($this->normalizeCaseEnabled) {
            $title = strtolower($title);
        }

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
