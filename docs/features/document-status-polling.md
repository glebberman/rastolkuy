# Автоматическое обновление статуса документов

## Описание

Dashboard автоматически отслеживает статус обрабатываемых документов и обновляет интерфейс в реальном времени без необходимости ручного обновления страницы.

## Как работает

### Polling механизм

1. **Автоматическая проверка**: Когда в списке есть документы со статусом `processing` или `pending`, Dashboard автоматически запускает polling каждые 2 секунды.

2. **Silent refresh**: Обновление происходит в фоновом режиме без показа индикатора загрузки, чтобы не мешать пользователю.

3. **Автоматическая остановка**: Когда все документы завершены (или завершились с ошибкой), polling автоматически останавливается для экономии ресурсов.

### Визуальные индикаторы

1. **Spinner иконка**: Документы в статусе `processing` или `pending` показывают анимированный spinner вместо обычной иконки.

2. **Пульсирующая строка**: Строка таблицы с обрабатываемым документом имеет пульсирующую анимацию для привлечения внимания.

3. **Подсветка фона**: Обрабатываемые документы выделяются классом `table-active`.

## Реализация

### Компонент Dashboard

```typescript
// Auto-refresh documents when there are processing documents
useEffect(() => {
    if (!isAuthenticated) return;

    // Check if there are any documents being processed
    const hasProcessingDocuments = documents.some(
        doc => doc.status === 'processing' || doc.status === 'pending'
    );

    if (!hasProcessingDocuments) return;

    // Poll every 2 seconds for processing documents
    const pollInterval = setInterval(() => {
        loadDocuments(documentsPagination.current_page, true);
    }, 2000);

    return () => clearInterval(pollInterval);
}, [isAuthenticated, documents, documentsPagination.current_page]);
```

### CSS анимация

```scss
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}
```

## Производительность

- **Условный polling**: Polling запускается только когда есть активные документы
- **Silent updates**: Обновления не блокируют UI
- **Автоматическая очистка**: Интервалы очищаются при размонтировании компонента или завершении обработки

## Совместимость

- ✅ React 18+
- ✅ Inertia.js
- ✅ Bootstrap 5
- ✅ Modern browsers (Chrome, Firefox, Safari, Edge)

## Альтернативные подходы

В будущем можно рассмотреть:

1. **WebSockets** - для real-time обновлений без polling
2. **Server-Sent Events (SSE)** - односторонняя связь от сервера
3. **Long polling** - более эффективная версия обычного polling

## Конфигурация

Интервал polling можно настроить в коде компонента:

```typescript
const pollInterval = setInterval(() => {
    loadDocuments(documentsPagination.current_page, true);
}, 2000); // 2 секунды
```

Рекомендуемые значения:
- **2-3 секунды** - для быстрой обработки документов
- **5-10 секунд** - для длительной обработки
- **Не менее 1 секунды** - чтобы не перегружать сервер
