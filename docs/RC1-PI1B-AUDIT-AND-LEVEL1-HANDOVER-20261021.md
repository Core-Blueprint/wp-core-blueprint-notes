# Notes RC1 — PI-1B Audit, Source Patch and Level 1 Handover

**Date:** 21 October 2026
**Branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`
**Audit entry source:** `3e808e59adf7d39d03049d46085d3c60e36af4c1`
**Code/test candidate at audit end:** `040eb682a45616aae29fa6f73245608cd16cfb70`; following documentation-only commit does not modify this tested candidate.
**Base comparison:** `Core-Blueprint/wp-core-blueprint@dcbf5da9b7729efde77cf508a3b90f2929b8d0b1`.
**Evidence:** Static source and GitHub compare review only. **Local source gate NOT RUN; WP 7.0/7.1.3 NOT RUN; PI-1B NOT ACCEPTED**.

## Executive status

The earlier P4 isolated two-site WordPress 7.0/7.1.3 round-trip PASS belongs only to Notes source `1954e3152d69586bc83004dd886628b3cf3d99de`, before PI-1B import activation changes. It does not certify this new source.

The user authorized PI-1B Notes-only source work, not Base changes, protected database writes, CI, `main` merge or release. Current scope expanded the Notes provider's `SUPPORT_IMPORT` descriptor but retains `NotesEntity::can_import` request-ticket enforcement. **Do not install this candidate on staging/production or ship it before end-to-end security review.**

## Source-audit findings and corrective work

| Finding | Source evidence and action | Status |
| --- | --- | --- |
| Base plan and authorization | Confirmed actual `Engine::prepare_import` requires advertised import and `EntityInterface::can_import`, and `Engine::apply_decoded` checks the canonical fingerprint before record writes. Notes calls Base Engine within full-archive `ImportSession`. | SOURCE design aligned; WP/runtime UNTESTED |
| Import record-count ambiguity | Base `applied_count` includes the catalog, so user-visible Notes counts must be derived from actual `items` belonging to Notes. | Patched prior to audit; guarded further here |
| Unexpected Base result after writes | After sequential apply, missing/truncated/mismatched `items` or aggregate count must never return false `complete`. | Patch: validate item indices, refs, operations and counts against original Notes session plan; ambiguous result => `recovery` and no asserted success count |
| Duplicate/replay | Base catalog may be `SKIP` when imported owner catalog already matches; static code incorrectly expected catalog `CREATE`. | Patch: condition expected catalog operation on existing vs mapped catalog, add fresh replay fingerprint mock and skip-not-duplicate assertion |
| Validation boundary | New confirm route has owner/nonce/acknowledgement + second actual JSON upload, exact file SHA and Base expected fingerprint; in-request ticket never exposed. | SOURCE design; no browser/fault runtime |
| Durable mapping identity | `MappingSeed` decouples ID remapping from `wp_salt('auth')`, initialized on activation/admin upgrade; fails closed for corrupt seed and detected legacy receipts. | SOURCE design and mocked tests; MariaDB, update path, concurrency/salt rotation still UNTESTED |
| Recovery | Pending journal retains intent, no automatic retry. UI distinguishes recovery-required from invalid upload. | SOURCE design; real failure recovery UI/runtime still HOLD |
| i18n | New import/recovery text is not yet reconciled with the pinned seven 319-entry POT/PO source files. `tools/check` source contracts do **not** imply locale editorial or `tools/i18n/check` acceptance. | PI-1D CONTENT HOLD |

## Remaining gates (do not claim pass until observed)

1. **Level 1 local operator run on this exact HEAD:** focused PHP 8.4 syntax, Node syntax/guard and mocked seed/import-flow tests; full `bash tools/check` with exitcode and failing log tail. Verify clean Git worktree. On RED, stop for one scoped repair, no blind test relaxation.
2. **PI-1C separate schema GO:** real Base `preview_json`→`apply_json`, owner B and anonymous refusal, malformed/change fingerprint, P4 regression at new SHA, salt rotation, catalog CAS, journal/meta/date interrupted writes, new-only MariaDB DB cleanup on both WP versions.
3. **PI-1D field:** fresh browser/HTTPS/origin+QUIC/LiteSpeed CDN noncache test, Light/Dark, mobile/zoom/keyboard and six reviewed translations. No installed customer/staging deployment without separate permission.
4. **P5 final RC1 Golden:** R01–R13, repeatable artifact and checksum, one exact-head build and independently approved release/merge.

**Next action:** run the source-only operator batch. **No current Level 1 PASS or Golden claim.**
