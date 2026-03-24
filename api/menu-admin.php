<?php
/**
 * /api/menu-admin.php — Admin CRUD for menu items
 *
 * POST   $_POST + $_FILES  — create item  (multipart/form-data)
 * POST   $_POST['_method'] = 'PUT'        — update item (method override, multipart)
 * DELETE ?id=X                            — delete item (JSON or query-string)
 *
 * Image handling (priority order):
 *   1. $_FILES['image_file'] present → upload via Storage::uploadFile(), store returned URL
 *   2. $_POST['image_url'] non-empty  → store the pasted URL as-is
 *   3. Neither provided on UPDATE     → keep existing image (from existing_image field)
 *   4. Neither provided on INSERT     → null (no image)
 *
 * On DELETE: if the stored image_url was uploaded via LocalStorage (matches our base_url
 * prefix), delete the file from storage too. External/Unsplash URLs are left untouched.
 *
 * All require admin session.
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole('admin', '/restaurant-qr-ordering/admin/login.php');

$db     = Database::getInstance();

// ── Method override: POST with _method=PUT acts as PUT ─────────
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'PUT') {
    $method = 'PUT';
}

// ── POST: Create menu item ──────────────────────────────────────
if ($method === 'POST') {
    $v = Validator::make($_POST, [
        'name'        => 'required|max:120',
        'category_id' => 'required|numeric',
        'price'       => 'required|numeric',
    ]);
    if ($v->fails()) {
        Api::error('Validation failed', 422, $v->errors());
    }

    $imageUrl = resolveImage(null);

    $id = $db->insert('menu_items', [
        'category_id' => (int)$_POST['category_id'],
        'name'        => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'price'       => round((float)$_POST['price'], 2),
        'image_url'   => $imageUrl,
        'available'   => (int)($_POST['available'] ?? 1),
    ]);

    Api::success(['id' => (int)$id, 'image_url' => $imageUrl], 'Item created', 201);
}

// ── PUT: Update menu item ───────────────────────────────────────
if ($method === 'PUT') {
    $v = Validator::make($_POST, [
        'id'          => 'required|numeric',
        'name'        => 'required|max:120',
        'category_id' => 'required|numeric',
        'price'       => 'required|numeric',
    ]);
    if ($v->fails()) {
        Api::error('Validation failed', 422, $v->errors());
    }

    $itemId   = (int)$_POST['id'];
    $existing = $db->fetchOne('SELECT image_url FROM menu_items WHERE id = ?', [$itemId]);
    if (!$existing) {
        Api::error('Item not found', 404);
    }

    $imageUrl = resolveImage($existing['image_url']);

    // If the image changed and the old one was a locally-uploaded file, delete it
    if ($existing['image_url'] && $imageUrl !== $existing['image_url']) {
        tryDeleteStoredImage($existing['image_url']);
    }

    $updated = $db->update('menu_items', [
        'category_id' => (int)$_POST['category_id'],
        'name'        => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'price'       => round((float)$_POST['price'], 2),
        'image_url'   => $imageUrl,
        'available'   => (int)($_POST['available'] ?? 1),
    ], 'id = ?', [$itemId]);

    if (!$updated) {
        Api::error('No changes saved', 200);
    }

    Api::success(['id' => $itemId, 'image_url' => $imageUrl], 'Item updated');
}

// ── DELETE: Remove menu item ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        Api::error('Missing id', 422);
    }

    $item = $db->fetchOne('SELECT image_url FROM menu_items WHERE id = ?', [$id]);
    if (!$item) {
        Api::error('Item not found', 404);
    }

    // Delete stored image file if it was uploaded via Storage
    if ($item['image_url']) {
        tryDeleteStoredImage($item['image_url']);
    }

    $db->delete('menu_items', 'id = ?', [$id]);
    Api::success(null, 'Item deleted');
}

Api::error('Method not allowed', 405);


// ── Helpers ─────────────────────────────────────────────────────

/**
 * Determine the final image URL to store:
 *
 * 1. A real file was uploaded → upload via Storage, return new URL
 * 2. A URL was pasted → return that URL
 * 3. Neither → fall back to $existingUrl (keep current) or null
 */
function resolveImage(?string $existingUrl): ?string
{
    // 1. File upload present?
    $file = $_FILES['image_file'] ?? null;
    if ($file && $file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        try {
            return Storage::uploadFile(
                $file,
                'menu',
                ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                5 * 1024 * 1024
            );
        } catch (RuntimeException $e) {
            // Surface upload errors to the caller
            Api::error('Image upload failed: ' . $e->getMessage(), 422);
        }
    }

    // 2. URL pasted?
    $url = trim($_POST['image_url'] ?? '');
    if ($url !== '') {
        return $url;
    }

    // 3. Fallback: keep existing (PUT) or null (POST with no image)
    // On PUT, the hidden field existing_image carries the current value —
    // but we trust $existingUrl fetched directly from DB instead.
    return $existingUrl;
}

/**
 * Attempt to delete a stored image via Storage::delete().
 * Only deletes files that were uploaded through our Storage layer
 * (identified by matching the configured local base_url or cloud bucket URL).
 * External URLs (e.g. Unsplash) are silently skipped.
 */
function tryDeleteStoredImage(string $imageUrl): void
{
    try {
        $config  = devcore_config();
        $driver  = $config['storage']['driver'] ?? 'local';
        $baseUrl = match ($driver) {
            'local' => rtrim($config['storage']['local']['base_url'] ?? '', '/'),
            's3'    => rtrim($config['storage']['s3']['base_url']    ?? '', '/'),
            'r2'    => rtrim($config['storage']['r2']['base_url']    ?? '', '/'),
            default => '',
        };

        // Only delete if URL belongs to our own storage
        if ($baseUrl && str_starts_with($imageUrl, $baseUrl)) {
            Storage::delete($imageUrl);
        }
    } catch (Throwable) {
        // Non-fatal — log in production, ignore here
    }
}
