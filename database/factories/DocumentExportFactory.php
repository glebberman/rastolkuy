<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentExport;
use App\Models\DocumentProcessing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentExport>
 */
class DocumentExportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DocumentExport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $formats = [
            DocumentExport::FORMAT_HTML,
            DocumentExport::FORMAT_DOCX,
            DocumentExport::FORMAT_PDF,
        ];
        $format = $formats[array_rand($formats)];

        $fileSize = $this->faker->numberBetween(50000, 5000000); // 50KB - 5MB
        $filename = $this->generateFilename($format);

        return [
            'document_processing_id' => DocumentProcessing::factory(),
            'format' => $format,
            'filename' => $filename,
            'file_path' => 'exports/' . $this->faker->uuid() . '.' . $format,
            'file_size' => $fileSize,
            'download_token' => Str::random(64),
            'expires_at' => $this->faker->dateTimeBetween('now', '+7 days'),
        ];
    }

    /**
     * Create an expired export.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    /**
     * Create an export with specific format.
     */
    public function format(string $format): static
    {
        return $this->state(fn () => [
            'format' => $format,
            'filename' => $this->generateFilename($format),
            'file_path' => 'exports/' . $this->faker->uuid() . '.' . $format,
        ]);
    }

    /**
     * Create an HTML export.
     */
    public function html(): static
    {
        return $this->format(DocumentExport::FORMAT_HTML);
    }

    /**
     * Create a DOCX export.
     */
    public function docx(): static
    {
        return $this->format(DocumentExport::FORMAT_DOCX);
    }

    /**
     * Create a PDF export.
     */
    public function pdf(): static
    {
        return $this->format(DocumentExport::FORMAT_PDF);
    }

    /**
     * Create a large file export.
     */
    public function largeFile(): static
    {
        return $this->state(fn () => [
            'file_size' => $this->faker->numberBetween(5000000, 50000000), // 5MB - 50MB
        ]);
    }

    /**
     * Generate filename based on format.
     */
    private function generateFilename(string $format): string
    {
        $baseNames = [
            'договор_перевод',
            'contract_translation',
            'юридический_документ',
            'legal_document',
            'анализ_рисков',
            'risk_analysis',
        ];

        $baseName = $baseNames[array_rand($baseNames)];
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "{$baseName}_{$timestamp}.{$format}";
    }
}
