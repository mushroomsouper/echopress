<?php
declare(strict_types=1);

return [
    'site' => [
        'name' => getenv('ECHOPRESS_SITE_NAME') ?: 'EchoPress Artist',
        'tagline' => getenv('ECHOPRESS_SITE_TAGLINE') ?: 'Independent artist powered by EchoPress',
        'description' => getenv('ECHOPRESS_SITE_DESCRIPTION') ?: 'Official EchoPress landing page for your music, video, and writing.',
        'url' => getenv('ECHOPRESS_SITE_URL') ?: '',
        'timezone' => getenv('ECHOPRESS_TIMEZONE') ?: 'UTC',
        'theme' => [
            'primary' => getenv('ECHOPRESS_THEME_PRIMARY') ?: '#ffffff',
            'accent' => getenv('ECHOPRESS_THEME_ACCENT') ?: '#ff3366',
            'background' => getenv('ECHOPRESS_THEME_BACKGROUND') ?: '#000000',
        ],
    ],

    'artist' => [
        'name' => getenv('ECHOPRESS_ARTIST_NAME') ?: 'EchoPress Artist',
        'bio' => 'Share your story here. This text is replaced during installation.',
        'default_album' => getenv('ECHOPRESS_ARTIST_DEFAULT_ALBUM') ?: '',
        'social' => [
            [
                'label' => 'Instagram',
                'icon' => 'fab fa-instagram',
                'url' => '',
            ],
            [
                'label' => 'YouTube',
                'icon' => 'fab fa-youtube',
                'url' => '',
            ],
        ],
    ],

    'contact' => [
        'email' => getenv('ECHOPRESS_CONTACT_EMAIL') ?: 'hello@example.com',
        'form_recipients' => array_filter([
            getenv('ECHOPRESS_CONTACT_EMAIL') ?: ''
        ]),
        'recaptcha' => [
            'site_key' => getenv('RECAPTCHA_SITE_KEY') ?: getenv('ECHOPRESS_RECAPTCHA_SITE_KEY') ?: '',
            'secret_key' => getenv('RECAPTCHA_SECRET_KEY') ?: getenv('ECHOPRESS_RECAPTCHA_SECRET_KEY') ?: '',
        ],
    ],

    'newsletter' => [
        'from_name' => getenv('ECHOPRESS_NEWSLETTER_FROM_NAME') ?: (getenv('ECHOPRESS_ARTIST_NAME') ?: 'EchoPress Artist'),
        'from_email' => getenv('ECHOPRESS_NEWSLETTER_FROM_EMAIL') ?: 'newsletter@example.com',
        'reply_to' => getenv('ECHOPRESS_NEWSLETTER_REPLY_TO') ?: '',
        'mailer' => [
            'driver' => getenv('ECHOPRESS_MAIL_DRIVER') ?: 'mail',
            'smtp' => [
                'host' => getenv('ECHOPRESS_SMTP_HOST') ?: '',
                'port' => getenv('ECHOPRESS_SMTP_PORT') ?: 587,
                'username' => getenv('ECHOPRESS_SMTP_USERNAME') ?: '',
                'password' => getenv('ECHOPRESS_SMTP_PASSWORD') ?: '',
                'encryption' => getenv('ECHOPRESS_SMTP_ENCRYPTION') ?: 'tls',
                'auth_mode' => getenv('ECHOPRESS_SMTP_AUTH_MODE') ?: '',
            ],
            'webhook' => [
                'url' => getenv('ECHOPRESS_MAIL_WEBHOOK_URL') ?: '',
                'secret' => getenv('ECHOPRESS_MAIL_WEBHOOK_SECRET') ?: '',
                'headers' => getenv('ECHOPRESS_MAIL_WEBHOOK_HEADERS') ?: '',
                'method' => getenv('ECHOPRESS_MAIL_WEBHOOK_METHOD') ?: 'POST',
            ],
        ],
    ],

    'database' => [
        'default' => getenv('ECHOPRESS_DB_CONNECTION') ?: 'sqlite',
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => getenv('ECHOPRESS_SQLITE_PATH') ?: (__DIR__ . '/../storage/database/echopress.sqlite'),
                'enforce_foreign_keys' => true,
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => getenv('ECHOPRESS_MYSQL_HOST') ?: '127.0.0.1',
                'port' => getenv('ECHOPRESS_MYSQL_PORT') ?: 3306,
                'database' => getenv('ECHOPRESS_MYSQL_DATABASE') ?: 'echopress',
                'username' => getenv('ECHOPRESS_MYSQL_USERNAME') ?: 'echopress',
                'password' => getenv('ECHOPRESS_MYSQL_PASSWORD') ?: '',
                'charset' => getenv('ECHOPRESS_MYSQL_CHARSET') ?: 'utf8mb4',
                'collation' => getenv('ECHOPRESS_MYSQL_COLLATION') ?: 'utf8mb4_unicode_ci',
            ],
        ],
    ],

    'analytics' => [
        'embed_code' => getenv('ECHOPRESS_ANALYTICS_EMBED') ?: '',
    ],

    'updates' => [
        'channel' => getenv('ECHOPRESS_UPDATE_CHANNEL') ?: 'stable',
        'endpoint' => getenv('ECHOPRESS_UPDATE_ENDPOINT') ?: '',
        'packages_path' => __DIR__ . '/../updates/packages',
    ],

    'installer' => [
        'lock_file' => __DIR__ . '/../storage/installer.lock',
    ],

    // New: feature flags to hide sections and routes
    'features' => [
        'blog' => (bool) (getenv('ECHOPRESS_FEATURE_BLOG') !== false ? (int) getenv('ECHOPRESS_FEATURE_BLOG') : 1),
        'playlists' => (bool) (getenv('ECHOPRESS_FEATURE_PLAYLISTS') !== false ? (int) getenv('ECHOPRESS_FEATURE_PLAYLISTS') : 1),
        'newsletter' => (bool) (getenv('ECHOPRESS_FEATURE_NEWSLETTER') !== false ? (int) getenv('ECHOPRESS_FEATURE_NEWSLETTER') : 1),
        'videos' => (bool) (getenv('ECHOPRESS_FEATURE_VIDEOS') !== false ? (int) getenv('ECHOPRESS_FEATURE_VIDEOS') : 1),
        'contact' => (bool) (getenv('ECHOPRESS_FEATURE_CONTACT') !== false ? (int) getenv('ECHOPRESS_FEATURE_CONTACT') : 1),
    ],

    // New: basic theme activation and plugin list
    'themes' => [
        // If empty, core templates under web/ are used.
        'active' => getenv('ECHOPRESS_THEME') ?: '',
    ],
    'plugins' => [
        // Example: ['hello-world'] loads web/plugins/hello-world/hello-world.php
        'enabled' => ['hello-world'],
    ],
];
