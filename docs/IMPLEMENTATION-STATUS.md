# Core Blueprint Notes — alpha3 status

**Version:** `0.1.0-alpha3`
**Feature branch:** `feature/notes-visibility-sharing-alpha3-v1`
**Status:** implementation and mocked smoke tests authored, runtime and final build verification pending; not Golden and not production-ready.

## Current implementation

- Preserves the accepted alpha2 Settings Hub, Notes list and Gutenberg core.
- Server-side native private status, early REST item guards, and bounded migration of existing alpha1/2 published Notes.
- Explicit Public Share with separate noindex/no-store route, frozen and allowlisted Gutenberg HTML snapshot, author-only write authority, Base danger confirmation with typed `PUBLIC` plus acknowledgement.
- Optional Secure Share provider via its stable `PublicApi`, with grant-backed frozen read-only delivery, optional password and bounded expiry. Missing dependency must fail soft.
- Independent permission checks for sharing GET/POST, content hash + expected sharing mode + modified-time verification for new exposure. Emergency revocation never requires matching stale editor state.
- Trash/permanent delete permanently disarm sharing so restore cannot re-enable an old link.
- Duplicate Notebook field label fixed. Sharing Gutenberg sidebar added.
- Added `tests/sharing-smoke.php` and `tests/sharing-ui-contract.cjs` to `tools/check`. Release version and build guard updated to alpha3.
- Public sharing rewrite reconciles after upgrades on administrator requests.

## Verification gate

The **source is committed** on the alpha3 feature branch. GitHub file presence/source inspection is not the same as executing the suite. The sandbox cannot pull this private repository directly over the network. Run `bash tools/check` and `bash tools/build-release` from the fetched branch to establish canonical exact-source validation and generate a SHA-256.

**Open**: real WordPress update/activation, current-user Gutenberg behavior, Base modal interaction, status migration, REST cross-user and anonymous denial, public snapshot/header/caching tests, Secure Share issuance and revocation, restore negative tests, accessibility and dark-mode visual review.

See [alpha3 acceptance](ALPHA3-ACCEPTANCE.md). Do not install on production or assume live security guarantees from mocked smoke tests.

**No Base or Secure Share patch, merge, CI/CD run, publication or deployment was performed.**
