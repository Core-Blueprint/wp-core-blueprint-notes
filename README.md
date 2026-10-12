# Core Blueprint Notes

**Version:** `0.1.0-alpha1` | **Status:** development test candidate; not Golden or production-ready.

Private, WordPress-native Gutenberg notes for the Core Blueprint WP Suite.

## What is implemented

- Native Gutenberg Notes editing with WordPress autosaves and revisions.
- Personal ownership: editors can manage only their own notes; administrators have an explicit `cb_notes_manage_all` override. Subscribers have no Notes access by default.
- Flat personal **Notebook** text labels stored per note in post meta; no site-global term taxonomy and no hierarchy/team sharing yet.
- Non-public `cb_note` post type, author-scoped lists and REST requests, object-level read/edit/delete checks.
- Required Core Blueprint Base API 1.2+, ExtensionRegistry and Governance/Audit integration; plugin activation requires active Base.
- Optional read-only Abilities API (`core-blueprint-notes/list-notes`, `search-notes`, `read-note`). **MCP access is OFF by default**, requires the separate authenticated WordPress MCP Adapter, and may be enabled under **Notes → AI Access**. Only accounts with `cb_notes_use_ai` may invoke read abilities, administrators by default. No external AI requests or AI write operations.

## Security limits

**Alpha staging only. Never use with real confidential notes or attachments until the multi-user WordPress acceptance checks pass.** Gutenberg may embed public Media Library URLs or external embeds: these are not private file storage. This alpha has no private attachment handling, team-sharing ACL, cross-extension relationship UI, importer/exporter or AI write proposals.

The original planning documents describe a **proposed** `cb_notebook` taxonomy. This alpha intentionally uses private per-note metadata instead to avoid leaking global taxonomy names. We must explicitly design per-user hierarchical notebook ACLs before adding the taxonomy.

## Install, checks and packaging

Requirements: WordPress 7.0+, PHP 8.4+, Core Blueprint Base with compatible Core API 1.2.

From the repository root:

```bash
bash tools/check
bash tools/build-release
sha256sum dist/core-blueprint-notes-0.1.0-alpha1.zip
```

Build output: `dist/core-blueprint-notes-0.1.0-alpha1.zip`, with plugin folder `core-blueprint-notes/`.

The source gate validates PHP/JS syntax, shell syntax and focused mocked permission/Abilities tests. **These tests do not execute WordPress.** Real installation, Gutenberg and REST authorization remain mandatory.

See [tomorrow's manual test batch](docs/TEST-PLAN.md), [Architecture RFC](docs/ARCHITECTURE-V1-RFC.md) and [Integration/Abilities RFC](docs/INTEGRATION-CONTRACTS-V1-RFC.md).

**Governance:** source is on a featurebranch. Do not merge, publish, deploy or trigger CI/CD without explicit GO.
