# DDD structure check result

Date: 2026-11-15 · Branch: main · Commit: f92e4f4, uncommitted changes
Command: `bash .claude/skills/ddd-structure-fix/scripts/check.sh` · Exit code: 0

## Summary

| Rule | Description | Violations |
|---|---|---|
| D1 | Domain depends on Application/Infrastructure | 0 |
| D2 | Domain depends on a framework (Doctrine ORM/DBAL, Symfony, Gesdinet) | 0 |
| D3 | ORM attributes in Domain | 0 |
| D4 | Domain depends on legacy App\Contract | 0 |
| I1 | Doctrine adapter depends on Application | 0 |
| P1 | Inner layer depends on Presentation | 0 |
| A1 | Application uses the ORM directly | 0 |
| A2 | External adapter (DomCrawler/HttpClient) in Application | 0 |
| N1 | Repository naming/pairing | 0 |
| N2 | Entity without XML mapping | 0 |
| L1 | File in legacy location | 0 |
| **Total** | | **0** |

## Violations and recommended fixes

No violations.

## False positives

None.

## Recommendations

1. The structure is clean; nothing to fix.
2. The script greps imports only. It cannot see fully-qualified class names used inline without `use`, runtime service lookups, or logic that belongs in another layer. Review these by hand.
3. The uncommitted changes (CMS admin client config, `DefaultController`, homepage template) are in Presentation/config and were scanned with the rest of `src/`.
