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
| Frontend   | Tailwind CSS (CDN), Alpine.js (CDN), Bootstrap Icons (CDN) |
| Build Tool | Vite (for asset bundling if needed)                        |
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

## ARIMA Forecasting

The system projects **three days forward** for two numbers (price and supply)
per fish type and quality class. A Tagalog, non-technical guide is in
[FORECASTING.md](FORECASTING.md). This section explains where each number comes from and how the
projection is calculated.

Everything below lives in two files:

- `app/Services/ArimaService.php` — the engine
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

Each metric becomes a **time series** — one number per day, oldest first. ARIMA
then looks at how that series has been _changing_ and projects the change
forward.

The model is **ARIMA(1,1,1)**, which in plain English means:

- **(1)** the change from one day to the next tends to **follow the previous
  day's change** (the _autoregressive_ part),
- **(1)** we model the **day-to-day change** rather than the raw level, because
  raw levels drift (the _differencing_ part),
- **(1)** recent **surprises** nudge the next day before fading out (the
  _moving-average_ part).

The full pipeline, implemented in `ArimaService::project()`:

1. **Collect** up to 90 days of confirmed history.
2. **Difference** the series to get the daily change.
3. **Fit φ** — how strongly today's change follows yesterday's.
4. **Fit θ** — how much the last surprise carries into tomorrow.
5. **Roll forward** 3 days, each day feeding into the next.
6. **Attach a range** to each day so the answer is a band, not a false pinpoint.

If a series has fewer than **7** recorded days it is skipped entirely rather
than guessed at. In a fresh database most combinations are skipped for this
reason — this is deliberate, not a failure.

---

### The formulas

Here is the arithmetic in full, so the model can be checked by hand.

**Step 1 — Difference (the `d = 1`)**

```
Δy[t] = y[t] − y[t−1]
```

**Step 2 — Average daily change (μ)**

```
μ = (1 / n) · Σ Δy[t]
```

**Step 3 — AR(1) coefficient (φ)**

φ measures how much of yesterday's change carries into today. It is the
lag-1 autocorrelation of the differenced series, clamped to the range
`[−0.99, 0.99]` so a series can never feed back on itself and explode:

```
        Σ (x[t] − x̄)(x[t+1] − x̄)
φ  =  ───────────────────────────── ,   where x = Δy and x̄ = μ
        Σ (x[t] − x̄)²
```

**Step 4 — Residual (ε) and MA(1) coefficient (θ)**

The residual is the part of today's change the AR term did **not** explain. It
compares today against **yesterday's** change:

```
ε[t] = (Δy[t] − μ) − φ · (Δy[t−1] − μ)
```

θ is then the lag-1 autocorrelation of those residuals, also clamped:

```
        Σ (e[t] − ē)(e[t+1] − ē)
θ  =  ───────────────────────────── ,   where e = ε and ē = mean(ε)
        Σ (e[t] − ē)²
```

> **Why the lag matters.** If ε were built from today's change paired with
> _itself_ instead of with yesterday's, then ε would be just a scaled copy of
> the differenced series. Because the φ formula divides by a sum of squares, it
> cannot tell a series from a scaled copy of itself — so it would return φ for
> θ as well, and the moving-average term would contribute nothing while still
> appearing in the model. The lag is what makes the two coefficients genuinely
> independent.

**Step 5 — Size of a typical miss (σ)**

σ is the standard deviation of the residuals — the model's typical error size:

```
        Σ (ε[t] − ε̄)²
σ  =  √ ──────────────
          n − 1
```

**Step 6 — Project forward**

Each future day repeats the pattern: the average change, adjusted by how much the
last change persisted, plus the fading effect of the last surprise.

```
Δŷ[t+1] = μ + φ · (Δy[t] − μ) + θ · ε[t]        ← day 1 uses the last real change
Δŷ[t+h] = μ + φ · (Δŷ[t+h−1] − μ) + θ · ε̂[t+h−1]  ← later days use the last forecast
ŷ[t+h]  = max(0, ŷ[t+h−1] + Δŷ[t+h])
```

- Each projected day becomes the "yesterday" for the next, so the three days form
  a connected path rather than three unrelated guesses.
- The **MA term only applies to the first projected day.** The last real surprise
  is already known, so it legitimately shifts tomorrow. For days 2 and 3 the
  shocks are still in the future and are assumed to average out to zero.
- Values are **clamped at 0** — a forecast cannot be a negative peso or a
  negative number of kilos.

**Step 7 — The prediction range**

Rather than a single number, each day gets a **95% range** that widens the
further ahead you look, because uncertainty compounds:

```
band = 1.96 · σ · √h

predicted_min = ŷ − band
predicted_max = ŷ + band
```

`1.96` is the z-value for a 95% interval, and `√h` makes the band grow as the
square root of the day — roughly 1.4× wider on day 2 and 1.7× wider on day 3
than on day 1.

> A wide band is not a defect. It means the daily numbers genuinely swing a lot
> from day to day, and the model is reporting that honestly instead of
> pretending to a precision it does not have.

**Step 8 — Trend label**

The three days are given a single label by comparing the **last projected day**
with the **average of the last 7 actual days** (`trend_baseline_days`), using a
±2% band:

```
baseline = average of the last 7 real days

Day 3 > baseline × 1.02   →  "upward"
Day 3 < baseline × 0.98   →  "downward"
otherwise                 →  "stable"
```

> The label answers "is this heading above or below what we saw this past
> week?". An earlier version compared forecast day 3 with forecast day 1, but
> those are only two days apart, so a ±2% band needed a ~1%-a-day move to ever
> trigger and almost every real series read `stable`.

---

### Worked example

A short series of average daily prices in ₱/kg, chosen so every step can be
checked by hand:

```
y : 100  101  103  102  105  104  108  107  111  110
```

| Step | Calculation        | Result                          |
| ---- | ------------------ | ------------------------------- |
| 1    | Daily changes `Δy` | `1, 2, −1, 3, −1, 4, −1, 4, −1` |
| 2    | Average change `μ` | `1.1111`                        |
| 3    | AR coefficient `φ` | `−0.8832`                       |
| 4    | MA coefficient `θ` | `−0.1366`                       |
| 5    | Typical error `σ`  | `0.7659`                        |

**Projecting day 1.** Yesterday's change was `−1`, so the model expects the move
to partly reverse, damped by `φ`. The last residual was `0.4403`:

```
Δŷ = μ + φ·(Δy − μ) + θ·ε
   = 1.1111 + (−0.8832)(−1 − 1.1111) + (−0.1366)(0.4403)
   = 1.1111 + 1.8645 − 0.0601
   = 2.9155

ŷ    = 110 + 2.9157 = 112.92
band = 1.96 × 0.7659 × √1 = 1.50
```

**Days 2 and 3** repeat the same step, each one using the previous _forecast_
as the new "yesterday", and with the shock term now zero. Every fitted value is
stored with the forecast in the `forecasts` table's `arima_params` column.

| Day | Predicted | Range (95%)       | Width |
| --- | --------: | ----------------- | ----: |
| 1   |   ₱112.92 | ₱111.41 – ₱114.42 |  3.01 |
| 2   |   ₱112.43 | ₱110.31 – ₱114.56 |  4.25 |
| 3   |   ₱114.95 | ₱112.35 – ₱117.55 |  5.20 |

All three days carry the same trend label, `upward`: the last 7 real days
average ₱106.71, and day 3 (₱114.95) is 7.7% above that — well outside the ±2%
band. The range widens as expected too, in the ratio √1 : √2 : √3.

> This example is pinned by a unit test
> (`tests/Unit/ArimaServiceTest::test_it_reproduces_the_documented_worked_example`),
> so the numbers above cannot silently drift away from the code.

---

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
| `history_days`       | `FORECAST_HISTORY_DAYS`       | `90`    | Days of history used to **fit** the model                      |
| `history_chart_days` | `FORECAST_HISTORY_CHART_DAYS` | `30`    | Days of history **displayed** on the chart behind the forecast |
| `order`              | —                             | `1,1,1` | The ARIMA order                                                |
| `z`                  | —                             | `1.96`  | Range width — `1.96` gives 95%                                 |
| `trend_threshold`    | —                             | `0.02`  | The ±2% band used for the trend label                          |
| `trend_baseline_days`| —                             | `7`     | Real days averaged as the trend label's baseline               |

Note that the **fit window (90 days)** and the **chart window (30 days)** are
deliberately different: the model uses everything available, while the chart
shows only the recent past so the projection stays readable.

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
