<h1>MyClip v2</h1>
A full-featured, self-hosted notepad/pastebin built with **PHP 8+** and **MySQL**.

## URL Structure

```
website.com/john                   → John's public profile (all his public notes)
website.com/john/my-php-snippet    → A specific note by John
website.com/raw/INTERNALSLUG       → Raw plain text output
```

Note titles are auto-converted to URL slugs:  
`"My PHP Snippet"` → `my-php-snippet`  
Duplicate slugs per user get `-2`, `-3` appended automatically.

---

## Features

| Feature | Details |
|---|---|
|  User accounts | Self-registration, login, session management |
|  Pretty URLs | `/username` and `/username/note-title` |
|  Profile pages | Public page listing all a user's public notes |
|  Note management | Create, edit, delete — only your own notes |
|  Public / Private | Toggle per-note visibility |
|  Password protection | Per-note optional password (bcrypt) |
|  Syntax highlighting | 18 languages via Highlight.js |
|  Markdown | Rendering + live preview |
|  Revision history | Up to 20 revisions, one-click restore |
|  Dashboard | Stats + paginated note list |
|  QR Code | Instant QR for any note |
|  Download | Download with correct file extension |
|  Raw endpoint | `/raw/SLUG` (plain text) |
|  Tags | Comma-separated, up to 10 per note |
|  Draft autosave | localStorage backup while writing |
|  Dark/Light theme | Persisted per browser |


---

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` enabled

---

## Setup (5 minutes)

### 1. Upload files
Upload the `pastenest/` folder to your server root, e.g. `/var/www/html/pastenest/`

### 2. Import the database
```bash
mysql -u root -p < schema.sql
```

### 3. Edit config
Open `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pastenest');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
define('APP_URL',  'https://yourdomain.com');  // no trailing slash
```

If installed at the domain root, change `RewriteBase /pastenest/` to `RewriteBase /` in `.htaccess`.



---

## File Structure

```
pastenest/
├── index.php           # Landing page
├── profile.php         # Public user profile (/username)
├── schema.sql          # Database setup
├── .htaccess           # URL routing
├── includes/
│   ├── config.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── dashboard/
│   └── index.php
├── note/
│   ├── new.php         # Create note
│   ├── view.php        # /username/note-slug
│   ├── edit.php
│   ├── delete.php
│   └── restore.php
├── raw/
    └── index.php       # /raw/SLUG

```

---

## Nginx Config

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html/pastenest;
    index index.php;

    location /includes/ { deny all; }

    location ~ ^/raw/([a-zA-Z0-9]{4,32})$ {
        try_files $uri /raw/index.php?slug=$1;
    }

    location ~ ^/([a-zA-Z0-9_]{3,40})/([a-zA-Z0-9\-_]{1,255})$ {
        try_files $uri /note/view.php?username=$1&note_slug=$2;
    }

    location ~ ^/([a-zA-Z0-9_]{3,40})$ {
        try_files $uri /profile.php?username=$1;
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## License
Apache
# MyClip
A full-featured, self-hosted notepad/pastebin built with PHP 8+ and MySQL.
