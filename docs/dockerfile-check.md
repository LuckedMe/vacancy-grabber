# Checking the production Dockerfile

The root `Dockerfile` (multi-stage production image: `php-base` → `vendor` → `assets` → `app`, plus `nginx`) is checked with the **global** Claude Code skill `/fix-dockerfile`. It lives in `~/.claude/skills/fix-dockerfile/` and is shared by all projects; it replaced the project skill `main-dockerfile-fix`.

## Run

```bash
bash ~/.claude/skills/fix-dockerfile/scripts/check.sh      # from the repository root
```

Or ask Claude: `/fix-dockerfile`. Exit code `0` is clean, `1` means findings, `2` means a bad root or no Dockerfile. `INFO` lines are hints and never fail the check.

## What is checked

| ID | Rule |
|---|---|
| F1 | PHP version satisfies `composer.json` and equals the dev default in `docker-compose.yaml` |
| F2 | PHP extensions from composer and the dev image are installed |
| F3 | Node image equals the one in `.docker/nodejs/Dockerfile` |
| F4 | Every `COPY`/`ADD` source exists |
| F5 | Compose `target:` values are stages of the Dockerfile |
| F6 | `.dockerignore` excludes `.git`, `vendor`, `node_modules`, `var`, env and key files |
| F7 | Runtime stage runs as non-root with `APP_ENV=prod`; `composer install --no-dev` |
| F8 | The frontend stage copies every input webpack needs |

Stage names are detected, not hard-coded, so the skill also works for projects with other stage names. The full rule table and the fix procedure are in the skill's `SKILL.md`.

## Notes

- The script only reads text. It cannot tell whether an extension is really used by the code.
- A full `docker build --target app -t vacancy-grabber-app .` takes minutes; run it yourself or ask Claude to.
- The skill is outside the repository, so a new machine needs it copied to `~/.claude/skills/fix-dockerfile/`.
