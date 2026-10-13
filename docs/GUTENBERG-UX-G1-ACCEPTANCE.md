# Core Blueprint Notes — Gutenberg UX Golden G1 acceptance

**Version:** 0.1.0-alpha9
**Branch:** feature/notes-gutenberg-ux-g1-v1
**Scope:** one progressive Gutenberg Organize panel; privacy messaging.
**Dependencies:** Core Blueprint Base API 1.2+, native Gutenberg. Secure Share remains optional.
**Status:** staging candidate only. Do not claim Golden before browser/runtime acceptance.

## Local test and build

    cd ~/Downloads/wp-core-blueprint-notes
    git fetch origin --prune
    git switch feature/notes-gutenberg-ux-g1-v1 || git switch --track origin/feature/notes-gutenberg-ux-g1-v1
    git pull --ff-only
    git rev-parse HEAD
    node --check assets/editor.js
    node tests/gutenberg-organization-interaction.cjs
    node tests/organization-ui-contract.cjs
    node tests/sharing-ui-contract.cjs
    bash tools/check
    bash tools/build-release
    unzip -t dist/core-blueprint-notes-0.1.0-alpha9.zip
    sha256sum dist/core-blueprint-notes-0.1.0-alpha9.zip
    git status --short

## WordPress acceptance

Use disposable staging and replace the installed plugin with the new alpha9 ZIP. Do not test with real sensitive notes.

1. Open an existing Note in Gutenberg. The right document sidebar shows **Organize** and **Sharing** as separate panels; the three old individual organization panels are gone. Gutenberg block editing, autosaves, revisions and native Save/Publish remain unchanged.
2. In Organize, Notebook is initially visible, while Category/Tags are behind "Show categories and tags". The toggle is keyboard operable and exposes aria-expanded; no more than one Organize plugin is registered.
3. Open a Note that already has a Category or Tags. The extra controls should automatically be visible, preserving every existing assignment. Hide and reopen them without losing assignments.
4. Select, clear and reselect a Notebook. Save, reopen, confirm the ID-backed choice persists and Library Notebook/Unfiled filters update.
5. Use New notebook to create a personal notebook inline, verify it is selected automatically, save and reopen. Cancel an inline creation and verify no empty catalog item appears. Repeat for Category and Tag.
6. Assign several tags, remove one and verify the change after reopening; at the maximum of twelve, no action should allow adding a thirteenth.
7. While editing an existing Note, verify changes aren't silently published via Sharing. Confirm that the separate Sharing panel still provides the explicit Public Share confirmation (typed PUBLIC and checkbox) and allows independent revocation. No new share link may be created by saving a Note.
8. Check narrow Gutenberg sidebars, keyboard focus/labels, browser zoom, screen readers, and WordPress Light/Dark Mode. Note that the editor uses Gutenberg components, not Base Admin Theme button styles.
9. Repeat with a second user. Labels and suggestions remain strictly personal. The existing cross-user REST and Public/Secure Share boundaries must not regress.
10. On first use, a failed organization REST request must show a meaningful error without overwriting saved classification meta; Share must remain available independently.

## Acceptance and follow-up

Collect one screenshot of the default collapsed Organize panel, one with Category/Tags revealed, and the local sourcegate/build output. No change to Base, Secure Share, the personal catalog data model or native Gutenberg is permitted in G1.

After G1 acceptance, plan **G2 Save & Share guided workflow polish** only if the real editor still needs improvement. Keep the Sharing safety contracts and explicit public confirmation intact. Do not merge or deploy without an explicit GO.
