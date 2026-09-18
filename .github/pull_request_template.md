## Summary

<!-- Explain the problem and the change. Keep this concise and link the related issue, for example: Closes #123. -->

## Affected layer

- [ ] V3 frontend
- [ ] V2 application or backend
- [ ] Database or migration
- [ ] Earlier root application
- [ ] Documentation or tooling

## Database impact

<!-- Describe migrations, data compatibility, rollback considerations, or state "None". -->

## Verification

<!-- List the exact commands and manual scenarios you ran, with results. -->

- [ ] Relevant PHP files pass syntax checks
- [ ] PHPUnit tests pass (`composer test`)
- [ ] V2 schema/workflow tests pass where applicable
- [ ] V3 frontend contracts and JavaScript checks pass where applicable
- [ ] I documented any check that could not run

## User interface evidence

<!-- For visible changes, add before/after screenshots or recordings with no personal or sensitive data. Delete this section if it does not apply. -->

- [ ] Tested relevant desktop and mobile layouts
- [ ] Tested keyboard operation and visible focus
- [ ] Checked light/dark themes where applicable
- [ ] Checked reduced-motion behavior where applicable

## Checklist

- [ ] The change is focused and does not include unrelated formatting or generated-file churn.
- [ ] Tests cover new or changed behavior.
- [ ] Documentation is updated.
- [ ] Schema changes use an ordered migration and update schema documentation/tests.
- [ ] V3 source assets and committed build output are both updated where applicable.
- [ ] Authorization, CSRF, escaping, uploads, sessions, and audit history were considered where relevant.
- [ ] No `.env`, credentials, secrets, real personal/payment data, logs, database dumps, or uploaded documents are included.
- [ ] I followed [CONTRIBUTING.md](/CONTRIBUTING.md) and the [Code of Conduct](/CODE_OF_CONDUCT.md).
