# Roadmap

Panulat is a modular, lightweight PHP framework for building clean REST APIs and API-first applications.

The goal is to keep the core small and focused, while moving optional features into separate packages.

## Current Packages

* `blcklab/panulat-core` — framework core
* `blcklab/panulat` — starter API project
* `blcklab/panulat-jwt` — optional JWT authentication package
* `blcklab/panulat-cli` — optional developer CLI and scaffolding commands

## Planned Packages

Future packages may include:

* `blcklab/panulat-redis` — Redis cache and rate limiting support
* `blcklab/panulat-queue` — queue workers and background jobs
* `blcklab/panulat-testing` — testing helpers for API applications
* `blcklab/panulat-openapi` — OpenAPI documentation generation
* `blcklab/panulat-access` — roles, permissions, and policies
* `blcklab/panulat-orm` — optional ORM features
* `blcklab/panulat-psr` — PSR bridge package

## Package Direction

Panulat follows a simple package rule:

```txt
Core provides the foundation.
Optional packages add extra features.
The starter shows a recommended setup.
```

Features such as Redis, queues, OpenAPI documentation, advanced access control, and ORM relationships are kept outside the core so developers can install only what their projects need.

## Priority

The current priority is to keep the first release stable, simple, and easy to use.

After the first release, the next likely packages are:

```txt
1. panulat-redis
2. panulat-queue
3. panulat-testing
```

This roadmap may change based on real usage and feedback.
