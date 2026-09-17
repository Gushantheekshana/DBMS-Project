# V3 testing

Run commands from the repository root unless a step says otherwise. The examples use the XAMPP PHP executable.

## Deterministic frontend build

Install exactly the locked dependencies and build from `v3/`:

```sh
cd v3 && npm ci
cd v3 && npm run build
cd v3 && npm run check:js
```

Run `npm run build` twice and compare hashes for these committed artifacts:

- `v3/assets/css/app.css`
- `v3/assets/icons/lucide.svg`
- `v3/assets/vendor/flowbite/flowbite.min.js`

The hashes must be unchanged between builds. Generated assets must contain no remote frontend dependency URLs, source-map trailer, environment values, credentials, or Bootstrap runtime/style references.

## PHP and contract checks

Lint every V3 PHP file and run:

```sh
C:/xampp/php/php.exe v3/tests/frontend_contract.php
C:/xampp/php/php.exe v3/tests/runtime_probe.php
C:/xampp/php/php.exe v2/tests/schema_contract.php
C:/xampp/php/php.exe v2/tests/trainer_application_workflow.php
```

The runtime probe must report frontend `v3`, a V3 base URL and session name, the existing V2 backend root and `AuthService` file, and the same V2 database name. The source scan must show no Bootstrap asset paths, `data-bs-*` hooks, Bootstrap JavaScript API usage, copied backend/schema directories, hardcoded V2 presentation links, or remote frontend dependencies.

## Browser verification

Use the configured PHP browser preview and verify:

- Public landing, login, member registration, and trainer registration pages.
- Representative dashboard, table, filter, form, status, error, success, and empty states for ADMIN, RECEPTIONIST, TRAINER, and MEMBER.
- Light, Dark, and System theme application before paint, persistence across reload and authentication, and live operating-system changes while System is selected.
- Desktop, tablet, and mobile layouts; mobile drawer controls and Escape dismissal; narrow table overflow; long content; and 200% zoom.
- Appearance dropdown, notification dismissal, password visibility, duplicate-submit locking, `pageshow` reset, visible focus, labels, live regions, keyboard behavior, dialog focus restoration, and reduced motion.
- Native confirmation dialogs only for trainer application review, class review, and enrollment decisions.
- No fresh console errors, failed application requests, missing assets, or PHP server errors.

Exercise the real workflows end to end: login/logout; trainer registration, pending denial, rejection, resubmission, and approval; trainer class creation and administrator review; member enrollment and trainer decision; attendance; membership selection and checkout; and reception check-in/out. A safe mutation completed through V3 must be visible in V2 after signing in separately, proving both versions use the same backend and database.

## Web-access protection

These paths must return 403 or 404:

- `/v3/bootstrap/`
- `/v3/config/`
- `/v3/tests/`
- `/v3/docs/`
- `/v3/scripts/`
- `/v3/node_modules/`
- `/v3/package.json`
- `/v3/package-lock.json`
- `/v3/.env`

Ordinary routes and browser assets must continue to load normally with the configured security headers.
