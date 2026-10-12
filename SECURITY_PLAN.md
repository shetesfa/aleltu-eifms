# SECURITY PLAN (PHASE 6) — Aleltu Farm EIFMS

This document outlines the security controls, authentication rules, and data protection practices of the **Aleltu Farm EIFMS**.

---

## 1. Authentication & Session Control

*   **Hashing Engine**: Passwords are encrypted using PHP `password_hash()` with the `PASSWORD_DEFAULT` blowfish algorithm.
*   **Two-Factor Authentication (2FA)**: Enforces multi-step login checking via TOTP (RFC 6238). Secret keys are encrypted and stored in `users.two_factor_secret`.
*   **Session Protections**:
    *   Sessions automatically expire after 30 minutes of inactivity.
    *   Session IDs are regenerated upon successful login/logout to prevent session fixation.

---

## 2. Request Protections

### A. SQL Injection (SQLi)
*   **Strict Parameterization**: Every database query must use PDO prepared statements. Passing raw variables directly into query strings is strictly prohibited.

### B. Cross-Site Request Forgery (CSRF)
*   **Form Security**: All state-modifying actions (POST/PUT/DELETE forms) must contain a hidden `csrf_token` input. The server checks this token against session values before processing:
    ```php
    // In auth.php
    csrf_verify();
    ```

### C. Cross-Site Scripting (XSS)
*   **Output Escaping**: All variables rendered inside HTML templates must pass through the `h()` escape utility, which translates special characters into HTML entities:
    ```php
    <?= h($user_input) ?>
    ```

---

## 3. Upload & File Protection

When attachments (e.g. proof of delivery, animal documents) are supported:
1.  **MIME Verification**: Files are validated against a strict whitelist (e.g. `image/jpeg`, `image/png`, `application/pdf`) rather than trusting the client-supplied extension.
2.  **Size Limits**: Maximum upload size is restricted to 5MB.
3.  **Secure Storage**: Uploaded files are renamed using randomly generated UUID values and saved outside the public document root to prevent arbitrary code execution.
