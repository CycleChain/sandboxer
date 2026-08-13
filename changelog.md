# Changelog

All notable changes to `Sandboxer` will be documented in this file.

## Version 0.2.1

### Fixed
- **Query & Read Isolation**: Integrated `SandboxScope` and `afterQuery` state transformation in `StorageManager` to correctly reflect sandboxed `INSERT`, `UPDATE`, and `DELETE` operations on query read operations.
- **Eloquent `save()` Contract**: Resolved model save cancellation issues by utilizing database connection pretend mode during model creation, updating, and deletion event handling.
- **Auto-Detection Domains**: Fixed domain and subdomain wildcard auto-detection in `SandboxManager` (`demo.*.com`, `sandbox.*.com`).
- **Fake ID Preservation**: Fixed primary key assignment for newly created sandboxed records across numeric and string key types.
- **Default Configuration**: Updated `excluded_tables` in `config/sandboxer.php` to prevent system tables (`sandbox_sessions`, `sandbox_storage`) from intercepting themselves.

### Added
- Comprehensive Unit & Feature test suite (`13 tests, 45 assertions`) covering `SandboxManager`, `StorageManager`, `SandboxAuthHelper`, `SandboxCleanupJob`, and end-to-end `SandboxIsolation`.
