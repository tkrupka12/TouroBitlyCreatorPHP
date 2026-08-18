import os
import re
import sqlite3
from contextlib import contextmanager
from datetime import datetime, timedelta
from functools import wraps
from urllib.parse import urlparse

from flask import Flask, render_template, request, redirect, url_for, flash, jsonify, abort, session
from flask_login import (
    LoginManager, UserMixin, login_user, login_required, logout_user, current_user,
)
from werkzeug.security import generate_password_hash, check_password_hash

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE_DIR, 'touro_users.db')
SLUG_PATTERN = re.compile(r'^[A-Za-z0-9_-]{1,64}$')
RESERVED_SLUGS = {'login', 'logout', 'register', 'shorten', 'static', 'admin', 'links'}

# Hardcoded initial admin. Change these for production use.
ADMIN_USERNAME = 'admin'
ADMIN_PASSWORD = 'admin123'

TIMESTAMP_FMT = '%Y-%m-%d %H:%M:%S'
DATETIME_LOCAL_FMTS = ('%Y-%m-%dT%H:%M:%S', '%Y-%m-%dT%H:%M')

SESSION_LIFETIME_MINUTES = 30
REMEMBER_DURATION_DAYS = 30

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'dev-secret-change-me')
app.config['PERMANENT_SESSION_LIFETIME'] = timedelta(minutes=SESSION_LIFETIME_MINUTES)
app.config['REMEMBER_COOKIE_DURATION'] = timedelta(days=REMEMBER_DURATION_DAYS)

login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'


@contextmanager
def get_db():
    conn = sqlite3.connect(DB_PATH)
    try:
        yield conn
    finally:
        conn.close()


class User(UserMixin):
    def __init__(self, id, username, is_admin=False):
        self.id = id
        self.username = username
        self.is_admin = bool(is_admin)


@login_manager.user_loader
def load_user(user_id):
    with get_db() as conn:
        row = conn.execute(
            'SELECT id, username, is_admin FROM users WHERE id = ?', (user_id,)
        ).fetchone()
    return User(id=row[0], username=row[1], is_admin=row[2]) if row else None


def admin_required(view):
    @wraps(view)
    @login_required
    def wrapper(*args, **kwargs):
        if not current_user.is_admin:
            abort(403)
        return view(*args, **kwargs)
    return wrapper


@app.context_processor
def inject_session_info():
    return {
        'session_lifetime_seconds': int(app.config['PERMANENT_SESSION_LIFETIME'].total_seconds()),
    }


def _ensure_column(conn, table, column, definition):
    cols = {row[1] for row in conn.execute(f'PRAGMA table_info({table})').fetchall()}
    if column not in cols:
        conn.execute(f'ALTER TABLE {table} ADD COLUMN {column} {definition}')


def init_db():
    with get_db() as conn:
        conn.execute('''
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT,
                is_admin INTEGER NOT NULL DEFAULT 0
            )
        ''')
        conn.execute('''
            CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                url TEXT NOT NULL,
                created_at TEXT,
                updated_at TEXT,
                expires_at TEXT,
                notes TEXT,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ''')
        # Idempotent migration for older DBs that predate the new columns.
        _ensure_column(conn, 'links', 'created_at', 'TEXT')
        _ensure_column(conn, 'links', 'updated_at', 'TEXT')
        _ensure_column(conn, 'links', 'expires_at', 'TEXT')
        _ensure_column(conn, 'links', 'notes', 'TEXT')
        _ensure_column(conn, 'links', 'updated_by', 'INTEGER')
        _ensure_column(conn, 'links', 'clicks', 'INTEGER NOT NULL DEFAULT 0')
        _ensure_column(conn, 'users', 'pending_reset', 'INTEGER NOT NULL DEFAULT 0')
        conn.commit()

        row = conn.execute(
            'SELECT id FROM users WHERE username = ?', (ADMIN_USERNAME,)
        ).fetchone()
        if not row:
            conn.execute(
                'INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)',
                (ADMIN_USERNAME, generate_password_hash(ADMIN_PASSWORD, method='pbkdf2:sha256')),
            )
            conn.commit()
    print("Database initialized.")


def now_str():
    return datetime.now().strftime(TIMESTAMP_FMT)


def parse_expires_at(value):
    if not value:
        return None
    value = value.strip()
    if not value:
        return None
    for fmt in DATETIME_LOCAL_FMTS:
        try:
            return datetime.strptime(value, fmt).strftime(TIMESTAMP_FMT)
        except ValueError:
            continue
    return None


def is_expired(expires_at):
    if not expires_at:
        return False
    try:
        return datetime.strptime(expires_at, TIMESTAMP_FMT) <= datetime.now()
    except ValueError:
        return False


def is_valid_url(url):
    if any(char.isspace() for char in url):
        return False
    parsed_url = urlparse(url)
    return parsed_url.scheme in ('http', 'https') and bool(parsed_url.hostname)


def is_valid_slug(slug):
    return bool(SLUG_PATTERN.fullmatch(slug)) and slug not in RESERVED_SLUGS


@app.route('/')
@login_required
def index():
    with get_db() as conn:
        rows = conn.execute('''
            SELECT links.id, links.slug, links.url, creators.username, links.user_id,
                   links.created_at, links.updated_at, links.expires_at, links.notes,
                   editors.username, links.clicks
            FROM links
            JOIN users AS creators ON creators.id = links.user_id
            LEFT JOIN users AS editors ON editors.id = links.updated_by
            ORDER BY links.id DESC
        ''').fetchall()

    all_links = [
        {
            'id': r[0],
            'slug': r[1],
            'url': r[2],
            'creator': r[3],
            'user_id': r[4],
            'created_at': r[5],
            'updated_at': r[6],
            'expires_at': r[7],
            'notes': r[8] or '',
            'updated_by': r[9],
            'clicks': r[10] or 0,
            'expired': is_expired(r[7]),
            'can_delete': current_user.is_admin or r[4] == current_user.id,
        }
        for r in rows
    ]
    return render_template('index.html', all_links=all_links)


@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = (request.form.get('username') or '').strip()
        password = request.form.get('password')
        remember = request.form.get('remember_me') == '1'

        if not username or not password:
            flash('Username and password are required.')
            return redirect(url_for('login'))

        with get_db() as conn:
            user_row = conn.execute(
                'SELECT id, username, password, is_admin FROM users WHERE username = ?',
                (username,),
            ).fetchone()

        if user_row and user_row[2] and check_password_hash(user_row[2], password):
            session.permanent = True
            session['remember_me'] = remember
            login_user(
                User(id=user_row[0], username=user_row[1], is_admin=user_row[3]),
                remember=remember,
            )
            return redirect(url_for('index'))

        flash('Invalid username or password.')
        return redirect(url_for('login'))

    if request.args.get('reason') == 'timeout':
        flash('Your session expired. Please log in again.')
    return render_template('login.html')


@app.route('/admin/users', methods=['GET', 'POST'])
@admin_required
def admin_users():
    if request.method == 'POST':
        new_username = (request.form.get('username') or '').strip()
        new_password = request.form.get('password') or ''
        make_admin = request.form.get('make_admin') == '1'

        if not new_username:
            flash('Username is required.')
            return redirect(url_for('admin_users'))
        if len(new_password) < 6:
            flash('Password must be at least 6 characters.')
            return redirect(url_for('admin_users'))

        hashed = generate_password_hash(new_password, method='pbkdf2:sha256')
        try:
            with get_db() as conn:
                conn.execute(
                    'INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)',
                    (new_username, hashed, 1 if make_admin else 0),
                )
                conn.commit()
        except sqlite3.IntegrityError:
            flash('That username already exists.')
            return redirect(url_for('admin_users'))

        session['new_user_credentials'] = {
            'username': new_username,
            'password': new_password,
            'login_url': url_for('login', _external=True),
            'is_admin': make_admin,
        }
        role = 'admin' if make_admin else 'user'
        flash(f'Added "{new_username}" as {role}.')
        return redirect(url_for('admin_users'))

    with get_db() as conn:
        users = conn.execute(
            'SELECT id, username, is_admin, pending_reset FROM users ORDER BY pending_reset DESC, is_admin DESC, id ASC'
        ).fetchall()
    creds = session.pop('new_user_credentials', None)
    return render_template('admin_users.html', users=users, new_creds=creds)


@app.route('/admin/users/<int:user_id>/delete', methods=['POST'])
@admin_required
def admin_delete_user(user_id):
    if user_id == current_user.id:
        flash('You cannot delete your own account.')
        return redirect(url_for('admin_users'))

    with get_db() as conn:
        row = conn.execute(
            'SELECT username, is_admin FROM users WHERE id = ?', (user_id,)
        ).fetchone()
        if not row:
            flash('User not found.')
            return redirect(url_for('admin_users'))

        if row[1]:
            admin_count = conn.execute(
                'SELECT COUNT(*) FROM users WHERE is_admin = 1'
            ).fetchone()[0]
            if admin_count <= 1:
                flash('Cannot delete the last remaining admin.')
                return redirect(url_for('admin_users'))

        conn.execute('DELETE FROM links WHERE user_id = ?', (user_id,))
        conn.execute('DELETE FROM users WHERE id = ?', (user_id,))
        conn.commit()

    flash(f'{row[0]} removed.')
    return redirect(url_for('admin_users'))


@app.route('/admin/users/<int:user_id>/toggle-admin', methods=['POST'])
@admin_required
def admin_toggle_admin(user_id):
    if user_id == current_user.id:
        flash('You cannot change your own admin status.')
        return redirect(url_for('admin_users'))

    with get_db() as conn:
        row = conn.execute(
            'SELECT username, is_admin FROM users WHERE id = ?', (user_id,)
        ).fetchone()
        if not row:
            flash('User not found.')
            return redirect(url_for('admin_users'))

        currently_admin = bool(row[1])
        if currently_admin:
            admin_count = conn.execute(
                'SELECT COUNT(*) FROM users WHERE is_admin = 1'
            ).fetchone()[0]
            if admin_count <= 1:
                flash('Cannot demote the last remaining admin.')
                return redirect(url_for('admin_users'))

        new_value = 0 if currently_admin else 1
        conn.execute('UPDATE users SET is_admin = ? WHERE id = ?', (new_value, user_id))
        conn.commit()

    action = 'demoted to user' if currently_admin else 'promoted to admin'
    flash(f'{row[0]} {action}.')
    return redirect(url_for('admin_users'))


@app.route('/logout')
@login_required
def logout():
    session.pop('remember_me', None)
    logout_user()
    return redirect(url_for('login'))


@app.route('/session/ping')
@login_required
def session_ping():
    session.permanent = True
    return ('', 204)


@app.route('/check-username')
def check_username():
    username = (request.args.get('u') or '').strip()
    if not username:
        return jsonify({'exists': False})
    with get_db() as conn:
        row = conn.execute(
            'SELECT 1 FROM users WHERE username = ?', (username,)
        ).fetchone()
    return jsonify({'exists': bool(row)})


@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():
    if request.method == 'POST':
        username = (request.form.get('username') or '').strip()
        if username:
            with get_db() as conn:
                conn.execute(
                    'UPDATE users SET pending_reset = 1 WHERE username = ?',
                    (username,),
                )
                conn.commit()
        flash('If that account exists, an administrator has been notified. Contact them to receive a new password.')
        return redirect(url_for('login'))
    return render_template('forgot_password.html')


@app.route('/admin/users/<int:user_id>/reset-password', methods=['POST'])
@admin_required
def admin_reset_password(user_id):
    new_password = request.form.get('password') or ''
    if len(new_password) < 6:
        flash('Password must be at least 6 characters.')
        return redirect(url_for('admin_users'))

    with get_db() as conn:
        row = conn.execute(
            'SELECT username, is_admin FROM users WHERE id = ?', (user_id,)
        ).fetchone()
        if not row:
            flash('User not found.')
            return redirect(url_for('admin_users'))

        hashed = generate_password_hash(new_password, method='pbkdf2:sha256')
        conn.execute(
            'UPDATE users SET password = ?, pending_reset = 0 WHERE id = ?',
            (hashed, user_id),
        )
        conn.commit()

    session['new_user_credentials'] = {
        'username': row[0],
        'password': new_password,
        'login_url': url_for('login', _external=True),
        'is_admin': bool(row[1]),
    }
    flash(f'Password reset for "{row[0]}".')
    return redirect(url_for('admin_users'))


@app.route('/profile', methods=['GET', 'POST'])
@login_required
def profile():
    if request.method == 'POST':
        current_password = request.form.get('current_password') or ''
        new_username = (request.form.get('new_username') or '').strip()
        new_password = request.form.get('new_password') or ''
        confirm_password = request.form.get('confirm_password') or ''

        with get_db() as conn:
            row = conn.execute(
                'SELECT password FROM users WHERE id = ?', (current_user.id,)
            ).fetchone()

            if not row or not check_password_hash(row[0], current_password):
                flash('Current password is incorrect.')
                return redirect(url_for('profile'))

            username_changing = new_username and new_username != current_user.username
            password_changing = bool(new_password)

            if not username_changing and not password_changing:
                flash('Nothing to update.')
                return redirect(url_for('profile'))

            if password_changing:
                if new_password != confirm_password:
                    flash('New password and confirmation do not match.')
                    return redirect(url_for('profile'))
                if len(new_password) < 6:
                    flash('New password must be at least 6 characters.')
                    return redirect(url_for('profile'))

            if username_changing:
                existing = conn.execute(
                    'SELECT id FROM users WHERE username = ? AND id != ?',
                    (new_username, current_user.id),
                ).fetchone()
                if existing:
                    flash('That username is already taken.')
                    return redirect(url_for('profile'))
                conn.execute(
                    'UPDATE users SET username = ? WHERE id = ?',
                    (new_username, current_user.id),
                )

            if password_changing:
                conn.execute(
                    'UPDATE users SET password = ? WHERE id = ?',
                    (generate_password_hash(new_password, method='pbkdf2:sha256'), current_user.id),
                )

            conn.commit()

        flash('Profile updated.')
        return redirect(url_for('profile'))

    return render_template('profile.html')


@app.route('/shorten', methods=['POST'])
@login_required
def shorten_url():
    data = request.get_json() or {}
    original_url = (data.get('url') or '').strip()
    custom_slug = (data.get('slug') or '').strip()
    raw_expires = data.get('expires_at')
    notes = (data.get('notes') or '').strip() or None

    if not original_url or not custom_slug:
        return jsonify({'error': 'Both URL and custom slug are required.'}), 400

    if not original_url.startswith(('http://', 'https://')):
        original_url = 'https://' + original_url

    if not is_valid_url(original_url):
        return jsonify({'error': 'Enter a valid URL.'}), 400

    if not is_valid_slug(custom_slug):
        return jsonify({
            'error': 'Slug must be 1-64 letters, numbers, dashes, or underscores and cannot be a reserved route.'
        }), 400

    expires_at = parse_expires_at(raw_expires)
    if raw_expires and not expires_at:
        return jsonify({'error': 'Invalid expiration date.'}), 400
    if expires_at and datetime.strptime(expires_at, TIMESTAMP_FMT) <= datetime.now():
        return jsonify({'error': 'Expiration date must be in the future.'}), 400

    try:
        with get_db() as conn:
            conn.execute(
                'INSERT INTO links (user_id, slug, url, created_at, expires_at, notes) VALUES (?, ?, ?, ?, ?, ?)',
                (current_user.id, custom_slug, original_url, now_str(), expires_at, notes),
            )
            conn.commit()
    except sqlite3.IntegrityError:
        return jsonify({'error': 'This custom tou.ro/slug is already taken.'}), 400

    short_link = url_for('redirect_to_url', slug=custom_slug, _external=True)
    return jsonify({'success': True, 'short_link': short_link})


@app.route('/links/<int:link_id>/edit', methods=['GET', 'POST'])
@login_required
def edit_link(link_id):
    with get_db() as conn:
        row = conn.execute(
            'SELECT id, user_id, slug, url, created_at, updated_at, expires_at, notes FROM links WHERE id = ?',
            (link_id,),
        ).fetchone()

    if not row:
        abort(404)

    link = {
        'id': row[0], 'user_id': row[1], 'slug': row[2], 'url': row[3],
        'created_at': row[4], 'updated_at': row[5], 'expires_at': row[6],
        'notes': row[7] or '',
    }

    if request.method == 'POST':
        new_url = (request.form.get('url') or '').strip()
        raw_expires = request.form.get('expires_at')
        clear_expiry = request.form.get('clear_expiry') == '1'
        new_notes = (request.form.get('notes') or '').strip() or None

        if not new_url:
            flash('URL is required.')
            return redirect(url_for('edit_link', link_id=link_id))

        if not new_url.startswith(('http://', 'https://')):
            new_url = 'https://' + new_url
        if not is_valid_url(new_url):
            flash('Enter a valid URL.')
            return redirect(url_for('edit_link', link_id=link_id))

        if clear_expiry:
            new_expires = None
        elif raw_expires:
            new_expires = parse_expires_at(raw_expires)
            if not new_expires:
                flash('Invalid expiration date.')
                return redirect(url_for('edit_link', link_id=link_id))
            if datetime.strptime(new_expires, TIMESTAMP_FMT) <= datetime.now():
                flash('Expiration date must be in the future.')
                return redirect(url_for('edit_link', link_id=link_id))
        else:
            new_expires = link['expires_at']

        with get_db() as conn:
            conn.execute(
                'UPDATE links SET url = ?, expires_at = ?, notes = ?, updated_at = ?, updated_by = ? WHERE id = ?',
                (new_url, new_expires, new_notes, now_str(), current_user.id, link_id),
            )
            conn.commit()

        flash('Link updated.')
        return redirect(url_for('index'))

    return render_template('edit_link.html', link=link)


@app.route('/links/<int:link_id>/notes', methods=['POST'])
@login_required
def update_link_notes(link_id):
    data = request.get_json() or {}
    new_notes = (data.get('notes') or '').strip() or None

    with get_db() as conn:
        row = conn.execute('SELECT id FROM links WHERE id = ?', (link_id,)).fetchone()
        if not row:
            return jsonify({'error': 'Link not found.'}), 404

        timestamp = now_str()
        conn.execute(
            'UPDATE links SET notes = ?, updated_at = ?, updated_by = ? WHERE id = ?',
            (new_notes, timestamp, current_user.id, link_id),
        )
        conn.commit()

    return jsonify({
        'success': True,
        'notes': new_notes or '',
        'updated_at': timestamp,
        'updated_by': current_user.username,
    })


@app.route('/links/clicks')
@login_required
def link_click_counts():
    with get_db() as conn:
        rows = conn.execute('SELECT id, clicks FROM links').fetchall()
    return jsonify({str(r[0]): r[1] or 0 for r in rows})


@app.route('/links/<int:link_id>/delete', methods=['POST'])
@login_required
def delete_link(link_id):
    with get_db() as conn:
        row = conn.execute(
            'SELECT user_id FROM links WHERE id = ?', (link_id,)
        ).fetchone()

        if not row:
            flash('Link not found.')
            return redirect(url_for('index'))
        if not current_user.is_admin and row[0] != current_user.id:
            abort(403)

        conn.execute('DELETE FROM links WHERE id = ?', (link_id,))
        conn.commit()

    flash('Link deleted.')
    return redirect(url_for('index'))


@app.route('/<path:slug>')
def redirect_to_url(slug):
    with get_db() as conn:
        result = conn.execute(
            'SELECT url, expires_at FROM links WHERE slug = ?', (slug,)
        ).fetchone()

        if not result:
            return render_template('not_found.html', slug=slug), 404

        url, expires_at = result
        if is_expired(expires_at):
            return render_template('expired.html', slug=slug, expires_at=expires_at), 410

        conn.execute('UPDATE links SET clicks = clicks + 1 WHERE slug = ?', (slug,))
        conn.commit()

    return redirect(url)


if __name__ == '__main__':
    init_db()
    app.run(debug=True, port=5000)
