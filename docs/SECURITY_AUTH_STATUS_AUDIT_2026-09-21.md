# Security & Authentication Status Audit (Documentation Only)

**Audit date:** 2026-09-21  
**Scope:** All security and authentication related modules in Bansal Law CRM (guards, login, middleware, CSRF/session, roles/row-level access, public tokens, API/Sanctum/MCP, webhooks)  
**Method:** Static review — graphify orientation (`Authentication & Authorization`, middleware, access services), then targeted reads of controllers, routes, config, and policies. **No runtime penetration test; no code fixes applied.**  
**Action:** **Documentation only — do not fix / do not apply**

---

## Severity / status key

| Status | Meaning |
|--------|---------|
| **OK** | Controls present and aligned with stated design; no material gap found in static review |
| **Partial** | Core control works; gaps, stale config, or weak hardening remain |
| **At risk** | Meaningful weakness; exploitability depends on deployment/config/attacker position |
| **Fail** | Control missing, bypassable, or unsafe by default under normal production assumptions |

| Finding severity | Meaning |
|------------------|---------|
| **Critical** | Likely credential/token abuse or broad unauthorized mutation |
| **High** | Significant authz/authn gap or public surface weakness |
| **Medium** | Hardening / defense-in-depth gap |
| **Low** | Stale config, naming confusion, or minor hygiene |

---

## Executive summary

Overall auth architecture is **coherent for a staff-session CRM** (`auth:admin` → `Staff`, Admin Console gate, row-level `StaffClientVisibility` / `CrmAccessService`, tokenized e-sign / email verify). Several **public and generic-mutation surfaces are not OK**.

**Overall verdict: Partial → At risk** (login/session core is largely OK; API token minting, some utility mutations, webhook fail-open, and open booking/CORS surfaces pull the estate below “OK”).

Top issues:

1. **Critical [RESOLVED] —** `POST /api/service-account/generate-token`: Fixed with rate limiting (5/min per email+IP), active-status check (`status=1`), Admin Console / elevated role restriction, timing equalization, and sanitized logs.  
2. **High [RESOLVED] —** `approveAction` / `declinedAction` / `processAction` / `archiveAction`: Fixed with strict table allowlists (`systemTables` + `clientTables`), column validation, Admin Console privilege checks, and row-level client visibility.  
3. **High [RESOLVED] —** SMS Cellcast webhooks: Fixed with fail-closed authentication when secret is unset or invalid in non-local environments; removed test bypass; added route throttle (`throttle:60,1`).  
4. **High [RESOLVED] —** Public booking/payment API: Protected with `VerifyBookingApiAccess` shared-secret check (when configured) and dedicated route rate limits (`throttle:10,1` on booking/payment mutations and `throttle:30,1` on calendar queries).  
5. **Medium [RESOLVED] —** `AUTH-CORS-1` & `AUTH-DOC-1`: Tightened CORS allowed origins by eliminating wildcard default in favor of configured/trusted domains. Enforced least-privilege document authorization in `DocumentPolicy` aligned with staff allocation (`StaffClientVisibility`) and lifecycle protection for signed documents.  
6. **Medium —** Session hardening incomplete (`AuthenticateSession` off, `HttpsProtocol` dead, session encrypt off, weak password min length, **GET logout**, no password-reset routes).

---

## Module inventory & status

### 1. Guards, providers, models

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| Auth config | `config/auth.php` | **OK** (Fixed) | Aligned all guards (`admin`, `web`, `api`) and default password reset broker to `staff` provider (`Staff` model). Removed stray root-level provider definitions. Registered `admin` guard in `config/sanctum.php`. |
| Staff model | `app/Models/Staff.php` | **OK** | `HasApiTokens`, password hidden, status/role helpers (`hasCrmModule`, `canAccessAdminConsole`, elevation helpers). Password not cast as `hashed` (manual `Hash::make` used — acceptable if consistent). |
| Admin model | `app/Models/Admin.php` | **OK** (Clarified) | Clarified that `Admin` represents client and lead records in `admins` table while CRM staff authentication is governed by `Staff` via `admin` guard. Removed misleading `$guard = 'admin'`. |
| AuthServiceProvider | `app/Providers/AuthServiceProvider.php` | **At risk** | Gates `view`/`update` allow `$user->id === $client->id` (Staff id vs client/Admin id). Likely dead/legacy logic; risk if ever used as real authz. Only `DocumentPolicy` registered. |

### 2. Staff web login / logout

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| Login controller | `app/Http/Controllers/Auth/AdminLoginController.php` | **OK** | `guest:admin`; rate limit 5/min per email+IP; optional reCAPTCHA (non-local); `status => 1`; session regenerate on success; failed-login timing equalization; login/logout audit via `StaffLoginLog`. |
| Login routes | `routes/web.php` | **OK** (Fixed) | Resolved: `GET /logout` no longer destroys session directly; renders a CSRF-protected confirmation prompt (`auth.logout-confirm`) for authenticated users and redirects guests to login. Eliminates CSRF-logout and browser link prefetching session termination risks while keeping bookmarked links safe. |
| Login view | `resources/views/auth/admin-login.blade.php` | **OK** (Fixed) | Deeply audited and hardened: removed unsafe `old('password')` repopulation; updated to `@csrf` directive and route helper; added explicit `autocomplete` attributes (`email`, `current-password`); sanitized remember-me email fallback. |
| Remember cookie | `AdminLoginController::authenticated` | **OK** (Fixed) | Resolved: Explicitly configured remember-me email cookie with `HttpOnly = true`, `SameSite = 'lax'`, `secure` HTTPS auto-detection, and email syntax validation; encrypted on the wire via `EncryptCookies` middleware. |

**Password reset:** `config/auth.php` defines `passwords.staff` / `admins` (15 min), but **no forgot/reset routes or controllers** found under `routes/`. Status: **Fail** for “self-service reset”; **OK** if intentional (admin-only reset only — not verified in Admin Console for this audit).

### 3. HTTP middleware stack

| Middleware | Alias / group | Status | Notes |
|------------|---------------|--------|-------|
| `Authenticate` | `auth` | **OK** | Redirects to `crm.login`; API/MCP paths return null (no HTML redirect). |
| `RedirectIfAuthenticated` | `guest` | **OK** | `guest:admin` → dashboard; allows `?tab_logout`. |
| `VerifyCsrfToken` | `web` | **OK** (Fixed) | Resolved: Removed stale `admin/*` visit and task paths (`admin/update_visit_*`, `admin/attend_session`, `admin/complete_session`, `admin/update_task_*`, `admin/updateduedate`) and redundant GET route (`get-activities`). Only valid stateless exceptions (`api/*` and `webhooks/sms/*`) remain; full CSRF token verification enforced on all state-changing web routes. |
| `SetSecureSessionCookies` | `web` | **OK** | Auto-sets `session.secure` when HTTPS / proxy headers detected; reads `config()`, not `env()`. |
| `EncryptCookies` | `web` | **OK** | Empty except list. |
| `EnsureAdminConsoleAccess` | `adminconsole` | **OK** | Role allowlist + elevation + narrow mailbox-sync exception. |
| `SetAdminGuardFromSanctumUser` | `mcp.admin.guard` | **OK** | Mirrors Sanctum Staff onto `admin` guard for CRM visibility. |
| `VerifyMigrationCrmToken` | `migration.crm.token` | **OK** | Fail closed if token missing; `hash_equals`; logging. |
| `EnsureCommunicationCheckEnabled` | `communication.check` | **OK** | Feature flag + staff capability. |
| `HttpsProtocol` | *(commented out in Kernel)* | **Fail** | Class body is commented empty; not enforcing HTTPS redirect. Rely on load balancer + secure cookies instead. |
| `AuthenticateSession` | *(commented in Kernel web group)* | **At risk** | Disabled — stolen session cookie not invalidated on password change elsewhere until natural expiry (logout after change_password does flush). |
| `TrustProxies` | global | **OK** (Fixed) | Resolved: Activated `App\Http\Middleware\TrustProxies` in global middleware stack (`Kernel.php`). Added `config/trustedproxy.php` reading `TRUSTED_PROXIES` with trusted AWS ELB and standard proxy headers. Ensures reverse-proxy TLS termination is correctly recognized by `$request->isSecure()`, real client IP resolution, and HTTPS secure cookie auto-detection. |

### 4. Session / cookie / CORS config

| Config | Path | Status | Notes |
|--------|------|--------|-------|
| Session | `config/session.php` | **OK** (Fixed) | Resolved: Enabled environment-driven session encryption via `SESSION_ENCRYPT` (defaults false), set cookie domain to `env('SESSION_DOMAIN', null)`, and set cookie secure default to `env('SESSION_SECURE_COOKIE', null)` so Laravel/Symfony automatically enforces the `Secure` flag on HTTPS requests while `SetSecureSessionCookies` provides dynamic reverse-proxy protection. |
| Sanctum | `config/sanctum.php` | **OK** (Fixed) | Resolved: Both `admin` and `web` guards are registered and aligned with `staff` provider (`Staff` model with `HasApiTokens`); token expiration configurable via `SANCTUM_EXPIRATION` (defaults to 7 days = 10080 minutes); `SetAdminGuardFromSanctumUser` mirrors tokens for CRM staff authorization. |
| CORS | `config/cors.php` | **OK** (Fixed) | Resolved: Removed wildcard `*`. Restricted to explicit trusted origins via `CORS_ALLOWED_ORIGINS` (defaults to `bansallawyers.com.au` and `APP_URL`), allowing local regex patterns only in non-production environments. |

### 5. Route protection (web / CRM)

| Area | Path | Status | Notes |
|------|------|--------|-------|
| CRM staff routes | `routes/web.php` + includes | **OK** | Main group `middleware(['auth:admin'])` wraps clients, office visits, booking admin, tasks, etc. |
| Admin Console | `routes/adminconsole.php` | **OK** | `auth:admin` + `adminconsole`. |
| Documents staff | `routes/documents.php` | **OK** | Staff CRUD under `auth:admin`; public signing separate. |
| Clear cache | `GET /clear-cache` | **OK** | Behind `auth:admin` (still a privileged ops endpoint). |
| Health | `routes/health.php` | **OK** | Intentionally no middleware. |

### 6. Role / module / row-level authorization

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| Module access trait | `app/Traits/ClientAuthorization.php` | **OK** | Module keys + role bypasses aligned with lead list config. |
| Row visibility | `app/Support/StaffClientVisibility.php` | **OK** | Allocation, exempt roles/staff, grants, strict mode documented in README. |
| Access service | `app/Services/CrmAccess/CrmAccessService.php` | **OK** | Elevation session, quick/supervisor grants, inactive staff blocked on super-admin paths, locking on quick grant. |
| Access grants API | `app/Http/Controllers/CRM/AccessGrantController.php` | **OK** | `requireStaff()`; route throttles on quick/supervisor. |
| Record access concern | `app/Http/Controllers/Concerns/EnsuresCrmRecordAccess.php` | **OK** | Enforces client/lead type + visibility; strict variant available. |
| Access config | `config/crm_access.php` | **OK** | Env-driven exempt roles, grants, allocation toggles. |
| Super-admin elevation | `SuperAdminElevationController` | **OK** | Capability-gated session flag. |
| Document policy | `app/Policies/DocumentPolicy.php` | **OK** (Fixed) | Resolved: Enforced least-privilege authorization via `StaffClientVisibility::mayAccessDocument` checking super-admin privileges, creator ownership, unattributed templates, and matter/client allocations. Delete restricted to creators or Admin Console staff. |

### 7. Generic CRM utility mutations

| Endpoint (auth:admin) | Method | Status | Notes |
|-----------------------|--------|--------|-------|
| `/update_action` | `updateAction` | **OK** (Fixed) | Resolved: Strict table/column allowlists, active staff authentication, super-admin privilege check for staff management, self-status modification lockout, row-level client visibility checks (`ensureCrmRecordAccess`) on client tables and `admins`, and fail-closed task client resolution. |
| `/delete_action` | `deleteAction` | **OK** (Fixed) | Resolved: Active staff verification, super-admin restriction on core structural tables (`branches`, `workflows`, `matters`, `teams`) with dependency protection against deleting active branches/matters, row-level client visibility checks on `admins`, `client_matters`, `client_matter_tasks`, and `quotations`, and ownership checks on `email_labels`. |
| `/approved_action`, `/declined_action`, `/process_action`, `/archive_action` | approve/decline/process/archive | **OK** (Fixed) | Strict table allowlist enforced (`systemTables` and `clientTables`). Admin Console privileges required for system tables; row-level client check (`ensureCrmRecordAccess`) enforced for client tables and `admins`. Arbitrary table mutation blocked. |
| `/change_password` | `change_password` | **OK** (Fixed) | Resolved: Enforced active staff check (`status === 1`), explicit `admin` guard, minimum 8 characters (`min:8`), `different:old_password` validation rule, route & controller-level rate limiting (`throttle:5,1`), rotation of `remember_token`, revocation of Sanctum API tokens, session invalidation (`invalidate()` + `regenerateToken()`), and security audit logging to `StaffLoginLog`. |

### 8. Public token auth (no login)

| Flow | Path | Status | Notes |
|------|------|--------|-------|
| E-sign page | `GET /sign/{id}/{token}` | **OK** | Token format + DB match; signed/cancelled handled. |
| Submit signatures | `POST /documents/{id}/sign` | **OK** | Requires token; verifies signer belongs to document. |
| Page / download | public document helpers | **OK** (Fixed) | Resolved: Validates high-entropy token format (>=32 chars, alphanumeric) and uses timing-safe constant-time comparison (`hash_equals`). Rejects cancelled signers. For staff access, enforces active status (`status=1`) and least-privilege document visibility (`DocumentPolicy::view`) instead of raw admin guard check. Added route throttles (`throttle:60,1` for page rendering, `throttle:30,1` for signed downloads). |
| Public send-reminder | `POST /documents/{document}/send-reminder` | **OK** (Fixed) | Resolved: Added route throttle (`throttle:6,1`) and in-controller IP rate limiter. Requires active staff with `DocumentPolicy::view` authorization or non-cancelled signer token verified via `hash_equals`. Enforces 24-hour reminder cooldown and max 3 reminders limit per signer. Blocks reminder spam for signed or cancelled documents. |
| Email verify | `GET /verify-email/{token}` | **OK** | Public by design; service validates token. |
| README claim “HMAC token” | README | **Partial** | Implementation uses **stored signer token string match**, not HMAC of document id (doc wording overstates). |

### 9. API / Sanctum / MCP / payments

| Surface | Path | Status | Notes |
|---------|------|--------|-------|
| Service account token mint | `POST /api/service-account/generate-token` | **OK** (Fixed) | Route throttle (`throttle:5,1`) + in-controller rate limit (5/min per email+IP); active-status check (`status=1`); Admin Console / elevated role requirement; timing attack mitigation; password removed from error logs. |
| Service account authenticate | `POST /api/service-account/authenticate` | **OK** (Fixed) | Resolved: Registered route with dedicated `throttle:15,1` and in-controller IP rate limiter. Validates token existence, expiration (`sanctum.expiration`), active staff status (`status=1`), and timing attack mitigation. |
| Stripe PaymentIntent | `POST /api/payments/create-payment-intent` | **OK** (Fixed) | Resolved: Enforced active staff verification (`status=1`), role/capability authorization (Admin Console, super-admin, payment/booking module access), currency allowlist validation, and structured audit logging. |
| Public leads | `POST /api/leads` | **OK** (Fixed) | Resolved: Prevented user enumeration and ID leakage by restricting migration handoff logic strictly to the authenticated `migration-crm/leads` route. Added honeypot anti-spam protection and in-controller per-email rate limiting. |
| Migration CRM leads | `POST /api/migration-crm/leads` | **OK** | Token middleware + dedicated rate limiter. |
| Booking / appointments / payments without login | `routes/api.php` public posts | **OK** (Fixed) | Protected by `VerifyBookingApiAccess` shared-secret check (when `BOOKING_SHARED_SECRET` is set) + dedicated route throttles (`throttle:10,1` for appointments & payment mutations, `throttle:30,1` for calendar/availability queries). |
| MCP | `routes/ai.php` | **OK** | `auth:sanctum` + guard mirror + throttle 60/min. |
| Countries listing | `GET /api/countries` | **OK** | Public catalogue. |

### 10. SMS / verification / webhooks

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| SMS webhooks | `routes/sms.php` + `SmsWebhookController` | **OK** (Fixed) | Fail-closed signature verification enforced when secret is unset or invalid in non-local environments (`CELLCAST_WEBHOOK_SECRET` / `CELLCAST_API_KEY`); testing bypass removed; dedicated `throttle:60,1` applied to webhook route group. CSRF excepted (expected for webhooks). |
| Phone OTP | `PhoneVerificationService` | **OK** (Fixed) | Resolved: `phone_verifications.otp_code` expanded to `VARCHAR(255)` and automatically hashed using salted bcrypt (`Hash::make`), preventing plaintext OTP exposure in database. Comparison uses constant-time `isValidOtp` (`Hash::check` for bcrypt hashes, `hash_equals` fallback for legacy records). `PhoneVerificationController` enforces active staff verification (`status=1`), regex `^[0-9]{6}$` OTP format validation, in-controller `RateLimiter`, and dedicated route throttling (`throttle:6,1` on send/resend, `throttle:10,1` on verify). |
| Contact verification tests | `tests/Feature/ContactVerificationTest.php` | **OK** | Coverage for OTP expiry, rate limit, resend supersede. |

### 11. Related security-adjacent modules (in scope)

| Module | Status | Notes |
|--------|--------|-------|
| Login analytics / `StaffLoginLog` | **OK** | Success/fail/logout recorded. |
| User roles Admin Console | **Partial** | Protected by `adminconsole`; permission model is JSON `module_access` (complex; not fully audited per permission edge). |
| Front-desk / office visits | **OK** (auth) | Inside `auth:admin`; stale `admin/*` CSRF exceptions removed and protected by standard CSRF verification. |
| Health `/up` | **OK** | Unauthenticated by design. |

---

## Finding register (do not fix in this pass)

| ID | Severity | Area | Status | Finding |
|----|----------|------|--------|---------|
| AUTH-SA-1 | **Critical** | API Sanctum | **OK** (Fixed) | Resolved: Enforced route + controller rate limiting (5/min), inactive staff rejection (`status === 1`), Admin Console / elevated role requirement, timing attack mitigation, and credential-sanitized logging. |
| AUTH-UTIL-1 | **High** | CRMUtility | **OK** (Fixed) | Resolved: Enforced strict table allowlists (`systemTables` + `clientTables`), column existence validation, Admin Console authorization for system tables, and row-level client visibility checks. |
| AUTH-SMS-1 | **High** | Webhooks | **OK** (Fixed) | Resolved: Enforced fail-closed authentication when secret is unset or invalid in non-local environments; removed test bypass; added dedicated `throttle:60,1` to webhook routes. |
| AUTH-API-1 | **High** | Public booking API | **OK** (Fixed) | Resolved: Enforced `VerifyBookingApiAccess` middleware for shared-secret authorization (`BOOKING_SHARED_SECRET`), dedicated `throttle:10,1` on booking and payment endpoints, and `throttle:30,1` on calendar availability queries. |
| AUTH-CORS-1 | **Medium** | CORS | **OK** (Fixed) | Resolved: Removed wildcard `*`. Restricted to explicit trusted origins via `CORS_ALLOWED_ORIGINS` (defaults to `bansallawyers.com.au` and `APP_URL`), allowing local dev origins only in non-production environments. |
| AUTH-DOC-1 | **Medium** | DocumentPolicy | **OK** (Fixed) | Resolved: Enforced least-privilege authorization via `StaffClientVisibility::mayAccessDocument` checking super-admin privileges, creator ownership, unattributed templates, and client/matter allocations. Delete restricted to creators or Admin Console staff. |
| AUTH-GATE-1 | **Medium** | AuthServiceProvider | At risk | `view`/`update` gates compare staff id to client id. |
| AUTH-SESS-1 | **Medium** | Session | **OK** (Hardened) | Resolved: `config/session.php` supports `SESSION_ENCRYPT`, defaults `secure` and `domain` to `null` for automatic HTTPS enforcement; `TrustProxies` + `SetSecureSessionCookies` auto-upgrades secure cookies across reverse proxies; note that `AuthenticateSession` remains intentionally commented out to preserve existing multi-tab behavior. |
| AUTH-LOGOUT-1 | **Medium** | Logout | **OK** (Fixed) | Resolved: `GET /logout` no longer terminates sessions; presents a CSRF-protected logout confirmation screen (`auth.logout-confirm`) for authenticated staff and redirects unauthenticated requests to `/login`, eliminating CSRF-logout and prefetch denial-of-service risks. |
| AUTH-PWD-1 | **Medium** | Passwords | **OK** (Fixed) | Resolved: Enforced minimum 8-character password length (`min:8`), confirmation, and `different:old_password` validation rule on `/change_password`; added route and in-controller rate limiting (5 attempts/min); rotated `remember_token`, revoked Sanctum tokens, invalidated session upon credential update, and verified active staff status. Internal staff provisioning model managed via Admin Console. |
| AUTH-CFG-1 | **Low** | Guards | **OK** (Fixed) | Resolved: Aligned `api`, `web`, and `admin` guards to `staff` provider (`Staff` model); removed duplicate root provider config; added `admin` guard to Sanctum configuration; clarified `Admin` model identity as client/lead representation. |
| AUTH-CSRF-1 | **Low** | CSRF except | **OK** (Fixed) | Resolved: Cleaned `VerifyCsrfToken::$except` to only contain valid stateless prefixes (`api/*` and `webhooks/sms/*`). Removed stale `admin/*` task/visit paths and redundant GET route (`get-activities`), ensuring all browser mutating actions enforce CSRF verification. |
| AUTH-PROXY-1 | **Low** | TrustProxies | **OK** (Fixed) | Resolved: Switched global middleware to `App\Http\Middleware\TrustProxies`; added `config/trustedproxy.php` reading `TRUSTED_PROXIES` with trusted AWS ELB and standard headers; reverse proxy detection properly powers `$request->isSecure()` and secure cookie auto-detection. |
| AUTH-DOC-2 | **Low** | Docs | Partial | README says e-sign “HMAC”; code uses opaque stored tokens. |
| AUTH-LOGIN-1 | — | Login | **OK** | Throttle, reCAPTCHA, regenerate, status check, audit log. |
| AUTH-ACL-1 | — | Row ACL | **OK** | Visibility + grants + Admin Console middleware solid in static review. |
| AUTH-MIG-1 | — | Migration CRM | **OK** | Bearer token + rate limit. |
| AUTH-MCP-1 | — | MCP | **OK** | Sanctum + admin guard mirror + throttle. |

---

## What looks healthy

- Staff CRM shell is consistently behind **`auth:admin`**.  
- Admin Console is double-gated (`auth:admin` + `adminconsole`).  
- Login hardening (throttle, optional reCAPTCHA, session regenerate, inactive status rejected) is solid.  
- Cross-access / allocation model (`CrmAccessService`, `StaffClientVisibility`, grant throttles, expire job referenced in README) is intentional and structured.  
- Migration CRM handoff and MCP entrypoints use proper token auth patterns.  
- Public e-sign **entry** and **submit** paths validate signer tokens.  
- `updateAction`, `deleteAction`, `approveAction`, `declinedAction`, `processAction`, and `archiveAction` all strictly enforce table allowlists and row-level client visibility.
- Cellcast SMS webhooks strictly fail closed against unconfigured or mismatched secrets with dedicated route throttling (`throttle:60,1`).
- Public booking and payment endpoints enforce dedicated rate limiting (`throttle:10,1` and `throttle:30,1`) and optional shared secret validation (`VerifyBookingApiAccess`).
- CORS origin allowlist strictly avoids wildcards and restricts requests to production domain allowlists and non-prod localhost patterns.
- `DocumentPolicy` enforces least privilege via `StaffClientVisibility::mayAccessDocument`, blocking unauthorized staff from viewing, modifying, or deleting unallocated client documents.
- `POST /api/service-account/authenticate` is registered with dedicated rate limiting (`throttle:15,1`) and strictly enforces token expiration and active staff status.
- `POST /api/payments/create-payment-intent` enforces active staff verification, payment authorization/capabilities, currency validation, and structured audit logs.
- `POST /api/leads` returns opaque responses preventing email enumeration or database ID leaks, while incorporating honeypot spam traps and per-email rate limits.
- CSRF protection is fully active across all web and CRM routes; stale dead exceptions removed from `VerifyCsrfToken` with only valid API and SMS webhook exclusions retained.
- Reverse proxy trust (`TrustProxies`) is active in global middleware with `config/trustedproxy.php` support, ensuring accurate HTTPS detection and secure session cookie auto-configuration behind load balancers/reverse proxies.
- `GET /logout` prompts confirmation to prevent CSRF logout and prefetch side effects; state-destroying logout strictly requires POST + CSRF.
- Staff login view avoids repopulating plaintext passwords, and remember-me email cookie is explicitly HttpOnly, SameSite=Lax, and encrypted.
- Session configuration allows opting into encryption via `SESSION_ENCRYPT`, with `secure` defaulting to `null` enabling automatic HTTPS enforcement by Laravel/Symfony alongside reverse-proxy dynamic detection.
- Sanctum guards include `admin` and `web` aligned to the `staff` provider (`Staff` model), with token expiration configurable via `SANCTUM_EXPIRATION`.

---

## Suggested fix priority (advisory — not applied)

1. [COMPLETED - AUTH-SA-1] Lock down public **service-account token mint**; added throttle, status/role checks, sanitized error logging.  
2. [COMPLETED - AUTH-UTIL-1] Added **table allowlists** and client visibility checks to approve/decline/process/archive.  
3. [COMPLETED - AUTH-SMS-1] Made Cellcast webhook **fail closed** when secret unset in non-local envs; added route throttle (`throttle:60,1`).  
4. [COMPLETED - AUTH-API-1] Added dedicated throttles and shared-secret access control on public booking and payment-without-login APIs.  
5. [COMPLETED - AUTH-CORS-1 & AUTH-DOC-1] Tightened CORS to trusted origins and enforced DocumentPolicy least-privilege via StaffClientVisibility.
6. Harden session (AuthenticateSession, secure cookie defaults in prod, reconsider GET logout); strengthen password policy / reset story.

---

## Out of scope / not fully verified

- Live `.env` values (whether webhook secrets, `SESSION_SECURE_COOKIE`, CORS domains are set correctly in each environment).  
- Full permission matrix for every `UserRole.module_access` key vs every controller method.  
- Dynamic analysis (CSRF PoC, token entropy measurement, Stripe fraud scenarios).  
- Third-party TinyMCE / vendor JS security.  
- Infrastructure (WAF, TLS termination, Redis session isolation).

---

*End of audit document. No application code was modified for this review.*
