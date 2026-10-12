# Notes 0.1.0-alpha1 — One-batch acceptance plan

**Environment:** disposable staging, backed up, WordPress 7.0+, PHP 8.4+, Core Blueprint Base with Core API 1.2. **No production use or sensitive attachments yet.**

## 1. Installation and dependency

1. Check out the feature branch and run `bash tools/check`, then `bash tools/build-release`.
2. Install `dist/core-blueprint-notes-0.1.0-alpha1.zip` in **Plugins → Add New → Upload Plugin**, with compatible Base active.
3. Verify **Core Blueprint Notes** appears with required Base dependency and shows a new **Notes** menu.
4. In a separate disposable instance, test missing/inactive/incompatible Base; expect activation refusal or inert plugin and clear required notice. Do not disable Base on shared test sites.

## 2. Native Gutenberg

5. As Administrator A, create Notes **Project Alpha** and **Meeting Notes** with headings, paragraphs, lists and a table.
6. Open the document settings sidebar **Notebook** panel. Set labels **Projects** and **Meetings**; save and reload. They must persist.
7. Test normal editing, autosave, WordPress revisions/restore, trash/untrash and note deletion.
8. Test normal public website search, sitemap, feeds and direct public URLs: Notes must not be published publicly.
9. Standard media uploads/embeds may be public; do **not** use confidential media in this alpha.

## 3. Mandatory two-user privacy

10. Create Editor B and Subscriber C. B should create and edit only B's notes. B must not see A's titles, body, Notebook labels, revisions or note counts in list/search.
11. Try B directly visiting A's note edit URL, preview URL and `GET /wp-json/wp/v2/cb-notes/{A_ID}?context=edit`; verify access denied. Also test the collection route with `author={A_USER_ID}`, `context=view` and `context=edit`.
12. As anonymous and Subscriber C, try the same REST requests, and revisions/autosave endpoints; nothing private may leak. C should not see the Notes admin menu.
13. Check Administrator A's explicit `cb_notes_manage_all` override against B's note. Confirm that this override is acceptable for your organization.
14. **STOP condition:** any user can see another user's content, Notebook label, restricted revisions or metadata without an authorized override.

## 4. Abilities / MCP (optional installed adapter)

15. Verify **Notes → AI Access** is OFF by default and the three Notes abilities are not externally discoverable through the authenticated MCP Adapter.
16. Enable the switch as Administrator A, reconnect the MCP client, and test `list-notes`, `search-notes`, `read-note`.
17. Verify no create/update/delete abilities exist. A client not assigned `cb_notes_use_ai` must be denied. If you deliberately assign that ability to B, B must only be able to read B's notes.
18. Disable the switch and retest: execution must be denied again.
19. Do not paste API keys/application passwords into the chat or logs.

## 5. Evidence requested

- PHP, WordPress and Base version; Notes activation and plugin-screen screenshot.
- Screenshots: Notes list as A and as B, Gutenberg note with Notebook sidebar, AI Access setting OFF/ON.
- REST HTTP status/response for A-own and B-to-A note access (redact content).
- If tested, MCP discovery list, denied read result and permission identity (no credentials).
- Sanitized `wp-content/debug.log` warning/error lines if any.
- Any visual or keyboard navigation issues in Gutenberg.

## Acceptance decision

Only after runtime privacy verification, Gutenberg round-trips, negative tests, and user acceptance can the alpha become a release candidate. This branch is not approved to merge, publish, deploy or run CI/CD.
