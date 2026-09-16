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
        if (!empty($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $file = $_FILES['avatar'];
            if ((int) $file['size'] > 2 * 1024 * 1024) throw new RuntimeException('ไฟล์รูปภาพต้องมีขนาดไม่เกิน 2MB');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) throw new RuntimeException('รองรับเฉพาะไฟล์ PNG, JPG และ WEBP');
            
            $uploadDir = __DIR__ . '/../assets/uploads/avatars';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ');
            }
            $filename = 'avatar-' . $currentUser['id'] . '-' . time() . '.' . $extensions[$mime];
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                throw new RuntimeException('บันทึกไฟล์รูปภาพไม่สำเร็จ');
            }
            $avatarUrl = '/assets/uploads/avatars/' . $filename;
        }

        $stmt = $pdo->prepare('UPDATE users SET first_name=:first,last_name=:last,nickname=:nickname,phone=:phone,avatar_url=:avatar WHERE id=:id');
        $stmt->execute([':first'=>$first,':last'=>$last,':nickname'=>$nickname?:null,':phone'=>$phone?:null,':avatar'=>$avatarUrl,':id'=>$currentUser['id']]);
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

                <form method="post" enctype="multipart/form-data" class="space-y-5">
                    <section class="bg-white rounded-[20px] border border-[#e8ecf2] p-6">
                        <h2 class="font-bold text-[17px] mb-5">รูปโปรไฟล์</h2>
                        <div class="flex items-center gap-6">
                            <?php if (!empty($currentUser['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($currentUser['avatar_url']) ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border border-[#e8ecf2]">
                            <?php else: ?>
                                <div class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-3xl border border-[#e8ecf2]">
                                    <?= htmlspecialchars(mb_substr($currentUser['first_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <input type="file" name="avatar" accept="image/png, image/jpeg, image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100 transition-colors">
                                <p class="text-[13px] text-slate-500 mt-2">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 2MB</p>
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

                    <button type="submit" class="h-11 px-7 rounded-xl bg-pink-500 text-white font-bold">บันทึกการเปลี่ยนแปลง</button>
                </form>
            </div>
        </main>
        <?php include 'includes/bottom-nav.php'; ?>
    </div>
</div>
</body>
</html>
