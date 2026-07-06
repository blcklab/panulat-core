# Security Policy

## Supported Versions

Panulat currently supports the latest tagged release.

Because Panulat is still in early development, users are encouraged to upgrade to the latest available version for security fixes and improvements.

## Reporting a Vulnerability

Please report suspected security issues privately.

Do not open a public GitHub issue with exploit details, sensitive logs, credentials, tokens, or reproduction steps that could put users at risk.

To report a vulnerability, contact the maintainer directly.

Please include:

* Affected package, such as `blcklab/panulat-core`, `blcklab/panulat`, `blcklab/panulat-jwt`, or `blcklab/panulat-cli`
* Affected version or commit
* Clear reproduction steps
* Expected and actual behavior
* Potential impact
* Suggested fix, if known

## Production Safety

For production applications, use:

```env
APP_ENV=production
APP_DEBUG=false
```

Panulat is designed to avoid unsafe debug behavior in production.

In production, errors are returned as safe JSON responses without stack traces.

## Responsible Disclosure

Please give the maintainer reasonable time to review and fix confirmed security issues before sharing details publicly.
