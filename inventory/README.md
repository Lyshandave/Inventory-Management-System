# 👟 Shoes Inventory Management System

## File Structure

```
inventory/
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
│   ├── sales_history.php      # Sales history with date range filters
│   ├── cashier_report.php     # Per-cashier shift breakdown
│   ├── manage_users.php       # Create/edit/deactivate user accounts
│   ├── lock.php               # Cashier lock screen
│   ├── logout.php             # Session destroy + redirect
│   ├── _navbar.php            # Shared navigation partial
│   │
│   ├── sell_product.php       # AJAX: record a sale
│   ├── add_stock.php          # AJAX: add inventory
│   ├── archive_product.php    # AJAX: archive/restore product
│   ├── process_refund.php     # AJAX: full or partial refund
│   ├── end_shift.php          # AJAX: end cashier shift + start new one
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
│       └── cashier_report.js  # Cashier report page JS
│
└── uploads/                   # Product images (auto-created, git-ignored)
```

---

## Default Login Credentials

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Cashier | `cashier` | `cashier123` |

> **Palitan agad ang passwords pagkatapos ng setup.**

---

## User Roles

### Admin
- Full access sa lahat ng pages at features
- Makakakita ng all-time sales stats at profit
- Makakapagtanggal, mag-edit, mag-archive, at mag-restore ng produkto
- Makakapag-proseso ng refund
- Makakapag-manage ng user accounts (create, edit, deactivate)
- Makakatanggap ng cashier reports para sa lahat ng shifts
- Walang shift management — walang lock screen, hindi sila nagtatanda ng shift

### Cashier
- Dashboard: makakatanggal, makakabenta, makakapag-add ng stock
- Shift management: auto-start sa login, makakatapus ng shift, makakalock ng screen
- Makakakita lang ng sarili nilang shift stats sa dashboard
- Hindi ma-access ang: Sales History, Cashier Report, Manage Users, Add/Edit/Archive Products

---

## Key Functionality

### Pagbebenta ng Produkto

1. I-click ang **sell button** (cash register icon) sa product row
2. I-adjust ang quantity gamit ang stepper — auto-compute ang total
3. I-click ang **Confirm Sale** — dine-decrement agad ang stock, nire-record ang transaction, at nag-ti-trigger ng real-time sync sa lahat ng bukas na tabs at devices

### Pagdagdag ng Stock

1. I-click ang **+ button** (berde) sa product row
2. Ilagay ang quantity — naka-enforce ang max stock limit
3. I-click ang **Add Stock** — na-update agad, naka-log sa `stock_logs` table kasama ang cashier at shift info

### Pag-proseso ng Refund

1. Pumunta sa **Sales History** → i-click ang **undo button** sa isang `Sold` transaction
2. Piliin ang quantity na ire-refund, kondisyon ng item (Resellable / Damaged), at optional na dahilan
3. Resellable → awtomatikong nadadagdag ang stock. Damaged → nire-record bilang refunded pero hindi nadadagdag
4. Warranty window: **30 araw** mula sa sale date (configurable sa `config.php`)
5. Partial refund ay supported — nababago ang original transaction at nagdadagdag ng bagong refund record

### Cashier Shifts

- Ang shift ay **nagsisimula nang awtomatiko** kapag nag-login ang cashier
- Para tapusin ang shift: i-click ang **flag icon** sa navbar — lalabas ang shift summary bago mag-commit
- Para i-lock ang screen: i-click ang **lock icon** — nananatiling bukas ang shift, kailangan ng password para mag-unlock
- Sa logout: may pagpipilian ang cashier na tapusin ang shift kasama ang summary, o mag-logout lang
- **Auto-lock:** nag-lo-lock ang screen pagkatapos ng **5 minuto ng walang aktibidad** (configurable sa `config.php`)
- Pagkatapos tapusin ang shift (nang hindi nag-lo-logout), **awtomatikong nagsisimula ng bagong shift** para sa parehong cashier

### Best Sellers

- Ang top 5 na produkto ayon sa kabuuang units na naibenta ay awtomatikong nakikilala
- Ang mga badges ay lumalabas sa product row: 🥇 #1 / 🥈 #2 / 🥉 #3 / ⭐ Top 4 / ⭐ Top 5
- Ang mga best sellers ay naka-float sa itaas ng product table

---

## Real-Time Sync

Ang lahat ng bukas na tabs at devices ay awtomatikong naka-sync — walang kailangang manual refresh.

| Method | Scope | Latency |
|---|---|---|
| **BroadcastChannel** | Parehong browser, lahat ng tabs | ~0 ms |
| **Long-poll** (`sync_check.php`) | Lahat ng devices, lahat ng browsers | ~100 ms |

**Paano gumagana ang long-poll:**
- Ang browser ay nagpapanatili ng bukas na HTTP request sa `sync_check.php`
- Nag-che-check ang server ng database changes bawat 100ms, hanggang 25 segundo
- Sa sandaling may nagbago, sumasagot ang server; tinatawag ng JS ang `syncAllData()` at agad nagbubukas ng bagong poll
- Sa network error: nagre-retry pagkatapos ng 2 segundo

**Ano ang nag-ti-trigger ng sync:**
- Bagong benta
- Stock na naidagdag
- Product na na-archive o na-restore
- Refund na na-proseso

---

## Security

| Layer | Implementation |
|---|---|
| **Authentication** | bcrypt (cost=12) password hashing |
| **Session** | Secure cookies, HttpOnly, SameSite=Strict, regenerated on login |
| **SQL Injection** | 100% prepared statements with bound parameters — walang raw string concat |
| **XSS** | Lahat ng output ay ine-escape gamit ang `htmlspecialchars()` via `h()` helper |
| **CSRF** | SameSite=Strict cookies — hindi isasama ng browser ang cookies sa cross-site requests |
| **File Upload** | MIME type whitelist (JPEG/PNG), 5MB size limit, randomized filenames |
| **Role-based Access** | `require_login('admin')` naka-enforce sa bawat admin endpoint at AJAX call |
| **AJAX Auth** | 401/403 JSON responses para sa expired/unauthorized sessions |
| **HTTP Headers** | X-Content-Type-Options, X-Frame-Options, CSP, Referrer-Policy via `.htaccess` |
| **Directory listing** | `Options -Indexes` sa `.htaccess` |
| **Sensitive files** | `config.php`, `database.sql`, `setup.php` ay nakablock sa `.htaccess` |
| **Session locking** | `session_write_close()` tinatawag nang maaga sa read-only endpoints para maiwasan ang blocking |
| **Setup guard** | `setup.php` nagba-block ng re-runs pagkatapos maisulat ang `.setup_done` flag |
| **Admin guard** | Hindi pwedeng i-downgrade ang huling aktibong admin sa cashier role |

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
| `category` | VARCHAR(100) | Running, Casual, Athletic, Formal, Sneakers, Boots, Sandals |
| `size` | VARCHAR(20) | e.g. 9, 10.5 |
| `color` | VARCHAR(50) | e.g. Black/White |
| `cost_price` | DECIMAL(10,2) | Purchase price |
| `price` | DECIMAL(10,2) | Selling price (dapat > 0) |
| `quantity` | INT | Current stock |
| `min_stock` | INT | Low-stock threshold |
| `max_stock` | INT | Max stock limit — enforced sa add_stock |
| `gender` | ENUM('Men','Women','Unisex') | |
| `description` | TEXT | Optional |
| `image` | VARCHAR(255) | Filename sa `uploads/` |
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
| `quantity_sold` | INT | Units sa transaction na ito |
| `unit_price` | DECIMAL(10,2) | Presyo sa oras ng benta — hindi nagbabago kahit i-edit ang product |
| `unit_cost` | DECIMAL(10,2) | Cost sa oras ng benta |
| `total_price` | DECIMAL(10,2) | `unit_price × qty` |
| `total_cost` | DECIMAL(10,2) | `unit_cost × qty` |
| `status` | ENUM | Sold / Refunded (Restocked) / Refunded (Damaged) |
| `refund_reason` | TEXT NULL | Optional na dahilan ng refund |
| `sale_date` | TIMESTAMP | Transaction timestamp |
| `updated_at` | TIMESTAMP | Auto-updated on refund |

### `shifts`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `user_id` | INT FK | References `users.id` |
| `started_at` | TIMESTAMP | Shift start time |
| `ended_at` | TIMESTAMP NULL | NULL = bukas pa ang shift |

### `stock_logs`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `product_id` | INT FK | References `products.id` |
| `added_by` | INT FK NULL | References `users.id` |
| `shift_id` | INT FK NULL | References `shifts.id` |
| `quantity` | INT | Units na naidagdag |
| `logged_at` | TIMESTAMP | Log timestamp |

---

## Project Description

Ito ay isang web-based na Shoes Inventory Management System — para sa tindahan ng sapatos na gustong i-digitize ang pamamahala ng kanilang imbentaryo at benta. Lahat ng dating ginagawa nang mano-mano — pagbibilang ng stock, pagtatala ng benta, pag-track kung sino ang nagbenta — nandoon na sa system. May dalawang uri ng gumagamit: Admin at Cashier. Ang Admin ang may buong kontrol, ang Cashier naman ang nagbebenta at nagdadagdag ng stock sa kanilang shift.

Ginawa gamit ang HTML, CSS, at JavaScript sa front-end, PHP sa back-end, at MySQL para sa database. May UI na responsive at angkop sa desktop, tablet, at mobile — may navbar, stat cards, product table, modals, toast notifications, badges, lock screen, at printable reports.

---

## Scope

**Inventory** — Iniingatan at pinamamahalaan ang lahat ng detalye ng produkto: pangalan, brand, kategorya, size, kulay, presyo, stock, min/max stock, at larawan. Pwedeng mag-add, mag-edit, mag-archive, at mag-restore ang Admin.

**Sales at Transactions** — Nire-record ang bawat benta: dami, halaga, kita. May Sales History na may date filter. May partial at full refund na may 30-araw na warranty window at auto-restock kapag resellable.

**Shifts** — Awtomatikong nagsisimula ang shift sa login ng cashier. Pwedeng tapusin nang manu-mano kasama ang shift summary. Pagkatapos tapusin, awtomatikong nagsisimula ng bagong shift. May lock screen na may 5-minutong auto-lock.

**User Roles** — Role-based access sa bawat page at AJAX endpoint. Hindi pwedeng mag-bypass ng cashier sa admin pages.

**Real-Time Sync** — BroadcastChannel para sa parehong browser, long-polling para sa iba pang devices. Zero manual refresh.

**Reports** — Print-ready na Sales History Report, Cashier Report, at Shift Summary. Responsive sa desktop, tablet, at mobile.

---

## Limitations

Hindi perpekto ang system — narito ang mga kilalang limitasyon:

1. **Single store lang** — Hindi angkop para sa multi-branch. Walang warehouse integration o supplier coordination.
2. **Manual data entry** — Lahat ng pagpasok ng data ay mano-mano. Prone sa human error.
3. **Walang barcode scanner** — Ang paghanap ng produkto ay sa pamamagitan ng search at product table lang.
4. **Limitado ang reports** — Preset formats lang. Walang chart analytics, predictive analytics, o downloadable Excel/CSV.
5. **Walang auto backup** — Kung masira ang database, walang built-in na recovery. Manu-mano lang ang backup.
6. **Walang brute force protection** — Walang account lockout o rate limiting sa login. Ang bcrypt (cost=12) ang pangunahing proteksyon laban dito.
7. **Partial audit trail lang** — Nire-record ang benta at stock additions kasama ang user at shift info, pero ang admin actions tulad ng pag-edit ng presyo o pag-archive ay hindi nila-log.

Sa kabila nito, gumagana nang maayos ang system para sa normal na operasyon ng isang single-store retail na tindahan ng sapatos.

---

## Defense Preparation — Mga Possible na Tanong

> **PAALALA:** Huwag isaulo nang salita-salita. Intindihin ang bawat sagot at isalita sa sarili mong paraan. Natural lang — para talagang naintindihan mo ang ginawa mo.

---

### SIGURADONG TATANUNGIN

---

**Q: Ano ang system ninyo at para saan ito?**

Ginawa namin itong web-based na Shoes Inventory Management System para sa tindahan ng sapatos na gustong i-digitize ang kanilang operasyon. Lahat ng dating ginagawa nang mano-mano — pagbilang ng stock, pagtala ng benta, pag-track kung sino ang nagbenta — nandoon na sa system. May dalawang gumagamit: Admin na may buong kontrol, at Cashier na nagbebenta at nagdadagdag ng stock sa loob ng kanilang shift.

---

**Q: Sino-sino ang users at ano ang pagkakaiba ng access nila?**

Dalawa lang ang roles namin — Admin at Cashier. Ang Admin ay makakagawa ng lahat: mag-add, mag-edit, mag-archive ng produkto, makita ang lahat ng benta, i-manage ang users, mag-proseso ng refund. Ang Cashier naman ay mas limitado — makakabenta, makakapag-add ng stock, at makakakita lang ng sarili niyang shift stats. Hindi siya makapapasok sa Sales History, Cashier Report, Manage Users, at admin-only pages. Naka-enforce ito sa server side gamit ang `require_login('admin')` sa bawat admin file at AJAX endpoint — kahit i-type ng cashier ang URL ng admin page, hindi siya makakalusot.

---

**Q: Ano ang mga teknolohiya na ginamit ninyo at bakit?**

PHP ang back-end, MySQL ang database, HTML/CSS/JavaScript ang front-end. Pinili namin ang PHP kasi compatible ito sa karamihan ng web hosting at seamless ang integration nito sa MySQL. Para sa real-time sync, dalawang paraan ang ginamit namin — BroadcastChannel para sa mga tab sa parehong browser, at long-polling para sa iba pang devices. Mas simple itong i-deploy kaysa WebSockets at gumagana nang maayos para sa aming scale.

---

**Q: Paano gumagana ang real-time sync ng system ninyo?**

May dalawa kaming paraan. Una, BroadcastChannel — kapag may benta o pagbabago sa isang tab, agad napupunta ang update sa lahat ng ibang tabs sa parehong browser, halos zero milliseconds. Pangalawa, long-polling gamit ang `sync_check.php` — nagpapadala ang browser ng bukas na HTTP request sa server, tapos nag-che-check ang server ng database changes bawat 100ms hanggang 25 segundo. Kapag may nagbago — benta, stock, refund — agad sumasagot ang server at tinatawag ng JS ang `syncAllData()`, tapos agad nagbubukas ng bagong poll. Sa network error, nagre-retry pagkatapos ng 2 segundo.

---

**Q: Paano gumagana ang proseso ng pagbebenta?**

Magbubukas ng sell modal ang cashier sa dashboard, ire-adjust ang quantity gamit ang stepper at makikita ang auto-computed na total. Pagka-click ng Confirm Sale, may POST request sa `sell_product.php`. Doon, may database transaction na nagsisimula — kine-check muna ng server kung sapat ang stock gamit ang `FOR UPDATE` lock para maiwasan ang race conditions. Kung okay, dine-decrement ang stock at ini-insert ang transaction record kasama ang `unit_price` at `unit_cost` sa oras ng benta, tapos commit. Pagkatapos, nag-ti-trigger ng real-time sync.

---

**Q: Paano secure ang system ninyo?**

Maraming layers. Bcrypt (cost=12) ang hashing ng passwords — sinadyang mabagal para sa brute force resistance. Prepared statements ang lahat ng database queries para sa SQL injection prevention — walang single raw string concat sa buong codebase. `htmlspecialchars()` ang lahat ng output para maiwasan ang XSS. Session cookies ay HttpOnly, Secure, at SameSite=Strict — yung SameSite ang nagpoprotekta laban sa CSRF. Role-based access ay naka-enforce sa bawat endpoint — AJAX calls nagbabalik ng 401 o 403 kung wala ng valid session. Para sa file uploads, may MIME type whitelist, 5MB limit, at nira-randomize ang filename para hindi ma-execute ang malisyosong file.

---

**Q: Paano gumagana ang refund system?**

Sa Sales History page, may undo button sa tabi ng bawat Sold transaction. Pagka-click, lalabas ang refund modal. Tine-check muna ng system kung nasa loob pa ng 30-day warranty window base sa `sale_date` ng transaction — kung lumabas na, tinatanggihan agad. Pipiliin ng admin ang Resellable o Damaged. Resellable → awtomatikong nadadagdag ang stock. Damaged → nire-record lang bilang refunded, hindi nadadagdag sa stock. May partial refund din — babaguhin ang original transaction at magdadagdag ng bagong refund record. Lahat ng kaso ay may audit trail sa database.

---

**Q: Paano gumagana ang cashier shift?**

Kapag nag-login ang cashier, automatic na nagsisimula ang shift — walang kailangan pang gawin. Bago mag-start ng bagong shift, tine-check ng system kung may naiwan pang bukas na shift at isasara ito. Para tapusin ang shift, may flag icon sa navbar — doon makikita ang Shift Summary bago tuluyang matapos. Pagkatapos tapusin ang shift, awtomatikong nagsisimula ng bagong shift para makapagpatuloy ang cashier nang hindi nag-lo-logout. May lock screen din — kapag hindi ginagalaw ang screen ng 5 minuto, automatic mag-lo-lock. Hindi natapos ang shift, kailangan ng password para ma-unlock.

---

**Q: Ano ang mga limitasyon ng system ninyo?**

Kinikilala namin na hindi ito perpekto. Isa lang na tindahan ang kaya nitong hawakan — walang multi-branch support. Manual pa rin ang data entry, kaya prone to human error. Walang barcode scanner integration. Ang reports ay preset formats lang — walang analytics charts o downloadable Excel. Walang automated database backup. Walang brute force protection tulad ng account lockout. At partial lang ang audit trail — nire-record ang benta at stock additions, pero hindi ang admin actions tulad ng pag-edit ng presyo. Para sa single-store retail setup, gumagana naman nang maayos ang system para sa normal na pang-araw-araw na operasyon.

---

**Q: Bakit web-based ang ginawa ninyo at hindi desktop app?**

Para sa isang tindahan, mas praktikal ang web-based. Walang kailangang i-install sa bawat computer — browser lang, gumagana na. Mas madaling i-update — baguhin sa server, lahat agad na makukuha ang pinakabagong version. At dahil web-based, kaya naming gawin ang real-time sync sa maraming devices nang sabay-sabay — hindi kaya ng simpleng desktop app nang walang mas complex na setup. Responsive din ito sa desktop, tablet, at mobile.

---

### HIGHLY LIKELY TATANUNGIN

---

**Q: Paano kayo nagpapatupad ng role-based access control?**

Sa pinaka-unang linya ng bawat admin page at AJAX endpoint, may `require_login('admin')` na tinatawag. Bago pa man mag-render ng kahit anong content, tine-check nito ang `$_SESSION['user_role']`. Kung wala o hindi admin ang role, nire-redirect agad sa login page — o sa AJAX calls, nagbabalik ng 401/403 JSON response. Walang paraan para lampasan ito kahit i-type ng cashier ang direktang URL ng admin page.

---

**Q: Paano ninyo pinipigilan ang SQL injection?**

Lahat ng database queries namin ay gumagamit ng prepared statements with bound parameters. Ang SQL template at ang user input ay hiwalay na ipinapadala sa database — ang MySQL mismo ang nag-se-separate nila. Kahit mag-type ang user ng `' OR '1'='1`, ita-treat lang ito bilang literal na text, hindi bilang SQL code. Walang single query sa buong codebase na nag-coconcat ng raw user input sa SQL string.

---

**Q: Paano ninyo tinitiyak na ang presyo na naitala sa transaction ay tama kahit baguhin ang presyo ng produkto sa hinaharap?**

Sa `transactions` table, isinasave namin ang `unit_price` at `unit_cost` — yung eksaktong presyo sa oras ng benta. Kaya kahit i-edit ng admin ang presyo ng produkto bukas, hindi mababago ang lumang transaction records. Ito ang tinatawag na price snapshot — mahalaga ito para sa tamang kita computation sa mga report at para sa refund processing.

---

**Q: Paano kayo nagtatrato ng network error o pagkapatay ng server sa gitna ng isang transaction?**

Gumagamit kami ng database transactions — BEGIN, COMMIT, ROLLBACK — sa lahat ng kritikal na operasyon. Kapag may error kahit saan sa gitna, automatic na mag-ro-rollback ang MySQL ng lahat ng partial changes. Halimbawa, kung na-decrement na ang stock pero hindi pa na-insert ang transaction record tapos biglang nag-error, mababalik sa dati ang stock — hindi mag-iiwan ng inconsistent na data.

---

**Q: Paano ninyo pinamamahalaan ang stock accuracy kapag maraming cashier ang nagbebenta nang sabay-sabay?**

Sa `sell_product.php` at `add_stock.php`, gumagamit kami ng `SELECT ... FOR UPDATE` sa loob ng database transaction. Kapag may cashier na nagpoproseso ng benta ng isang produkto, naka-lock ang row na yun — hindi na maaaring mag-benta ng ibang cashier ng parehong item sa eksaktong parehong oras. Ito ang nagpoprotekta sa amin mula sa race conditions — hindi mangyayari na mabenta nang dalawang beses ang last item sa stock.

---

**Q: Mayroon bang audit trail ang system ninyo?**

Partial lang ang audit trail namin. Sa `transactions` table, nire-record ang `sold_by` at `shift_id` sa bawat benta. Sa `stock_logs` table, nire-record ang `added_by` at `shift_id` sa bawat stock addition. Makikita ng Admin sa Cashier Report kung sino ang nagbenta ng ano at magkano sa bawat shift. Inamin namin na hindi ito buong audit log — ang admin actions tulad ng pag-edit ng presyo o pag-archive ay hindi nila-log — at ito ay isa sa mga kilalang limitasyon ng system.

---

**Q: Bakit long-polling ang ginamit ninyo para sa real-time sync at hindi WebSockets?**

Pinili namin ang long-polling kasi mas simple itong i-deploy sa standard PHP at Apache hosting. Ang WebSockets ay kailangan ng persistent na connection at special server configuration na hindi laging available sa shared hosting. Long-polling gumagamit lang ng normal HTTP — compatible sa lahat ng standard web server. Para sa aming scale at use case, ang 100ms latency ng long-polling ay sapat na.

---

**Q: Ano ang mangyayari kapag nag-expire ang session ng isang gumagamit habang nagtatrabaho?**

Kapag nag-expire ang session, yung susunod na AJAX request ng user ay babagsak sa 401 Unauthorized response mula sa server. May handler sa JavaScript na agad magpapakita ng notification na expired na ang session, at ire-redirect ang user sa login page. Lahat ng AJAX calls namin ay dumadaan sa centralized `apiFetch()` wrapper function na nag-che-check ng response status — garantisado itong madetect.

---

### SECURITY-FOCUSED NA TANONG

---

**Q: Lahat ng security features ninyo, isa-isa — ano yung purpose ng bawat isa?**

Lahat ng security features namin ay may partikular na banta na tinotonggal. Ito ang buong listahan:

| Feature | Nagpoprotekta laban sa |
|---|---|
| bcrypt password hashing | Database leak — hindi mababasa ang passwords |
| Prepared statements | SQL Injection — hindi makapasok sa DB gamit ang input |
| `htmlspecialchars()` / `h()` | XSS — hindi makapag-inject ng malicious JS |
| `SameSite=Strict` cookies | CSRF — hindi makapag-trigger ng aksyon mula sa ibang site |
| `HttpOnly` cookies | Session hijacking via XSS — hindi mababasa ng JS ang cookie |
| `Secure` cookie flag | Man-in-the-middle — hindi masniff ang cookie sa plain HTTP |
| Session ID regeneration | Session fixation — hindi magamit ang lumang session ID |
| `require_login('admin')` | Unauthorized access — hindi makapasok sa admin pages ang cashier |
| `FOR UPDATE` row locking | Race condition — hindi mabenta nang dalawang beses ang last stock |
| DB transactions (ROLLBACK) | Data inconsistency — lahat o wala, walang half-done na state |
| MIME type validation (`finfo`) | Malicious file upload — hindi makapag-upload ng PHP disguised as image |
| Randomized filenames | File enumeration — hindi mahulaan ang filename ng uploaded files |
| `.htaccess` file blocking | Direct browser access sa `config.php`, `database.sql`, `setup.php` |
| `Options -Indexes` | Directory listing — hindi makita ang listahan ng files sa folder |
| `X-Content-Type-Options: nosniff` | MIME sniffing — hindi mag-guess ang browser ng file type |
| `X-Frame-Options: SAMEORIGIN` | Clickjacking — hindi ma-embed ang system sa ibang website gamit ang iframe |
| `Content-Security-Policy` | XSS at data injection — kinokontrol kung saan pwedeng mag-load ng scripts |
| `Referrer-Policy` | Information leakage — hindi ma-leak ang URL ng system sa external sites |
| `X-Powered-By` removal | Fingerprinting — hindi malalaman ng attacker na PHP ang gamit namin |
| `display_errors Off` | Information disclosure — hindi makikita ng user ang raw PHP errors |
| Setup guard (`.setup_done`) | Accidental re-run ng setup wizard sa production |
| `session_write_close()` | Session blocking — hindi naghihintay ang ibang requests sa locked session |
| `valid_id()` / `valid_qty()` | Invalid input — tinatanggihan ang non-integer at out-of-range values |
| Admin count guard | Lockout — hindi pwedeng tanggalin o i-downgrade ang huling aktibong admin |

---

**Q: Ano ang bcrypt at bakit ninyo ito ginamit para sa passwords?**

**Para saan:** Para hindi mabasa ang passwords ng users kahit ma-leak ang database.

**Kung wala ito:** Kung nag-store kami ng plain text o MD5/SHA1, at may nakapasok sa database — makikita agad ng attacker ang lahat ng passwords. One second, tapos na.

**Paano namin ito ginagawa:** Ang bcrypt ay hashing algorithm na sinadyang mabagal. May cost factor — sa amin, cost 12. Ibig sabihin, kahit makuha ng attacker ang hashed passwords, bawat brute force guess ay halos 200–400ms ang compute time. Gumagamit kami ng `password_hash()` at `password_verify()` ng PHP — built-in, hindi DIY.

**Demo example:** Buksan ang database at tingnan ang `users` table. Ganito ang hitsura ng password ng admin:
```
$2y$12$mEi5l3gDqKnsGuk2FVTSMeIq9AYwzERNj5F2pNgS7QWWwfIqFZpLG
```
Yun ay `admin123`. Kahit titingnan nang matagal, hindi mo malalaman ang original na value — kaya walang kwenta kahit makita ng attacker ang buong database dump.

---

**Q: Ano ang SQL Injection at paano kayo nagpoprotekta?**

**Para saan:** Para hindi makapasok ang attacker sa system o makapag-basa/makapag-bura ng data sa pamamagitan ng malisyosong input.

**Kung wala ito:** Kung ang login query namin ay ganito (MALI):
```php
$sql = "SELECT * FROM users WHERE username = '$username'";
```
At ang nilagay ng attacker sa username ay `admin' OR '1'='1' --`, magiging ganito ang query:
```sql
SELECT * FROM users WHERE username = 'admin' OR '1'='1' -- ...
```
Ang `'1'='1'` ay palaging true — mag-lo-login siya kahit walang tamang password.

**Paano namin ito ginagawa:** Lahat ng queries ay prepared statements:
```php
$stmt = $conn->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
$stmt->bind_param('s', $username);
$stmt->execute();
```
Ang SQL template at ang user input ay hiwalay na ipinapadala sa database. Kahit mag-type ang user ng `' OR '1'='1`, literal na text lang ang ita-treat ng MySQL — hindi SQL code.

---

**Q: Ano ang XSS at paano kayo nagpoprotekta?**

**Para saan:** Para hindi makapag-inject ng malisyosong JavaScript ang isang user na mag-e-execute sa browser ng ibang user.

**Kung wala ito:** Kung naglagay ang isang tao ng `<script>alert('hacked')</script>` bilang product name at hindi namin ine-escape ang output — kapag binuksan ng Admin ang dashboard, mag-e-execute ang script sa browser niya. Sa mas malala, pwedeng magnakaw ng session cookie.

**Paano namin ito ginagawa:** May `h()` helper function sa `config.php` na tinatawag sa lahat ng output:
```php
function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
```
Ang `<script>` sa data ay magiging `&lt;script&gt;` sa HTML — text lang ang lalabas sa browser, hindi ise-execute.

**Demo example:** Mag-add ng produkto na may pangalang `<b>Bold Test</b>`. Sa product table, lalabas itong literal na `<b>Bold Test</b>` — hindi mag-bo-bold. Iyon ang XSS protection in action.

---

**Q: Ano ang CSRF at paano kayo nagpoprotekta?**

**Para saan:** Para hindi makapag-trigger ng aksyon sa aming system ang ibang website gamit ang session ng naka-login na user nang hindi niya alam.

**Kung wala ito:** Halimbawa, naka-login ang Admin. Nag-visit siya ng ibang site na may nakatagong form na nag-se-send ng POST request sa aming `sell_product.php`. Dahil may valid session cookie ang Admin, tatanggapin ng aming server ang request — kahit hindi niya sinadya.

**Paano namin ito ginagawa:** `SameSite=Strict` ang session cookie:
```php
'samesite' => 'Strict',
```
Hindi isasama ng browser ang cookie sa request na galing sa ibang domain. Kahit may hidden form ang malisyosong site, walang cookie — rejected agad ng server.

---

**Q: Paano ninyo pinamamahalaan ang session security?**

**Para saan:** Para hindi makapag-hijack ng session ng naka-login na user ang attacker.

**Kung wala ito:** Kapag may XSS at walang HttpOnly — pwedeng basahin ng JavaScript ang session cookie gamit ang `document.cookie` at gamitin ito para mag-pretend na siya ang naka-login na user.

**Paano namin ito ginagawa, apat na layers:**

**HttpOnly** — Hindi mababasa ng JavaScript ang cookie kahit may XSS:
```php
'httponly' => true,
```

**Secure** — Ipinapadala lang ang cookie sa HTTPS:
```php
'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
```

**SameSite=Strict** — Hindi isasama ng browser ang cookie sa cross-site requests (para din sa CSRF).

**Session ID regeneration** pagkatapos ng login — kahit may nakakuha ng lumang session ID, invalid na ito pagkatapos mag-login:
```php
session_regenerate_id(true);
```

---

**Q: Paano gumagana ang file upload security?**

**Para saan:** Para hindi makapag-upload ng malisyosong PHP file ang isang tao na nakabalot bilang image.

**Kung wala ito:** Pwedeng mag-upload ang attacker ng `shell.php` na may `.jpg` extension. Kapag na-access niya ang URL, mag-e-execute ang PHP sa server — buong access na niya.

**Paano namin ito ginagawa, tatlong layers:**

**MIME type check gamit `finfo`** — tinitingnan ang actual na file bytes, hindi ang extension:
```php
// Sa config.php — define('IMAGE_ALLOWED_MIME', ['image/jpeg', 'image/jpg', 'image/png']);
// Sa save_image():
if (!in_array($file['type'], IMAGE_ALLOWED_MIME, true)) {
    return [null, 'Only JPG and PNG images are allowed.'];
}
```

**Randomized filename** — hindi mahuhulaan ng attacker kung nasaan ang file nila:
```php
$filename = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
// e.g. img_e8b8c5e3760595a4.jpg
```

**5MB size limit** + `uploads/` folder ay nakablock sa `.htaccess` para hindi mag-execute ng PHP kahit may nakalusot.

---

**Q: Paano ninyo nipo-protect ang config.php at iba pang sensitive files?**

**Para saan:** Para hindi ma-access ng browser ang database credentials, schema, at setup wizard.

**Kung wala ito:** Pwedeng i-type ng kahit sino ang `yourdomain.com/inventory/config.php` at makita ang DB password at lahat ng configuration.

**Paano namin ito ginagawa:** Sa `.htaccess`:
```apache
<FilesMatch "^(config\.php|database\.sql|setup\.php|README\.md|\.env)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Block din ang .sql, .log, .bak, .sh, .env, .git files
<FilesMatch "\.(sql|log|bak|sh|env|git)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

Options -Indexes
```
403 Forbidden agad ang isasauli ng server sa lahat ng direct browser access sa mga file na ito. At `Options -Indexes` ang nagpoprotekta na hindi makita ang listahan ng files sa folder.

**Demo example:** Subukan mong i-access ang `localhost/inventory/config.php` sa browser — 403 Forbidden agad, kahit sa localhost.

---

**Q: Ano ang mga HTTP security headers ninyo at para saan ang bawat isa?**

**Para saan:** Dagdag na layer ng proteksyon sa browser level — nagtatakda kung ano ang pwede at hindi pwedeng gawin ng browser habang nakabukas ang system.

**Paano namin ito ginagawa:** Sa `.htaccess`, lahat ay naka-set sa `Header always set`:

**`X-Content-Type-Options: nosniff`** — Pinipigilan ang browser na mag-guess ng file type. Kung sinabi naming HTML ang isang file, hindi siya mag-re-render nito bilang ibang bagay. Nagpoprotekta laban sa drive-by download attacks.

**`X-Frame-Options: SAMEORIGIN`** — Hinaharangan ang pag-embed ng system sa ibang website gamit ang `<iframe>`. Ito ang nagpoprotekta laban sa clickjacking — yung atake kung saan isinasalamangka ang user na mag-click ng button sa aming system habang nakabalot ito sa ibang site.

**`Content-Security-Policy`** — Nagtatakda kung saan lang pwedeng mag-load ng scripts, styles, fonts, at images:
```apache
"default-src 'self';
 script-src 'self' https://cdnjs.cloudflare.com;
 style-src 'self' https://cdnjs.cloudflare.com;
 img-src 'self' data:;
 frame-ancestors 'none';"
```
Hindi pwedeng mag-load ng script mula sa ibang domain — kaya kahit may XSS injection, hindi pwedeng mag-load ng external malicious script. Ang `frame-ancestors 'none'` ay nagdodoble ng proteksyon laban sa clickjacking kasama ang `X-Frame-Options`.

**`Referrer-Policy: strict-origin-when-cross-origin`** — Kinokontrol kung anong impormasyon ang ipinapadala sa external sites kapag may nag-click ng link. Hindi mai-leak ang buong URL ng system sa labas.

**`X-XSS-Protection: 1; mode=block`** — Para sa lumang browsers na may built-in XSS filter — kapag nadetect ang XSS attempt, ibino-block ang page imbes na i-render.

**`X-Powered-By` removal** — Tinatanggal ang header na nagpapakita ng PHP version:
```apache
Header unset X-Powered-By
Header always unset X-Powered-By
```
Hindi malalaman ng attacker na PHP ang gamit namin at kung anong version — hindi niya mahahanap ang known vulnerabilities ng specific na PHP version.

---

**Q: Ano ang `display_errors Off` at bakit kailangan ito?**

**Para saan:** Para hindi makita ng mga users ang raw PHP error messages sa production.

**Kung wala ito:** Kapag may nag-cause ng error — mali-type sa URL, invalid na request — lalabas ang full PHP error kasama ang file path, line number, at minsan pati ang database query. Malaking tulong ito sa attacker para malaman ang structure ng system.

**Paano namin ito ginagawa:** Sa `.htaccess`:
```apache
php_flag display_errors Off
php_flag log_errors     On
```
Ang errors ay nilo-log sa server log — makikita ng developer, hindi ng user. Ang user ay makakakita lang ng generic na error response.

---

**Q: Ano ang setup guard at para saan ito?**

**Para saan:** Para hindi ma-re-run ang setup wizard sa production at ma-overwrite ang existing data o ma-reset ang admin password.

**Kung wala ito:** Kahit matagal nang live ang system, pwedeng i-access ng kahit sino ang `setup.php` at muling mag-run ng setup — posibleng ma-overwrite ang admin credentials.

**Paano namin ito ginagawa:** Pagkatapos ma-complete ang setup, nagsusulat ng flag file:
```php
@file_put_contents(__DIR__ . '/uploads/.setup_done', date('Y-m-d H:i:s'));
```
At sa simula ng `setup.php`:
```php
if (file_exists(__DIR__ . '/uploads/.setup_done')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Setup has already been completed.</p>');
}
```
Hindi na pwedeng i-run muli ang setup pagkatapos ng unang successful run.

---

**Q: Ano ang `session_write_close()` at para saan ito?**

**Para saan:** Para hindi mablock ang sabay-sabay na requests ng parehong user.

**Kung wala ito:** Ang PHP ay naglalagay ng lock sa session file habang bukas ang isang request. Kapag nag-send ng maraming sabay-sabay na AJAX requests ang browser — tulad ng long-poll at dashboard stats na sabay — ang pangalawang request ay maghihintay sa unang matapos. Nagiging mabagal at nagse-serialize ang lahat ng requests ng isang user.

**Paano namin ito ginagawa:** Sa lahat ng read-only endpoints, tinatawag namin ang `session_write_close()` pagkatapos mabasa ang session data:
```php
$user    = current_user();    // basahin ang session data
$shiftId = current_shift_id();
session_write_close();        // bitawan ang lock — hindi na babaguhin ang session
// ... magpatuloy ng DB query at response
```
Kaya ang long-poll, dashboard stats, at iba pang AJAX calls ay pwedeng tumakbo nang sabay-sabay nang hindi naghihintay sa isa't isa.

---

**Q: Ano ang `valid_id()` at `valid_qty()` at para saan ang mga ito?**

**Para saan:** Para matanggihan agad ang invalid o malisyosong input bago pa man umabot sa database.

**Kung wala ito:** Kung direkta naming ginagamit ang raw `$_POST` values sa DB queries — kahit may prepared statements — pwedeng magpadala ang attacker ng negative numbers, zero, o sobrang laking value na nagse-cause ng logic errors.

**Paano namin ito ginagawa:**
```php
function valid_id(mixed $value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : 0;
}

function valid_qty(mixed $value, int $min = 1, int $max = 9999): int {
    $qty = filter_var($value, FILTER_VALIDATE_INT);
    if ($qty === false || $qty < $min || $qty > $max) return 0;
    return $qty;
}
```
Kapag nagbalik ng 0 ang `valid_id()` o `valid_qty()`, agad kaming nag-eejson_error bago pa man mag-DB query:
```php
$productId = valid_id($_POST['product_id'] ?? 0);
if (!$productId) json_error('Invalid product ID.');
```

---

**Q: Paano ninyo pinamamahalaan ang role-based access control?**

**Para saan:** Para hindi makapasok ang Cashier sa Admin-only pages kahit i-type niya ang direktang URL.

**Kung wala ito:** Kahit walang link sa navbar ang isang page — kapag alam ng cashier ang URL, mapupuntahan niya ito.

**Paano namin ito ginagawa:** Unang linya ng bawat admin file at AJAX endpoint:
```php
require_login('admin');
```
Bago pa mag-render ng kahit isang character, tine-check nito ang `$_SESSION['user_role']`. Kung hindi admin: redirect para sa normal pages, 401/403 JSON para sa AJAX calls. Walang paraan para lampasan ito.

**Demo example:** Mag-login bilang Cashier. I-type ang `localhost/inventory/admin/manage_users.php` sa address bar — ire-redirect ka agad. Walang lalabas na kahit isang bahagi ng page.

---

**Q: Paano ninyo pinipigilan ang race conditions kapag maraming cashier ang nagbebenta nang sabay?**

**Para saan:** Para hindi mabenta nang dalawang beses ang last item sa stock.

**Kung wala ito:** Cashier A at Cashier B ay sabay na nag-click ng sell sa last item. Sinuri ng dalawa ang stock — 1 pa. Ibinenta ng dalawa. Naging -1 ang stock — imposible sa totoong buhay.

**Paano namin ito ginagawa:** `SELECT ... FOR UPDATE` sa loob ng database transaction:
```php
$conn->begin_transaction();

$stmt = $conn->prepare(
    'SELECT quantity FROM products WHERE id = ? AND is_archived = 0 FOR UPDATE'
);
```
Kapag may cashier na nagpoproseso ng sale, naka-lock ang product row — hindi na makakapag-start ng ibang sale para sa parehong product hanggang hindi pa natapos ang una. Ang MySQL ang nagmamanage ng locking.

---

**Q: Ano ang database transactions (ROLLBACK) at para saan?**

**Para saan:** Para palaging consistent ang data — lahat ng related na changes ay mangyayari nang sabay, o hindi mangyayari.

**Kung wala ito:** Kapag namatay ang server pagkatapos ma-decrement ang stock pero bago ma-insert ang transaction record — nawawalang stock na walang katumbas na sale. Malaking problema sa inventory at accounting.

**Paano namin ito ginagawa:**
```php
$conn->begin_transaction();
try {
    // 1. Lock ang row (FOR UPDATE)
    // 2. Decrement ang stock
    // 3. Insert ang transaction record
    $conn->commit(); // Lahat ay nai-save
} catch (RuntimeException $e) {
    $conn->rollback(); // Kahit isa ay nagfail → bumalik lahat sa dati
    json_error($e->getMessage());
}
```
Ang `COMMIT` o `ROLLBACK` ang nagga-garantiya na ang stock at transaction record ay palaging magkasabay — hindi pwedeng mag-exist ang isa nang wala ang isa.

---

**Q: Ano ang admin count guard at para saan?**

**Para saan:** Para hindi ma-lock out ang lahat ng admin sa system.

**Kung wala ito:** Pwedeng i-deactivate ng Admin ang sarili niya o i-downgrade sa cashier role — at kung siya lang ang aktibong admin, walang makakapasok sa admin functions. Permanently locked out ang system.

**Paano namin ito ginagawa:** Sa `manage_users.php`, bago mag-edit ng user:
```php
// Kung idi-downgrade ang isang admin sa cashier role:
$stmtAdm = $conn->prepare(
    "SELECT COUNT(*) c FROM users WHERE role = 'admin' AND is_active = 1 AND id != ?"
);
// Kung ang count ay 0 → ibig sabihin, siya lang ang natitira — hindi payagan
if ($adminCount === 0) $errors[] = 'Cannot downgrade — at least one active admin is required.';
```
Palaging may isang aktibong admin ang system — hindi posibleng ma-lock out ang lahat.

---

## Presentation Guide — Paano Ipapakita ang System

> Ito ang step-by-step na walkthrough ng system para sa demo o defense. Sundin ang order na ito — natural ang flow at matutukoy lahat ng major features.

---

### PART 1 — Login Page (`index.php`)

**Ipakita:** Yung login form mismo.

Sabihin: "Dito nagsisimula ang lahat. Single entry point ang system — walang ibang paraan para pumasok maliban dito. Dalawang roles ang pwedeng mag-login: Admin at Cashier. Ibang-iba ang makikita nila pagpasok."

I-login bilang **Admin** muna.

---

### PART 2 — Admin Dashboard (`admin.php`)

**Ipakita:** Ang buong dashboard — stat cards sa itaas, product table sa baba.

**Stat Cards** — "Dito makikita ng admin ang live na datos: Total Sales ngayon, Total Revenue, Total Profit, at Total Products. Nag-re-refresh ito automatically — walang kailangan pang i-reload ng page."

**Product Table** — "Lahat ng produkto nandito — may search filter, may category filter, may stock status badges (In Stock / Low Stock / Out of Stock). Yung mga best sellers ay may gold, silver, bronze badges at naka-float sa itaas."

**I-demo ang Real-Time Sync** — Kung may dalawang devices o tabs: "Buksan ang dalawang tabs. Gumawa ng sale sa isa — makikita ninyo na mag-u-update agad ang stock sa isa pang tab nang hindi nire-reload."

---

### PART 3 — Pagbebenta (`sell_product.php`)

**Ipakita:** I-click ang sell button sa kahit anong in-stock na produkto.

"Mag-click ng sell button sa isang produkto — lalabas ang sell modal. Dito, ang cashier ay mag-a-adjust ng quantity gamit ang stepper, at automatic na nako-compute ang total price. I-click ang Confirm Sale — dine-decrement agad ang stock, nire-record ang transaction kasama ang presyo at cost sa oras na iyon, at nag-ti-trigger ng sync sa lahat ng bukas na devices."

I-confirm ang isang sale. Ipakita na bumaba ang stock sa product table.

---

### PART 4 — Pagdagdag ng Stock (`add_stock.php`)

**Ipakita:** I-click ang + button sa isang produkto.

"Ganito ang pagdadagdag ng stock. Maglalagay ng quantity, tapos i-click ang Add Stock. May max stock limit na naka-enforce — hindi pwedeng lagpasan. Nire-record din ito sa stock_logs table para makita ng Admin sa Cashier Report kung sino ang nagdagdag at magkano."

---

### PART 5 — Add Product (`add_product.php`)

**Ipakita:** Ang add product form.

"Dito nagdadagdag ng bagong produkto ang Admin. Kailangan ng product name, brand, category, size, color, cost price, selling price, at initial stock. May optional na image upload — nag-va-validate kami ng MIME type, may 5MB limit, at nira-randomize ang filename para sa security. Hindi pwedeng gumawa ng produkto ang Cashier — Admin-only ito."

---

### PART 6 — Edit Product (`edit_product.php`)

**Ipakita:** I-click ang edit button sa isang produkto.

"Pareho lang sa Add Product pero pre-filled na ang fields. Kapag binago ang presyo dito, hindi mababago ang mga nakaraang transaction — naka-snapshot ang presyo sa oras ng benta."

---

### PART 7 — Archive / Restore (`archive_product.php`)

**Ipakita:** I-click ang archive button sa isang produkto.

"Kapag hindi na available ang isang produkto, nag-a-archive kami — hindi namin bine-delete para hindi mawala ang sales history. Ang archived products ay nananatili sa database pero hindi na lumalabas sa dashboard. Pwede ring i-restore anytime."

---

### PART 8 — Sales History (`sales_history.php`)

**Ipakita:** Ang sales history page — i-demo ang date range filter.

"Dito makikita ng Admin ang lahat ng transactions — benta, refund, at partial refunds. May date range filter. Makikita rin dito kung sino ang nagbenta at sa anong shift. May undo button sa tabi ng bawat Sold transaction para sa refund."

**I-demo ang Refund** — "I-click ang undo button sa isang benta. Lalabas ang refund modal — pipiliin namin ang dami, kondisyon (Resellable o Damaged), at optional na dahilan. Kapag Resellable, awtomatikong nadadagdag ang stock. May 30-day warranty window — kapag lumabas na sa window, hindi na tatanggapin ng system."

---

### PART 9 — Cashier Report (`cashier_report.php`)

**Ipakita:** Ang cashier report page.

"Dito makikita ng Admin ang breakdown per cashier per shift — magkano ang naibenta, ilan ang transactions, at magkano ang stock na naidagdag. Useful ito para malaman kung sino ang pinaka-productive. Print-ready din ito."

---

### PART 10 — Manage Users (`manage_users.php`)

**Ipakita:** Ang manage users page.

"Dito namamahala ang Admin ng user accounts — pwedeng mag-create, mag-edit, at mag-deactivate. May safeguard kami: hindi pwedeng i-deactivate o i-downgrade ang huling aktibong admin — para hindi ma-lock out ang lahat sa system."

---

### PART 11 — I-logout bilang Admin, I-login bilang Cashier

I-logout. I-login gamit ang cashier account.

"Pansinin na iba ang makikita ng Cashier. Wala nang Sales History, Cashier Report, at Manage Users sa navbar. Admin-only pages lang ang hindi makikita — pero ang dashboard, sell, at add stock ay accessible pa rin."

---

### PART 12 — Cashier Shift Features

**Auto-start ng Shift** — "Pagka-login ng cashier, automatic na nagsimula ang shift. Walang kailangang gawin."

**Lock Screen** — I-click ang lock icon sa navbar. "I-click ang lock icon — nag-lo-lock ang screen agad. Kailangan ng password para ma-unlock. Nananatiling bukas ang shift — hindi ito end-of-shift."

I-unlock. Ipakita na bumalik sa dashboard.

**End Shift** — I-click ang flag icon. "Kapag natapos na ang shift ng cashier, i-click ang flag icon. Lalabas ang Shift Summary — ilan ang naibenta, magkano ang revenue para sa shift na ito. Pagkatapos i-confirm, automatic na nagsisimula ng bagong shift."

---

### PART 13 — Ipakita ang Real-Time Sync (kung may dalawang devices)

Buksan ang system sa dalawang devices o dalawang browser tabs.

"Gumawa ng benta sa isang device — pansinin na mag-u-update agad ang stock sa isa pang device nang walang manual refresh. Ganito ang long-polling namin — ang browser ay nagpapanatili ng bukas na connection sa server at kapag may nagbago sa database, agad sumasagot ang server at nire-refresh ng JavaScript ang lahat ng data."

---

### Tips para sa Presentation

- **Mag-login bilang Admin muna** — mas maraming features ang makikita at mas maayos ang flow ng demo.
- **Huwag magmadali sa sell at refund demo** — dito ka karamihan tatanungin.
- **Buksan ang dalawang tabs** kung gusto mong i-demo ang real-time sync — visual impact ito.
- **Kapag may tanong tungkol sa security** — huwag agad mag-dive sa code. Ipaliwanag muna sa plain terms, tapos banggitin ang specific na implementation kung kailangan.
- **Okay lang sabihin "ito ang isa sa mga limitasyon namin"** — mas magandang marinig na alam mo ang weakness ng system kaysa mag-bluff.

---

## License

MIT — free for personal and commercial use.

---

*Built for the Philippines 🇵🇭 — timezone: Asia/Manila (UTC+8), currency: Philippine Peso (₱)*
