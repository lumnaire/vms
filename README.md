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
- [Calculation Guide](#calculation-guide)
- [Hostinger Deployment](#hostinger-deployment)
- [ARIMA Forecasting](#arima-forecasting)
- [Sale Reports](#sale-reports)
- [Stale Stock Alerts](#stale-stock-alerts)
- [Common Commands](#common-commands)
- [Project Structure](#project-structure-key-files)

---

## Tech Stack

| Layer      | Technology                                                 |
| ---------- | ---------------------------------------------------------- |
| Backend    | PHP 8.2+, Laravel 11                                       |
| Database   | MySQL 8.0+                                                 |
| Frontend   | Tailwind CSS (Vite), Alpine.js (CDN), Bootstrap Icons (CDN) |
| Build Tool | Vite (required for dashboard CSS and JavaScript)           |
| Auth       | Custom session-based with role middleware                  |

---

## User Roles

| Role           | Access                                                                           |
| -------------- | -------------------------------------------------------------------------------- |
| **Supervisor** | Full control — vendors, staff, fish types, price guides, forecasts, reports      |
| **Staff**      | Confirm/reject vendor price entries, manage vendors, view price guides & reports |
| **Vendor**     | Submit daily inventory and pricing entries, file the daily sale declaration      |
| **Public**     | View the live price board at `/` — no login required                             |

---

## Requirements

Make sure you have these installed before starting:

- **PHP** `>= 8.2` — [php.net/downloads](https://www.php.net/downloads)
- **Composer** `>= 2.x` — [getcomposer.org](https://getcomposer.org)
- **MySQL** `>= 8.0` — included in [Laragon](https://laragon.org) ✅, XAMPP, or standalone
- **Node.js** `20.19+` or `22.12+` + **npm** — [nodejs.org](https://nodejs.org) _(required to build frontend assets)_
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

> This creates all the tables in `virac_market_db`: `users`, `sessions`, `cache`, `jobs`, `vendor_profiles`, `fish_types`, `price_guides`, `vendor_inventories`, `vendor_sale_reports`, `vendor_sale_report_items`, `activity_logs`, `forecasts`, `reports`.

---

### 7. Seed the database with sample data

```bash
php artisan db:seed
```

> This inserts:
>
> - 1 Supervisor account, 1 Staff account, 12 Vendor accounts
> - All common Catanduanes fish types
> - Sample price guides, 153 days of inventory history, 14 days of vendor sale
>   declarations, and 3-day ARIMA forecasts for **price** and **supply**
>   (stock in) — see [ARIMA Forecasting](#arima-forecasting) for how each number
>   is calculated
>
> The seeded history is deliberately realistic: older trading days mostly sold
> out, with a small deterministic share of leftovers, plus a handful of entries
> forced to sit unsold past the freshness window so the
> [stale-stock alert](#stale-stock-alerts) has something real to show.

---

### 8. Create the storage symlink

```bash
php artisan storage:link
```

> This links `storage/app/public` → `public/storage` so uploaded fish photos are accessible from the browser. **Required** for the fish type image feature to work.

---

### 9. Install and build frontend assets

The app loads its Tailwind styles and dashboard CSS/JavaScript through Vite.
This step is required: updating files under `resources/` does not update the
production files served from `public/build/`.

```bash
npm ci
npm run dev      # development mode with hot reload
# or
npm run build    # production build
```

Use `npm run build` for deployment. `npm run dev` is only for local development.

---

### 10. Start the development server

**Option A — Laragon / Valet / Herd (recommended):**

If you're using Laragon, your site is already accessible at the virtual host you configured. No extra command needed.

```
http://vms.test/           ← public price board
```

> The board is the site root. Signing in happens through the form in the board's
> own navbar — there is no separate `/login` page. Closing the browser signs you
> out, because the session cookie is issued without an expiry.

**Option B — Built-in PHP server:**

```bash
php artisan serve
```

Then open: **http://localhost:8000**

```
http://localhost:8000/     ← public price board
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

## Calculation Guide

Researchers can open **`/calculation`** directly without signing in. The consumer
price board does not link to it. Every role's sidebar and the supervisor forecast
page retain their links.

The guide explains supply, remaining stock, sales, tariff markup, tubo,
weighted and unweighted averages, dashboard periods, report totals, ARIMA,
trend labels, forecast ranges and MAPE. It includes a variable glossary and
the implementation's research limitations.

Its editable worked examples run through `ArimaService::explain()`, the same
calculation used by `project()`. They show unrounded fitting and forecast steps
without reading private vendor data, expiring inventory or saving forecasts.
Example selections and custom input are shareable through the page URL.
Use **Print / save PDF** to print the guide with detailed working expanded.

---

## Hostinger Deployment

The dashboard layout is built from `resources/css/dashboard.css` and
`resources/js/dashboard.js`, imported by the Vite entry points. The generated
`public/build/` directory is intentionally ignored by Git. **An hPanel Git
deployment or `git pull` alone does not update the production frontend.**

After deploying the latest source, open SSH in the deployed Laravel directory
(the folder containing `artisan`) and run:

```bash
git log -1 --oneline             # Confirm the expected source commit is deployed
npm ci --include=dev            # Vite and Tailwind are development dependencies
npm run build                   # Generate public/build/manifest.json and assets/
php artisan route:clear         # Make newly deployed routes available
php artisan view:clear
php artisan view:cache
```

Stop if any command fails. Run the build and view commands after each Git
deployment, either over SSH or in your deployment pipeline. Rebuilding and
uploading assets locally is also supported; Node.js is only needed where the
build runs.

### When Node.js is unavailable on Hostinger

1. Deploy the latest source through Git as usual.
2. On your computer, check out the same commit and run `npm ci` followed by
   `npm run build` (use `npm.cmd` in PowerShell if script execution is disabled).
3. Upload the **entire** generated `public/build/` directory, including
   `manifest.json` and `assets/`, to the deployed application's `public/build/`.
   Upload the asset files before replacing the manifest, and keep older hashed
   assets until existing pages no longer reference them.
4. Run `php artisan route:clear`, `php artisan view:clear` and
   `php artisan view:cache` over SSH.

With this repository's root `.htaccess` and the project installed directly in
`public_html/`, the destination is `public_html/public/build/`. If your domain
serves a different Laravel installation, use that installation's `public/build/`.
Uploading to a different copy of the project will not update the live site.

Do not upload the local `public/hot` file; remove it from the production
application if it exists. Laravel uses that file to select the development
server instead of the production manifest.

### Verify the deployed dashboard

- Compare the live `public/build/manifest.json` with the generated local manifest.
  In browser developer tools, the dashboard's `/build/assets/` CSS and JavaScript
  filenames should match the manifest, and both requests should return HTTP 200.
- In hPanel, open the site's **Cache Manager** and purge the cache. Purge any
  enabled CDN cache as well, then reload the dashboard with **Ctrl+Shift+R**.
- If it still looks old, verify the Git branch, deployed commit and application
  directory serving the domain before deploying again.

See [Laravel's production asset build instructions](https://laravel.com/docs/12.x/vite#running-vite)
and [Hostinger's Cache Manager instructions](https://www.hostinger.com/support/6215624-how-to-use-cache-manager-at-hostinger/).

---

## ARIMA Forecasting

The system projects **three days forward** for two numbers (price and supply)
per fish type and quality class. A Tagalog, non-technical guide is in
[FORECASTING.md](FORECASTING.md). This section explains where each number comes from and how the
projection is calculated.

The forecasting implementation and settings are in three files:

- `app/Services/ArimaService.php` — the engine
- `app/Services/ArimaMaximumLikelihood.php` — standard Gaussian likelihood and numerical fitting
- `config/forecast.php` — every tunable value

---

### The 2 metrics

Both are built from the same table, `vendor_inventories`, which vendors fill
in daily and staff confirm. Only **confirmed** rows are used, and only rows dated
**before today** — the model never sees partial or unverified entries.

For each day, the confirmed rows for that fish type and quality class are
collected and reduced to a single number:

| Metric     | Question it answers                 | Source column  | How the day is calculated           | Unit |
| ---------- | ----------------------------------- | -------------- | ----------------------------------- | ---- |
| **Price**  | "What will one kilo cost tomorrow?" | `price_per_kg` | **Average** of every vendor's price | ₱/kg |
| **Supply** | "How much will be available?"       | `stock_kg`     | **Sum** of every vendor's stock     | kg   |

> `sold_kg` is no longer a forecasting metric. What a vendor actually sold is
> now declared once per day in a [sale report](#sale-reports), and it feeds the
> stale-stock alert rather than a demand projection.

#### Why price is averaged but supply is summed

This is the part that most often causes confusion, so it is worth being precise.

**Price is a rate, supply is a quantity.** Adding three vendors' prices together
(`₱180 + ₱190 + ₱200 = ₱570`) would be meaningless — there is no such thing as a
"total price". So price is **averaged**, giving the typical market price for that
day.

**Supply is a physical amount of fish.** If vendor A brings 12 kg, vendor B brings
8 kg and vendor C brings 5 kg, then 25 kg reached the market that day. Summing is
the only meaningful operation.

A worked day for one fish type and quality class:

| Vendor           | `price_per_kg` | `stock_kg` |
| ---------------- | -------------: | ---------: |
| A                |        ₱180.00 |         12 |
| B                |        ₱190.00 |          8 |
| C                |        ₱200.00 |          5 |
| **Daily metric** |    **₱190.00** |     **25** |

> Price: `(180 + 190 + 200) ÷ 3 = 190`
> Supply: `12 + 8 + 5 = 25 kg`

The same `AVG` / `SUM` rule is defined once, in `ArimaService::buildSeries()` and
`ArimaService::historicalSeries()`.

---

### How the projection is calculated

The fixed **ARIMA(1,1,1) with drift** uses **exact Gaussian maximum likelihood**.
`ArimaMaximumLikelihood` jointly optimizes AR and MA coefficients using a stationary
innovations/Kalman likelihood. Drift and innovation variance are profiled analytically.
A multi-start Nelder–Mead search enforces stationarity and invertibility. Coefficients
are not set equal to autocorrelations. This is a PHP implementation of a standard
estimator, verified numerically against statsmodels; Python is not required on the host.

Training reads up to **30 calendar days**, as specified in Chapters 1–3 of
`thesis_chpt1_3.pdf` (printed pp. 8, 13, 33). Use the latest consecutive recorded
segment ending yesterday, with at least 7 observations. Missing dates end the segment;
display-filled prices and zeros are not used for fitting. The minimum is an operational
rule and does not establish statistical sufficiency.

Model equation, for x[t] = y[t] − y[t−1]:

```text
x[t] − μ = φ(x[t−1] − μ) + e[t] + θ e[t−1]
```

The MLE likelihood uses innovation v[t] and unit-variance factor F[t]:

```text
σ² = Σ(v[t]² / F[t]) / m
log L = −½[m log(2π) + m log(σ²) + Σlog(F[t]) + Σ(v[t]²/F[t])/σ²]
```

μ is fitted drift, not necessarily the sample mean change. Forecasts use the filtered
last shock; expected future shocks are zero. Raw levels are recursively retained.
Negative published levels and bounds are clipped to zero. Conditional prediction
variance includes integrated ARMA impulse responses and the remaining filtered-state
uncertainty, rather than the former σ²h approximation. Parameter uncertainty is excluded.

Because training ends yesterday, a forecast generated today first predicts the
unobserved current day, then publishes tomorrow and the next two days. Stored model
steps are therefore 2, 3 and 4 for the default horizon. Constant-change series are
explicitly marked `deterministic`: the zero-variance limiting case has no finite Gaussian
likelihood and unidentified AR/MA coefficients. Non-converged fits produce no new rows.

### Research references and limits

Mardesci, H., & Fitriani, D. (2025). *Performance evaluation of ARIMA and ANN models
for forecasting oil palm production trends*. Agricultural Revolution Journal, 1(2), 75–83.
https://doi.org/10.64570/agrivolution.v1i2.33

Section 2.1, p. 77 explicitly uses maximum likelihood. Its annual oil-palm study
supports the estimation method; adapting it to daily fish records still requires local
forecast evaluation. The thesis cites it but omits its full bibliography entry.
The thesis’s p. 159 says 90 days, contradicting Chapters 1–3; the selected project
configuration follows the **30-day** specification. Its Caquilala reference title also
needs correction: DOI 10.1063/5.0309236 identifies
[*Optimizing rice production forecasts in a Philippine province: A comparative analysis of time series models*](https://pubs.aip.org/aip/acp/issue/3446/1).

Likelihood reference: [R stats::arima](https://stat.ethz.ch/R-manual/R-devel/library/stats/html/arima.html).
Order is fixed by study design, not automatically selected. Stationarity hypothesis
testing is not automated. ISO/IEC 25010 user ratings do not establish forecast accuracy.

### Reproducible numerical verification

`tests/Fixtures/arima_mle_reference.json` contains independent statsmodels reference
coefficients, likelihoods, forecasts and variances. To regenerate with NumPy, SciPy
and statsmodels installed, run `python tests/Fixtures/generate_arima_reference.py`.
Run `php artisan test --filter=ArimaServiceTest` to compare the PHP implementation.
The public `/calculation` page shows the live ten-step worked computation.

### Viewing the output

Supervisors see the forecasts at **`/supervisor/forecasts`**, where the metric
can be switched between Price and Supply. The page shows the last 30 days of
actuals behind the projection and labels the model as `AR(1)I(1)MA(1)`.

The fitted coefficients themselves (`φ`, `θ`, `μ`, `σ`) are not shown on the
page — they are stored per row in the `forecasts.arima_params` column so every
published figure keeps the parameters that produced it:

```sql
SELECT fish_type_id, quality_class, metric, forecast_date,
       predicted_value, predicted_min, predicted_max, trend, arima_params
FROM   forecasts
ORDER  BY fish_type_id, metric, forecast_date;
```

---

### Configuration

Every value lives in `config/forecast.php`. The first four can be set in `.env`:

| Setting              | `.env` variable               | Default | What it controls                                               |
| -------------------- | ----------------------------- | ------- | -------------------------------------------------------------- |
| `horizon`            | `FORECAST_HORIZON`            | `3`     | How many days ahead are projected                              |
| `min_history`        | `FORECAST_MIN_HISTORY`        | `7`     | Minimum recorded days before a series is forecast at all       |
| `history_days`       | `FORECAST_HISTORY_DAYS`       | `30`    | Days of history used to **fit** the model                      |
| `history_chart_days` | `FORECAST_HISTORY_CHART_DAYS` | `30`    | Days of history **displayed** on the chart behind the forecast |
| `order`              | —                             | `1,1,1` | The ARIMA order                                                |
| `z`                  | —                             | `1.96`  | Range width — `1.96` gives 95%                                 |
| `trend_threshold`    | —                             | `0.02`  | The ±2% band used for the trend label                          |
| `trend_baseline_days`| —                             | `7`     | Real days averaged as the trend label's baseline               |

The **fit window (30 days)** and the **chart window (30 days)** have separate
settings but share the thesis’s default length. Training uses the latest consecutive
recorded segment; the chart may show filled dates, clearly excluded from fitting.

### Hostinger: the scheduler does not run by itself

The forecast page reads pre-computed rows out of the `forecasts` table. Nothing
regenerates those rows unless `forecast:generate` runs, and on shared hosting the
Laravel scheduler is **not** automatic. If hPanel has no cron job, the page sits
at "No data" forever even when there is plenty of history to fit the model.

Add this in **hPanel → Advanced → Cron Jobs** (Hostinger's own docs call this
"PHP commands"; tick the box next to it so the output is emailed to you):

| Field     | Value                                                                     |
| --------- | ------------------------------------------------------------------------- |
| Command   | `/usr/bin/php /home/USER/domains/DOMAIN/public_html/artisan schedule:run` |
| Frequency | `* * * * *` (every minute)                                                |

> Run it from the directory that holds `artisan`, and adjust `USER` / `DOMAIN` to
> match your account. `schedule:run` picks up both nightly tasks in
> `bootstrap/app.php` — `forecast:generate` at 00:01 and `inventory:lock` at 00:05.
> The every-minute frequency is correct: Laravel only fires each task once, when
> its own `dailyAt()` time arrives.

To confirm it is alive, add a temporary entry to the crontab at `* * * * *` and
check the emailed output for the `[VPM] Starting forecast generation` line.

**If you cannot set up cron**, the supervisor forecast page generates the
selected series on demand when it finds no stored rows for it, so the chart
still renders. That covers the page itself; only the nightly refresh of
series nobody has looked at is lost.

### Regenerating forecasts

The system refreshes forecasts automatically every night. To do it by hand:

```bash
php artisan forecast:generate                          # everything
php artisan forecast:generate --metric=price           # one metric only
php artisan forecast:generate --fish_type_id=5         # one fish type
php artisan forecast:generate --quality_class="First Class"
```

> ⚠️ After changing anything in `config/forecast.php` or the engine, re-run
> `forecast:generate` — the `forecasts` table keeps the last generated values
> and will not update on its own until the next scheduled run. Remember
> `php artisan config:clear` if you changed a value in `.env`.

> ⚠️ Deploying runs `migrate`, and the
> `switch_forecasts_to_three_day_supply_demand` migration truncates the
> `forecasts` table. Push, deploy, then run `php artisan forecast:generate`
> once — or just reload the forecast page, which now regenerates on demand.

## `

### Good to know

- **A day with no confirmed rows is skipped, not counted as zero.** If a fish
  type has no entries on a given day, that day is simply absent from the series,
  so one "daily change" may span more than 24 hours.
- **Forecasts are per fish type _and_ quality class.** A `First Class` forecast
  is fitted only from `First Class` entries.
- **A new series needs at least 7 recorded days** before any forecast appears.
  This is the usual reason the forecast table is empty right after seeding.

---

## Sale Reports

Each vendor closes the trading day by declaring what they actually sold, in one
pass, against the entries staff confirmed that day.

```
GET  /vendor/sale-report    the declaration form + the vendor's own history
POST /vendor/sale-report    submit, or revise before the cutoff
GET  /staff/sale-reports    staff view: totals, unsold value, calendar
GET  /supervisor/sale-reports
```

### The rules it enforces

| Rule                             | Why                                                                                                  |
| -------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Only **confirmed** stock         | An entry awaiting staff approval has no agreed price, so there is nothing to sell against            |
| **Every** entry declared         | Totals are summed from what arrives, so a partial payload would silently understate the day          |
| Never more than released         | A declaration cannot invent stock that staff did not release for sale                                |
| Own fish only                    | Entries are resolved from the signed-in vendor, so a hand-built request cannot reach another's stock |
| Cutoff at `SALE_REPORT_DEADLINE` | Default `23:59`, in the app timezone. Before then the day may be revised; after it, ask staff        |
| Previous days are closed         | Only today is reportable                                                                             |

Submitting writes the declared kg back to `vendor_inventories.sold_kg`, so the
price board's remaining stock always agrees with the declaration.

### Why the lines are copied, not joined

`vendor_sale_report_items` snapshots fish name, quality class, price, released kg
and the declared kg onto the item. A sale report is a statement about a day that
has already happened, so it must keep showing what was true that day even after
a price guide or quality class is later corrected.

### The staff/supervisor view

Both roles see the same page. It answers three questions at once:

- a table of vendor / fish / quality / price / kg / value, filterable by date and
  vendor, with what each line left unsold
- **which vendors have confirmed stock but have not filed** — the outstanding
  notice resolves from the whole day, so it is unaffected by the vendor filter
- **which trading days have declarations at all**, as a calendar, so a gap is
  visible without hunting through dates

A vendor with no confirmed stock is never listed as outstanding — there is
nothing to declare.

---

## Stale Stock Alerts

Fresh fish does not keep. An inventory entry is flagged when **both** hold:

- it is at least `STALE_STOCK_AFTER_DAYS` old (default 3), measured from
  `entry_date` — the trading day, not `created_at`, so a back-filled entry cannot
  look fresh
- it still has unsold stock remaining

Age is measured from `entry_date` because that is when the vendor declared the
stock. `created_at` would make a back-filled entry look new.

Flagged entries appear in red:

- **on the vendor dashboard**, oldest first, capped at ten rows with a count of
  what is not shown, and what the leftover is costing at the confirmed price
- **in the inventory history**, as a red row with the remaining kg and its age

Sold-out and unconfirmed entries are never stale — there is nothing left to lose.

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
│   │   ├── Auth/           # Login/logout (POST only)
│   │   ├── Public/         # Price board (no auth)
│   │   ├── Supervisor/     # Supervisor panel
│   │   ├── Staff/          # Staff panel
│   │   ├── Vendor/         # Vendor panel
│   │   └── SaleReportController.php   # Shared staff/supervisor declaration view
│   ├── Models/             # Eloquent models, incl. VendorSaleReport(+Item)
│   ├── Services/           # ArimaService — forecasting engine
│   ├── Console/Commands/   # forecast:generate, inventory:lock
│   └── Http/Middleware/    # Role-based access
├── config/
│   ├── forecast.php        # Forecast horizon, metrics, ARIMA settings
│   └── inventory.php       # Stale-stock window and sale-report cutoff
├── database/
│   ├── migrations/         # Table definitions
│   └── seeders/            # Sample data
├── tests/
│   ├── Unit/               # ArimaServiceTest
│   └── Feature/            # MarketplaceFlow, FrontendSmoke, SaleReport
├── resources/views/
│   ├── public/             # priceboard.blade.php
│   ├── supervisor/         # Supervisor views
│   ├── staff/              # Staff views
│   ├── vendor/             # Vendor views
│   ├── sale-reports/       # Shared staff/supervisor declaration view
│   └── layouts/            # Shared layout
├── routes/
│   └── web.php             # All routes
└── public/
    ├── storage/            # Symlinked uploaded files
    └── logo.png / bg.jpg
```

---

_Catanduanes State University · Virac Public Market Capstone Project_
