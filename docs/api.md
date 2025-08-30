# API Documentation

## Общая информация

**Base URL**: `https://your-domain.com/api`  
**Аутентификация**: Bearer Token (Laravel Sanctum)  
**Content-Type**: `application/json`  
**Rate Limiting**: Настраивается через middleware `custom.throttle`

## Аутентификация

### POST `/auth/register`
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

### POST `/auth/login`
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

### POST `/auth/logout` 🔒
Выход из системы.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Response 200**:
```json
{
  "message": "Logged out successfully"
}
```

### GET `/auth/user` 🔒
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

### PUT `/auth/user` 🔒
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

### POST `/auth/forgot-password`
Запрос сброса пароля.

**Rate Limit**: 3 requests per minute

**Request Body**:
```json
{
  "email": "email (required)"
}
```

### POST `/auth/reset-password`
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

### GET `/user/credits/balance` 🔒
Получение текущего баланса кредитов.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Response 200**:
```json
{
  "balance": 150.5,
  "currency": "credits"
}
```

### GET `/user/credits/statistics` 🔒
Получение статистики по кредитам.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Response 200**:
```json
{
  "balance": 150.5,
  "total_topups": 200.0,
  "total_debits": 49.5,
  "total_refunds": 0.0,
  "transaction_count": 15,
  "last_transaction_at": "2025-08-29T12:00:00Z",
  "cached_at": "2025-08-29T12:30:00Z"
}
```

### GET `/user/credits/history` 🔒
История транзакций с пагинацией.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Query Parameters**:
- `per_page` (integer, optional) - количество записей на страницу (default: 20)

**Response 200**:
```json
{
  "data": [
    {
      "id": 1,
      "type": "topup",
      "amount": 100.0,
      "balance_before": 50.0,
      "balance_after": 150.0,
      "description": "Welcome bonus",
      "metadata": {
        "source": "registration_bonus"
      },
      "created_at": "2025-08-29T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 15,
    "last_page": 1
  }
}
```

### POST `/user/credits/topup` 🔒
Пополнение кредитов (только для разработки).

**Environment**: `local` only  
**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 10 requests per minute

**Request Body**:
```json
{
  "amount": "numeric (required|min:0.01)",
  "description": "string (optional)"
}
```

**Response 201**:
```json
{
  "message": "Credits added successfully",
  "transaction": {
    "id": 1,
    "type": "topup",
    "amount": 50.0,
    "balance_after": 200.0,
    "created_at": "2025-08-29T12:00:00Z"
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

### POST `/user/credits/check-balance` 🔒
Проверка достаточности баланса.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Request Body**:
```json
{
  "required_amount": "numeric (required|min:0.01)"
}
```

**Response 200**:
```json
{
  "sufficient": true,
  "current_balance": 150.0,
  "required_amount": 10.0
}
```

### POST `/credits/convert-usd` 🔒
Конвертация USD в кредиты.

**Headers**: `Authorization: Bearer {token}`  
**Rate Limit**: 60 requests per minute

**Request Body**:
```json
{
  "usd_amount": "numeric (required|min:0.01)"
}
```

**Response 200**:
```json
{
  "usd_amount": 1.0,
  "credits": 100.0,
  "conversion_rate": 100.0,
  "currency": "USD"
}
```

## Обработка документов

### POST `/v1/documents` 🔒
Загрузка документа для обработки.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.create`

**Request** (multipart/form-data):
```
file: File (required|mimes:pdf,docx,txt|max:51200) // 50MB
options: JSON (optional) - дополнительные опции обработки
```

**Response 201**:
```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "status": "pending",
  "created_at": "2025-08-29T12:00:00Z"
}
```

### GET `/v1/documents/{uuid}/status` 🔒
Получение статуса обработки документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.view`

**Path Parameters**:
- `uuid` - UUID документа

**Response 200**:
```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed|pending|failed|processing",
  "progress": 100,
  "created_at": "2025-08-29T12:00:00Z",
  "completed_at": "2025-08-29T12:05:00Z"
}
```

### GET `/v1/documents/{uuid}/result` 🔒
Получение результата обработки документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.view`

**Path Parameters**:
- `uuid` - UUID документа

**Response 200**:
```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed",
  "result": {
    "original_filename": "contract.pdf",
    "processed_content": "...",
    "sections": [...],
    "risks_detected": [...],
    "metadata": {...}
  },
  "cost_credits": 25.5,
  "processing_time_ms": 15000
}
```

### POST `/v1/documents/{uuid}/cancel` 🔒
Отмена обработки документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.cancel`

**Path Parameters**:
- `uuid` - UUID документа

**Response 200**:
```json
{
  "message": "Document processing cancelled",
  "uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

### DELETE `/v1/documents/{uuid}` 🔒
Удаление записи об обработке документа.

**Headers**: `Authorization: Bearer {token}`  
**Permissions**: `documents.delete`

**Path Parameters**:
- `uuid` - UUID документа

**Response 204** (No Content)

## Административные функции

### GET `/v1/documents/admin` 🔒
Список всех обработок с фильтрацией и пагинацией.

**Headers**: `Authorization: Bearer {token}`  
**Roles**: `admin`

**Query Parameters**:
- `status` (string, optional) - фильтр по статусу
- `user_id` (integer, optional) - фильтр по пользователю
- `per_page` (integer, optional) - записей на страницу

**Response 200**:
```json
{
  "data": [
    {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "user_id": 1,
      "status": "completed",
      "original_filename": "contract.pdf",
      "cost_credits": 25.5,
      "created_at": "2025-08-29T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150
  }
}
```

### GET `/v1/documents/admin/stats` 🔒
Статистика по обработкам документов.

**Headers**: `Authorization: Bearer {token}`  
**Roles**: `admin`

**Response 200**:
```json
{
  "total_documents": 1250,
  "completed_today": 45,
  "pending_count": 12,
  "failed_count": 8,
  "total_credits_used": 25000.5,
  "average_processing_time_ms": 12000,
  "top_users": [...]
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

## Примеры использования

### JavaScript/TypeScript
```typescript
// Авторизация
const response = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});

const { token } = await response.json();

// Использование API с токеном
const balance = await fetch('/api/user/credits/balance', {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

### cURL
```bash
# Авторизация
curl -X POST https://api.example.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Проверка баланса
curl -X GET https://api.example.com/api/user/credits/balance \
  -H "Authorization: Bearer YOUR_TOKEN"

# Загрузка документа
curl -X POST https://api.example.com/api/v1/documents \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@document.pdf"
```

---

🔒 - Требует аутентификации  
⚡ - Асинхронная обработка  
📊 - Кешируется  

*Обновлено: 2025-08-29*