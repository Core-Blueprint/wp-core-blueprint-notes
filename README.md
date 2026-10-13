# Core Blueprint Notes

**Version:** `0.1.0-alpha3` | **Status:** private-staging test candidate; not Golden, merged or production-ready.

WordPress-native Gutenberg Notes with owner-scoped privacy, personal notebook labels and deliberately controlled sharing. **Core Blueprint Base API 1.2+ is mandatory**. Secure Share is an optional, independent provider.

## Implemented capabilities

- Native Gutenberg editor, WordPress revisions/autosaves, private-by-default `cb_note` storage and explicit owner/administrator permissions.
- Flat personal **Notebook** label, with sidebar field named **Name**.
- Base Settings Hub registration under **Business** with Overview / AI Access. Existing read-only Abilities API/MCP opt-in is preserved.
- **Sharing** Gutenberg panel with three mutually exclusive access states:
  - **Private**: default, no external access.
  - **Public Share**: frozen, allowlisted static-text HTML snapshot at a dedicated `/cb-notes/shared/<key>/` URL, noindex/no-store, behind a mandatory **Base Modal Foundation danger confirmation** with typed `PUBLIC` and acknowledgement.
  - **Secure Share**: optional Secure Share public `ResourceProvider` integration; frozen view-only snapshot, password/PIN when supplied, 1/7/30-day expiration, per-grant authorization and ability to revoke. No fallback if Secure Share is absent.
- Ordinary WordPress Publish action is coerced to internal `private` status and does **not** publish to a public URL.
- Public and Secure Share are different capabilities from Notes editing. The owner must separately authorize either mode.
- Content-version digest and expected sharing mode prevent stale sharing approvals. **Stopping sharing is allowed even if the editor has stale/unsaved changes.**
- Existing alpha1/alpha2 Notes with `publish` status are migrated to `private` in bounded batches on administrator visits.
- Trash/delete revokes the share; restore must never reactivate the prior public URL.
- Private Notes editing, public sharing and Secure Share operations are intentionally separate from one another.

## Boundaries / warnings

**Only use disposable staging and test data.** Neither multi-user WordPress runtime privacy nor the authenticated Secure Share provider flow has been accepted yet. Unsupported block types (media, embeds, dynamic/custom blocks), public media attachments, shortcode expansion and live collaboration are out of scope. Only statically supported text/layout blocks are eligible for sharing.

Shared copies are frozen. Editing the original does not change the shared version. After withdrawal, recipients might retain copies; disabling a link does not recall them. A shared link is a bearer secret where applicable; never put a secure invitation URL in logs or tickets.

Public Share URL delivery is **unlisted, not password protected**. Noindex is not a secrecy mechanism. Secure Share is the separate controlled-access mode.

## Build from the canonical GitHub branch

```bash
git fetch origin
git switch feature/notes-visibility-sharing-alpha3-v1 || git switch --track origin/feature/notes-visibility-sharing-alpha3-v1
git pull --ff-only
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha3.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha3.zip
```

Output: `dist/core-blueprint-notes-0.1.0-alpha3.zip` with root `core-blueprint-notes/`.

`tools/check` runs PHP/JavaScript syntax, the existing owner/Abilities and Settings Hub smoke tests, plus alpha3 sharing-state and modal-contract tests. These are **mock/unit-level** controls; they do not establish production safety. See [alpha3 acceptance](docs/ALPHA3-ACCEPTANCE.md) for the required WordPress, two-user privacy, cache and Secure Share runtime tests.

No merge, deployment, publication, release, or CI/CD action is authorized by building this test candidate.
