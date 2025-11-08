<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$bioFile = __DIR__ . '/../profile/bio/bio.json';
$bio = ['text' => '', 'image' => '', 'srcsetWebp' => '', 'srcsetJpg' => ''];
if (file_exists($bioFile)) {
    $bio = json_decode(file_get_contents($bioFile), true) ?: $bio;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle = 'Edit Bio';
$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
$versionParam = htmlspecialchars($version, ENT_QUOTES);
$headExtras = implode("\n", [
    '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">',
    '<link rel="stylesheet" href="https://unpkg.com/pell/dist/pell.min.css">',
    '<script src="https://unpkg.com/pell"></script>',
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
<h1>Edit Bio</h1>
<form action="save_bio.php" method="post" enctype="multipart/form-data">
    <textarea name="bio_text" id="bio_text" style="display:none;" rows="10" cols="60"><?php echo $bio['text']; ?></textarea>
    <div id="bio-editor" class="pell"></div>
    <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($bio['image']); ?>">
    <input type="hidden" name="existing_srcset_webp" value="<?php echo htmlspecialchars($bio['srcsetWebp']); ?>">
    <input type="hidden" name="existing_srcset_jpg" value="<?php echo htmlspecialchars($bio['srcsetJpg']); ?>">
    <label>Photo: <input type="file" name="bio_image"></label>
    <?php if (!empty($bio['image'])): ?>
        <img src="<?php echo htmlspecialchars(cache_bust('/'.$bio['image'])); ?>" alt="bio" height="80">
    <?php endif; ?>
    <br>
    <button type="submit">Save</button>
</form>
<p><a href="index.php">Back</a></p>
<script>
    window.addEventListener('load', function(){
        const textarea = document.getElementById('bio_text');
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
            element: document.getElementById('bio-editor'),
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
    });
</script>
</body>
</html>
