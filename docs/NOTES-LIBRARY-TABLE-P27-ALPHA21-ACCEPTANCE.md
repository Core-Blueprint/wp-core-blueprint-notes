# Notes Library Table View P2.7 — Alpha21 acceptance

**Status:** source candidate, not Golden/production-ready.  
**Base:** main after Alpha18 PR #1, commit `2530ee11b2fcb84a94146ecb71901f309474417f`  
**Branch:** `feature/notes-library-table-view-p27-v1`  
**Artifact:** `dist/core-blueprint-notes-0.1.0-alpha21.zip`

## Local sourcegate and release

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-library-table-view-p27-v1 || git switch --track origin/feature/notes-library-table-view-p27-v1
git pull --ff-only
git rev-parse HEAD
php -l src/Library/Page.php
php -l src/Library/Display.php
php tests/library-overview-smoke.php
php tests/library-display-smoke.php
node tests/library-table-contract.cjs
node tests/library-ui-contract.cjs
node tests/library-bulk-contract.cjs
node tests/library-overview-contract.cjs
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha21.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha21.zip
git status --short
```

## WordPress staging acceptance (disposable user accounts)

1. Start in **List View**, the default for every user without a stored preference. Use the **List / Table** icon buttons next to the note count to change presentation. Table preference survives refreshing, reopening Library, search, pagination and a new session. User B must retain their own independent choice.
2. Table headings: **Title, Notebook, Category, Tags, Modified, Actions**. All visible rows are exclusively the current user’s authorized notes, including an administrator who must not automatically see another user's private notes. Verify no extra list query is introduced.
3. Populate examples: Unfiled, category missing, no tags, three tags, five tags, long labels, special characters and a blank title. Table must show escaped text, semantic empty cells, `+2` extra tags, visible status and all four familiar Favorite/Pin/Edit/Trash icons, with accessible names.
4. Click **Title** repeatedly: first click A–Z, second Z–A, third A–Z. Click **Modified** repeatedly: first newest-to-oldest, second oldest-to-newest, third newest-to-oldest. Verify the active header shows the correct arrow and `aria-sort`; clicks reset pagination to page 1 without opening **More filters** automatically. If Category/Tag is already filtered, that filter panel may remain expanded. Verify the current search, notebook, Category/Tag and favorite/pinned view remain intact. Explicit Modified sorting must sort chronologically, rather than keeping pinned notes artificially first. The default view still prioritizes pins. Notebook, Category and Tags are informational columns, not clickable sort claims.
5. Select multiple notes in Table View, select-all, clear and perform notebook/category edits and Base-confirmed Bulk Trash. The same maximum 20 visible notes and per-owner AJAX contracts apply in either view. Cancel must not trash; failed requests show accurate partial results.
6. Favorites/Pinned and row-level Trash still work with the existing AJAX/Base-danger flow. Verify state after a refresh, combined notebook/category/tag filters and independent user A/B catalogs; public/shared snapshots never include personal labels.
7. At narrow/mobile widths and 200% zoom, only the **table area** scrolls horizontally and maintains all columns/actions; the complete WordPress admin page must not become horizontally scrollable. The List icon button remains easily accessible as the simpler compact option.
8. **Alpha21 UI polish:** verify the Title/Modified text remains underlined, but the ASC/DESC arrow itself is never underlined. List/Table uses equal-size icon-only Base buttons (list and table glyphs); only the selected mode is Base primary. Check visible active state, localized title tooltips and `aria-label`s, decorative glyphs `aria-hidden`, keyboard focus and `aria-current=page`. Switching views must retain user preference and query filters.
9. Dark/Light Mode, keyboard navigation and screenreader semantics: real table with thead/tbody, th scope and aria-sort on supported sort columns, region label for scroll, clear active List/Table indication and focus outlines. Empty state remains contextual in either mode.
10. Verify authorization: an unauthenticated or unauthorized account cannot save a preference via `admin-post.php?action=cb_notes_library_display`; nonce is required, only list/table modes are accepted, URL redirect is rebuilt from personal sanitized filters. No open redirect or change to note contents.
11. Verify the Notes sidebar no longer shows duplicate native **All Notes** and that the native URL and Gutenberg editor remain functional for compatible deep links.

## Explicit boundaries

- Single owner-scoped WP_Query; **no** new post type, taxonomy, global list endpoint, REST write action or table pagination engine.
- Table/View preference uses owner-bound `user_meta` and a nonce-protected, capability-gated `admin_post` handler. This does not modify note content, sharing or organization catalog.
- Only Title and Modified are sortable: no misleading Notebook/Category/Tags sorting.
- List View stays the default, Table View is optional. The Table View scrolls inside the Library on small screens.
- An alpha sourcegate PASS is not equivalent to full WordPress runtime security acceptance.

**Governance:** no CI/Actions, merge, production deployment, tagging, release or WordPress.org publication without explicit separate GO.
