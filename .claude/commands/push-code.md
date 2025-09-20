---
allowed-tools: Bash(git add:*), Bash(git commit:*), Bash(git push:*), Bash(git branch:*), Bash(git diff:*)
argument-hint: [add] [description]
description: Commits and pushes changes with task code from branch name
---

Extract task code from current git branch name and create a commit with format: `{TASK_CODE}: {description}`. If description is not provided, generate it from git diff.

1. Get current branch name: `!git branch --show-current`
2. Extract task code from branch (pattern: `RAS-\d+`)
3. If first argument is "add", stage all changes: `!git add .`
4. Check staged changes: `!git diff --cached --name-only`
5. If no staged changes, show status: `!git status --porcelain`
6. If no description provided, analyze `!git diff --cached` to generate one
7. Commit staged changes: `!git commit -m "{TASK_CODE}: {description}"`
8. Push: `!git push -u origin {current-branch}`

**Note**:
- Without "add" argument: commits only **staged** changes
- With "add" argument: stages all changes first, then commits

Examples:
- `/push-code Implement JWT authentication` → "RAS-33: Implement JWT authentication"
- `/push-code add` → Stage all changes and auto-generate description
- `/push-code add Fix validation bug` → "RAS-33: Fix validation bug"