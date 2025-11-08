CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    albumTitle VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL DEFAULT 'EchoPress Artist',
    volume INT,
    releaseDate DATE,
    live TINYINT(1) DEFAULT 1,
    comingSoon TINYINT(1) DEFAULT 0,
    themeColor VARCHAR(7) DEFAULT '#333333',
    textColor VARCHAR(7) DEFAULT '#ffffff',
    backgroundColor VARCHAR(7) DEFAULT '#000000',
    background VARCHAR(255),
    backgroundImage VARCHAR(255),
    cover VARCHAR(255),
    back VARCHAR(255),
    font VARCHAR(255),
    genre VARCHAR(100),
    languages VARCHAR(255),
    type VARCHAR(20) DEFAULT 'album',
    IF NOT EXISTS genre VARCHAR(100) AFTER font,
    IF NOT EXISTS languages VARCHAR(255) AFTER genre,
    IF NOT EXISTS type VARCHAR(20) DEFAULT 'album' AFTER languages
);

CREATE TABLE IF NOT EXISTS album_tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    track_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    file VARCHAR(255) NOT NULL,
    length VARCHAR(20),
    artist VARCHAR(255),
    year VARCHAR(10),
    genre VARCHAR(100),
    composer VARCHAR(255),
    comment TEXT,
    lyricist VARCHAR(255),
    explicit TINYINT(1) DEFAULT 0,
    lyrics TEXT,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    IF NOT EXISTS lyricist VARCHAR(255) AFTER comment,
    IF NOT EXISTS explicit TINYINT(1) DEFAULT 0 AFTER lyricist
);

CREATE TABLE IF NOT EXISTS album_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    image VARCHAR(255),
    image_srcset_webp TEXT,
    image_srcset_jpg TEXT,
    published TINYINT(1) DEFAULT 1,
    post_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS blog_post_categories (
    post_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (post_id, category_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS page_meta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255),
    description TEXT,
    keywords TEXT,
    og_title VARCHAR(255),
    og_description TEXT,
    og_image VARCHAR(255),
    og_image_srcset_webp TEXT,
    og_image_srcset_jpg TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL,
    releaseDate DATE,
    url VARCHAR(255),
    comingSoon TINYINT(1) DEFAULT 0,
    released TINYINT(1) DEFAULT 1,
    cover VARCHAR(255),
    cover_srcset_webp TEXT,
    cover_srcset_jpg TEXT,
    appearance_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL DEFAULT 'EchoPress Artist',
    releaseDate DATE,
    url VARCHAR(255) NOT NULL,
    platform VARCHAR(20) DEFAULT 'vimeo',
    thumbnail VARCHAR(255),
    thumb_srcset_webp TEXT,
    thumb_srcset_jpg TEXT,
    video_order INT DEFAULT 0
);

# Newsletter tables
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    wants_album BOOLEAN DEFAULT 0,
    wants_single BOOLEAN DEFAULT 0,
    wants_video BOOLEAN DEFAULT 0,
    wants_appears BOOLEAN DEFAULT 0,
    wants_coming_soon BOOLEAN DEFAULT 0,
    wants_all_posts BOOLEAN DEFAULT 0,
    manage_token VARCHAR(255),
    via ENUM('public','admin') NOT NULL DEFAULT 'public',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    IF NOT EXISTS via ENUM('public','admin') NOT NULL DEFAULT 'public' AFTER manage_token
);

CREATE TABLE IF NOT EXISTS newsletter_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    post_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_newsletter_log_sub_post (subscriber_id, post_id),
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL DEFAULT 'EchoPress Artist',
    description TEXT,
    display_order INT DEFAULT 0,
    live TINYINT(1) DEFAULT 1,
    comingSoon TINYINT(1) DEFAULT 0,
    themeColor VARCHAR(7) DEFAULT '#333333',
    textColor VARCHAR(7) DEFAULT '#ffffff',
    backgroundColor VARCHAR(7) DEFAULT '#000000',
    background VARCHAR(255),
    backgroundImage VARCHAR(255),
    cover VARCHAR(255),
    cover_srcset_webp TEXT,
    cover_srcset_jpg TEXT,
    cover_blur VARCHAR(255),
    font VARCHAR(255),
    genre VARCHAR(100),
    languages VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS playlist_tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playlist_id INT NOT NULL,
    track_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES album_tracks(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_playlist_track (playlist_id, track_id)
);

CREATE TABLE IF NOT EXISTS playlist_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playlist_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS newsletter_unsubscribed (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT,
    email VARCHAR(255),
    name VARCHAR(255),
    via ENUM('public','admin') NOT NULL DEFAULT 'public',
    unsubscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (subscriber_id),
    IF NOT EXISTS via ENUM('public','admin') NOT NULL DEFAULT 'public' AFTER name
);
CREATE TABLE IF NOT EXISTS newsletter_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (ip_address),
    INDEX (attempted_at)
);
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    sent_success BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
