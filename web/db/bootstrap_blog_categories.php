<?php
// Insert special blog categories if not present.
require_once __DIR__ . '/../admin/db_connect.php';

$tags = ['album','single','video','appears','coming-soon'];
foreach ($tags as $slug) {
    $stmt = $pdo->prepare('SELECT id FROM blog_categories WHERE name=?');
    $stmt->execute([$slug]);
    if (!$stmt->fetchColumn()) {
        $pdo->prepare('INSERT INTO blog_categories (name) VALUES (?)')->execute([$slug]);
        echo "Inserted $slug\n";
    }
}

