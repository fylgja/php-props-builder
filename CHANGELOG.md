# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This package is a port of the Node version, and each release has the same version number as the Node release it matches.
For the features and fixes in a release, see the [Node changelog](https://github.com/fylgja/fylgja/blob/main/props-builder/CHANGELOG.md).
This changelog only lists the changes that are specific to the PHP version.

## Unreleased

### Added

- PHP port of the Props Builder, matching the Node version 2.2.0 and its Unreleased changes (Design Tokens 2025.10).
- An `onWarning` option to handle warnings, such as skipped `dtcg` tokens. Defaults to `error_log()`.
- `build()` returns the generated output, also when it writes the file.
