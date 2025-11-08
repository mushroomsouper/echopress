<?php
require_once __DIR__ . '/session_secure.php';
session_start();
date_default_timezone_set('America/Regina');
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/../includes/utils.php';
$slug = basename($_GET['article'] ?? '');
$stmt = $pdo->query('SELECT name FROM blog_categories ORDER BY name');
$allCats = $stmt->fetchAll(PDO::FETCH_COLUMN);
$post = ['title'=>'','date'=>date('Y-m-d H:i'),'categories'=>[], 'body'=>'', 'image'=>'','srcsetWebp'=>'','srcsetJpg'=>'','published'=>1];
if ($slug) {
    $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE slug=?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if ($row) {
        $post = [
            'title' => $row['title'],
            'date'  => $row['post_date'],
            'categories' => [],
            'body'  => $row['body'],
            'image' => $row['image'],
            'srcsetWebp' => $row['image_srcset_webp'],
            'srcsetJpg'  => $row['image_srcset_jpg'],
            'published' => (int)$row['published'],
        ];
        $c = $pdo->prepare('SELECT c.name FROM blog_categories c JOIN blog_post_categories pc ON c.id=pc.category_id WHERE pc.post_id=?');
        $c->execute([$row['id']]);
        $post['categories'] = $c->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = ($slug ? 'Edit' : 'New') . ' Post';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
    $versionParam = htmlspecialchars($version, ENT_QUOTES);
    $headExtras = implode("\n", [
        '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">',
        '<link rel="stylesheet" href="https://unpkg.com/pell/dist/pell.min.css">',
        '<script src="https://unpkg.com/pell"></script>',
        '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>',
        '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />',
        '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>',
        '<script src="/js/admin-session.js?v=' . $versionParam . '" defer></script>'
    ]);
    $pageDescription = $pageDescription ?? '';
    $pageKeywords = $pageKeywords ?? '';
?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php if ($pageDescription !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
<?php endif; ?>
<?php if ($pageKeywords !== ''): ?>
    <meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>" />
<?php endif; ?>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
    <link rel="manifest" href="/profile/favicon/site.webmanifest">
    <link rel="icon" href="/profile/favicon/favicon.ico">
</head>
<body>
<h1><?= $slug? 'Edit':'New' ?> Post</h1>
<?php if ($slug && !$post['published']): ?>
    <p><a href="/blog/post.php?article=<?= urlencode($slug) ?>&preview=1" target="_blank">View Draft</a></p>
<?php endif; ?>
<form action="save_blog.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="orig_slug" value="<?= htmlspecialchars($slug) ?>">
    <label>Title: <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>"></label>
    <label>Date: <input type="datetime-local" name="date" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($post['date']))) ?>"></label>
    <label>Published: <input type="checkbox" name="published" value="1" <?= $post['published'] ? 'checked' : '' ?>></label>
    <label>Categories:
        <select id="categories" name="categories[]" multiple style="width:300px;">
            <?php foreach ($allCats as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= in_array($cat, $post['categories']) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Image: <input type="file" name="image"></label>
    <?php if ($post['image']): ?>
        <p>Current image:<br><img src="<?= htmlspecialchars(cache_bust($post['image'])) ?>" alt="" style="max-width:200px"></p>
    <?php endif; ?>
    <input type="hidden" name="orig_image" value="<?= htmlspecialchars($post['image']) ?>">
    <input type="hidden" name="existing_srcset_webp" value="<?= htmlspecialchars($post['srcsetWebp']) ?>">
    <input type="hidden" name="existing_srcset_jpg" value="<?= htmlspecialchars($post['srcsetJpg']) ?>">
    <label>Body:</label>
    <textarea name="body" id="body" style="display:none;" rows="10" cols="80"><?= $post['body'] ?></textarea>
    <div id="body-editor" class="pell"></div>
    <button type="submit" formaction="blog_preview.php" formtarget="_blank">Preview</button>
    <button type="submit">Save</button>
</form>
<script>
    window.addEventListener('load', function(){
        const textarea = document.getElementById('body');
        let htmlMode = false;
        const toggleHtml = () => {
            htmlMode = !htmlMode;
            const src = document.getElementById('html-source');
            if (htmlMode) {
                const area = document.createElement('textarea');
                area.id = 'html-source';
                area.style.width = '100%';
                area.style.height = '200px';
                area.value = editor.content.innerHTML;
                editor.content.parentNode.insertBefore(area, editor.content);
                editor.content.style.display = 'none';
            } else if (src) {
                editor.content.innerHTML = src.value;
                src.remove();
                editor.content.style.display = '';
                textarea.value = editor.content.innerHTML;
            }
        };
        const editor = pell.init({
            element: document.getElementById('body-editor'),
            onChange: html => textarea.value = html,
            defaultParagraphSeparator: 'p',
            actions: [
                'bold',
                'italic',
                'underline',
                'strikethrough',
                'heading1',
                'heading2',
                'paragraph',
                'quote',
                'olist',
                'ulist',
                'code',
                'line',
                {
                    name: 'link',
                    icon: '&#128279;',
                    title: 'Link',
                    result: () => {
                        const url = window.prompt('Enter the link URL');
                        if (url) document.execCommand('createLink', false, url);
                    }
                },
                {
                    name: 'html',
                    icon: '&lt;/&gt;',
                    title: 'HTML',
                    result: toggleHtml
                }
            ]
        });
        editor.content.innerHTML = textarea.value;
        $('#categories').select2({ tags: true });
    });
</script>
</body>
</html>
