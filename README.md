# tou.ro Link Manager

A PHP + SQLite URL shortener for creating and managing Touro short links. Super admins create groups and accounts; signed-in users create custom or random `tou.ro/<short-url>` links, set expirations, add notes, and see live click counts.

`tou.ro/` is display branding. While you run the app locally, links actually open on whatever host you use (for example `http://localhost:8000/admissions`).

## Features

### Link management
- Custom short URLs (lowercase letters, numbers, dashes, underscores; reserved routes are blocked)
- Optional automatic 6-character short URLs
- Optional expiration dates — expired links leave the main list and show a dedicated “gone” page
- An **Expired Links** tab for group admins and super admins
- Notes with hover preview and inline editing
- One-click **Copy** next to every short URL
- Live click counts and a **Last clicked** time (on click, on tab focus, and a 3-second poll)
- Link creators and group admins can edit or delete links; **Last edited** shows who touched it last
- `https://tou.ro` and `https://tou.ro/` always go to `https://www.touro.edu`. That row stays in the links table so you can see clicks, but it cannot be updated or deleted in the app. Change `ROOT_LINK_URL` in the software to point it somewhere else. The signed-in app lives at `/links/`.
- Super admins see a Group column and pick a group when creating a link

### Accounts, groups, and roles
- Three roles: **super admin**, **group admin**, and **user**
- Super admins manage every group; group admins only see their own group
- Create users with a name, email (used to log in), initial password, role, and group
- Super admins have no group (“all groups”). Choosing **super admin** hides the group picker
- Copy-friendly email modal after creating a user or resetting a password
- Users can change their name, email, and password on **Profile**
- The last remaining super admin cannot be removed or demoted
- Removing a user opens a popup: transfer their links to someone else, or delete them

### Session and password recovery
- 30-minute rolling session with a warning 2 minutes before expiry
- **Keep me logged in** (30-day cookie) silences the timeout warning
- **Forgot password?** — the user requests a reset; an admin sees a badge on Manage Users and sets a new password
- Live “account found / not found” hint on the login form

## Requirements

- PHP 8.1+ with the PDO SQLite extension
- nginx with PHP-FPM for production
- PHP’s built-in server is enough for local development

## Run locally

```bash
cd src
php -S localhost:8000 router.php
```

The router keeps private application files from being served. Open `http://localhost:8000/login/` (or `/links/` after you sign in). Visiting `/` locally follows the same homepage redirect as `tou.ro` (`https://www.touro.edu`). On first run the app creates `src/db/touro_users.db` (SQLite).

### Configuration (`.env`)

Copy `src/.env.example` to `src/.env` and edit the values. The app loads that file on startup. Real environment variables still take precedence if they are already set.

```bash
cp src/.env.example src/.env
```

Keep secrets in `.env`, not in PHP source:

| Variable | Purpose |
| --- | --- |
| `SECRET_KEY` | Session secret. Generate with `openssl rand -hex 32` |
| `SESSION_COOKIE_NAME` | PHP session cookie name |
| `SESSION_LIFETIME_MINUTES` | Idle timeout when “Keep me logged in” is off |
| `REMEMBER_DURATION_DAYS` | Cookie lifetime when “Keep me logged in” is on |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | First admin account, used only if the database has no users |
| `SHORT_LINK_DOMAIN` | Display branding for short links |
| `ROOT_LINK_URL` | Where the public homepage `tou.ro/` sends visitors |
| `DEFAULT_GROUP_NAME` | Group created on first run |
| `DB_PATH` | Optional absolute path to the SQLite file |

`.env` is gitignored. Do not commit it.

Runtime login/session state remains in PHP's server-side `$_SESSION`. The `.env` file is only for deployment configuration and secrets.

## Deploy with nginx

Copy `nginx.conf.example` into your nginx configuration, then update:

- `server_name` for the deployed hostname
- `root` to the absolute path of this project's `src` directory
- `fastcgi_pass` to the PHP-FPM socket or address installed on the server

Test and reload nginx after enabling the site:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

The configuration sends `/` to `https://www.touro.edu`, serves the signed-in app from `/links/` (login, profile, create links), and keeps admin tools under `/admin/`. Unknown root paths such as `/admissions` go to `redirects/index.php`. Web access to `.env`, `db/`, `templates/`, and `lib.php` is blocked.

### Default admin

```
login:    admin
password: admin123
```

That account is a super admin. Change the password from **Profile** before deploying. The seed username and password come from `.env` and are only used to create the first admin if none exists.

## Project layout

```
.
├── README.md
├── .gitignore
├── nginx.conf.example              # nginx + PHP-FPM server block
└── src/
    ├── .env.example                 # Template for secrets and session settings
    ├── .env                         # Local secrets (gitignored; copy from .env.example)
    ├── index.php                    # Public homepage: tou.ro → www.touro.edu
    ├── lib.php                      # Database, sessions, auth helpers
    ├── router.php                   # Local server: app files + redirects
    ├── .htaccess                    # Equivalent Apache rules (optional)
    ├── links/                       # Signed-in create-link page + active-link table
    ├── login/                       # Login page and account lookup
    ├── logout/                      # Logout endpoint
    ├── forgot-password/             # Password-reset request page
    ├── profile/                     # Account profile page
    ├── edit-links/                  # Link editor
    ├── admin/
    │   ├── users/                   # User administration
    │   ├── groups/                  # Group administration
    │   └── expired-links/           # Expired-link list
    ├── redirects/
    │   └── index.php                # Resolves root short-link slugs
    ├── db/
    │   └── touro_users.db           # Local SQLite data (gitignored)
    └── templates/
        ├── base.php                 # Layout, nav, session-timeout dialog
        ├── login.php                # Login + remember-me + live email check
        ├── forgot_password.php      # Request a reset
        ├── profile.php              # Change name, email, password
        ├── admin_users.php          # Create users, roles, remove with link transfer
        ├── admin_groups.php         # Super-admin group management
        ├── admin_expired.php        # Expired links (admins only)
        ├── index.php                # Create-short-link form + link table
        ├── edit_link.php            # Full link edit
        ├── expired.php              # Shown when a short URL has expired
        └── not_found.php            # Shown for unknown short URLs
```

## Notes

- Passwords are hashed with PHP `password_hash` (and still verify older Flask `pbkdf2` hashes if you upgraded an existing database).
- The schema migrates itself on startup: columns are renamed or added idempotently, so an older SQLite file keeps working.
- Logins are stored in `users.username_email`. A separate `users.users_name` holds the person’s display name.
- No email provider is wired up. After creating a user or resetting a password, copy the template from the modal and send it yourself.
- `src/db/` and `src/.env` are gitignored so local accounts and secrets are not committed.
