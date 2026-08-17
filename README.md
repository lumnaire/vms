# 🐟 Virac Public Market — Fish Price Monitoring System

A web-based commodity supply projection and price monitoring system for the fish section of Virac Public Market, Catanduanes. Built with **Laravel 11**, **SQLite**, **Tailwind CSS**, and **Alpine.js**.

---

## 📋 Table of Contents

- [Tech Stack](#tech-stack)
- [User Roles](#user-roles)
- [Requirements](#requirements)
- [Installation & Setup](#installation--setup)
- [Default Login Credentials](#default-login-credentials)
- [Running the App](#running-the-app)
- [Common Commands](#common-commands)

---

## Tech Stack

| Layer      | Technology                                                 |
| ---------- | ---------------------------------------------------------- |
| Backend    | PHP 8.2+, Laravel 11                                       |
| Database   | MySQL 8.0+                                                 |
| Frontend   | Tailwind CSS (CDN), Alpine.js (CDN), Bootstrap Icons (CDN) |
| Build Tool | Vite (for asset bundling if needed)                        |
| Auth       | Custom session-based with role middleware                  |

---

## User Roles

| Role           | Access                                                                           |
| -------------- | -------------------------------------------------------------------------------- |
| **Supervisor** | Full control — vendors, staff, fish types, price guides, forecasts, reports      |
| **Staff**      | Confirm/reject vendor price entries, manage vendors, view price guides & reports |
| **Vendor**     | Submit daily inventory and pricing entries                                       |
| **Public**     | View live price board at `/prices` — no login required                           |

---

## Requirements

Make sure you have these installed before starting:

- **PHP** `>= 8.2` — [php.net/downloads](https://www.php.net/downloads)
- **Composer** `>= 2.x` — [getcomposer.org](https://getcomposer.org)
- **MySQL** `>= 8.0` — included in [Laragon](https://laragon.org) ✅, XAMPP, or standalone
- **Node.js** `>= 18.x` + **npm** — [nodejs.org](https://nodejs.org) _(only needed if you run Vite for assets)_
- **Git** — [git-scm.com](https://git-scm.com)

> 💡 **Recommended local stack: [Laragon](https://laragon.org)** — comes with PHP, MySQL, Apache/Nginx, and automatic `.test` virtual hosts all in one installer. Your `APP_URL=http://vms.test` is already set up for this.

> **Quick check** — run these in your terminal to confirm they're installed:
>
> ```bash
> php -v
> composer -V
> mysql --version
> node -v
> npm -v
> git --version
> ```

---

## Installation & Setup

Follow these steps **in order** after cloning the project.

### 1. Clone the repository

```bash
git clone https://github.com/lumnaire/vms.git
cd vms
```

---

### 2. Install PHP dependencies

```bash
composer install
```

> This creates the `vendor/` folder. It may take a minute.

---

### 3. Create your `.env` file from the example

```bash
cp .env.example .env
```

Then open `.env` and update these values to match your local setup:

```env
APP_NAME="Virac Market System"
APP_URL=http://vms.test   # or http://localhost:8000 if using php artisan serve

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=virac_market_db
DB_USERNAME=root
DB_PASSWORD=                              # leave blank if your MySQL root has no password (default in Laragon)

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> The `SESSION_DRIVER=database`, `CACHE_STORE=database`, and `QUEUE_CONNECTION=database` settings are already handled — their tables are created automatically when you run migrations in step 6.

---

### 4. Generate the application key

```bash
php artisan key:generate
```

> This fills in the `APP_KEY=` value in your `.env`. The app won't work without this.

---

### 5. Create the MySQL database

Log in to MySQL and create the database:

```bash
mysql -u root -p
```

Then inside the MySQL prompt:

```sql
CREATE DATABASE virac_market_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

> If your root has **no password** (default in Laragon), just press Enter when prompted, or use:
>
> ```bash
> mysql -u root -e "CREATE DATABASE virac_market_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
> ```

> **Using Laragon?** You can also create it through the built-in **HeidiSQL** or **phpMyAdmin** UI — just create a new database named `virac_market_db`.

---

### 6. Run migrations

```bash
php artisan migrate
```

> This creates all the tables in `virac_market_db`: `users`, `sessions`, `cache`, `jobs`, `vendor_profiles`, `fish_types`, `price_guides`, `vendor_inventories`, `activity_logs`, `forecasts`, `reports`.

---

### 7. Seed the database with sample data

```bash
php artisan db:seed
```

> This inserts:
>
> - 1 Supervisor account, 1 Staff account, 12 Vendor accounts
> - All common Catanduanes fish types
> - Sample price guides, 37 days of inventory history, and 14-day ARIMA forecasts

---

### 8. Create the storage symlink

```bash
php artisan storage:link
```

> This links `storage/app/public` → `public/storage` so uploaded fish photos are accessible from the browser. **Required** for the fish type image feature to work.

---

### 9. (Optional) Install and build frontend assets

The app uses **CDN versions** of Tailwind and Alpine by default, so this step is only needed if you want to build assets locally with Vite:

```bash
npm install
npm run dev      # development mode with hot reload
# or
npm run build    # production build
```

---

### 10. Start the development server

**Option A — Laragon / Valet / Herd (recommended):**

If you're using Laragon, your site is already accessible at the virtual host you configured. No extra command needed.

```
http://vms.test/prices     ← public price board
http://vms.test/login      ← login
```

**Option B — Built-in PHP server:**

```bash
php artisan serve
```

Then open: **http://localhost:8000**

```
http://localhost:8000/prices    ← public price board
http://localhost:8000/login     ← login
```

> If you use `php artisan serve`, temporarily change `APP_URL=http://localhost:8000` in your `.env`.

---

## Default Login Credentials

> ⚠️ Change these passwords immediately if deploying to production.

| Role            | Username                | Password        |
| --------------- | ----------------------- | --------------- |
| Supervisor      | `supervisor`            | `supervisor123` |
| Staff           | `staff`                 | `staff123`      |
| Vendor (all 12) | `vendor46` – `vendor57` | `vendor123`     |

---

## Running the App

```bash
# Start the web server
php artisan serve

# Run scheduled tasks manually (forecasts + inventory lock)
php artisan schedule:run

# Generate forecasts manually
php artisan forecast:generate

# Lock previous day entries manually
php artisan inventory:lock
```

---

## Common Commands

```bash
# ── Setup ────────────────────────────────────────────────────────
composer install                  # Install PHP packages
npm install                       # Install JS packages
cp .env.example .env              # Create env file
php artisan key:generate          # Generate app key
# (create MySQL database manually first — see step 5)

# ── Database ─────────────────────────────────────────────────────
php artisan migrate               # Run all migrations
php artisan migrate:fresh         # Drop all tables and re-migrate
php artisan migrate:fresh --seed  # Fresh migrate + seed data
php artisan db:seed               # Seed without migrating
php artisan storage:link          # Link storage for file uploads

# ── Development ──────────────────────────────────────────────────
php artisan serve                 # Start dev server at :8000
npm run dev                       # Start Vite hot reload
npm run build                     # Build production assets

# ── Debugging ────────────────────────────────────────────────────
php artisan route:list            # See all registered routes
php artisan config:clear          # Clear config cache
php artisan cache:clear           # Clear app cache
php artisan view:clear            # Clear compiled views
php artisan optimize:clear        # Clear everything at once

# ── Scheduled Commands ───────────────────────────────────────────
php artisan forecast:generate     # Run ARIMA forecast manually
php artisan inventory:lock        # Lock previous-day entries manually
php artisan schedule:run          # Trigger due scheduled tasks
```

---

## Project Structure (Key Files)

```
├── app/
│   ├── Http/Controllers/
│   │   ├── Auth/           # Login/logout
│   │   ├── Public/         # Price board (no auth)
│   │   ├── Supervisor/     # Supervisor panel
│   │   ├── Staff/          # Staff panel
│   │   └── Vendor/         # Vendor panel
│   ├── Models/             # Eloquent models
│   └── Http/Middleware/    # Role-based access
├── database/
│   ├── migrations/         # Table definitions
│   └── seeders/            # Sample data
├── resources/views/
│   ├── public/             # priceboard.blade.php
│   ├── supervisor/         # Supervisor views
│   ├── staff/              # Staff views
│   ├── vendor/             # Vendor views
│   └── layouts/            # Shared layout
├── routes/
│   └── web.php             # All routes
└── public/
    ├── storage/            # Symlinked uploaded files
    └── logo.png / bg.jpg
```

---

_Catanduanes State University · Virac Public Market Capstone Project_
