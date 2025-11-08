<?php if (!defined('PLX_ROOT'))
	exit; ?>


<?php
require_once dirname(__DIR__, 3) . '/includes/app.php';
$versionFile = $_SERVER['DOCUMENT_ROOT'] . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';
$siteName = echopress_site_name();
$baseUrl = echopress_base_url();
if ($baseUrl === '') {
	$baseUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
$blogBase = rtrim($baseUrl, '/') . '/blog/';
$defaultOgImage = $blogBase . 'themes/defaut/img/profilephoto.jpg';
?>


<!DOCTYPE html>
<html lang="<?php $plxShow->defaultLang() ?>">

<head>
	<meta charset="<?php $plxShow->charset('min'); ?>">
	<meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1.0">
	<title><?php $plxShow->pageTitle(); ?></title>
	<?php
	$plxShow->meta('description');
	$plxShow->meta('keywords');
	$plxShow->meta('author');
	?>
	<link rel="icon" href="<?php $plxShow->template(); ?>/img/favicon.png" />
	<link rel="stylesheet" href="<?php $plxShow->template(); ?>/css/plucss.min.css?v=<?php echo $version; ?>"
		media="screen,print" />
	<link rel="stylesheet" href="<?php $plxShow->template(); ?>/css/theme.min.css?v=<?php echo PLX_VERSION ?>"
		media="screen" />


	<link rel="stylesheet" href="/css/blog.css?v=<?php echo $version; ?>" />


	<?php
	// OG Tags for article page
	if ($plxShow->mode() == 'article'): ?>
		<meta property="og:type" content="article" />
		<meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>" />
		<meta property="og:title" content="<?php $plxShow->artTitle(); ?>" />
		<meta property="og:description"
			content="<?php echo strip_tags($plxShow->plxMotor->plxRecord_arts->f('chapo')); ?>" />

		<meta property="og:url" content="<?php $plxShow->artUrl(); ?>" />
		<?php
		// Detect PluXml’s current mode:
		$mode = $plxShow->plxMotor->mode;

		// 1) If we’re on the blog’s home page, always emit the default image:
		if ($mode === 'home') {
			echo '<meta property="og:image" content="' . htmlspecialchars($defaultOgImage) . '" />';
		}
		// 2) Otherwise (i.e. in article view), only emit og:image when a thumbnail is set:
		else {
			$thumb = $plxShow->plxMotor->plxRecord_arts->f('thumbnail');
			if (!empty($thumb)) {
				// Make sure the thumbnail URL is absolute:
				echo '<meta property="og:image" content="' . htmlspecialchars($blogBase . ltrim($thumb, '/')) . '" />';
			}
			// (If no thumbnail, emit nothing at all.)
		}
		?>
	<?php else: ?>
		<!-- Static OG Tags for blog home or other pages -->
		<meta property="og:type" content="website" />
		<meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>" />
		<meta property="og:title" content="<?= htmlspecialchars($siteName . ' - Blog') ?>" />
		<meta property="og:description"
			content="<?= htmlspecialchars('Updates, thoughts, and works-in-progress from ' . $siteName . '.') ?>" />
		<meta property="og:url" content="<?= htmlspecialchars(rtrim($baseUrl, '/') . '/blog') ?>" />
		<meta property="og:image" content="<?= htmlspecialchars($defaultOgImage) ?>" />
	<?php endif; ?>

<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
 
  crossorigin="anonymous"
  referrerpolicy="no-referrer"
/>

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Share+Tech+Mono&display=swap"
		rel="stylesheet">

	<?php
	$plxShow->templateCss();
	$plxShow->pluginsCss();
	?>
        <link rel="alternate" type="application/rss+xml" title="<?php $plxShow->lang('ARTICLES_RSS_FEEDS') ?>"
                href="<?php $plxShow->urlPostsRssFeed($plxShow->plxMotor->mode) ?>" />
        <link rel="alternate" type="application/rss+xml" title="<?php $plxShow->lang('COMMENTS_RSS_FEEDS') ?>"
                href="<?php $plxShow->urlRewrite('feed.php?rss/commentaires') ?>" />
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>

<body id="top" class="page mode-<?php $plxShow->mode(true) ?>">

	<header class="header">
<button id="dark-toggle" aria-label="Toggle dark mode">
  <!-- Start with outline bulb for “light mode” -->
  <i id="toggle-icon" class="far fa-lightbulb"></i>
</button>
		<div class="container">
			<div="grid">


				<div class="logo">
					<h1><?php $plxShow->mainTitle('link'); ?></h1>
					<nav class="breadcrumbs">
						<a href="/">Home</a> &gt;
						<a href="/blog/">Blog</a>
						<?php if ($plxShow->mode() == 'article'): ?>
							&gt; <?php $plxShow->artTitle(); ?>
						<?php endif; ?>
					</nav>
				</div>

		</div>


		<!-- <div class="col sml-6 med-7 lrg-8">

					<nav class="nav">

						<div class="responsive-menu">
							<label for="menu"></label>
							<input type="checkbox" id="menu">
							<ul class="menu">
								</?php $plxShow->staticList($plxShow->getLang('HOME'), '<li class="#static_class #static_status" id="#static_id"><a href="#static_url" title="#static_name">#static_name</a></li>'); ?>
								<?php $plxShow->pageBlog('<li class="#page_class #page_status" id="#page_id"><a href="#page_url" title="#page_name">#page_name</a></li>'); ?>
							</ul>
						</div>

					</nav>

				</div> -->



	</header>
