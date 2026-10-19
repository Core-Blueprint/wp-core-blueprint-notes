# Notes Library P2.8 — Cards and responsive sidebar acceptance

**Date:** 2026-10-19  
**Candidate branch:** `feature/notes-rc1-portability-data-exchange-v1`  
**Status:** SOURCE CANDIDATE; not accepted Golden, not a public release.  
**Scope:** presentation and personal display preferences only, no new queries, note metadata, REST endpoints or sharing changes.

## Focused development checks

On the exact Git HEAD:

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-rc1-portability-data-exchange-v1
git pull --ff-only
git rev-parse HEAD
git diff --check origin/main...HEAD
php tests/library-display-smoke.php
node tests/library-cards-sidebar-contract.cjs
node tests/library-sidebar-contract.cjs
node tests/library-table-contract.cjs
bash tools/i18n/check
bash tools/build-alpha26-fixture
sha256sum dist/core-blueprint-notes-0.1.0-alpha26.zip
git status --short
```

The internal ZIP is a **NON-CUSTOMER** development fixture and does not include customer-ready localized runtime bundles. `build-alpha26-fixture` runs the full source gate, so there is no need to run `tools/check` twice.

## Sandbox acceptance with disposable Notes

1. **Three modes:** switch List → Cards → Table → Cards. Reload, search, paginate and reopen Library: the per-user choice persists. A second Editor account retains its own preference. Invalid layout requests and nonce failures must not save preferences.
2. **Cards:** verify a responsive 3–4 column grid when space allows, two columns on narrower screens and one on mobile; cards must show the same title, status, modified time, notebook, optional category/tags and +N overflow as List. No note body preview. A blank title and long label remain safe.
3. **Actions:** Favorite, Pin, Edit and Base-confirmed Trash on each card; selection checkbox and select-all (max 20 on the visible page), notebook/category bulk updates, cancel/confirm Trash and partial-failure reporting. These must match List/Table, without changing author scope or creating duplicate queries.
4. **Sidebar:** wide initial view shows the existing notebook panel; Hide navigation removes the sidebar column and lets the results fill available width. Show navigation restores it; refresh and login in a new session preserve the individual user's choice.
5. **Compact navigation:** when hidden, the selector shows the current All Notes / Favorites / Pinned / Unfiled / named Notebook context. Opening it provides the same owner-only notebook links and Organize notes. Search/filter/sort/page state is preserved across sidebar toggles; keyboard/summary focus works without JavaScript.
6. **Responsive default:** on a new account, the sidebar is visible at desktop width and hidden at widths up to 1000px. Explicitly shown/hidden preferences override this responsive default. Confirm no horizontal scroll of the overall admin page at 680px or at 200% zoom.
7. **Design and compatibility:** Base Light/Dark tokens, contrast, focus and available fullscreen space. Existing table horizontal scrolling must remain confined to the table area. The separate native Gutenberg SelectControl darkmode issue remains Base-owned.
8. **Privacy:** user A and B must never see each other's private notebooks, counts, labels or notes in Cards or compact navigation. Anonymous users must not gain Library access. Card actions do not weaken object authorization.

**Stop** for cross-user disclosure, unexpected public Notes, missing Base danger confirmation, data loss, invalid preference writes, broken keyboard navigation or a missing native Library fallback.

Record installed ZIP SHA-256, Base version/commit, viewport, theme, any screenshots and the short PASS/FAIL observations. Source-only tests and the isolated older P1 runtime evidence do not establish installed browser acceptance. No merges, CI/Actions, Golden release, public artifacts or deployment without separate approval.
