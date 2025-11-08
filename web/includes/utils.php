<?php
require_once __DIR__ . '/app.php';
function format_srcset(string $srcset, string $basePath): string {
    if ($srcset === '') {
        return '';
    }
    $parts = [];
    foreach (explode(',', $srcset) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $sub = preg_split('/\s+/', $p);
        $file = $sub[0];
        $size = $sub[1] ?? '';
        $url = cache_bust($basePath . $file);
        $parts[] = trim($url . ($size ? ' ' . $size : ''));
    }
    return implode(', ', $parts);
}

function cache_bust(string $path): string {
    $root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
    $full = $root . '/' . ltrim($path, '/');
    if (is_file($full)) {
        return $path . '?v=' . filemtime($full);
    }
    return $path;
}

/**
 * Convert a block of HTML to a plain text excerpt.
 * - Decodes HTML entities
 * - Converts common block level tags to spaces
 * - Strips all other tags
 * - Collapses whitespace
 */
function html_excerpt(string $html, int $length = 200): string {
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<(br|p|div|li)[^>]*>/i', ' ', $text);
    $text = strip_tags($text);
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text) > $length) {
        $text = mb_substr($text, 0, $length) . '…';
    }
    return $text;
}

/**
 * Convert arbitrary text to a URL friendly slug using hyphens.
 */
function slugify(string $text): string {
    $text = preg_replace('/[^a-z0-9]+/i', '-', strtolower($text));
    $text = trim($text, '-');
    return $text;
}

/**
 * Map a blog category slug to the user-facing label.
 */
function blog_category_label(string $name): string {
    static $map = [
        'album'       => 'New Album',
        'single'      => 'New EP/Single',
        'video'       => 'New Video',
        'appears'     => 'New Guest Appearance',
        'coming-soon' => 'Coming Soon',
    ];
    return $map[$name] ?? $name;
}

/**
 * Convert a list of category slugs to display labels.
 */
function blog_category_labels(array $names): array {
    return array_map('blog_category_label', $names);
}

/**
 * Determine if any track on an album is marked explicit.
 */
function album_has_explicit(PDO $pdo, string $slug): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM album_tracks t '
        .'JOIN albums a ON t.album_id = a.id '
        .'WHERE a.slug = ? AND t.explicit = 1'
    );
    $stmt->execute([$slug]);
    return $stmt->fetchColumn() > 0;
}
