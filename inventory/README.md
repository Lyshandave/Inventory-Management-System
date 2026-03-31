# 👟 Shoes Inventory Management System

A fast, fully responsive, production-ready Shoes Inventory Management System built with **PHP**, **HTML**, **CSS**, and **JavaScript** — no frameworks, no dependencies beyond a MySQL database.

---

## Table of Contents

- [Features](#features)
- [Screenshots (Pages)](#screenshots-pages)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Default Credentials](#default-credentials)
- [File Structure](#file-structure)
- [User Roles](#user-roles)
- [Key Functionality](#key-functionality)
- [Real-Time Sync](#real-time-sync)
- [Security](#security)
- [Deployment Checklist](#deployment-checklist)
- [Database Schema](#database-schema)
- [Customization](#customization)
- [Troubleshooting](#troubleshooting)

---

## Features

| Category | Details |
|---|---|
| **Inventory** | Add, edit, archive, restore products with image upload |
| **Sales** | Sell products, quantity stepper, per-transaction records |
| **Refunds** | Full or partial refund, resellable (restocks) or damaged |
| **Stock** | Add stock, min/max limits, low-stock alerts, best-seller badges |
| **Reports** | Sales history with date-range filters (Today / Week / Month / Custom) |
| **Cashier Report** | Per-cashier, per-shift breakdown with print support |
| **Shifts** | Cashier shift management — start, end, lock screen |
| **Users** | Admin creates/edits/deactivates cashier and admin accounts |
| **Real-Time** | BroadcastChannel (same browser) + long-poll (cross-device) sync |
| **Print** | Print-optimized Sales History and Cashier Report |
| **Responsive** | Mobile, tablet, desktop — all screen sizes |
| **Accessible** | ARIA labels, focus-visible, keyboard navigation throughout |

---

## Screenshots (Pages)

| Page | URL | Access |
|---|---|---|
| Login | `index.php` | Public |
| Dashboard | `admin/admin.php` | All users |
| Add Product | `admin/add_product.php` | Admin only |
| Edit Product | `admin/edit_product.php?id=N` | Admin only |
| Sales History | `admin/sales_history.php` | Admin only |
| Cashier Report | `admin/cashier_report.php` | Admin only |
| Manage Users | `admin/manage_users.php` | Admin only |
| Lock Screen | `admin/lock.php` | Cashier only |

---

## Tech Stack

- **Backend:** PHP 8.1+ (typed returns, `match`, `str_starts_with`, `str_contains`)
- **Database:** MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4)
- **Frontend:** Vanilla JavaScript (ES5-compatible, no framework)
- **CSS:** Custom design system with Normalize.css v8.0.1 (inlined) + Autoprefixer vendor prefixes
- **Icons:** Font Awesome 6.4 (CDN)
- **Server:** Apache with `mod_rewrite`, `mod_headers`, `mod_expires`, `mod_deflate`

---

## Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| PHP | 8.1 | 8.2+ |
| MySQL | 5.7 | 8.0+ |
| Apache | 2.4 | 2.4 |
| PHP Extensions | `mysqli`, `mbstring`, `fileinfo` | — |
| Disk space | 50 MB | 500 MB+ (for images) |
| Browser | Chrome 80+, Firefox 75+, Safari 14+, Edge 80+ | Latest |

---

## Installation

### Local Development (XAMPP / WAMP / MAMP)

1. **Clone or extract** the project into your web server root:
   ```
   htdocs/shoes_inventory/   (XAMPP)
   www/shoes_inventory/      (WAMP / MAMP)
   ```

2. **Start Apache and MySQL** in your control panel.

3. **Run setup** — open your browser and go to:
   ```
   http://localhost/shoes_inventory/setup.php
   ```
   This creates the database, tables, and sample data automatically.

4. **Log in** at:
   ```
   http://localhost/shoes_inventory/
   ```

### Manual Database Setup (alternative)

If you prefer to import manually:
```bash
mysql -u root shoes_inventory < database.sql
```
Or import `database.sql` via **phpMyAdmin**.

### Configuration

Edit `config.php` to match your environment:

```php
define('DB_HOST', 'localhost');   // Database host
define('DB_USER', 'root');        // Database username
define('DB_PASS', '');            // Database password
define('DB_NAME', 'shoes_inventory'); // Database name
```

Other settings in `config.php`:

```php
define('WARRANTY_DAYS',    30);   // Refund window in days
define('AUTO_LOCK_MINUTES', 5);   // Cashier inactivity lock timeout
```

---

## Default Credentials

> ⚠️ **Change these immediately in production.**

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Cashier | `cashier` | `cashier123` |

Passwords are hashed with **bcrypt (cost=12)** — safe to store.

---

## File Structure

```
shoes_inventory/
├── index.php                  # Login page
├── config.php                 # DB config, helpers, shared functions
├── database.sql               # Full schema + sample data
├── setup.php                  # One-time setup wizard (delete after use)
├── .htaccess                  # Apache: security, caching, compression
├── README.md                  # This file
│
├── admin/
│   ├── admin.php              # Dashboard (product table + stats)
│   ├── add_product.php        # Add new product form
│   ├── edit_product.php       # Edit existing product form
│   ├── sales_history.php      # Sales history with range filters
│   ├── cashier_report.php     # Per-cashier shift breakdown
│   ├── manage_users.php       # Create/edit/deactivate users
│   ├── lock.php               # Cashier lock screen
│   ├── logout.php             # Session destroy + redirect
│   ├── _navbar.php            # Shared navigation partial
│   │
│   ├── sell_product.php       # AJAX: record a sale
│   ├── add_stock.php          # AJAX: add inventory
│   ├── archive_product.php    # AJAX: archive/restore product
│   ├── process_refund.php     # AJAX: full or partial refund
│   ├── end_shift.php          # AJAX: end cashier shift
│   ├── dashboard_stats.php    # AJAX: live stat cards
│   ├── products_sync.php      # AJAX: stock levels for sync
│   └── sync_check.php         # Long-poll: cross-device change detection
│
├── assets/
│   ├── css/
│   │   ├── normalize.css      # Normalize.css v8.0.1 (reference copy)
│   │   └── style.css          # Main stylesheet (normalize inlined + full system)
│   └── js/
│       ├── main.js            # All client-side logic
│       └── cashier_report.js  # Cashier report specific JS
│
└── uploads/                   # Product images (auto-created, git-ignored)
```

---

## User Roles

### Admin

- Full access to all pages and features
- View all-time sales stats and profit
- Add, edit, archive, restore products
- Process refunds
- Manage user accounts (create, edit, deactivate)
- View cashier reports for all shifts
- No shift management (no lock screen, no end-shift)

### Cashier

- Dashboard: view products, sell, add stock
- Shift management: start (auto on login), end shift, lock screen
- View only their own shift stats on dashboard
- Cannot access: Sales History, Cashier Report, Manage Users, Add/Edit/Archive products

---

## Key Functionality

### Selling a Product

1. Click the **sell button** (cash register icon) on any in-stock product row
2. Adjust quantity with the stepper — total auto-calculates
3. Click **Confirm Sale** — stock decrements instantly, transaction recorded

### Adding Stock

1. Click the **+ button** (green) on any product row
2. Enter quantity — max stock limit is enforced
3. Click **Add Stock** — updates immediately across all open tabs and devices

### Processing a Refund

1. Go to **Sales History** → click the **undo button** on any `Sold` transaction
2. Select quantity to refund, item condition (Resellable / Damaged), optional reason
3. Resellable refunds automatically restock the product
4. Warranty window: **30 days** (configurable in `config.php`)

### Cashier Shifts

- A shift **starts automatically** when a cashier logs in
- To end a shift: click the **flag icon** in the navbar
- To lock the screen: click the **lock icon** — shift stays active, password required to unlock
- On logout: cashier can choose to end shift with summary or just log out
- Auto-lock: screen locks after **5 minutes of inactivity** (configurable)

### Best Sellers

- Top 5 products by units sold are automatically detected
- Gold 🥇 / Silver 🥈 / Bronze 🥉 / ⭐ Top 4–5 badges appear on product rows
- Best sellers float to the top of the product table

---

## Real-Time Sync

The system keeps all open tabs and devices in sync automatically — zero manual refresh needed.

| Method | Scope | Latency |
|---|---|---|
| **BroadcastChannel** | Same browser, all tabs | ~0 ms |
| **Long-poll** (`sync_check.php`) | All devices, all browsers | ~100 ms |

**How long-poll works:**
- The browser holds an open HTTP request to `sync_check.php`
- The server checks for DB changes every 100 ms for up to 25 seconds
- The moment data changes, the server responds; JS calls `syncAllData()` and immediately starts a new poll
- On network error: retries after 2 seconds

**What triggers a sync:**
- New sale
- Stock added
- Product archived/restored
- Refund processed

---

## Security

| Layer | Implementation |
|---|---|
| **Authentication** | bcrypt (cost=12) password hashing |
| **Session** | Secure cookies, HttpOnly, SameSite=Strict, regenerated on login |
| **SQL Injection** | 100% prepared statements with bound parameters |
| **XSS** | All output escaped with `htmlspecialchars()` via `h()` helper |
| **CSRF** | SameSite=Strict cookies prevent cross-site form submission |
| **File Upload** | MIME type whitelist, size limit (5 MB), randomized filenames |
| **Role-based Access** | `require_login('admin')` enforced on every admin endpoint |
| **AJAX Auth** | 401/403 JSON responses for expired/unauthorized AJAX calls |
| **Headers** | X-Content-Type-Options, X-Frame-Options, CSP, Referrer-Policy via `.htaccess` |
| **Directory listing** | `Options -Indexes` in `.htaccess` |
| **Sensitive files** | `config.php`, `database.sql`, `setup.php`, `.env` blocked by `.htaccess` |
| **Session locking** | `session_write_close()` called early on read-only endpoints to prevent blocking |
| **Setup guard** | `setup.php` blocks re-runs after `.setup_done` flag is written |

---

## Deployment Checklist

Before going live:

- [ ] **Change default passwords** for `admin` and `cashier`
- [ ] Set `DB_PASS` to a strong password in `config.php`
- [ ] Verify `uploads/` folder is **writable** by the web server (`chmod 755` or `775`)
- [ ] **Delete or rename `setup.php`** (or leave the `.setup_done` flag in place)
- [ ] Enable HTTPS and **uncomment the HTTPS redirect** in `.htaccess`
- [ ] Set `display_errors = Off` and `log_errors = On` in your PHP config
- [ ] Consider rate-limiting the login page at the server level
- [ ] Set file permissions: `config.php` → `640`, PHP files → `644`, directories → `755`
- [ ] Review and tighten the CSP header in `.htaccess` for your CDN choices

---

## Database Schema

### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `username` | VARCHAR(60) UNIQUE | Login name |
| `password` | VARCHAR(255) | bcrypt hash |
| `full_name` | VARCHAR(120) | Display name |
| `role` | ENUM('admin','cashier') | Access level |
| `is_active` | TINYINT(1) | 1=active, 0=deactivated |
| `created_at` | TIMESTAMP | Account creation time |

### `products`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `product_name` | VARCHAR(255) | Required |
| `brand` | VARCHAR(100) | Required |
| `category` | VARCHAR(100) | e.g. Running, Casual |
| `size` | VARCHAR(20) | e.g. 9, 10.5 |
| `color` | VARCHAR(50) | e.g. Black/White |
| `cost_price` | DECIMAL(10,2) | Purchase price |
| `price` | DECIMAL(10,2) | Selling price (> 0) |
| `quantity` | INT | Current stock |
| `min_stock` | INT | Low-stock threshold |
| `max_stock` | INT | Max stock limit |
| `gender` | ENUM('Men','Women','Unisex') | |
| `description` | TEXT | Optional |
| `image` | VARCHAR(255) | Filename in `uploads/` |
| `is_archived` | TINYINT(1) | 0=active, 1=archived |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | Auto-updated on change |

### `transactions`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `product_id` | INT FK | References `products.id` |
| `sold_by` | INT FK NULL | References `users.id` |
| `shift_id` | INT FK NULL | References `shifts.id` |
| `quantity_sold` | INT | Units in this transaction |
| `unit_price` | DECIMAL(10,2) | Price at time of sale |
| `unit_cost` | DECIMAL(10,2) | Cost at time of sale |
| `total_price` | DECIMAL(10,2) | `unit_price × qty` |
| `total_cost` | DECIMAL(10,2) | `unit_cost × qty` |
| `status` | ENUM | Sold / Refunded (Restocked) / Refunded (Damaged) |
| `refund_reason` | TEXT NULL | Optional refund note |
| `sale_date` | TIMESTAMP | Transaction timestamp |
| `updated_at` | TIMESTAMP | Auto-updated on refund |

### `shifts`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `user_id` | INT FK | References `users.id` |
| `started_at` | TIMESTAMP | Shift start time |
| `ended_at` | TIMESTAMP NULL | Null = shift still open |

### `stock_logs`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `product_id` | INT FK | References `products.id` |
| `added_by` | INT FK NULL | References `users.id` |
| `shift_id` | INT FK NULL | References `shifts.id` |
| `quantity` | INT | Units added |
| `logged_at` | TIMESTAMP | Log timestamp |

---

## Customization

### Change Warranty Period
```php
// config.php
define('WARRANTY_DAYS', 30);   // Change to desired number of days
```

### Change Auto-Lock Timeout
```php
// config.php
define('AUTO_LOCK_MINUTES', 5);  // Minutes of inactivity before lock screen
```

### Add Product Categories
```php
// config.php
define('SHOE_CATEGORIES', ['Running', 'Casual', 'Athletic', 'Formal', 'Sneakers', 'Boots', 'Sandals']);
```

### Change Colors / Branding
All colors are CSS custom properties in `assets/css/style.css`:
```css
:root {
    --blue:   #1d4ed8;   /* Primary brand color */
    --accent: #e94560;   /* Active tab / highlight color */
    --green:  #059669;   /* Success / in-stock */
    --red:    #dc2626;   /* Error / out-of-stock */
    /* ... */
}
```

### Change Upload Limits
```php
// config.php
define('IMAGE_MAX_BYTES', 5 * 1024 * 1024);   // 5 MB
```
Also update `.htaccess` and your PHP `upload_max_filesize` / `post_max_size` settings.

---

## Troubleshooting

| Problem | Solution |
|---|---|
| **Blank page / 500 error** | Enable PHP error display temporarily: `php_flag display_errors On` in `.htaccess` |
| **Database connection failed** | Check `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` in `config.php` |
| **Image upload fails** | Check `uploads/` folder exists and is writable: `chmod 755 uploads/` |
| **Session not persisting** | Ensure cookies are enabled; check `session.save_path` in `php.ini` |
| **Real-time sync not working** | Check browser supports `fetch` API; ensure `sync_check.php` is reachable |
| **Long-poll blocking other requests** | `session_write_close()` must be called at the top of `sync_check.php` — already implemented |
| **HTTPS redirect not working** | Uncomment the redirect block in `.htaccess` and ensure `mod_rewrite` is enabled |
| **504 Gateway Timeout on sync** | Normal on some hosts — the system retries automatically; consider reducing long-poll timeout |
| **`setup.php` shows 403** | The setup flag `uploads/.setup_done` exists — delete it only if you need to re-run setup |

---

## License

MIT — free for personal and commercial use.

---

*Built for the Philippines 🇵🇭 — timezone: Asia/Manila (UTC+8), currency: ₱ (Philippine Peso)*
