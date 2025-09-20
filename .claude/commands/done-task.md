---
allowed-tools: Bash(curl:*), Bash(grep:*)
argument-hint: <task_code>
description: Marks a YouTrack task as completed (stage = "Готово")
---

Marks a YouTrack task as completed by updating its stage to "Готово" (Done).

1. Get YouTrack token: `!grep "YOUTRACK_API_TOKEN" .env`
2. Update task stage:
```bash
!curl -X POST "https://glebberman.youtrack.cloud/api/issues/$1/fields/Stage" \
  -H "Authorization: Bearer $YOUTRACK_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"value": {"name": "Готово"}}'
```
3. Confirm status change

Example: `/done-task RAS-33`

This will update task RAS-33 stage to "Готово" and display confirmation.