# tou.ro Link Manager

A small Flask + SQLite URL shortener with a full user-management layer. Admins create accounts and hand out credentials; users log in to create custom `tou.ro/<slug>` short links, set expirations, add notes, and see live click counts.

## Features

### Link management
- Custom slugs (letters, numbers, dashes, underscores; reserved routes are blocked)
- Optional expiration dates — expired links show a dedicated "gone" page
- Notes per link with hover preview and inline editing (no page reload)
- One-click **Copy Link** button next to every short URL
- Live click counts that update on click, on tab focus, and via a 3-second poll
- Anyone signed in can edit any link; the "Last edited" column shows who touched it last

### Accounts and access
- Admin creates users with a username and initial password
- Copy-friendly email modal auto-opens after user creation with a pre-written message the admin can paste and send
- Users can change their own username and password from a **Profile** page
- Promote/demote admins; the last remaining admin can't be removed or demoted

### Session and password recovery
- 30-minute rolling session with a warning dialog 2 minutes before expiry
- **Keep me logged in** on the login form (30-day remember cookie) — silences the timeout warning
- Auto-redirect to `/login?reason=timeout` with a flash message when the session ends
- **Forgot password?** flow — user requests a reset, admin sees a "reset requested" badge on Manage Users and can reset the password inline (same email modal reappears with the new credentials)
- Live "account found / not found" hint on the login form as you type the username

## Requirements

- Python 3.10+
- `flask`, `flask-login`, `werkzeug`

```bash
pip install -r requirements.txt
```

## Run locally

```bash
cd src
python3 app.py
```

The app starts on `http://localhost:5000` and creates `touro_users.db` (SQLite) in the `src/` directory on first run.

### Default admin

```
username: admin
password: admin123
```

**Change this before deploying anywhere.** The credentials are hardcoded at the top of `src/app.py` and only used to seed the first admin — after login, use the Profile page (or create a fresh admin and remove the default) to rotate them.

Also set a real secret in production:

```bash
export SECRET_KEY="something-long-and-random"
```

## Project layout

```
.
├── README.md
├── requirements.txt
├── .gitignore
└── src/
    ├── app.py                       # Flask app: routes, auth, DB
    └── templates/
        ├── base.html                # Layout, nav, session-timeout dialog
        ├── login.html               # Login + remember-me + live username check
        ├── forgot_password.html     # Request a reset
        ├── profile.html             # Self-service username/password change
        ├── admin_users.html         # Manage users, inline reset, credentials modal
        ├── index.html               # Create link + link table with live clicks
        ├── edit_link.html           # Full link edit
        ├── expired.html             # Shown when a slug's expiration has passed
        └── not_found.html           # Shown for unknown slugs
```

## Notes

- Passwords are hashed with `werkzeug.security.generate_password_hash` (pbkdf2:sha256).
- Database schema migrates itself on startup — new columns are added idempotently, so upgrading an existing DB just works.
- No email provider is wired up. The "forgot password" flow relies on the admin resetting the password and copying the email template to send manually.
