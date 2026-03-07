# Features Documentation - March 2026

This document explains the new features and logic improvements recently implemented in the School ERP SaaS platform.

---

## 🏗 Automated School Provisioning (Governance Panel)

The school creation process has been transformed into a fully automated "Zero-Config" workflow. Technical values related to databases and storage are now handled by the system.

### 1. Smart Field Sync
Previously, users had to manually enter database names and storage prefixes, leading to potential typos and errors.
- **Dynamic Generation**: When you enter the **School Name**, the system now automatically generates:
    - **Database Name**: (e.g., `school_international_high`)
    - **Storage Prefix**: (e.g., `international_high`)
- **Seamless Experience**: These fields are hidden from the UI to keep the form clean and focused on school data.

### 2. Intelligent Defaults
- **Admin Email Suggestion**: Once you enter a **Primary Domain**, the system automatically suggests an `Admin Email` (e.g., `admin@yourschool.com`).
- **Environment Auto-Fetch**: The system now pulls `DB_USERNAME` and `DB_PASSWORD` directly from the server's `.env` for all new tenant databases, eliminating the need for manual credential input.

---

## 🧹 Infrastructure Cleanup & Optimization

Apart from new features, the system's core has been significantly hardened.

### 1. Decoupled Mint/Service
The legacy `mint/service` infrastructure has been completely removed.
- **Improved Performance**: The application no longer performs unnecessary background update checks during login.
- **Dependency Stability**: Removed old package references that were causing autoloader failures.

### 2. Autoloader & Namespace Repair
- **Collision Resolution**: Fixed a critical issue where the `laravel/pint` package was incorrectly declaring the `App\` namespace, which was causing the "Target class [auth] does not exist" error.
- **Clean Booting**: All Laravel package discovery and optimization commands (`optimize:clear`, `package:discover`) now run without warnings.

---

## 🛠 How to Use the New Provisioning
1. Login to the **Governance Panel**.
2. Navigate to **Manage Schools**.
3. Click **Provision New School**.
4. Fill in:
    - **School Name** (DB/Storage will generate automatically).
    - **Sub-Division** & **Domain**.
    - **Admin Account** (Email will be suggested).
5. Click **Start Automated Provisioning**.

---
*Documentation provided by Antigravity AI.*
