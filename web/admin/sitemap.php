<?php
require_once __DIR__ . '/../includes/app.php';

function regenerate_sitemap(PDO $pdo): void
{
    $baseUrl = echopress_base_url();
    if ($baseUrl === '') {
        $baseUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    }
    $baseUrl = rtrim($baseUrl, '/');
    $static = [
        '',
        '/blog/',
        '/discography/',
        '/discography/playlists/',
        '/contact/'
    ];

    $stmt = $pdo->query('SELECT slug FROM albums WHERE live=1 AND comingSoon=0');
    $albumSlugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $playlistStmt = $pdo->query('SELECT slug FROM playlists WHERE live=1 AND comingSoon=0');
    $playlistSlugs = $playlistStmt->fetchAll(PDO::FETCH_COLUMN);

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    foreach ($static as $path) {
        $xml .= "  <url>\n    <loc>{$baseUrl}{$path}</loc>\n  </url>\n";
    }

    foreach ($albumSlugs as $slug) {
        $xml .= "  <url>\n    <loc>{$baseUrl}/discography/albums/{$slug}/</loc>\n  </url>\n";
    }

    foreach ($playlistSlugs as $slug) {
        $xml .= "  <url>\n    <loc>{$baseUrl}/discography/playlists/{$slug}/</loc>\n  </url>\n";
    }

    $xml .= "</urlset>\n";

    file_put_contents(__DIR__ . '/../sitemap.xml', $xml);
}
