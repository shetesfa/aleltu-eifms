<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'hr_officer']);

$empId = (int) ($_GET['id'] ?? 0);
$emp = $empId > 0 ? db()->query("SELECT id, full_name, employee_number, photo_path FROM employees WHERE id = {$empId}")->fetch() : null;
if (!$emp) {
    $firstId = db()->query("SELECT id FROM employees WHERE status != 'terminated' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $empId = (int)$firstId;
        $emp = db()->query("SELECT id, full_name, employee_number, photo_path FROM employees WHERE id = {$empId}")->fetch();
    } else {
        flash('No employee records found.', 'error');
        header('Location: ' . BASE_URL . '/modules/employees/list.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $saved = false;
        // 1. Base64 cropped image from canvas
        if (!empty($_POST['cropped_image']) && str_starts_with($_POST['cropped_image'], 'data:image/')) {
            employee_save_photo($empId, $_POST['cropped_image']);
            $saved = true;
        }
        // 2. Direct file upload fallback
        elseif (!empty($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            employee_save_photo_file($empId, $_FILES['photo_file']);
            $saved = true;
        }

        if ($saved) {
            flash(t('idc.photo_updated', 'Photo updated successfully.'));
            header("Location: " . BASE_URL . "/modules/employees/id_card.php?id={$empId}");
            exit;
        } else {
            flash('Please choose an image file to upload.', 'error');
        }
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px"><?= h(t('idc.change_photo_title', 'Change Photo')) ?> — <?= h($emp['full_name']) ?></h2>
        <span class="text-muted"><?= h($emp['employee_number']) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/modules/employees/id_card.php?id=<?= (int) $empId ?>" class="btn secondary">← <?= h(t('idc.back_to_id', 'Back to ID Card')) ?></a>
</div>

<div class="card" style="max-width:520px;margin:0 auto;box-shadow:0 8px 24px rgba(0,0,0,0.06);border-radius:12px;">
    <!-- Current Photo Display -->
    <div style="text-align:center;padding:12px 0 20px;border-bottom:1px solid var(--border);margin-bottom:16px;">
        <span class="text-muted" style="display:block;font-size:13px;margin-bottom:8px">Current Photo</span>
        <?php if (employee_photo_exists($emp['photo_path'] ?? null)): ?>
            <img src="<?= h($emp['photo_path']) ?>?v=<?= time() ?>" alt="Current Photo" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);box-shadow:0 4px 12px rgba(0,0,0,0.12)">
        <?php else: ?>
            <div style="width:110px;height:110px;border-radius:50%;background:#e0e0e0;color:#666;display:inline-flex;align-items:center;justify-content:center;font-size:48px;border:3px solid #ccc">👤</div>
        <?php endif; ?>
    </div>

    <form method="post" id="saveForm" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="cropped_image" id="croppedImageInput">

        <div class="form-group">
            <label style="font-weight:600;display:block;margin-bottom:6px"><?= h(t('idc.choose_image', 'Choose New Photo (JPG, PNG, WEBP)')) ?></label>
            <input type="file" name="photo_file" id="fileInput" accept="image/png,image/jpeg,image/webp" style="width:100%;padding:8px;border:1px dashed var(--border);border-radius:8px;background:var(--bg)">
        </div>

        <div id="cropArea" style="display:none;margin-top:16px;background:rgba(0,0,0,0.02);padding:14px;border-radius:10px;border:1px solid var(--border);">
            <p class="text-muted" style="font-size:13px;margin-top:0;text-align:center">
                <?= h(t('idc.crop_hint', 'Drag to reposition, use slider to zoom. Circle preview shows the final ID photo.')) ?>
            </p>
            <div style="position:relative;width:280px;height:280px;margin:0 auto;overflow:hidden;border-radius:12px;background:#111;touch-action:none;cursor:grab" id="viewport">
                <img id="previewImg" style="position:absolute;left:0;top:0;user-select:none;pointer-events:none" draggable="false">
                <div style="position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 2000px rgba(0,0,0,0.45);pointer-events:none;border:2px solid rgba(255,255,255,0.7)"></div>
            </div>
            <div class="form-group" style="margin-top:14px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <label style="margin:0"><?= h(t('idc.zoom', 'Zoom')) ?></label>
                    <span id="zoomVal" class="text-muted">1x</span>
                </div>
                <input type="range" id="zoomRange" min="1" max="3" step="0.01" value="1" style="width:100%">
            </div>
            <div style="display:flex;gap:8px;justify-content:center">
                <button type="button" class="btn secondary" onclick="rotateImg()" style="padding:6px 14px">↻ <?= h(t('idc.rotate', 'Rotate 90°')) ?></button>
                <button type="button" class="btn secondary" onclick="resetCrop()" style="padding:6px 14px">↺ Reset</button>
            </div>
        </div>

        <div style="margin-top:20px;display:flex;gap:10px">
            <button class="btn" type="submit" id="saveBtn" disabled style="flex:1;padding:12px;font-size:15px">💾 <?= h(t('idc.save_photo', 'Save Photo')) ?></button>
            <a href="<?= BASE_URL ?>/modules/employees/id_card.php?id=<?= (int) $empId ?>" class="btn secondary" style="padding:12px 18px"><?= h(t('idc.cancel', 'Cancel')) ?></a>
        </div>
    </form>
</div>

<canvas id="workCanvas" width="400" height="400" style="display:none"></canvas>

<script>
const OUTPUT_SIZE = 400; // saved photo is a 400x400 square PNG
const VIEWPORT = 280;
let img = new Image();
let scale = 1, minScale = 1, rotation = 0;
let offsetX = 0, offsetY = 0;
let dragging = false, startX = 0, startY = 0, startOffX = 0, startOffY = 0;

const fileInput = document.getElementById('fileInput');
const cropArea = document.getElementById('cropArea');
const previewImg = document.getElementById('previewImg');
const viewport = document.getElementById('viewport');
const zoomRange = document.getElementById('zoomRange');
const zoomVal = document.getElementById('zoomVal');
const saveBtn = document.getElementById('saveBtn');

fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) {
        saveBtn.disabled = true;
        cropArea.style.display = 'none';
        return;
    }
    
    // Enable save button immediately upon file selection (fallback ready)
    saveBtn.disabled = false;

    const reader = new FileReader();
    reader.onload = (ev) => {
        img = new Image();
        img.onload = () => {
            rotation = 0;
            minScale = Math.max(VIEWPORT / img.width, VIEWPORT / img.height);
            scale = minScale;
            zoomRange.min = minScale;
            zoomRange.max = minScale * 4;
            zoomRange.value = minScale;
            zoomVal.textContent = '1x';
            offsetX = (VIEWPORT - img.width * scale) / 2;
            offsetY = (VIEWPORT - img.height * scale) / 2;
            render();
            cropArea.style.display = 'block';
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
});

function render() {
    previewImg.src = img.src;
    previewImg.style.width = (img.width * scale) + 'px';
    previewImg.style.height = (img.height * scale) + 'px';
    previewImg.style.left = offsetX + 'px';
    previewImg.style.top = offsetY + 'px';
    previewImg.style.transform = `rotate(${rotation}deg)`;
    previewImg.style.transformOrigin = 'center center';
}

zoomRange.addEventListener('input', () => {
    const oldScale = scale;
    scale = parseFloat(zoomRange.value);
    zoomVal.textContent = (scale / minScale).toFixed(1) + 'x';
    const cx = VIEWPORT / 2, cy = VIEWPORT / 2;
    offsetX = cx - (cx - offsetX) * (scale / oldScale);
    offsetY = cy - (cy - offsetY) * (scale / oldScale);
    render();
});

function rotateImg() {
    rotation = (rotation + 90) % 360;
    render();
}

function resetCrop() {
    rotation = 0;
    scale = minScale;
    zoomRange.value = minScale;
    zoomVal.textContent = '1x';
    offsetX = (VIEWPORT - img.width * scale) / 2;
    offsetY = (VIEWPORT - img.height * scale) / 2;
    render();
}

viewport.addEventListener('pointerdown', (e) => {
    dragging = true;
    startX = e.clientX;
    startY = e.clientY;
    startOffX = offsetX;
    startOffY = offsetY;
    viewport.style.cursor = 'grabbing';
});

window.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    offsetX = startOffX + (e.clientX - startX);
    offsetY = startOffY + (e.clientY - startY);
    render();
});

window.addEventListener('pointerup', () => {
    dragging = false;
    viewport.style.cursor = 'grab';
});

document.getElementById('saveForm').addEventListener('submit', (e) => {
    // If image is loaded in canvas, export cropped base64 PNG
    if (img && img.src && img.complete && img.naturalWidth > 0) {
        try {
            const canvas = document.getElementById('workCanvas');
            canvas.width = OUTPUT_SIZE;
            canvas.height = OUTPUT_SIZE;
            const ctx = canvas.getContext('2d');
            const ratio = OUTPUT_SIZE / VIEWPORT;
            ctx.save();
            ctx.translate(OUTPUT_SIZE / 2, OUTPUT_SIZE / 2);
            ctx.rotate(rotation * Math.PI / 180);
            ctx.translate(-OUTPUT_SIZE / 2, -OUTPUT_SIZE / 2);
            ctx.drawImage(img, offsetX * ratio, offsetY * ratio, img.width * scale * ratio, img.height * scale * ratio);
            ctx.restore();
            document.getElementById('croppedImageInput').value = canvas.toDataURL('image/png');
        } catch (err) {
            console.warn('Canvas export failed, falling back to direct file upload:', err);
            // Fallback will naturally upload photo_file via multipart form
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
