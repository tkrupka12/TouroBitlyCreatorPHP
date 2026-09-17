<?php

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function ensure_column(string $table, string $column, string $definition): void {
    $cols = db()->query("PRAGMA table_info({$table})")->fetchAll();
    foreach ($cols as $c) {
        if ($c['name'] === $column) return;
    }
    db()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

function init_db(): void {
    db()->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT,
            is_admin INTEGER NOT NULL DEFAULT 0
        )
    ');
    db()->exec('
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
    ');
    db()->exec('
        CREATE TABLE IF NOT EXISTS remember_tokens (
            token TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
    ');

    ensure_column('links', 'created_at', 'TEXT');
    ensure_column('links', 'updated_at', 'TEXT');
    ensure_column('links', 'expires_at', 'TEXT');
    ensure_column('links', 'notes',      'TEXT');
    ensure_column('links', 'updated_by', 'INTEGER');
    ensure_column('links', 'clicks',     'INTEGER NOT NULL DEFAULT 0');
    ensure_column('links', 'last_clicked_at', 'TEXT');
    ensure_column('users', 'pending_reset', 'INTEGER NOT NULL DEFAULT 0');

    $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([ADMIN_USERNAME]);
    $admin = $stmt->fetch();
    if (!$admin) {
        $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)')
            ->execute([ADMIN_USERNAME, $hash]);
        $admin_id = (int)db()->lastInsertId();
    } else {
        $admin_id = (int)$admin['id'];
    }

    $root = db()->prepare('SELECT id FROM links WHERE slug = ?');
    $root->execute([ROOT_LINK_SLUG]);
    if (!$root->fetch()) {
        db()->prepare('INSERT INTO links (user_id, slug, url, created_at, notes) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                $admin_id,
                ROOT_LINK_SLUG,
                ROOT_LINK_URL,
                date(TIMESTAMP_FMT),
                'Homepage redirect for tou.ro',
            ]);
    }
}
