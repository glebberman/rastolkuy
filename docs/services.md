# Services & Architecture Documentation

## Архитектурный обзор

Система **Растолкуй** построена на модульной сервис-ориентированной архитектуре, следующей принципам SOLID и лучшим практикам Laravel. Система организована в специализированные слои сервисов, каждый из которых отвечает за определенные аспекты обработки и анализа документов.

### Основные принципы архитектуры

- **Единственная ответственность**: Каждый сервис имеет четко определенную ответственность
- **Dependency Injection**: Все сервисы связаны через контейнер Laravel
- **Interface-based Design**: Ключевые компоненты реализуют контракты для гибкости
- **Event-Driven Architecture**: Сервисы взаимодействуют через события Laravel
- **Configuration-Driven**: Обширное использование конфигураций для гибкости
- **Обработка ошибок**: Комплексная обработка исключений с пользовательскими типами

### Организация сервисного слоя

```
app/Services/
├── Основные сервисы (Бизнес-логика)
│   ├── CreditService.php              - Управление кредитами и биллинг
│   ├── DocumentProcessingService.php  - Оркестрация обработки документов
│   └── AuthService.php               - Аутентификация и авторизация
├── LLM/ (Слой интеграции ИИ)
│   ├── LLMService.php                - Основной фасад LLM сервиса
│   ├── Adapters/                     - Провайдер-специфичные реализации
│   ├── DTOs/                         - Объекты передачи данных
│   └── Support/                      - Rate limiting и retry логика
├── Parser/ (Извлечение документов)
│   ├── Extractors/                   - Экстракторы форматов файлов
│   └── Support/                      - Классификация и утилиты
├── Prompt/ (LLM взаимодействие)
│   ├── PromptManager.php            - Оркестрация выполнения промптов
│   ├── TemplateEngine.php           - Рендеринг шаблонов
│   └── Extractors/                  - Парсинг ответов
├── Structure/ (Анализ документов)
│   ├── StructureAnalyzer.php        - Определение структуры документа
│   ├── AnchorGenerator.php          - Генерация уникальных якорей
│   └── SectionDetector.php          - Алгоритмы определения секций
└── Validation/ (Безопасность и качество)
    ├── DocumentValidator.php        - Многоуровневая валидация
    └── Validators/                  - Специфичные правила валидации
```

---

## Основные сервисы

### 1. CreditService - Финансовый менеджмент

**Назначение**: Управление балансами кредитов пользователей, транзакциями и операциями биллинга.

**Ключевые возможности**:
- Thread-safe операции с балансом через транзакции базы данных
- Event-driven архитектура для операций с кредитами
- Комплексное логирование транзакций
- Конвертация курсов между USD и кредитами
- Асинхронная обработка возвратов
- Настраиваемые политики и лимиты

**Основные методы**:
```php
// Операции с балансом
public function getBalance(User $user): float
public function hasSufficientBalance(User $user, float $amount): bool

// Транзакционные операции
public function addCredits(User $user, float $amount, string $description): CreditTransaction
public function debitCredits(User $user, float $amount, string $description): CreditTransaction
public function refundCredits(User $user, float $amount, string $description): CreditTransaction

// Конвертация и статистика
public function convertUsdToCredits(float $usdAmount): float
public function getUserStatistics(User $user): array
```

**События**:
- `CreditAdded` - События пополнения кредитов
- `CreditDebited` - События использования кредитов
- `CreditRefunded` - События возврата кредитов
- `InsufficientBalance` - Предупреждения о низком балансе

**Конфигурация**: `config/credits.php`
```php
return [
    'initial_balance' => 100,
    'maximum_balance' => 100000,
    'minimum_balance' => 0,
    'usd_to_credits_rate' => 100, // 1 USD = 100 кредитов
    'low_balance_threshold' => 10,
];
```

**Производительность**:
- Транзакции базы данных для консистентности
- Redis кеширование для статистики пользователей (TTL 30 мин)
- Асинхронная обработка возвратов через очереди

### 2. LLMService - Слой интеграции ИИ

**Назначение**: Предоставляет высокоуровневый интерфейс для операций с большими языковыми моделями с абстракцией провайдера.

**Архитектурные паттерны**:
- **Adapter Pattern**: `ClaudeAdapter` реализует `LLMAdapterInterface`
- **Strategy Pattern**: Разные модели для разных уровней сложности
- **Decorator Pattern**: Rate limiting и retry логика оборачивают основные операции

**Ключевые возможности**:
- Провайдер-агностичный интерфейс (сейчас Claude, расширяемый)
- Адаптивный выбор модели (Haiku для простых, Sonnet для сложных)
- Встроенный rate limiting и механизмы повторов
- Расчет стоимости и метрики использования
- Batch обработка

**Основные методы**:
```php
// Единичный перевод
public function translateSection(
    string $sectionContent,
    string $documentType = 'legal_document',
    array $context = [],
    array $options = []
): LLMResponse

// Batch обработка
public function translateBatch(array $sections): Collection<LLMResponse>

// Общего назначения
public function generate(string $prompt, array $options = []): LLMResponse

// Утилиты
public function estimateCost(string $content, ?string $model = null): array
public function validateConnection(): bool
```

**Зависимости**:
- `RateLimiter` - Лимитирование запросов и токенов
- `RetryHandler` - Умные повторы с экспоненциальной задержкой
- `UsageMetrics` - Отслеживание стоимости и производительности
- `ClaudeAdapter` - Интеграция с Claude API

**Конфигурация**: `config/llm.php`
```php
return [
    'default' => 'claude',
    'providers' => [
        'claude' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => 'https://api.anthropic.com',
            'models' => [
                'claude-sonnet-4' => [
                    'max_tokens' => 4096,
                    'pricing' => ['input' => 3.00, 'output' => 15.00] // за 1M токенов
                ]
            ]
        ]
    ]
];
```

### 3. DocumentProcessingService - Оркестрация рабочих процессов

**Назначение**: Управляет полным рабочим процессом обработки документов от загрузки до завершения.

**Ключевые ответственности**:
- Управление загрузкой и хранением файлов
- Диспетчеризация асинхронных задач
- Отслеживание прогресса и управление статусами
- Оценка стоимости
- Управление жизненным циклом документа

**Рабочий процесс**:
1. Загрузка файла и валидация
2. Создание записи в базе данных
3. Диспетчеризация задачи в очередь
4. Фоновая обработка
5. Обновления статуса и уведомления

**Основные методы**:
```php
// Основные операции
public function uploadAndProcess(ProcessDocumentRequest $request, User $user): DocumentProcessing
public function getByUuid(string $uuid): ?DocumentProcessing
public function getFilteredList(array $filters = [], int $perPage = 20): LengthAwarePaginator

// Управление
public function cancelProcessing(DocumentProcessing $documentProcessing): void
public function deleteProcessing(DocumentProcessing $documentProcessing): void

// Аналитика
public function getStatistics(): array
public function estimateProcessingCost(int $fileSizeBytes, ?string $model = null): array
```

### 4. PromptManager - Оркестрация LLM взаимодействий

**Назначение**: Управляет выполнением промптов, рендерингом шаблонов и обработкой ответов.

**Архитектурные паттерны**:
- **Template Pattern**: Структурированные шаблоны промптов с переменными
- **Factory Pattern**: Создает разные типы выполнения промптов
- **Observer Pattern**: Анализ качества и сбор обратной связи

**Ключевые возможности**:
- Система промптов на основе шаблонов
- Валидация и подстановка переменных
- Обогащение структуры документа
- Интеграция анализа качества
- Отслеживание выполнения и метрики

**Основные методы**:
```php
// Выполнение
public function executePrompt(PromptRenderRequest $request): PromptExecutionResult
public function renderTemplate(PromptRenderRequest $request): string

// Валидация и управление
public function validateTemplate(string $systemName, string $templateName, array $variables): array
public function getSystemsByType(string $type): array
public function getExecutionStats(string $systemName, ?string $templateName = null): array
```

**Шаблоны промптов**:
```
Переменные: {{ variable_name }}
Условия: {% if variable %}контент{% endif %}
Циклы: {% for item in array %}{{ item }}{% endfor %}
```

### 5. StructureAnalyzer - Интеллект документов

**Назначение**: Анализирует структуру документа, определяет секции и генерирует навигационные якоря.

**Основной алгоритм**:
1. **Определение секций**: Распознавание заголовков на основе паттернов
2. **Построение иерархии**: Связи родитель-ребенок на основе стека
3. **Генерация якорей**: Уникальные идентификаторы для каждой секции
4. **Оценка уверенности**: Оценка качества на основе машинного обучения

**Ключевые возможности**:
- Определение многоуровневой иерархии секций
- Генерация уникальных якорей для точного референса
- Оценка уверенности с настраиваемыми порогами
- Возможности batch обработки
- Мониторинг производительности с защитой от таймаута

**Основные методы**:
```php
// Основной анализ
public function analyze(ExtractedDocument $document): StructureAnalysisResult
public function analyzeBatch(array $documents): array

// Валидация
public function canAnalyze(ExtractedDocument $document): bool
```

**Конфигурация**: `config/structure_analysis.php`
```php
return [
    'confidence_threshold' => 0.7,
    'max_processing_time' => 30, // секунд
    'patterns' => [
        'header' => [
            '/^\\d+\\.\\s+[А-ЯA-Z]/',
            '/^(Статья|Раздел|Глава)/ui'
        ]
    ]
];
```

### 6. DocumentValidator - Шлюз безопасности и качества

**Назначение**: Многоуровневая система валидации, обеспечивающая безопасность и качество документов.

**Уровни валидации**:
1. **Валидация формата файла** - Проверка MIME типов и расширений
2. **Валидация размера файла** - Настраиваемые ограничения размера
3. **Валидация безопасности** - Обнаружение вредоносных программ и скриптов
4. **Валидация содержимого** - Структура документа и читаемость

**Возможности**:
- Концепция критических валидаторов (остановка при сбоях безопасности)
- Мониторинг производительности с защитой от таймаута
- Комплексное логирование для аудита
- Настраиваемые правила валидации
- Расширяемая архитектура валидатора

**Цепочка валидаторов**:
```php
$validators = [
    new FileFormatValidator(),  // Критический
    new FileSizeValidator(),    // Критический  
    new SecurityValidator(),    // Критический
    new ContentValidator(),     // Не критический
];
```

---

## Точки интеграции

### Event-Driven коммуникация

**События кредитной системы**:
```php
// Отправляются CreditService
CreditAdded::dispatch($user, $transaction, $balanceBefore, $balanceAfter);
CreditDebited::dispatch($user, $transaction, $balanceBefore, $balanceAfter, $amount);
CreditRefunded::dispatch($user, $transaction, $balanceBefore, $balanceAfter, $description);
InsufficientBalance::dispatch($user, $amount, $currentBalance, $operation);
```

**Слушатели**:
- `InvalidateCreditCache` - Очищает кеш статистики кредитов пользователя
- `LogCreditActivity` - Комплексное логирование операций с кредитами
- `SendLowBalanceNotification` - Уведомления пользователя о низком балансе

### Обработка на основе очередей

**Асинхронные задачи**:
- `ProcessDocumentJob` - Основной рабочий процесс обработки документов
- `ProcessLLMBatchTranslationJob` - Batch обработка переводов
- `ProcessCreditRefund` - Асинхронная обработка возвратов
- `RecalculateUserStatistics` - Фоновые обновления статистики

**Конфигурация очередей**:
- Система очередей на основе Redis
- Выделенные очереди для разных операций
- Механизмы повторов с экспоненциальной задержкой
- Dead letter очереди для неудачных задач

### Стратегии кеширования

**Слои кеша**:
- **Статистика пользователей** (TTL 30 мин) - Балансы кредитов и сводки транзакций
- **Валидация подключения LLM** (TTL 5 мин) - Статус подключения API
- **Рендеринг шаблонов** (TTL 1 час) - Общие выходы шаблонов
- **Метаданные документов** (TTL 24 часа) - Характеристики файлов и результаты валидации

**Теги кеша**:
```php
// Тегированное кеширование для селективной инвалидации
Cache::tags(["user_credits_{$user->id}"])->flush();
Cache::tags(["document_processing_{$uuid}"])->flush();
```

---

## Провайдеры сервисов

### LLMServiceProvider

**Ответственности**:
- Регистрация и конфигурация адаптера LLM
- Настройка rate limiter и retry handler
- Инициализация метрик использования
- Конфигурация модели и ценообразования

**Singleton привязки**:
```php
$this->app->singleton(LLMAdapterInterface::class, function() {
    return match(config('llm.default')) {
        'claude' => $this->createClaudeAdapter(),
        default => throw new LLMException("Unsupported provider")
    };
});
```

### StructureAnalysisServiceProvider

**Ответственности**:
- Регистрация компонентов анализа структуры
- Привязки интерфейс-к-реализации
- Публикация конфигурации

### EventServiceProvider

**Ответственности**:
- Связывание событий и слушателей
- Конфигурация event-driven архитектуры

**Основные связи**:
```php
protected $listen = [
    CreditAdded::class => [
        LogCreditActivity::class . '@handleCreditAdded',
        InvalidateCreditCache::class,
    ],
    CreditDebited::class => [
        LogCreditActivity::class . '@handleCreditDebited',
        InvalidateCreditCache::class,
        SendLowBalanceNotification::class . '@handleCreditDebited',
    ],
];
```

---

## Соображения производительности

### Управление ресурсами

**Управление памятью**:
- Потоковая обработка документов для больших файлов
- Подсказки сборщика мусора в долго работающих процессах
- Мониторинг и предупреждения о лимите памяти

**Оптимизация CPU**:
- Защита от таймаута для всех операций
- Настраиваемые лимиты обработки
- Оптимизация размера batch для обработки очередей

### Паттерны масштабируемости

**Горизонтальное масштабирование**:
- Дизайн stateless сервисов
- Асинхронная обработка на основе очередей
- Пулинг подключений к базе данных
- Слой кеша для производительности

**Вертикальное масштабирование**:
- Настраиваемые лимиты ресурсов
- Адаптивный выбор модели на основе сложности
- Эффективная по памяти обработка документов

---

## Архитектура безопасности

### Валидация входных данных

**Многоуровневая валидация**:
1. HTTP валидация запросов (классы FormRequest)
2. Валидация формата и размера файла
3. Сканирование безопасности содержимого
4. Валидация бизнес-правил

### Аутентификация и авторизация

**Слои безопасности**:
- JWT-основанная API аутентификация
- Контроль доступа на основе ролей (RBAC)
- Авторизация на основе политик
- Rate limiting по пользователю/IP

---

## Управление конфигурацией

### Файлы конфигурации сервисов

**Основная конфигурация**:
- `config/llm.php` - LLM провайдеры, модели и ценообразование
- `config/credits.php` - Политики кредитной системы и курсы
- `config/document_validation.php` - Правила валидации и лимиты
- `config/structure_analysis.php` - Пороги анализа и паттерны
- `config/extractors.php` - Настройки обработки документов

### Environment переменные

**Критические настройки**:
```env
# LLM конфигурация
CLAUDE_API_KEY=your-api-key
CLAUDE_DEFAULT_MODEL=claude-3-5-sonnet-20241022
CLAUDE_MAX_TOKENS=4096

# Кредитная система
CREDITS_INITIAL_BALANCE=100
CREDITS_USD_RATE=100
CREDITS_LOW_BALANCE_THRESHOLD=10

# Лимиты обработки
LLM_REQUESTS_PER_MINUTE=60
LLM_TOKENS_PER_MINUTE=40000
```

---

*Обновлено: 2025-08-29*