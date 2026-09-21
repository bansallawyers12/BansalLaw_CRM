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
5. **Medium/High —** `DocumentPolicy` grants near-global document mutate/view to any authenticated user; Auth gates compare `user->id` to `client->id` in a confusing way.  
6. **Medium —** Session hardening incomplete (`AuthenticateSession` off, `HttpsProtocol` dead, session encrypt off, weak password min length, **GET logout**, no password-reset routes).

---

## Module inventory & status

### 1. Guards, providers, models

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| Auth config | `config/auth.php` | **Partial** | Default guard `admin` → provider `staff` (`Staff`) is correct for CRM login. Legacy `web`/`api` still use provider `admins` (`Admin`) while Sanctum tokens are issued on **Staff** — token auth works via Sanctum tokenable lookup, but guard/provider docs and config are inconsistent. |
| Staff model | `app/Models/Staff.php` | **OK** | `HasApiTokens`, password hidden, status/role helpers (`hasCrmModule`, `canAccessAdminConsole`, elevation helpers). Password not cast as `hashed` (manual `Hash::make` used — acceptable if consistent). |
| Admin model | `app/Models/Admin.php` | **Partial** | Clients/leads table model still `Authenticatable` + `HasApiTokens`; `password`/`id` fillable; `protected $guard = 'admin'` is misleading (admin guard uses Staff). |
| AuthServiceProvider | `app/Providers/AuthServiceProvider.php` | **At risk** | Gates `view`/`update` allow `$user->id === $client->id` (Staff id vs client/Admin id). Likely dead/legacy logic; risk if ever used as real authz. Only `DocumentPolicy` registered. |

### 2. Staff web login / logout

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| Login controller | `app/Http/Controllers/Auth/AdminLoginController.php` | **OK** | `guest:admin`; rate limit 5/min per email+IP; optional reCAPTCHA (non-local); `status => 1`; session regenerate on success; failed-login timing equalization; login/logout audit via `StaffLoginLog`. |
| Login routes | `routes/web.php` | **Partial** | `GET/POST /login`, `POST /logout` OK. **`GET /logout` also destroys session** (CSRF-logout / link-prefetch risk). |
| Login view | `resources/views/auth/admin-login.blade.php` | **OK** *(assumed)* | Used by controller; not deeply UI-audited. |
| Remember cookie | `AdminLoginController::authenticated` | **Low / Partial** | Queues plaintext `email` cookie on remember; forgets `password` cookie (good). Prefer not storing PII in non-HttpOnly custom cookies. |

**Password reset:** `config/auth.php` defines `passwords.staff` / `admins` (15 min), but **no forgot/reset routes or controllers** found under `routes/`. Status: **Fail** for “self-service reset”; **OK** if intentional (admin-only reset only — not verified in Admin Console for this audit).

### 3. HTTP middleware stack

| Middleware | Alias / group | Status | Notes |
|------------|---------------|--------|-------|
| `Authenticate` | `auth` | **OK** | Redirects to `crm.login`; API/MCP paths return null (no HTML redirect). |
| `RedirectIfAuthenticated` | `guest` | **OK** | `guest:admin` → dashboard; allows `?tab_logout`. |
| `VerifyCsrfToken` | `web` | **Partial** | CSRF on for web. Except: `api/*`, `webhooks/sms/*`, and **stale `admin/*` task/visit paths** that no longer match current `/update_visit_*` routes (dead exceptions — low risk). |
| `SetSecureSessionCookies` | `web` | **OK** | Auto-sets `session.secure` when HTTPS / proxy headers detected; reads `config()`, not `env()`. |
| `EncryptCookies` | `web` | **OK** | Empty except list. |
| `EnsureAdminConsoleAccess` | `adminconsole` | **OK** | Role allowlist + elevation + narrow mailbox-sync exception. |
| `SetAdminGuardFromSanctumUser` | `mcp.admin.guard` | **OK** | Mirrors Sanctum Staff onto `admin` guard for CRM visibility. |
| `VerifyMigrationCrmToken` | `migration.crm.token` | **OK** | Fail closed if token missing; `hash_equals`; logging. |
| `EnsureCommunicationCheckEnabled` | `communication.check` | **OK** | Feature flag + staff capability. |
| `HttpsProtocol` | *(commented out in Kernel)* | **Fail** | Class body is commented empty; not enforcing HTTPS redirect. Rely on load balancer + secure cookies instead. |
| `AuthenticateSession` | *(commented in Kernel web group)* | **At risk** | Disabled — stolen session cookie not invalidated on password change elsewhere until natural expiry (logout after change_password does flush). |
| TrustProxies | global | **Partial** | Uses framework `TrustProxies`; custom app class exists but unused. Proxy trust must be correct for secure-cookie auto-detect. |

### 4. Session / cookie / CORS config

| Config | Path | Status | Notes |
|--------|------|--------|-------|
| Session | `config/session.php` | **Partial** | Driver default redis; lifetime 30; `http_only` true; `same_site` lax; **`encrypt` false**; **`secure` default false** (mitigated by middleware when HTTPS detected). |
| Sanctum | `config/sanctum.php` | **Partial** | 7-day token expiry; `guard => ['web']` (Admin provider) while MCP/service tokens are Staff. |
| CORS | `config/cors.php` | **At risk** | `allowed_origins => ['*']` with comment to tighten for production; `supports_credentials` false. |

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
| Document policy | `app/Policies/DocumentPolicy.php` | **At risk** | `view`/`update`/`delete`/`void`/`associate`/`sendReminder` effectively **true for any authenticated user** (only create requires Staff; delete/void block signed). Firm-wide staff access may be intentional — still weak least-privilege. |

### 7. Generic CRM utility mutations

| Endpoint (auth:admin) | Method | Status | Notes |
|-----------------------|--------|--------|-------|
| `/update_action` | `updateAction` | **Partial** | Table/column allowlists + Admin Console check for system tables + client visibility for client tables. |
| `/delete_action` | `deleteAction` | **Partial** | Allowlist + some per-table rules; still powerful. |
| `/approved_action`, `/declined_action`, `/process_action`, `/archive_action` | approve/decline/process/archive | **OK** (Fixed) | Strict table allowlist enforced (`systemTables` and `clientTables`). Admin Console privileges required for system tables; row-level client check (`ensureCrmRecordAccess`) enforced for client tables. Arbitrary table mutation blocked. |
| `/change_password` | `change_password` | **Partial** | Requires old password; min length **6**; logs out after change; uses `Auth::user()` (OK with default admin guard). |

### 8. Public token auth (no login)

| Flow | Path | Status | Notes |
|------|------|--------|-------|
| E-sign page | `GET /sign/{id}/{token}` | **OK** | Token format + DB match; signed/cancelled handled. |
| Submit signatures | `POST /documents/{id}/sign` | **OK** | Requires token; verifies signer belongs to document. |
| Page / download | public document helpers | **Partial** | Token **or** logged-in admin guard; token compared via query existence (OK if tokens high-entropy). |
| Public send-reminder | `POST /documents/{document}/send-reminder` | **Partial** | Token required when not staff; can be abused for reminder spam if token leaked. |
| Email verify | `GET /verify-email/{token}` | **OK** | Public by design; service validates token. |
| README claim “HMAC token” | README | **Partial** | Implementation uses **stored signer token string match**, not HMAC of document id (doc wording overstates). |

### 9. API / Sanctum / MCP / payments

| Surface | Path | Status | Notes |
|---------|------|--------|-------|
| Service account token mint | `POST /api/service-account/generate-token` | **OK** (Fixed) | Route throttle (`throttle:5,1`) + in-controller rate limit (5/min per email+IP); active-status check (`status=1`); Admin Console / elevated role requirement; timing attack mitigation; password removed from error logs. |
| Service account authenticate | controller method | **Partial** | Exists on controller; **not registered** in `routes/api.php` (dead unless called elsewhere). |
| Stripe PaymentIntent | `POST /api/payments/create-payment-intent` | **Partial** | Behind `auth:sanctum` + throttle 6/min — OK if only trusted tokens exist; weak if any staff can mint tokens (see above). |
| Public leads | `POST /api/leads` | **Partial** | Throttle 5/min — intentional public intake. |
| Migration CRM leads | `POST /api/migration-crm/leads` | **OK** | Token middleware + dedicated rate limiter. |
| Booking / appointments / payments without login | `routes/api.php` public posts | **OK** (Fixed) | Protected by `VerifyBookingApiAccess` shared-secret check (when `BOOKING_SHARED_SECRET` is set) + dedicated route throttles (`throttle:10,1` for appointments & payment mutations, `throttle:30,1` for calendar/availability queries). |
| MCP | `routes/ai.php` | **OK** | `auth:sanctum` + guard mirror + throttle 60/min. |
| Countries listing | `GET /api/countries` | **OK** | Public catalogue. |

### 10. SMS / verification / webhooks

| Component | Path | Status | Notes |
|-----------|------|--------|-------|
| SMS webhooks | `routes/sms.php` + `SmsWebhookController` | **OK** (Fixed) | Fail-closed signature verification enforced when secret is unset or invalid in non-local environments (`CELLCAST_WEBHOOK_SECRET` / `CELLCAST_API_KEY`); testing bypass removed; dedicated `throttle:60,1` applied to webhook route group. CSRF excepted (expected for webhooks). |
| Phone OTP | `PhoneVerificationService` | **Partial** | Attempt limits / expiry / rate helpers present; OTP compared with `!==` (prefer `hash_equals`); storage of plaintext OTP likely (not fully traced). Staff-gated controllers expected. |
| Contact verification tests | `tests/Feature/ContactVerificationTest.php` | **OK** | Coverage for OTP expiry, rate limit, resend supersede. |

### 11. Related security-adjacent modules (in scope)

| Module | Status | Notes |
|--------|--------|-------|
| Login analytics / `StaffLoginLog` | **OK** | Success/fail/logout recorded. |
| User roles Admin Console | **Partial** | Protected by `adminconsole`; permission model is JSON `module_access` (complex; not fully audited per permission edge). |
| Front-desk / office visits | **OK** (auth) | Inside `auth:admin`; CSRF exceptions for old `admin/*` paths appear stale. |
| Health `/up` | **OK** | Unauthenticated by design. |

---

## Finding register (do not fix in this pass)

| ID | Severity | Area | Status | Finding |
|----|----------|------|--------|---------|
| AUTH-SA-1 | **Critical** | API Sanctum | **OK** (Fixed) | Resolved: Enforced route + controller rate limiting (5/min), inactive staff rejection (`status === 1`), Admin Console / elevated role requirement, timing attack mitigation, and credential-sanitized logging. |
| AUTH-UTIL-1 | **High** | CRMUtility | **OK** (Fixed) | Resolved: Enforced strict table allowlists (`systemTables` + `clientTables`), column existence validation, Admin Console authorization for system tables, and row-level client visibility checks. |
| AUTH-SMS-1 | **High** | Webhooks | **OK** (Fixed) | Resolved: Enforced fail-closed authentication when secret is unset or invalid in non-local environments; removed test bypass; added dedicated `throttle:60,1` to webhook routes. |
| AUTH-API-1 | **High** | Public booking API | **OK** (Fixed) | Resolved: Enforced `VerifyBookingApiAccess` middleware for shared-secret authorization (`BOOKING_SHARED_SECRET`), dedicated `throttle:10,1` on booking and payment endpoints, and `throttle:30,1` on calendar availability queries. |
| AUTH-CORS-1 | **Medium** | CORS | At risk | `allowed_origins = *` for `api/*`. |
| AUTH-DOC-1 | **Medium** | DocumentPolicy | At risk | Global document view/update/delete/void for any authenticated user. |
| AUTH-GATE-1 | **Medium** | AuthServiceProvider | At risk | `view`/`update` gates compare staff id to client id. |
| AUTH-SESS-1 | **Medium** | Session | At risk | `AuthenticateSession` disabled; session encryption off; HTTPS middleware dead. |
| AUTH-LOGOUT-1 | **Medium** | Logout | Partial | `GET /logout` enables CSRF logout / prefetch side effects. |
| AUTH-PWD-1 | **Medium** | Passwords | Partial | Min length 6; no self-service reset routes despite password broker config. |
| AUTH-CFG-1 | **Low** | Guards | Partial | `api`/`web` providers still `Admin` while CRM tokens are `Staff`; Admin model still auth-shaped. |
| AUTH-CSRF-1 | **Low** | CSRF except | Partial | Stale `admin/update_*` exceptions do not match current route paths. |
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

---

## Suggested fix priority (advisory — not applied)

1. [COMPLETED - AUTH-SA-1] Lock down public **service-account token mint**; added throttle, status/role checks, sanitized error logging.  
2. [COMPLETED - AUTH-UTIL-1] Added **table allowlists** and client visibility checks to approve/decline/process/archive.  
3. [COMPLETED - AUTH-SMS-1] Made Cellcast webhook **fail closed** when secret unset in non-local envs; added route throttle (`throttle:60,1`).  
4. [COMPLETED - AUTH-API-1] Added dedicated throttles and shared-secret access control on public booking and payment-without-login APIs.  
5. Revisit DocumentPolicy least-privilege; fix or remove misleading Auth gates.  
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
