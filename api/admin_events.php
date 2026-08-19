<?php
/**
 * Adhaar – Admin Events & News CRUD API
 * Actions: create | edit | delete | list
 * Admin-only. CSRF protected. Image upload supported.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin guard
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// ── LIST (GET) ────────────────────────────────────────────────
if ($action === 'list') {
    $rows = $conn->query(
        "SELECT id, title, content, category, emoji, image, is_published, event_date, created_by, created_at
         FROM events_news ORDER BY created_at DESC LIMIT 100"
    )->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $rows]);
    exit;
}

// All write actions require CSRF
csrf_verify();

$admin_email = $_SESSION['admin_email'] ?? 'admin';

// ── CREATE ────────────────────────────────────────────────────
if ($action === 'create') {
    $title      = trim($_POST['title']      ?? '');
    $content    = trim($_POST['content']    ?? '');
    $category   = in_array($_POST['category'] ?? '', ['event','news','drive','milestone'])
                    ? $_POST['category'] : 'news';
    $emoji      = mb_substr(trim($_POST['emoji'] ?? '📰'), 0, 10);
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $published  = isset($_POST['is_published']) ? 1 : 0;

    if (!$title || !$content) {
        header('Location: ../admin/admin_dashboard.php?tab=events&msg=missing_fields');
        exit;
    }

    // Image upload
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/';
        $image = secure_upload($_FILES['image'], $upload_dir, 'event');
    }

    $stmt = $conn->prepare(
        "INSERT INTO events_news (title, content, category, emoji, image, is_published, event_date, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssiis',
        $title, $content, $category, $emoji, $image, $published, $event_date, $admin_email
    );
    $stmt->execute();

    header('Location: ../admin/admin_dashboard.php?tab=events&msg=created');
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────
if ($action === 'edit') {
    $id         = (int)($_POST['id'] ?? 0);
    $title      = trim($_POST['title']   ?? '');
    $content    = trim($_POST['content'] ?? '');
    $category   = in_array($_POST['category'] ?? '', ['event','news','drive','milestone'])
                    ? $_POST['category'] : 'news';
    $emoji      = mb_substr(trim($_POST['emoji'] ?? '📰'), 0, 10);
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $published  = isset($_POST['is_published']) ? 1 : 0;

    if (!$id || !$title || !$content) {
        header('Location: ../admin/admin_dashboard.php?tab=events&msg=missing_fields');
        exit;
    }

    // Check new image upload
    $image_sql = '';
    $new_image = null;
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/';
        $new_image  = secure_upload($_FILES['image'], $upload_dir, 'event');
        if ($new_image) {
            $image_sql = ", image = '$new_image'";
        }
    }

    $stmt = $conn->prepare(
        "UPDATE events_news
         SET title=?, content=?, category=?, emoji=?, is_published=?, event_date=?
         $image_sql
         WHERE id=?"
    );
    $stmt->bind_param('ssssisi',
        $title, $content, $category, $emoji, $published, $event_date, $id
    );
    $stmt->execute();

    header('Location: ../admin/admin_dashboard.php?tab=events&msg=updated');
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        // Only delete local fallback files — Cloudinary URLs are managed on the CDN
        $row = $conn->query("SELECT image FROM events_news WHERE id=$id")->fetch_assoc();
        if ($row && $row['image'] && !str_starts_with($row['image'], 'http')) {
            $file_path = __DIR__ . '/../' . $row['image'];
            if (file_exists($file_path)) @unlink($file_path);
        }
        $conn->query("DELETE FROM events_news WHERE id=$id");
    }
    header('Location: ../admin/admin_dashboard.php?tab=events&msg=deleted');
    exit;
}

// ── TOGGLE PUBLISH ────────────────────────────────────────────
if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $conn->query("UPDATE events_news SET is_published = 1 - is_published WHERE id=$id");
    }
    header('Location: ../admin/admin_dashboard.php?tab=events&msg=toggled');
    exit;
}

// Fallback
header('Location: ../admin/admin_dashboard.php?tab=events');
exit;
