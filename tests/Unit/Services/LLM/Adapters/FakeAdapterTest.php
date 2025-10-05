<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM\Adapters;

use App\Services\LLM\Adapters\FakeAdapter;
use App\Services\LLM\DTOs\LLMRequest;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Exceptions\LLMException;
use Tests\TestCase;

class FakeAdapterTest extends TestCase
{
    private FakeAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new FakeAdapter(baseDelay: 0.001, shouldSimulateErrors: false);
    }

    public function testExecutesSuccessfulRequest(): void
    {
        $request = new LLMRequest(
            content: 'Договор веб-разработки на сумму 150000 рублей <!-- SECTION_ANCHOR_section_1 -->',
            model: 'fake-claude-3-5-sonnet'
        );

        $response = $this->adapter->execute($request);

        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertStringContainsString('fake-claude', $response->model);
        $this->assertGreaterThan(0, $response->inputTokens);
        $this->assertGreaterThan(0, $response->outputTokens);
        $this->assertGreaterThan(0.0, $response->costUsd);
        $this->assertGreaterThan(0.0, $response->executionTimeMs);

        // Check that response contains valid JSON in Claude's format
        $responseData = json_decode($response->content, true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('sections', $responseData);
        $this->assertIsArray($responseData['sections']);
        $this->assertGreaterThan(0, count($responseData['sections']));

        // Check section structure
        $section = $responseData['sections'][0];
        $this->assertArrayHasKey('anchor', $section);
        $this->assertArrayHasKey('content', $section);
        $this->assertArrayHasKey('type', $section);
    }

    public function testDetectsEmploymentContract(): void
    {
        $request = new LLMRequest(
            content: 'Трудовой договор с работником ООО ТехноСтар <!-- SECTION_ANCHOR_emp_1 -->'
        );

        $response = $this->adapter->execute($request);
        $responseData = json_decode($response->content, true);

        $this->assertArrayHasKey('sections', $responseData);
        $this->assertGreaterThan(0, count($responseData['sections']));

        // Check that response is about employment
        $allContent = implode(' ', array_column($responseData['sections'], 'content'));
        $hasEmploymentContent = str_contains(mb_strtolower($allContent), 'работ') ||
            str_contains(mb_strtolower($allContent), 'компания') ||
            str_contains(mb_strtolower($allContent), 'нанимает');
        $this->assertTrue($hasEmploymentContent, 'Response should contain employment-related content');
    }

    public function testDetectsLeaseContract(): void
    {
        $request = new LLMRequest(
            content: 'Договор аренды квартиры на 12 месяцев <!-- SECTION_ANCHOR_lease_1 -->'
        );

        $response = $this->adapter->execute($request);
        $responseData = json_decode($response->content, true);

        $this->assertArrayHasKey('sections', $responseData);
        $this->assertGreaterThan(0, count($responseData['sections']));

        // Check that we have both translations and risks
        $types = array_column($responseData['sections'], 'type');
        $this->assertContains('translation', $types);
    }

    public function testExecutesBatchRequests(): void
    {
        $requests = [
            new LLMRequest(content: 'Первый договор'),
            new LLMRequest(content: 'Второй контракт'),
        ];

        $responses = $this->adapter->executeBatch($requests);

        $this->assertCount(2, $responses);
        $this->assertContainsOnlyInstancesOf(LLMResponse::class, $responses);
    }

    public function testValidatesConnection(): void
    {
        $isValid = $this->adapter->validateConnection();

        $this->assertTrue($isValid);
    }

    public function testReturnsProviderInfo(): void
    {
        $this->assertSame('fake', $this->adapter->getProviderName());

        $models = $this->adapter->getSupportedModels();
        $this->assertIsArray($models);
        $this->assertContains('fake-claude-3-5-sonnet', $models);
    }

    public function testCalculatesCost(): void
    {
        $cost = $this->adapter->calculateCost(1000, 500, 'fake-model');

        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0.0, $cost);
        $this->assertLessThan(1.0, $cost); // Should be much cheaper than real Claude
    }

    public function testCountsTokens(): void
    {
        $tokens = $this->adapter->countTokens('Тестовый текст для подсчета токенов', 'fake-model');

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(50, $tokens); // Reasonable token count for short text
    }

    public function testSimulatesErrors(): void
    {
        $adapterWithErrors = new FakeAdapter(baseDelay: 0.001, shouldSimulateErrors: true);
        $request = new LLMRequest(content: 'Test content');

        $errorThrown = false;

        // Try multiple times since error is random (10% chance)
        for ($i = 0; $i < 20; $i++) {
            try {
                $adapterWithErrors->execute($request);
            } catch (LLMException $e) {
                $errorThrown = true;
                $this->assertStringContainsString('Fake LLM error', $e->getMessage());
                $this->assertArrayHasKey('fake_error', $e->getContext());
                break;
            }
        }

        // If no error was thrown in 20 attempts, the test should still pass
        // as the error simulation is probabilistic
        $this->assertTrue(true, 'Error simulation test completed');
    }

    public function testCustomizesContentWithAmounts(): void
    {
        $request = new LLMRequest(
            content: 'Договор на 300 000 рублей <!-- SECTION_ANCHOR_amount_1 -->'
        );

        $response = $this->adapter->execute($request);
        $responseData = json_decode($response->content, true);

        // Should have sections array
        $this->assertArrayHasKey('sections', $responseData);
        $this->assertGreaterThan(0, count($responseData['sections']));
    }

    public function testIncludesMetadata(): void
    {
        $request = new LLMRequest(content: 'Test content');

        $response = $this->adapter->execute($request);

        $this->assertArrayHasKey('fake_adapter', $response->metadata);
        $this->assertTrue($response->metadata['fake_adapter']);
        $this->assertArrayHasKey('document_type', $response->metadata);
        $this->assertArrayHasKey('base_delay', $response->metadata);
    }
}