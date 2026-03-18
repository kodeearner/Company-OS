# CompanyOS
### Corporate Governance & Document Management Suite

> A self-hosted PHP + MySQL company management system with an MS Office–style ribbon UI,
> full resolution workflows, director management, document storage, user roles,
> audit logging, and undo/redo — all in **5 PHP files**.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Feature Summary](#2-feature-summary)
3. [System Requirements](#3-system-requirements)
4. [Installation](#4-installation)
5. [File Structure](#5-file-structure)
6. [Configuration — settings.json](#6-configuration--settingsjson)
7. [Modules Guide](#7-modules-guide)
8. [User Roles & Permissions](#8-user-roles--permissions)
9. [Keyboard Shortcuts](#9-keyboard-shortcuts)
10. [Ribbon Interface](#10-ribbon-interface)
11. [Undo / Redo System](#11-undo--redo-system)
12. [API Reference](#12-api-reference)
13. [Database Schema](#13-database-schema)
14. [Themes & Branding](#14-themes--branding)
15. [Security](#15-security)
16. [Audit Logging](#16-audit-logging)
17. [Export & Printing](#17-export--printing)
18. [Notifications](#18-notifications)
19. [Troubleshooting](#19-troubleshooting)
20. [Appendices](#20-appendices)

---

## 1. Overview

**CompanyOS** is a self-contained corporate governance and document management system for small-to-medium companies, law firms, and corporate secretariat teams. It runs on a standard LAMP/WAMP/XAMPP stack with **zero external dependencies** beyond PHP and MySQL.

### Design Philosophy

| Principle | Implementation |
|-----------|---------------|
| Zero-config startup | DB tables auto-created on first run via `api.php` |
| Single source of truth | All branding, DB, themes, roles, and flags live in `settings.json` |
| MS Office familiarity | Ribbon toolbar with grouped tabs and labeled icon buttons |
| Complete auditability | Every create / update / delete / login logged with user, IP, and timestamp |
| 5-file architecture | `index.php` · `api.php` · `resolutions.php` · `documents.php` · `settings_ui.php` |

---

## 2. Feature Summary

### Resolutions
- Create, draft, edit, and auto-number resolutions (`RES-2025-0001`)
- Four types: Ordinary (51 %), Special (75 %), Unanimous (100 %), Circular (100 %)
- Rich-text editor with formatting toolbar
- Submit → vote → auto-resolve when quorum met
- Status lifecycle: Draft → Pending → Approved / Rejected → Archived
- Tags, comments, and file attachments
- Print-ready layout and PDF export
- Full undo / redo (50 steps, persisted in DB per session)
- Auto-save every 30 seconds

### Approval Workflow
- Approvals assigned automatically to directors and fellows on submission
- Each voter can: Approve · Reject · Abstain (with optional reason)
- Quorum auto-check: resolution resolves when votes are complete
- In-app notifications sent to voters on submission
- Full approval history with voter names, timestamps, and IP addresses

### Directors
- Full registry: name, designation, CNIC, DIN, email, phone, qualification, expertise
- 8 configurable designations and 5 configurable committees
- Appointment date, term expiry, and term tracking
- Resign director with date and reason — history preserved
- Status: Active · Resigned · Removed · Deceased
- Card-based grid with committee badges

### Documents & Files
- Folder tree with 8 default folders (Board Resolutions, Financial Records, Legal, HR, Correspondence, Minutes, Contracts, Policies)
- Internal rich-text documents and external file uploads
- Supported: PDF, Word, Excel, PowerPoint, images, ZIP — up to 50 MB
- Version control: auto-snapshot on every save (up to 20 versions)
- Soft-delete with Recycle Bin (30-day retention)
- Access levels: Public · Internal · Confidential · Restricted

### User Management
- Create, edit, deactivate users
- 6 built-in roles: Super Admin, Admin, Director, Fellow, Staff, Viewer
- Password policy: length, uppercase, number, special character
- Account lockout after N failed logins
- Last-login tracking

### Settings Panel (settings_ui.php)
- **10 tabs**: General · Users · Directors · Roles & Perms · Security · Theme · Database · Notifications · Audit Log · System Info
- Live theme preview and selector (3 built-in themes)
- Company profile, locale, and feature-flag editing
- SMTP configuration with test button
- Searchable audit log viewer with CSV export
- PHP extension check, directory permissions, keyboard shortcut reference

### UI & Productivity
- MS Office–style ribbon with grouped tabs per module
- Collapsible sidebar navigation
- Persistent status bar with live clock and auto-save indicator
- Toast notification system (success · error · warning · info)
- Global modal system
- **Undo / Redo** (Ctrl+Z / Ctrl+Y), **Cut / Copy / Paste** via ribbon and keyboard
- Breadcrumb navigation and context menus

---

## 3. System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 7.4 | 8.1+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0 / 10.6 |
| Web Server | Apache 2.4 / Nginx | Apache with mod_rewrite |
| Browser | Chrome 90+, Firefox 88+, Edge 90+ | Latest Chrome or Edge |
| Disk Space | 100 MB | 1 GB+ (for uploaded documents) |

### Required PHP Extensions

```
pdo    pdo_mysql    json    mbstring    openssl    session    fileinfo
```

### Optional Extensions

```
gd      (image thumbnails)
zip     (archive uploads)
curl    (SMTP / webhooks)
xml     (export)
intl    (locale formatting)
```

---

## 4. Installation

### Step 1 — Place Files

Put all 6 files in your web root (or a subdirectory):

```
your-webroot/
├── index.php
├── api.php
├── resolutions.php
├── documents.php
├── settings_ui.php
└── settings.json
```

### Step 2 — Configure settings.json

Update the `database` block with your MySQL credentials:

```json
"database": {
    "host": "localhost",
    "port": 3306,
    "name": "companyos_db",
    "username": "your_db_user",
    "password": "your_db_password"
}
```

Update the `app` block with your company details:

```json
"app": {
    "company_name": "Your Company Ltd.",
    "company_short": "YourCo",
    "company_registration": "RC-2024-00001",
    "company_address": "123 Main Street, City",
    "company_email": "admin@yourcompany.com"
}
```

### Step 3 — Create Storage Directories

```bash
mkdir -p storage/documents storage/thumbnails logs assets
chmod 755 storage storage/documents storage/thumbnails logs
```

Or visit **Settings → System Info** and click **Create** next to any missing directory.

### Step 4 — First Run

Navigate to your site in the browser. On first load, `api.php` automatically:

1. Creates the database (if it does not exist)
2. Creates all 17 tables
3. Seeds the default admin user
4. Seeds 8 default document folders

### Step 5 — Login

| Field | Default Value |
|-------|--------------|
| Email | `admin@acmecorp.com` |
| Password | `Admin@1234` |

> ⚠ **Change the default password immediately** at Settings → Users.

### Step 6 — Update the Encryption Key

In `settings.json`:

```json
"security": {
    "data_encryption_key": "CHANGE_THIS_TO_A_RANDOM_32_CHAR_STRING"
}
```

Generate one: `php -r "echo bin2hex(random_bytes(32));"`

---

## 5. File Structure

```
companyos/
│
├── index.php           ← Entry point, auth, layout shell, routing
├── api.php             ← REST backend: CRUD, DB auto-install, audit
├── resolutions.php     ← Resolution editor & approval UI
├── documents.php       ← File manager, folder tree, document editor
├── settings_ui.php     ← 10-tab admin panel
├── settings.json       ← Master config (branding, DB, themes, roles)
│
├── storage/
│   ├── documents/      ← Uploaded files (auto-created)
│   └── thumbnails/     ← Thumbnails (auto-created)
│
├── logs/
│   ├── audit.log       ← Flat-file audit backup
│   ├── error.log
│   └── access.log
│
└── assets/
    ├── logo.png        ← Company logo (optional)
    └── favicon.ico
```

### File Responsibilities

| File | Role | ~Lines |
|------|------|--------|
| `index.php` | Auth, session, layout shell (sidebar + topbar + ribbon + status bar), routing, `cos` JS object | 500 |
| `api.php` | All AJAX endpoints, DB auto-install, CRUD, audit logging, undo state, notifications | 500 |
| `resolutions.php` | Resolution list / create / edit UI, rich-text editor, approval voting panel | 500 |
| `documents.php` | Folder tree, upload, document editor, version history, recycle bin | 500 |
| `settings_ui.php` | 10-tab admin panel, user and director management, audit viewer | 500 |
| `settings.json` | All configuration: branding, DB, themes, roles, flags | 420 |

---

## 6. Configuration — settings.json

Every configurable aspect of the system lives in `settings.json`. No `.env` files or PHP constants are needed.

### app — Identity & Locale

```json
"app": {
    "name": "CompanyOS",
    "tagline": "Corporate Governance & Document Management Suite",
    "company_name": "Acme Corporation Ltd.",
    "company_short": "Acme Corp",
    "company_registration": "RC-2024-00001",
    "timezone": "Asia/Karachi",
    "date_format": "d M Y",
    "currency": "PKR"
}
```

### database

```json
"database": {
    "host": "localhost",
    "port": 3306,
    "name": "companyos_db",
    "username": "root",
    "password": "",
    "prefix": "cos_",
    "auto_create_tables": true
}
```

### auth — Password & Session Policy

```json
"auth": {
    "password_min_length": 8,
    "password_require_uppercase": true,
    "password_require_number": true,
    "max_login_attempts": 5,
    "lockout_duration_minutes": 15,
    "session_lifetime": 86400,
    "default_admin_email": "admin@acmecorp.com",
    "default_admin_password": "Admin@1234"
}
```

### roles — RBAC

```json
"roles": {
    "super_admin": { "label": "Super Administrator", "permissions": ["*"] },
    "admin":       { "label": "Administrator",       "permissions": ["manage_users", "manage_directors", "..."] },
    "director":    { "label": "Board Director",      "permissions": ["view_resolutions", "approve_resolutions", "..."] }
}
```

### resolutions

```json
"resolutions": {
    "numbering_format": "RES-{YEAR}-{SEQ:04d}",
    "types": {
        "ordinary":  { "quorum_percent": 51 },
        "special":   { "quorum_percent": 75 },
        "unanimous": { "quorum_percent": 100 }
    },
    "undo_stack_limit": 50,
    "autosave_interval_seconds": 30
}
```

### documents

```json
"documents": {
    "max_file_size_mb": 50,
    "allowed_extensions": ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "png", "jpg", "jpeg", "zip"],
    "storage_path": "storage/documents/",
    "version_control": true,
    "max_versions_kept": 20,
    "recycle_bin_days": 30
}
```

### directors

```json
"directors": {
    "designations": ["Chairman", "CEO", "Executive Director", "Non-Executive Director", "..."],
    "committees": ["Audit Committee", "Remuneration Committee", "Nomination Committee", "..."],
    "term_years": 3,
    "retirement_age": 70
}
```

### theme

```json
"theme": {
    "active": "corporate_blue",
    "themes": {
        "corporate_blue": {
            "primary": "#1e3a5f",
            "primary_light": "#2563eb",
            "bg_sidebar": "#1e3a5f",
            "font_ui": "'Segoe UI', system-ui, sans-serif"
        }
    }
}
```

### notifications / smtp

```json
"notifications": {
    "enabled": true,
    "email_enabled": false,
    "smtp": {
        "host": "smtp.example.com",
        "port": 587,
        "username": "",
        "password": "",
        "from_email": "noreply@acmecorp.com",
        "encryption": "tls"
    }
}
```

### security

```json
"security": {
    "csrf_enabled": true,
    "rate_limiting": true,
    "rate_limit_requests_per_minute": 120,
    "force_https": false,
    "ip_whitelist_enabled": false,
    "data_encryption_key": "CHANGE_THIS_IN_PRODUCTION"
}
```

---

## 7. Modules Guide

### 7.1 Dashboard

The default landing page after login. Shows:

- **4 stat cards**: Total Resolutions · Pending Approvals · Active Directors · Documents
- **Recent Resolutions** table with status badges
- **My Pending Approvals** with inline Approve / Reject buttons
- **Quick Actions**: New Resolution · Upload Document · Directors · Audit Log

### 7.2 Resolutions

#### Creating a Resolution

1. Click **➕ New Resolution** (ribbon Home tab or Ctrl+N)
2. Enter title and body in the rich-text editor
3. Select **Type** — this sets the quorum percentage
4. Set optional Meeting Date, Effective Date, and Deadline
5. Add Tags (comma-separated)
6. **💾 Save** (Ctrl+S) → auto-generates number, e.g., `RES-2025-0001`
7. Saved as **Draft**

#### Submitting for Approval

1. Open a Draft resolution
2. Click **📨 Submit** in the Resolution ribbon tab
3. System creates approval slots for all active Directors and Fellows
4. Status → **Pending**; voters receive in-app notifications

#### Resolution Types & Quorum

| Type | Quorum | Typical Use |
|------|--------|-------------|
| Ordinary | 51 % | Routine business decisions |
| Special | 75 % | Significant changes (articles, capital) |
| Unanimous | 100 % | Written resolutions |
| Circular | 100 % | Resolutions passed without a meeting |

### 7.3 Approval Workflow

- Voters see pending approvals on their dashboard
- Click **✅ Approve** / **❌ Reject** / **⏸ Abstain** (with optional reason)
- System auto-resolves: Approved if votes ≥ quorum %, Rejected otherwise
- Full approval history (voter, timestamp, IP) is preserved

### 7.4 Directors

1. **Settings → Directors** or sidebar shortcut
2. Click **➕ Add Director**
3. Required: Name, Designation, Appointment Date, Email
4. Optional: CNIC, DIN, Phone, Qualification, Expertise, Committees, Notes
5. Click **💾 Save Director**

To **resign** a director: click **🚪 Resign** on the card, enter date and reason. Status → Resigned (history preserved).

### 7.5 Documents & Files

**Default folder tree:**

```
Board Resolutions · Financial Records · Legal & Compliance · HR & Personnel
Correspondence · Meeting Minutes · Contracts · Policies & Procedures
```

**Creating a document:** Select folder → New Document → enter title and content → Save.

**Uploading a file:** Ribbon Tools tab → Upload → select file (max 50 MB).

**Version history:** Every save auto-snapshots the previous version (up to 20 kept).

**Recycle Bin:** Deleted documents move here; restore within 30 days or permanently delete.

### 7.6 Settings & Administration

Accessed via ⚙️ Settings in the sidebar or `index.php?page=settings_ui`.

| Tab | Contents |
|-----|----------|
| General | Company profile, locale, resolution & document settings |
| Users | User list (card grid), add / edit / deactivate, role assignment |
| Directors | Full director management (same data as sidebar module) |
| Roles & Perms | Read-only permission matrix from `settings.json` |
| Security | Auth policy, CSRF, rate limiting, IP whitelist, encryption key |
| Theme | Visual theme selector with color swatches, UI preferences |
| Database | Connection info (read-only), DB stats, maintenance actions |
| Notifications | SMTP config, in-app/email toggles, event trigger table |
| Audit Log | Searchable viewer with user / action filters and CSV export |
| System Info | PHP version, extensions, directory permissions, shortcut reference |

**Saving settings:** Any change marks the page dirty (● indicator). Click **💾 Save** or press Ctrl+S. Changes are written to `cos_settings` in the DB and read back into `$CFG` on next load. Structural changes (DB host, table prefix) require editing `settings.json` directly.

---

## 8. User Roles & Permissions

### Built-in Roles

| Role Key | Label | Key Permissions |
|----------|-------|-----------------|
| `super_admin` | Super Administrator | All (`*`) |
| `admin` | Administrator | Manage users, directors, resolutions, documents, settings |
| `director` | Board Director | View & approve resolutions, create documents |
| `fellow` | Fellow | View & approve resolutions, create documents |
| `staff` | Staff Member | View resolutions, create own documents |
| `viewer` | Viewer / Guest | View resolutions and documents only |

### All Permissions

| Permission | Description |
|-----------|-------------|
| `manage_users` | Create, edit, deactivate users |
| `manage_directors` | Add, edit, resign directors |
| `manage_resolutions` | Create, edit, archive, delete resolutions |
| `manage_documents` | Manage all documents and folders |
| `view_resolutions` | View resolution list and details |
| `approve_resolutions` | Vote on pending resolutions |
| `sign_resolutions` | Apply digital signature placeholder |
| `view_documents` | View documents |
| `create_documents` | Create new documents |
| `edit_own_documents` | Edit documents the user created |
| `view_audit` | Access audit log viewer |
| `manage_settings` | Change application settings |

### Adding a Custom Role

Edit `settings.json → roles`:

```json
"legal_advisor": {
    "label": "Legal Advisor",
    "color": "#8b5cf6",
    "icon": "briefcase",
    "permissions": ["view_resolutions", "view_documents", "create_documents"]
}
```

---

## 9. Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+S` | Save |
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Ctrl+N` | New resolution or document |
| `Ctrl+P` | Print |
| `Ctrl+F` | Find / search |
| `Ctrl+X` | Cut |
| `Ctrl+C` | Copy |
| `Ctrl+V` | Paste |
| `Ctrl+A` | Select all |
| `Ctrl+B` | Bold (in editors) |
| `Ctrl+I` | Italic (in editors) |
| `Ctrl+U` | Underline (in editors) |
| `Ctrl++` | Zoom in |
| `Ctrl+-` | Zoom out |
| `F1` | Help |
| `F5` | Refresh |
| `F11` | Fullscreen |
| `Ctrl+W` | Close tab |

---

## 10. Ribbon Interface

The ribbon is modeled on Microsoft Office's Fluent UI ribbon toolbar.

### Structure

```
[ Tab ]  [ Tab ]  [ Tab ]                 ← Tab bar
────────────────────────────────────
[ Group 1              ]  [ Group 2 ]     ← Panel
[  btn  ]  [  btn  ]      [  btn  ]
  Group Label               Group Label
```

### Ribbon Tabs — Resolutions

| Tab | Groups / Actions |
|-----|-----------------|
| Home | Clipboard (Paste, Cut, Copy), History (Undo, Redo), File (Save, Print, Export) |
| Resolution | New, Type selector, Submit, Archive, Delete |
| Approval | Approve, Reject, Abstain, View Votes, Send Reminders |
| Directors | Add Director, View All |
| Review | Find / Replace, Word Count, Comments |

### Ribbon Tabs — Documents

| Tab | Groups / Actions |
|-----|-----------------|
| Home | Clipboard, History, File |
| Insert | Image, Link, Table |
| Format | Bold, Italic, Underline, Justify, Lists |
| Tools | Upload, New Folder, Recycle Bin |
| Review | Find, Word Count, Comments |

### Ribbon Tabs — Settings

| Tab | Contents |
|-----|----------|
| General | Company profile fields |
| Database | DB status and maintenance |
| Security | Security toggles |
| Theme | Theme selector |
| Notifications | SMTP config |

---

## 11. Undo / Redo System

CompanyOS uses a two-layer undo system:

**Client layer (in-memory):** The `cos` JavaScript object maintains a per-entity stack for real-time editor changes — instant undo without a network round-trip.

**Server layer (persisted):** Before any destructive API operation, the current entity state is serialized and stored in `cos_undo_history`, scoped per `session_id + user_id + entity_type`. The stack is capped at 50 steps (configurable) and survives page refreshes.

### Flow

```
User edits → cos.pushUndo(state)              ← client stack
User saves → api.php saves + saveUndoState()  ← DB stack
Ctrl+Z     → pop client stack (instant)
             if stack empty → api.php undo     ← DB pop + restore
Ctrl+Y     → pop redo stack → re-apply
```

### Coverage

| Entity | Operations Covered |
|--------|-------------------|
| Resolutions | Create, Edit body/title/type, Submit, Archive |
| Documents | Create, Edit, Delete |
| Directors | Create, Edit |

---

## 12. API Reference

All calls are `POST` to `api.php` with an `action` parameter plus a CSRF token.

### Authentication

| Action | Parameters |
|--------|-----------|
| `login` | `email`, `password` |
| `logout` | — |

### Dashboard

| Action | Returns |
|--------|---------|
| `dashboard_stats` | `{resolutions, directors, documents, pending_approvals}` |
| `recent_resolutions` | Array of recent resolution records |
| `my_pending_approvals` | Array of pending approval records for session user |

### Resolutions

| Action | Key Parameters | Permission |
|--------|---------------|------------|
| `list_resolutions` | `status`, `type`, `search`, `page` | `view_resolutions` |
| `get_resolution` | `id` | `view_resolutions` |
| `create_resolution` | `title`, `content`, `type`, `meeting_date`, `deadline`, `tags` | `manage_resolutions` |
| `update_resolution` | `id`, any editable field | `manage_resolutions` |
| `submit_resolution` | `id` | `manage_resolutions` |
| `approve_resolution` | `id`, `vote` (approve/reject/abstain), `reason` | `approve_resolutions` |
| `archive_resolution` | `id` | `manage_resolutions` |
| `delete_resolution` | `id` | `manage_resolutions` |

### Directors

| Action | Key Parameters | Permission |
|--------|---------------|------------|
| `list_directors` | `status`, `search` | Any auth |
| `get_director` | `id` | Any auth |
| `create_director` | `name`, `designation`, `email`, `appointment_date`, `committees`, … | `manage_directors` |
| `update_director` | `id`, any field | `manage_directors` |
| `resign_director` | `id`, `resignation_date`, `reason` | `manage_directors` |

### Documents

| Action | Key Parameters | Permission |
|--------|---------------|------------|
| `list_documents` | `folder_id`, `search`, `deleted` | `view_documents` |
| `create_document` | `title`, `content`, `folder_id`, `access_level` | `create_documents` |
| `update_document` | `id`, `title`, `content`, `change_summary` | `edit_own_documents` |
| `delete_document` | `id` | `edit_own_documents` |
| `restore_document` | `id` | `manage_documents` |
| `list_folders` | — | `view_documents` |
| `create_folder` | `name`, `parent_id`, `icon`, `color` | `manage_documents` |

### Users

| Action | Key Parameters | Permission |
|--------|---------------|------------|
| `list_users` | — | `manage_users` |
| `create_user` | `name`, `email`, `password`, `role` | `manage_users` |
| `update_user` | `id`, `name`, `role`, `status`, `password` | `manage_users` |
| `delete_user` | `id` | `manage_users` |

### Notifications & Comments

| Action | Key Parameters |
|--------|---------------|
| `get_notifications` | `limit` |
| `mark_notification_read` | `id` |
| `mark_all_notifications_read` | — |
| `add_comment` | `entity_type`, `entity_id`, `content` |
| `get_comments` | `entity_type`, `entity_id` |

### Settings & Audit

| Action | Key Parameters | Permission |
|--------|---------------|------------|
| `save_setting` | `key`, `value` | `manage_settings` |
| `list_audit_log` | `limit`, `action`, `user_id` | `view_audit` |
| `export_excel` | `type` | varies |

### Response Format

```json
{
  "success": true,
  "data": { "...": "..." },
  "message": "Resolution created"
}
```

---

## 13. Database Schema

All 17 tables auto-created by `api.php` on first run. Default prefix: `cos_`.

| Table | Purpose |
|-------|---------|
| `cos_users` | User accounts, roles, lockout state |
| `cos_roles` | Role metadata (supplementary) |
| `cos_permissions` | Fine-grained permission overrides (reserved) |
| `cos_directors` | Director registry |
| `cos_resolutions` | Resolution records |
| `cos_approvals` | Votes per resolution per voter |
| `cos_documents` | Document records |
| `cos_document_versions` | Version snapshots per document |
| `cos_folders` | Folder tree for documents |
| `cos_audit_log` | All system events |
| `cos_sessions` | Extended session data (reserved) |
| `cos_settings` | Runtime key-value settings store |
| `cos_comments` | Comments on resolutions and documents |
| `cos_notifications` | In-app notification queue |
| `cos_undo_history` | Undo/redo state stack (per session) |
| `cos_tags` | Tag definitions |
| `cos_tag_relations` | Tag–entity relationships |

### Key Relationships

```
cos_users        ──< cos_resolutions    (created_by)
cos_resolutions  ──< cos_approvals      (resolution_id)
cos_users        ──< cos_approvals      (voter_id)
cos_folders      ──< cos_documents      (folder_id)
cos_documents    ──< cos_document_versions (document_id)
cos_users        ──< cos_notifications  (user_id)
cos_resolutions  >── cos_comments       (entity_type = 'resolution')
cos_documents    >── cos_comments       (entity_type = 'document')
```

---

## 14. Themes & Branding

### Switching Themes

1. **Settings → Theme** tab
2. Click a theme card
3. **💾 Apply & Save** → reload page

### Built-in Themes

| Key | Description |
|-----|-------------|
| `corporate_blue` | Deep navy sidebar, white content area, blue accents |
| `executive_dark` | Full dark-mode with indigo/purple accents |
| `classic_green` | Forest-green sidebar, clean white content |

### Creating a Custom Theme

Add an entry to `settings.json → theme → themes`:

```json
"my_brand": {
    "label": "My Brand",
    "primary": "#1a1a2e",
    "primary_light": "#e94560",
    "secondary": "#0f3460",
    "accent": "#f5a623",
    "success": "#27ae60",
    "danger": "#e74c3c",
    "warning": "#f39c12",
    "info": "#2980b9",
    "bg_body": "#f5f5f5",
    "bg_sidebar": "#1a1a2e",
    "bg_topbar": "#ffffff",
    "bg_card": "#ffffff",
    "text_primary": "#2c3e50",
    "text_secondary": "#7f8c8d",
    "text_sidebar": "#bdc3c7",
    "border_color": "#e8e8e8",
    "ribbon_bg": "#f9f9f9",
    "ribbon_border": "#e8e8e8",
    "tab_active_bg": "#ffffff",
    "tab_active_border": "#e94560",
    "font_ui": "'Inter', sans-serif",
    "font_doc": "'Georgia', serif"
}
```

Add the key to `theme.available` and set `theme.active`.

### Company Logo

Place at `assets/logo.png`. Referenced in printed documents and PDF headers.

---

## 15. Security

### CSRF Protection
All POST requests are validated against a session token. Generated on login, checked in `api.php`.

### Password Hashing
`password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])` — not MD5, not SHA1.

### Account Lockout
After N failed attempts (default 5), the account is locked for M minutes (default 15). Stored in `cos_users.locked_until`.

### Rate Limiting
When enabled: max 120 requests per minute per IP. Configurable in `settings.json`.

### IP Whitelist
Set `security.ip_whitelist_enabled: true` and list CIDRs/IPs in `security.ip_whitelist`.

### Production Security Checklist

```
☐ Change default admin password immediately after install
☐ Set data_encryption_key to 32+ random characters
☐ Set force_https: true when SSL is active
☐ Configure ip_whitelist if on a private network
☐ Use a strong DB password; restrict DB user to this DB only
☐ Move settings.json above the web root in production
☐ Set file permissions: 755 dirs, 644 files, 600 settings.json
☐ Disable PHP display_errors in production php.ini
☐ Enable PHP opcache for performance
☐ Set up cron to purge old audit logs and recycle bin
```

---

## 16. Audit Logging

Every significant action is recorded in `cos_audit_log`:

| Column | Content |
|--------|---------|
| `user_id` / `user_name` | Who acted |
| `action` | Action code, e.g., `create_resolution`, `login`, `update_director` |
| `entity_type` / `entity_id` | What was affected |
| `old_value` / `new_value` | JSON before/after (for updates) |
| `ip_address` | Client IP |
| `user_agent` | Browser string |
| `created_at` | UTC timestamp |

### Viewing Logs

**Settings → Audit Log** — searchable, filterable, paginated. Filter by action keyword or user. Export to CSV.

### Retention

Configured via `audit.retention_days` (default 365). Purge from **Settings → Database → Maintenance**.

---

## 17. Export & Printing

### Print
`Ctrl+P` or the **🖨 Print** ribbon button opens the browser's print dialog. Print styles hide the sidebar and ribbon.

### PDF Export
Ribbon **📄 PDF** button or `api.php?action=export_pdf`. Full PDF output requires FPDF or TCPDF (not bundled). Install: `composer require setasign/fpdf` and require in `resolutions.php`.

### Excel / CSV Export
Ribbon **📊 Excel** button. Built-in CSV works immediately. Full XLSX requires PhpSpreadsheet: `composer require phpoffice/phpspreadsheet`.

### Watermarks
Draft PDFs can be watermarked. Controlled by `export.watermark_drafts` and `export.watermark_text` in `settings.json`.

---

## 18. Notifications

### In-App
Bell icon in the topbar shows unread count. Clicking opens the notification panel. Controlled by `notifications.in_app_enabled`.

### Email (optional)
1. Set `notifications.email_enabled: true`
2. Configure SMTP at **Settings → Notifications**
3. Test via **📧 Send Test Email**
4. Requires PHPMailer: `composer require phpmailer/phpmailer`

### Event Triggers

| Event | Notified Roles |
|-------|---------------|
| Resolution submitted | Admin, Director |
| Resolution approved | Admin, Staff |
| Resolution rejected | Admin, Staff |
| Approval required | Director, Fellow |
| Document uploaded | Admin |
| Director added | Admin |
| Director resigned | Admin, Director |

---

## 19. Troubleshooting

**Database connection failed**
Verify credentials in `settings.json`. Confirm MySQL is running. Ensure the DB user has `CREATE DATABASE` rights or create the DB manually:
```sql
CREATE DATABASE companyos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON companyos_db.* TO 'user'@'localhost';
```

**settings.json not found**
Confirm it is in the same directory as `index.php`. Check: `chmod 644 settings.json`.

**Blank white page**
Add to the top of `index.php` temporarily: `error_reporting(E_ALL); ini_set('display_errors', 1);`. Check `logs/error.log`.

**"Permission denied" on storage directories**
```bash
chmod 755 storage storage/documents storage/thumbnails logs
```

**Login loops / session issues**
Clear browser cookies for your domain. Confirm `session.save_path` is writable in `php.ini`.

**Tables not being created**
Check `database.auto_create_tables: true` in `settings.json`. Hit `api.php?action=dashboard_stats` directly to trigger the installer. Review `logs/error.log` for DDL errors.

**Undo not working**
Ensure you are logged in (undo is session-scoped). Confirm `cos_undo_history` exists. Check the browser console for JS errors.

**File uploads failing**
Increase `upload_max_filesize` and `post_max_size` in `php.ini`. Confirm `storage/documents/` is writable. Check `documents.max_file_size_mb` in `settings.json`.

**Notifications not sending**
Email requires PHPMailer and a valid SMTP config. Use the test button in **Settings → Notifications** to diagnose. Check `logs/error.log` for SMTP errors.

**Audit log empty**
Confirm `audit.enabled: true` and `audit.log_crud: true` in `settings.json`. The table is created on first run; visit a page that triggers a DB action to initialize it.

---

## 20. Appendices

### Appendix A — Directory Permissions

```
companyos/           755
├── index.php        644
├── api.php          644
├── resolutions.php  644
├── documents.php    644
├── settings_ui.php  644
├── settings.json    600   ← restrict in production
├── storage/         755
│   ├── documents/   755   ← PHP must be able to write here
│   └── thumbnails/  755
├── logs/            755   ← PHP must be able to write here
└── assets/          755
```

### Appendix B — Useful SQL Queries

```sql
-- All active users
SELECT id, name, email, role, last_login FROM cos_users WHERE status = 'active';

-- Resolutions by status
SELECT status, COUNT(*) FROM cos_resolutions GROUP BY status;

-- Recent audit events
SELECT * FROM cos_audit_log ORDER BY created_at DESC LIMIT 50;

-- Directors with terms expiring within 90 days
SELECT name, designation, term_expires FROM cos_directors
WHERE status = 'active'
  AND term_expires BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 90 DAY);

-- Resolutions pending for more than 7 days
SELECT number, title, submitted_at FROM cos_resolutions
WHERE status = 'pending'
  AND submitted_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Reset a user password manually
-- First generate hash: php -r "echo password_hash('NewPass@123', PASSWORD_BCRYPT, ['cost'=>12]);"
UPDATE cos_users SET password_hash = '$2y$12$...' WHERE email = 'user@company.com';

-- Purge undo history older than 7 days
DELETE FROM cos_undo_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Appendix C — Adding a New Module

1. Create `meetings.php` with your module UI content.
2. Add routing in `index.php`:
   ```php
   case 'meetings':
       include_once ROOT . '/meetings.php';
       break;
   ```
3. Add to `$navGroups` in `index.php` for the sidebar link.
4. Add ribbon tab definitions in `getRibbonGroups()`.
5. Add API actions in the `switch ($action)` block in `api.php`.
6. Add the table DDL in `autoInstallTables()` in `api.php`.

### Appendix D — Optional Composer Packages

None are required for core functionality. These enhance specific features:

| Package | Feature | Command |
|---------|---------|---------|
| `phpmailer/phpmailer` | Email notifications | `composer require phpmailer/phpmailer` |
| `setasign/fpdf` | PDF export | `composer require setasign/fpdf` |
| `phpoffice/phpspreadsheet` | XLSX export | `composer require phpoffice/phpspreadsheet` |

---

*CompanyOS v1.0.0 — Built for corporate governance teams*
*Default timezone: Asia/Karachi (PKT, UTC+5)*
