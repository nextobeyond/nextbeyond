<?php
$pageTitle = 'โปรไฟล์ของฉัน';
$currentPage = 'profile.php';
require_once __DIR__ . '/includes/guard.php';
$message = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $nickname = trim((string) ($_POST['nickname'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    try {
        if ($first === '' || $last === '') throw new RuntimeException('กรุณากรอกชื่อและนามสกุล');
        $pdo->beginTransaction();
        
        $avatarUrl = $currentUser['avatar_url'] ?? null;
        $uploadDir = __DIR__ . '/../assets/uploads/avatars';

        // 1. ตรวจสอบการส่งรูปผ่าน Base64 (ที่ถูกย่อและบีบอัดอัตโนมัติจากฝั่งเบราว์เซอร์)
        $avatarBase64 = trim((string) ($_POST['avatar_base64'] ?? ''));
        if ($avatarBase64 !== '') {
            if (preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/', $avatarBase64, $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                $decoded = base64_decode($matches[2], true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    if (strlen($decoded) > 5 * 1024 * 1024) {
                        throw new RuntimeException('ไฟล์รูปภาพมีขนาดใหญ่เกินไป');
                    }
                    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('สร้างโฟลเดอร์สำหรับเก็บรูปโปรไฟล์ไม่สำเร็จ');
                    }
                    // ลบรูปเดิมของผู้ใช้นี้
                    foreach (glob($uploadDir . '/avatar-' . (int) $currentUser['id'] . '-*') ?: [] as $old) {
                        @unlink($old);
                    }
                    $filename = 'avatar-' . (int) $currentUser['id'] . '-' . time() . '.' . $ext;
                    if (file_put_contents($uploadDir . '/' . $filename, $decoded) === false) {
                        throw new RuntimeException('บันทึกรูปภาพไม่สำเร็จ กรุณาตรวจสอบสิทธิ์การเขียนโฟลเดอร์');
                    }
                    $avatarUrl = '/assets/uploads/avatars/' . $filename;
                } else {
                    throw new RuntimeException('ข้อมูลรูปภาพไม่ถูกต้อง');
                }
            } else {
                throw new RuntimeException('รูปแบบรูปภาพไม่ถูกต้อง รองรับเฉพาะ JPG, PNG, WEBP');
            }
        }
        // 2. ตรวจสอบการอัปโหลดผ่าน File Input ปกติ (Fallback)
        elseif (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar'];
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                throw new RuntimeException('ไฟล์รูปภาพมีขนาดใหญ่เกินไป (กรุณาเลือกรูปขนาดไม่เกิน 5MB)');
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (รหัส: ' . $file['error'] . ')');
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new RuntimeException('ไม่พบไฟล์ที่อัปโหลด');
            }
            if ((int) $file['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5MB');
            }

            $mime = '';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file($file['tmp_name']);
            } elseif (function_exists('mime_content_type')) {
                $mime = (string) mime_content_type($file['tmp_name']);
            }
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                $origExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = $origExt === 'jpeg' ? 'jpg' : $origExt;
                } else {
                    throw new RuntimeException('รองรับเฉพาะไฟล์ PNG, JPG และ WEBP เท่านั้น');
                }
            } else {
                $ext = $extensions[$mime];
            }

            if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('สร้างโฟลเดอร์สำหรับเก็บรูปโปรไฟล์ไม่สำเร็จ');
            }
            foreach (glob($uploadDir . '/avatar-' . (int) $currentUser['id'] . '-*') ?: [] as $old) {
                @unlink($old);
            }
            $filename = 'avatar-' . (int) $currentUser['id'] . '-' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                throw new RuntimeException('บันทึกไฟล์รูปภาพไม่สำเร็จ');
            }
            $avatarUrl = '/assets/uploads/avatars/' . $filename;
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET first_name=:first,last_name=:last,nickname=:nickname,phone=:phone,avatar_url=:avatar WHERE id=:id');
            $stmt->execute([':first'=>$first,':last'=>$last,':nickname'=>$nickname?:null,':phone'=>$phone?:null,':avatar'=>$avatarUrl,':id'=>$currentUser['id']]);
        } catch (PDOException $e) {
            try {
                $stmt = $pdo->prepare('UPDATE users SET first_name=:first,last_name=:last,phone=:phone,avatar_url=:avatar WHERE id=:id');
                $stmt->execute([':first'=>$first,':last'=>$last,':phone'=>$phone?:null,':avatar'=>$avatarUrl,':id'=>$currentUser['id']]);
            } catch (PDOException $e2) {
                $stmt = $pdo->prepare('UPDATE users SET first_name=:first,last_name=:last,phone=:phone WHERE id=:id');
                $stmt->execute([':first'=>$first,':last'=>$last,':phone'=>$phone?:null,':id'=>$currentUser['id']]);
            }
        }
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if ($newPassword !== '') {
            $oldPassword = (string) ($_POST['old_password'] ?? '');
            $check = $pdo->prepare('SELECT password_hash FROM users WHERE id=:id');
            $check->execute([':id'=>$currentUser['id']]);
            if (!password_verify($oldPassword, (string)$check->fetchColumn())) throw new RuntimeException('รหัสผ่านเดิมไม่ถูกต้อง');
            if (strlen($newPassword) < 8) throw new RuntimeException('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
            $update = $pdo->prepare('UPDATE users SET password_hash=:hash WHERE id=:id');
            $update->execute([':hash'=>password_hash($newPassword,PASSWORD_DEFAULT),':id'=>$currentUser['id']]);
        }
        $pdo->commit();
        $message = 'บันทึกโปรไฟล์แล้ว';
        $currentUser['first_name']=$first; $currentUser['last_name']=$last; $currentUser['nickname']=$nickname; $currentUser['phone']=$phone; $currentUser['avatar_url']=$avatarUrl;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
    <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
    <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950">
<div class="min-h-screen flex">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0">
        <?php include 'includes/topbar.php'; ?>
        <main class="p-8 max-[640px]:p-4 max-w-[900px]">
            <div class="student-profile-shell">
                <?php if ($message): ?>
                    <div class="mb-4 p-4 rounded-xl bg-green-50 text-green-700 font-bold"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mb-4 p-4 rounded-xl bg-red-50 text-red-700 font-bold"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form id="profileForm" method="post" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="avatar_base64" id="avatarBase64" value="">

                    <section class="bg-white rounded-[20px] border border-[#e8ecf2] p-6">
                        <h2 class="font-bold text-[17px] mb-5">รูปโปรไฟล์</h2>
                        <div class="flex items-center gap-6">
                            <?php $avatarSrc = studentAvatarUrl($currentUser['avatar_url'] ?? null); ?>
                            <div class="relative w-24 h-24 shrink-0">
                                <img id="avatarPreviewImg" src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border border-[#e8ecf2] <?= empty($avatarSrc) ? 'hidden' : '' ?>">
                                <div id="avatarFallback" class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-3xl border border-[#e8ecf2] <?= !empty($avatarSrc) ? 'hidden' : '' ?>">
                                    <?= htmlspecialchars(mb_substr($currentUser['first_name'], 0, 1)) ?>
                                </div>
                            </div>
                            <div>
                                <input type="file" id="avatarInput" name="avatar" accept="image/png, image/jpeg, image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-pink-50 file:text-pink-600 hover:file:bg-pink-100 file:cursor-pointer transition-colors">
                                <p class="text-[13px] text-slate-500 mt-2">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 5MB (ระบบปรับขนาดให้อัตโนมัติ)</p>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-[20px] border border-[#e8ecf2] p-6">
                        <h2 class="font-bold text-[17px] mb-5">ข้อมูลส่วนตัว</h2>
                        <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
                            <label class="text-[13px] font-bold">
                                ชื่อ
                                <input name="first_name" value="<?= htmlspecialchars($currentUser['first_name']) ?>" autocomplete="given-name" required class="mt-2 w-full h-11 px-4 border rounded-xl">
                            </label>
                            <label class="text-[13px] font-bold">
                                นามสกุล
                                <input name="last_name" value="<?= htmlspecialchars($currentUser['last_name']) ?>" autocomplete="family-name" required class="mt-2 w-full h-11 px-4 border rounded-xl">
                            </label>
                            <label class="text-[13px] font-bold">
                                ชื่อเล่น
                                <input name="nickname" value="<?= htmlspecialchars($currentUser['nickname'] ?? '') ?>" autocomplete="nickname" class="mt-2 w-full h-11 px-4 border rounded-xl">
                            </label>
                            <label class="text-[13px] font-bold">
                                เบอร์โทร
                                <input type="tel" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" autocomplete="tel" class="mt-2 w-full h-11 px-4 border rounded-xl">
                            </label>
                            <label class="text-[13px] font-bold col-span-2 max-[640px]:col-span-1">
                                อีเมล
                                <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" autocomplete="email" disabled class="mt-2 w-full h-11 px-4 border rounded-xl bg-[#f8fafc]">
                            </label>
                        </div>
                    </section>

                    <section class="bg-white rounded-[20px] border border-[#e8ecf2] p-6">
                        <h2 class="font-bold text-[17px] mb-5">เปลี่ยนรหัสผ่าน</h2>
                        <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
                            <input type="password" name="old_password" autocomplete="current-password" aria-label="รหัสผ่านเดิม" placeholder="รหัสผ่านเดิม" class="h-11 px-4 border rounded-xl">
                            <input type="password" name="new_password" autocomplete="new-password" minlength="8" aria-label="รหัสผ่านใหม่ อย่างน้อย 8 ตัว" placeholder="รหัสผ่านใหม่ อย่างน้อย 8 ตัว" class="h-11 px-4 border rounded-xl">
                        </div>
                    </section>

                    <button type="submit" class="h-11 px-7 rounded-xl bg-pink-500 hover:bg-pink-600 active:scale-[0.99] text-white font-bold transition-all shadow-sm">บันทึกการเปลี่ยนแปลง</button>
                </form>
            </div>
        </main>
        <?php include 'includes/bottom-nav.php'; ?>
    </div>
</div>

<script>
(() => {
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreviewImg = document.getElementById('avatarPreviewImg');
    const avatarFallback = document.getElementById('avatarFallback');
    const avatarBase64 = document.getElementById('avatarBase64');

    if (!avatarInput) return;

    avatarInput.addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (!file) return;

        // ตรวจสอบชนิดไฟล์
        if (!file.type.match(/^image\/(jpeg|png|webp)$/i) && !file.name.match(/\.(jpe?g|png|webp)$/i)) {
            alert('กรุณาเลือกไฟล์รูปภาพที่เป็น JPG, PNG หรือ WEBP');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(evt) {
            const img = new Image();
            img.onload = function() {
                // Auto crop & resize to square 500x500 for crisp, lightweight upload (~60KB)
                const maxDim = 500;
                let width = img.width;
                let height = img.height;

                const size = Math.min(width, height);
                const sx = (width - size) / 2;
                const sy = (height - size) / 2;
                const destSize = Math.min(maxDim, size);

                const canvas = document.createElement('canvas');
                canvas.width = destSize;
                canvas.height = destSize;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, sx, sy, size, size, 0, 0, destSize, destSize);

                const compressed = canvas.toDataURL('image/jpeg', 0.88);
                if (avatarBase64) {
                    avatarBase64.value = compressed;
                }

                // แสดง Preview ทันที
                if (avatarPreviewImg) {
                    avatarPreviewImg.src = compressed;
                    avatarPreviewImg.classList.remove('hidden');
                }
                if (avatarFallback) {
                    avatarFallback.classList.add('hidden');
                }
            };
            img.src = evt.target.result;
        };
        reader.readAsDataURL(file);
    });
})();
</script>
</body>
</html>
