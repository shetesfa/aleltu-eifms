# DEPLOYMENT & INSTALLATION (PHASE 9) — Aleltu Farm EIFMS

This document provides system prerequisites and deployment instructions for hosting the **Aleltu Farm EIFMS**.

---

## 1. System Requirements

*   **Operating System**: Windows (XAMPP/IIS) or Linux (Ubuntu/CentOS).
*   **Web Server**: Apache 2.4+ (with `mod_rewrite` enabled) or Nginx.
*   **PHP Version**: PHP 8.1+ (with the following extensions enabled: `pdo_mysql`, `openssl`, `mbstring`).
*   **Database Server**: MySQL 8.0+ or MariaDB 10.4+.

---

## 2. Step-by-Step Installation

1.  **Clone / Copy Files**: Extract the codebase zip archive into the web server document root directory (e.g., `c:\xampp\htdocs\aleltu-eifms`).
2.  **Database Restoration**:
    *   Log in to the database dashboard (e.g. phpMyAdmin).
    *   Create an empty database named `aleltu_eifms` with character set collation `utf8mb4_unicode_ci`.
    *   Import the SQL database schema [`schema.sql`](file:///c:/xampp/htdocs/aleltu-eifms/sql/schema.sql) file.
3.  **Environment Configuration**:
    *   Create a local database settings file inside `/config`.
    *   Provide the target database host, username, password, and port settings.
4.  **Directory Permissions**:
    *   Ensure the web server has write access permissions to the root directory for logging and configuration purposes.

---

## 3. Post-Deployment Verification

1.  Navigate to the login page (e.g., `http://localhost/aleltu-eifms/`).
2.  Log in using the default system administrator credentials:
    *   **Email**: `admin@aleltufarm.com`
    *   **Password**: `ChangeMe!2026`
3.  Go to **🌱 Demo Data Setup** in the sidebar. Click **Generate Demo Data** to seed sample records and create testing accounts for different operational roles.
