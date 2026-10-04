# Security Policy

## Reporting a vulnerability

Please report security issues privately by emailing the project maintainer at security@example.com or by opening a private report through the repository security tab.

Do not disclose details publicly until a fix is available and the issue has been assessed.

## Supported versions

The project is currently maintained on the main branch and the active hardening branch for release validation.

## Disclosure expectations

- Share the root cause, impact, and reproduction steps.
- Avoid exploiting or exfiltrating data beyond what is necessary to validate the issue.
- Allow a reasonable remediation window before public disclosure.

## Security posture

This project keeps the runtime configuration in environment variables, rejects unsafe redirects, validates JWTs and cookies, enforces same-origin CSRF checks, and runs CI with secret scanning and dependency review.
