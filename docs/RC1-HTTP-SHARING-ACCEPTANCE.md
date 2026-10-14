# Notes RC1 | HTTP, CDN and sharing security acceptance

**Status: LOCAL HTTP ORIGIN PASS (2026-10-14), CDN/real Secure Share NOT RUN.** A disposable WordPress 7.0 origin served actual HTTP requests through 127.0.0.1:18777. Operator evidence confirms **44 HTTP checks PASS** using the pinned Alpha24 ZIP. This does not establish acceptance for LiteSpeed/QUIC.cloud, external HTTPS, the real Secure Share provider, or historic-backup active-key rollback. Use only synthetic content.

## Test environment and STOP rules

- Record the WordPress and PHP versions, Notes ZIP hash, Base API version, web server, LiteSpeed/QUIC.cloud configuration if present, HTTP origin, CDN hostname and cache rules. Test direct-to-origin and via CDN separately when both are available.
- Use two synthetic editor accounts A/B, an authorized site administrator and a fresh anonymous client. For authenticated REST requests, use appropriately authorized test accounts and WordPress-supported authentication (e.g. nonce/cookies in a browser). Never copy live credentials or access tokens into issue comments, commands, logs or repository files.
- Use a dedicated disposable test site. Do **not** enable public access to real notes. Do not modify production DNS, cache policies or genuine provider grants to make a test pass.
- **STOP** on anonymous/private content leakage, foreign-owner REST access, stale cached public content after revocation, unexpected live grant, non-private note visibility or absent required security headers.

## Automated first stage: isolated localhost origin (PASS, 44 HTTP checks)

The new `tools/check-http` script provisions only `core_blueprint_notes_http_test` in the canonical local MariaDB 10.11.19 test container and uses a temporary WordPress 7.0 site served by PHP's built-in web server bound to `127.0.0.1:18777`. It refuses an already existing test database, unexpected image/port, an occupied HTTP listener, modified tracked source, or a ZIP with SHA-256 other than `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`. It runs only with synthetic Notes; its site configuration rejects non-loopback clients. Temporary synthetic Application Passwords authenticate editor A and editor B over local HTTP; the runner cleans its test server, database and private temporary files on exit.

Covered: A owner HTTP REST read, B own read and denied cross-owner read/write, anonymous exact read and list, B's owner-scoped list, explicitly confirmed anonymous frozen public snapshot, origin security headers, private edit without snapshot mutation, repeated 404 after revocation and invalid share paths. This **does not** test a CDN, real HTTPS endpoint, hostile external clients, Secure Share provider or the historical active-key backup rollback case.

**2026-10-14 first operator run: BLOCKED before HTTP verification.** The initial script had a malformed Bash single-quoted regular expression and repeated script fragments in the REST collection-count section. `bash -n tools/check-http` failed near its output footer, and `bash tools/check` also exited before its final `Notes source checks: PASS` line; the old Node source contract was misleading because it looked only for substrings. Although the synthetic seed printed `PASS (seed)`, there is **no valid HTTP runtime PASS**. The runner has since been repaired by replacing the corrupt tail, adding an immediate whole-script Bash preflight **before any database allocation**, and requiring actual `bash -n` execution and unique test phases from the Node contract. **Updated source checks and real HTTP test are PENDING operator execution.** The recorded source-only SQL restore PASS is unaffected.

**2026-10-14 second operator run: source gate PASS, real HTTP test BLOCKED at B collection total.** The full `bash tools/check` now concludes with `Notes source checks: PASS`, and the synthetic seed plus preliminary owner/B/anonymous HTTP checks passed. The HTTP test incorrectly expected B's private Note from the unfiltered `/wp-json/wp/v2/cb-notes` endpoint. WordPress 7.0 REST collection defaults to `status=publish`; Notes persists its entries as `private`. The corrected test **retains** the strict 0-result/0-total assertion on the default endpoint and separately requires `GET /wp-json/wp/v2/cb-notes?context=edit&status=private` as B to return exactly B's Note ID/content, no A body and `X-WP-Total: 1`. Contract tests now pin this distinction. **Historical failure, superseded by the final successful run below.**

**2026-10-14 final operator execution: PASS.** Branch `feature/notes-rc1-golden-hardening-v1` at source HEAD `bbb4d21dbdb7efed2bc69876cf4a91e04c9d0297`: `bash -n tools/check-http` PASS, `node tests/http-runner-contract.cjs` PASS, `bash tools/check` fully ended with `Notes source checks: PASS`; `bash tools/check-http` completed with `Notes synthetic HTTP fixture: PASS (seed)`, `PASS (private edit)`, `PASS (revoked)`, then **`Notes real loopback HTTP origin: PASS (44 checks, WP 7.0)`**. The test covered synthetic owner REST read, owner B's denied read/write of A, anonymous exact/list denial, empty publish-default collection, B's correctly scoped explicit `context=edit&status=private` list/count, frozen anonymous public snapshot, no-store/noindex/CSP/security response headers, source edit keeping the snapshot frozen, and two repeated revoked-share HTTP 404 checks plus malformed-key denial. Tested PHP local-server **origin only**; no production, CDN or Secure Share provider interaction.

| Evidence | Verified value |
|---|---|
| WordPress target | 7.0, temporary loopback PHP HTTP server |
| Real HTTP assertions | **44 PASS** |
| Synthetic fixture phases | Seed / private edit / revoke: **PASS** |
| ZIP artifact SHA-256 | `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc` |
| Teardown | Runner exited without cleanup error; independent post-run DB inspection not separately reported |
| Remaining scope | External HTTPS/origin infrastructure, LiteSpeed/QUIC.cloud, real Secure Share, historical active-key backup rollback |

**Important:** This closes only the *local HTTP-origin subset* of R03/R07. Browser, CDN and real-provider acceptance are still open. The static suite's *HTTP runner NOT EXECUTED* wording refers only to the source-contract phase and must not override the separate actual HTTP PASS evidence.

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

### Unresolved old-backup replay gate (SECURITY DECISION REQUIRED)

See [RC1-HISTORICAL-SHARE-ROLLBACK-DECISION.md](RC1-HISTORICAL-SHARE-ROLLBACK-DECISION.md) for the proposed offline recovery quarantine/reset policy, long-term non-rollback barrier architecture and negative test. A revoked public link that becomes reachable again after restoring an older database remains a release blocker until the accepted mitigation is tested. Neither the local HTTP PASS nor the full SQL restore PASS settles this case.

## R08: Optional Secure Share

- **Absent**: Notes must function normally, show no broken Secure Share UI, and expose no secure share link.
- **Present**: with the real compatible Secure Share provider, test password, expiry, key isolation, failed issuance, grant revocation and provider-unavailable conditions. Check actual anonymous HTTP delivery after revocation, not just mocked PHP.
- Do not enable Secure Share solely to clear the first HTTP gate if it is out of scope; keep absent and present scenarios separate.

## Evidence and completion

Record test site type, version/build, user category, scenario ID, sanitized HTTP status, expected/actual cache/robots headers, results before and after revocation, plus the exact Notes artifact SHA. Keep screenshots/response bodies synthetic and do not store bearer links.

**Local origin PASS** is recorded above. **Full R03/R07 external infrastructure PASS** still requires actual applicable CDN/LiteSpeed, HTTPS and relevant browser/provider scenarios. **FAIL** on any privacy or revocation leak. Do not interpret this local PASS as a global CDN, real Secure Share or historical-backup rollback security claim.
