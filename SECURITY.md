# Security Policy

## Supported Versions

Only the latest major version of Fylgja Props Builder for PHP receives security updates.

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| < 2.0   | :x:                |

The version numbers follow the [Node version](https://www.npmjs.com/package/@fylgja/props-builder) of the Props Builder.

## Reporting a Vulnerability

If you discover a security vulnerability, please **do not** open a public GitHub issue.

Instead, report it privately via [GitHub Security Advisories](https://github.com/fylgja/php-props-builder/security/advisories/new).

We'll acknowledge your report as soon as possible and keep you updated as the issue is investigated and resolved. Once a fix is released, we'll credit you in the advisory unless you prefer to remain anonymous.

If the issue also affects the Node version, please mention it in the report, so both can be fixed together.

## Scope

The output is not escaped for HTML, by design, since it is also written to CSS, SCSS and JSON files.
Escaping token values from user input before printing them in a page is up to the application, see [Direct output](README.md#direct-output).
A report about a value breaking out of a `<style>` tag is only in scope when the escaping shown in the README does not prevent it.
