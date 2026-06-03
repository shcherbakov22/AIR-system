# Agent Instructions

## Codebase Memory

This project uses codebase-memory-mcp to maintain a knowledge graph of the codebase.
Prefer MCP graph tools over grep/glob/file-search for code discovery.

Priority order:

1. `search_graph` - find functions, classes, routes, variables by pattern.
2. `trace_path` - trace callers/callees and data flow.
3. `get_code_snippet` - read specific function/class source code.
4. `query_graph` - run Cypher queries for complex patterns.
5. `get_architecture` - high-level project summary.

Fallback to `rg`/file reads for string literals, configs, shell scripts, docs, or when graph results are insufficient.

## Private Local Notes

Before making any changes or running deployment/ops commands, read `docs/PRIVATE_LOCAL_NOTES.md` if it exists.

That file is intentionally untracked and may contain SSH passwords, database credentials, tokens, local deployment details, and environment-specific gotchas. Use it for operational context, but do not commit it, quote secrets from it in final answers, or copy its secrets into tracked files.

## Work Log Discipline

Before making code changes, read:

- `docs/PRIVATE_LOCAL_NOTES.md` if it exists
- `docs/WORKLOG.md`
- `docs/ISSUES_AND_GOTCHAS.md`
- the relevant folder note, if it exists:
  - `apps/platform/NOTES.md`
  - `apps/companion/NOTES.md`
  - `apps/extension/NOTES.md`
  - `apps/hardware-bridge/NOTES.md`

After making code changes, update the logs when the work creates useful operational memory:

- Append a concise entry to `docs/WORKLOG.md` for notable fixes, deployments, behavior changes, migrations, build changes, or reliability lessons.
- Update `docs/ISSUES_AND_GOTCHAS.md` for bugs, traps, failed approaches, fragile areas, or recurring failure modes discovered during the fix.
- Update the relevant folder `NOTES.md` when the detail is specific to one code area.

Do not duplicate normal commit diffs in the work log. Record why the change mattered, what was verified, and any deployment or rollback notes.

## Work Log Entry Format

Use this shape unless there is a clear reason not to:

```md
## YYYY-MM-DD - Short Title

Changed:
- ...

Verified:
- ...

Notes:
- ...
```

For unresolved issues, use:

```md
## Short Issue Title

Status: open|watching|resolved

Details:
- ...

Current guidance:
- ...
```

## Deployment Notes

Production app server: `192.168.11.228`.

Typical platform deploy:

- sync changed files into `/var/www/school-system-redo/platform`
- run migrations if needed: `php artisan migrate --force`
- rebuild frontend if JS/CSS changed: `npm run build`
- clear caches: `php artisan optimize:clear`

Do not deploy AI/experimental features unless explicitly requested.

## Permissions

Do not add Windows permission hardening, deny rules, or broad `icacls` changes unless the user explicitly requests it. Previous permission-lock attempts caused repair failures. Prefer monitoring, diagnostics, and reversible repair actions.
