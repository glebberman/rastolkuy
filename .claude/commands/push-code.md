---
allowed-tools: Bash(git add:*), Bash(git commit:*), Bash(git push:*), Bash(git branch:*), Bash(git diff:*)
argument-hint: [description]
description: Commits and pushes changes with task code from branch name
---

Extract task code from current git branch name and create a commit with format: `{TASK_CODE}: {description}`. If description is not provided, generate it from git diff.

1. Get current branch name: `!git branch --show-current`
2. Extract task code from branch (pattern: `RAS-\d+`)
3. Check staged changes: `!git diff --cached --name-only`
4. If no staged changes, show status: `!git status --porcelain`
5. If no description provided, analyze `!git diff --cached` to generate one
6. Commit staged changes: `!git commit -m "{TASK_CODE}: {description}"`
7. Push: `!git push -u origin {current-branch}`

**Note**: This command commits only **staged** changes. Use `git add` manually to stage specific files first.

Examples:
- `/push-code Implement JWT authentication` → "RAS-33: Implement JWT authentication"
- `/push-code` → Auto-generate from git diff