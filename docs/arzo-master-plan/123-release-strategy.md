# Release Strategy

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process + New (repository and pipeline) · **Priority:** **P0** for the repository; P1 otherwise · **Phase:** now
**Depends on:** `83-devops.md`, `85-deployment.md`, `129-quality-gates.md`
**Blocks:** every phase — nothing is released without it

---

## Current state — ARZO has no release process, because it has no repository of its own

Earlier documents described the release pipeline as "trunk-ish with `develop` and `main`, release-tagged
images, Vapor plus DigitalOcean". That is **upstream Hi.Events' pipeline**. `CONFIRMED`:

| Fact | Evidence |
|---|---|
| The only git remote is the public upstream repository | `git remote -v` → `origin https://github.com/HiEventsDev/Hi.Events.git` |
| ARZO cannot push to it | `gh api repos/HiEventsDev/Hi.Events` → `push: false` |
| **All ARZO work is local-only** | `git status -sb` → `develop...origin/develop [ahead 14]` — `455585b3` through `e7228c1d`, plus a large uncommitted working tree |
| **ARZO's code has never run in CI** | GitHub Actions runs only on the upstream repository; recent runs listed by `gh run list` are all upstream's |
| `deploy.yml` deploys **Hi.Events' SaaS** | Push to `main` → Vapor production, `develop` → staging; AWS `eu-west-1`; env file from `s3://hi.events-env-secrets` (`.github/workflows/deploy.yml`) |
| Upstream deploys are not gated on tests | `deploy.yml` has no `needs:` on `tests.yml`, `frontend-tests.yml` or `e2e.yml` |
| Release images publish on a GitHub release | `post-release-push-images.yml`, `release: published` |
| Version | `VERSION` = `2.0.0-alpha.1`; upstream tags up to `v2.0.0-rc.1` |

Three consequences, in order of severity:

1. **Single point of total loss.** Fourteen commits — the master plan, Phase 0 fixes, the Phase 1 and
   2 schema, the access engine — exist on one workstation. A disk failure loses all of it.
2. **The Phase 0 "CI gates" are not gates.** The architecture tests (ARZ-004/005), the cross-tenant
   suite (ARZ-006) and the frontend runner (ARZ-007) pass when someone runs them. Nothing runs them on
   every change. `113`'s Phase 0 exit — "CI gates architecture and tenancy invariants" — is not yet
   true.
3. **ARZO has no production environment in evidence.** Which hosting ARZO will use is `UNVERIFIED`
   and belongs to `84`.

## P0 — before anything else in this document

| # | Action | Why now |
|---|---|---|
| RS1 | **Create an ARZO-owned private repository**; push `develop` and every branch; make it `origin`; keep upstream as a second remote named `upstream` | Ends the single-copy risk; ARZO's plan and proprietary work must not live only on a laptop, nor near a public remote |
| RS2 | Enable Actions on it with `tests.yml`, `frontend-tests.yml`, `e2e.yml`; **disable `deploy.yml` and `post-release-push-images.yml`** until ARZO has its own targets | Upstream's deploy workflow points at Hi.Events' secrets and buckets; it must never run under ARZO's name |
| RS3 | Branch protection on `develop` and `main`: required status checks, no direct pushes | Turns the Phase 0 tests into gates (`129`) |
| RS4 | Decide the licence position before RS1's repository is shared with anyone outside ARZO (R1, `66`) | The repository is the thing the licence governs |

RS1 is an hour's work and the highest-value item in the entire plan per unit of effort.

## Branching — keep the upstream shape

| Branch | Purpose | Deploys to |
|---|---|---|
| `develop` | Integration; every PR lands here | ARZO staging — once it exists (`84`) |
| `main` | Released code only | ARZO production |
| `feature/*` | Short-lived work | Preview, optional |
| `upstream-sync/*` | Merges from upstream Hi.Events | Staging first, always |

Keeping upstream's branch names makes pulling upstream fixes cheap. **Upstream merges are releases in
their own right**: they arrive with their own migrations and behaviour changes, and go through staging
like any ARZO change. How often to sync, and whether ARZO's divergence (the rebrand, the new domains)
makes syncing progressively harder, is a standing cost to track.

## Versioning

ARZO's releases need their own version, distinct from upstream's `VERSION`, or support conversations
become ambiguous: `arzo-YYYY.MM.N` (calendar versioning) tagged on `main`, with the upstream base
recorded in release notes. The OpenAPI contract test asserts `info.version` matches `VERSION`
(`48`), so the choice must update that test rather than break it.

## Feature flags

Anything touching check-in, access, badges or payments ships **behind a flag, off**, and is turned on
per account or per event. These features cannot be rolled back mid-event, and a flag can be switched
off in seconds where a deploy cannot.

**Decision: build a minimal flag table, do not adopt a flag service yet.**

```
feature_flags   id, key, description, default_enabled bool, timestamps
feature_flag_overrides
                id, feature_flag_id, scope_type,   -- ACCOUNT | EVENT
                scope_id, enabled bool, set_by, set_at
```

A hosted flag service is justified when there are dozens of flags or percentage rollouts; ARZO has
neither. The table also serves SaaS tier gating (`100`). Flags are removed once a feature is stable;
a flag older than two releases is debt (`121`).

## Event-aware freeze

**No production deploy while an ARZO-operated event is in `BUILD_UP` or `LIVE_OPS`** (`56`). This is
the requirement specific to this domain: a deploy that restarts workers mid-arrival, or a migration
that locks `attendees` at 08:55, is an operational incident.

| Rule | Mechanism |
|---|---|
| Freeze windows derive from `event_operations` timelines | The deploy job queries ARZO's own API for active operated events and refuses to run |
| **Emergency deploy during a freeze** | Allowed only with a named approver (`106`: the event director plus the engineering lead), recorded with reason, in the audit trail (`67`) |
| Device apps | Never force-updated mid-event; new versions install at the next event's staging (`103`) |
| Data migrations | Never inside a freeze (`122`) |

Until `event_operations` exists, the freeze is a shared calendar and a rule. That is fine — the rule
matters more than the mechanism.

## Release procedure

```
1. PR to develop → required checks green (129)
2. develop auto-deploys to staging; E2E smoke against staging
3. Release PR develop → main with release notes: changes, migrations, data migrations (122), flags
4. Freeze check (above)
5. Deploy; run post-deploy checks — health (76), smoke, error rate vs baseline (78)
6. Tag arzo-YYYY.MM.N; publish notes
7. Watch for 30 minutes; rollback plan open (85)
```

Release notes list **every migration** and state whether it is reversible — `122`'s rule that an
empty `down()` is a one-way door must be visible at release time, not discovered at rollback time.

## Device and app releases

Native scanner and kiosk apps (`94`, `96`) and the print host (`37`) release on their own channels:
internal build → staging devices → production, pinned per event. The server supports **N and N-1**
app versions; the fleet view flags anything older (`40`).

## Open questions

- **Where does ARZO host?** `84` owns it; until answered, "production" in this document has no target.
- **How often to merge upstream?** Monthly keeps drift small; each merge needs the rebrand re-applied where upstream touches branded files.
- **Who approves emergency deploys during an event?** Named in `106`, not assumed.
- **Is the self-host all-in-one image part of ARZO's product?** If not, `post-release-push-images.yml` can be retired rather than maintained.

## Related

`83-devops.md` · `84-infrastructure.md` · `85-deployment.md` · `129-quality-gates.md` ·
`122-migration-plan.md` · `56-event-operations.md` · `106-event-operating-model.md` ·
`66-compliance.md` · `100-saas-tenancy.md` · `113-roadmap.md` · `120-risk-register.md`
