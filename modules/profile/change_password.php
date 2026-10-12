<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([current_user()['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = current_locale() === 'am' ? 'የአሁኑ የይለፍ ቃል የተሳሳተ ነው።' : 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = current_locale() === 'am' ? 'አዲሱ የይለፍ ቃል ቢያንስ 8 ቁምፊዎች መሆን አለበት።' : 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = current_locale() === 'am' ? 'አዲሱ የይለፍ ቃል እና ማረጋገጫው አይመሳሰሉም።' : 'New password and confirmation do not match.';
    } else {
        db()->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), current_user()['id']]);
        log_activity('auth', 'update', 'users', current_user()['id'], 'Password changed');
        flash(current_locale() === 'am' ? 'የይለፍ ቃልዎ በተሳካ ሁኔታ ተቀይሯል።' : 'Password changed successfully.');
        header('Location: /aleltu-eifms/index.php');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width:540px;margin:24px auto;">
    <div class="card" style="box-shadow:0 8px 30px rgba(0,0,0,0.08);border-radius:12px;padding:32px;border:1px solid var(--border);">
        
        <!-- Header / Brand -->
        <div style="text-align:center;margin-bottom:26px;">
            <div style="width:64px;height:64px;background:rgba(46,125,50,0.1);color:#2e7d32;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:12px;">
                🔐
            </div>
            <h2 style="margin:0 0 6px;"><?= current_locale() === 'am' ? 'የይለፍ ቃል ቀይር' : 'Change Password' ?></h2>
            <p class="text-muted" style="margin:0;font-size:14px;">
                <?= current_locale() === 'am' 
                    ? 'አካውንትዎ ደህንነቱ የተጠበቀ እንዲሆን ጠንካራ የይለፍ ቃል ይጠቀሙ።' 
                    : 'Ensure your account stays secure by using a strong, unique password.' ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="flash error" style="margin-bottom:20px;border-radius:8px;">
                ⚠️ <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="changePasswordForm">
            <?= csrf_field() ?>

            <!-- Current Password -->
            <div class="form-group" style="margin-bottom:18px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;">
                    <?= current_locale() === 'am' ? 'የአሁኑ የይለፍ ቃል *' : 'Current Password *' ?>
                </label>
                <div style="position:relative;">
                    <input type="password" name="current_password" id="current_password" required
                           placeholder="••••••••"
                           style="width:100%;padding:10px 42px 10px 14px;border-radius:8px;border:1px solid var(--border);font-size:15px;">
                    <button type="button" onclick="togglePasswordVisibility('current_password', this)"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:#777;padding:4px;">
                        👁️
                    </button>
                </div>
            </div>

            <!-- New Password -->
            <div class="form-group" style="margin-bottom:12px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;">
                    <?= current_locale() === 'am' ? 'አዲስ የይለፍ ቃል *' : 'New Password *' ?>
                </label>
                <div style="position:relative;">
                    <input type="password" name="new_password" id="new_password" required minlength="8"
                           placeholder="••••••••"
                           oninput="checkPasswordStrength()"
                           style="width:100%;padding:10px 42px 10px 14px;border-radius:8px;border:1px solid var(--border);font-size:15px;">
                    <button type="button" onclick="togglePasswordVisibility('new_password', this)"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:#777;padding:4px;">
                        👁️
                    </button>
                </div>
            </div>

            <!-- Strength Meter Bar -->
            <div style="margin-bottom:18px;">
                <div style="height:6px;background:#eee;border-radius:3px;overflow:hidden;margin-bottom:6px;">
                    <div id="strengthBar" style="height:100%;width:0%;transition:width 0.3s ease, background 0.3s ease;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;">
                    <span class="text-muted"><?= current_locale() === 'am' ? 'የይለፍ ቃል ጥንካሬ:' : 'Password Strength:' ?></span>
                    <strong id="strengthLabel" style="color:#888;">—</strong>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-weight:600;display:block;margin-bottom:6px;">
                    <?= current_locale() === 'am' ? 'አዲሱን የይለፍ ቃል አረጋግጥ *' : 'Confirm New Password *' ?>
                </label>
                <div style="position:relative;">
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                           placeholder="••••••••"
                           oninput="checkMatch()"
                           style="width:100%;padding:10px 42px 10px 14px;border-radius:8px;border:1px solid var(--border);font-size:15px;">
                    <button type="button" onclick="togglePasswordVisibility('confirm_password', this)"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:#777;padding:4px;">
                        👁️
                    </button>
                </div>
                <div id="matchNotice" style="font-size:12px;margin-top:4px;display:none;"></div>
            </div>

            <!-- Live Checklist -->
            <div style="background:var(--bg, #f8f9fa);padding:14px 16px;border-radius:8px;margin-bottom:22px;font-size:13px;border:1px solid var(--border);">
                <div style="font-weight:600;margin-bottom:6px;"><?= current_locale() === 'am' ? 'የይለፍ ቃል መስፈርቶች:' : 'Password Requirements:' ?></div>
                <div id="reqLength" style="color:#888;margin-bottom:3px;">○ <?= current_locale() === 'am' ? 'ቢያንስ 8 ፊደላት/ቁጥሮች' : 'At least 8 characters' ?></div>
                <div id="reqComplex" style="color:#888;margin-bottom:3px;">○ <?= current_locale() === 'am' ? 'የፊደላት እና የቁጥሮች ውህድ' : 'Mix of letters and numbers' ?></div>
                <div id="reqMatch" style="color:#888;">○ <?= current_locale() === 'am' ? 'ሁለቱም የይለፍ ቃሎች ይመሳሰላሉ' : 'Both passwords match' ?></div>
            </div>

            <!-- Submit & Cancel Buttons -->
            <div style="display:flex;gap:12px;align-items:center;">
                <button class="btn" type="submit" style="flex:1;padding:12px 18px;font-size:15px;font-weight:bold;border-radius:8px;">
                    <?= current_locale() === 'am' ? 'የይለፍ ቃል አዘምን' : 'Update Password' ?>
                </button>
                <a href="/aleltu-eifms/index.php" class="btn secondary" style="padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600;">
                    <?= current_locale() === 'am' ? 'ይቅር' : 'Cancel' ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🔒';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

function checkPasswordStrength() {
    const pwd = document.getElementById('new_password').value;
    const bar = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    const reqLen = document.getElementById('reqLength');
    const reqComp = document.getElementById('reqComplex');

    let score = 0;
    if (!pwd) {
        bar.style.width = '0%';
        label.textContent = '—';
        label.style.color = '#888';
        reqLen.style.color = '#888'; reqLen.innerHTML = '○ ' + reqLen.textContent.substring(2);
        reqComp.style.color = '#888'; reqComp.innerHTML = '○ ' + reqComp.textContent.substring(2);
        checkMatch();
        return;
    }

    // Length check
    if (pwd.length >= 8) {
        score += 25;
        reqLen.style.color = '#2e7d32';
        reqLen.innerHTML = '✓ ' + reqLen.textContent.substring(2);
    } else {
        reqLen.style.color = '#c62828';
        reqLen.innerHTML = '✗ ' + reqLen.textContent.substring(2);
    }

    if (pwd.length >= 12) score += 15;
    if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score += 20;
    if (/\d/.test(pwd)) score += 20;
    if (/[^a-zA-Z0-9]/.test(pwd)) score += 20;

    // Complexity check (letters + numbers)
    if (/[a-zA-Z]/.test(pwd) && /\d/.test(pwd)) {
        reqComp.style.color = '#2e7d32';
        reqComp.innerHTML = '✓ ' + reqComp.textContent.substring(2);
    } else {
        reqComp.style.color = '#888';
        reqComp.innerHTML = '○ ' + reqComp.textContent.substring(2);
    }

    bar.style.width = Math.min(score, 100) + '%';
    const isAm = '<?= current_locale() ?>' === 'am';

    if (score < 40) {
        bar.style.background = '#e53935';
        label.textContent = isAm ? 'ደካማ' : 'Weak';
        label.style.color = '#e53935';
    } else if (score < 70) {
        bar.style.background = '#fb8c00';
        label.textContent = isAm ? 'መካከለኛ' : 'Moderate';
        label.style.color = '#fb8c00';
    } else if (score < 90) {
        bar.style.background = '#43a047';
        label.textContent = isAm ? 'ጠንካራ' : 'Strong';
        label.style.color = '#43a047';
    } else {
        bar.style.background = '#1e88e5';
        label.textContent = isAm ? 'እጅግ ጠንካራ' : 'Very Strong';
        label.style.color = '#1e88e5';
    }

    checkMatch();
}

function checkMatch() {
    const newPwd = document.getElementById('new_password').value;
    const confirmPwd = document.getElementById('confirm_password').value;
    const notice = document.getElementById('matchNotice');
    const reqMatch = document.getElementById('reqMatch');
    const isAm = '<?= current_locale() ?>' === 'am';

    if (!confirmPwd) {
        notice.style.display = 'none';
        reqMatch.style.color = '#888';
        reqMatch.innerHTML = '○ ' + reqMatch.textContent.substring(2);
        return;
    }

    notice.style.display = 'block';
    if (newPwd === confirmPwd) {
        notice.style.color = '#2e7d32';
        notice.textContent = isAm ? '✓ የይለፍ ቃሎች ይጣጣማሉ' : '✓ Passwords match';
        reqMatch.style.color = '#2e7d32';
        reqMatch.innerHTML = '✓ ' + reqMatch.textContent.substring(2);
    } else {
        notice.style.color = '#c62828';
        notice.textContent = isAm ? '✗ የይለፍ ቃሎች አይመሳሰሉም' : '✗ Passwords do not match';
        reqMatch.style.color = '#c62828';
        reqMatch.innerHTML = '✗ ' + reqMatch.textContent.substring(2);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
