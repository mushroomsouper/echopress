<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/admin/db_connect.php';

$versionFile = __DIR__ . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

// ─── Fetch credentials ─────────────────────────────────────────────────
$siteKey         = echopress_config('contact.recaptcha.site_key', '');
$recaptchaSecret = echopress_config('contact.recaptcha.secret_key', '');
$siteName = echopress_site_name();
$contactEmail = echopress_support_email();
$recipients = array_filter((array) echopress_config('contact.form_recipients', [$contactEmail]));
if (!$recipients && $contactEmail) {
    $recipients = [$contactEmail];
}

// ─── Form handling ─────────────────────────────────────────────────────
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $message = trim($_POST['message'] ?? '');
    $token   = $_POST['recaptcha_token'] ?? '';

    if ($name && $email && $message && $token) {
        // Verify v3 token
        $verifyResp = file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify?' . 
            http_build_query([
                'secret'   => $recaptchaSecret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ])
        );
        $resp = json_decode($verifyResp, true);
        error_log('reCAPTCHA v3 response: ' . json_encode($resp));

        // check success, action match, and score threshold
        $threshold = 0.5;
        if (
            !empty($resp['success']) &&
            !empty($resp['action']) && $resp['action'] === 'contact' &&
            isset($resp['score']) && $resp['score'] >= $threshold
        ) {
            // Log the message first
            $logStmt = $pdo->prepare('
                INSERT INTO contact_messages
                    (name,email,message,ip_address,user_agent)
                VALUES (?,?,?,?,?)
            ');
            $logStmt->execute([
                $name,
                $email,
                $message,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            $logId = $pdo->lastInsertId();

            // Send mail
            $body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}";
            $subject = 'New contact form submission for ' . $siteName;

            $mailResult = echopress_mail([
                'to' => array_map(function ($addr) use ($siteName) {
                    return ['email' => $addr, 'name' => $siteName . ' Team'];
                }, $recipients),
                'subject' => $subject,
                'text' => $body,
                'html' => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
                'reply_to' => ['email' => $email, 'name' => $name],
            ]);

            if ($mailResult) {
                $success = true;
                $pdo->prepare('UPDATE contact_messages SET sent_success=1 WHERE id=?')
                    ->execute([$logId]);
            } else {
                $error = 'Failed to send email.';
            }
        } else {
            $error = 'Captcha verification failed.';
        }
    } else {
        $error = 'All fields are required.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Connect with <?= htmlspecialchars($siteName) ?>."/>

  <title>Contact</title>  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

  <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
  <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($siteKey); ?>" async defer></script>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

  <main class="container">
    <h1>Contact</h1>


    <p>Use this form for booking, collaborations, licensing, or to share thoughtful feedback. Messages go directly to the EchoPress admin inbox for your site.</p>
<p>Please include timelines, context, and any helpful links so we can respond quickly.</p>


    <?php if ($success): ?>
      <p>Thank you for your message!</p>
    <?php else: ?>
      <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>



      <form method="post" novalidate id="contact-form">
        <p class="error" id="form-error-summary" role="alert" aria-live="polite" hidden></p>

        <div class="form-field">
          <label for="contact-name">Name</label>
          <input type="text" id="contact-name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          <p class="error field-error" data-error-for="name" aria-live="polite"></p>
        </div>

        <div class="form-field">
          <label for="contact-email">Email</label>
          <input type="email" id="contact-email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          <p class="error field-error" data-error-for="email" aria-live="polite"></p>
        </div>

        <div class="form-field">
          <label for="contact-message">Message</label>
          <textarea id="contact-message" name="message" required rows="5"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
          <p class="error field-error" data-error-for="message" aria-live="polite"></p>
        </div>

        <!-- v3 token placeholder -->
        <input type="hidden" name="recaptcha_token" id="recaptcha_token">

        <button type="submit">Send</button>
      </form>
    <?php endif; ?>
  </main>

  <script>
    (function () {
      var form = document.getElementById('contact-form');
      if (!form) {
        return;
      }

      var summary = document.getElementById('form-error-summary');
      var fields = ['name', 'email', 'message'];
      var validators = {
        name: function (value) {
          return value ? '' : 'Name is required.';
        },
        email: function (value) {
          if (!value) {
            return 'Email is required.';
          }
          var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          return emailPattern.test(value) ? '' : 'Enter a valid email address.';
        },
        message: function (value) {
          return value ? '' : 'Message is required.';
        }
      };

      function setFieldError(field, message) {
        var errorEl = form.querySelector('[data-error-for="' + field + '"]');
        var inputEl = form.elements[field];
        if (!errorEl || !inputEl) {
          return;
        }
        if (message) {
          errorEl.textContent = message;
          if (inputEl.classList) {
            inputEl.classList.add('field-invalid');
          }
        } else {
          errorEl.textContent = '';
          if (inputEl.classList) {
            inputEl.classList.remove('field-invalid');
          }
        }
      }

      function hasVisibleFieldErrors() {
        var errors = form.querySelectorAll('.field-error');
        for (var i = 0; i < errors.length; i += 1) {
          if (errors[i].textContent.trim() !== '') {
            return true;
          }
        }
        return false;
      }

      function showSummaryMessage(message, type) {
        if (!summary) {
          return;
        }
        summary.textContent = message;
        summary.hidden = !message;
        if (message) {
          summary.setAttribute('data-type', type || '');
        } else {
          summary.removeAttribute('data-type');
        }
      }

      form.addEventListener('submit', function (evt) {
        evt.preventDefault();

        var hasErrors = false;
        var firstInvalid = null;

        for (var i = 0; i < fields.length; i += 1) {
          var fieldName = fields[i];
          var inputEl = form.elements[fieldName];
          if (!inputEl) {
            continue;
          }
          var trimmedValue = inputEl.value.trim();
          var message = validators[fieldName](trimmedValue);
          if (message) {
            hasErrors = true;
            setFieldError(fieldName, message);
            if (!firstInvalid) {
              firstInvalid = inputEl;
            }
          } else {
            setFieldError(fieldName, '');
            inputEl.value = trimmedValue;
          }
        }

        if (hasErrors) {
          showSummaryMessage('Please fill in the required fields before sending your message.', 'validation');
          if (firstInvalid) {
            firstInvalid.focus();
          }
          return;
        }

        showSummaryMessage('', '');

        if (window._paq) {
          window._paq.push(['trackEvent', 'Contact', 'submit']);
        }

        if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
          showSummaryMessage('Unable to submit the form right now. Please refresh the page and try again.', 'captcha');
          return;
        }

        grecaptcha.ready(function () {
          grecaptcha.execute('<?php echo htmlspecialchars($siteKey); ?>', { action: 'contact' })
            .then(function (token) {
              var tokenInput = document.getElementById('recaptcha_token');
              if (tokenInput) {
                tokenInput.value = token;
              }
              form.submit();
            })
            .catch(function (error) {
              console.error('reCAPTCHA error', error);
              showSummaryMessage('Unable to submit the form right now. Please try again later.', 'captcha');
            });
        });
      });

      form.addEventListener('input', function (evt) {
        var target = evt.target || evt.srcElement;
        var fieldName = target && target.name;
        if (!fieldName || !validators[fieldName]) {
          return;
        }
        var message = validators[fieldName](target.value.trim());
        setFieldError(fieldName, message);

        if (summary && summary.getAttribute('data-type') === 'validation' && !hasVisibleFieldErrors()) {
          showSummaryMessage('', '');
        }
      });
    })();
  </script>
</body>
</html>
