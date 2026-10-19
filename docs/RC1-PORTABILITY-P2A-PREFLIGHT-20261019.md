# Notes P2-A: Portable record and full-graph preflight (development)

**Branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`  
**Status:** READ-ONLY source implementation; importer and UI are not yet enabled. No Golden or release approval.

## Canonical schema v1

The current P1 Base JSON envelope contains one catalog and zero or more notes. `ArchivePreflight::inspect()` checks all records **together**. It is an extension-owned domain validator, not a replacement for Base's transport parser or authentication. A caller must still use authenticated `Engine::preview_json/apply_json` after the necessary full-graph preflight guarantees have been established. An isolated Base `EntityInterface::plan_import()` per-record callback is **not** adequate to promise a complete-document acceptance by itself.

Exactly one first `catalog:personal` record with `notebook`, `category`, and `tag` arrays; each label has an opaque 32-lowercase-hex source-local ID, unique (case-insensitive) name within the kind, and at most 150 labels per kind. Each subsequent `note:<positive WordPress source ID>` record contains only the twelve P1 exported fields. The source ID is a reference inside an archive, **not** a stable target identity. Notes reference source catalog IDs, and duplicate/unresolved tags are rejected. Private records never carry share keys, providers, grant information, author IDs or remote tokens.

Schema/status, GMT timestamps, booleans, string types, canonical Unicode serialization, content and title null bytes, duplicates, and bounded 10 MiB/5,000 records are validated. No unknown fields are accepted. No writes, schema migration or external calls occur in `inspect()`. Export data fidelity is not yet the same as import fidelity; the importer must explicitly decide how source `publish`, `pending` or `future` are made private before writes without silent loss.

## Batch gate still open

**Not an import approval.** Existing Base `Engine::prepare_import()` calls Notes once **per record**, not once per complete archive; its sequential `apply_import()` is fail-stop, not transactional. Before any `can_import` change, P2-B must prove end-to-end binding of full-graph digest, authenticated destination owner, Base plan fingerprint, safe stable identity/collision policy and durable crash recovery. If not feasible using Base v1's public API, request a generic opt-in Base batch preflight/transaction boundary and hold import activation.

The first focused test `php tests/portability-archive-preflight-smoke.php` tests valid/invalid catalogs, orphan relations, duplicate references, injecting sharing metadata, invalid status, malformed timestamps and overflow. No WordPress integration or user-facing import is claimed by this stage.
