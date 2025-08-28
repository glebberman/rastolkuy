#!/bin/bash

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}🔍 Мониторинг логов Laravel...${NC}"
echo -e "${BLUE}Логи сохраняются в: storage/logs/laravel.log${NC}"
echo -e "${YELLOW}Нажмите Ctrl+C для остановки${NC}"
echo ""

# Создаем файл лога если его нет
touch storage/logs/laravel.log

# Следим за логами в реальном времени
tail -f storage/logs/laravel.log | while read line; do
    if [[ $line == *"ERROR"* ]]; then
        echo -e "${RED}$line${NC}"
    elif [[ $line == *"WARNING"* ]]; then
        echo -e "${YELLOW}$line${NC}"
    elif [[ $line == *"INFO"* ]]; then
        echo -e "${GREEN}$line${NC}"
    else
        echo "$line"
    fi
done