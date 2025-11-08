CREATE TABLE IF NOT EXISTS albums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    albumTitle TEXT NOT NULL,
    artist TEXT NOT NULL DEFAULT 'EchoPress Artist',
    volume INTEGER,
    releaseDate TEXT,
    live INTEGER DEFAULT 1,
    comingSoon INTEGER DEFAULT 0,
    themeColor TEXT DEFAULT '#333333',
    textColor TEXT DEFAULT '#ffffff',
    backgroundColor TEXT DEFAULT '#000000',
    background TEXT,
    backgroundImage TEXT,
    cover TEXT,
    back TEXT,
    font TEXT,
    genre TEXT,
    languages TEXT,
    type TEXT DEFAULT 'album'
);

CREATE TABLE IF NOT EXISTS album_tracks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    album_id INTEGER NOT NULL,
    track_number INTEGER NOT NULL,
    title TEXT NOT NULL,
    file TEXT NOT NULL,
    length TEXT,
    artist TEXT,
    year TEXT,
    genre TEXT,
    composer TEXT,
    comment TEXT,
    lyricist TEXT,
    explicit INTEGER DEFAULT 0,
    lyrics TEXT,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS album_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    album_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS blog_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    image TEXT,
    image_srcset_webp TEXT,
    image_srcset_jpg TEXT,
    published INTEGER DEFAULT 1,
    post_date TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS blog_post_categories (
    post_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, category_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS page_meta (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page TEXT NOT NULL UNIQUE,
    title TEXT,
    description TEXT,
    keywords TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    og_image_srcset_webp TEXT,
    og_image_srcset_jpg TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appearances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    artist TEXT NOT NULL,
    releaseDate TEXT,
    url TEXT,
    comingSoon INTEGER DEFAULT 0,
    released INTEGER DEFAULT 1,
    cover TEXT,
    cover_srcset_webp TEXT,
    cover_srcset_jpg TEXT,
    appearance_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    artist TEXT NOT NULL DEFAULT 'EchoPress Artist',
    releaseDate TEXT,
    url TEXT NOT NULL,
    platform TEXT DEFAULT 'vimeo',
    thumbnail TEXT,
    thumb_srcset_webp TEXT,
    thumb_srcset_jpg TEXT,
    video_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    name TEXT,
    wants_album INTEGER DEFAULT 0,
    wants_single INTEGER DEFAULT 0,
    wants_video INTEGER DEFAULT 0,
    wants_appears INTEGER DEFAULT 0,
    wants_coming_soon INTEGER DEFAULT 0,
    wants_all_posts INTEGER DEFAULT 0,
    manage_token TEXT,
    via TEXT DEFAULT 'public',
    subscribed_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS newsletter_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subscriber_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    sent_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subscriber_id, post_id),
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS playlists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    artist TEXT NOT NULL DEFAULT 'EchoPress Artist',
    description TEXT,
    display_order INTEGER DEFAULT 0,
    live INTEGER DEFAULT 1,
    comingSoon INTEGER DEFAULT 0,
    themeColor TEXT DEFAULT '#333333',
    textColor TEXT DEFAULT '#ffffff',
    backgroundColor TEXT DEFAULT '#000000',
    background TEXT,
    backgroundImage TEXT,
    cover TEXT,
    cover_srcset_webp TEXT,
    cover_srcset_jpg TEXT,
    cover_blur TEXT,
    font TEXT,
    genre TEXT,
    languages TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS playlist_tracks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    playlist_id INTEGER NOT NULL,
    track_id INTEGER,
    position INTEGER DEFAULT 0,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES album_tracks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS playlist_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    playlist_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS newsletter_unsubscribed (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subscriber_id INTEGER,
    unsubscribed_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS newsletter_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    email TEXT NOT NULL,
    attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    message TEXT,
    ip_address TEXT,
    user_agent TEXT,
    sent_success INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
