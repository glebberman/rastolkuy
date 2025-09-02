# API Documentation v1

## Общая информация

**Base URL**: `https://your-domain.com/api`  
**API Version**: `v1` (все маршруты начинаются с `/api/v1/`)  
**Аутентификация**: Bearer Token (Laravel Sanctum)  
**Content-Type**: `application/json`  
**Rate Limiting**: Настраивается через middleware `custom.throttle`

## Структура маршрутов

Все API маршруты используют версионирование v1 с плоской структурой:

**Публичные маршруты** (без аутентификации):
- Регистрация и аутентификация: `/api/v1/auth/*`

**Защищенные маршруты** (требуют Bearer token):
- Управление сессией: `/api/v1/auth/user`, `/api/v1/auth/logout`, etc.
- Кредитная система: `/api/v1/credits/*`  
- Документы: `/api/v1/documents/*`
- Админ панель: `/api/v1/documents/admin/*`

**Именованные маршруты**: Все routes имеют dot notation: `api.v1.{resource}.{action}`

## Аутентификация

### POST `/api/v1/auth/register`
Регистрация нового пользователя.

**Rate Limit**: 5 requests per minute

**Request Body**:
```json
{
  "name": "string (required)",
  "email": "email (required|unique)",
  "password": "string (required|min:8|confirmed)"
}
```

**Response 201**:
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "created_at": "2025-08-29T12:00:00Z"
  },
  "token": "sanctum_token_here"
}
```

### POST `/api/v1/auth/login`
Авторизация пользователя.

**Rate Limit**: 10 requests per minute

**Request Body**:
```json
{
  "email": "email (required)",
  "password": "string (required)"
}
```

**Response 200**:
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  },
  "token": "sanctum_token_here"
}
```

### POST `/api/v1/auth/logout` 🔒
Выход из системы.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Response 200**:
```json
{
  "message": "Logged out successfully"
}
```

### GET `/api/v1/auth/user` 🔒
Получение данных текущего пользователя.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Response 200**:
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "user@example.com",
  "email_verified_at": "2025-08-29T12:00:00Z",
  "created_at": "2025-08-29T12:00:00Z"
}
```

### PUT `/api/v1/auth/user` 🔒
Обновление профиля пользователя.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Request Body**:
```json
{
  "name": "string (optional)",
  "email": "email (optional|unique)"
}
```

### POST `/api/v1/auth/forgot-password`
Запрос сброса пароля.

**Rate Limit**: 3 requests per minute

**Request Body**:
```json
{
  "email": "email (required)"
}
```

### POST `/api/v1/auth/reset-password`
Сброс пароля по токену.

**Rate Limit**: 5 requests per minute

**Request Body**:
```json
{
  "token": "string (required)",
  "email": "email (required)", 
  "password": "string (required|min:8|confirmed)"
}
```

## Управление кредитами

Все endpoints кредитной системы теперь используют кастомные Request и Response классы для улучшенной валидации и единообразной структуры ответов.

### GET `/api/v1/credits/balance` 🔒
Получение текущего баланса кредитов пользователя.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `CreditBalanceRequest`  
**Response Class**: `CreditBalanceResponse`

**Response 200**:
```json
{
  "message": "Баланс кредитов пользователя",
  "data": {
    "balance": 150.5,
    "user_id": 1
  }
}
```

### GET `/api/v1/credits/statistics` 🔒
Получение детальной статистики по кредитам пользователя.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `CreditStatisticsRequest`  
**Response Class**: `CreditStatisticsResponse`

**Response 200**:
```json
{
  "message": "Статистика кредитов пользователя",
  "data": {
    "balance": 150.5,
    "total_topups": 200.0,
    "total_debits": 49.5,
    "total_refunds": 0.0,
    "transaction_count": 15,
    "last_transaction_at": "2025-08-29T12:00:00Z",
    "cached_at": "2025-08-29T12:30:00Z"
  }
}
```

### GET `/api/v1/credits/history` 🔒
История транзакций с пагинацией и валидацией параметров.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `CreditHistoryRequest`  
**Response Class**: `CreditHistoryResponse`

**Query Parameters**:
- `per_page` (integer, optional) - количество записей на страницу (1-100, default: 20)

**Validation Rules**:
- `per_page`: sometimes|integer|min:1|max:100

**Response 200**:
```json
{
  "message": "История транзакций кредитов",
  "data": [
    {
      "id": 1,
      "type": "topup",
      "type_description": "Пополнение",
      "amount": 100.0,
      "absolute_amount": 100.0,
      "balance_before": 50.0,
      "balance_after": 150.0,
      "description": "Welcome bonus",
      "timestamps": {
        "created_at": "2025-08-29T12:00:00Z"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 15,
    "from": 1,
    "to": 15
  }
}
```

### POST `/api/v1/credits/topup` 🔒
Пополнение кредитов с валидацией и улучшенным error handling (только для разработки).

**Environment**: `local` only  
**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 10 requests per minute  
**Request Class**: `CreditTopupRequest`  
**Response Class**: `CreditTopupResponse | CreditErrorResponse`

**Request Body**:
```json
{
  "amount": "numeric (required|min:1|max:10000)",
  "description": "string (optional|max:255)"
}
```

**Validation Rules**:
- `amount`: required|numeric|min:1|max:10000
- `description`: sometimes|string|max:255

**Localized Error Messages**:
- `amount.required`: "Сумма пополнения обязательна"
- `amount.min`: "Минимальная сумма пополнения: 1"
- `amount.max`: "Максимальная сумма пополнения: 10000"

**Response 201**:
```json
{
  "message": "Кредиты успешно добавлены",
  "data": {
    "id": 1,
    "type": "topup",
    "type_description": "Пополнение",
    "amount": 50.0,
    "absolute_amount": 50.0,
    "balance_before": 150.0,
    "balance_after": 200.0,
    "description": "Test credit topup",
    "timestamps": {
      "created_at": "2025-08-29T12:00:00Z"
    }
  }
}
```

**Response 403** (Production):
```json
{
  "error": "Not available",
  "message": "Этот endpoint доступен только в среде разработки"
}
```

**Response 400** (Validation Error):
```json
{
  "error": "Invalid request",
  "message": "Adding 15000 credits would exceed maximum balance of 10000"
}
```

### POST `/api/v1/credits/check-balance` 🔒
Проверка достаточности баланса с детальной информацией.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `CheckBalanceRequest`  
**Response Class**: `CheckBalanceResponse`

**Request Body**:
```json
{
  "required_amount": "numeric (required|min:0|max:1000000)"
}
```

**Validation Rules**:
- `required_amount`: required|numeric|min:0|max:1000000

**Localized Error Messages**:
- `required_amount.required`: "Требуемая сумма обязательна"
- `required_amount.min`: "Требуемая сумма не может быть отрицательной"
- `required_amount.max`: "Максимальная проверяемая сумма: 1,000,000"

**Response 200**:
```json
{
  "message": "Проверка баланса кредитов",
  "data": {
    "current_balance": 150.0,
    "required_amount": 10.0,
    "has_sufficient_balance": true,
    "deficit": 0
  }
}
```

### POST `/api/v1/credits/convert-usd` 🔒
Конвертация USD в кредиты с расширенными лимитами и валидацией.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `ConvertUsdRequest`  
**Response Class**: `ConvertUsdResponse`

**Request Body**:
```json
{
  "usd_amount": "numeric (required|min:0|max:100000)"
}
```

**Validation Rules**:
- `usd_amount`: required|numeric|min:0|max:100000

**Localized Error Messages**:
- `usd_amount.required`: "Сумма в долларах обязательна"
- `usd_amount.min`: "Сумма не может быть отрицательной"
- `usd_amount.max`: "Максимальная сумма для конвертации: $100,000"

**Response 200**:
```json
{
  "message": "Конвертация USD в кредиты",
  "data": {
    "usd_amount": 1.0,
    "credits": 100.0,
    "rate": 100
  }
}
```

### GET `/api/v1/credits/rates` 🔒
Получить курсы обмена валют с кешированием и улучшенной обработкой ошибок.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `ExchangeRatesRequest`  
**Response Class**: `ExchangeRatesResponse | CreditErrorResponse`  
**Caching**: 1 hour TTL

**Response 200**:
```json
{
  "message": "Курсы обмена валют",
  "data": {
    "rates": {
      "RUB": 1.0,
      "USD": 95.0,
      "EUR": 105.0
    },
    "base_currency": "RUB",
    "supported_currencies": ["RUB", "USD", "EUR"],
    "updated_at": "2025-01-09T12:00:00.000000Z"
  }
}
```

**Response 400** (Configuration Error):
```json
{
  "error": "Invalid configuration",
  "message": "Некорректная конфигурация валют",
  "details": "Base currency 'RUB' not found in currency configuration"
}
```

**Response 500** (System Error):
```json
{
  "error": "Configuration error",
  "message": "Ошибка конфигурации валютной системы",
  "details": "Exchange rate for currency 'USD' must be positive number"
}
```

**Описание полей**:
- `rates` - курсы валют относительно базовой валюты
- `base_currency` - базовая валюта системы (обычно RUB)
- `supported_currencies` - список поддерживаемых валют
- `updated_at` - время последнего обновления

### GET `/api/v1/credits/costs` 🔒
Получить стоимость 1 кредита в разных валютах с кешированием и валидацией.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute  
**Request Class**: `CreditCostsRequest`  
**Response Class**: `CreditCostsResponse | CreditErrorResponse`  
**Caching**: 1 hour TTL

**Response 200**:
```json
{
  "message": "Стоимость кредитов в валютах",
  "data": {
    "credit_costs": {
      "RUB": 1.0,
      "USD": 0.01,
      "EUR": 0.009
    },
    "base_currency": "RUB",
    "supported_currencies": ["RUB", "USD", "EUR"],
    "description": "Cost of 1 credit in different currencies",
    "updated_at": "2025-01-09T12:00:00.000000Z"
  }
}
```

**Response 400** (Configuration Error):
```json
{
  "error": "Invalid configuration",
  "message": "Некорректная конфигурация валют",
  "details": "Supported currency 'GBP' missing in exchange rates configuration"
}
```

**Response 500** (System Error):
```json
{
  "error": "Configuration error", 
  "message": "Ошибка конфигурации валютной системы",
  "details": "Credit cost for currency 'USD' must be positive number"
}
```

**Описание полей**:
- `credit_costs` - стоимость 1 кредита в различных валютах
- `base_currency` - базовая валюта системы
- `supported_currencies` - список поддерживаемых валют
- `description` - описание формата данных
- `updated_at` - время последнего обновления

## Обработка документов

### Новый workflow (3-этапный)

С версии RAS-19 реализован новый трехэтапный процесс обработки документов:

1. **Upload** (`uploaded`) - загрузка файла без запуска обработки
2. **Estimate** (`estimated`) - расчет стоимости обработки 
3. **Process** (`pending` → `processing` → `completed`/`failed`) - запуск обработки

### POST `/api/v1/documents/upload` 🔒
Загрузка документа без запуска обработки (Этап 1).

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.create`

**Request** (multipart/form-data):
```
file: File (required|mimes:pdf,docx,txt|max:51200) // 50MB
task_type: string (required) - "translation"|"analysis"|"ambiguity" 
anchor_at_start: boolean (optional, default: false)
options: JSON (optional) - дополнительные опции
```

**Response 201**:
```json
{
  "message": "Документ загружен и готов к оценке стоимости",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "filename": "contract.pdf",
    "file_type": "application/pdf", 
    "file_size": 102400,
    "task_type": "translation",
    "task_description": "Перевод в простой язык",
    "anchor_at_start": false,
    "status": "uploaded",
    "status_description": "Файл загружен",
    "progress_percentage": 10,
    "timestamps": {
      "created_at": "2025-08-31T12:00:00Z",
      "started_at": null,
      "completed_at": null,
      "updated_at": "2025-08-31T12:00:00Z"
    }
  },
  "meta": {
    "api_version": "v1",
    "action": "document_uploaded",
    "timestamp": "2025-08-31T12:00:00Z"
  }
}
```

### POST `/api/v1/documents/{uuid}/estimate` 🔒
Расчет стоимости обработки документа (Этап 2).

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.view`

**Path Parameters**:
- `uuid` - UUID документа в статусе "uploaded"

**Request Body**:
```json
{
  "task_type": "translation|analysis|ambiguity (required)",
  "anchor_at_start": "boolean (optional, default: false)"
}
```

**Response 200**:
```json
{
  "message": "Стоимость обработки рассчитана",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "filename": "contract.pdf",
    "status": "estimated", 
    "status_description": "Стоимость рассчитана",
    "progress_percentage": 20,
    "estimation": {
      "estimated_cost_usd": 1.25,
      "credits_needed": 125.0,
      "has_sufficient_balance": true,
      "estimated_tokens": 5000,
      "model": "claude-sonnet-4"
    }
  },
  "meta": {
    "api_version": "v1", 
    "action": "document_estimated",
    "timestamp": "2025-08-31T12:00:00Z"
  }
}
```

### POST `/api/v1/documents/{uuid}/process` 🔒
Запуск обработки оцененного документа (Этап 3).

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.view`

**Path Parameters**:
- `uuid` - UUID документа в статусе "estimated"

**Response 200**:
```json
{
  "message": "Обработка документа запущена",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "status_description": "В очереди на обработку", 
    "progress_percentage": 25
  },
  "meta": {
    "api_version": "v1",
    "action": "document_processed",
    "timestamp": "2025-08-31T12:00:00Z"
  }
}
```

**Response 409** (Insufficient Balance):
```json
{
  "error": "Cannot process document",
  "message": "Insufficient balance to process document"
}
```

### POST `/api/v1/documents` 🔒 (Legacy)
Загрузка документа с немедленным запуском обработки (старый API для обратной совместимости).

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.create`

**Request** (multipart/form-data):
```
file: File (required|mimes:pdf,docx,txt|max:51200) // 50MB
task_type: string (required) - "translation"|"analysis"|"ambiguity"
anchor_at_start: boolean (optional, default: false)
options: JSON (optional) - дополнительные опции обработки
```

**Response 201**:
```json
{
  "message": "Документ загружен и поставлен в очередь на обработку",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "progress_percentage": 25
  },
  "meta": {
    "api_version": "v1",
    "action": "document_stored", 
    "timestamp": "2025-08-31T12:00:00Z"
  }
}
```

### GET `/api/v1/documents/{uuid}/status` 🔒
Получение статуса обработки документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.view`

**Path Parameters**:
- `uuid` - UUID документа

**Response 200**:
```json
{
  "message": "Статус обработки документа",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "filename": "contract.pdf",
    "file_type": "application/pdf",
    "task_type": "translation",
    "status": "completed",
    "status_description": "Обработка завершена",
    "progress_percentage": 100,
    "estimation": {
      "estimated_cost_usd": 1.25,
      "credits_needed": 125.0,
      "has_sufficient_balance": true
    },
    "timestamps": {
      "created_at": "2025-08-31T12:00:00Z",
      "started_at": "2025-08-31T12:01:00Z",
      "completed_at": "2025-08-31T12:05:00Z",
      "updated_at": "2025-08-31T12:05:00Z"
    }
  },
  "meta": {
    "api_version": "v1",
    "action": "document_status",
    "timestamp": "2025-08-31T12:10:00Z"
  }
}
```

### GET `/api/v1/documents/{uuid}/result` 🔒
Получение результата обработки документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.view`

**Path Parameters**:
- `uuid` - UUID документа (только для завершенных документов)

**Response 200**:
```json
{
  "message": "Результат обработки документа",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "filename": "contract.pdf",
    "task_type": "translation",
    "result": {
      "original_filename": "contract.pdf",
      "processed_content": "...",
      "sections": [...],
      "risks_detected": [...],
      "metadata": {...}
    },
    "processing_time_seconds": 15.0,
    "cost_usd": 1.25,
    "metadata": {
      "model_used": "claude-sonnet-4",
      "tokens_processed": 5000
    },
    "completed_at": "2025-08-31T12:05:00Z"
  },
  "meta": {
    "api_version": "v1",
    "action": "document_result",
    "timestamp": "2025-08-31T12:10:00Z"
  }
}
```

**Response 202** (Processing Not Complete):
```json
{
  "error": "Processing not completed",
  "message": "Обработка документа еще не завершена",
  "status": "processing",
  "progress": 75
}
```

### POST `/api/v1/documents/{uuid}/cancel` 🔒
Отмена обработки документа (только если статус "pending" или "uploaded").

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.cancel`

**Path Parameters**:
- `uuid` - UUID документа

**Response 200**:
```json
{
  "message": "Обработка документа отменена",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "cancelled",
    "status_description": "Обработка отменена"
  },
  "meta": {
    "api_version": "v1",
    "action": "document_cancelled", 
    "timestamp": "2025-08-31T12:10:00Z"
  }
}
```

**Response 409** (Cannot Cancel):
```json
{
  "error": "Cannot cancel",
  "message": "Cannot cancel processing that has already started",
  "status": "processing"
}
```

### DELETE `/api/v1/documents/{uuid}` 🔒
Удаление записи об обработке документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.delete`

**Path Parameters**:
- `uuid` - UUID документа

**Response 200**:
```json
{
  "message": "Запись об обработке документа удалена"
}
```

## Административные функции

### GET `/api/v1/documents` 🔒
Список всех обработок с фильтрацией и пагинацией.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.viewAny` (admin only)

**Query Parameters**:
- `status` (string, optional) - фильтр по статусу
- `task_type` (string, optional) - фильтр по типу задачи
- `per_page` (integer, optional) - записей на страницу (default: 20)

**Response 200**:
```json
{
  "message": "Список обработок документов",
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "filename": "contract.pdf",
      "file_type": "application/pdf",
      "task_type": "translation",
      "status": "completed",
      "progress_percentage": 100,
      "cost_usd": 1.25,
      "processing_time_seconds": 15.0,
      "user_id": 1,
      "timestamps": {
        "created_at": "2025-08-31T12:00:00Z",
        "completed_at": "2025-08-31T12:05:00Z"
      }
    }
  ],
  "meta": {
    "api_version": "v1",
    "action": "documents_list",
    "timestamp": "2025-08-31T12:10:00Z",
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 150,
      "last_page": 8,
      "from": 1,
      "to": 20
    }
  }
}
```

### GET `/api/v1/documents/stats` 🔒
Статистика по обработкам документов.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.stats` (admin only)

**Response 200**:
```json
{
  "message": "Статистика обработки документов",
  "data": {
    "total_documents": 1250,
    "status_breakdown": {
      "uploaded": 15,
      "estimated": 8, 
      "pending": 12,
      "processing": 3,
      "completed": 1200,
      "failed": 8,
      "cancelled": 4
    },
    "task_type_breakdown": {
      "translation": 800,
      "analysis": 350,
      "ambiguity": 100
    },
    "completed_today": 45,
    "total_cost_usd": 1250.75,
    "average_processing_time_seconds": 12.5,
    "top_users": [
      {
        "user_id": 123,
        "documents_processed": 25,
        "total_cost_usd": 125.50
      }
    ]
  },
  "generated_at": "2025-08-31T12:10:00Z",
  "meta": {
    "api_version": "v1",
    "action": "documents_stats",
    "timestamp": "2025-08-31T12:10:00Z"
  }
}
```

## Коды ошибок

### 400 Bad Request
```json
{
  "error": "Bad Request",
  "message": "Invalid request data"
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "error": "Forbidden",
  "message": "Access denied"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### 429 Too Many Requests
```json
{
  "message": "Too Many Attempts.",
  "retry_after": 60
}
```

### 500 Internal Server Error
```json
{
  "error": "Internal Server Error",
  "message": "An unexpected error occurred"
}
```

## Middleware

### Rate Limiting (`custom.throttle`)
Ограничение скорости запросов с настраиваемыми лимитами:
- Регистрация/авторизация: строгие лимиты
- API endpoints: 60 запросов/минута
- Административные: повышенные лимиты

### Authentication (`auth:sanctum`)
Проверка Bearer токена для защищенных маршрутов.

### Permissions (`permission:`)
Проверка разрешений через Spatie Laravel Permission:
- `documents.create` - создание документов
- `documents.view` - просмотр документов
- `documents.cancel` - отмена обработки
- `documents.delete` - удаление

### Roles (`role:`)
Проверка ролей пользователей:
- `admin` - администраторские функции

## Именованные маршруты

Все маршруты имеют именованные aliases для использования в Laravel:

```php
// Аутентификация
route('api.v1.auth.register')              // POST /api/v1/auth/register
route('api.v1.auth.login')                 // POST /api/v1/auth/login
route('api.v1.auth.logout')                // POST /api/v1/auth/logout

// Кредиты
route('api.v1.credits.balance')            // GET /api/v1/credits/balance
route('api.v1.credits.history')            // GET /api/v1/credits/history
route('api.v1.credits.statistics')         // GET /api/v1/credits/statistics
route('api.v1.credits.rates')              // GET /api/v1/credits/rates
route('api.v1.credits.costs')              // GET /api/v1/credits/costs

// Документы - новый workflow
route('api.v1.documents.upload')           // POST /api/v1/documents/upload
route('api.v1.documents.estimate', $uuid)  // POST /api/v1/documents/{uuid}/estimate  
route('api.v1.documents.process', $uuid)   // POST /api/v1/documents/{uuid}/process

// Документы - управление
route('api.v1.documents.status', $uuid)    // GET /api/v1/documents/{uuid}/status
route('api.v1.documents.result', $uuid)    // GET /api/v1/documents/{uuid}/result
route('api.v1.documents.cancel', $uuid)    // POST /api/v1/documents/{uuid}/cancel
route('api.v1.documents.destroy', $uuid)   // DELETE /api/v1/documents/{uuid}

// Документы - legacy и админ
route('api.v1.documents.store')            // POST /api/v1/documents (legacy)
route('api.v1.documents.index')            // GET /api/v1/documents (admin)
route('api.v1.documents.stats')            // GET /api/v1/documents/stats (admin)
```

**Формат именования**: `api.v1.{resource}.{action}`

## Примеры использования

### JavaScript/TypeScript

**Новый 3-этапный процесс обработки документов:**
```typescript
// 1. Авторизация
const authResponse = await fetch('/api/v1/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});
const { token } = await authResponse.json();

// 2. Загрузка документа
const formData = new FormData();
formData.append('file', file);
formData.append('task_type', 'translation');
formData.append('anchor_at_start', 'false');

const uploadResponse = await fetch('/api/v1/documents/upload', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
});
const uploadResult = await uploadResponse.json();
const documentId = uploadResult.data.id;

// 3. Получение оценки стоимости
const estimateResponse = await fetch(`/api/v1/documents/${documentId}/estimate`, {
  method: 'POST',
  headers: { 
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    task_type: 'translation',
    anchor_at_start: false
  })
});
const estimateResult = await estimateResponse.json();
console.log('Стоимость:', estimateResult.data.estimation.credits_needed);

// 4. Запуск обработки (если хватает баланса)
if (estimateResult.data.estimation.has_sufficient_balance) {
  const processResponse = await fetch(`/api/v1/documents/${documentId}/process`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` }
  });
  
  // 5. Проверка статуса
  const statusResponse = await fetch(`/api/v1/documents/${documentId}/status`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const statusResult = await statusResponse.json();
  
  // 6. Получение результата (когда готов)
  if (statusResult.data.status === 'completed') {
    const resultResponse = await fetch(`/api/v1/documents/${documentId}/result`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const result = await resultResponse.json();
    console.log('Результат обработки:', result.data.result);
  }
}
```

### cURL

**Новый 3-этапный процесс:**
```bash
# 1. Авторизация
curl -X POST https://api.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Сохраняем токен
TOKEN="your_received_token_here"

# 2. Загрузка документа
curl -X POST https://api.example.com/api/v1/documents/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@contract.pdf" \
  -F "task_type=translation" \
  -F "anchor_at_start=false"

# Сохраняем UUID документа из ответа
DOC_UUID="550e8400-e29b-41d4-a716-446655440000"

# 3. Оценка стоимости
curl -X POST https://api.example.com/api/v1/documents/$DOC_UUID/estimate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"task_type":"translation","anchor_at_start":false}'

# 4. Запуск обработки
curl -X POST https://api.example.com/api/v1/documents/$DOC_UUID/process \
  -H "Authorization: Bearer $TOKEN"

# 5. Проверка статуса  
curl -X GET https://api.example.com/api/v1/documents/$DOC_UUID/status \
  -H "Authorization: Bearer $TOKEN"

# 6. Получение результата
curl -X GET https://api.example.com/api/v1/documents/$DOC_UUID/result \
  -H "Authorization: Bearer $TOKEN"

# Legacy: загрузка с немедленным запуском
curl -X POST https://api.example.com/api/v1/documents \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@document.pdf" \
  -F "task_type=translation"
```

---

🔒 - Требует аутентификации  
⚡ - Асинхронная обработка  
📊 - Кешируется  

---

**API Version**: v1  
**Route Naming**: `api.v1.{resource}.{action}`  
**New Features**: 3-этапный процесс обработки (upload → estimate → process)  
**Resource Format**: Все ответы через JsonResource с единой структурой  
**Backward Compatibility**: Legacy endpoint `/api/v1/documents` сохранен  

## Архитектура Request/Response классов

### Кредитная система (RAS-21)

Все endpoints кредитной системы реализованы с использованием кастомных Request и Response классов для обеспечения type safety и единообразия:

#### Request Classes
- **CreditBalanceRequest** - базовая авторизация
- **CreditStatisticsRequest** - базовая авторизация  
- **CreditHistoryRequest** - валидация `per_page` (1-100)
- **CreditTopupRequest** - валидация `amount` (1-10000) и `description` (max:255)
- **ConvertUsdRequest** - валидация `usd_amount` (0-100000)
- **CheckBalanceRequest** - валидация `required_amount` (0-1000000)
- **ExchangeRatesRequest** - базовая авторизация
- **CreditCostsRequest** - базовая авторизация

#### Response Classes
- **CreditBalanceResponse** - баланс + user_id
- **CreditStatisticsResponse** - детальная статистика
- **CreditHistoryResponse** - пагинированная история через CreditTransactionResource
- **CreditTopupResponse** - транзакция через CreditTransactionResource
- **ConvertUsdResponse** - результат конвертации
- **CheckBalanceResponse** - детальная проверка баланса
- **ExchangeRatesResponse** - курсы валют с timestamp
- **CreditCostsResponse** - стоимость кредитов с timestamp
- **CreditErrorResponse** - унифицированная обработка ошибок

#### Улучшения
- **Type Safety**: Все методы используют строгую типизацию
- **Валидация**: Кастомные правила с локализованными сообщениями на русском
- **Error Handling**: Детализированные сообщения об ошибках конфигурации
- **Кеширование**: 1-час TTL для курсов валют и стоимости кредитов
- **Единообразие**: Все ответы следуют одинаковой структуре `{message, data}`

### Файловая структура

```
app/Http/
├── Requests/Api/Credit/
│   ├── CreditBalanceRequest.php
│   ├── CreditStatisticsRequest.php
│   ├── CreditHistoryRequest.php
│   ├── CreditTopupRequest.php
│   ├── ConvertUsdRequest.php
│   ├── CheckBalanceRequest.php
│   ├── ExchangeRatesRequest.php
│   └── CreditCostsRequest.php
└── Responses/Api/Credit/
    ├── CreditBalanceResponse.php
    ├── CreditStatisticsResponse.php
    ├── CreditHistoryResponse.php
    ├── CreditTopupResponse.php
    ├── ConvertUsdResponse.php
    ├── CheckBalanceResponse.php
    ├── ExchangeRatesResponse.php
    ├── CreditCostsResponse.php
    └── CreditErrorResponse.php
```

### Совместимость
- **PHPStan Level 9**: Полная совместимость без ошибок
- **Тесты**: Все существующие тесты проходят без изменений
- **API**: Структура JSON ответов остается неизменной для обратной совместимости

*Обновлено: 2025-01-09 - Реализация RAS-21: Custom Request/Response classes*