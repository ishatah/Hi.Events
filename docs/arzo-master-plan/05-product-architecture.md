# Target Product Architecture

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 02, 06  
**Blocks:** all subsystem docs


---

## Purpose

The target system shape: surfaces, services, data stores, and the boundaries between them.

## Current state

`CONFIRMED`: Laravel 13 monolith (268 actions / 201 handlers / 193 domain services / 59 repositories) + React 19 SSR frontend (88 routes) + Postgres + Redis. Queue worker and scheduler under supervisord in production. No realtime transport. Deployed as Vapor (backend) + DigitalOcean (frontend), or a single all-in-one container for self-host.

## Key decisions

- Keep the monolith. The layered architecture is sound and the audit found no scaling wall that a service split would solve. Extracting services now would multiply the tenancy risk in F11.
- Add capability modules inside the monolith rather than new services: accreditation, access, programme, exhibitors, devices.
- The one genuine new runtime component is the realtime transport (Reverb) — see `71`.
- Offline devices are clients of the API, not services. They hold a local replica and reconcile.

## Open questions

- Does the command center need a read-optimized store (materialized views, or a separate OLAP store) or will Postgres serve? Depends on load modelling in `74`.
- Should the badge render pipeline be in-process or a separate worker? Rendering is CPU-heavy and event-day bursty.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `02` · `06` · `all subsystem docs`
