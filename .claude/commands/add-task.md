---
allowed-tools: Bash(curl:*), Bash(grep:*), Read, Glob
argument-hint: <task_concept>
description: Creates a detailed YouTrack task based on task concept and project documentation
---

Creates a new task in YouTrack project RAS with expanded description based on task concept and project documentation.

## Process:

1. **Read project documentation** from `docs/` folder to understand system architecture
2. **Analyze task concept** provided in arguments
3. **Generate detailed description** including:
   - Clear task summary for title
   - Detailed implementation steps
   - Relevant technical context from documentation
   - Dependencies and considerations
   - Acceptance criteria

4. **Create YouTrack task** with generated content:
   - Get YouTrack token: `!grep "YOUTRACK_API_TOKEN" .env`
   - Create task via API with detailed summary and description

## Technical Context Available:

Based on project documentation, the system includes:
- **Backend**: Laravel 11 + PHP 8.3, PostgreSQL, Redis, MinIO
- **Frontend**: React 18 + TypeScript, Inertia.js, Tabler UI
- **AI Integration**: Claude API (Sonnet 4, 3.5 Sonnet/Haiku)
- **Document Processing**: PDF/DOCX/TXT parsing with anchor system
- **Services**: Parser, Structure Analysis, LLM, Prompt Management, Validation
- **Features**: Credit system, document translation, risk analysis

## Examples:

`/add-task Добавить экспорт результатов в PDF`
→ Generates detailed task about implementing PDF export functionality with technical specifications

`/add-task Улучшить парсинг DOCX документов`
→ Creates comprehensive task covering DOCX parser improvements with architecture context

`/add-task Добавить поддержку новой валюты`
→ Expands into detailed multi-currency implementation task

The command will automatically expand simple concepts into detailed technical tasks suitable for development.