---
allowed-tools: Bash(curl:*), Bash(grep:*)
argument-hint: <task_description>
description: Creates a new task in YouTrack project RAS
---

Creates a new task in YouTrack project RAS with the provided description.

1. Get YouTrack token: `!grep "YOUTRACK_API_TOKEN" .env`
2. Create task via API:
```bash
!curl -X POST "https://glebberman.youtrack.cloud/api/issues" \
  -H "Authorization: Bearer $YOUTRACK_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"project": {"id": "0-2"}, "summary": "$ARGUMENTS"}'
```

Example:
`/add-task Implement user authentication with JWT tokens`

Returns the created task ID and number for reference.