# DDD structure check result

Date: 2026-11-23 · Branch: main · Commit: 771a23a
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
| A3 | Application depends on Infrastructure | 0 |
| N1 | Repository naming/pairing | 0 |
| N2 | Entity without XML mapping | 0 |
| L1 | File in legacy location | 0 |
| **Total** | | **0** |

## Violations and recommended fixes

No violations.

## False positives

None.

## Recommendations

1. The structure is clean for every rule the script checks; nothing to fix.
2. Resolved since the last run: no file under `src/Application` imports `App\Infrastructure` any more (checked with `grep -rn 'App\\Infrastructure' src/Application`, no hits).
   - `SendTelegramNotificationHandler` depends on the Application port `App\Application\Telegram\TelegramSender`, implemented by `Infrastructure/Telegram/TelegramNotifier`.
   - `GrabSourceHandler` and `GetDashboardCollectionHandler` depend on the Application port `App\Application\Shared\Bus\MessageBus`, implemented by `Infrastructure/Messenger/MessageBusHandler`.
3. Rule A3 (Application must not depend on Infrastructure) now enforces this, so it can not come back unnoticed.
4. Presentation now uses the `MessageBus` port too (35 files switched). Nothing outside `Infrastructure/Messenger/MessageBusHandler.php` references the concrete class, so the port is the only entry point to the bus.
5. The script greps imports only. It cannot see fully-qualified class names used inline without `use`, runtime service lookups, or logic that belongs in another layer.
