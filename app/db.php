<?php
declare(strict_types=1);

const SCHEMA_VERSION = 1;

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']),
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        migrate($pdo);
    }
    return $pdo;
}

/** Führt eine Abfrage mit Parametern aus. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Legt beim ersten Aufruf alle Tabellen an und befüllt Standard-Kategorien. */
function migrate(PDO $pdo): void
{
    try {
        $v = $pdo->query("SELECT v FROM settings WHERE k = 'schema_version'")->fetchColumn();
        if ((int) $v >= SCHEMA_VERSION) {
            return;
        }
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht → Neuinstallation
    }

    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $tables = [
        "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) PRIMARY KEY, v TEXT NOT NULL) $opts",
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            role VARCHAR(10) NOT NULL,
            password_hash VARCHAR(255) NULL,
            invite_token VARCHAR(64) NULL UNIQUE,
            invite_expires INT UNSIGNED NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            last_login_at DATETIME NULL
        ) $opts",
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            slug VARCHAR(90) NOT NULL UNIQUE,
            description VARCHAR(300) NOT NULL DEFAULT '',
            icon VARCHAR(30) NOT NULL DEFAULT 'box',
            sort_order INT NOT NULL DEFAULT 0
        ) $opts",
        "CREATE TABLE IF NOT EXISTS providers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(90) NOT NULL UNIQUE,
            legal_form VARCHAR(40) NOT NULL DEFAULT '',
            street VARCHAR(150) NOT NULL DEFAULT '',
            zip VARCHAR(15) NOT NULL DEFAULT '',
            city VARCHAR(100) NOT NULL DEFAULT '',
            country VARCHAR(60) NOT NULL DEFAULT 'Deutschland',
            founded_year SMALLINT NULL,
            employees VARCHAR(30) NOT NULL DEFAULT '',
            website VARCHAR(300) NOT NULL DEFAULT '',
            email VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            short_description VARCHAR(240) NOT NULL DEFAULT '',
            description MEDIUMTEXT NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            submitted_by_name VARCHAR(100) NOT NULL DEFAULT '',
            submitted_by_email VARCHAR(150) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_status (status)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS provider_categories (
            provider_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (provider_id, category_id),
            FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) $opts",
        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            description VARCHAR(1000) NOT NULL DEFAULT '',
            url VARCHAR(300) NOT NULL DEFAULT '',
            price_info VARCHAR(100) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
        ) $opts",
        "CREATE TABLE IF NOT EXISTS reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id INT UNSIGNED NOT NULL,
            rating TINYINT NOT NULL,
            title VARCHAR(120) NOT NULL DEFAULT '',
            comment TEXT NOT NULL,
            author_name VARCHAR(80) NOT NULL,
            author_email VARCHAR(150) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            moderated_at DATETIME NULL,
            moderated_by INT UNSIGNED NULL,
            INDEX idx_provider (provider_id, status),
            FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
            FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
        ) $opts",
        // Zugriffsrechte externer Bearbeiter je Anbieter und Informationsbereich
        "CREATE TABLE IF NOT EXISTS provider_editors (
            user_id INT UNSIGNED NOT NULL,
            provider_id INT UNSIGNED NOT NULL,
            can_edit_master TINYINT(1) NOT NULL DEFAULT 1,
            can_edit_description TINYINT(1) NOT NULL DEFAULT 1,
            can_edit_products TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (user_id, provider_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
        ) $opts",
        // Änderungsvorschläge externer Bearbeiter, werden erst nach Freigabe übernommen
        "CREATE TABLE IF NOT EXISTS provider_changes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            data MEDIUMTEXT NOT NULL,
            sections VARCHAR(255) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            note VARCHAR(1000) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            reviewed_by INT UNSIGNED NULL,
            INDEX idx_status (status, provider_id),
            FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) $opts",
        "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            k VARCHAR(190) NOT NULL,
            ts INT UNSIGNED NOT NULL,
            INDEX idx_k (k, ts)
        ) $opts",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $defaults = [
            ['Fotostudio', 'camera', 'Produktfotos, Lifestyle-Shootings und 360°-Aufnahmen für Listings und Shops.'],
            ['Grafikagentur', 'pen', 'Listing-Grafiken, A+ Content, Brand Stores, Verpackung und Corporate Design.'],
            ['PPC-Agentur', 'target', 'Betreuung von Amazon Ads, Google Shopping und weiteren Marktplatz-Kampagnen.'],
            ['Steuerberater', 'calculator', 'Steuerberatung mit E-Commerce-Fokus: OSS, Umsatzsteuer im Ausland, Buchhaltung.'],
            ['Third-Party-Lager (3PL)', 'warehouse', 'Fulfillment, Lagerung, Prep-Center und Versanddienstleister.'],
            ['Rechtsberatung', 'scale', 'Markenrecht, Abmahnschutz, AGB und Produktkonformität.'],
            ['Übersetzung & Lokalisierung', 'globe', 'Listing-Übersetzungen und Keyword-Lokalisierung für internationale Marktplätze.'],
            ['Software & Tools', 'chart', 'Analyse-, Repricing-, Buchhaltungs- und Warenwirtschafts-Tools.'],
        ];
        $ins = $pdo->prepare('INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?,?,?,?,?)');
        foreach ($defaults as $i => [$name, $icon, $desc]) {
            $ins->execute([$name, slugify($name), $icon, $desc, ($i + 1) * 10]);
        }
    }

    $pdo->prepare("REPLACE INTO settings (k, v) VALUES ('schema_version', ?)")->execute([(string) SCHEMA_VERSION]);
}
