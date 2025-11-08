<?php
function get_page_meta(PDO $pdo, string $page): array {
    $stmt = $pdo->prepare('SELECT * FROM page_meta WHERE page=?');
    $stmt->execute([$page]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

