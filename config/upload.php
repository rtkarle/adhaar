<?php
/**
 * Adhaar – Secure File Upload Helper (Cloudinary Edition)
 *
 * Primary:  Uploads to Cloudinary CDN via signed API request using cURL.
 *           Returns the secure HTTPS URL from Cloudinary.
 * Fallback: If cURL/Cloudinary fails, falls back to local uploads/ directory
 *           and returns relative path 'uploads/filename.ext' for DB storage.
 *
 * Magic-byte MIME validation is always performed regardless of destination.
 * User-supplied filenames are NEVER used — only our generated safe names.
 *
 * Include this wherever file uploads are processed.
 */

if (!defined('CLOUDINARY_CLOUD_NAME')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Generate a Cloudinary signed upload signature.
 *
 * @param  array  $params  POST params to sign (excluding api_key, file)
 * @param  string $secret  Cloudinary API secret
 * @return string          SHA-1 hex signature
 */
function cloudinary_sign(array $params, string $secret): string
{
    // Sort alphabetically, build key=value string, append secret
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) {
        $str .= ($str ? '&' : '') . "$k=$v";
    }
    return sha1($str . $secret);
}

/**
 * Upload a file to Cloudinary via signed REST API.
 *
 * @param  string $tmp_path   Absolute path to the validated temp file
 * @param  string $prefix     Folder/public_id prefix (e.g. 'adhaar/food')
 * @return string|null        Cloudinary secure_url, or null on failure
 */
function cloudinary_upload(string $tmp_path, string $prefix): ?string
{
    $timestamp  = time();
    $folder     = 'adhaar/' . $prefix;
    $public_id  = $folder . '/' . $prefix . '_' . $timestamp . '_' . bin2hex(random_bytes(4));

    $sign_params = [
        'public_id' => $public_id,
        'timestamp' => $timestamp,
    ];
    $signature = cloudinary_sign($sign_params, CLOUDINARY_API_SECRET);

    $post_fields = [
        'file'       => new CURLFile($tmp_path),
        'api_key'    => CLOUDINARY_API_KEY,
        'timestamp'  => $timestamp,
        'public_id'  => $public_id,
        'signature'  => $signature,
    ];

    $ch = curl_init(CLOUDINARY_UPLOAD_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_fields,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err || $http_code !== 200) {
        error_log("Cloudinary upload failed [HTTP $http_code]: $curl_err — Response: $response");
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['secure_url'])) {
        error_log("Cloudinary: no secure_url in response: $response");
        return null;
    }

    return $data['secure_url'];  // e.g. https://res.cloudinary.com/soulserves/image/upload/…
}

/**
 * Validate and upload an image — Cloudinary first, local fallback.
 *
 * @param  array  $file       $_FILES['fieldname']
 * @param  string $upload_dir Absolute path to local upload dir (fallback only)
 * @param  string $prefix     Prefix for folder organisation and filenames
 * @param  int    $max_bytes  Max file size in bytes (default 8 MB)
 * @return string|null        Cloudinary HTTPS URL  OR  'uploads/filename.ext'  OR  null
 */
function secure_upload(array $file, string $upload_dir, string $prefix = 'img', int $max_bytes = 8 * 1024 * 1024): ?string
{
    // 1. PHP upload error check
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // 2. File size limit
    if ($file['size'] > $max_bytes) {
        error_log("secure_upload: file too large ({$file['size']} bytes, max $max_bytes)");
        return null;
    }

    // 3. Magic-byte MIME check — cannot be spoofed by renaming
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);
    if (!in_array($real_mime, $allowed_mimes, true)) {
        error_log("secure_upload: rejected MIME '$real_mime'");
        return null;
    }

    // 4. Map MIME → safe extension
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $ext_map[$real_mime];

    // ── Try Cloudinary first ──────────────────────────────────
    if (function_exists('curl_init')) {
        $cloud_url = cloudinary_upload($file['tmp_name'], $prefix);
        if ($cloud_url) {
            return $cloud_url;   // Full HTTPS URL stored directly in DB
        }
        error_log("secure_upload: Cloudinary failed for prefix='$prefix', falling back to local");
    }

    // ── Local fallback ────────────────────────────────────────
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $dest = $upload_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        error_log("secure_upload: move_uploaded_file failed for '$dest'");
        return null;
    }

    return 'uploads/' . $filename;   // Relative path for DB storage
}

/**
 * Resolve a stored image path/URL to a usable <img src> value.
 * Cloudinary URLs start with https:// and are used as-is.
 * Local paths like 'uploads/foo.jpg' are prefixed with APP_URL.
 *
 * @param  string|null $stored   Value from DB column
 * @param  string      $base     App base URL (default APP_URL constant)
 * @return string|null           Full URL for HTML output, or null
 */
function image_url(?string $stored, string $base = ''): ?string
{
    if (!$stored) return null;
    if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
        return $stored;   // Already a Cloudinary / absolute URL
    }
    // Legacy local path — make absolute
    $base = $base ?: (defined('APP_URL') ? APP_URL : '');
    return rtrim($base, '/') . '/' . ltrim($stored, '/');
}
