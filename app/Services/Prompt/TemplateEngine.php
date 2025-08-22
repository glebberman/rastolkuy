<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\PromptTemplate;
use App\Services\Prompt\Exceptions\PromptException;

final class TemplateEngine
{
    private const VARIABLE_PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';
    private const CONDITIONAL_PATTERN = '/\{\%\s*if\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\%\}(.*?)\{\%\s*endif\s*\%\}/s';
    private const LOOP_PATTERN = '/\{\%\s*for\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+in\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\%\}(.*?)\{\%\s*endfor\s*\%\}/s';

    public function render(PromptTemplate $template, array $variables): string
    {
        $this->validateRequiredVariables($template, $variables);

        $content = $template->template;

        $content = $this->processConditionals($content, $variables);
        $content = $this->processLoops($content, $variables);
        $content = $this->processVariables($content, $variables);

        return trim($content);
    }

    public function renderDirect(string $templateContent, array $variables): string
    {
        $content = $this->processConditionals($templateContent, $variables);
        $content = $this->processLoops($content, $variables);
        $content = $this->processVariables($content, $variables);

        return trim($content);
    }

    public function validate(PromptTemplate $template, array $variables): array
    {
        $errors = [];
        $warnings = [];

        $requiredVars = $template->required_variables ?? [];
        $optionalVars = $template->optional_variables ?? [];
        $templateVars = $this->extractVariables($template->template);

        foreach ($requiredVars as $requiredVar) {
            if (!array_key_exists($requiredVar, $variables)) {
                $errors[] = "Missing required variable: $requiredVar";
            }
        }

        foreach ($templateVars as $templateVar) {
            if (!in_array($templateVar, $requiredVars) && !in_array($templateVar, $optionalVars)) {
                $warnings[] = "Variable $templateVar used in template but not declared in schema";
            }
        }

        foreach (array_keys($variables) as $variable) {
            if (!in_array($variable, $templateVars)) {
                $warnings[] = "Variable $variable provided but not used in template";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function extractVariables(string $template): array
    {
        $variables = [];

        preg_match_all(self::VARIABLE_PATTERN, $template, $matches);

        if (!empty($matches[1])) {
            $variables = array_merge($variables, $matches[1]);
        }

        preg_match_all(self::CONDITIONAL_PATTERN, $template, $matches);

        if (!empty($matches[1])) {
            $variables = array_merge($variables, $matches[1]);
        }

        preg_match_all(self::LOOP_PATTERN, $template, $matches);

        if (!empty($matches[2])) {
            $variables = array_merge($variables, $matches[2]);
        }

        return array_unique($variables);
    }

    public function previewTemplate(PromptTemplate $template, array $sampleVariables = []): array
    {
        $defaultSamples = $this->generateDefaultSamples($template);
        $variables = array_merge($defaultSamples, $sampleVariables);

        try {
            $rendered = $this->render($template, $variables);
            $validation = $this->validate($template, $variables);

            return [
                'rendered' => $rendered,
                'variables_used' => $variables,
                'validation' => $validation,
                'character_count' => mb_strlen($rendered),
                'word_count' => str_word_count($rendered),
            ];
        } catch (PromptException $e) {
            return [
                'error' => $e->getMessage(),
                'variables_used' => $variables,
            ];
        }
    }

    private function validateRequiredVariables(PromptTemplate $template, array $variables): void
    {
        $requiredVars = $template->required_variables ?? [];

        foreach ($requiredVars as $requiredVar) {
            if (!array_key_exists($requiredVar, $variables)) {
                throw new PromptException("Missing required variable: $requiredVar");
            }
        }
    }

    private function processVariables(string $content, array $variables): string
    {
        $result = preg_replace_callback(
            self::VARIABLE_PATTERN,
            static function ($matches) use ($variables) {
                $varName = $matches[1];

                if (!array_key_exists($varName, $variables)) {
                    return $matches[0];
                }

                $value = $variables[$varName];

                if (is_array($value)) {
                    return implode(', ', $value);
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                return (string) $value;
            },
            $content,
        );
        
        if ($result === null) {
            throw new PromptException('Failed to process template variables: ' . preg_last_error_msg());
        }
        
        return $result;
    }

    private function processConditionals(string $content, array $variables): string
    {
        $result = preg_replace_callback(
            self::CONDITIONAL_PATTERN,
            static function ($matches) use ($variables) {
                $varName = $matches[1];
                $conditionalContent = $matches[2];

                $shouldInclude = false;

                if (array_key_exists($varName, $variables)) {
                    $value = $variables[$varName];

                    if (is_bool($value)) {
                        $shouldInclude = $value;
                    } elseif (is_array($value)) {
                        $shouldInclude = !empty($value);
                    } elseif (is_string($value)) {
                        $shouldInclude = trim($value) !== '';
                    } elseif (is_numeric($value)) {
                        $shouldInclude = $value != 0;
                    } else {
                        $shouldInclude = $value !== null;
                    }
                }

                return $shouldInclude ? $conditionalContent : '';
            },
            $content,
        );
        
        if ($result === null) {
            throw new PromptException('Failed to process template conditionals: ' . preg_last_error_msg());
        }
        
        return $result;
    }

    private function processLoops(string $content, array $variables): string
    {
        $result = preg_replace_callback(
            self::LOOP_PATTERN,
            function ($matches) use ($variables) {
                $itemVar = $matches[1];
                $arrayVar = $matches[2];
                $loopContent = $matches[3];

                if (!array_key_exists($arrayVar, $variables) || !is_array($variables[$arrayVar])) {
                    return '';
                }

                $loopResult = '';

                foreach ($variables[$arrayVar] as $item) {
                    $loopVariables = array_merge($variables, [$itemVar => $item]);
                    $processedContent = $this->processVariables($loopContent, $loopVariables);
                    $loopResult .= $processedContent;
                }

                return $loopResult;
            },
            $content,
        );

        if ($result === null) {
            throw new PromptException('Failed to process template loops: ' . preg_last_error_msg());
        }
        
        return $result;
    }

    private function generateDefaultSamples(PromptTemplate $template): array
    {
        $samples = [];
        $requiredVars = $template->required_variables ?? [];
        $optionalVars = $template->optional_variables ?? [];

        foreach ($requiredVars as $var) {
            $samples[$var] = $this->generateSampleValue($var);
        }

        foreach ($optionalVars as $var) {
            $samples[$var] = $this->generateSampleValue($var);
        }

        return $samples;
    }

    private function generateSampleValue(string $varName): string
    {
        $sampleValues = [
            'document' => 'Пример юридического документа',
            'text' => 'Пример текста для анализа',
            'content' => 'Содержимое документа',
            'language' => 'простой',
            'format' => 'структурированный',
            'analysis_type' => 'перевод',
            'requirements' => 'Основные требования к анализу',
            'context' => 'Контекст документа',
        ];

        return $sampleValues[$varName] ?? "Пример значения для $varName";
    }
}
