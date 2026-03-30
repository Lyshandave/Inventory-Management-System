# 👟 Shoes Inventory Management System

A web-based inventory and sales management system for a small shoe store.
Built with plain PHP, MySQL, HTML, CSS, and vanilla JavaScript — no frameworks required.

> **Current version: v1.4** — Optimized, fully responsive, production-ready.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [User Roles](#user-roles)
- [Folder Structure](#folder-structure)
- [File Descriptions](#file-descriptions)
- [Getting Started](#getting-started)
- [Default Accounts](#default-accounts)
- [Features](#features)
- [Cashier Shift System](#cashier-shift-system)
- [Real-Time Sync](#real-time-sync)
- [System Flowchart](#system-flowchart)
- [Scope](#scope)
- [Limitations](#limitations)
- [System Weaknesses](#system-weaknesses)
- [Possible Questions from Sir (Defense Guide)](#possible-questions-from-sir-defense-guide)
- [Bug Fixes (v1.4)](#bug-fixes-v14)
- [Bug Fixes & Optimizations (v1.3)](#bug-fixes--optimizations-v13)
- [Bug Fixes & Optimizations (v1.2)](#bug-fixes--optimizations-v12)
- [Bug Fixes (v1.1)](#bug-fixes-v11)
- [Database Schema](#database-schema)

---

## Overview

This system allows a shoe store to manage inventory, record sales, process refunds,
track profit, and monitor cashier performance — all from a clean, role-based web interface
accessible on desktop, tablet, and mobile.

---

## Tech Stack

| Layer      | Technology                                        |
|------------|---------------------------------------------------|
| Backend    | PHP 8.1+                                          |
| Database   | MySQL 5.7+ / MariaDB 10.4+                        |
| Frontend   | HTML5, CSS3, Vanilla JavaScript (ES5-compatible)  |
| CSS Reset  | Normalize.css v8.0.1 (inlined via `@import`)      |
| Prefixing  | Autoprefixer-style vendor prefixes (`-webkit-`)   |
| Icons      | Font Awesome 6.4                                  |
| Server     | Apache (XAMPP / WAMP / Laragon)                   |

---

## User Roles

The system has two roles. Each role sees a different interface.

### Admin
Full access to everything:
- View dashboard (products, stock, sales, revenue, net profit)
- Add, edit, and archive products
- Set cost price and selling price per product
- View full sales history with profit tracking
- View cashier daily reports (who sold what, per shift)
- Manage user accounts (create, edit, activate/deactivate)
- Process refunds

### Cashier
Focused on selling only:
- View dashboard — active products only (no archived tab, no archive/restore buttons)
- Add stock to existing products
- Record sales
- Process refunds (within warranty period)
- End shift (records shift summary, starts a new shift — stays logged in)
- Lock screen (password-protected, shift stays active — for breaks/meals)

> **Why does the cashier not see Archived products?**
> Archived products are items the admin has removed from selling
> (discontinued, seasonal, etc.). The cashier only needs to see what
> is currently available for sale. Archiving is an admin task.

---

## Folder Structure

```
shoes_inventory/
│
├── index.php                # Login page (entry point)
├── setup.php                # One-time setup — creates tables and inserts sample Nike products
├── config.php               # Database config, helper functions, shift management
├── database.sql             # Full SQL schema + 20 Nike PH sample products
├── .htaccess                # Production: security headers, caching, compression, file protection
│
├── uploads/                 # Product images (auto-created by setup.php)
│
├── assets/
│   ├── css/
│   │   ├── normalize.css    # Normalize.css v8.0.1 — cross-browser baseline reset
│   │   └── style.css        # All styles — responsive, print-ready, autoprefixed, imports normalize.css
│   └── js/
│       ├── main.js          # All frontend JS — modals, AJAX, table filtering
│       └── cashier_report.js # Shift detail modal for the Cashier Report page
│
└── admin/
    ├── _navbar.php          # Shared navigation bar (role-aware, included by all pages)
    ├── admin.php            # Dashboard
    ├── add_product.php      # Add new product (admin only)
    ├── edit_product.php     # Edit existing product (admin only)
    ├── sales_history.php    # Sales history + print report (admin only)
    ├── cashier_report.php   # Per-cashier daily shift report (admin only)
    ├── manage_users.php     # Create and manage user accounts (admin only)
    ├── add_stock.php        # AJAX — add stock to a product
    ├── sell_product.php     # AJAX — record a sale
    ├── archive_product.php  # AJAX — archive or restore a product (admin only)
    ├── process_refund.php   # AJAX — process a refund
    ├── dashboard_stats.php  # AJAX — live dashboard stats
    ├── products_sync.php    # AJAX — real-time stock levels for all products
    ├── sync_check.php       # Long-poll — cross-device real-time sync signal
    ├── end_shift.php        # AJAX — end current shift, start new one (cashier)
    ├── lock.php             # Lock screen — password to resume, shift stays active
    └── logout.php           # End shift + destroy session + redirect to login
```

---

## File Descriptions

### Root files

| File           | Purpose |
|----------------|---------|
| `index.php`    | Login page. Authenticates username/password. Starts a shift automatically on cashier login. Redirects to dashboard on success. |
| `setup.php`    | Run once to create all database tables and default user accounts. Delete after use. |
| `config.php`   | Database credentials, constants, and all shared PHP helper functions: session management, shift management, input validation, image handling, formatting utilities. |
| `database.sql` | Complete SQL schema — creates `users`, `products`, `shifts`, and `transactions` tables. |

### `admin/` — Pages

| File                  | Who can access  | Purpose |
|-----------------------|-----------------|---------|
| `admin.php`           | Admin + Cashier | Dashboard. Summary cards (products, stock, sales, revenue). Admin also sees Net Profit card and Archived tab. |
| `add_product.php`     | Admin only      | Form to add a new product with full details and optional image. |
| `edit_product.php`    | Admin only      | Pre-filled form to edit an existing product. |
| `sales_history.php`   | Admin only      | All transactions with date range filters. Printable A4 landscape report. |
| `cashier_report.php`  | Admin only      | Daily report per cashier. Click "View" on any shift to see its individual transactions. |
| `manage_users.php`    | Admin only      | Create and manage user accounts. Cannot deactivate the last active admin. |
| `lock.php`            | Cashier only    | Lock screen. Session and shift remain active — no sales data is lost. |
| `_navbar.php`         | All             | Shared navigation bar, role-aware. |

### `admin/` — AJAX Endpoints (return JSON)

| File                    | Who can call    | Purpose |
|-------------------------|-----------------|---------|
| `add_stock.php`         | Admin + Cashier | Adds stock. Enforces max limit. Uses `FOR UPDATE` row lock. |
| `sell_product.php`      | Admin + Cashier | Records a sale, decrements stock, captures cost for profit tracking. |
| `archive_product.php`   | Admin only      | Archives or restores a product. |
| `process_refund.php`    | Admin + Cashier | Processes a refund within the 30-day warranty window. |
| `dashboard_stats.php`   | Admin + Cashier | Returns live dashboard card values. Profit only for admins. |
| `products_sync.php`     | Admin + Cashier | Returns current stock levels and best-seller ranks. |
| `sync_check.php`        | Admin + Cashier | Long-poll endpoint. Responds instantly when any data changes. |
| `end_shift.php`         | Cashier only    | Closes current shift, returns summary, opens a new shift. |

---

## Getting Started

### Requirements

- XAMPP, WAMP, or Laragon (PHP 8.1+ and MySQL/MariaDB)
- A modern web browser (Chrome, Firefox, Edge, Safari)

### Installation

**Step 1 — Copy the folder**

| Server  | Path |
|---------|------|
| XAMPP   | `C:/xampp/htdocs/shoes_inventory/` |
| WAMP    | `C:/wamp64/www/shoes_inventory/` |
| Laragon | `C:/laragon/www/shoes_inventory/` |

**Step 2 — Configure database credentials** (if needed)

Open `config.php` and update:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password
define('DB_NAME', 'shoes_inventory');
```

**Step 3 — Run setup**

```
http://localhost/shoes_inventory/setup.php
```

Setup will automatically:
- Create all 4 database tables (users, products, shifts, transactions, stock_logs)
- Insert 2 default user accounts (admin + cashier)
- Insert **20 Nike Philippines sample products** across 5 categories (Running, Casual, Sneakers, Athletic, Basketball)
- Create the `uploads/` folder

> ⚠️ **Delete `setup.php` immediately after setup.** The `.htaccess` also blocks direct access to it in production.

**Step 4 — Open the system**

```
http://localhost/shoes_inventory/
```

### CSS Files

The system uses two CSS files, both located in `assets/css/`:

| File | Purpose |
|------|---------|
| `normalize.css` | Normalize.css v8.0.1 — cross-browser baseline reset |
| `style.css` | All system styles (imports normalize.css via `@import`) |

> Both files must remain in the same folder. `style.css` automatically loads `normalize.css` via `@import url('normalize.css')` — no extra `<link>` tag needed in PHP files.

### Production Deployment Notes

When deploying to a live server (online hosting):

1. **HTTPS** — The session cookie `secure` flag is automatically enabled when the site runs over HTTPS. No config change needed.
2. **Database credentials** — Update `DB_USER` and `DB_PASS` in `config.php` to your hosting credentials. Do **not** use `root` with an empty password on production.
3. **Delete `setup.php`** — Remove it immediately after first-time setup.
4. **File permissions** — Ensure `uploads/` folder is writable (`chmod 755` or `chmod 775`).
5. **PHP version** — Requires PHP 8.1+. Check with your host that this is available.

---

## Default Accounts

| Role    | Username  | Password     |
|---------|-----------|--------------|
| Admin   | `admin`   | `admin123`   |
| Cashier | `cashier` | `cashier123` |

---

## Features

### Inventory
- ✅ Add, edit, and archive products
- ✅ Upload product images (JPG/PNG, max 5 MB)
- ✅ Min/max stock limits with live validation hints
- ✅ Low-stock and out-of-stock dashboard alerts
- ✅ Cost price + selling price per product
- ✅ Live profit-per-unit preview when adding/editing
- ✅ Best-seller badges (Top 5 by units sold)
- ✅ Search by name, brand, category, or color
- ✅ Filter by category and brand with stock summary bar

### Sales
- ✅ Record sales with quantity stepper and real-time total
- ✅ Process refunds (30-day warranty)
- ✅ Refund conditions: Resellable (restocks) or Damaged (no restock)
- ✅ Revenue and net profit tracking
- ✅ Race condition protection via MySQL row-level locking

### Reporting
- ✅ Sales history with date range filters (All Time / Today / Week / Month / Custom)
- ✅ Per-cashier daily shift report
- ✅ Shift-level transaction detail (click View on any shift)
- ✅ Printable A4 landscape sales report

### Users & Roles
- ✅ bcrypt password hashing (cost=12)
- ✅ Two roles: Admin (full access) and Cashier (sell/stock only)
- ✅ Last admin protected from deactivation or role downgrade

### UX & Technical
- ✅ Fully responsive (mobile, tablet, desktop)
- ✅ Compatible with Chrome, Firefox, Edge, Safari (Normalize.css + vendor prefixes)
- ✅ Normalize.css v8.0.1 — consistent cross-browser baseline
- ✅ Autoprefixer-style `-webkit-` vendor prefixes on all transitions, transforms, animations, and flex properties
- ✅ No inline styles or inline JS event handlers
- ✅ No frameworks — plain ES5 vanilla JavaScript
- ✅ Keyboard accessible — `:focus-visible` outline on all interactive elements
- ✅ Modal body scrollable on small screens (`overflow-y: auto`, `max-height: 70vh`)
- ✅ Thin, functional scrollbars on tables and modals
- ✅ Session cookies auto-secured over HTTPS in production
- ✅ `session_write_close()` on all pages after session reads — no concurrent request blocking
- ✅ `.htaccess` — security headers, browser caching, Gzip compression, file protection
- ✅ **Gender filter** — Men shows Men + Unisex, Women shows Women + Unisex (exact match)
- ✅ **Best sellers sorted to top** — most-sold product is always row #1, re-sorted after every sale in real-time
- ✅ Auto-lock screen after 5 minutes of inactivity (cashier only)
- ✅ Cross-tab sync (BroadcastChannel — instant)
- ✅ Cross-device sync (long-poll — ~100ms latency)
- ✅ 20 Nike Philippines sample products pre-loaded across 5 categories

---

## Cashier Shift System

A **shift** is a work session. Every cashier sale is linked to a shift.

```
Login
  └─→ Orphaned open shift auto-closed (crash recovery)
  └─→ New shift starts automatically

  [Cashier records sales — all linked to current shift]

  Option A: End Shift
    └─→ Shift closes → Summary shown → New shift starts → Stay logged in

  Option B: Lock Screen
    └─→ Password prompt → Shift and session stay active → Unlock to continue

Logout
  └─→ Current shift closes → Session ends → Redirect to login
```

---

## Real-Time Sync

**Same browser (cross-tab):** BroadcastChannel API — 0ms delay.

**Different devices/browsers:** Long-poll on `sync_check.php` — each page holds an HTTP request open for up to 25 seconds. The server checks every 100ms and responds the moment any product or transaction changes. The browser auto-refreshes stock, stat cards, and sales tables, then immediately opens the next poll.

---

## System Flowchart

```
┌───────────────────────────────────────────────────────────────────┐
│                    ENTRY POINT: index.php                         │
│                       (Login Page)                                │
└───────────────────────────┬───────────────────────────────────────┘
                            │
             ┌──────────────▼──────────────┐
             │    Enter Username &          │
             │        Password             │
             └──────────────┬──────────────┘
                            │
             ┌──────────────▼──────────────┐
             │    Credentials Valid?         │
             └────┬─────────────────┬───────┘
                  │ NO              │ YES
           Show Error          Check Role
                         ┌────────┴────────┐
                         │                 │
                    ┌────▼────┐      ┌─────▼──────┐
                    │  ADMIN  │      │  CASHIER   │
                    └────┬────┘      └─────┬──────┘
                         │                 │
                         │     Close orphaned shift (if any)
                         │     Start new shift automatically
                         │                 │
             ┌───────────▼─────────────────▼─────────────┐
             │            DASHBOARD (admin.php)           │
             │                                            │
             │  ┌─────────┐ ┌─────────┐ ┌─────────┐      │
             │  │ Products│ │  Stock  │ │  Sales  │ ...  │
             │  │  (live) │ │  (live) │ │  (live) │      │
             │  └─────────┘ └─────────┘ └─────────┘      │
             │                                            │
             │  ⚠️ Low-Stock / Out-of-Stock Alert Banner   │
             │     (shown when qty ≤ min_stock for any    │
             │      active product — auto-hides when OK)  │
             │                                            │
             │  🏆 Best Sellers Top-Sorted (Rank 1 first) │
             │                                            │
             │  [Search] [Category ▼] [Brand ▼]           │
             │  [Gender ▼: All / Men+Unisex / Women+Unisex│
             │            / Unisex only]                  │
             │  [Active Tab] [Archived Tab] (admin only)  │
             │                                            │
             │  Products Table (sorted: best sellers top) │
             │  ┌─────────────────────────────────────┐   │
             │  │ Img│Name+Badge│Brand│Cat│Size│Color │   │
             │  │   Gender│Price│Stock│Status│Actions │   │
             │  │ [+Stock] [Sell] [Edit*] [Archive*]  │   │
             │  │            (* admin only)            │   │
             │  └─────────────────────────────────────┘   │
             └───┬────────────────────────────────┬───────┘
                 │                                │
     ┌───────────▼──────────────┐     ┌───────────▼──────────────┐
     │      ADD STOCK Modal     │     │       SELL Modal          │
     │                          │     │                           │
     │  Product: [name]         │     │  Product: [name]          │
     │  Current Stock: [qty]    │     │  In Stock: [qty]          │
     │  Maximum: [max]          │     │  Unit Price: ₱[price]     │
     │  Can Add: [max−current]  │     │                           │
     │                          │     │  Qty: [− 1 +]             │
     │  Qty: [− 1 +]            │     │  Total: ₱[calculated]     │
     │                          │     │                           │
     │  [Cancel] [Add Stock]    │     │  [Cancel][Confirm Sale]   │
     └───────────┬──────────────┘     └───────────┬──────────────┘
                 │                                │
                 ▼                                ▼
     POST add_stock.php                  POST sell_product.php
     • Validate max limit                • Validate stock available
     • BEGIN TRANSACTION                 • BEGIN TRANSACTION
     • SELECT FOR UPDATE (lock row)      • SELECT FOR UPDATE (lock row)
     • UPDATE products qty += n          • UPDATE products qty −= n
     • INSERT stock_logs row             • INSERT transactions row
     • COMMIT                            • COMMIT
     • Return new_stock                  • Return new_stock
                 │                                │
                 └──────────────┬─────────────────┘
                                │
             ┌──────────────────▼─────────────────────────┐
             │         REAL-TIME SYNC TRIGGERED            │
             │                                             │
             │  Same browser → BroadcastChannel (0ms)     │
             │  Other devices → Long-poll sync_check.php   │
             │                  (server responds ≤100ms    │
             │                   when DB changes detected) │
             │                                             │
             │  All open pages auto-refresh:               │
             │  • Stock quantities in table rows           │
             │  • Summary stat cards                       │
             │  • Low-stock / out-of-stock alert banner    │
             │  • Best-seller badges + table re-sort       │
             │  • Sales table (on Sales History page)      │
             └─────────────────────────────────────────────┘


══════════════════════ ADMIN-ONLY PAGES ════════════════════════════

  ┌─────────────────────────────────────────────────────────────┐
  │                  SALES HISTORY PAGE                         │
  │                                                             │
  │  [All Time] [Today] [This Week] [This Month] [Custom ▼]     │
  │  Custom: [Start Date ________] [End Date ________]          │
  │                                                             │
  │  Summary Cards (live-updated on tab switch):                │
  │  Total Sales│Items Sold│Revenue│Refunds│Net Revenue│Profit  │
  │                                                             │
  │  Transactions Table:                                        │
  │  Date│Product│Brand│Category│Qty│Unit Price│Total│          │
  │  Profit (color-coded)│Sold By│Status│[Refund]              │
  │                             │                               │
  │                      [Refund Button]                        │
  │                             │                               │
  │                  ┌──────────▼──────────┐                    │
  │                  │    REFUND MODAL      │                    │
  │                  │                     │                    │
  │                  │  Product: [name]    │                    │
  │                  │  Qty stepper: [n]   │                    │
  │                  │  Unit Price: ₱[n]   │                    │
  │                  │  Total: ₱[calc]     │                    │
  │                  │  Sale Date: [date]  │                    │
  │                  │                     │                    │
  │                  │  Condition:         │                    │
  │                  │  ○ Resellable       │                    │
  │                  │    → Item restocked │                    │
  │                  │  ○ Damaged          │                    │
  │                  │    → No restock     │                    │
  │                  │                     │                    │
  │                  │  Reason: [______]   │                    │
  │                  │  Warranty: 30 days  │                    │
  │                  │  (Server blocks if  │                    │
  │                  │   > 30 days old)    │                    │
  │                  │                     │                    │
  │                  │ [Cancel][Confirm]   │                    │
  │                  └─────────────────────┘                    │
  │                                                             │
  │  [🖨 Print Report] — A4 landscape, colors preserved         │
  └─────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────────────────┐
  │                   CASHIER REPORT PAGE                       │
  │                                                             │
  │  Filter: [Date: ________] [Cashier: All ▼]                  │
  │  (Auto-submits on change — no button needed)                │
  │                                                             │
  │  Daily Summary per Cashier:                                 │
  │  Cashier│Shifts│Transactions│Items Sold│Revenue             │
  │                                                             │
  │  Shift Breakdown:                                           │
  │  Cashier│Start Time│End Time│Duration│Transactions│Revenue  │
  │                                              [View]         │
  │                                               │             │
  │                          ┌────────────────────▼──────────┐  │
  │                          │    SHIFT DETAIL MODAL         │  │
  │                          │                               │  │
  │                          │  Time│Product│Brand│Qty│      │  │
  │                          │  Unit Price│Total│Status      │  │
  │                          │                               │  │
  │                          │  SHIFT TOTAL: ₱[total]       │  │
  │                          └───────────────────────────────┘  │
  └─────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────────────────┐
  │                    MANAGE USERS PAGE                        │
  │                                                             │
  │  Create / Edit User:                                        │
  │  Full Name | Username | Role [Admin/Cashier]                │
  │  Password | Confirm Password                                │
  │  [Save User]                                                │
  │                                                             │
  │  Existing Users:                                            │
  │  Name│Username│Role│Status│Created│[Edit][Activate/Deact]   │
  │                                                             │
  │  Server-enforced rules:                                     │
  │  ✗ Cannot deactivate the last active admin                  │
  │  ✗ Cannot downgrade the last active admin to cashier        │
  │  ✗ Cannot deactivate your own account                       │
  │  ✗ Usernames must be unique                                 │
  └─────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────────────────┐
  │              ADD / EDIT PRODUCT PAGES                       │
  │                                                             │
  │  Product Name* | Brand* | Category* | Size* | Color*        │
  │  Gender* [Men / Women / Unisex]                             │
  │  Cost Price (₱) | Selling Price (₱)*                        │
  │  Profit Preview: ₱[selling − cost] ([margin]%)              │
  │  Initial Stock | Min Stock | Max Stock                      │
  │  Image Upload (JPG/PNG ≤ 5 MB, optional)                    │
  │  Description (optional)                                     │
  │                                                             │
  │  Validation:                                                │
  │  • All starred fields required                              │
  │  • Price must be > 0                                        │
  │  • Cost price cannot be negative                            │
  │  • Stock must be 0 or within min–max range                  │
  │  • Max stock must be > min stock                            │
  │                                                             │
  │  [Save Product] [Cancel]                                    │
  └─────────────────────────────────────────────────────────────┘


══════════════════════ CASHIER ACTIONS ════════════════════════════

  ┌─────────────────────────────────────────────────────────────┐
  │                    SHIFT LIFECYCLE                          │
  │                                                             │
  │  Login ──→ Auto-close orphaned shift ──→ Start new shift   │
  │                      │                                     │
  │             [Recording sales / adding stock]               │
  │                      │                                     │
  │       ┌──────────────┼──────────────────┐                  │
  │       │              │                  │                  │
  │  [End Shift]   [Lock Screen]        [Logout]               │
  │       │              │                  │                  │
  │  Show summary  Password prompt   Close shift               │
  │  Start new     Shift stays       End session               │
  │  shift         active            Redirect to login         │
  │  Stay logged in Unlock to continue                         │
  │                                                             │
  │  AUTO-LOCK TIMER (cashier only):                           │
  │  5 min idle ──→ 30-second countdown overlay                │
  │              ──→ [I'm Still Here] to dismiss               │
  │              ──→ Auto-redirect to lock.php if no action    │
  └─────────────────────────────────────────────────────────────┘
```


## Scope

This system is designed for a **single-store** shoe shop managed by an admin/owner with one or more cashier staff. It covers the full daily operations of a small shoe retail business.

### What the system CAN do

#### Inventory Management
- Add, edit, and archive shoe products (name, brand, category, size, color, gender, description, image)
- Set cost price (supplier/purchase price) and selling price per product
- Live profit-per-unit preview (₱ amount + margin %) when adding or editing
- Configure minimum stock threshold and maximum stock limit per product
- Archive discontinued/seasonal products without deleting them; restore anytime
- Active and Archived product tabs on the dashboard (admin only sees Archived tab)

#### Stock Management
- Add stock manually per product (admin or cashier)
- System enforces maximum stock limit — rejects additions that would exceed it
- Real-time stock updates across all open tabs and devices (~100ms latency)
- **Low-stock and out-of-stock alert banner** on the dashboard — appears automatically when any active product's quantity is at or below its minimum stock threshold, and shows exactly how many products are affected
- Best-seller badges (Top 5 by total units sold, Rank 1–5 with gold/silver/bronze labels)
- **Best sellers sorted to the top of the product table** — re-sorted after every sale in real-time

#### Sales & Transactions
- Record a sale for any in-stock product with a quantity stepper
- Validates stock availability before confirming (server-side, with `SELECT FOR UPDATE` locking)
- Every sale records: product, quantity, unit price frozen at time of sale, unit cost, total, cashier name, shift ID
- Real-time total preview before confirming
- Gender-aware product filter: Men shows Men + Unisex, Women shows Women + Unisex

#### Refund Processing
- Refunds accepted within the 30-day warranty window (server enforces — not just frontend)
- Partial refunds supported (refund a subset of the original quantity)
- Resellable condition → item restocked immediately; Damaged → stock not restored
- Optional refund reason field
- Refunds past 30 days are blocked server-side with a clear error message

#### Dashboard & Statistics
- Live summary cards: total products, total stock, total sales, total revenue
- Admins also see Net Profit; cashiers see only their current shift's stats
- Low-stock / out-of-stock alert banner (auto-shown, auto-hidden)
- Product search by name, brand, category, and color
- Filter by category, brand, and gender (exact match, no false positives)

#### Sales History & Reporting
- Full transaction history: All Time / Today / This Week / This Month / Custom date range
- Profit column per transaction with color coding (green = profit, red = loss)
- Summary stats: Total Sales, Items Sold, Revenue, Refunds, Net Revenue, Profit
- Printable A4 landscape report with summary header and totals footer
- Cashier name shown per transaction

#### Cashier Report (Admin Only)
- Per-cashier daily performance: shifts worked, transactions, items sold, revenue
- Per-shift detail: start time, end time, duration, transaction count, revenue
- Click View on any shift to see every individual transaction inside it

#### User & Role Management (Admin Only)
- Create users with username, full name, role, and password
- Two roles: Admin (full access) and Cashier (sell/stock only)
- Edit full name, role, and password; activate or deactivate accounts
- Cannot deactivate or downgrade the last active admin
- Cannot deactivate your own account
- Passwords hashed with bcrypt (cost factor 12)

#### Cashier Shift System
- Shift auto-starts on cashier login
- Orphaned shifts from unexpected browser close auto-closed on next login
- End Shift shows a summary and starts a new shift (cashier stays logged in)
- Lock Screen keeps shift active — password required to resume (for breaks)
- Auto-lock after 5 minutes of inactivity (cashier only, with 30-second countdown)
- Logout properly closes the shift before ending the session

#### Real-Time Sync
- Same browser (cross-tab): BroadcastChannel API (instant, 0ms)
- Different devices/browsers: long-poll on `sync_check.php` (~100ms latency)
- Syncs: stock quantities, stat cards, low-stock alerts, best-seller ranks, sales table

#### Security
- Prepared statements on every query — SQL injection protected
- `htmlspecialchars()` on all output — XSS protected
- HttpOnly + SameSite=Strict session cookies
- Session cookie `Secure` flag auto-enabled on HTTPS
- `session_write_close()` after all session reads — no request blocking
- Role-based access control enforced server-side on every page and AJAX endpoint
- Bcrypt password hashing (cost 12)
- `.htaccess` blocks direct access to `config.php`, `database.sql`, `setup.php`, and all `.sql`/`.log`/`.env` files
- `.htaccess` sets security headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`


## Limitations

### 1. Single Store Only
No support for multiple branches or warehouses. All products and stock belong to one store. A second branch requires a separate installation with its own database.

### 2. Requires Local Server (Not Internet-Ready Out of the Box)
Built for XAMPP/WAMP/Laragon on a local network. The `.htaccess` and secure session cookies provide a solid security baseline, but public internet deployment also requires: a proper domain with HTTPS/SSL, a strong database password (not `root` with no password), and ideally a login rate limiter.

### 3. No Barcode Scanner or Receipt Printer
Sales are recorded manually by selecting a product and entering a quantity. No barcode scanner input is supported, and no customer receipt or invoice is generated — only a printable admin sales report is available.

### 4. No Customer Accounts or Purchase History
Transactions are not linked to customer profiles. There is no loyalty program, repeat-customer tracking, or per-customer purchase history.

### 5. No Product Variants or Bundles
Each size/color combination is a separate product record. There is no grouped variant system (e.g., one product with size options). Bundle deals or combo pricing are also not supported.

### 6. No Supplier or Purchase Order Tracking
Stock is added manually by entering a quantity. There is no supplier database, delivery/shipment records, or formal purchase order workflow.

### 7. No Notification Outside the Dashboard
The dashboard shows a low-stock alert banner when any product's quantity is at or below its minimum threshold. However, this requires someone to have the dashboard open — there are no email, SMS, or push notifications sent automatically to alert staff when stock drops.

### 8. No Data Export (CSV / Excel)
Sales history and cashier reports can be printed as an A4 report but cannot be exported to CSV, Excel, or any downloadable file format from within the system.

### 9. No Built-In Database Backup
No backup or restore feature exists in the system interface. Database backups must be done manually using phpMyAdmin or the `mysqldump` command on the server.

### 10. Lock Screen Does Not Block Direct URL Access in New Tabs
The Lock Screen is a convenience feature for short breaks — it locks the current tab. It does not prevent someone from opening a new browser tab and navigating to `admin.php` directly if the session is still active. For unattended terminals, always log out fully.

### 11. No Charts, Graphs, or Trend Analytics
All reporting is tabular. There are no line charts, bar graphs, trend lines, demand forecasting, or visual analytics of any kind.

### 12. No Discount or Promo Support
There is no discount code system, percentage-off promotions, or price override at the point of sale. Every sale is recorded at the product's current selling price.

### 13. Refunds Limited to 30-Day Warranty Window
The system enforces a 30-day refund policy at the server level. Refunds for transactions older than 30 days are automatically rejected, even if the admin wants to approve one manually.


## System Weaknesses

Honest trade-offs made to keep the system simple for a small business use case.

### 1. Flat Product Structure
Each size/color/variant is its own product entry. A store with 50 models × 10 sizes × 3 colors = 1,500 rows. The product list becomes very long with no variant grouping or drill-down view.

### 2. Manual Stock Entry Only
Each product must be restocked individually. No bulk stock import from a spreadsheet. Receiving a large shipment across many products is slow to enter.

### 3. No Offline Support
Requires an active connection to the local server. If the server goes down, the system is completely unavailable. No offline mode, local caching, or PWA capability.

### 4. Long-Poll Has Server Cost
Every open browser tab holds one HTTP request open for up to 25 seconds. With 10 tabs open simultaneously, that is 10 persistent PHP worker connections at all times. On a resource-constrained server this can exhaust available PHP workers. Acceptable for a small store (2–5 terminals), but does not scale to large deployments.

### 5. No Audit Log for Product or User Changes
There is no record of who changed what and when — beyond transaction rows. If an admin edits a product's price, cost, or quantity directly, there is no history of the previous value. Mistakes made in the Edit Product form are not recoverable without a manual database backup.

### 6. Session Tied to Browser Tab
Sessions are PHP file-based. If a cashier closes the browser without logging out, the session remains open until PHP's natural session expiry. The orphan-shift recovery handles this on the cashier's next login, but an admin cannot view active sessions or force-logout a specific user remotely.

### 7. Image Storage is Local
Product images are stored in `/uploads/` on the server. If the server is replaced, the folder is not backed up, or a fresh installation is done, all product images are permanently lost.

### 8. No Login Rate Limiting
The login page does not throttle failed attempts or lock accounts after repeated failures. Brute-force attacks are not blocked. This is acceptable on a closed local network but is a real risk if the system is ever exposed to the internet without an additional layer (e.g., `.htaccess` IP restriction, a reverse-proxy with rate limiting, or Fail2Ban).

### 9. Profit Accuracy Depends on Cost Entry
Net profit calculations rely on the admin entering an accurate cost price per product. If cost price is left at ₱0.00 (the default), the system reports 100% profit margin on every sale for that product. There is no warning or enforcement that cost price must be filled in.

### 10. No Scheduled or Automated Reports
The admin must manually open the system and navigate to the Sales History or Cashier Report pages. There is no daily email summary, no automatic end-of-day report generation, and no scheduled export.


## Possible Questions from Sir (Defense Guide)

### 📌 General

**Q: What is the main purpose of your system?**
> A web-based inventory and sales management tool for a small shoe store. It tracks product stock, records sales, monitors cashier performance through shifts, and gives the admin a real-time view of revenue and profit.

**Q: What technologies did you use and why?**
> PHP for the backend — widely supported, easy to learn, works well with MySQL. Plain HTML, CSS, and vanilla JavaScript on the frontend — no frameworks needed, so the system is fast and easy to understand without a build process. MySQL for all data storage.

**Q: Why a web-based system instead of a desktop application?**
> A web-based system can be accessed from any device on the local network — the admin can check the dashboard from a phone while the cashier uses the desktop. Nothing to install per machine, and updates are instant because everyone shares the same server files.

---

### 📌 Database

**Q: What tables do you have and how are they related?**
> Four tables: `users` (accounts and roles), `products` (inventory), `shifts` (cashier work sessions), and `transactions` (every sale and refund). Transactions link to the product sold, the user who sold it, and the shift it happened in — through foreign keys.

**Q: Why store unit_price and unit_cost inside transactions instead of just referencing the product?**
> Because product prices can change. If we only stored the product ID and looked up the current price, every historical profit calculation would become wrong the moment the admin edits a price. Freezing the price and cost at the time of sale preserves accurate financial history.

**Q: What if two cashiers try to sell the last item at the exact same time?**
> We use a MySQL `SELECT ... FOR UPDATE` transaction. The product row is locked when a sale starts processing. The second request waits until the first finishes. If the first sale already consumed the last unit, the second sees zero stock and gets an "Out of Stock" error. No double-selling is possible.

**Q: Why is there a shifts table? Can't you just look at who made each transaction?**
> The shifts table groups transactions by work session, not just by date or user. One cashier might work two separate sessions in a day. The shift table lets the admin see each session separately — start time, end time, and everything sold — which is more accurate than filtering by user and date alone.

---

### 📌 Features

**Q: What is the difference between archiving and deleting a product?**
> Archiving hides the product from selling but keeps the record and all its sales history in the database. Deleting would break historical transaction data — the system would show sales for a product that no longer exists. We use archiving to preserve data integrity.

**Q: How does your refund system work?**
> The admin or cashier opens the refund modal on a sold transaction. They select whether the item is resellable (stock goes back) or damaged (stock not restored). The system checks the sale date — if more than 30 days have passed, the server blocks the refund with a "Warranty expired" error. The status is updated in the transaction record.

**Q: Why Lock Screen instead of logging out for breaks?**
> Logging out starts a new shift on re-login. All sales before and after the break end up in separate shifts. Lock Screen keeps the current shift active — only a password prompt appears. All sales for the day stay in one shift record, giving the admin an accurate per-session view of cashier performance.

**Q: How does real-time sync work without WebSockets?**
> Two mechanisms. For tabs on the same browser: BroadcastChannel API — one tab posts a message and all others update instantly. For different devices: long-polling — each page holds an HTTP request open for up to 25 seconds. The server checks the database every 100ms and responds the moment anything changes. The browser refreshes, then immediately opens the next poll.

**Q: Why not WebSockets?**
> WebSockets need a persistent background process to manage connections — not available on standard XAMPP/WAMP. Long-polling achieves the same result with regular HTTP requests that Apache already handles. For a small store, the ~100ms latency is imperceptible and needs no extra server software.

---

### 📌 Security

**Q: How do you prevent SQL injection?**
> All queries use PHP MySQLi prepared statements with bound parameters. User input is never concatenated into a SQL string — it is always a typed parameter passed after the query structure is already compiled by the database.

**Q: How are passwords stored?**
> Using PHP's `password_hash()` with `PASSWORD_BCRYPT` at cost factor 12. The hash is computationally expensive to crack and is automatically salted. Plain text passwords are never stored or logged.

**Q: Can a cashier access admin pages by typing the URL?**
> No. Every admin-only page starts with `require_login('admin')` which checks the server-side session role. If the session does not have `user_role === 'admin'`, the request is rejected server-side. Bypassing it from the browser is not possible because the check runs on the server, not in JavaScript.

---

### 📌 Limitations and Weaknesses

**Q: What are the main limitations?**
> Single store only; requires local server (not internet-ready without HTTPS + strong credentials); no barcode scanner or receipt printer; no customer accounts; no product variant grouping; no notifications outside the dashboard (low-stock banner visible only when the system is open); no CSV/Excel export; no built-in backup; no charts or analytics; no discounts or promos; refunds limited to 30-day warranty window.

**Q: What would you improve if you had more time?**
> Product variant system (one product with multiple sizes/colors), barcode scanner support, CSV/Excel export, email alerts for low stock, an audit log, cloud deployment with HTTPS, and discount/promo support.

**Q: Can this handle a busy sales day with many simultaneous transactions?**
> For a small store, yes. Row-level locking prevents conflicts between simultaneous sales. The long-poll uses about one persistent connection per open page — 3–5 terminals is well within XAMPP's defaults. For high-volume with 20+ simultaneous terminals, the long-poll would need to be replaced with WebSockets and a more capable server.

**Q: What if the server crashes mid-transaction?**
> We use MySQL transactions (`BEGIN TRANSACTION ... COMMIT`). If the server crashes between the stock update and the transaction insert, MySQL rolls back the incomplete operation on restart. There will never be a case where stock was deducted but no sale was recorded — or a sale recorded but no stock deducted.

**Q: What happens if a cashier closes the browser without logging out?**
> The session expires naturally based on PHP session lifetime. On next login, the system checks for orphaned open shifts belonging to that user and auto-closes them before starting a fresh shift. No transactions are lost.

**Q: Your system has no charts. Is it still useful for a store owner?**
> Yes — the store owner can see all key numbers at a glance (total products, stock, sales, revenue, profit) on the dashboard. The sales history with date filters and the printable report cover daily, weekly, and monthly reporting needs. Charts would be a valuable addition but are not required for the core business operations the system supports.

---

## Bug Fixes (v1.4)

| # | File | Bug | Fix |
|---|------|-----|-----|
| 1 | `assets/js/main.js` | **Gender filter wrong results** — `"women".indexOf("men") !== -1` was `true`, so filtering "Men" also showed Women's products. Root cause: using `.indexOf()` for gender matching instead of exact `===` comparison | Fixed with exact `rowGender === gender` match. Unisex products now correctly appear under both Men and Women filters (since Unisex fits all), but not under each other |
| 2 | `assets/js/main.js` | **Category & Brand filter partial match** — `"running".indexOf("run")` would match, causing unexpected results with similar category names | Changed `matchCat` and `matchBrand` from `.indexOf()` to `===` exact match |
| 3 | `assets/js/main.js` | **Best sellers not sorted to top** — Products with best-seller badges were displayed in random order (newest first). The most-sold product should always appear at the top of the table | Added `sortTableByBestSeller()` function that re-orders the table rows: Rank 1 (best seller) → top, Rank 2–5 → follow, no rank → bottom. Called automatically on every filter, sale, refund, stock update, and real-time sync |
| 4 | `assets/js/main.js` | **Best-seller rank not updated in real-time** — After a sale, the badge updated but the row position didn't change until page reload | `refreshBestSellerBadges()` now updates `data-bs-rank` on each row then calls `sortTableByBestSeller()` — table re-sorts instantly after every transaction |
| 5 | `admin/admin.php` | **Missing `data-bs-rank` attribute** — The new sort function reads `row.dataset.bsRank` but the attribute wasn't rendered by PHP | Added `data-bs-rank="<?= $bsRank ?>"` to every product `<tr>` |
| 6 | `assets/js/main.js` | **Brand bar count wrong** — `updateBrandBar` used `.indexOf(brand)` which could count brands with similar names | Fixed to `=== brand` exact match |

---

## Bug Fixes & Optimizations (v1.3)

| # | File | Change | Details |
|---|------|--------|---------|
| 1 | `database.sql` | **20 Nike Philippines sample products added** | Covers all categories: Running (5), Casual (5), Sneakers (4), Athletic/Training (3), Basketball (2), with realistic PH prices (₱3,995 – ₱13,995), sizes, colors, stock levels, and full descriptions |
| 2 | `setup.php` | **Same 20 Nike products** inserted automatically on setup | Products are only inserted if the table is empty — safe to re-run. Uses prepared statements for security. Shows count of inserted products in setup log |
| 3 | `admin/edit_product.php` | **`SELECT *` replaced with explicit column list** | `SELECT *` is a bad practice that fetches unnecessary data and breaks if columns are reordered. Now selects only the 15 columns the form actually uses |
| 4 | `admin/manage_users.php` | **`session_write_close()` added** | Session lock was held for the entire page render (including DB queries and HTML output), blocking any concurrent AJAX request from the same user. Now released immediately after all session reads are done |
| 5 | `admin/manage_users.php` | **Duplicate `$currentUserId` assignment removed** | `$currentUserId` was assigned twice — once correctly before `session_write_close()`, and once redundantly after. Second assignment removed |
| 6 | `.htaccess` | **New file — production security and performance** | Blocks directory listing; blocks direct access to `config.php`, `database.sql`, `setup.php`, `README.md`, and all `.sql`/`.log`/`.env` files; sets security headers (`X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`); enables browser caching for CSS/JS (1 week) and images (1 month); enables Gzip compression; configures PHP production settings (`display_errors Off`); includes commented HTTPS redirect for easy activation |

---

## Bug Fixes & Optimizations (v1.2)

| # | File | Change | Details |
|---|------|--------|---------|
| 1 | `assets/css/normalize.css` | **New file** — Normalize.css v8.0.1 | Ensures consistent base rendering across all major browsers (Chrome, Firefox, Safari, Edge) |
| 2 | `assets/css/style.css` | **Normalize.css imported** | `@import url('normalize.css')` added at top of stylesheet — applies normalize before any custom rules |
| 3 | `assets/css/style.css` | **Autoprefixer vendor prefixes** | Added `-webkit-transition`, `-webkit-transform`, `-webkit-animation`, `@-webkit-keyframes`, `-webkit-flex`, `-webkit-flex-wrap`, `-webkit-flex-direction`, `-webkit-align-items`, `-webkit-justify-content`, `-webkit-flex-shrink`, `-webkit-align-self`, and `display: -webkit-flex` / `display: -webkit-inline-flex` throughout the entire stylesheet — ensures compatibility with older Safari and WebKit browsers |
| 4 | `assets/css/style.css` | **Bug fix — brace imbalance** | The automated `@-webkit-keyframes` duplication produced broken blocks missing `to {}` and closing `}`. Fixed all three keyframe blocks: `toastBar`, `modalIn`, `fadeInUp` |
| 5 | `assets/css/style.css` | **Bug fix — scrollbar UX** | Global `* { scrollbar-width: none }` was hiding ALL scrollbars including the horizontal table scroll and vertical modal scroll, making content inaccessible on small screens. Replaced with thin, styled scrollbars on `html`, `.table-wrap`, and `.modal-body` |
| 6 | `assets/css/style.css` | **Bug fix — CSS cascade order** | `.modal-body` base rule was declared *after* `.modal-lg .modal-body`, causing the base `padding: 1.25rem` to override `.modal-lg`'s intentional `padding: 0`. Reordered so base comes before modifier |
| 7 | `assets/css/style.css` | **Added `overflow-y: auto` to `.modal-body`** | Modals now scroll internally on small/mobile screens instead of overflowing the viewport |
| 8 | `assets/css/style.css` | **Keyboard focus styles** | Added `:focus-visible` outline rules for buttons, links, and focusable elements — improves keyboard navigation accessibility without affecting mouse users |
| 9 | `admin/admin.php` | **Bug fix — empty-state colspan** | Empty state row used `colspan="10"` but the products table has **11 columns** (Image, Product, Brand, Category, Size, Color, Gender, Price, Stock, Status, Actions). Fixed to `colspan="11"` |
| 10 | `config.php` | **Production-ready session cookies** | `'secure' => false` changed to `'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'` — cookies are automatically marked Secure when served over HTTPS (production), and left plain for local HTTP development |

---

## Bug Fixes (v1.1)

| # | File | Bug | Fix |
|---|------|-----|-----|
| 1 | `main.js` | Dead code — `BEST_SELLER_BADGES` had a rank 6 entry (ranks only go 1–5) | Removed rank 6 |
| 2 | `main.js` | Color not included in search — searching "Black" returned nothing | Added `data-color` to search text |
| 3 | `main.js` | `closeModal()` did not reset the Cashier Logout modal — stale state on reopen | Added logout modal reset |
| 4 | `main.js` | Toast durations too short — success 1s, error/warning 2.5s | Success → 2.5s, Error/Warning → 4.5s |
| 5 | `main.js` | Navbar hamburger did not close when clicking outside | Added click-outside listener |
| 6 | `admin.php` | Product rows missing `data-color` — color search fix had no data to match | Added `data-color` to all `<tr>` rows |
| 7 | `add_stock.php` | `updated_at` not explicitly set — sync detection could miss stock changes | Added `updated_at = NOW()` |
| 8 | `sell_product.php` | Same sync issue | Added `updated_at = NOW()` |
| 9 | `process_refund.php` | Warranty check off-by-one near midnight due to DATETIME time component | Changed to date-only comparison |
| 10 | `process_refund.php` | Same `updated_at` sync issue | Added `updated_at = NOW()` |
| 11 | `cashier_report.js` | Shift total qty counted refunded items — inflated "items sold" in modal | Only count `status === 'Sold'` rows |
| 12 | `style.css` | Summary cards could overflow on 320px screens | Changed to `minmax(min(180px, 100%), 1fr)` |
| 13 | `style.css` | Mobile table scroll triggered browser back/forward | Added `overscroll-behavior-x: contain` |
| 14 | All HTML pages | No `theme-color` meta — mobile browser chrome did not match app color | Added `<meta name="theme-color">` to all 8 pages |

---

## Database Schema

### `users`

| Column       | Type                    | Description                  |
|--------------|-------------------------|------------------------------|
| `id`         | INT PK                  | Auto-increment               |
| `username`   | VARCHAR(60) UNIQUE      | Login username               |
| `password`   | VARCHAR(255)            | bcrypt hash (cost=12)        |
| `full_name`  | VARCHAR(120)            | Display name                 |
| `role`       | ENUM('admin','cashier') | Determines access level      |
| `is_active`  | TINYINT(1)              | 1 = active, 0 = deactivated  |
| `created_at` | TIMESTAMP               | When the account was created |

### `products`

| Column         | Type          | Description                          |
|----------------|---------------|--------------------------------------|
| `id`           | INT PK        | Auto-increment                       |
| `product_name` | VARCHAR(255)  | Name of the shoe                     |
| `brand`        | VARCHAR(100)  | Brand                                |
| `category`     | VARCHAR(100)  | Category (Running, Casual, etc.)     |
| `size`         | VARCHAR(20)   | Shoe size                            |
| `color`        | VARCHAR(50)   | Color                                |
| `cost_price`   | DECIMAL(10,2) | Purchase/supplier price (for profit) |
| `price`        | DECIMAL(10,2) | Selling price (₱)                    |
| `quantity`     | INT           | Current stock                        |
| `min_stock`    | INT           | Low-stock warning threshold          |
| `max_stock`    | INT           | Maximum stock allowed                |
| `description`  | TEXT          | Optional description                 |
| `image`        | VARCHAR(255)  | Uploaded image filename              |
| `is_archived`  | TINYINT(1)    | 0 = active, 1 = archived             |
| `created_at`   | TIMESTAMP     | When added                           |
| `updated_at`   | TIMESTAMP     | Last modified (used by sync_check)   |

### `shifts`

| Column       | Type      | Description                                 |
|--------------|-----------|---------------------------------------------|
| `id`         | INT PK    | Auto-increment                              |
| `user_id`    | INT FK    | References `users.id`                       |
| `started_at` | TIMESTAMP | When the shift started (login or End Shift) |
| `ended_at`   | TIMESTAMP | When the shift ended (null = still active)  |

### `transactions`

| Column          | Type          | Description                                             |
|-----------------|---------------|---------------------------------------------------------|
| `id`            | INT PK        | Auto-increment                                          |
| `product_id`    | INT FK        | References `products.id`                                |
| `sold_by`       | INT FK        | References `users.id` — who made the sale               |
| `shift_id`      | INT FK        | References `shifts.id` — which shift this belongs to    |
| `quantity_sold` | INT           | Units sold                                              |
| `unit_price`    | DECIMAL(10,2) | Selling price frozen at time of sale                    |
| `unit_cost`     | DECIMAL(10,2) | Cost price frozen at time of sale (for profit tracking) |
| `total_price`   | DECIMAL(10,2) | quantity × unit_price                                   |
| `total_cost`    | DECIMAL(10,2) | quantity × unit_cost                                    |
| `status`        | ENUM          | `Sold`, `Refunded (Restocked)`, `Refunded (Damaged)`    |
| `refund_reason` | TEXT          | Optional reason for refund                              |
| `sale_date`     | TIMESTAMP     | When the transaction was recorded                       |
| `updated_at`    | TIMESTAMP     | Last modified (used by sync_check for change detection) |

---

*Built as a simple inventory and sales management tool for small shoe store operations.*
