# Security Policy

## Supported versions

Security fixes are focused on the current V3 presentation layer and the V2 backend, database, and services it uses. The earlier root implementation is retained for reference and may not receive equivalent fixes.

| Application | Security support |
|---|---|
| V3 frontend with V2 backend | Supported |
| V2 application | Supported |
| Earlier root application | Best effort |

## Reporting a vulnerability

Do not open a public issue for an undisclosed vulnerability.

Use this repository's **Security** tab and select **Report a vulnerability** to open a private GitHub Security Advisory with the maintainers:

<https://github.com/Gushantheekshana/DBMS-Project/security/advisories/new>

Include, when possible:

- the affected version, branch, commit, route, or component;
- the vulnerability's impact and the conditions required to trigger it;
- clear reproduction steps or a minimal proof of concept;
- sanitized logs, requests, responses, or screenshots;
- a suggested mitigation, if known.

Never include real credentials, personal information, payment information, production database content, or another person's data in a report. Use fictional values and the reserved `example.test` domain.

The maintainers will assess the report through the private advisory, request clarification when needed, and coordinate remediation and disclosure based on severity and available project resources. Please allow a reasonable opportunity to investigate before public disclosure.

## Scope

Relevant reports include vulnerabilities in:

- authentication, sessions, authorization, and role boundaries;
- CSRF protection, output escaping, and request validation;
- database queries, constraints, transactions, migrations, and data integrity;
- trainer qualification uploads and file handling;
- V2 services exposed through V3 routes;
- bundled frontend assets and dependencies;
- accidental exposure of secrets or sensitive data.

General bugs, feature requests, and setup questions should use the public issue templates instead.

## Deployment responsibility

GymPro is an educational project and has not undergone an independent security audit. Before deployment:

- use HTTPS and set secure session cookies;
- set `APP_ENV=production` and disable debug output;
- generate a private application key and protect all environment values;
- use a least-privilege database account and restrict private application paths;
- keep PHP, the database server, Composer dependencies, and frontend dependencies patched;
- do not use the demo credentials or run the demo seeder;
- review upload storage, backups, logging, and retention for the deployment environment.
