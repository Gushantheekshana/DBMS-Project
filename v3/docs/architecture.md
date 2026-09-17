# V3 architecture

V3 is a separate presentation layer over the canonical V2 backend.

## Ownership boundary

- V2 owns the database schema, migrations, demo seeder, configuration defaults, and the `GymPro\V2` service layer.
- V3 owns browser routes, templates, theme state, frontend behavior, and committed browser assets.
- Both versions use the database configured through `v2/.env`; V3 does not copy backend, database, or service code.
- V3 uses `V3_APP_URL` (default `http://localhost/gym_system/v3`) and `V3_SESSION_NAME` (default `gym_v3_session`). Separate cookies allow V2 and V3 to be tested or rolled back independently.
- Schema changes remain in `v2/database/migrations` and demo records remain owned by `v2/cli/seed-demo.php`.
- V3 can be removed or disabled without a database downgrade. V2 remains independently available.

## Frontend stack

The browser interface uses locally built and served assets only:

- Tailwind CSS 4.3.3 provides the utility engine and design tokens.
- Flowbite 4.0.2 owns the authenticated appearance dropdown. GymPro JavaScript continues to own mobile navigation, theme persistence, notifications, password visibility, submit locking, and native confirmation dialogs so component ownership does not overlap.
- Lucide Static 1.47.0 provides the approved icon sources. `scripts/build-icons.mjs` generates a small symbol sprite, `includes/icons.php` limits icons to a strict symbolic-name allowlist, and the shared header embeds the sprite into each document.
- Vanilla JavaScript in `assets/js/app.js` supplies application interactions. V3 does not use a client-side framework.
- System UI fonts avoid a remote font dependency.

Exact dependency versions are pinned in `package.json` and `package-lock.json`. Node and npm are build-time tools only. Apache/PHP serves the committed, minified `assets/css/app.css`, local Flowbite runtime, and generated icon sprite in production.

Build the production assets from `v3/` with:

```sh
npm ci
npm run build
```

The source design system is `assets/css/input.css`. It defines the slate and emerald tokens, light/dark variants, shared shell, controls, forms, panels, compact tables, status treatments, alerts, dialogs, responsive behavior, and reduced-motion behavior. Semantic component classes keep server-rendered route templates readable while Tailwind remains the underlying CSS system.

Bootstrap is not loaded or retained by V3. Remote Tailwind, Flowbite, Lucide, font, and general CDN dependencies are prohibited.

## Theme and interaction behavior

Theme preference is stored as `gympro-v3-theme` with `light`, `dark`, and `system` values. A guarded inline initializer applies the resolved theme before the stylesheet paints, while `assets/js/app.js` keeps controls, `color-scheme`, and the theme-color metadata synchronized. System mode follows operating-system changes.

Consequential confirmations remain native `<dialog>` elements and are limited to trainer application review, class review, and enrollment decisions. Routine mutations remain confirmation-free. Interaction transitions are short and disabled or collapsed for `prefers-reduced-motion: reduce`.

## Deployment protection

`v3/.htaccess` denies direct access to bootstrap, configuration, tests, documentation, build scripts, `node_modules`, package manifests, environment files, and related private metadata. Production deployment needs the PHP sources and committed browser assets, but not `node_modules`.
