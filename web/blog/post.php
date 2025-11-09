<?php
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/feature_guard.php';
echopress_require_feature('blog');
date_default_timezone_set(echopress_timezone());
require_once __DIR__ . '/../includes/utils.php';

$versionFile = __DIR__ . '/../version.txt';

$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

/**
 * Render a friendly 404 “post not found” page and stop execution.
 *
 * @param string $slug The bad slug so we can optionally log or display it.
 */
/**
 * Render a friendly 404 “post not found” page and stop execution.
 *
 * @param string $slug The bad slug so we can optionally log or display it.
 */
function render_post_not_found(string $slug = ''): void
{
    global $version;  // ← bring in the $version from the outer scope

    http_response_code(404);

    // Lightweight excerpt for <meta> description
    $siteName = echopress_site_name();
    $metaDesc = 'The article you were looking for could not be found on ' . $siteName . '.';

    // Build absolute URL helpers
    $host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https')
        . '://' . $_SERVER['HTTP_HOST'];

    // ---- HTML starts here ----
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Post Not Found | <?= htmlspecialchars($siteName) ?></title>

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
        <meta property="og:title" content="Post Not Found">
        <meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>">
        <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
        <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
        <link rel="stylesheet" href="/css/blog.css?v=<?php echo $version; ?>">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
    </head>

    <body class="blog-page blog-post not-found">

        <?php include __DIR__ . '/../includes/header.php'; ?>

        <main class="container">
            <section class="not-found-wrapper">
                <h1><code>404 — Post Not Found</code></h1>
                <p>
                    Sorry, the article you were looking for
                    <?= $slug ? '(<code>' . htmlspecialchars($slug) . '</code>) ' : '' ?>
                    doesn’t exist or has been unpublished.
                </p>

                <p>
                    <a href="/blog/" class="btn">← Back to all blog posts</a>
                </p>
            </section>
        </main>
    </body>

    </html>
    <?php
    exit;
}

if (!isset($slug) || $slug === '') {
    $slug = basename($_GET['article'] ?? '');
}
$preview = $preview ?? isset($_GET['preview']);

if (!isset($post)) {
    require_once __DIR__ . '/../admin/db_connect.php';
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        http_response_code(404);
        echo 'Post not found';
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ",") AS cats
         FROM blog_posts p
         LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
         LEFT JOIN blog_categories c ON pc.category_id = c.id
         WHERE p.slug=?' . ($preview ? '' : ' AND p.published=1 AND p.post_date <= NOW()') . '
         GROUP BY p.id'
    );
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if (!$post) {
        render_post_not_found($slug);

    }
    $post['categories'] = $post['cats'] ? explode(',', $post['cats']) : [];
} else {
    // when included from a generated index.php, ensure categories key exists
    $post['categories'] = $post['categories'] ?? [];
}
function format_blog_date($str)
{
    $ts = strtotime(str_replace('T', ' ', $str));
    return $ts !== false ? date('F j, Y g:i a', $ts) : $str;
}
$bannerFile = __DIR__ . '/data/banner.json';
$banner = ['image' => '/images/blog_banner.jpg', 'srcsetWebp' => '', 'srcsetJpg' => ''];
if (file_exists($bannerFile)) {
    $tmp = json_decode(file_get_contents($bannerFile), true);
    if ($tmp)
        $banner = array_merge($banner, $tmp);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title><?= htmlspecialchars($post['title']) ?></title>
    <?php
    $host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . $_SERVER['HTTP_HOST'];
    $desc = html_excerpt($post['body']);
    // Only use a blog post's image for Open Graph if one was uploaded
    // Otherwise leave the value blank so no default image is used
    $img = !empty($post['image']) ? $host . $post['image'] : '';
    ?>
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="<?= htmlspecialchars(echopress_site_name()) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($post['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($desc) ?>">
    <meta name="description" content="<?= htmlspecialchars($desc) ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($host . '/blog/post.php?article=' . $slug) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($img) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="/css/blog.css?v=<?php echo $version; ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/theme_vars.php'; ?>
</head>

<body class="blog-page blog-post">

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
        <article>
            <div class="post-header">

                <h1><?= htmlspecialchars($post['title']) ?></h1>
                <div class="post-date"><?= htmlspecialchars(format_blog_date($post['post_date'])) ?></div>
                <?php if (!empty($post['categories'])): ?>
                    <div class="post-cats">Categories:
                        <?= htmlspecialchars(implode(', ', blog_category_labels($post['categories']))) ?>
                    </div>
                <?php endif; ?>

            </div>

            <?php if (!empty($post['image'])): ?>
                <div class="post-image">
                    <picture>
                        <?php if (!empty($post['image_srcset_webp'])): ?>
                            <source type="image/webp" srcset="<?= htmlspecialchars($post['image_srcset_webp']) ?>">
                        <?php endif; ?>
                        <?php if (!empty($post['image_srcset_jpg'])): ?>
                            <source type="image/jpeg" srcset="<?= htmlspecialchars($post['image_srcset_jpg']) ?>">
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars(cache_bust($post['image'])) ?>" alt="<?= htmlspecialchars($post['title']) ?>"
                            class="post-header-image">
                    </picture>
                </div>
            <?php endif; ?>


            <div class="post-body">
                <?= $post['body'] ?>
            </div>
            <p>
                ---
            </p>
            <p>
                If you wish to discuss this article or another topic, please feel free to <a href="/contact/">contact
                    us</a>.
            </p>
        </article>
        <?php include __DIR__ . '/../includes/newsletter_widget.php'; ?>
        <p><a href="/blog/">Back to Blog</a></p>
    </main>
</body>

</html>
