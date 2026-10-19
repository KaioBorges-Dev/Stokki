# Security Policy

## Supported Versions

We take the security of **Stokki** seriously. Currently, only the latest stable release receives security updates and patches. If a critical vulnerability is found, it will be backported to the current major version as soon as possible.

| Version | Supported          |
| ------- | ------------------ |
| v1.0.x  | :white_check_mark: |
| < v1.0  | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability within the Stokki ecosystem (including the Laravel backend, Flutter application, or database configurations), please report it responsibly by following these steps:

1. **Email Us Directly:** Send a detailed report to security@stockki.io or contact the maintainer directly through **://kaioborges.com.br**.
2. **Include Technical Details:** To help us understand and patch the issue quickly, please include:
   - A clear description of the vulnerability.
   - Step-by-step instructions or a Proof of Concept (PoC) script to reproduce the behavior.
   - The potential impact (e.g., SQL Injection, XSS, Broken Multi-Tenant Data Isolation).
3. **Response Timeline:** We will acknowledge receipt of your vulnerability report within 48 hours and provide a status update or fix within 7 business days.

## Multi-Tenant Security Standards

Stokki enforces strict data isolation boundaries. If you identify any flaw that allows cross-tenant data leaks (a user accessing another workspace's inventory without proper authorization), please flag it as a **Critical Severity Incident** in your email subject line.

Thank you for helping keep Stokki secure for everyone!
