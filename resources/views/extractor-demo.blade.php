<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Extractor Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">🤖 Document Extraction System Demo</h1>
            <p class="text-gray-600">Система извлечения и анализа документов RAS-4 с поддержкой кириллицы</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Upload Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">📄 Загрузить документ</h2>
                
                <form id="upload-form" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="document" class="block text-sm font-medium text-gray-700 mb-2">
                            Выберите текстовый файл:
                        </label>
                        <input type="file" id="document" name="document" accept=".txt,.text" 
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div class="mb-4">
                        <label for="config" class="block text-sm font-medium text-gray-700 mb-2">
                            Профиль обработки:
                        </label>
                        <select id="config" name="config" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="default">По умолчанию</option>
                            <option value="fast">Быстрый (без форматирования)</option>
                            <option value="large">Большие файлы</option>
                            <option value="streaming">Потоковая обработка</option>
                        </select>
                    </div>

                    <button type="submit" id="upload-btn" 
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50">
                        🚀 Извлечь данные
                    </button>
                </form>

                <div id="upload-progress" class="mt-4 hidden">
                    <div class="bg-blue-100 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-blue-800">Обработка документа...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Files -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">🧪 Тестовые примеры</h2>
                
                <div class="space-y-3">
                    <button onclick="testBasic()" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50">
                        <div class="font-medium">Базовый тест</div>
                        <div class="text-sm text-gray-600">Тестирование всех типов элементов</div>
                    </button>
                    
                    <button onclick="testStreaming()" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50">
                        <div class="font-medium">Потоковая обработка</div>
                        <div class="text-sm text-gray-600">Большой файл для тестирования streaming</div>
                    </button>
                </div>

                <div class="mt-4 p-4 bg-gray-50 rounded-md">
                    <h3 class="font-medium text-gray-900 mb-2">Поддерживаемые возможности:</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>✅ Кириллица (UTF-8, Windows-1251)</li>
                        <li>✅ Markdown заголовки (# ## ###)</li>
                        <li>✅ Нумерованные и ненумерованные списки</li>
                        <li>✅ Автоматическое определение кодировки</li>
                        <li>✅ Защита от DoS атак</li>
                        <li>✅ Потоковая обработка больших файлов</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div id="results" class="mt-8 hidden">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">📊 Результаты извлечения</h2>
                <div id="results-content"></div>
            </div>
        </div>
    </div>

    <script>
        // CSRF Token setup
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Form submission
        document.getElementById('upload-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('upload-btn');
            const uploadProgress = document.getElementById('upload-progress');
            
            uploadBtn.disabled = true;
            uploadProgress.classList.remove('hidden');
            
            try {
                const response = await fetch('/extractor-upload', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const result = await response.json();
                displayResults(result);
                
            } catch (error) {
                displayResults({
                    status: 'error',
                    message: 'Ошибка сети: ' + error.message
                });
            } finally {
                uploadBtn.disabled = false;
                uploadProgress.classList.add('hidden');
            }
        });

        // Test functions
        async function testBasic() {
            const response = await fetch('/test-extractor');
            const result = await response.json();
            displayResults(result);
        }

        async function testStreaming() {
            const response = await fetch('/test-extractor-streaming');
            const result = await response.json();
            displayResults(result);
        }

        // Display results
        function displayResults(result) {
            const resultsDiv = document.getElementById('results');
            const resultsContent = document.getElementById('results-content');
            
            if (result.status === 'error') {
                resultsContent.innerHTML = `
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <strong>Ошибка:</strong> ${result.message}
                    </div>
                `;
            } else {
                let html = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 mb-2">Информация о файле</h3>
                `;
                
                if (result.file_info) {
                    html += `
                        <div class="space-y-1 text-sm">
                            <div><span class="font-medium">Имя:</span> ${result.file_info.original_name || 'Тестовый файл'}</div>
                            <div><span class="font-medium">Размер:</span> ${result.file_info.size || result.file_info.file_size} байт</div>
                            <div><span class="font-medium">MIME:</span> ${result.file_info.mime_type}</div>
                            <div><span class="font-medium">Кодировка:</span> ${result.file_info.encoding}</div>
                            <div><span class="font-medium">Строк:</span> ${result.file_info.line_count || 'N/A'}</div>
                            <div><span class="font-medium">Режим:</span> ${result.file_info.processing_mode || 'regular'}</div>
                        </div>
                    `;
                }
                
                html += `
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 mb-2">Статистика обработки</h3>
                            <div class="space-y-1 text-sm">
                                <div><span class="font-medium">Время:</span> ${result.extraction_time || result.extraction?.time}s</div>
                                <div><span class="font-medium">Элементов:</span> ${result.elements_count || result.extraction?.elements_count}</div>
                                <div><span class="font-medium">Конфигурация:</span> ${result.extraction?.config_used || 'default'}</div>
                            </div>
                        </div>
                    </div>
                `;

                if (result.elements && result.elements.length > 0) {
                    html += `
                        <div class="mb-6">
                            <h3 class="font-semibold text-gray-900 mb-3">Извлеченные элементы (${result.elements.length})</h3>
                            <div class="space-y-3">
                    `;
                    
                    result.elements.forEach((element, index) => {
                        const typeColors = {
                            'header': 'bg-purple-100 text-purple-800',
                            'paragraph': 'bg-blue-100 text-blue-800',
                            'list': 'bg-green-100 text-green-800',
                            'text': 'bg-gray-100 text-gray-800',
                            'table': 'bg-yellow-100 text-yellow-800'
                        };
                        
                        const typeColor = typeColors[element.type] || 'bg-gray-100 text-gray-800';
                        
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${typeColor}">
                                        ${element.type}
                                    </span>
                                    <span class="text-xs text-gray-500">Уверенность: ${element.confidence}</span>
                                </div>
                                <div class="text-sm text-gray-900 whitespace-pre-wrap">${element.content}</div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                }

                // Metrics
                if (result.metrics) {
                    html += `
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-900 mb-2">Метрики производительности</h3>
                            <div class="text-xs font-mono text-gray-600">
                                ${JSON.stringify(result.metrics, null, 2)}
                            </div>
                        </div>
                    `;
                }

                resultsContent.innerHTML = html;
            }
            
            resultsDiv.classList.remove('hidden');
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>