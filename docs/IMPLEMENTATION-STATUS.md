# Core Blueprint Notes — alpha2 implementation status

**Version:** 0.1.0-alpha2. **Status:** feature-branch test candidate; not merged, not Golden, not production-ready.

## Implemented

- Native Gutenberg Notes and personal Notebook labels (alpha1 preserved).
- Owner-scoped Notes/REST boundaries and opt-in read-only Abilities (alpha1 preserved).
- Required Core Blueprint Base public API 1.2, now including the SettingsRegistry dependency.
- Base Settings Hub provider: official Notes tile in **Business**, Overview and AI Access tabs.
- Overview: current-author Notes count, Gutenberg/AI state, Manage Notes shortcut.
- AI Access: existing `cb_notes_mcp_read_enabled` option and `cb_notes_settings` settings group; no new authorization or external transport.
- Legacy **Notes → AI Access** submenu redirects to the canonical Hub tab before WordPress renders the admin header.
- Dedicated mocked Settings Hub contract tests, in addition to the alpha1 permission/Abilities suite.

## Validation boundary

The new Settings Hub PHP implementation has passed sandbox PHP lint and a local equivalent of the 25-assertion mocked Settings Hub test. Existing PHP/JS and WordPress-specific integration checks must be rerun from **the canonical GitHub featurebranch** with `bash tools/check` and `bash tools/build-release`.

The sandbox cannot fetch the private GitHub branch directly; a local source mirror is not evidence of byte-for-byte package identity. The canonical ZIP and SHA-256 must therefore be built/recorded from the fetched featurebranch during acceptance.

Still open: WordPress activation/update, official first-party tile registration, tab appearance/light/dark/mobile behavior, form persistence, legacy shortcut redirect, multi-user REST/privacy smoke, optional MCP Adapter smoke.

**No merge, CI/CD, publish or deployment was performed.**
