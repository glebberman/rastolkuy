---
allowed-tools: Bash(curl:*), Bash(grep:*), Bash(git checkout:*), Bash(git branch:*), Read, Glob, Task
argument-hint: <task_code>
description: Start working on a YouTrack task with full project context and development guidelines
---

Fetches YouTrack task details, loads project documentation, reads agent instructions, and creates a new git branch for development.

## Process:

1. **Load project context (if not already loaded):**
   - Read all documentation from `docs/` folder using Glob and Read tools
   - Load project architecture, services, API endpoints, workflow processes
   - Build comprehensive understanding of system structure

2. **Read agent development instructions:**
   - Load `dev/agent-instruction.md` for development guidelines
   - Follow coding standards, patterns, and best practices
   - Understand project-specific conventions and requirements

3. **Fetch task details:**
   - Get YouTrack token: `!grep "YOUTRACK_API_TOKEN" .env`
   - Fetch task details:
   ```bash
   !curl -H "Authorization: Bearer $YOUTRACK_TOKEN" \
     "https://glebberman.youtrack.cloud/api/issues/$1?fields=summary,description"
   ```

4. **Create development branch:**
   - Extract key words from task summary for branch name
   - Create branch: `!git checkout -b $1_key-words-from-summary`

5. **Optional: Launch development agent (for complex tasks):**
   - Use Task tool with general-purpose agent for multi-step implementation
   - Provide task context, documentation, and development guidelines
   - Focus on following project architecture and conventions

## Documentation Context Loaded:

After running this command, the agent will have comprehensive knowledge of:
- **Project Architecture**: Laravel 11, services layer, LLM integration
- **API Structure**: v1 endpoints, Request/Response patterns
- **Document Processing**: Upload → Estimate → Process workflow
- **Database Schema**: Models, relationships, migrations
- **Service Layer**: CreditService, DocumentProcessingService, etc.
- **Security**: Authentication, permissions, validation
- **Testing**: PHPUnit, PHPStan Level 9 requirements
- **Deployment**: Docker, queues, configuration

## Agent Instructions Integration:

The agent will follow development guidelines from `dev/agent-instruction.md`:
- Code quality standards
- Architecture patterns
- Testing requirements
- Documentation practices
- Git workflow conventions

## Branch Naming Examples:

- `RAS-33: Implement user authentication` → `RAS-33_implement-user-authentication`
- `RAS-45: Fix database migration` → `RAS-45_fix-database-migration`
- `RAS-147: Export documents to PDF` → `RAS-147_export-documents-pdf`

## Usage Examples:

`/do-task RAS-33`
→ Load docs, read instructions, fetch task, create branch, ready for development

`/do-task RAS-147`
→ Full context loading, complex task analysis, branch creation with development setup

This command ensures the agent has complete project understanding and follows all development guidelines before starting work on any task.