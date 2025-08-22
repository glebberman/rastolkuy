<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Prompt;

use App\PromptTemplate;
use App\Services\Prompt\Exceptions\PromptException;
use App\Services\Prompt\TemplateEngine;
use PHPUnit\Framework\TestCase;

class TemplateEngineTest extends TestCase
{
    private TemplateEngine $templateEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateEngine = new TemplateEngine();
    }

    public function testCanRenderSimpleTemplate(): void
    {
        $template = $this->createMockTemplate('Hello {{ name }}!');
        $variables = ['name' => 'World'];

        $result = $this->templateEngine->render($template, $variables);

        $this->assertEquals('Hello World!', $result);
    }

    public function testCanRenderTemplateWithConditionals(): void
    {
        $template = $this->createMockTemplate('Hello{% if name %}, {{ name }}{% endif %}!');

        $result1 = $this->templateEngine->render($template, ['name' => 'World']);
        $result2 = $this->templateEngine->render($template, ['name' => '']);

        $this->assertEquals('Hello, World!', $result1);
        $this->assertEquals('Hello!', $result2);
    }

    public function testCanRenderTemplateWithLoops(): void
    {
        $template = $this->createMockTemplate('Items:{% for item in items %} {{ item }}{% endfor %}');
        $variables = ['items' => ['apple', 'banana', 'cherry']];

        $result = $this->templateEngine->render($template, $variables);

        $this->assertEquals('Items: apple banana cherry', $result);
    }

    public function testValidatesRequiredVariables(): void
    {
        $template = $this->createMockTemplate('Hello {{ name }}!', ['name']);

        $this->expectException(PromptException::class);
        $this->expectExceptionMessage('Missing required variable: name');

        $this->templateEngine->render($template, []);
    }

    public function testCanValidateTemplateVariables(): void
    {
        $template = $this->createMockTemplate(
            'Hello {{ name }}! {{ age }}',
            ['name'],
            ['age'],
        );

        $validation = $this->templateEngine->validate($template, ['name' => 'John']);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    public function testValidationReportsMissingRequiredVariables(): void
    {
        $template = $this->createMockTemplate('Hello {{ name }}!', ['name']);

        $validation = $this->templateEngine->validate($template, []);

        $this->assertFalse($validation['valid']);
        $this->assertContains('Missing required variable: name', $validation['errors']);
    }

    public function testCanExtractVariablesFromTemplate(): void
    {
        $template = 'Hello {{ name }}! Your age is {{ age }}.';

        $variables = $this->templateEngine->extractVariables($template);

        $this->assertEquals(['name', 'age'], $variables);
    }

    public function testCanRenderDirectTemplate(): void
    {
        $template = 'Hello {{ name }}!';
        $variables = ['name' => 'World'];

        $result = $this->templateEngine->renderDirect($template, $variables);

        $this->assertEquals('Hello World!', $result);
    }

    public function testHandlesBooleanVariables(): void
    {
        $template = $this->createMockTemplate('Active: {{ active }}');

        $result1 = $this->templateEngine->render($template, ['active' => true]);
        $result2 = $this->templateEngine->render($template, ['active' => false]);

        $this->assertEquals('Active: true', $result1);
        $this->assertEquals('Active: false', $result2);
    }

    public function testHandlesArrayVariables(): void
    {
        $template = $this->createMockTemplate('Tags: {{ tags }}');
        $variables = ['tags' => ['php', 'laravel', 'testing']];

        $result = $this->templateEngine->render($template, $variables);

        $this->assertEquals('Tags: php, laravel, testing', $result);
    }

    public function testPreviewTemplateFunctionality(): void
    {
        $template = $this->createMockTemplate('Hello {{ name }}!', ['name']);

        $preview = $this->templateEngine->previewTemplate($template, ['name' => 'Test']);

        $this->assertArrayHasKey('rendered', $preview);
        $this->assertArrayHasKey('validation', $preview);
        $this->assertArrayHasKey('character_count', $preview);
        $this->assertEquals('Hello Test!', $preview['rendered']);
        $this->assertTrue($preview['validation']['valid']);
    }

    /**
     * @phpstan-return PromptTemplate
     */
    private function createMockTemplate(
        string $template,
        array $requiredVariables = [],
        array $optionalVariables = [],
    ): PromptTemplate {
        return new PromptTemplate([
            'prompt_system_id' => 1,
            'name' => 'test_template',
            'template' => $template,
            'required_variables' => $requiredVariables,
            'optional_variables' => $optionalVariables,
            'description' => 'Test template description',
        ]);
    }
}
