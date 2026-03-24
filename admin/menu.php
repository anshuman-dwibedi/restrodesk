<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole('admin', '/admin/login.php');

$db         = Database::getInstance();
$categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order');
$items      = $db->fetchAll(
    'SELECT m.*, c.name AS category_name
     FROM menu_items m
     JOIN categories c ON c.id = m.category_id
     ORDER BY m.category_id, m.name'
);

$config = devcore_config();
$storageDriver = ucfirst($config['storage']['driver'] ?? 'local');

// ── Helper: render the reusable image picker widget ────────────
function imagePicker(string $prefix): string {
    return <<<HTML
    <div class="img-picker" id="{$prefix}-picker">
        <div class="img-picker-tabs">
            <button type="button" class="img-tab active" id="{$prefix}-tab-upload"><i class="dc-icon dc-icon-arrow-up"></i> Upload File</button>
            <button type="button" class="img-tab"        id="{$prefix}-tab-url"><i class="dc-icon dc-icon-paste"></i> Paste URL</button>
        </div>
        <div id="{$prefix}-pane-upload">
            <div class="upload-drop-zone" id="{$prefix}-drop-zone">
                <span class="drop-icon"><i class="dc-icon dc-icon-image"></i></span>
                <p><strong>Click to browse</strong> or drag &amp; drop</p>
                <p>JPG, PNG, WebP · Max 5 MB</p>
                <input type="file" name="image_file" id="{$prefix}-file-input"
                       accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
        </div>
        <div id="{$prefix}-pane-url" style="display:none;">
            <input type="url" id="{$prefix}-url-input" class="dc-input"
                   placeholder="https://images.example.com/dish.jpg">
        </div>
        <div class="img-preview-wrap">
            <div class="img-preview-empty" id="{$prefix}-empty-icon"><i class="dc-icon dc-icon-utensils"></i></div>
            <img class="img-preview" id="{$prefix}-preview" src="" alt="Preview">
            <button type="button" class="img-clear-btn"
                    onclick="clearPicker('{$prefix}')"><i class="dc-icon dc-icon-x"></i> Clear</button>
        </div>
    </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Items — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../core/ui/devcore.css">
    <link rel="stylesheet" href="../../../core/ui/parts/_icons.css">
    <style>:root{--dc-accent:#e8a838;--dc-accent-2:#f0c060;--dc-accent-glow:rgba(232,168,56,0.2);}</style>
    <style>
        .page-header  { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
        .page-title   { font-size:1.5rem; font-weight:700; }
        .item-img     { width:48px; height:48px; border-radius:8px; object-fit:cover; background:var(--dc-bg-3); }
        .item-img-placeholder { width:48px; height:48px; border-radius:8px; background:var(--dc-bg-3); display:flex; align-items:center; justify-content:center; font-size:1.25rem; }
        .form-row     { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group   { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px; color:var(--dc-text-2); }

        /* Image picker */
        .img-picker         { border:2px dashed var(--dc-border); border-radius:12px; padding:16px; transition:border-color 0.2s; }
        .img-picker:hover   { border-color:var(--dc-accent); }
        .img-picker-tabs    { display:flex; gap:0; margin-bottom:12px; border:1px solid var(--dc-border); border-radius:8px; overflow:hidden; }
        .img-tab            { flex:1; padding:7px 12px; font-size:0.8rem; font-weight:600; background:none; border:none; color:var(--dc-text-3); cursor:pointer; transition:all 0.15s; }
        .img-tab.active     { background:rgba(108,99,255,0.15); color:var(--dc-accent-2); }
        .img-preview-wrap   { display:flex; align-items:center; gap:12px; margin-top:10px; }
        .img-preview        { width:72px; height:72px; border-radius:10px; object-fit:cover; border:1px solid var(--dc-border); background:var(--dc-bg-3); flex-shrink:0; display:none; }
        .img-preview.visible{ display:block; }
        .img-preview-empty  { width:72px; height:72px; border-radius:10px; border:1px dashed var(--dc-border); background:var(--dc-bg-3); display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; }
        .img-clear-btn      { background:none; border:none; color:var(--dc-text-3); cursor:pointer; font-size:0.8rem; padding:4px 8px; border-radius:6px; }
        .img-clear-btn:hover{ background:rgba(255,92,106,0.1); color:var(--dc-danger); }
        .upload-drop-zone   { border-radius:8px; background:var(--dc-bg-3); padding:20px; text-align:center; cursor:pointer; transition:background 0.15s; }
        .upload-drop-zone:hover, .upload-drop-zone.drag-over { background:rgba(108,99,255,0.08); }
        .upload-drop-zone input[type=file] { display:none; }
        .drop-icon          { font-size:1.5rem; display:block; margin-bottom:6px; }
        .upload-drop-zone p { margin:0; font-size:0.8rem; color:var(--dc-text-3); }
        .upload-drop-zone strong { color:var(--dc-accent-2); }

        @media(max-width:600px) { .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<aside class="dc-sidebar">
    <div class="dc-sidebar__logo"><i class="dc-icon dc-icon-utensils"></i> <span>Restrodesk</span></div>
    <div class="dc-sidebar__section">Management</div>
    <a href="dashboard.php"    class="dc-sidebar__link"><i class="dc-icon dc-icon-bar-chart"></i> Dashboard</a>
    <a href="orders.php"       class="dc-sidebar__link"><i class="dc-icon dc-icon-receipt"></i> Orders</a>
    <a href="menu.php"         class="dc-sidebar__link active"><i class="dc-icon dc-icon-clipboard"></i> Menu Items</a>
    <a href="qr-generator.php" class="dc-sidebar__link"><i class="dc-icon dc-icon-qr-code"></i> QR Codes</a>
    <div class="dc-sidebar__section" style="margin-top:auto">Account</div>
    <a href="logout.php"       class="dc-sidebar__link"><i class="dc-icon dc-icon-log-out"></i> Logout</a>
</aside>

<div class="dc-with-sidebar">
    <div class="dc-container dc-section">

        <div class="page-header">
            <div>
                <div class="page-title">Menu Items</div>
                <div class="dc-text-dim" style="font-size:0.875rem;">
                    Upload images directly or paste a URL
                    <span class="dc-badge dc-badge-accent" style="margin-left:6px;">
                        <?= htmlspecialchars($storageDriver) ?> Storage
                    </span>
                </div>
            </div>
            <button class="dc-btn dc-btn-primary" data-modal-open="modalAdd">+ Add Item</button>
        </div>

        <div class="dc-card" style="padding:0;">
            <div class="dc-table-wrap">
                <table class="dc-table" id="menuTable">
                    <thead>
                        <tr>
                            <th style="width:60px;"></th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr id="row-<?= $item['id'] ?>">
                            <td>
                                <?php if ($item['image_url']): ?>
                                    <img class="item-img"
                                         src="<?= htmlspecialchars($item['image_url']) ?>"
                                         alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="item-img-placeholder"><i class="dc-icon dc-icon-utensils"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <div class="dc-text-dim" style="font-size:0.8rem;max-width:260px;">
                                    <?= htmlspecialchars(mb_substr($item['description'] ?? '', 0, 80)) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><strong>$<?= number_format($item['price'], 2) ?></strong></td>
                            <td>
                                <?php if ($item['available']): ?>
                                    <span class="dc-badge dc-badge-success">Available</span>
                                <?php else: ?>
                                    <span class="dc-badge dc-badge-neutral">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dc-flex" style="gap:8px;">
                                    <button class="dc-btn dc-btn-ghost dc-btn-sm"
                                            onclick="openEdit(<?= htmlspecialchars(json_encode($item)) ?>)">
                                        Edit
                                    </button>
                                    <button class="dc-btn dc-btn-danger dc-btn-sm"
                                            onclick="deleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ─── ADD MODAL ──────────────────────────────────────────────── -->
<div class="dc-modal-overlay" id="modalAdd">
    <div class="dc-modal" style="max-width:580px; max-height:90vh; overflow-y:auto;">
        <div class="dc-modal__header">
            <h3 style="font-size:1.1rem;font-weight:700;">Add Menu Item</h3>
            <button class="dc-modal__close" data-modal-close="modalAdd"><i class="dc-icon dc-icon-x"></i></button>
        </div>
        <form id="formAdd" enctype="multipart/form-data" onsubmit="submitAdd(event)">
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" class="dc-input"
                           placeholder="e.g. Grilled Salmon" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" class="dc-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="dc-textarea" rows="2"
                          placeholder="Brief description of the dish"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price ($) *</label>
                    <input type="number" name="price" class="dc-input"
                           step="0.01" min="0" placeholder="12.50" required>
                </div>
                <div class="form-group">
                    <label>Available?</label>
                    <select name="available" class="dc-select">
                        <option value="1">Yes — show on menu</option>
                        <option value="0">No — hide from menu</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Item Image</label>
                <?= imagePicker('add') ?>
            </div>
            <div class="dc-flex" style="gap:12px;margin-top:8px;">
                <button type="submit" id="addSubmitBtn" class="dc-btn dc-btn-primary">Save Item</button>
                <button type="button" class="dc-btn dc-btn-ghost" data-modal-close="modalAdd">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ─── EDIT MODAL ─────────────────────────────────────────────── -->
<div class="dc-modal-overlay" id="modalEdit">
    <div class="dc-modal" style="max-width:580px; max-height:90vh; overflow-y:auto;">
        <div class="dc-modal__header">
            <h3 style="font-size:1.1rem;font-weight:700;">Edit Menu Item</h3>
            <button class="dc-modal__close" data-modal-close="modalEdit"><i class="dc-icon dc-icon-x"></i></button>
        </div>
        <form id="formEdit" enctype="multipart/form-data" onsubmit="submitEdit(event)">
            <input type="hidden" name="id"             id="editId">
            <input type="hidden" name="_method"        value="PUT">
            <input type="hidden" name="existing_image" id="editExistingImage">
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="editName" class="dc-input" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" id="editCategory" class="dc-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editDesc" class="dc-textarea" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Price ($) *</label>
                    <input type="number" name="price" id="editPrice" class="dc-input"
                           step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Available?</label>
                    <select name="available" id="editAvailable" class="dc-select">
                        <option value="1">Yes — show on menu</option>
                        <option value="0">No — hide from menu</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Item Image</label>
                <?= imagePicker('edit') ?>
            </div>
            <div class="dc-flex" style="gap:12px;margin-top:8px;">
                <button type="submit" id="editSubmitBtn" class="dc-btn dc-btn-primary">Update Item</button>
                <button type="button" class="dc-btn dc-btn-ghost" data-modal-close="modalEdit">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="../../../core/ui/devcore.js"></script>
<script>
// ── Image Picker initialisation ────────────────────────────────
function initPicker(prefix) {
    const uploadTab  = document.getElementById(`${prefix}-tab-upload`);
    const urlTab     = document.getElementById(`${prefix}-tab-url`);
    const uploadPane = document.getElementById(`${prefix}-pane-upload`);
    const urlPane    = document.getElementById(`${prefix}-pane-url`);
    const fileInput  = document.getElementById(`${prefix}-file-input`);
    const urlInput   = document.getElementById(`${prefix}-url-input`);
    const preview    = document.getElementById(`${prefix}-preview`);
    const emptyIcon  = document.getElementById(`${prefix}-empty-icon`);
    const dropZone   = document.getElementById(`${prefix}-drop-zone`);

    uploadTab.addEventListener('click', () => {
        uploadTab.classList.add('active');  urlTab.classList.remove('active');
        uploadPane.style.display = '';      urlPane.style.display = 'none';
    });
    urlTab.addEventListener('click', () => {
        urlTab.classList.add('active');     uploadTab.classList.remove('active');
        urlPane.style.display = '';         uploadPane.style.display = 'none';
    });

    // Click drop zone → open file picker
    dropZone.addEventListener('click', () => fileInput.click());

    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            showFilePreview(file, preview, emptyIcon);
        }
    });

    // File input change → show preview
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) showFilePreview(fileInput.files[0], preview, emptyIcon);
    });

    // URL input → show preview
    urlInput.addEventListener('input', () => {
        const url = urlInput.value.trim();
        if (url) {
            preview.src = url;
            preview.classList.add('visible');
            emptyIcon.style.display = 'none';
        } else {
            clearPreview(preview, emptyIcon);
        }
    });
}

function showFilePreview(file, previewEl, emptyEl) {
    const reader = new FileReader();
    reader.onload = (e) => {
        previewEl.src = e.target.result;
        previewEl.classList.add('visible');
        emptyEl.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function clearPreview(previewEl, emptyEl) {
    previewEl.src = '';
    previewEl.classList.remove('visible');
    emptyEl.style.display = '';
}

function clearPicker(prefix) {
    const fileInput = document.getElementById(`${prefix}-file-input`);
    const urlInput  = document.getElementById(`${prefix}-url-input`);
    if (fileInput) fileInput.value = '';
    if (urlInput)  urlInput.value  = '';
    clearPreview(
        document.getElementById(`${prefix}-preview`),
        document.getElementById(`${prefix}-empty-icon`)
    );
}

// Pre-populate picker with an existing URL (for edit modal)
function setPickerUrl(prefix, url) {
    if (!url) { clearPicker(prefix); return; }
    document.getElementById(`${prefix}-tab-url`).click();
    const urlInput  = document.getElementById(`${prefix}-url-input`);
    const preview   = document.getElementById(`${prefix}-preview`);
    const emptyIcon = document.getElementById(`${prefix}-empty-icon`);
    urlInput.value = url;
    preview.src = url;
    preview.classList.add('visible');
    emptyIcon.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    initPicker('add');
    initPicker('edit');
});

// ── Helpers ────────────────────────────────────────────────────
function isUrlTabActive(prefix) {
    return document.getElementById(`${prefix}-pane-url`).style.display !== 'none';
}

function buildFormData(formEl, prefix) {
    // Clone FormData from the form (includes the hidden _method field if present)
    const fd = new FormData(formEl);

    // Decide image source: uploaded file OR pasted URL
    if (isUrlTabActive(prefix)) {
        const url = document.getElementById(`${prefix}-url-input`).value.trim();
        fd.set('image_url', url);
        // Remove the file entry so the server ignores it
        fd.delete('image_file');
    } else {
        // File tab — image_file is already in fd via the <input type="file">
        fd.delete('image_url');
    }
    return fd;
}

// ── submitAdd ──────────────────────────────────────────────────
async function submitAdd(e) {
    e.preventDefault();
    const btn = document.getElementById('addSubmitBtn');
    btn.classList.add('loading');

    try {
        const fd  = buildFormData(e.target, 'add');
        const res = await fetch('../api/menu-admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status !== 'success') throw new Error(data.message);
        Toast.success('Item added! Refreshing...');
        setTimeout(() => location.reload(), 1200);
    } catch (err) {
        Toast.error(err.message);
        btn.classList.remove('loading');
    }
}

// ── submitEdit ─────────────────────────────────────────────────
async function submitEdit(e) {
    e.preventDefault();
    const btn = document.getElementById('editSubmitBtn');
    btn.classList.add('loading');

    try {
        const fd  = buildFormData(e.target, 'edit');
        // _method=PUT hidden field tells the API endpoint to treat as PUT
        const res = await fetch('../api/menu-admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status !== 'success') throw new Error(data.message);
        Toast.success('Item updated! Refreshing...');
        setTimeout(() => location.reload(), 1200);
    } catch (err) {
        Toast.error(err.message);
        btn.classList.remove('loading');
    }
}

// ── openEdit ───────────────────────────────────────────────────
function openEdit(item) {
    document.getElementById('editId').value            = item.id;
    document.getElementById('editName').value          = item.name;
    document.getElementById('editCategory').value      = item.category_id;
    document.getElementById('editDesc').value          = item.description || '';
    document.getElementById('editPrice').value         = item.price;
    document.getElementById('editAvailable').value     = item.available;
    document.getElementById('editExistingImage').value = item.image_url || '';
    setPickerUrl('edit', item.image_url || '');
    Modal.open('modalEdit');
}

// ── deleteItem ─────────────────────────────────────────────────
async function deleteItem(id, name) {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    try {
        await DC.delete(`../api/menu-admin.php?id=${id}`);
        Toast.success('Item deleted');
        document.getElementById('row-' + id)?.remove();
    } catch (err) {
        Toast.error(err.message);
    }
}
</script>
</body>
</html>
