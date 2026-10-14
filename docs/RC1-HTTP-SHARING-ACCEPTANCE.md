# Notes RC1 | HTTP, CDN and sharing security acceptance

**Status: PLAN ONLY, NOT RUN (2026-10-14).** The separate SQL restore runner passed, but it does not make public HTTP requests through a web server or CDN. This gate must use a controlled test WordPress installation and **only synthetic notes**. Use the existing accepted Alpha24 ZIP with its recorded SHA-256, not a newly built artifact with different documentation.

## Test environment and STOP rules

- Record the WordPress and PHP versions, Notes ZIP hash, Base API version, web server, LiteSpeed/QUIC.cloud configuration if present, HTTP origin, CDN hostname and cache rules. Test direct-to-origin and via CDN separately when both are available.
- Use two synthetic editor accounts A/B, an authorized site administrator and a fresh anonymous client. For authenticated REST requests, use appropriately authorized test accounts and WordPress-supported authentication (e.g. nonce/cookies in a browser). Never copy live credentials or access tokens into issue comments, commands, logs or repository files.
- Use a dedicated disposable test site. Do **not** enable public access to real notes. Do not modify production DNS, cache policies or genuine provider grants to make a test pass.
- **STOP** on anonymous/private content leakage, foreign-owner REST access, stale cached public content after revocation, unexpected live grant, non-private note visibility or absent required security headers.

## Automated first stage: isolated localhost origin (ADDED; NOT RUN)

The new `tools/check-http` script provisions only `core_blueprint_notes_http_test` in the canonical local MariaDB 10.11.19 test container and uses a temporary WordPress 7.0 site served by PHP's built-in web server bound to `127.0.0.1:18777`. It refuses an already existing test database, unexpected image/port, an occupied HTTP listener, modified tracked source, or a ZIP with SHA-256 other than `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`. It runs only with synthetic Notes; its site configuration rejects non-loopback clients. Temporary synthetic Application Passwords authenticate editor A and editor B over local HTTP; the runner cleans its test server, database and private temporary files on exit.

Covered: A owner HTTP REST read, B own read and denied cross-owner read/write, anonymous exact read and list, B's owner-scoped list, explicitly confirmed anonymous frozen public snapshot, origin security headers, private edit without snapshot mutation, repeated 404 after revocation and invalid share paths. This **does not** test a CDN, real HTTPS endpoint, hostile external clients, Secure Share provider or the historical active-key backup rollback case.

**2026-10-14 first operator run: BLOCKED before HTTP verification.** The initial script had a malformed Bash single-quoted regular expression and repeated script fragments in the REST collection-count section. `bash -n tools/check-http` failed near its output footer, and `bash tools/check` also exited before its final `Notes source checks: PASS` line; the old Node source contract was misleading because it looked only for substrings. Although the synthetic seed printed `PASS (seed)`, there is **no valid HTTP runtime PASS**. The runner has since been repaired by replacing the corrupt tail, adding an immediate whole-script Bash preflight **before any database allocation**, and requiring actual `bash -n` execution and unique test phases from the Node contract. **Updated source checks and real HTTP test are PENDING operator execution.** The recorded source-only SQL restore PASS is unaffected.

**Execute only after source checks:**
```bash
cd ~/Downloads/wp-core-blueprint-notes
bash -n tools/check-http
php -l tests/http/wordpress-config.php
php -l tests/http/router.php
php -l tests/http/fixture.php
node tests/http-runner-contract.cjs
bash tools/check
sha256sum dist/core-blueprint-notes-0.1.0-alpha24.zip
bash tools/check-http
```

A valid final `PASS` must come from `Notes real loopback HTTP origin: PASS (... checks, WP 7.0)`, not the static Node source contract. The full R03/R07 external HTTP/CDN matrix remains separately open.

## R03: Native REST and privacy over actual HTTP

1. With editor A, create a synthetic private Gutenberg note and a private notebook/category/tag catalog.
2. Call exact `/wp-json/wp/v2/cb-notes/<A_NOTE_ID>?context=edit` and revision/autosave endpoints as A: expected owner access. Confirm owner list is scoped to A.
3. As editor B and anonymous client, repeat exact reads, list queries, write attempts, delete attempts, revision and autosave requests. Expect denied results and **no note title/body/classification/ID leakage**, including in errors, redirects, counts and query projections.
4. Confirm the WordPress single-post frontend, WordPress search, feeds, oEmbed, REST users/media endpoints and sitemap do not reveal A's private Notes data. Record negative outcomes without saving note bodies into logs.
5. Verify public Gutenberg `Publish` cannot convert Notes into normally public posts.

## R07: Explicit public snapshot, headers and revocation

1. On the disposable test site, create a **synthetic** allowlisted static-block note and explicitly confirm Public sharing with the required `PUBLIC` acknowledgement. Record share-path shape without placing the full bearer key in shared evidence.
2. Anonymous GET `/cb-notes/shared/<TEST_KEY>/` must return 200 and **only** the frozen snapshot. Browser and origin HTTP response must include at minimum:
   - `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`
   - `X-Robots-Tag: noindex, nofollow, noarchive`
   - `Referrer-Policy: no-referrer`
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - Restrictive `Content-Security-Policy` (compare with current `Sharing\Controller::response_headers()`).
3. Edit the private source note. Existing public snapshot must **remain frozen** and not expose revised content; regular private REST remains denied to anonymous.
4. Revoke the share using A's authorized sharing action. Immediately and repeatedly request the same share URL: expect 404 with no disclosed snapshot. Repeat after note Trash and Restore, after cache warm-up, and using different anonymous clients.
5. Repeat direct-to-origin and via CDN/LiteSpeed. An origin-level 404 is **not** sufficient if an edge cache still serves previous content. Record sanitized response status, cache decision/header and policy evidence (never the raw bearer URL).
6. Test invalid/malformed keys and route permutations, as well as dangerous/embedded blocks (which must not enter a public frozen snapshot).
7. Separate unresolved security question: restoring an **older full backup containing a once-active public key** may resurrect access at the origin unless an external/non-rollback revocation mechanism prevents it. Explicitly test or define the documented restore/rekey policy **before broad production claims**.

## R08: Optional Secure Share

- **Absent**: Notes must function normally, show no broken Secure Share UI, and expose no secure share link.
- **Present**: with the real compatible Secure Share provider, test password, expiry, key isolation, failed issuance, grant revocation and provider-unavailable conditions. Check actual anonymous HTTP delivery after revocation, not just mocked PHP.
- Do not enable Secure Share solely to clear the first HTTP gate if it is out of scope; keep absent and present scenarios separate.

## Evidence and completion

Record test site type, version/build, user category, scenario ID, sanitized HTTP status, expected/actual cache/robots headers, results before and after revocation, plus the exact Notes artifact SHA. Keep screenshots/response bodies synthetic and do not store bearer links.

**PASS** only after actual HTTP origin and applicable CDN behaviors have been observed. **FAIL** on any privacy or revocation leak. **NOT RUN** remains until these procedures have actual recorded outcomes. This document is a test plan, not evidence of completed HTTP tests.
