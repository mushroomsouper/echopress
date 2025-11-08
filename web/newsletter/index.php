<?php
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/../includes/page_meta.php';
require_once __DIR__ . '/../includes/newsletter_guard.php';

$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';
$recaptchaSiteKey = newsletter_recaptcha_site_key();
$siteName = echopress_site_name();
$baseUrl = echopress_base_url();

$pageMeta = get_page_meta($pdo, '/newsletter/index.php');
$metaTitle = $pageMeta['title'] ?? 'Newsletter';
$metaDesc  = $pageMeta['description'] ?? ('Sign up for updates from ' . $siteName);
$metaKeywords = $pageMeta['keywords'] ?? '';
$ogTitle = $pageMeta['og_title'] ?? $metaTitle;
$ogDesc  = $pageMeta['og_description'] ?? $metaDesc;
$ogImage = $pageMeta['og_image'] ?? '/images/site-og-image.jpg';
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
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars(rtrim($baseUrl, '/') . '/newsletter/') ?>">
    <meta property="og:image" content="<?= htmlspecialchars(($baseUrl ?: '') . $ogImage) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container newsletter-widget ">
    <h1>Newsletter Signup</h1>
    <form method="post" action="subscribe.php" class="newsletter-form">
        <label>Email:<br>
            <input type="email" name="email" required>
        </label><br>
        <label>Name (optional):<br>
            <input type="text" name="name">
        </label><br>
        <div class="newsletter-honeypot" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="nickname" tabindex="-1" autocomplete="off">
        </div>
        <fieldset>
            <legend>Notify me about:</legend>
            <label><input type="checkbox" name="album"> New Album</label>
            <label><input type="checkbox" name="single"> New EP/Single</label>
            <label><input type="checkbox" name="video"> New Video</label>
            <label><input type="checkbox" name="appears"> New Guest Appearance</label>
            <label><input type="checkbox" name="coming_soon"> Coming Soon</label>
            <label><input type="checkbox" name="all_posts"> All Posts</label>
        </fieldset>
        <?php if ($recaptchaSiteKey !== ''): ?>
        <input type="hidden" name="g-recaptcha-response">
        <?php else: ?>
        <p class="recaptcha-warning">reCAPTCHA is not configured. Please add keys in the environment.</p>
        <?php endif; ?>
        <button type="submit">Subscribe</button>
    </form>
</main>
<?php if ($recaptchaSiteKey !== ''): ?>
<script>
(function() {
  if (!window.__newsletterEnsureRecaptcha) {
    window.__newsletterEnsureRecaptcha = (function () {
      var loaderPromise = null;
      var activeKey = null;

      return function (siteKey) {
        if (!siteKey) {
          return Promise.reject(new Error('Missing reCAPTCHA site key.'));
        }

        if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
          return Promise.resolve(grecaptcha);
        }

        if (loaderPromise) {
          if (activeKey && activeKey !== siteKey) {
            console.warn('reCAPTCHA already initialised with a different site key.');
          }
          return loaderPromise;
        }

        activeKey = siteKey;
        loaderPromise = new Promise(function (resolve, reject) {
          var script = document.createElement('script');
          script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(siteKey);
          script.async = true;
          script.defer = true;
          script.onload = function () {
            if (typeof grecaptcha !== 'undefined') {
              resolve(grecaptcha);
            } else {
              reject(new Error('reCAPTCHA failed to initialise.'));
            }
          };
          script.onerror = function () {
            reject(new Error('Unable to load reCAPTCHA.'));
          };
          document.head.appendChild(script);
        });
        return loaderPromise;
      };
    })();
  }

  var form = document.querySelector('.newsletter-form');
  if (!form) {
    return;
  }

  var tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
  var siteKey = '<?= htmlspecialchars($recaptchaSiteKey) ?>';
  var isSubmitting = false;
  var isPrimed = false;

  form.addEventListener('focusin', function () {
    if (isPrimed) {
      return;
    }
    isPrimed = true;
    window.__newsletterEnsureRecaptcha(siteKey).catch(function (error) {
      console.warn('reCAPTCHA warmup failed', error);
      isPrimed = false;
    });
  });

  form.addEventListener('submit', function (event) {
    if (isSubmitting) {
      return;
    }

    event.preventDefault();

    isSubmitting = true;

    window.__newsletterEnsureRecaptcha(siteKey)
      .then(function () {
        return new Promise(function (resolve, reject) {
          try {
            grecaptcha.ready(function () {
              grecaptcha.execute(siteKey, { action: 'newsletter' })
                .then(resolve)
                .catch(reject);
            });
          } catch (err) {
            reject(err);
          }
        });
      })
      .then(function (token) {
        if (tokenInput) {
          tokenInput.value = token;
        }
        form.submit();
      })
      .catch(function (error) {
        console.error('reCAPTCHA error', error);
        alert('Unable to verify reCAPTCHA. Please try again later.');
        isSubmitting = false;
      });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
