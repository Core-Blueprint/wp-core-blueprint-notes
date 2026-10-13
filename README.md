# Core Blueprint Notes

**Version:** `0.1.0-alpha3` | **Status:** feature-branch test candidate; not Golden or production-ready.

Private Gutenberg notes for the Core Blueprint WordPress Suite, with optional and explicitly controlled sharing.

## Features implemented

- Native WordPress Gutenberg notes, revisions and autosaves; personal notebook labels (`Notebook → Name`) in the document sidebar.
- Non-public `cb_note` type, user ownership and object-level authorization. Saving or pressing Gutenberg's native Publish never exposes the original Note publicly. Previously `publish`-status alpha1/2 Notes are migrated to `private` in bounded admin batches.
- Base Settings Hub tile under **Business** with Overview and AI Access tabs; required Base public API 1.2.
- Optional read-only WordPress Abilities API and MCP exposure, **off by default**. Connected AI users may read only their own notes.
- **Sharing** in the Gutenberg sidebar, independent from saving:
  - **Private** is the default and revocation state.
  - **Public Share** creates a separate, hard-to-guess, anonymous public URL to a *frozen, allowlisted static HTML snapshot* only after a **Base danger modal** with typed `PUBLIC` and an acknowledgement checkbox.
  - **Secure Share** integrates optionally with `CB\SecureShare\PublicApi\ResourceProvider` to issue a frozen view-only invitation. Secure Share owns tokens, password/PIN, expiry and transport. No silent fallback if Secure Share is absent.
- Sharing requires authorship and Notes-specific capabilities. Snapshot publishing rejects stale content or sharing state. Emergency revocation always remains available.
- Trash and deletion revoke stored sharing authority, and restoring a Note does **not** reactivate prior share links.

## Security scope

**Do not use real confidential notes or files yet.** This alpha needs multi-user WordPress REST, caching/CDN, Gutenberg and optional Secure Share runtime acceptance. Do not put public Media Library files, embeds, dynamic blocks, confidential attachments, JavaScript or unsupported blocks into a shared Note. Sharing currently only supports an allowlist of static blocks such as paragraphs, headings, lists, quotes, preformatted code and tables; unsupported blocks are explicitly refused. Shared snapshots are isolated from future Note edits.

Public links are anonymous for anyone who knows the URL, with `noindex` and no-store as defense in depth. This is *not* access control. Secure Share links are controlled bearer-based access; neither mode proves a specific recipient actually read the content. No public Gutenberg post page or custom theme template is enabled by this alpha.

Notebooks remain **personal labels**, not hierarchical shared knowledge spaces. CRM, Work, Helpdesk and Docs adapters are still design-only and are not delivered by this alpha. No new Base or Secure Share code is required in this branch.

## Source checks and package

Requirements: WordPress 7.0+, PHP 8.4+, Core Blueprint Base with compatible public Core API 1.2. Secure Share is optional.

```bash
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha3.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha3.zip
```

Package root: `core-blueprint-notes/`, output: `dist/core-blueprint-notes-0.1.0-alpha3.zip`.
The source gate covers PHP lint, JS and shell syntax, mocked ownership/Abilities/Settings Hub/Sharing contracts, and static modal UX tests. **These are not live WordPress integration tests.**

Read the [complete alpha3 acceptance test batch](docs/ALPHA3-ACCEPTANCE.md), [implementation status](docs/IMPLEMENTATION-STATUS.md), [architecture RFC](docs/ARCHITECTURE-V1-RFC.md) and [integration RFC](docs/INTEGRATION-CONTRACTS-V1-RFC.md).

**Governance:** no merge, release, publication, deployment or CI/CD is authorized by work on this branch.
