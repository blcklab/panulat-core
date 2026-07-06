# Changelog

## 0.1.0 - Initial Release

First public release of Panulat Core.

Panulat Core provides the modular foundation for building lightweight PHP REST APIs. This release includes the essential framework pieces needed to create structured, production-ready API applications while keeping the core small and focused.

### Added

* HTTP request and response handling
* Routing
* Middleware pipeline
* Dependency injection container
* Application kernel
* Configuration and environment loading
* Error handling
* Validation
* PDO database layer
* Query builder
* Migrations and seeders
* API-key middleware
* Basic authentication primitives
* CORS support
* Rate limiting
* Resources and pagination
* Cache support
* Event support
* Console command foundation
* Locale support
* Lightweight framework helpers

### Improved

* Production-safe error handling
* Safer CORS credentials handling
* Secure API-key comparison
* Better client IP handling
* Fresh controller resolution per request
* Lazy request body parsing
* Cleaner Composer package metadata

### Request Support

Added support for common API request formats:

* JSON request bodies
* URL-encoded form bodies
* Multipart form uploads
* Lightweight uploaded file objects

Panulat Core supports file uploads without bundling a media library or storage abstraction, keeping uploads useful while preserving the lightweight core.

### Response Helpers

Added small response helpers for common API responses:

* `Response::text()`
* `Response::noContent()`

### Modular Packages

JWT authentication is kept outside the core and is available through the optional `blcklab/panulat-jwt` package.

Developer scaffolding commands are also kept outside the core and are available through the optional `blcklab/panulat-cli` package.
