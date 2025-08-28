#!/bin/bash

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}📋 Последние логи аутентификации:${NC}"
echo ""

# Получаем сегодняшнюю дату в формате логов
TODAY=$(date +"%Y-%m-%d")

# Показываем логи аутентификации за сегодня
grep -E "(User logged in|Login validation|Registration)" storage/logs/laravel.log | grep "$TODAY" | tail -20 | while read line; do
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

echo ""
echo -e "${BLUE}💡 Для мониторинга в реальном времени: ./watch-logs.sh${NC}"