<?php
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/feature_guard.php';
echopress_require_feature('blog');
date_default_timezone_set(echopress_timezone());
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/page_meta.php';

$versionFile = dirname(__DIR__) . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

$posts = [];
function format_blog_date($str)
{
    $ts = strtotime(str_replace('T', ' ', $str));
    return $ts !== false ? date('F j, Y g:i a', $ts) : $str;
}

function prepare_blog_post_payload(array $post): array
{
    return [
        'slug' => $post['slug'],
        'title' => $post['title'],
        'date' => format_blog_date($post['post_date']),
        'categories' => blog_category_labels($post['categories'] ?? []),
        'excerpt' => html_excerpt($post['body']),
        'image' => $post['image'] ? cache_bust($post['image']) : '',
        'imageWebp' => $post['image_srcset_webp'] ?? '',
        'imageJpg' => $post['image_srcset_jpg'] ?? '',
    ];
}
$stmt = $pdo->prepare(
    'SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ",") AS cats
     FROM blog_posts p
     LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
     LEFT JOIN blog_categories c ON pc.category_id = c.id
     WHERE p.published=1 AND p.post_date <= NOW()
     GROUP BY p.id
     ORDER BY p.post_date DESC'
);
$stmt->execute();
$posts = $stmt->fetchAll();
foreach ($posts as &$p) {
    $p['categories'] = $p['cats'] ? explode(',', $p['cats']) : [];
}
unset($p);

$postsPerPage = 15;
$showAll = isset($_GET['all']) && $_GET['all'] === '1';
if ($showAll) {
    $initialPosts = $posts;
    $postChunks = [];
} else {
    $initialPosts = array_slice($posts, 0, $postsPerPage);
    $remainingPosts = array_slice($posts, $postsPerPage);
    $postChunks = [];
    if (!empty($remainingPosts)) {
        $prepared = array_map('prepare_blog_post_payload', $remainingPosts);
        $postChunks = array_chunk($prepared, $postsPerPage);
    }
}
$hasMorePosts = !$showAll && !empty($postChunks);

$bannerFile = __DIR__ . '/data/banner.json';
$banner = ['image' => '/images/blog_banner.jpg', 'srcsetWebp' => '', 'srcsetJpg' => ''];
if (file_exists($bannerFile)) {
    $tmp = json_decode(file_get_contents($bannerFile), true);
    if ($tmp)
        $banner = array_merge($banner, $tmp);
}

$pageMeta = get_page_meta($pdo, '/blog/index.php');
$siteName = echopress_site_name();
$metaTitle = $pageMeta['title'] ?? 'Blog';
$metaDesc  = $pageMeta['description'] ?? ('Updates and articles from ' . $siteName);
$metaKeywords = $pageMeta['keywords'] ?? '';
$ogTitle = $pageMeta['og_title'] ?? $metaTitle;
$ogDesc  = $pageMeta['og_description'] ?? $metaDesc;
$ogImage = $pageMeta['og_image'] ?? $banner['image'];
$ogWebp  = $pageMeta['og_image_srcset_webp'] ?? $banner['srcsetWebp'];
$ogJpg   = $pageMeta['og_image_srcset_jpg'] ?? $banner['srcsetJpg'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title><?= htmlspecialchars($metaTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>"/>
<?php if ($metaKeywords !== ''): ?>
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>" />
<?php endif; ?>
    <?php
    $host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . $_SERVER['HTTP_HOST'];
    ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($host . '/blog/') ?>">
    <meta property="og:image"
        content="<?= htmlspecialchars($host . $ogImage) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="/css/blog.css?v=<?php echo $version; ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/theme_vars.php'; ?>
</head>

<body class="blog-page">

    <?php include __DIR__ . '/../includes/header.php'; ?>


    <div class="banner">
        <picture>
            <?php if ($banner['srcsetWebp']): ?>
                <source type="image/webp" srcset="<?= htmlspecialchars($banner['srcsetWebp']) ?>">
            <?php endif; ?>
            <?php if ($banner['srcsetJpg']): ?>
                <source type="image/jpeg" srcset="<?= htmlspecialchars($banner['srcsetJpg']) ?>">
            <?php endif; ?>
            <img src="<?= htmlspecialchars(cache_bust($banner['image'])) ?>" alt="Blog banner">
        </picture>
        <h1>The Error Report</h1>
    </div>
    <main class="container">

        <div id="blog-post-list">
        <?php foreach ($initialPosts as $post): ?>
            <article>
                <?php if (!empty($post['image'])): ?>
                    <a href="post.php?article=<?= urlencode($post['slug']) ?>">
                        <picture>
                            <?php if (!empty($post['image_srcset_webp'])): ?>
                                <source type="image/webp" srcset="<?= htmlspecialchars($post['image_srcset_webp']) ?>">
                            <?php endif; ?>
                            <?php if (!empty($post['image_srcset_jpg'])): ?>
                                <source type="image/jpeg" srcset="<?= htmlspecialchars($post['image_srcset_jpg']) ?>">
                            <?php endif; ?>
                            <img src="<?= htmlspecialchars(cache_bust($post['image'])) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="post-list-thumb">
                        </picture>
                    </a>
                <?php endif; ?>
                <div class="post-details">

                    <h2><a
                            href="post.php?article=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a>
                    </h2>
                    <div class="post-date"><?= htmlspecialchars(format_blog_date($post['post_date'])) ?></div>
                    <?php if (!empty($post['categories'])): ?>
                        <div class="post-cats">Categories: <?= htmlspecialchars(implode(', ', blog_category_labels($post['categories']))) ?></div>
                    <?php endif; ?>
                    <p><?= htmlspecialchars(html_excerpt($post['body'])) ?> <a
                            href="post.php?article=<?= urlencode($post['slug']) ?>">Read More</a></p>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ($hasMorePosts): ?>
            <div class="blog-load-more">
                <button type="button" id="blog-load-more">Show More</button>
                <noscript><p><a href="?all=1">View full archive</a></p></noscript>
            </div>
        <?php elseif (!$showAll && empty($initialPosts)): ?>
            <p class="blog-load-more__empty">No posts yet. Check back soon!</p>
        <?php endif; ?>
        <?php include __DIR__ . '/../includes/newsletter_widget.php'; ?>
    </main>
    <?php if ($hasMorePosts): ?>
        <script>
            window.blogPostChunks = <?= json_encode($postChunks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        </script>
        <script src="/js/blog-load-more.js?v=<?= htmlspecialchars($version) ?>" defer></script>
    <?php endif; ?>
</body>

</html>
