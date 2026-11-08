# e-HadirGo

Staff field-attendance with location verification for Jabatan Perikanan Sabah.
Plain PHP 8 + PDO (MySQL) + a PWA front end. No framework, no build step.

## Layout

```
e-hadirgo/
├─ app/
│  ├─ bootstrap.php        constants, includes, session
│  ├─ helpers.php          URLs, escaping, CSRF, formatting
│  ├─ i18n.php             BM / EN
│  ├─ auth.php             signup, OTP, login, reset, Telegram tokens
│  ├─ geo.php              haversine + attendance rules
│  ├─ notify.php           Telegram, SMTP, notification log
│  ├─ repo.php             all queries
│  ├─ controllers/         auth, staff, settings, supervisor, admin
│  ├─ views/               templates
│  └─ partials/layout.php
├─ db/                     schema.mysql.sql, db.php, seed.php, check.php, migrate.php
├─ lang/                   ms.php, en.php
├─ public/                 document root
│  ├─ index.php            front controller
│  ├─ assets/              app.css, app.js, icons
│  ├─ manifest.webmanifest
│  └─ sw.js
└─ cron/reminders.php      H-1, H-30 and no-show job
```

## Setup

1. **Database** — create it, then apply the schema and demo data:

   ```bash
   mysql -u user -p -e "CREATE DATABASE ehadirgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   php db/migrate.php
   php db/seed.php
   ```

   `migrate.php` reads the same credentials as the app, so no `mysql` client is
   needed — useful on Windows. MySQL **8.0.16+** or MariaDB **10.2+** (the schema
   uses `CHECK` constraints).

2. **Environment** — set on Clever Cloud, or copy `.env.example` to `.env` for
   local development. The PHP bootstrap loads `.env` without replacing values
   already provided by the process environment. The local `.env` is git-ignored.
   You can also export the values directly:

   | Variable | Purpose |
   |---|---|
   | `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` | Database connection |
   | `DATABASE_URL` | Alternative: `mysql://user:pass@host:3306/db` |
   | `MYSQL_SSL` | `1` when the server requires TLS |
   | `TELEGRAM_BOT_TOKEN` | Bot token from @BotFather |
   | `TELEGRAM_BOT_USERNAME` | e.g. `ehadirgo_bot`, used for the deep link |
   | `TELEGRAM_WEBHOOK_SECRET` | Optional; checked against the webhook header |
   | `APP_SECRET` | HMAC key for Telegram link tokens — set a long random value |
   | `APP_DEBUG` | `1` shows the OTP and reset link on screen (dev only) |
   | `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` | Gmail or department relay |
   | `MAIL_FROM` / `MAIL_FROM_NAME` | Sender identity |

   Place search on the assignment form queries two free OpenStreetMap-based
   providers in parallel — Nominatim and Photon — and orders the hits by their
   distance from the centre of the map on screen, so a local Sabah place is not
   beaten by a same-named street in another state. If neither provider finds a
   match, the user can open Google Maps and paste coordinates or a full Maps
   URL back into the form. Google Maps is not embedded, and no Google API key
   is used. Shortened share URLs that conceal coordinates must be replaced with
   copied coordinates or a full URL.

### Where the database credentials go

There are **no credentials inside `db.php`** — that is deliberate. It reads them
from the environment at runtime, so the same file works everywhere and nothing
secret is committed.

On Clever Cloud, linking a MySQL add-on injects:

```
MYSQL_ADDON_HOST   MYSQL_ADDON_PORT   MYSQL_ADDON_DB
MYSQL_ADDON_USER   MYSQL_ADDON_PASSWORD
```

Resolution order in `db_config()`: `db.local.php` → `DATABASE_URL` →
`MYSQL_ADDON_URI` → `MYSQL_ADDON_*` → `DB_*` → localhost defaults.

Two variables belong to the application rather than the add-on:

```bash
clever env set CC_WEBROOT /public     # document root (required, or the app 404s)
clever env set APP_SECRET "$(openssl rand -hex 32)"
clever env set APP_DEBUG 0
```

Check what PHP actually sees, without printing the password:

```bash
php db/check.php
```

**No environment support?** Copy `db/db.local.example.php` to `db/db.local.php`
and fill it in. That file is git-ignored and takes precedence over everything else.

3. **Open the app locally** — on Windows, double-click `start.bat`. It starts the
   PHP development server and opens `http://127.0.0.1:8000/` in your browser.
   The homepage sends signed-out visitors to the login page. Keep the server
   window open while using the app.

   Or start it manually from the project folder:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

   Never expose `app/` or `db/` as the document root.

4. **Telegram webhook** — Telegram must be able to reach this URL over public
   HTTPS. A `localhost` URL is not reachable by Telegram. For a deployed site,
   register its webhook URL once (replace the token, host, and secret):

   ```bash
   curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook" \
        -d "url=https://your-host/index.php?r=tg.webhook" \
        -d "secret_token=$TELEGRAM_WEBHOOK_SECRET"
   ```

   **Local development on Windows:** expose the PHP server temporarily using
   an HTTPS tunnel such as ngrok. Keep the PHP server running in one terminal:

   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

   In a second terminal, start the tunnel and leave it running:

   ```powershell
   ngrok http 8000
   ```

   Copy the HTTPS forwarding address shown by ngrok. In a third PowerShell
   terminal, register it as the webhook. Enter the bot token and the same
   webhook secret configured in `.env`; leave the secret blank only if
   `TELEGRAM_WEBHOOK_SECRET` is blank there:

   ```powershell
   $botToken = Read-Host "Telegram bot token"
   $publicUrl = (Read-Host "HTTPS forwarding address from ngrok").TrimEnd("/")
   $webhookSecret = Read-Host "TELEGRAM_WEBHOOK_SECRET from .env (blank if unset)"
   $body = @{ url = "$publicUrl/index.php?r=tg.webhook" }
   if ($webhookSecret) { $body.secret_token = $webhookSecret }
   Invoke-RestMethod -Method Post `
       -Uri "https://api.telegram.org/bot$botToken/setWebhook" `
       -Body $body
   ```

   Confirm that Telegram has the current URL and check `last_error_message`:

   ```powershell
   Invoke-RestMethod `
       -Uri "https://api.telegram.org/bot$botToken/getWebhookInfo" |
       Select-Object url, last_error_message
   ```

   Then log in to the local app, open **Settings**, choose **Link Telegram**,
   open the generated link, and press **Start** in the bot chat. The link's
   `/start` token is required; sending `/start` to the bot by itself does not
   link an account. The bot should reply that the account is linked. Only then
   use **Send test message** in Settings. If the tunnel address changes, repeat
   the webhook registration with the new HTTPS address. Keep the bot token and
   webhook secret private.

5. **Cron** — every five minutes:

   ```
   */5 * * * * php /path/to/e-hadirgo/cron/reminders.php >> /var/log/ehadirgo.log 2>&1
   ```

## Run with Docker (one command)

MySQL 8 + PHP 8.3/Apache, schema and demo roster applied automatically.

```bash
docker compose up -d --build
# open http://localhost:8080
```

First boot prints the URL and loads 10 demo accounts (password `HadirGo#2026`).
`APP_DEBUG=1` is on, so the sign-up OTP and password-reset link appear on screen —
no mail server needed to try the flows.

| Command | Effect |
|---|---|
| `docker compose logs -f app` | Entrypoint output, seed result |
| `docker compose down` | Stop; data survives in the `mysqldata` volume |
| `docker compose down -v` | Stop and wipe the database |
| `docker compose exec db mysql -u ehadirgo -p ehadirgo` | MySQL shell |
| `docker compose exec app php tests/logic_test.php` | Run the test suite |

Host port **3307** maps to MySQL (3306 is often taken); the app talks to `db:3306`
inside the network. Configuration lives in `.env` (copy `.env.example`).

## How it behaves

- **Sign-up** requires e-mail, name, password and unit. `@sabah.gov.my` activates after OTP;
  anything else is flagged `is_dummy` and needs the confirmation checkbox.
- **Everyone starts as staff.** The Peta, Semakan and Laporan pages are for
  penyelia and super admin accounts. A super admin can grant `penyelia` from
  Users & Roles; the role is additive.
- **Assignments** carry one pin, one radius (50 m – 5 km) and a time window.
  Only super admins can create or edit assignments. Selecting multiple
  participants creates a separate assignment and attendance record for each
  person; participants in that selection can view one another's attendance,
  but can only submit their own.
- **Check-in** is allowed at any time before the start. After start + 15 minutes it is late and a
  reason is mandatory. Outside the radius a note is mandatory. Both are warnings, not blocks.
  During check-in, the map draws a dashed line between the staff member and the assignment
  location when they are outside its radius.
- **Notifications** go to the preferred verified channel (Telegram, else e-mail) and every attempt
  lands in `notification_log`.
- **Notification test buttons** send directly to their named channel: the email test goes to the
  signed-in account's email, and the Telegram test requires a linked Telegram chat. They do not
  fall back to another channel.
- **Language** follows an explicit `?lang=` selection, then the current session,
  then the user's saved preference.
- **Check-out** records the finish time and, when available, the device location.
  If location permission or GPS fails, the check-out still submits without coordinates.
- **Reports** can be printed or saved as PDF from the styled HTML report page.

When updating an existing database, run the participant-group migration once:

```bash
php db/migrations/20261108_assignment_participant_group.php
```

New databases already include this column in `db/schema.mysql.sql`.

## Security notes

- CSRF token on every POST; session cookie is HttpOnly + SameSite=Lax (+ Secure over HTTPS).
- Argon2/bcrypt via `password_hash`; OTP stored hashed, 10-minute expiry, 5 attempts.
- Table and column names in `db_insert`/`db_update` are validated against `[A-Za-z][A-Za-z0-9_]*`.
- Rate limiting is left to the web server or a reverse proxy; add it before production.

## Tests

```bash
php tests/logic_test.php      # distance + check-in rules, no database
php tests/lang_test.php       # BM/EN key parity
php tests/sql_split_test.php  # schema statement splitter
php tests/geo_provider_test.php  # place search normalisation + ranking
php tests/assignment_access_test.php  # participant attendance visibility
php tests/language_selection_test.php  # language override persistence
php tests/attendance_actions_test.php  # check-out and optional review note
```
