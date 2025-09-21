---
allowed-tools: Bash(git diff:*), Bash(git branch:*), Read, Edit, Glob, Write
argument-hint: [target_docs]
description: Updates documentation in docs/ folder based on current git branch changes
---

Updates project documentation based on code changes in the current git branch by analyzing diffs and updating relevant documentation files.

## Process:

1. **Analyze current branch changes**:
   - Get current branch: `!git branch --show-current`
   - Get changes since main: `!git diff main...HEAD`
   - Identify modified files and their nature

2. **Determine documentation scope**:
   - Map code changes to relevant documentation files
   - Identify new features, API changes, architecture updates
   - Check if new documentation files are needed

3. **Read existing documentation**:
   - Scan `docs/` folder for relevant files
   - Read current documentation content
   - Identify sections that need updates

4. **Update documentation**:
   - Update existing docs with new information
   - Add new sections for new features
   - Update API endpoints, service descriptions
   - Maintain documentation consistency

5. **Optional target docs** (if specified in arguments):
   - Focus updates only on specified documentation files
   - Examples: `api.md`, `services.md`, `database.md`

## Documentation Mapping:

Based on code changes, updates these docs:

- **API changes** (`app/Http/Controllers/`, `routes/`) → `docs/api.md`
- **Service changes** (`app/Services/`) → `docs/services.md`
- **Database changes** (`database/migrations/`, `app/Models/`) → `docs/database.md`
- **Architecture changes** (major structural) → `docs/project-summary.md`
- **New features** → Multiple docs + potentially new files
- **Queue/Job changes** (`app/Jobs/`) → `docs/queues.md`
- **Testing changes** (`tests/`) → `docs/testing.md`
- **Deployment changes** (`docker/`, config files) → `docs/deployment.md`

## Examples:

`/doc-update`
→ Analyzes all changes and updates all relevant documentation

`/doc-update api.md services.md`
→ Updates only API and services documentation

`/doc-update database.md`
→ Focuses only on database-related documentation updates

## Features:

- **Smart change detection**: Identifies the nature of code changes
- **Context-aware updates**: Uses existing documentation structure
- **Consistency maintenance**: Preserves documentation formatting and style
- **Incremental updates**: Only updates sections that need changes
- **Cross-reference updates**: Updates related sections across multiple docs