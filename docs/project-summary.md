# Растолкуй - Project Documentation

## Обзор проекта

**Растолкуй** - это SaaS-сервис для автоматического перевода юридических документов на простой человеческий язык с использованием Claude API. Система анализирует договоры, выделяет риски и подводные камни, предоставляя пользователям понятные объяснения каждого пункта.

### Основные возможности

- 📄 **Поддержка форматов**: PDF, DOCX, TXT
- 🤖 **ИИ-перевод**: Использует Claude API (Sonnet/Haiku) для высокоточного анализа
- ⚠️ **Выявление рисков**: Автоматическое выделение опасных пунктов
- 🎯 **Система якорей**: Точное сопоставление оригинала и перевода
- 📊 **Детальная аналитика**: Метрики качества и производительности
- 💳 **Монетизация**: Freemium модель с кредитной системой

## Технологический стек

### Backend
- **Laravel 11** - PHP фреймворк
- **PHP 8.3** - Язык программирования
- **PostgreSQL** - Основная база данных
- **Redis** - Кеширование и очереди
- **MinIO** - S3-совместимое хранилище файлов
- **Laravel Sanctum** - API аутентификация

### Frontend
- **React 18** с TypeScript
- **Inertia.js** - SPA без API
- **Vite** - Сборщик модулей
- **Tabler** - UI компоненты
- **Bootstrap 5** - CSS фреймворк
- **Sass** - CSS препроцессор

### LLM & AI
- **Claude API** (Anthropic) - Основная модель ИИ
- **Поддержка моделей**: Claude Sonnet 4, Claude 3.5 Sonnet, Claude 3.5 Haiku
- **Адаптивный выбор модели** для оптимизации затрат

### DevOps & Tools
- **Docker** - Контейнеризация
- **GitHub Actions** - CI/CD
- **PHPStan Level 9** - Статический анализ
- **PHP CS Fixer** - Форматирование кода
- **PHPUnit** - Тестирование

### Внешние сервисы
- **YouTrack** - Управление задачами
- **Claude API** - Обработка текста ИИ

## Архитектура системы

### Основные компоненты

#### 1. Система парсинга документов (`app/Services/Parser/`)
- **ExtractorManager** - Фабрика экстракторов
- **Extractors/** - Парсеры для разных форматов (PDF, DOCX, TXT)
- **Support/** - Вспомогательные классы (классификация, кодировки, метрики)

#### 2. Система анализа структуры (`app/Services/Structure/`)
- **StructureAnalyzer** - Анализ структуры документа
- **AnchorGenerator** - Генерация уникальных якорей
- **SectionDetector** - Определение секций документа

#### 3. LLM система (`app/Services/LLM/`)
- **LLMService** - Основной сервис работы с ИИ
- **Adapters/** - Адаптеры для разных провайдеров ИИ
- **Support/** - Rate limiting, retry логика
- **DTOs/** - Объекты передачи данных

#### 4. Система промптов (`app/Services/Prompt/`)
- **PromptManager** - Управление промптами
- **TemplateEngine** - Шаблонизатор промптов
- **QualityAnalyzer** - Анализ качества ответов
- **SchemaManager** - Управление JSON-схемами

#### 5. Валидация документов (`app/Services/Validation/`)
- **DocumentValidator** - Основной валидатор
- **Validators/** - Специализированные валидаторы

#### 6. Кредитная система (`app/Services/CreditService.php`)
- Управление балансом пользователей
- События и слушатели для операций
- Асинхронная обработка через очереди

## Структура базы данных

### Основные таблицы

- **users** - Пользователи системы
- **user_credits** - Баланс кредитов пользователей
- **credit_transactions** - История операций с кредитами
- **document_processings** - Обработка документов
- **prompt_systems** - Системы промптов
- **prompt_templates** - Шаблоны промптов
- **prompt_executions** - Выполнения промптов
- **prompt_feedback** - Обратная связь по промптам

### Интеграции

- **spatie/laravel-permission** - Роли и разрешения
- **personal_access_tokens** - API токены Sanctum

## API Endpoints

### Аутентификация (`/api/auth/`)
- POST `/register` - Регистрация
- POST `/login` - Авторизация
- POST `/logout` - Выход
- GET `/user` - Данные пользователя
- POST `/forgot-password` - Сброс пароля

### Кредиты (`/api/user/credits/`, `/api/credits/`)
- GET `/balance` - Текущий баланс
- GET `/statistics` - Статистика
- GET `/history` - История транзакций
- POST `/topup` - Пополнение (dev only)
- POST `/convert-usd` - Конвертация USD
- POST `/check-balance` - Проверка баланса

### Документы (`/api/v1/documents/`)
- POST `/` - Загрузка документа
- GET `/{uuid}/status` - Статус обработки
- GET `/{uuid}/result` - Результат обработки
- POST `/{uuid}/cancel` - Отмена обработки
- DELETE `/{uuid}` - Удаление

## Event-Driven Architecture

### События (Events)
- **CreditAdded** - Пополнение кредитов
- **CreditDebited** - Списание кредитов
- **CreditRefunded** - Возврат кредитов
- **InsufficientBalance** - Недостаточно средств

### Слушатели (Listeners)
- **LogCreditActivity** - Логирование операций
- **InvalidateCreditCache** - Сброс кеша
- **SendLowBalanceNotification** - Уведомления о низком балансе

### Асинхронные задачи (Jobs)
- **ProcessCreditRefund** - Обработка возвратов
- **RecalculateUserStatistics** - Пересчет статистики
- **ProcessDocumentJob** - Обработка документов
- **ProcessLLMTranslationJob** - LLM переводы

## Тестирование

### Покрытие тестами
- **380+ тестов** общее количество
- **1349+ assertions** проверок
- **Unit тесты** для всех сервисов
- **Feature тесты** для API endpoints
- **Integration тесты** для комплексных сценариев

### Качество кода
- **PHPStan Level 9** - максимальный уровень анализа
- **PHP CS Fixer** - стандарты кодирования
- **100% type coverage** - полная типизация

## Развертывание

### Docker
- **Multi-stage builds** для оптимизации
- **Production-ready** конфигурация
- **Health checks** для всех сервисов

### Мониторинг
- Structured logging
- Метрики производительности
- Error tracking
- API usage analytics

## Файловая структура

```
app/
├── Console/Commands/          # Artisan команды
├── Events/                    # События системы
├── Http/
│   ├── Controllers/           # Контроллеры
│   ├── Middleware/            # Посредники
│   ├── Requests/              # Валидация запросов
│   └── Resources/             # API ресурсы
├── Jobs/                      # Асинхронные задачи
├── Listeners/                 # Слушатели событий
├── Models/                    # Eloquent модели
├── Policies/                  # Политики авторизации
├── Providers/                 # Сервис-провайдеры
├── Rules/                     # Правила валидации
└── Services/                  # Бизнес-логика
    ├── LLM/                   # LLM интеграция
    ├── Parser/                # Парсинг документов
    ├── Prompt/                # Система промптов
    ├── Structure/             # Анализ структуры
    └── Validation/            # Валидация
```

## Конфигурация

### Основные config файлы
- **config/credits.php** - Кредитная система
- **config/llm.php** - LLM провайдеры
- **config/extractors.php** - Настройки парсеров
- **config/structure_analysis.php** - Анализ структуры
- **config/document_validation.php** - Валидация документов

### Environment переменные
- Claude API ключи
- Database настройки
- Redis/Cache конфигурация
- MinIO/S3 хранилище
- YouTrack интеграция

---

*Создано: 2025-08-29*  
*Версия: 1.0*  
*Статус: В разработке*