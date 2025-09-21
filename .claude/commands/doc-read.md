---
allowed-tools: Glob, Read
argument-hint: [filter_pattern]
description: Loads all documentation files from docs/ folder into context
---

Reads and loads all documentation files from the `docs/` folder into the current context for comprehensive project understanding.

## Process:

1. **Scan documentation folder**:
   - Use `!glob "docs/**/*.md"` to find all markdown files
   - Optional filter pattern to focus on specific files

2. **Read all documentation files**:
   - Load each documentation file using Read tool
   - Process files in logical order (summary first, then specific docs)
   - Build comprehensive context of project documentation

3. **Provide documentation overview**:
   - List all loaded documentation files
   - Brief summary of what each file contains
   - Total documentation scope loaded

## Usage Examples:

`/doc-read`
→ Loads all documentation files from docs/ folder

`/doc-read api`
→ Loads only files containing "api" in the name

`/doc-read "*.md"`
→ Loads all markdown files (same as default)

## Benefits:

- **Complete project context**: Agent has full understanding of project architecture
- **Informed responses**: Can reference any part of documentation
- **Cross-documentation awareness**: Understands relationships between different docs
- **Up-to-date information**: Always reads current state of documentation

## Loaded Documentation Includes:

- Project overview and architecture
- API endpoints and usage
- Service layer documentation
- Database schema and models
- Development workflows
- Testing procedures
- Deployment instructions
- Change logs and updates

This command is useful before starting work on complex features or when needing comprehensive project understanding.