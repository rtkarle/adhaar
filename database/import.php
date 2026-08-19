<?php
/**
 * Adhaar – One-time Database Import Tool
 * Run this ONCE after deploy to create all tables on Railway MySQL.
 *
 * URL: https://adhaar-php.onrender.com/database/import.php?key=YOUR_DB_IMPORT_KEY
 *
 * DELETE this file after import is complete!
 */

// The import endpoint is disabled until an explicit deployment secret exists.
$secret_key = (string) getenv('DB_IMPORT_KEY');
if ($secret_key === '' || !hash_equals($secret_key, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    die('<h2 style="color:red">403 Forbidden — invalid key</h2>');
}

require_once __DIR__ . '/../config/db.php';

$files = [
    __DIR__ . '/adhaar_full_schema.sql',
    __DIR__ . '/fix_missing_columns.sql',
];

$results = [];
$total_ok = 0;
$total_err = 0;

foreach ($files as $file) {
    if (!file_exists($file)) {
        $results[] = ['file' => basename($file), 'status' => 'error', 'msg' => 'File not found'];
        continue;
    }

    $sql = file_get_contents($file);

    // Split on semicolons (skip empty statements)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => strlen($s) > 5
    );

    $file_ok = 0; $file_err = 0; $errors = [];
    foreach ($statements as $stmt) {
        if (mysqli_query($conn, $stmt)) {
            $file_ok++;
        } else {
            $err = mysqli_error($conn);
            // Ignore "already exists" errors — safe to re-run
            if (!str_contains($err, 'already exists') && !str_contains($err, "Duplicate")) {
                $errors[] = substr($err, 0, 120);
                $file_err++;
            } else {
                $file_ok++;
            }
        }
    }

    $total_ok  += $file_ok;
    $total_err += $file_err;
    $results[] = [
        'file'   => basename($file),
        'status' => $file_err === 0 ? 'success' : 'partial',
        'ok'     => $file_ok,
        'errors' => $errors,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Adhaar DB Import</title>
<style>
  body{font-family:Inter,Arial,sans-serif;background:#f6f5f0;padding:32px;color:#2f2e26}
  .card{background:#fff;border-radius:16px;padding:24px 28px;max-width:700px;margin:auto;box-shadow:0 8px 24px rgba(0,0,0,.08)}
  h1{font-size:20px;font-weight:800;margin-bottom:20px}
  .ok{color:#065f46;background:#d1fae5;padding:4px 12px;border-radius:20px;font-weight:700;font-size:12px}
  .err{color:#991b1b;background:#fee2e2;padding:4px 12px;border-radius:20px;font-weight:700;font-size:12px}
  .partial{color:#92400e;background:#fef3c7;padding:4px 12px;border-radius:20px;font-weight:700;font-size:12px}
  .file-row{border-bottom:1px solid #f0ede5;padding:12px 0;display:flex;justify-content:space-between;align-items:center}
  .err-list{font-size:12px;color:#991b1b;margin-top:8px}
  .summary{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 18px;margin-top:20px;font-size:14px;font-weight:600;color:#065f46}
  .warn{background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:14px 18px;margin-top:16px;font-size:13px;color:#92400e}
</style>
</head>
<body>
<div class="card">
  <h1>🗄️ Adhaar Database Import</h1>

  <?php foreach ($results as $r): ?>
  <div class="file-row">
    <strong><?= htmlspecialchars($r['file']) ?></strong>
    <span class="<?= $r['status'] ?>">
      <?= $r['status'] === 'success' ? '✅ ' . $r['ok'] . ' statements OK' : ($r['status'] === 'partial' ? '⚠ ' . $r['ok'] . ' OK / ' . count($r['errors']) . ' errors' : '❌ ' . $r['msg']) ?>
    </span>
  </div>
  <?php if (!empty($r['errors'])): ?>
    <div class="err-list">
      <?php foreach (array_slice($r['errors'], 0, 5) as $e): ?>
        <div>→ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php endforeach; ?>

  <div class="summary">
    ✅ Import complete — <?= $total_ok ?> statements executed successfully.
    <?php if ($total_err > 0): ?> ⚠ <?= $total_err ?> non-critical errors (check above). <?php endif; ?>
  </div>

  <div class="warn">
    ⚠️ <strong>Security:</strong> Delete this file after import!<br>
    SSH into your server and run: <code>rm /var/www/html/database/import.php</code><br>
    Or add <code>import.php</code> to <code>.gitignore</code> and redeploy.
  </div>

  <div style="margin-top:20px;font-size:13px;color:#5a594d">
    <strong>Database:</strong> <?= DB_NAME ?> &nbsp;|&nbsp;
    <strong>Host:</strong> <?= DB_HOST ?> &nbsp;|&nbsp;
    <strong>Port:</strong> <?= DB_PORT ?>
  </div>
</div>
</body>
</html>
