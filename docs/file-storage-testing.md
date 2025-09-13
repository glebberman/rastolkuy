# Тестирование файлового хранилища S3/MinIO

## Обзор

В проекте реализованы различные уровни тестирования для работы с файловым хранилищем:

1. **Unit тесты** - используют `Storage::fake()` для эмуляции хранилища
2. **Integration тесты** - работают с реальным MinIO в Docker контейнерах
3. **Manual тесты** - Artisan команды для проверки соединения

## Типы тестов

### 1. Unit тесты (с fake storage)

```php
// В тестах используется fake storage
Storage::fake('local');
Storage::fake('minio');
Storage::fake('s3');

// Проверка что файл был сохранен
Storage::disk('minio')->assertExists($filePath);
```

**Команда для запуска:**
```bash
task test
# или
php artisan test
```

**Преимущества:**
- Быстрые
- Не требуют внешних зависимостей
- Изолированные

**Ограничения:**
- Не проверяют реальную интеграцию с S3/MinIO
- Не тестируют сетевые проблемы
- Не проверяют авторизацию и конфигурацию

### 2. Integration тесты (с реальным MinIO)

```php
// tests/Integration/FileStorageIntegrationTest.php
// tests/Integration/MinIODocumentProcessingTest.php
```

**Команды для запуска:**
```bash
# Все интеграционные тесты
task test-integration

# Только тесты хранилища файлов
task test-storage

# Только тесты документооборота с MinIO
task test-minio
```

**Преимущества:**
- Тестируют реальную интеграцию
- Проверяют соединение с MinIO
- Тестируют все операции с файлами

**Требования:**
- Запущенные Docker контейнеры
- Настроенный MinIO
- Созданные buckets

### 3. Manual тестирование

**Проверка соединения с хранилищем:**
```bash
# Тест соединения с MinIO
docker-compose exec app php artisan storage:test-connection minio

# Тест соединения с локальным хранилищем
docker-compose exec app php artisan storage:test-connection local
```

**Миграция файлов между хранилищами:**
```bash
# Dry-run миграции из local в MinIO
docker-compose exec app php artisan storage:migrate-files --from=local --to=minio --dry-run

# Реальная миграция
docker-compose exec app php artisan storage:migrate-files --from=local --to=minio
```

## Настройка для тестирования

### 1. Конфигурация MinIO

```yaml
# docker-compose.yml
minio:
  image: minio/minio:latest
  environment:
    MINIO_ROOT_USER: minioadmin
    MINIO_ROOT_PASSWORD: minioadmin
  ports:
    - "9000:9000"
    - "9001:9001"
```

### 2. Создание buckets

```bash
# Создание bucket для тестов
docker-compose exec minio mc alias set local http://localhost:9000 minioadmin minioadmin
docker-compose exec minio mc mb local/laravel --ignore-existing
docker-compose exec minio mc mb local/rastolkuy-documents --ignore-existing
```

### 3. Переменные окружения

```env
# .env
FILESYSTEM_DISK=minio
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY_ID=minioadmin
MINIO_SECRET_ACCESS_KEY=minioadmin
MINIO_REGION=us-east-1
MINIO_BUCKET=laravel
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

## Структура тестов

### FileStorageIntegrationTest

Тестирует основную функциональность `FileStorageService`:

- ✅ Сохранение и получение файлов
- ✅ Операции с uploaded файлами
- ✅ Копирование между разными дисками
- ✅ Работа с разными типами хранилищ
- ✅ Команда миграции файлов
- ✅ Получение информации о хранилище

### MinIODocumentProcessingTest

Тестирует интеграцию с API документооборота:

- ✅ Загрузка документов через API в MinIO
- ✅ Удаление документов из MinIO
- ✅ Генерация публичных URL
- ✅ Информация о хранилище
- ✅ Все операции с файлами

## Проверка результатов

### 1. Логи

```bash
# Просмотр логов приложения
docker-compose exec app tail -f storage/logs/laravel.log

# Логи MinIO
docker-compose logs minio
```

### 2. MinIO Web Console

Откройте http://localhost:9002 для доступа к web-интерфейсу MinIO:
- Логин: `minioadmin`
- Пароль: `minioadmin`

**Примечание**: Supervisor доступен на http://localhost:9001

### 3. Проверка файлов через CLI

```bash
# Список файлов в bucket
docker-compose exec minio mc ls local/laravel

# Просмотр содержимого файла
docker-compose exec minio mc cat local/laravel/test-file.txt
```

## Debugging проблем

### 1. Ошибка соединения с MinIO

```bash
# Проверка статуса контейнера
docker-compose ps minio

# Проверка логов MinIO
docker-compose logs minio

# Тест соединения
docker-compose exec app php artisan storage:test-connection minio -v
```

### 2. Проблемы с bucket

```bash
# Список buckets
docker-compose exec minio mc ls local/

# Создание bucket
docker-compose exec minio mc mb local/bucket-name

# Проверка политик bucket
docker-compose exec minio mc policy get local/bucket-name
```

### 3. Проблемы с конфигурацией

```bash
# Проверка конфигурации Laravel
docker-compose exec app php artisan config:show filesystems.disks.minio

# Очистка кеша конфигурации
docker-compose exec app php artisan config:clear
```

## Лучшие практики

### 1. Использование различных environment

- **Local development**: MinIO в Docker
- **Staging**: AWS S3 или другой S3-compatible сервис
- **Production**: AWS S3 с proper credentials

### 2. Тестирование различных сценариев

```php
// Тест с большими файлами
$largeFile = UploadedFile::fake()->create('large.pdf', 50000); // 50MB

// Тест с различными форматами
$files = [
    UploadedFile::fake()->create('doc.pdf'),
    UploadedFile::fake()->create('doc.docx'),
    UploadedFile::fake()->create('doc.txt'),
];

// Тест ошибок сети (можно эмулировать остановкой MinIO)
```

### 3. Cleanup после тестов

```php
protected function tearDown(): void
{
    // Очистка тестовых файлов
    $this->cleanupTestFiles();
    parent::tearDown();
}
```

## CI/CD интеграция

Для автоматических тестов в CI/CD добавьте services в pipeline:

```yaml
# GitHub Actions example
services:
  minio:
    image: minio/minio:latest
    env:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin
    ports:
      - 9000:9000
    options: --health-cmd "curl -f http://localhost:9000/minio/health/live"
```

Это обеспечивает полноценное тестирование файлового хранилища на всех уровнях.