<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/newsletter_guard.php';

$widgetVersion = $version ?? null;
if ($widgetVersion === null || $widgetVersion === '') {
    $versionFile = ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) . '/version.txt';
    $widgetVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
}
$recaptchaSiteKey = newsletter_recaptcha_site_key();
?>
<section class="newsletter-widget"<?php if ($recaptchaSiteKey !== ''): ?> data-recaptcha-key="<?= htmlspecialchars($recaptchaSiteKey) ?>"<?php endif; ?>>
    <h2>Join the Newsletter</h2>
    <form method="post" action="/newsletter/subscribe.php" class="newsletter-form">
        <label>
            <input type="email" name="email" placeholder="your@email.com" required>
        </label>
        <div class="newsletter-honeypot" aria-hidden="true">
            <label for="newsletter-website">Website</label>
            <input type="text" id="newsletter-website" name="nickname" tabindex="-1" autocomplete="off">
        </div>
        <button type="button" class="pref-next">Subscribe</button>
        <div class="pref-popover" hidden>
            <div class="pref-popover-inner">
            <fieldset>
                <legend>Notify me about:</legend>
                <label><input type="checkbox" name="album" checked> New Album</label>
                <label><input type="checkbox" name="single" checked> New EP/Single</label>
                <label><input type="checkbox" name="video" checked> New Video</label>
                <label><input type="checkbox" name="appears" checked> New Guest Appearance</label>
                <label><input type="checkbox" name="coming_soon" checked> Coming Soon</label>
                <label><input type="checkbox" name="all_posts" checked> All Posts</label>
            </fieldset>
            <?php if ($recaptchaSiteKey !== ''): ?>
            <input type="hidden" name="g-recaptcha-response">
            <?php else: ?>
            <p class="recaptcha-warning">reCAPTCHA keys missing. Add them to enable the form.</p>
            <?php endif; ?>
            <button type="submit">Confirm</button></div>
        </div>
    </form>
    <script src="/js/newsletter-widget.js?v=<?= htmlspecialchars($widgetVersion) ?>"></script>
</section>
