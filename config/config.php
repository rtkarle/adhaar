<?php
/**
 * Adhaar – The SoulServe
 * Central config — reads from environment variables when set (Render/production),
 * falls back to XAMPP local values for development.
 *
 * Production:  set env vars in Render dashboard
 * Development: values below are used automatically
 */

// ── DATABASE ──────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'adhaar_db');

// ── MAIL ──────────────────────────────────────────────────────
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      getenv('MAIL_PORT')       ?  (int)getenv('MAIL_PORT') : 587);
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')   ?: '');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')   ?: '');
define('MAIL_FROM',      getenv('MAIL_FROM')       ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME')  ?: 'Adhaar – The SoulServe');

// ── CLOUDINARY ────────────────────────────────────────────────
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'soulserves');
define('CLOUDINARY_API_KEY',    getenv('CLOUDINARY_API_KEY')    ?: '');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '');
define('CLOUDINARY_UPLOAD_URL', 'https://api.cloudinary.com/v1_1/' . (getenv('CLOUDINARY_CLOUD_NAME') ?: 'soulserves') . '/image/upload');

// ── APP ───────────────────────────────────────────────────────
// APP_URL: set in Render dashboard as your live domain
// e.g. https://adhaar-php.onrender.com
define('APP_URL',  rtrim(getenv('APP_URL') ?: 'http://localhost/adhaar', '/'));
define('APP_NAME', 'Adhaar – The SoulServe');
