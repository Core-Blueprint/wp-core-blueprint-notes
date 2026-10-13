# Core Blueprint Notes — alpha3 handover

**Version:** `0.1.0-alpha3` | **Branch:** `feature/notes-visibility-sharing-alpha3-v1`
**Status:** development test candidate. No Golden claim or approved runtime acceptance.

## Architecture and ownership

- Base is a mandatory external dependency. Its Modal Foundation (WP-native presentation), Settings Hub and Governance/Audit remain owned by Base. **No Base changes are included.**
- Secure Share is an optional external dependency. Notes registers an adapter only when its public provider interface/facade is available. **No Secure Share changes are included.**
- Notes owns personal content, its sharing mode and frozen snapshot. Gutenberg `publish` never implies public exposure.
- Public and Secure Share are mutually exclusive, with a separate explicit public warning; reverting to private invalidates the local authorization before revoking provider grants.
- Supported static Gutenberg blocks only. Attachments/media/embeds excluded until a separate protected-files contract is designed.

## Changes relative to alpha2

1. Sharing API, public snapshot endpoint, optional Secure Share resource provider and Gutenberg Sharing panel.
2. Base danger confirmation with typed phrase and required checkbox, without a custom modal.
3. Earlier REST exact-note authorization, forced private internal status, legacy status migration and private-by-default sharing state.
4. Stale content/share state checks; immediate revocation even from a stale edit session.
5. Trash/delete disarms public and secure delivery, preventing restore from reviving old URLs.
6. Notebook form field changed from repeating “Notebook” to “Name”.
7. Additional PHP/Node sharing smoke and UI contract tests; canonical check/build scripts target alpha3.

## Verification and open gates

Verify `bash tools/check` and `bash tools/build-release` from the **canonical GitHub branch**, then record ZIP checksum and exact HEAD. WordPress runtime privacy, Gutenberg modal interaction, public route/HTTP headers, Secure Share integration, real two-user REST/revisions tests, cross-extension fail-soft behavior and production caching are **open** until proven on staging.

If Base or Secure Share needs a contract change, stop that part and coordinate with its owning project before altering either repository.

[Complete acceptance batch](ALPHA3-ACCEPTANCE.md) · [Original architecture RFC](ARCHITECTURE-V1-RFC.md) · [Integration RFC](INTEGRATION-CONTRACTS-V1-RFC.md)

No merge, CI, deployment, release or publishing without explicit GO.
