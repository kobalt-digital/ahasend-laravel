# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.1 - 2026-11-17

### Changed

- The package is now called `kobaltdigital/ahasend-laravel`, without the hyphen.

## 1.0.0 - 2026-11-17

### Added

- `ahasend` mail transport for Laravel, built on the AhaSend API v2.
- `AhaSend` client and facade for the rest of the API, with retries and idempotency keys.
- Webhook route with signature verification and a Laravel event for every AhaSend event.
