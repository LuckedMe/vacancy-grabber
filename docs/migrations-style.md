# Doctrine migration style

Migrations in `migrations/` are kept readable: every `addSql()` is split over several lines. Formatting is done by the **global** Claude Code skill `/fix-migrations` (`~/.claude/skills/fix-migrations/`), shared by all projects. It replaced the project skill of the same name.

## Workflow

```bash
docker compose exec -T php-fpm bin/console doctrine:migrations:diff     # generate
python3 ~/.claude/skills/fix-migrations/scripts/format_migrations.py     # format all of migrations/
python3 ~/.claude/skills/fix-migrations/scripts/format_migrations.py migrations/2026/Version<id>.php   # or one file
python3 ~/.claude/skills/fix-migrations/scripts/format_migrations.py --check   # exit 1 if something would change
make migrate                                                             # apply
```

Or ask Claude: `/fix-migrations`. Run `php -l` on every changed file afterwards (inside the `php-fpm` container).

## Style

- `/** @lang MySQL */` marker before each SQL string; `$this->addSql(`, the marker and the closing `);` at the same indent.
- Backticked identifiers, upper-case keywords.
- `CREATE TABLE`: aligned columns, empty line, aligned keys, empty line, table options. `ALTER TABLE`: one clause per line, foreign keys split into `ADD CONSTRAINT` / `FOREIGN KEY` / `REFERENCES` / `ON DELETE`.
- One empty line between `addSql()` calls; Doctrine's "auto-generated" comments removed.

## Notes

- Only whitespace, backticks and the generated comments change; the script checks that every rewritten statement keeps the same tokens, so the SQL stays the same.
- Hand-written SQL (`INSERT`, `UPDATE`, ...) is reported as "left unchanged" (exit code 2) and formatted by hand following the skill's examples.
- Do not run php-cs-fixer on migrations; it breaks the layout.
- The skill is outside the repository, so a new machine needs it copied to `~/.claude/skills/fix-migrations/`.
