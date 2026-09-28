# Webhooks

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 47, 48


---

## Purpose

Existing outbound webhook system.

## Current state

`CONFIRMED` and well built: `webhooks` / `webhook_logs`, 19 `DomainEventType` cases, per-event subscriptions, spatie webhook-server with exponential backoff, signed payloads. `SecureCallWebhookJob` implements real SSRF and DNS-rebinding defence (manual redirect following with per-hop revalidation, `CURLOPT_RESOLVE` pinning) — the best-engineered infrastructure in the codebase, and `UNVERIFIED`/untested.

## Key decisions

- Keep and test the SSRF defences — they are load-bearing security with no test coverage.
- **Decouple payloads from API `JsonResource` classes (F14).** Today a REST refactor is silently a webhook breaking change for integrators.
- Note `dispatchSync()` means the HTTP call runs inside the queued job, so one slow endpoint delays the rest for that event.

## Open questions

- Versioning scheme for payloads?
- Should `checkin.created` fire from both check-in paths? Today only the public path emits it (F10).

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `47` · `48`
