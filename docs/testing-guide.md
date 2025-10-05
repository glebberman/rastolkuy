# 🧪 Руководство по тестированию Legal Translator

## 📋 Обзор

Данное руководство описывает процедуры ручного тестирования системы перевода документов Legal Translator, а также работу с автоматизированными тестами.

## 🎯 Типы тестирования

### 1. Автоматизированные тесты

#### Unit тесты
```bash
# Запуск всех unit тестов
php artisan test --testsuite=Unit

# Запуск тестов конкретного сервиса
php artisan test tests/Unit/Services/Export/ContentProcessorTest.php

# Запуск бенчмарк тестов
php artisan test tests/Unit/Services/Export/ContentProcessorBenchmarkTest.php
```

#### Feature тесты
```bash
# Запуск всех feature тестов
php artisan test --testsuite=Feature

# Тестирование API документооборота
php artisan test tests/Feature/DocumentProcessingApiTest.php

# Интеграционные тесты
php artisan test tests/Feature/DocumentTranslationIntegrationTest.php
```

#### Полный запуск тестов
```bash
# Все тесты с покрытием
php artisan test --coverage

# Быстрый запуск без покрытия
php artisan test

# Тесты с подробной информацией
php artisan test --verbose
```

### 2. Статический анализ

```bash
# PHPStan Level 9 анализ
./vendor/bin/phpstan analyse --level=9

# Анализ конкретной папки
./vendor/bin/phpstan analyse app/Services/Export/ --level=9

# Быстрая команда через task
task phpstan
```

### 3. Ручное тестирование

## 📄 Тестовые данные

### Основные фикстуры

#### 1. Договор веб-разработки (`document_translation_response.json`)
- **Описание**: Полный договор на создание сайта за 150,000₽
- **Секции**: 7 разделов от предмета до разрешения споров
- **Риски**: 6 различных типов рисков и предупреждений
- **Использование**: Основной тест-кейс для всех сценариев

#### 2. Трудовой договор (`contract_employment_response.json`)
- **Описание**: Договор с IT-разработчиком зарплатой 250,000₽
- **Секции**: 7 разделов от сторон до расторжения
- **Риски**: 5 рисков включая материальную ответственность
- **Использование**: Тестирование HR-документов

#### 3. Договор аренды (`contract_lease_response.json`)
- **Описание**: Аренда квартиры за 85,000₽ с депозитом 170,000₽
- **Секции**: 7 разделов от сторон до ответственности
- **Риски**: 6 рисков включая высокие пени
- **Использование**: Тестирование недвижимости

#### 4. Простой договор (`contract_simple_response.json`)
- **Описание**: Минимальный договор на 50,000₽ за 30 дней
- **Секции**: 3 базовых раздела
- **Риски**: 2 предупреждения о неполноте
- **Использование**: Тестирование edge cases

### Некорректные документы

#### 1. Поврежденный JSON (`invalid_documents/malformed_json.json`)
- **Проблема**: Некорректный JSON с лишней запятой
- **Использование**: Тестирование обработки ошибок парсинга

#### 2. Отсутствующие якоря (`invalid_documents/missing_anchors.json`)
- **Проблема**: Документ без системы якорей
- **Использование**: Тестирование fallback логики

#### 3. Поврежденный контент (`invalid_documents/corrupted_content.json`)
- **Проблема**: Некорректные якоря и структура
- **Использование**: Тестирование устойчивости парсера

## 🎭 Переключение на фейковый LLM для разработки

### Быстрое переключение

```bash
# Переключиться на фейковый адаптер (без реальных API вызовов)
php artisan llm:switch fake --test

# Переключиться обратно на реальный Claude API
php artisan llm:switch claude --test
```

### Преимущества фейкового адаптера

#### ✅ **Для разработки**
- **Без затрат**: Никаких реальных API вызовов к Claude
- **Быстрота**: Ответы за 0.5 секунды (настраивается)
- **Реалистичность**: Структурированные ответы в правильном формате
- **Детекция типов**: Автоматически определяет тип документа

#### ✅ **Тестовые данные**
- **3 типа договоров**: веб-разработка, трудовой, аренда
- **Реалистичные якоря**: Правильный формат `<!-- SECTION_ANCHOR_id -->`
- **Различные риски**: risk, warning, contradiction
- **Умная кастомизация**: Извлекает суммы из входного текста

### Конфигурация

#### В `.env` файле:
```env
# Основной провайдер
LLM_DEFAULT_PROVIDER=fake

# Настройки фейкового адаптера
FAKE_LLM_BASE_DELAY=0.5        # Задержка в секундах
FAKE_LLM_SIMULATE_ERRORS=false # Симуляция ошибок для тестирования
```

#### Программное переключение:
```php
// В тестах или сервисах
app()->bind(LLMAdapterInterface::class, FakeAdapter::class);

// Создание с настройками
$fakeAdapter = new FakeAdapter(
    baseDelay: 0.1,           // Быстрые ответы
    shouldSimulateErrors: true // Тестирование обработки ошибок
);
```

### Примеры ответов фейкового адаптера

#### Договор веб-разработки:
```json
{
  "content": "## 1. ПРЕДМЕТ ДОГОВОРА\n\nЗаказчик поручает...\n\n<!-- SECTION_ANCHOR_section_1_predmet -->",
  "anchors": [
    {
      "id": "section_1_predmet",
      "title": "1. ПРЕДМЕТ ДОГОВОРА",
      "translation": "Простыми словами: Программист будет делать сайт..."
    }
  ],
  "risks": [
    {
      "type": "risk",
      "text": "Нет четкого описания технического задания..."
    }
  ]
}
```

#### Трудовой договор:
```json
{
  "content": "## 1. РАБОТОДАТЕЛЬ И РАБОТНИК\n\nРаботодатель: ООО \"ТехноСтар\"...",
  "anchors": [...],
  "risks": [
    {
      "type": "warning",
      "text": "Отсутствует информация о социальных гарантиях..."
    }
  ]
}
```

### Тестирование с фейковым адаптером

```php
// В тестах
class DocumentProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Переключиться на фейковый адаптер для тестов
        config(['llm.default' => 'fake']);
    }

    public function testDocumentTranslation(): void
    {
        $response = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('test.pdf'),
            'task_type' => 'translation'
        ]);

        $response->assertStatus(200);
        // Документ будет обработан фейковым адаптером
    }
}
```

## 🔧 Ручное тестирование API

### Настройка окружения

```bash
# Запуск локального сервера
php artisan serve

# Запуск очередей (в отдельном терминале)
php artisan queue:work

# Проверка статуса очередей
./bin/queue-status.sh status
```

### Базовые API тесты

#### 1. Загрузка документа

```bash
# POST /api/v1/documents
curl -X POST http://localhost:8000/api/v1/documents \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@/path/to/test.pdf" \
  -F "task_type=translation" \
  -F "anchor_at_start=false"
```

**Ожидаемый ответ:**
```json
{
  "success": true,
  "message": "Документ успешно сохранен и поставлен в очередь на обработку",
  "data": {
    "id": "uuid-here",
    "status": "pending",
    "filename": "test.pdf"
  }
}
```

#### 2. Проверка статуса

```bash
# GET /api/v1/documents/{uuid}
curl -X GET http://localhost:8000/api/v1/documents/{uuid} \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Статусы документа:**
- `pending` - в очереди
- `processing` - обрабатывается
- `completed` - готов
- `failed` - ошибка

#### 3. Получение результата

```bash
# GET /api/v1/documents/{uuid}/result
curl -X GET http://localhost:8000/api/v1/documents/{uuid}/result \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Структура ответа:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "filename": "test.pdf",
    "result": {
      "content": "Переведенный контент с якорями",
      "anchors": [...],
      "risks": [...]
    },
    "processing_time_seconds": 45.2,
    "cost_usd": 0.23
  }
}
```

#### 4. Удаление документа

```bash
# DELETE /api/v1/documents/{uuid}
curl -X DELETE http://localhost:8000/api/v1/documents/{uuid} \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Тестирование различных форматов

#### PDF документы
```bash
# Загрузка PDF
curl -X POST http://localhost:8000/api/v1/documents \
  -F "file=@contract.pdf" \
  -F "task_type=translation"
```

#### DOCX документы
```bash
# Загрузка DOCX
curl -X POST http://localhost:8000/api/v1/documents \
  -F "file=@agreement.docx" \
  -F "task_type=analysis"
```

#### TXT документы
```bash
# Загрузка TXT
curl -X POST http://localhost:8000/api/v1/documents \
  -F "file=@simple.txt" \
  -F "task_type=translation"
```

## 🎭 Сценарии тестирования

### Сценарий 1: Полный цикл обработки

1. **Загрузка документа**
   ```bash
   curl -X POST .../documents -F "file=@test.pdf"
   ```

2. **Ожидание обработки**
   ```bash
   # Проверять статус каждые 30 секунд
   curl -X GET .../documents/{uuid}
   ```

3. **Получение результата**
   ```bash
   curl -X GET .../documents/{uuid}/result
   ```

4. **Проверка качества**
   - Все якоря присутствуют
   - Переводы понятны
   - Риски выявлены корректно

### Сценарий 2: Обработка ошибок

1. **Загрузка некорректного файла**
   ```bash
   curl -X POST .../documents -F "file=@image.jpg"
   ```

2. **Проверка ошибки валидации**
   ```json
   {
     "success": false,
     "error": {
       "type": "validation_error",
       "code": "UNSUPPORTED_FORMAT",
       "details": "Формат файла не поддерживается"
     }
   }
   ```

3. **Загрузка слишком большого файла**
   ```bash
   curl -X POST .../documents -F "file=@huge_file.pdf"
   ```

### Сценарий 3: Производительность

1. **Загрузка большого документа** (>50 страниц)
2. **Мониторинг времени обработки**
3. **Проверка использования памяти**
4. **Анализ качества результата**

## 📊 Метрики и мониторинг

### Ключевые метрики

#### Производительность
- **Время обработки**: < 2 минуты для 20 страниц
- **Использование памяти**: < 512MB на документ
- **Пропускная способность**: 10 документов параллельно

#### Качество
- **Покрытие якорями**: > 95% секций
- **Точность перевода**: субъективная оценка
- **Выявление рисков**: > 80% реальных проблем

#### Надежность
- **Успешность обработки**: > 99%
- **Время отклика API**: < 500ms
- **Доступность сервиса**: > 99.9%

### Логи и отладка

```bash
# Логи приложения
tail -f storage/logs/laravel.log

# Логи очередей
tail -f storage/logs/queue.log

# Логи веб-сервера
tail -f /var/log/nginx/access.log
```

## 🚨 Проблемы и решения

### Частые проблемы

#### 1. Долгая обработка документов
```bash
# Проверить очереди
php artisan queue:work --verbose

# Перезапустить worker'ы
./bin/queue-status.sh restart
```

#### 2. Ошибки Claude API
```bash
# Проверить API ключ
echo $CLAUDE_API_KEY

# Проверить лимиты
curl -H "x-api-key: $CLAUDE_API_KEY" \
  https://api.anthropic.com/v1/messages
```

#### 3. Проблемы с базой данных
```bash
# Проверить миграции
php artisan migrate:status

# Очистить кеш
php artisan cache:clear
php artisan config:clear
```

## 🔒 Безопасность

### Проверки безопасности

1. **Валидация файлов**
   - Проверка MIME-типов
   - Ограничение размера (50MB)
   - Сканирование на вирусы

2. **Защита от инъекций**
   - SQL injection тесты
   - XSS защита
   - CSRF токены

3. **Аутентификация**
   - JWT токены
   - Rate limiting
   - IP whitelist

### Тест на безопасность

```bash
# Попытка загрузки исполняемого файла
curl -X POST .../documents -F "file=@malware.exe"

# Попытка SQL инъекции
curl -X GET ".../documents/'; DROP TABLE users; --"

# Превышение лимитов запросов
for i in {1..100}; do
  curl -X GET .../documents &
done
```

## 📈 Отчетность

### Автоматические отчеты

```bash
# Покрытие тестами
php artisan test --coverage-html=reports/coverage

# Статический анализ
./vendor/bin/phpstan analyse --error-format=json > reports/phpstan.json

# Производительность
php artisan test tests/Unit/Services/Export/ContentProcessorBenchmarkTest.php
```

### Ручные отчеты

1. **Еженедельный отчет по тестированию**
   - Количество пройденных тестов
   - Найденные проблемы
   - Производительность системы

2. **Отчет по качеству перевода**
   - Примеры хороших переводов
   - Проблемные места
   - Предложения по улучшению

## 🎓 Обучение команды

### Основы тестирования

1. **Принципы тестирования**
   - Unit vs Integration vs E2E
   - TDD подход
   - Мокирование зависимостей

2. **Инструменты**
   - PHPUnit для автотестов
   - cURL для API тестов
   - PHPStan для статического анализа

3. **Лучшие практики**
   - Именование тестов
   - Структура Arrange-Act-Assert
   - Изоляция тестов

### Чек-лист для новых функций

- [ ] Unit тесты написаны
- [ ] Feature тесты покрывают основные сценарии
- [ ] API документация обновлена
- [ ] Безопасность проверена
- [ ] Производительность измерена
- [ ] Логирование добавлено

---

**Документ обновлен**: 2025-01-20
**Версия**: 1.0
**Ответственные**: Команда разработки Legal Translator