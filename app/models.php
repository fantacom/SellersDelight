<?php
declare(strict_types=1);

/* ---------- Kategorien ---------- */

final class Categories
{
    public static function all(): array
    {
        return q_all("
            SELECT c.*, (
                SELECT COUNT(*) FROM provider_categories pc
                JOIN providers p ON p.id = pc.provider_id AND p.status = 'published'
                WHERE pc.category_id = c.id
            ) AS provider_count
            FROM categories c ORDER BY c.sort_order, c.name
        ");
    }

    public static function bySlug(string $slug): ?array
    {
        return q_one('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public static function byId($id): ?array
    {
        return q_one('SELECT * FROM categories WHERE id = ?', [(int) $id]);
    }

    public static function save(array $data, ?int $id = null): int
    {
        $name = str($data['name'] ?? '', 80);
        $desc = str($data['description'] ?? '', 300);
        $icon = str($data['icon'] ?? '', 30) ?: 'box';
        $order = (int) ($data['sort_order'] ?? 0);
        if ($id) {
            q('UPDATE categories SET name=?, slug=?, description=?, icon=?, sort_order=? WHERE id=?',
                [$name, unique_slug('categories', $name, $id), $desc, $icon, $order, $id]);
            return $id;
        }
        q('INSERT INTO categories (name, slug, description, icon, sort_order) VALUES (?,?,?,?,?)',
            [$name, unique_slug('categories', $name), $desc, $icon, $order]);
        return (int) db()->lastInsertId();
    }

    public static function remove($id): void
    {
        q('DELETE FROM categories WHERE id = ?', [(int) $id]);
    }
}

/* ---------- Anbieter ---------- */

final class Providers
{
    // Bayes-Gewichtung: Anbieter mit wenigen Bewertungen werden zum Mittelwert hin gezogen,
    // damit eine einzelne 5-Sterne-Bewertung nicht automatisch Platz 1 bedeutet.
    private const PRIOR_WEIGHT = 3;
    private const PRIOR_MEAN = 3.5;

    public const SORTS = [
        'rating' => 'score DESC, review_count DESC, p.name',
        'reviews' => 'review_count DESC, score DESC, p.name',
        'name' => 'p.name',
        'newest' => 'p.created_at DESC',
    ];

    private static function select(): string
    {
        $w = self::PRIOR_WEIGHT;
        $m = self::PRIOR_MEAN;
        return "
            SELECT p.*,
              COALESCE(r.n, 0) AS review_count,
              r.avg_rating,
              ($w * $m + COALESCE(r.total, 0)) / ($w + COALESCE(r.n, 0)) AS score
            FROM providers p
            LEFT JOIN (
              SELECT provider_id, COUNT(*) AS n, AVG(rating) AS avg_rating, SUM(rating) AS total
              FROM reviews WHERE status = 'approved' GROUP BY provider_id
            ) r ON r.provider_id = p.id
        ";
    }

    public static function list(array $opts = []): array
    {
        $status = array_key_exists('status', $opts) ? $opts['status'] : 'published';
        $sort = self::SORTS[$opts['sort'] ?? 'rating'] ?? self::SORTS['rating'];
        $limit = (int) ($opts['limit'] ?? 500);
        $where = [];
        $params = [];
        if ($status) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if (!empty($opts['categoryId'])) {
            $where[] = 'p.id IN (SELECT provider_id FROM provider_categories WHERE category_id = ?)';
            $params[] = (int) $opts['categoryId'];
        }
        if (($opts['search'] ?? '') !== '') {
            $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.city LIKE ? OR p.description LIKE ?)';
            $like = '%' . addcslashes($opts['search'], '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = self::select() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY $sort LIMIT $limit";
        return self::attachCategories(q_all($sql, $params));
    }

    public static function topRated(int $limit = 6): array
    {
        return array_values(array_filter(self::list(['sort' => 'rating', 'limit' => $limit]), fn($p) => $p['review_count'] > 0));
    }

    public static function bySlug(string $slug): ?array
    {
        $id = q_val('SELECT id FROM providers WHERE slug = ?', [$slug]);
        return $id ? self::byId($id) : null;
    }

    public static function byId($id): ?array
    {
        $p = q_one(self::select() . ' WHERE p.id = ?', [(int) $id]);
        if (!$p) {
            return null;
        }
        [$p] = self::attachCategories([$p]);
        $p['products'] = q_all('SELECT * FROM products WHERE provider_id = ? ORDER BY sort_order, id', [$p['id']]);
        $p['category_ids'] = array_map(fn($c) => (int) $c['id'], $p['categories']);
        return $p;
    }

    public static function ratingDistribution(int $id): array
    {
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach (q_all("SELECT rating, COUNT(*) AS n FROM reviews WHERE provider_id = ? AND status = 'approved' GROUP BY rating", [$id]) as $r) {
            $dist[(int) $r['rating']] = (int) $r['n'];
        }
        return $dist;
    }

    /** Bereinigt Formulardaten (Längen, URLs, Jahr) – genutzt beim Speichern und für die Änderungsvorschau. */
    public static function normalize(array $data): array
    {
        $founded = (int) ($data['founded_year'] ?? 0);
        $names = to_array($data['product_name'] ?? null);
        $descs = to_array($data['product_description'] ?? null);
        $urls = to_array($data['product_url'] ?? null);
        $prices = to_array($data['product_price'] ?? null);
        $products = [];
        foreach (array_slice($names, 0, 30) as $i => $n) {
            $name = str($n, 150);
            if ($name === '') {
                continue;
            }
            $products[] = [
                'name' => $name,
                'description' => str($descs[$i] ?? '', 1000),
                'url' => normalize_url($urls[$i] ?? ''),
                'price_info' => str($prices[$i] ?? '', 100),
            ];
        }
        return [
            'master' => [
                'name' => str($data['name'] ?? '', 150),
                'legal_form' => str($data['legal_form'] ?? '', 40),
                'street' => str($data['street'] ?? '', 150),
                'zip' => str($data['zip'] ?? '', 15),
                'city' => str($data['city'] ?? '', 100),
                'country' => str($data['country'] ?? '', 60) ?: 'Deutschland',
                'founded_year' => $founded >= 1800 && $founded <= 2100 ? $founded : null,
                'employees' => str($data['employees'] ?? '', 30),
                'website' => normalize_url($data['website'] ?? ''),
                'email' => str($data['email'] ?? '', 150),
                'phone' => str($data['phone'] ?? '', 50),
            ],
            'description' => [
                'short_description' => str($data['short_description'] ?? '', 240),
                'description' => str($data['description'] ?? '', 20000),
            ],
            'products' => $products,
        ];
    }

    /**
     * Speichert einen Anbieter. `$sections` legt fest, welche Bereiche übernommen werden
     * (master, description, categories, products) – so nutzen Admin, externe Bearbeiter
     * und das öffentliche Formular dieselbe Logik mit unterschiedlichen Rechten.
     */
    public static function save(array $data, ?int $id = null, array $sections = [], ?string $status = null): int
    {
        return transaction(function () use ($data, $id, $sections, $status) {
            $in = self::normalize($data);
            if (!$id) {
                $name = $in['master']['name'];
                q("INSERT INTO providers (name, slug, description, status, submitted_by_name, submitted_by_email, created_at, updated_at)
                   VALUES (?,?,'',?,?,?,?,?)", [
                    $name, unique_slug('providers', $name), $status ?: 'pending',
                    str($data['submitted_by_name'] ?? '', 100), str($data['submitted_by_email'] ?? '', 150), now(), now(),
                ]);
                $id = (int) db()->lastInsertId();
            } elseif ($status) {
                q('UPDATE providers SET status = ? WHERE id = ?', [$status, $id]);
            }

            if (!empty($sections['master'])) {
                $m = $in['master'];
                $slugBase = str($data['slug'] ?? '', 80) ?: $m['name'];
                q('UPDATE providers SET name=?, slug=?, legal_form=?, street=?, zip=?, city=?, country=?,
                     founded_year=?, employees=?, website=?, email=?, phone=? WHERE id=?', [
                    $m['name'], unique_slug('providers', $slugBase, $id), $m['legal_form'], $m['street'], $m['zip'],
                    $m['city'], $m['country'], $m['founded_year'], $m['employees'], $m['website'], $m['email'], $m['phone'], $id,
                ]);
            }
            if (!empty($sections['description'])) {
                q('UPDATE providers SET short_description=?, description=? WHERE id=?',
                    [$in['description']['short_description'], $in['description']['description'], $id]);
            }
            if (!empty($sections['categories'])) {
                q('DELETE FROM provider_categories WHERE provider_id = ?', [$id]);
                foreach (array_unique(array_map('intval', to_array($data['category_ids'] ?? null))) as $cid) {
                    if (Categories::byId($cid)) {
                        q('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)', [$id, $cid]);
                    }
                }
            }
            if (!empty($sections['products'])) {
                q('DELETE FROM products WHERE provider_id = ?', [$id]);
                foreach ($in['products'] as $i => $pr) {
                    q('INSERT INTO products (provider_id, name, description, url, price_info, sort_order) VALUES (?,?,?,?,?,?)',
                        [$id, $pr['name'], $pr['description'], $pr['url'], $pr['price_info'], $i]);
                }
            }
            q('UPDATE providers SET updated_at = ? WHERE id = ?', [now(), $id]);
            return $id;
        });
    }

    public static function setStatus($id, string $status): void
    {
        q('UPDATE providers SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), (int) $id]);
    }

    public static function remove($id): void
    {
        q('DELETE FROM providers WHERE id = ?', [(int) $id]);
    }

    public static function countByStatus(): array
    {
        $out = ['pending' => 0, 'published' => 0, 'rejected' => 0];
        foreach (q_all('SELECT status, COUNT(*) n FROM providers GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    private static function attachCategories(array $rows): array
    {
        if (!$rows) {
            return $rows;
        }
        $ids = array_column($rows, 'id');
        $links = q_all('
            SELECT pc.provider_id, c.id, c.name, c.slug FROM provider_categories pc
            JOIN categories c ON c.id = pc.category_id
            WHERE pc.provider_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
            ORDER BY c.sort_order, c.name', $ids);
        foreach ($rows as &$r) {
            $r['review_count'] = (int) $r['review_count'];
            $r['categories'] = array_values(array_filter($links, fn($l) => (int) $l['provider_id'] === (int) $r['id']));
        }
        return $rows;
    }
}

/* ---------- Bewertungen ---------- */

final class Reviews
{
    public static function forProvider(int $providerId): array
    {
        return q_all("SELECT * FROM reviews WHERE provider_id = ? AND status = 'approved' ORDER BY created_at DESC", [$providerId]);
    }

    public static function list(?string $status = 'pending'): array
    {
        $order = $status === 'pending' ? 'ASC' : 'DESC';
        return q_all('
            SELECT r.*, p.name AS provider_name, p.slug AS provider_slug, p.status AS provider_status
            FROM reviews r JOIN providers p ON p.id = r.provider_id
            ' . ($status ? 'WHERE r.status = ?' : '') . "
            ORDER BY r.created_at $order LIMIT 500", $status ? [$status] : []);
    }

    public static function create(int $providerId, int $rating, string $title, string $comment, string $name, string $email): int
    {
        q('INSERT INTO reviews (provider_id, rating, title, comment, author_name, author_email, created_at) VALUES (?,?,?,?,?,?,?)',
            [$providerId, $rating, $title, $comment, $name, $email, now()]);
        return (int) db()->lastInsertId();
    }

    public static function existsFor(int $providerId, string $email): bool
    {
        return (bool) q_val("SELECT 1 FROM reviews WHERE provider_id = ? AND author_email = ? AND status != 'rejected'", [$providerId, $email]);
    }

    public static function moderate($id, string $status, ?int $userId): void
    {
        q('UPDATE reviews SET status = ?, moderated_at = ?, moderated_by = ? WHERE id = ?', [$status, now(), $userId, (int) $id]);
    }

    public static function remove($id): void
    {
        q('DELETE FROM reviews WHERE id = ?', [(int) $id]);
    }

    public static function countByStatus(): array
    {
        $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach (q_all('SELECT status, COUNT(*) n FROM reviews GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public static function recentApproved(int $limit = 3): array
    {
        return q_all("
            SELECT r.*, p.name AS provider_name, p.slug AS provider_slug FROM reviews r
            JOIN providers p ON p.id = r.provider_id AND p.status = 'published'
            WHERE r.status = 'approved' ORDER BY r.created_at DESC, r.id DESC LIMIT " . (int) $limit);
    }
}

/* ---------- Änderungsvorschläge externer Bearbeiter ---------- */

final class Changes
{
    /** Legt einen Vorschlag an; ein offener Vorschlag desselben Bearbeiters wird ersetzt. */
    public static function submit(int $providerId, int $userId, array $data, array $sections): void
    {
        transaction(function () use ($providerId, $userId, $data, $sections) {
            q("DELETE FROM provider_changes WHERE provider_id = ? AND user_id = ? AND status = 'pending'", [$providerId, $userId]);
            q('INSERT INTO provider_changes (provider_id, user_id, data, sections, created_at) VALUES (?,?,?,?,?)',
                [$providerId, $userId, json_encode($data, JSON_UNESCAPED_UNICODE), json_encode($sections), now()]);
        });
    }

    public static function byId($id): ?array
    {
        return self::hydrate(q_one('
            SELECT c.*, p.name AS provider_name, p.slug AS provider_slug, u.name AS user_name, u.email AS user_email
            FROM provider_changes c JOIN providers p ON p.id = c.provider_id LEFT JOIN users u ON u.id = c.user_id
            WHERE c.id = ?', [(int) $id]));
    }

    public static function pendingFor(int $providerId, int $userId): ?array
    {
        return self::hydrate(q_one("SELECT * FROM provider_changes WHERE provider_id = ? AND user_id = ? AND status = 'pending'", [$providerId, $userId]));
    }

    public static function lastDecidedFor(int $providerId, int $userId): ?array
    {
        return q_one("SELECT status, reviewed_at, note FROM provider_changes WHERE provider_id = ? AND user_id = ? AND status != 'pending'
                      ORDER BY reviewed_at DESC, id DESC LIMIT 1", [$providerId, $userId]);
    }

    public static function list(string $status = 'pending'): array
    {
        $order = $status === 'pending' ? 'ASC' : 'DESC';
        return array_map([self::class, 'hydrate'], q_all("
            SELECT c.*, p.name AS provider_name, u.name AS user_name, u.email AS user_email
            FROM provider_changes c JOIN providers p ON p.id = c.provider_id LEFT JOIN users u ON u.id = c.user_id
            WHERE c.status = ? ORDER BY c.created_at $order LIMIT 300", [$status]));
    }

    public static function countPending(): int
    {
        return (int) q_val("SELECT COUNT(*) FROM provider_changes WHERE status = 'pending'");
    }

    public static function decide(int $id, string $status, int $reviewerId, string $note = ''): void
    {
        q('UPDATE provider_changes SET status = ?, reviewed_by = ?, reviewed_at = ?, note = ? WHERE id = ?', [$status, $reviewerId, now(), $note, $id]);
    }

    private static function hydrate(?array $row): ?array
    {
        if ($row) {
            $row['data'] = json_decode($row['data'], true) ?: [];
            $row['sections'] = json_decode($row['sections'], true) ?: [];
        }
        return $row;
    }
}

/* ---------- Benutzer & Bearbeitungsrechte ---------- */

final class Users
{
    public static function all(): array
    {
        return q_all('
            SELECT u.*, (SELECT COUNT(*) FROM provider_editors pe WHERE pe.user_id = u.id) AS provider_count
            FROM users u ORDER BY u.role, u.name');
    }

    public static function byId($id): ?array
    {
        return q_one('SELECT * FROM users WHERE id = ?', [(int) $id]);
    }

    public static function byEmail(string $email): ?array
    {
        return q_one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    public static function byInviteToken(string $t): ?array
    {
        return q_one('SELECT * FROM users WHERE invite_token = ? AND invite_expires > ?', [$t, time()]);
    }

    public static function count(): int
    {
        return (int) q_val('SELECT COUNT(*) FROM users');
    }

    public static function create(string $email, string $name, string $role): int
    {
        q('INSERT INTO users (email, name, role, created_at) VALUES (?,?,?,?)', [mb_strtolower($email), $name, $role, now()]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, string $name, string $role, bool $active): void
    {
        q('UPDATE users SET name=?, role=?, active=? WHERE id=?', [$name, $role, $active ? 1 : 0, $id]);
    }

    public static function setInvite(int $id, string $t, int $expires): void
    {
        q('UPDATE users SET invite_token=?, invite_expires=? WHERE id=?', [$t, $expires, $id]);
    }

    public static function setPassword(int $id, string $password): void
    {
        q('UPDATE users SET password_hash=?, invite_token=NULL, invite_expires=NULL WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function touchLogin(int $id): void
    {
        q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $id]);
    }

    public static function remove(int $id): void
    {
        q('DELETE FROM users WHERE id = ?', [$id]);
    }

    public static function countAdmins(): int
    {
        return (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
    }

    public static function assignments(int $userId): array
    {
        return q_all('
            SELECT pe.*, p.name AS provider_name, p.slug AS provider_slug, p.status AS provider_status
            FROM provider_editors pe JOIN providers p ON p.id = pe.provider_id
            WHERE pe.user_id = ? ORDER BY p.name', [$userId]);
    }

    public static function assignment(int $userId, int $providerId): ?array
    {
        return q_one('SELECT * FROM provider_editors WHERE user_id = ? AND provider_id = ?', [$userId, $providerId]);
    }

    public static function editorsOf(int $providerId): array
    {
        return q_all('
            SELECT pe.*, u.name, u.email FROM provider_editors pe JOIN users u ON u.id = pe.user_id
            WHERE pe.provider_id = ? ORDER BY u.name', [$providerId]);
    }

    public static function assign(int $userId, int $providerId, array $perms): void
    {
        q('INSERT INTO provider_editors (user_id, provider_id, can_edit_master, can_edit_description, can_edit_products)
           VALUES (?,?,?,?,?)
           ON DUPLICATE KEY UPDATE can_edit_master=VALUES(can_edit_master),
             can_edit_description=VALUES(can_edit_description), can_edit_products=VALUES(can_edit_products)',
            [$userId, $providerId, (int) !empty($perms['master']), (int) !empty($perms['description']), (int) !empty($perms['products'])]);
    }

    public static function unassign(int $userId, int $providerId): void
    {
        q('DELETE FROM provider_editors WHERE user_id = ? AND provider_id = ?', [$userId, $providerId]);
    }
}
