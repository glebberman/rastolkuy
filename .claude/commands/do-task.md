---
allowed-tools: Bash(curl:*), Bash(grep:*), Bash(git checkout:*), Bash(git branch:*)
argument-hint: <task_code>
description: Start working on a YouTrack task by creating a new git branch
---

Fetches YouTrack task details and creates a new git branch for development.

1. Get YouTrack token: `!grep "YOUTRACK_API_TOKEN" .env`
2. Fetch task details:
```bash
!curl -H "Authorization: Bearer $YOUTRACK_TOKEN" \
  "https://glebberman.youtrack.cloud/api/issues/$1?fields=summary,description"
```
3. Extract key words from task summary for branch name
4. Create branch: `!git checkout -b $1_key-words-from-summary`

Branch naming examples:
- `RAS-33: Implement user authentication` → `RAS-33_implement-user-authentication`
- `RAS-45: Fix database migration` → `RAS-45_fix-database-migration`

Example: `/do-task RAS-33`