# Laravel Queue Management с Supervisor

## 🚀 Обзор

Система использует Laravel Queues с Redis для асинхронной обработки документов. Supervisor управляет процессами-worker'ами для обеспечения надежности в продакшене.

## 📋 Архитектура очередей

### Типы очередей:

1. **`default`** - Основная очередь для общих задач (2 worker'а)
2. **`document-analysis`** - Анализ структуры документов (1 worker)  
3. **`document-processing`** - LLM-обработка документов (2 worker'а)

### Workflow обработки:

```
Document Upload → AnalyzeDocumentStructureJob (document-analysis)
                ↓
              Document Estimation → ProcessDocumentJob (document-processing)
                ↓
            Document Completed
```

## 🔧 Настройка

### Docker-compose

```bash
# Запуск всех сервисов включая Supervisor
docker-compose up -d

# Проверка статуса
docker-compose ps
```

### Конфигурационные файлы:

- `docker/supervisor/conf.d/laravel-worker.conf` - Конфигурация worker'ов
- `docker/supervisor/supervisord.conf` - Основная конфигурация Supervisor
- `config/document.php` - Настройки очередей Laravel

## 📊 Мониторинг

### Управление через скрипт:

```bash
# Конфигурация очередей (переменные окружения)
./bin/queue-status.sh config

# Статус всех worker'ов
./bin/queue-status.sh status

# Перезапуск worker'ов
./bin/queue-status.sh restart  

# Логи конкретной очереди
./bin/queue-status.sh logs document-analysis

# Неудачные задачи
./bin/queue-status.sh failed

# Повтор всех неудачных задач
./bin/queue-status.sh retry all
```

### Web-интерфейс Supervisor:

- URL: http://localhost:9001
- Username: `admin`
- Password: `secret123`

### Laravel команды:

```bash
# Статистика очередей
php artisan queue:monitor

# Неудачные задачи  
php artisan queue:failed

# Повтор конкретной задачи
php artisan queue:retry {id}

# Очистка неудачных задач
php artisan queue:flush
```

## ⚙️ Настройка worker'ов

### Основные параметры:

```ini
# laravel-worker.conf
command=php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=300
numprocs=2          # Количество процессов
stopwaitsecs=3600   # Время ожидания graceful shutdown
```

### Параметры по очередям:

| Очередь | Worker'ы | Timeout | Max Time | Tries |
|---------|----------|---------|----------|-------|
| default | 2 | 300s | 3600s | 3 |
| document-analysis | 1 | 300s | 3600s | 3 |  
| document-processing | 2 | 300s | 3600s | 3 |

## 🚨 Troubleshooting

### Проблемы с памятью:

```bash
# Перезапуск worker'ов для очистки памяти
./bin/queue-status.sh restart

# Проверка использования памяти
docker stats laravel_supervisor
```

### Заблокированные задачи:

```bash
# Проверка активных задач
php artisan queue:monitor

# Очистка заблокированных задач
docker-compose exec supervisor supervisorctl restart all
```

### Логи:

```bash
# Все логи
./bin/queue-status.sh logs

# Специфичная очередь
./bin/queue-status.sh logs document-analysis

# Системные логи Supervisor
docker-compose logs supervisor
```

## 🔒 Безопасность

### Переменные окружения:

```env
# Основные настройки очередей
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

# Настройки документообработки
DOCUMENT_ANALYSIS_QUEUE=document-analysis
DOCUMENT_PROCESSING_QUEUE=document-processing
ANALYSIS_JOB_MAX_TRIES=3
ANALYSIS_JOB_TIMEOUT=300
ANALYSIS_JOB_RETRY_DELAY=60

# Claude API для worker'ов
CLAUDE_API_KEY=your-claude-api-key
CLAUDE_DEFAULT_MODEL=claude-3-5-sonnet-20241022
```

**Важно**: Все переменные окружения автоматически передаются в Supervisor-контейнер через docker-compose.yml

### Пользователи и права:

- Worker'ы запускаются под пользователем `www-data`
- Логи пишутся в `/var/www/storage/logs/`
- Конфигурация в `/etc/supervisor/conf.d/`

## 📈 Производительность

### Рекомендации по масштабированию:

1. **Увеличение worker'ов** для высокой нагрузки:
   ```ini
   numprocs=4  # document-processing
   numprocs=2  # document-analysis
   ```

2. **Мониторинг ресурсов**:
   ```bash
   # CPU/Memory usage
   docker stats laravel_supervisor
   
   # Queue depth
   php artisan queue:monitor
   ```

3. **Горизонтальное масштабирование**:
   - Несколько экземпляров supervisor-контейнеров
   - Shared Redis instance
   - Load balancing

## 🔄 Обновления

### Деплой новых job'ов:

```bash
# 1. Остановка worker'ов
./bin/queue-status.sh stop

# 2. Обновление кода
git pull origin main

# 3. Перестроение контейнеров
docker-compose build supervisor

# 4. Запуск worker'ов
./bin/queue-status.sh start
```

### Graceful restart:

```bash
# Мягкий перезапуск (завершает текущие задачи)
./bin/queue-status.sh restart
```

---

> **Важно**: В продакшене всегда используйте Supervisor для управления Laravel queue worker'ами. Без него асинхронные задачи не будут обрабатываться!