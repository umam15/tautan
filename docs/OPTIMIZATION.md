# Optimization Review

> Review of repository `umam15/tautan` on branch `main`.
>
> **Scope:** performance, database design, security hardening, reliability, maintainability, and deployment efficiency.
>
> **Important:** this document contains recommendations only. The application source code has not been modified as part of this review.

## 1. Executive Summary

Tautan is a lightweight PHP + SQLite application. The current implementation is already reasonably optimized for a small installation: it uses PDO prepared statements, SQLite indexes for the main ordering/visibility queries, CSRF protection, password hashing, server-side filtering, and a local favicon cache.

The largest opportunities are not micro-optimizations. They are:

1. **Fix authorization boundaries for link mutation.**
2. **Make reorder operations atomic and validate the submitted ID set.**
3. **Reduce repeated SQLite schema/migration work on every request.**
4. **Improve SQLite concurrency settings and transaction handling.**
5. **Harden favicon fetching against SSRF/resource abuse and validate image content.**
6. **Add login rate limiting and stronger session cookie settings.**
7. **Add input length limits and upload/resource limits consistently.**
8. **Improve import performance by batching writes in one transaction.**
9. **Add automated PHP syntax/static checks to CI.**
10. **Keep the implementation compatible with the PHP version actually used by the deployment target.**

---

## 2. Priority Matrix

| Priority | Area | Recommendation | Expected impact |
|---|---|---|---|
| P0 | Authorization | Restrict edit/delete/reorder operations to permitted links/users | Security / data integrity |
| P0 | Reorder | Validate IDs and use a transaction with rollback handling | Security / reliability |
| P0 | Favicon | Harden outbound fetch and validate response/content | Security / reliability |
| P1 | Authentication | Add login throttling and secure session cookie configuration | Security |
| P1 | SQLite | Configure WAL/busy timeout and review transaction boundaries | Concurrency |
| P1 | Import | Wrap bulk import in a transaction and improve duplicate lookup | Performance |
| P1 | Schema initialization | Separate migration/bootstrap work from normal request path | Performance / maintainability |
| P2 | Search | Consider normalized tags / FTS only when data volume requires it | Scalability |
| P2 | Assets | Add cache headers/versioning and optionally minify production assets | Frontend performance |
| P2 | CI | Add lint/syntax checks and basic regression tests | Maintainability |
| P3 | Micro-optimizations | Reduce minor allocations and repeated helper work | Low |

---

## 3. Security and Authorization

### 3.1 Link ownership checks should be explicit

Current link mutation endpoints obtain a link by ID and then perform the operation. For example, edit and delete operations do not appear to enforce that the logged-in user owns the target link or that the user has an administrator-level permission.

This means the application should not rely only on the fact that the user is authenticated.

**Recommendation:**

Introduce an authorization helper such as:

- owner can edit/delete their own link;
- admin can manage all links;
- ordinary users cannot modify another user's link;
- visibility changes should follow the same authorization rule.

Prefer enforcing the rule inside the data-access operation itself, not only in the HTML page.

For example, an update/delete query should include the permitted `user_id` condition where appropriate. This prevents a future endpoint from accidentally bypassing a UI-level check.

**Priority: P0**

---

### 3.2 Reorder endpoint needs stronger validation

`links/reorder.php` verifies login and CSRF, but the submitted ID array should be treated as untrusted input.

Recommended validation:

- IDs must be positive integers.
- Remove or reject duplicates.
- Verify every submitted ID exists.
- Verify every submitted ID belongs to the current user's allowed set.
- Decide whether admins may reorder all links.
- Reject a partial or foreign ID set instead of silently updating what was supplied.

The current helper updates every supplied ID and does not verify ownership.

**Priority: P0**

---

### 3.3 Reorder transaction needs rollback handling

`reorder_links()` starts a transaction but commits without a `try/catch` rollback path.

Use the pattern:

1. `beginTransaction()`
2. execute all updates
3. `commit()`
4. on exception, `rollBack()` if still in a transaction
5. rethrow/report the error

This prevents a partially applied reorder if one statement fails.

**Priority: P1**

---

## 4. Authentication and Session Hardening

### 4.1 Add login throttling

Password hashing is already handled through `password_hash()` and verification through `password_verify()`, which is appropriate.

The missing layer is protection against repeated login attempts.

Recommended lightweight approach for a SQLite deployment:

- Track failed attempts by username and/or a privacy-conscious client identifier.
- Apply exponential or fixed backoff after repeated failures.
- Expire counters automatically.
- Avoid storing raw passwords or unnecessary identifying data.
- Return the same generic login error for invalid usernames and passwords.

For a small private installation, even a simple short lockout/backoff can substantially reduce automated password guessing.

**Priority: P1**

---

### 4.2 Harden session cookie configuration

Before `session_start()`, explicitly configure:

- `httponly = true`
- `secure = true` when served over HTTPS
- `samesite = Lax` or stricter where compatible
- an appropriate session lifetime

Also consider regenerating/clearing the session completely on logout.

**Priority: P1**

---

### 4.3 Password policy

The current minimum password length is 6 characters.

For a real deployment, consider increasing this to at least 10–12 characters, while avoiding unnecessarily complex composition rules. A longer passphrase is generally easier for users and harder to brute-force.

Do not hard-code a particular hash algorithm parameter unless there is a measured need; `PASSWORD_DEFAULT` is a sensible choice.

**Priority: P2**

---

## 5. SQLite Optimization

### 5.1 Enable WAL mode for concurrent reads/writes

The application is a good fit for SQLite, but SQLite concurrency can improve with:

```sql
PRAGMA journal_mode = WAL;
PRAGMA busy_timeout = 5000;
PRAGMA foreign_keys = ON;
```

`foreign_keys = ON` is already enabled.

WAL can allow readers to continue while a writer is active and is especially useful when the application is accessed by multiple users.

Test WAL carefully if the database is placed on network/shared storage. SQLite databases should preferably live on local storage.

**Priority: P1**

---

### 5.2 Avoid expensive schema checks on every request

`db()` calls `init_schema()`, and `init_schema()` performs table/index creation checks and `PRAGMA table_info(links)` on the request path.

For a small application this is acceptable, but it is unnecessary work once the installation is stable.

Recommended architecture:

- Run migrations during setup/deployment.
- Store a schema version in a metadata table, e.g. `app_settings`.
- Check the version once during startup or deployment.
- Execute only required migrations.
- Avoid repeatedly inspecting the schema during ordinary page requests.

This will reduce database overhead and make future migrations safer and easier to reason about.

**Priority: P1**

---

### 5.3 Transactional imports

The bookmark importer currently loops over parsed links and calls `create_link()` individually.

For hundreds or thousands of imported links, wrap the whole import in a transaction:

```text
BEGIN
  insert link 1
  insert link 2
  ...
COMMIT
```

If an error occurs, rollback the complete import.

This can be substantially faster than committing each write independently.

**Priority: P1**

---

### 5.4 Improve duplicate detection

`get_all_link_urls()` loads every URL into PHP memory before importing.

This is fine for a small bookmark collection, but it scales linearly in memory.

Possible future design:

- Add a normalized/canonical URL column.
- Add a unique index if duplicate URLs are globally forbidden.
- Or query duplicates in batches.
- If duplicates are intentionally allowed, retain the current behavior and document the scope clearly.

Do not add a unique constraint blindly because existing data may intentionally contain the same URL.

**Priority: P2**

---

## 6. Database Schema and Query Optimization

### 6.1 Current indexes are appropriate for the current scale

The application already creates indexes for:

- `sort_order`
- `visibility`

The primary ordering query also uses `id` as a deterministic tie breaker.

For the current small-data model, this is sufficient.

Avoid adding indexes speculatively. Every additional index increases write cost and database size.

---

### 6.2 Tags are intentionally denormalized

Tags are stored as comma-separated text and searched using `LIKE`.

This is simple and reasonable for dozens or hundreds of links.

If the application grows significantly, migrate to:

- `tags(id, name)`
- `link_tags(link_id, tag_id)`

with indexes on both foreign keys.

That would allow indexed tag filtering and cleaner tag management.

**Do not migrate solely for theoretical performance at the current scale.**

**Priority: P2**

---

### 6.3 Consider SQLite FTS for large search collections

The current search uses substring `LIKE` over title, description, and URL.

For a small bookmark manager this is appropriate.

If the dataset reaches many thousands of links and search becomes a measurable bottleneck, SQLite FTS5 can provide a better search architecture.

This should be introduced only after measuring real search latency.

**Priority: P2**

---

## 7. Favicon Fetching

The local favicon cache is a good architectural choice because repeated browser requests do not have to contact the external favicon service.

However, the favicon endpoint performs outbound network requests based on a user-controlled hostname.

### Recommendations

1. Validate the resolved destination before making an outbound request.
2. Consider blocking private, loopback, link-local, multicast, and other reserved IP ranges after DNS resolution.
3. Set strict connection and total download limits.
4. Limit response size before storing it.
5. Validate that the downloaded content is actually an allowed image type.
6. Do not trust an arbitrary upstream `Content-Type` header.
7. Consider a maximum favicon size, e.g. tens/hundreds of KB rather than unlimited response bodies.
8. Consider using cURL with explicit timeout, redirect, DNS, protocol, and maximum-size controls if available.

The current hostname regex reduces some malformed-input risk, but hostname validation alone is not a complete SSRF defense.

**Priority: P0**

---

## 8. Backup and Restore

The backup flow already has several good properties:

- admin-only access;
- CSRF protection for restore;
- SQLite validation;
- schema validation;
- automatic backup before restore;
- temporary files for generated backups.

Recommended improvements:

### 8.1 Limit uploaded backup size

Add an explicit application-level maximum file size in addition to web-server/PHP upload limits.

### 8.2 Validate more than table names

Schema validation should also verify:

- expected columns;
- compatible schema version;
- SQLite integrity using `PRAGMA integrity_check`;
- optionally foreign-key integrity.

### 8.3 Avoid exposing internal exception details

The application currently includes exception messages in some user-facing backup errors.

For production:

- log detailed exceptions server-side;
- show a generic message to the user.

This prevents accidental disclosure of filesystem/database implementation details.

**Priority: P1**

---

## 9. Import/Export

### 9.1 Import size is limited

The current 5 MB import limit is a good baseline.

Also consider:

- server-side `upload_max_filesize`;
- `post_max_size`;
- execution-time limits;
- memory limits.

### 9.2 DOM parsing can consume significantly more memory

`DOMDocument::loadHTML()` builds a complete DOM in memory.

For the current 5 MB maximum this is probably acceptable, but very large bookmark exports can expand considerably in memory.

If large imports become common, consider:

- lowering the maximum upload size;
- parsing incrementally;
- or processing the file in chunks with a more appropriate parser.

Do not optimize this prematurely.

---

## 10. HTTP and Browser Performance

### 10.1 Static asset caching

The service worker already caches CSS, JavaScript, and selected icons.

For normal HTTP requests, also configure long-lived cache headers for versioned static assets.

A useful pattern is:

```text
/assets/app.js?v=<version>
/assets/style.css?v=<version>
```

with a long cache lifetime.

When the asset version changes, the URL changes and the browser obtains the new asset.

### 10.2 Service worker cache versioning

The current `CACHE_NAME` is manually versioned.

Keep incrementing it when static assets change, or move toward asset revisioning so stale CSS/JS is less likely after deployments.

### 10.3 Avoid caching dynamic HTML

The existing service worker deliberately avoids caching PHP pages. Keep this behavior because pages contain session-specific and visibility-specific data.

---

## 11. Frontend JavaScript

The frontend is already lightweight.

Potential improvements:

- debounce client-side search URL updates if `history.replaceState()` becomes noisy;
- avoid repeated DOM queries where practical;
- handle failed reorder requests visibly to the user;
- revert the UI order if the server rejects a reorder;
- disable repeated submissions for expensive operations such as restore/import.

The reorder request currently logs errors to the console but leaves the UI apparently successful. A failed save should provide user-visible feedback.

**Priority: P2**

---

## 12. Error Handling and Logging

Production behavior should distinguish between:

- user-facing validation errors;
- expected application errors;
- unexpected server exceptions.

Recommended pattern:

```text
User -> safe generic message
Server -> detailed log
Developer -> stack trace in development only
```

Keep `display_errors=0` in production.

Consider adding a small centralized error/exception handler so all endpoints follow the same policy.

---

## 13. Maintainability

### 13.1 Separate concerns

`includes/functions.php` has grown into a large multipurpose module containing:

- link operations;
- tags;
- statistics;
- import;
- export;
- users;
- settings;
- backup/restore;
- shared UI helpers.

For a small project this is understandable, but it is becoming a maintenance bottleneck.

A future refactor could split it into:

```text
includes/
├── auth.php
├── db.php
├── links.php
├── users.php
├── tags.php
├── import.php
├── export.php
├── backup.php
├── settings.php
└── helpers.php
```

This is primarily a maintainability improvement rather than a raw performance optimization.

**Priority: P2**

---

### 13.2 Centralize validation

URL, username, tag, upload, and permission validation should be reusable functions.

This prevents individual endpoints from gradually developing different validation rules.

---

### 13.3 Add constants for limits

Centralize values such as:

- maximum title length;
- maximum description length;
- maximum URL length;
- maximum icon URL length;
- maximum tag count;
- maximum tag length;
- maximum import size;
- maximum backup size.

This makes resource limits consistent and easy to change.

---

## 14. PHP Version and Deployment Compatibility

The Docker image currently targets PHP 8.2.

The README states PHP 8.0+.

Before adopting newer language/library features, ensure they are compatible with the minimum supported PHP version.

Recommended policy:

- explicitly define the minimum supported PHP version;
- test against that version in CI;
- test against the production version used by the deployment environment;
- keep Docker and documentation synchronized.

If deployment is on a NAS/shared-hosting environment, prioritize the PHP version actually supplied by that platform rather than assuming the Docker version is representative.

---

## 15. CI/CD Recommendations

The existing GitHub Actions release workflow should be extended with a validation job before releases.

Suggested checks:

1. PHP syntax check:
   `php -l` for every PHP file.
2. Static analysis, optionally PHPStan at a practical level.
3. Basic smoke tests.
4. Docker image build.
5. Optional SQLite migration test from an older schema.
6. Import/export regression test.
7. Authentication/authorization tests.

A minimal syntax check alone would already catch many accidental PHP regressions.

---

## 16. Suggested Optimization Roadmap

### Phase 1 — Security and correctness

- [ ] Add link ownership/authorization checks.
- [ ] Validate reorder IDs and ownership.
- [ ] Add transaction rollback handling.
- [ ] Harden favicon outbound requests.
- [ ] Configure secure session cookies.
- [ ] Add login throttling.
- [ ] Avoid exposing internal exception details.

### Phase 2 — Database and performance

- [ ] Enable SQLite WAL where deployment storage supports it.
- [ ] Add a reasonable SQLite busy timeout.
- [ ] Move schema migrations out of the normal request path.
- [ ] Wrap bookmark imports in a transaction.
- [ ] Measure query latency before adding more indexes.

### Phase 3 — Maintainability

- [ ] Split the large `includes/functions.php`.
- [ ] Centralize validation and resource limits.
- [ ] Introduce a schema version.
- [ ] Add automated syntax/static checks.
- [ ] Add regression tests for authorization and import/export.

### Phase 4 — Scale only if required

- [ ] Normalize tags.
- [ ] Consider SQLite FTS5.
- [ ] Introduce more advanced caching only after profiling.
- [ ] Revisit SQLite vs another database only when actual workload justifies it.

---

## 17. What Should Not Be Optimized Yet

Avoid premature changes such as:

- replacing SQLite with MySQL/MariaDB without a measured need;
- adding many database indexes;
- introducing a framework;
- minifying every asset manually;
- rewriting PHP helpers solely to reduce a few function calls;
- caching dynamic HTML;
- replacing the current tag model before data volume requires it.

For the current application size, these changes add complexity without a guaranteed practical benefit.

---

## 18. Recommended Validation Before Code Changes

Before implementing the recommendations, establish a baseline:

- PHP version;
- SQLite version;
- typical number of links;
- maximum number of users;
- average page response time;
- import time for 100 / 1,000 / 5,000 links;
- database size;
- favicon cache size;
- concurrent users/requests.

Then implement the P0/P1 changes and compare the same measurements.

The goal should be **measurable improvement without unnecessary architectural complexity**.

---

## Review Status

- Repository reviewed: `umam15/tautan`
- Branch: `main`
- Code changes: **none**
- Documentation changes: **this file**
- `CONTRIBUTING.md`: not present in the current repository tree
- `docs/OPTIMIZATION.md`: not present before this review
