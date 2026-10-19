# Security Policy

## Reporting a vulnerability

Please report security issues privately through the repository's Security tab. Do not
include credentials, personal data, or production database contents in a report.

Do not disclose details publicly until a fix is available and the issue has been assessed.

## Supported versions

The `main` branch is the maintained release line. Changes under review are not
supported releases until merged.

## Disclosure expectations

- Share the root cause, impact, and reproduction steps.
- Avoid exploiting or exfiltrating data beyond what is necessary to validate the issue.
- Allow a reasonable remediation window before public disclosure.

## Security posture

This project keeps the runtime configuration in environment variables, rejects unsafe redirects, validates JWTs and cookies, enforces same-origin CSRF checks, and runs CI with secret scanning and dependency review.
