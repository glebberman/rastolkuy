<?php

declare(strict_types=1);

namespace App\Http\Responses\Export;

use App\Models\DocumentExport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ со списком доступных форматов экспорта.
 */
final class FormatsResponse extends JsonResponse
{
    public function __construct()
    {
        $formats = $this->getAvailableFormats();

        $data = [
            'success' => true,
            'message' => 'Список доступных форматов экспорта',
            'data' => [
                'formats' => $formats,
                'total_count' => count($formats),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }

    /**
     * Получает список доступных форматов экспорта.
     *
     * @return array<array<string, mixed>>
     */
    private function getAvailableFormats(): array
    {
        $configFormats = config('export.formats', []);

        $formats = [];

        foreach ([
            DocumentExport::FORMAT_HTML => 'HTML',
            DocumentExport::FORMAT_DOCX => 'Microsoft Word (DOCX)',
            DocumentExport::FORMAT_PDF => 'PDF',
        ] as $key => $name) {
            $formatConfig = is_array($configFormats) && isset($configFormats[$key]) ? $configFormats[$key] : [];
            $enabled = is_array($formatConfig) && isset($formatConfig['enabled']) ? $formatConfig['enabled'] : true;

            if ($enabled) {
                $formats[] = [
                    'key' => $key,
                    'name' => $name,
                    'mime_type' => is_array($formatConfig) && isset($formatConfig['mime_type']) ? $formatConfig['mime_type'] : 'application/octet-stream',
                    'extension' => is_array($formatConfig) && isset($formatConfig['extension']) ? $formatConfig['extension'] : $key,
                    'description' => $this->getFormatDescription($key),
                    'features' => $this->getFormatFeatures($key),
                ];
            }
        }

        return $formats;
    }

    /**
     * Получает описание формата.
     */
    private function getFormatDescription(string $format): string
    {
        return match ($format) {
            DocumentExport::FORMAT_HTML => 'Веб-страница с интерактивными элементами и стилями',
            DocumentExport::FORMAT_DOCX => 'Документ Microsoft Word для редактирования',
            DocumentExport::FORMAT_PDF => 'Готовый к печати PDF документ',
            default => 'Неизвестный формат'
        };
    }

    /**
     * Получает особенности формата.
     *
     * @return array<string>
     */
    private function getFormatFeatures(string $format): array
    {
        return match ($format) {
            DocumentExport::FORMAT_HTML => [
                'Интерактивный просмотр',
                'Поддержка стилей',
                'Быстрое создание',
                'Совместимость с браузерами',
            ],
            DocumentExport::FORMAT_DOCX => [
                'Редактирование текста',
                'Совместимость с Microsoft Word',
                'Сохранение форматирования',
                'Поддержка комментариев',
            ],
            DocumentExport::FORMAT_PDF => [
                'Готов к печати',
                'Фиксированное форматирование',
                'Универсальная совместимость',
                'Защита от изменений',
            ],
            default => []
        };
    }
}