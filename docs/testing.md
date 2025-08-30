# Testing Documentation

## Обзор тестирования

Проект **Растолкуй** имеет комплексную стратегию тестирования с покрытием **380+ тестов** и **1349+ assertions**. Тестирование построено на принципах TDD (Test-Driven Development) и включает Unit, Feature, Integration тесты с высокими стандартами качества кода.

### Стандарты качества

- **PHPStan Level 9** - максимальный уровень статического анализа
- **PHP CS Fixer** - стандарты форматирования кода PSR-12
- **100% Type Coverage** - полная типизация всех методов
- **Continuous Integration** - автоматическое тестирование через GitHub Actions

## Структура тестов

```
tests/
├── TestCase.php                    # Базовый класс с общими утилитами
├── CreatesApplication.php          # Trait для создания приложения
├── Unit/                          # Unit тесты (изолированные компоненты)
│   ├── CreditServiceTest.php
│   ├── DocumentOwnershipTest.php
│   ├── RolePermissionTest.php
│   └── Services/
│       ├── LLM/                   # LLM сервис тесты
│       ├── Parser/                # Парсинг документов
│       ├── Prompt/                # Система промптов
│       ├── Structure/             # Анализ структуры
│       └── Validation/            # Валидация документов
├── Feature/                       # Feature тесты (полные сценарии)
│   ├── AuthControllerTest.php     # API аутентификации
│   ├── CreditApiTest.php          # API кредитной системы
│   └── PolicyAuthorizationTest.php # Авторизация
├── Integration/                   # Integration тесты
│   └── Services/
│       ├── LLM/
│       └── Prompt/
└── Fixtures/                      # Тестовые данные и файлы
    └── extractors/
        ├── empty.txt
        ├── encoding_test.txt
        └── simple.txt
```

---

## Unit тесты

### CreditServiceTest - Кредитная система

**Покрытие**: Все методы CreditService с граничными случаями

**Ключевые тесты**:
```php
public function testGetUserBalance(): void
{
    $user = User::factory()->create();
    UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 150.75]);
    
    $balance = $this->creditService->getBalance($user);
    $this->assertEquals(150.75, $balance);
}

public function testAddCreditsWithEvents(): void
{
    Event::fake();
    
    $transaction = $this->creditService->addCredits($user, 50.0, 'Test topup');
    
    Event::assertDispatched(CreditAdded::class);
    $this->assertEquals(CreditTransaction::TYPE_TOPUP, $transaction->type);
}

public function testInsufficientBalanceThrowsException(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->creditService->debitCredits($user, 200.0, 'Test debit');
}
```

**Граничные случаи**:
- Отрицательные суммы
- Превышение максимального баланса
- Недостаточный баланс для списания
- Конкурентные операции (race conditions)

### LLM Service Tests

#### ClaudeAdapterTest
**Назначение**: Тестирование интеграции с Claude API

```php
public function testSuccessfulApiCall(): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'Test response']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50]
        ], 200)
    ]);
    
    $response = $this->adapter->generate('Test prompt');
    
    $this->assertInstanceOf(LLMResponse::class, $response);
    $this->assertEquals('Test response', $response->getContent());
}

public function testRateLimitHandling(): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'rate_limit'], 429)
    ]);
    
    $this->expectException(LLMRateLimitException::class);
    $this->adapter->generate('Test prompt');
}
```

#### RateLimiterTest  
**Назначение**: Тестирование ограничения скорости запросов

```php
public function testAllowsRequestsWithinLimit(): void
{
    $limiter = new RateLimiter(maxRequests: 5, windowSeconds: 60);
    
    for ($i = 0; $i < 5; $i++) {
        $this->assertTrue($limiter->attempt('test_key'));
    }
    
    $this->assertFalse($limiter->attempt('test_key'));
}

public function testResetsAfterWindow(): void
{
    $limiter = new RateLimiter(maxRequests: 1, windowSeconds: 1);
    
    $this->assertTrue($limiter->attempt('test_key'));
    $this->assertFalse($limiter->attempt('test_key'));
    
    sleep(2);
    $this->assertTrue($limiter->attempt('test_key'));
}
```

### Parser Service Tests

#### TxtExtractorTest
**Назначение**: Тестирование извлечения текста из TXT файлов

```php
public function testExtractsSimpleTextFile(): void
{
    $filePath = $this->createTestFile("Header 1\n\nParagraph content\n\n- List item");
    
    $result = $this->extractor->extract($filePath);
    
    $this->assertInstanceOf(ExtractedDocument::class, $result);
    $this->assertCount(3, $result->getElements());
}

public function testHandlesEncodingDetection(): void
{
    $filePath = $this->createTestFile("Тест русского текста", 'windows-1251');
    
    $result = $this->extractor->extract($filePath);
    
    $this->assertEquals('UTF-8', $result->getMetadata()['detected_encoding']);
    $this->assertStringContains('Тест русского', $result->getContent());
}
```

#### ElementClassifierTest
**Назначение**: Тестирование классификации элементов документа

```php
public function testClassifiesHeaders(): void
{
    $patterns = [
        '1. Заголовок первого уровня' => 'header',
        'Статья 15. Права и обязанности' => 'header',
        'Обычный параграф текста.' => 'paragraph',
        '- Элемент списка' => 'list_item',
    ];
    
    foreach ($patterns as $text => $expectedType) {
        $result = $this->classifier->classify($text);
        $this->assertEquals($expectedType, $result, "Failed for: $text");
    }
}
```

### Structure Analysis Tests

#### StructureAnalyzerTest
**Назначение**: Тестирование анализа структуры документов

```php
public function testAnalyzesDocumentStructure(): void
{
    $document = $this->createTestDocument([
        '1. Основной раздел',
        'Контент раздела',
        '1.1. Подраздел',
        'Контент подраздела'
    ]);
    
    $result = $this->analyzer->analyze($document);
    
    $this->assertGreaterThan(0, $result->getSectionsCount());
    $this->assertGreaterThan(0.7, $result->averageConfidence);
    $this->assertNotEmpty($result->sections);
}

public function testDetectsHierarchicalStructure(): void
{
    $document = $this->createNestedDocument();
    $result = $this->analyzer->analyze($document);
    
    $topLevelSections = array_filter($result->sections, fn($s) => $s->level === 1);
    $this->assertCount(2, $topLevelSections);
    
    $subsections = array_filter($result->sections, fn($s) => $s->level === 2);
    $this->assertGreaterThan(0, count($subsections));
}
```

#### AnchorGeneratorTest
**Назначение**: Тестирование генерации уникальных якорей

```php
public function testGeneratesUniqueAnchors(): void
{
    $anchors = [];
    $titles = ['Введение', 'Основная часть', 'Заключение'];
    
    foreach ($titles as $index => $title) {
        $anchor = $this->generator->generate((string)$index, $title);
        $this->assertNotContains($anchor, $anchors);
        $anchors[] = $anchor;
    }
}

public function testHandlesDuplicateTitles(): void
{
    $anchor1 = $this->generator->generate('1', 'Заголовок');
    $anchor2 = $this->generator->generate('2', 'Заголовок');
    
    $this->assertNotEquals($anchor1, $anchor2);
    $this->assertStringContains('SECTION_ANCHOR_', $anchor1);
}
```

### Validation Tests

#### DocumentValidatorTest
**Назначение**: Тестирование валидации документов

```php
public function testValidatesSuccessfulDocument(): void
{
    $filePath = $this->createValidTestFile();
    
    $result = $this->validator->validate($filePath);
    
    $this->assertTrue($result->isValid());
    $this->assertEmpty($result->getErrors());
}

public function testRejectsInvalidFileFormat(): void
{
    $filePath = $this->createTestFile('content', '.exe');
    
    $result = $this->validator->validate($filePath);
    
    $this->assertFalse($result->isValid());
    $this->assertContains('Invalid file format', $result->getErrors());
}

public function testHandlesOversizedFiles(): void
{
    $filePath = $this->createLargeTestFile(60 * 1024 * 1024); // 60MB
    
    $result = $this->validator->validate($filePath);
    
    $this->assertFalse($result->isValid());
    $this->assertContains('File size exceeds limit', $result->getErrors());
}
```

---

## Feature тесты

### CreditApiTest - API кредитной системы

**Покрытие**: Все API endpoints с аутентификацией и валидацией

**Тесты аутентификации**:
```php
public function testRequiresAuthentication(): void
{
    $response = $this->getJson('/api/user/credits/balance');
    $response->assertStatus(401);
}

public function testAuthenticatedUserCanAccessBalance(): void
{
    $user = User::factory()->create();
    UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 100]);
    
    $response = $this->actingAs($user)->getJson('/api/user/credits/balance');
    
    $response->assertStatus(200)
            ->assertJson(['balance' => 100.0]);
}
```

**Тесты валидации**:
```php
public function testValidatesTopupAmount(): void
{
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
                    ->postJson('/api/user/credits/topup', ['amount' => -10]);
    
    $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
}

public function testValidatesEnvironmentForTopup(): void
{
    app()['env'] = 'production';
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
                    ->postJson('/api/user/credits/topup', ['amount' => 50]);
    
    $response->assertStatus(403);
}
```

**Тесты пагинации**:
```php
public function testTransactionHistoryPagination(): void
{
    $user = User::factory()->create();
    CreditTransaction::factory()->count(25)->create(['user_id' => $user->id]);
    
    $response = $this->actingAs($user)
                    ->getJson('/api/user/credits/history?per_page=10');
    
    $response->assertStatus(200);
    
    $data = $response->json();
    $this->assertCount(10, $data['data']);
    $this->assertEquals(25, $data['meta']['total']);
}
```

### AuthControllerTest - API аутентификации

**Тесты регистрации**:
```php
public function testUserRegistration(): void
{
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ];
    
    $response = $this->postJson('/api/auth/register', $userData);
    
    $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);
    
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
}

public function testRegistrationValidation(): void
{
    $response = $this->postJson('/api/auth/register', [
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123'
    ]);
    
    $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
}
```

**Тесты авторизации**:
```php
public function testUserLogin(): void
{
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    
    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123'
    ]);
    
    $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
}

public function testInvalidCredentials(): void
{
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'wrongpassword'
    ]);
    
    $response->assertStatus(401);
}
```

---

## Integration тесты

### LLMServiceIntegrationTest
**Назначение**: Тестирование полной интеграции с LLM провайдерами

```php
public function testFullTranslationWorkflow(): void
{
    $this->markTestSkipped('Requires actual API key for integration testing');
    
    $llmService = app(LLMService::class);
    
    $response = $llmService->translateSection(
        'Арендатор обязуется выплачивать арендную плату в размере 50000 рублей ежемесячно.',
        'rental_contract'
    );
    
    $this->assertInstanceOf(LLMResponse::class, $response);
    $this->assertNotEmpty($response->getContent());
    $this->assertGreaterThan(0, $response->getTokensUsed());
}
```

### StructureAnalysisIntegrationTest
**Назначение**: Комплексное тестирование анализа структуры

```php
public function testFullDocumentAnalysisWorkflow(): void
{
    $filePath = $this->createComplexTestDocument();
    
    // Извлечение контента
    $extractor = app(ExtractorManager::class);
    $document = $extractor->extract($filePath);
    
    // Анализ структуры
    $analyzer = app(StructureAnalyzer::class);
    $result = $analyzer->analyze($document);
    
    // Проверки
    $this->assertGreaterThan(5, $result->getSectionsCount());
    $this->assertGreaterThan(0.8, $result->averageConfidence);
    $this->assertTrue($result->hasHierarchy());
}
```

---

## Тестовые утилиты и фабрики

### Фабрики моделей

**UserFactory**:
```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'remember_token' => Str::random(10),
    ];
}
```

**CreditTransactionFactory**:
```php
public function definition(): array
{
    return [
        'user_id' => User::factory(),
        'type' => fake()->randomElement([
            CreditTransaction::TYPE_TOPUP,
            CreditTransaction::TYPE_DEBIT,
            CreditTransaction::TYPE_REFUND
        ]),
        'amount' => fake()->randomFloat(2, 1, 1000),
        'balance_before' => fake()->randomFloat(2, 0, 1000),
        'balance_after' => fake()->randomFloat(2, 0, 1000),
        'description' => fake()->sentence(),
        'metadata' => ['source' => fake()->word()],
    ];
}
```

### Базовый TestCase

**Общие утилиты**:
```php
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Отключение внешних вызовов в тестах
        Http::fake();
        Event::fake();
        Queue::fake();
        
        // Настройка тестовой среды
        config(['app.env' => 'testing']);
    }
    
    protected function createTestFile(string $content, string $extension = '.txt'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_file') . $extension;
        file_put_contents($tempFile, $content);
        
        return $tempFile;
    }
    
    protected function createTestDocument(array $lines): ExtractedDocument
    {
        $content = implode("\n", $lines);
        return new ExtractedDocument($content, 'test.txt', []);
    }
}
```

---

## Стратегии тестирования

### Unit тестирование
- **Изоляция**: Все внешние зависимости мокируются
- **Coverage**: 100% покрытие критически важных методов
- **Edge Cases**: Тестирование граничных случаев и ошибок
- **Performance**: Тесты выполняются быстро (< 100ms каждый)

### Feature тестирование
- **End-to-End**: Тестирование полных пользовательских сценариев
- **Authentication**: Проверка всех уровней доступа
- **Validation**: Тестирование всех правил валидации
- **Database**: Использование RefreshDatabase для изоляции

### Integration тестирование
- **External APIs**: Тестирование с реальными провайдерами (отключено по умолчанию)
- **Service Communication**: Проверка взаимодействия сервисов
- **Event Flow**: Тестирование event-driven архитектуры

### Непрерывная интеграция

**GitHub Actions Workflow**:
```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --level=9
      - name: Run tests
        run: php artisan test --coverage
      - name: Check code style
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Метрики качества

**Текущие показатели**:
- **Тесты**: 380+ (Unit: 250+, Feature: 80+, Integration: 50+)
- **Assertions**: 1349+ проверок
- **PHPStan**: Level 9, 0 ошибок  
- **Code Style**: PSR-12 compliant
- **Type Coverage**: 100%

### Лучшие практики

1. **Именование тестов**: Описательные имена с контекстом
2. **Arrange-Act-Assert**: Четкая структура тестов
3. **Data Providers**: Для тестирования множественных случаев
4. **Mock объекты**: Изоляция внешних зависимостей
5. **Фабрики**: Генерация тестовых данных
6. **Очистка**: Автоматическая очистка после каждого теста

---

*Обновлено: 2025-08-29*