# Deployment & Configuration Guide

## Обзор развертывания

Проект **Растолкуй** разработан для контейнеризованного развертывания с использованием Docker и Docker Compose. Архитектура включает все необходимые сервисы для полнофункциональной работы системы анализа юридических документов.

### Архитектура развертывания

```
┌─────────────────┐    ┌─────────────────┐
│     Nginx       │    │   React SPA     │
│   (Reverse      │    │   (Frontend)    │
│    Proxy)       │    │                 │
└─────────────────┘    └─────────────────┘
         │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐
│   Laravel App   │    │      Redis      │
│   (PHP 8.3)     │◄───┤   (Cache &      │
│                 │    │    Queue)       │
└─────────────────┘    └─────────────────┘
         │
         ▼
┌─────────────────┐    ┌─────────────────┐
│   PostgreSQL    │    │     MinIO       │
│   (Database)    │    │  (S3 Storage)   │
└─────────────────┘    └─────────────────┘
```

---

## Системные требования

### Минимальные требования
- **CPU**: 2 ядра
- **RAM**: 4 GB
- **Диск**: 20 GB SSD
- **ОС**: Linux (Ubuntu 20.04+, CentOS 7+), macOS, Windows 10+

### Рекомендуемые для продакшена
- **CPU**: 4+ ядер
- **RAM**: 8+ GB
- **Диск**: 100+ GB SSD
- **Network**: 100+ Mbps

### Программное обеспечение
- Docker 20.0+
- Docker Compose 2.0+
- Task runner (go-task) - опционально

---

## Установка и настройка

### 1. Клонирование репозитория
```bash
git clone https://github.com/your-org/rastolkuy.git
cd rastolkuy
```

### 2. Настройка environment файлов

**Создание .env файла**:
```bash
cp .env.example .env
```

**Основные настройки .env**:
```env
# Приложение
APP_NAME="Растолкуй"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:GENERATED_KEY_HERE

# Админ пользователь
APP_USER_ADMIN_EMAIL=admin@your-domain.com
APP_USER_ADMIN_DEFAULT_PASSWORD=SecurePassword123!

# База данных PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=rastolkuy
DB_USERNAME=laravel
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# MinIO S3 Storage
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY_ID=your_minio_key
MINIO_SECRET_ACCESS_KEY=your_minio_secret
MINIO_DEFAULT_REGION=us-east-1
MINIO_BUCKET=rastolkuy
MINIO_USE_PATH_STYLE_ENDPOINT=true

# Claude API
CLAUDE_API_KEY=your_claude_api_key
CLAUDE_DEFAULT_MODEL=claude-3-5-sonnet-20241022
CLAUDE_MAX_TOKENS=4096

# Кредитная система
CREDITS_INITIAL_BALANCE=100
CREDITS_USD_RATE=100
CREDITS_LOW_BALANCE_THRESHOLD=10
CREDITS_MAXIMUM_BALANCE=100000

# YouTrack интеграция
YOUTRACK_API_TOKEN=your_youtrack_token

# Почта (Production)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Очереди
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# Логирование
LOG_CHANNEL=stack
LOG_LEVEL=info
```

### 3. Быстрый запуск с Task Runner

**Установка Task Runner** (если не установлен):
```bash
# macOS
brew install go-task/tap/go-task

# Linux
sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b ~/.local/bin

# Windows
choco install go-task
```

**Полная настройка проекта**:
```bash
# Автоматическая настройка всего проекта
task setup
```

Эта команда выполнит:
1. Сборку Docker образов
2. Запуск всех сервисов
3. Установку PHP зависимостей
4. Генерацию ключа приложения
5. Миграции базы данных с seed данными

### 4. Ручная настройка (альтернативный способ)

**Сборка и запуск**:
```bash
# Сборка контейнеров
docker-compose build

# Запуск сервисов в фоновом режиме
docker-compose up -d

# Установка зависимостей
docker-compose exec app composer install

# Генерация ключа приложения
docker-compose exec app php artisan key:generate

# Миграции и сидеры
docker-compose exec app php artisan migrate:fresh --seed

# Установка frontend зависимостей
docker-compose exec app npm install

# Сборка frontend
docker-compose exec app npm run build
```

---

## Конфигурация сервисов

### 1. Nginx (Reverse Proxy)

**Конфигурация**: `docker/nginx/conf.d/app.conf`

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # File upload size
    client_max_body_size 50M;

    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Frontend SPA
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM handling
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 2. PostgreSQL Database

**Конфигурация**:
- Версия: PostgreSQL 16
- Persistent volume: `postgres_data`
- Порт: 5432 (внешний доступ для разработки)

**Продакшн настройки**:
```yaml
postgres:
  image: postgres:16-alpine
  restart: unless-stopped
  environment:
    POSTGRES_DB: ${DB_DATABASE}
    POSTGRES_USER: ${DB_USERNAME}
    POSTGRES_PASSWORD: ${DB_PASSWORD}
    # Производительность
    POSTGRES_SHARED_PRELOAD_LIBRARIES: pg_stat_statements
    POSTGRES_MAX_CONNECTIONS: 100
    POSTGRES_SHARED_BUFFERS: 256MB
    POSTGRES_EFFECTIVE_CACHE_SIZE: 1GB
  volumes:
    - postgres_data:/var/lib/postgresql/data
    - ./docker/postgres/postgresql.conf:/etc/postgresql/postgresql.conf
```

### 3. Redis (Cache & Queues)

**Конфигурация**:
- Версия: Redis 7.4
- Persistent volume: `redis_data`
- Использование: Cache, Sessions, Queue driver

**Продакшн настройки**:
```yaml
redis:
  image: redis:7.4-alpine
  restart: unless-stopped
  command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
  volumes:
    - redis_data:/data
  sysctls:
    - net.core.somaxconn=65535
```

### 4. MinIO (S3-Compatible Storage)

**Конфигурация**:
- Веб интерфейс: http://localhost:9002
- API endpoint: http://localhost:9000
- Persistent volume: `minio_data`

**Продакшн настройки**:
```yaml
minio:
  image: minio/minio:latest
  restart: unless-stopped
  environment:
    MINIO_ROOT_USER: ${MINIO_ROOT_USER}
    MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    MINIO_BROWSER_REDIRECT_URL: https://storage.your-domain.com
  volumes:
    - minio_data:/data
  command: server /data --console-address ":9001" --address ":9000"
```

---

## Команды для разработки

### Task Runner команды

**Основные команды**:
```bash
# Запуск всех проверок качества
task quality

# PHPStan анализ
task phpstan

# Исправление стиля кода
task cs-fix

# Запуск тестов
task test

# Laravel Artisan команды
task artisan -- migrate:fresh --seed
task artisan -- tinker

# Доступ к контейнеру
task shell

# Просмотр логов
task logs -- -f app

# Frontend разработка
task npm-dev
task npm-build
```

**Утилитарные команды**:
```bash
# Остановка всех сервисов
task dc-down

# Полная очистка
task cleanup

# Пересборка контейнеров
task dc-build -- --no-cache
```

### Docker Compose команды

**Управление сервисами**:
```bash
# Запуск
docker-compose up -d

# Остановка
docker-compose down

# Логи
docker-compose logs -f app

# Выполнение команд
docker-compose exec app php artisan migrate
docker-compose exec app composer install
docker-compose exec postgres psql -U laravel -d rastolkuy
```

---

## Production развертывание

### 1. Окружение

**Важные настройки для продакшена**:
```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

# HTTPS настройки
SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=your-domain.com
```

### 2. SSL/TLS настройки

**Nginx с Let's Encrypt**:
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /etc/ssl/certs/your-domain.com.crt;
    ssl_certificate_key /etc/ssl/private/your-domain.com.key;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # ... остальная конфигурация
}

# HTTP redirect
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

### 3. Monitoring

**Health checks**:
```yaml
app:
  healthcheck:
    test: ["CMD", "php", "artisan", "tinker", "--execute=echo 'OK';"]
    interval: 30s
    timeout: 10s
    retries: 3
    
postgres:
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]
    interval: 30s
    timeout: 5s
    retries: 5
```

**Мониторинг логов**:
```bash
# Настройка логирования в продакшене
# config/logging.php

'channels' => [
    'production' => [
        'driver' => 'stack',
        'channels' => ['single', 'slack', 'syslog'],
    ],
],
```

### 4. Бэкапы

**База данных**:
```bash
#!/bin/bash
# Ежедневный бэкап PostgreSQL
docker-compose exec -T postgres pg_dump -U laravel rastolkuy | gzip > backups/db-$(date +%Y%m%d-%H%M%S).sql.gz
```

**MinIO storage**:
```bash
# Синхронизация с внешним S3
mc mirror minio/rastolkuy s3/your-bucket/backups/$(date +%Y%m%d)/
```

### 5. Автообновления

**GitHub Actions Deploy**:
```yaml
name: Deploy
on:
  push:
    branches: [main]
    
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to server
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.KEY }}
          script: |
            cd /var/www/rastolkuy
            git pull origin main
            docker-compose down
            docker-compose build app
            docker-compose up -d
            docker-compose exec -T app php artisan migrate --force
            docker-compose exec -T app php artisan config:cache
            docker-compose exec -T app php artisan view:cache
            docker-compose exec -T app php artisan route:cache
```

---

## Масштабирование

### 1. Load Balancer

**Nginx upstream**:
```nginx
upstream rastolkuy_app {
    server app1:9000 weight=3;
    server app2:9000 weight=2;
    server app3:9000 weight=1;
}

server {
    location ~ \.php$ {
        fastcgi_pass rastolkuy_app;
        # ... остальные настройки
    }
}
```

### 2. Database scaling

**Read replicas**:
```yaml
postgres-master:
  image: postgres:16-alpine
  environment:
    POSTGRES_REPLICATION_USER: replica
    POSTGRES_REPLICATION_PASSWORD: replica_pass

postgres-replica:
  image: postgres:16-alpine
  environment:
    PGUSER: replica
    POSTGRES_PASSWORD: replica_pass
    POSTGRES_MASTER_SERVICE: postgres-master
```

### 3. Redis Cluster

```yaml
redis-master:
  image: redis:7.4-alpine
  command: redis-server --port 6379

redis-sentinel:
  image: redis:7.4-alpine
  command: redis-sentinel /usr/local/etc/redis/sentinel.conf
```

---

## Troubleshooting

### Частые проблемы

**1. Permission errors**:
```bash
# Исправление прав доступа
sudo chown -R $USER:$USER ./
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**2. Memory issues**:
```bash
# Увеличение лимитов PHP
# docker/php/local.ini
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M
```

**3. Database connection**:
```bash
# Проверка подключения к БД
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();
```

**4. Cache issues**:
```bash
# Очистка всех кешей
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan view:clear
```

### Логи для отладки

**Важные файлы логов**:
```bash
# Laravel логи
docker-compose exec app tail -f storage/logs/laravel.log

# Nginx логи
docker-compose logs nginx

# PostgreSQL логи
docker-compose logs postgres

# Redis логи
docker-compose logs redis
```

---

## Безопасность

### 1. Firewall настройки

**Открытые порты**:
- 80 (HTTP) - только для редиректа на HTTPS
- 443 (HTTPS) - веб трафик
- 22 (SSH) - только для администрирования

### 2. Environment security

**Защищенные переменные**:
- API ключи должны быть в секретах
- Пароли БД генерируются случайно
- JWT секреты уникальны для каждого окружения

### 3. Container security

**Best practices**:
- Использование non-root пользователей в контейнерах
- Минимальные образы (Alpine Linux)
- Регулярные обновления базовых образов
- Сканирование образов на уязвимости

---

*Обновлено: 2025-08-29*