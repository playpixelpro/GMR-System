# Security Audit Report

## Executive summary

This was a source-code and configuration review of the repository at commit
`c4797568a93673d4bbb2cf20ffc3e5001ed7c8b2` on branch `dev`. It was not a
penetration test or a review of a deployed environment.

The codebase has useful security controls, including authenticated route groups,
role-based gates, password hashing, CSRF-protected web routes, and input
validation. However, the review found a **high-risk default administrator
credential** and several areas that need hardening or deployment verification.
The system should not be treated as production-ready until the default
administrator credential and the other high-priority items below have been
addressed and verified.

No security issues were fixed as part of this review. This report is the only
file created.

## Findings

### 1. High — Known fallback administrator password can be seeded

**Evidence:** `config/nfa.php:19-22`; `database/seeders/DatabaseSeeder.php:21-47`

The administrator seeder uses `ChangeMe!2026` when `NFA_ADMIN_PASSWORD` is not
configured. It also uses `admin@email.com` as the fallback email. On a fresh
seed, these values are used to create an active administrator account. The
generated account is marked as requiring a password change, but that does not
prevent an attacker who knows the fallback credentials from attempting to log
in.

**Recommendation:** Remove usable credential defaults from production seeding.
Require an explicitly supplied, unique initial administrator secret and fail
closed when it is absent. Verify that production secrets are configured before
running seeders, and rotate the administrator credential if this fallback may
ever have been used.

### 2. Medium — Temporary passwords are included in responses and session flash data

**Evidence:** `app/Http/Controllers/UserController.php:75-91` and
`app/Http/Controllers/UserController.php:140-154`

User creation and password resets put the temporary password in a status
message and in `credentials_modal` flash data. The users page then renders the
temporary password in the browser. This is convenient for administrators, but
it creates additional places where a credential can be exposed, including the
administrator's browser session, rendered page, screenshots, or a shared
workstation. The same values are also supplied when email delivery fails.

**Recommendation:** Avoid including credentials in general status text or
retaining them in session flash data. Use a narrowly scoped, short-lived
credential handoff with explicit no-store response handling and clear the
credential immediately after display; prefer a one-time password setup link
where feasible. Review operational practices for administrators who may have
already used this flow.

### 3. Medium — Administrator-set temporary passwords can be as short as eight characters

**Evidence:** `app/Http/Controllers/UserController.php:37-47` and
`app/Http/Controllers/UserController.php:106-108`

The administrator's create-user and reset-password inputs allow a caller-chosen
password with a minimum length of eight characters. By comparison, the
self-service password-change and reset flows require at least twelve
characters. This creates inconsistent password strength requirements for
temporary credentials.

**Recommendation:** Align administrator-supplied temporary password rules with
the stronger password policy, and prefer generated, high-entropy temporary
credentials or one-time setup links over administrator-selected passwords.

### 4. Medium — Public registration has no visible application-level rate limit

**Evidence:** `routes/web.php:32-39`; `app/Http/Controllers/AuthController.php:33-63`

Registration is publicly reachable and creates a user record and attempts to
send a temporary-password notification. No route throttle or registration
limiter was identified in the route or controller. Repeated automated requests
could create unwanted pending accounts, consume database resources, or abuse
outbound email delivery.

**Recommendation:** Apply abuse controls appropriate to registration (such as
rate limits and monitoring), and establish a policy for pending or unconfirmed
accounts. Verify outbound mail quotas and alerting.

### 5. Medium — Production cookie and debug settings depend on deployment overrides

**Evidence:** `.env.example:1-5`, `.env.example:28-32`,
`config/session.php:172-185`, `config/session.php:202`

The sample environment file sets `APP_DEBUG=true`, while no
`SESSION_SECURE_COOKIE` value is set. The session configuration reads that
value without a secure-by-default fallback, so whether cookies are restricted
to HTTPS depends on the deployed environment and framework behavior. These
settings can be appropriate for local development, but copying the sample
without overriding it for production can expose sensitive error details or
weaken session-cookie transport protection.

**Recommendation:** Enforce `APP_DEBUG=false` and HTTPS-only session cookies in
production configuration. Confirm the effective, cached production
configuration and actual `Set-Cookie` headers over HTTPS rather than relying
only on the checked-in sample.

### 6. Medium — No application-level security-header policy was identified

**Evidence:** `bootstrap/app.php:9-25`

The inspected application bootstrap adds an application middleware alias but
does not register custom response security headers. A repository search did
not identify an application policy for Content Security Policy, HSTS,
`X-Content-Type-Options`, or clickjacking protection. A reverse proxy or
hosting platform may add these headers; that infrastructure was not available
for this review.

**Recommendation:** Define and verify an appropriate security-header policy at
the application or trusted edge, including a progressively enforced CSP if
compatible with the inline scripts currently used in Blade views. Avoid
duplicating or conflicting policies between layers.

## Existing controls observed

- Protected application routes are grouped behind `auth` and
  `password.changed`; administrative and role-sensitive route groups use
  authorization gates in `routes/web.php`.
- Role gates are explicitly defined in `app/Providers/AppServiceProvider.php`.
- Passwords are hashed through the `hashed` model cast in
  `app/Models/User.php`; password and remember-token fields are hidden from
  serialization.
- Password-reset completion clears the remember token and uses Laravel's
  password broker in `app/Http/Controllers/AuthController.php:215-249`.
- Profile image uploads are limited to image MIME types and size in
  `app/Http/Controllers/UserController.php:171-181`.
- Login and password-reset submissions have a custom IP-based lockout in
  `app/Support/AuthThrottle.php` and `app/Http/Controllers/AuthController.php`.
  This is useful abuse protection, although IP-wide lockouts can also affect
  other users behind the same network.
- `.env` is not tracked; only `.env.example` is tracked.

These observations are not a guarantee that each control is correct in every
deployment or fully covered by tests.

## Review scope and limitations

The review inspected the Laravel routes, selected authentication and user
management code, application bootstrap, session/auth configuration, the
administrator seeder, and the tracked environment example. It did not inspect
a live deployment, reverse-proxy configuration, database contents, production
secrets, infrastructure permissions, or runtime HTTP response headers.

Composer dependencies were not installed in the worktree, so `composer audit`
and the automated test suite could not be run. Dependency vulnerabilities,
runtime behavior, concurrency behavior, and any controls provided only by
hosting infrastructure remain unverified.

## Remediation follow-up

The following changes were made on branch `security/harden-audit-findings` in
response to findings 1–6 above. The application workflow remains the same where
possible; security validation and response behavior are stricter.

1. **High — Default administrator credentials:** Removed the fallback email and
   password from `config/nfa.php`. Initial seeding now fails with an actionable
   error if no administrator exists and either credential is missing. Seeding
   an installation that already has an administrator leaves that account
   unchanged when credentials are omitted. A configured initial password must
   have at least 16 characters. The setup requirement is documented in
   `README.md`.
2. **Medium — Temporary-password exposure:** Removed temporary passwords from
   status messages and added `Cache-Control: private, no-store, max-age=0` to
   the administrator users page. The existing one-time credentials modal is
   retained to preserve the administrator workflow, so the password is still
   intentionally rendered to the authorized administrator once. This reduces
   incidental exposure but does not replace a future one-time setup-link
   workflow.
3. **Medium — Weak administrator-supplied temporary passwords:** User creation
   and password reset now require at least 12 characters when an administrator
   supplies a temporary password. Generated passwords and the first-login
   password-change flow are unchanged.
4. **Medium — Unthrottled public registration:** The registration POST route is
   now limited to five attempts per ten minutes per client IP. Confirm the deployed
   trusted-proxy configuration so rate limits use the real client address when
   the application is behind a reverse proxy.
5. **Medium — Debug and session-cookie defaults:** The tracked environment
   example now sets `APP_DEBUG=false`. Session cookies default to HTTPS-only
   when `APP_ENV=production`, unless explicitly overridden. Production should
   not set `SESSION_SECURE_COOKIE=false`.
6. **Medium — Response security headers:** Added global `X-Content-Type-Options`,
   `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and a narrowly
   scoped Content Security Policy restricting base URLs, embedded objects,
   and framing. HSTS is added only when Laravel sees the request as HTTPS.
   Verify HTTPS detection through the actual production proxy and inspect
   deployed response headers before relying on HSTS.

Feature tests were added for missing seeder credentials, registration
throttling, password-length validation, no-store behavior, and security
headers. The targeted tests completed without failures (182 assertions); they
reported environment warnings because this worktree has no `.env` file. The
full test suite reported one failure in
`tests/Feature/DataEntryTest.php:364` (an expected rendered label was not
found), 283 environment warnings, and 1,498 assertions. That failure is outside
the files changed for these remediations and remains unresolved.

`composer audit --locked` found no security advisories in the PHP lock file;
`npm audit` found zero JavaScript vulnerabilities. Deployment-level
proxy/header verification and a live security test remain outstanding.
Findings 2, 4, 5, and 6 require the noted operational verification or retain a
deliberately preserved behavior; do not consider them fully verified solely
from this source change.
