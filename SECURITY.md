# Security Policy

## Supported Versions

Panulat currently supports the latest tagged `0.x` release line.

Security fixes are applied to the latest available release unless noted otherwise.

## Reporting a Vulnerability

Please report suspected security vulnerabilities privately.

Do not open a public GitHub issue that includes exploit details, sensitive logs, credentials, or reproduction steps that could put users at risk.

To report a vulnerability, contact the project maintainer directly.

Please include as much detail as possible:

* Affected package, such as `blcklab/panulat-core`, `blcklab/panulat`, or `blcklab/panulat-jwt`
* Affected version or commit
* Clear reproduction steps
* Expected and actual behavior
* Potential impact
* Suggested mitigation, if known

## Production Safety

For production applications, use:

```env
APP_ENV=production
APP_DEBUG=false
```

Panulat is designed to avoid unsafe debug behavior in production.

In production, errors are returned as safe JSON responses without stack traces.

## Security Scope

Security reports may include issues related to:

* Request handling
* Middleware behavior
* Authentication or authorization primitives
* API-key handling
* CORS behavior
* Rate limiting
* Error rendering
* Database query handling
* File upload handling

## Responsible Disclosure

Please give the maintainer reasonable time to review and fix confirmed security issues before sharing details publicly.
