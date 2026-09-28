<?php
/**
 * student/includes/topbar.php — topbar สำหรับหน้า Student
 */
$pageTitle = $pageTitle ?? 'Student Dashboard';
?>
<?php if (!empty($adminInspectMode) && !empty($adminRealUser)): ?>
<div id="admin-inspect-banner" style="position:sticky;top:0;z-index:40;background:linear-gradient(90deg,#d97706,#f59e0b);color:#fff;padding:0 20px;height:44px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px;font-weight:600;box-shadow:0 2px 8px rgba(217,119,6,0.35);">
  <div style="display:flex;align-items:center;gap:10px;">
    <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
    <span>⚠️ Admin Inspection Mode — กำลังดูระบบในมุมมอง: <strong><?= htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?></strong><?= !empty($currentUser['nickname']) ? ' (' . htmlspecialchars($currentUser['nickname']) . ')' : '' ?></span>
  </div>
  <button id="btn-exit-inspect" type="button" style="flex-shrink:0;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.35);color:#fff;padding:5px 16px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background 0.15s;" onmouseover="this.style.background='rgba(0,0,0,0.4)'" onmouseout="this.style.background='rgba(0,0,0,0.25)'">
    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    ออกจากโหมดตรวจระบบ
  </button>
</div>
<?php endif; ?>
<header class="student-topbar sticky top-0 z-30 bg-white border-b border-[#e8ecf2]">
  <div class="flex items-center h-[60px] px-6 gap-4">
    <!-- Mobile hamburger -->
    <button id="sidebarToggle" class="hidden max-[1024px]:flex items-center justify-center w-9 h-9 rounded-lg text-[#65738a] hover:bg-[#f4f7fb] transition-colors" type="button" aria-label="เปิดเมนู" aria-controls="studentSidebar" aria-expanded="false">
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>

<?php
require_once __DIR__ . '/../../includes/settings-service.php';
$topbarLogo = (string) SettingsService::get('school_logo', '', $pdo ?? null);
$topbarSchoolName = (string) SettingsService::get('school_name', 'NEXT BEYOND', $pdo ?? null);
?>
    <a href="index.php" class="student-mobile-brand" aria-label="<?= htmlspecialchars($topbarSchoolName) ?> Student">
      <?php if (!empty($topbarLogo)): ?>
        <img src="../<?= htmlspecialchars(ltrim($topbarLogo, '/')) ?>?v=<?= @filemtime(__DIR__ . '/../../' . ltrim($topbarLogo, '/')) ?: time() ?>" alt="Logo" class="w-8 h-8 object-contain rounded-lg shrink-0">
      <?php else: ?>
        <svg viewBox="0 0 40 40" fill="none" aria-hidden="true" class="w-8 h-8 shrink-0">
          <line x1="25" y1="32" x2="31" y2="8" stroke="#ff168b" stroke-width="8" stroke-linecap="round"/>
          <clipPath id="logo-clip-topbar"><rect x="0" y="9" width="40" height="22"/></clipPath>
          <g clip-path="url(#logo-clip-topbar)"><path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#061a40" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8"/></g>
        </svg>
      <?php endif; ?>
      <span><strong><?= htmlspecialchars(mb_strtoupper($topbarSchoolName)) ?></strong><small>STUDENT</small></span>
    </a>

    <h1 class="text-[16px] font-bold text-navy-950 truncate"><?= htmlspecialchars($pageTitle) ?></h1>

    <div class="ml-auto flex items-center gap-3">
      <!-- Notification Bell (future) -->
      <button class="student-notification w-9 h-9 rounded-lg flex items-center justify-center text-[#65738a] hover:bg-[#f4f7fb] transition-colors relative" aria-label="การแจ้งเตือน">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
      </button>
      <!-- Profile link -->
      <a href="profile.php" class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-[#f4f7fb] transition-colors">
        <?php
        $u = $currentUser ?? [];
        $initials = mb_strtoupper(mb_substr($u['first_name'] ?? 'N', 0, 1) . mb_substr($u['last_name'] ?? 'B', 0, 1));
        $displayName = !empty($u['nickname']) ? trim($u['nickname']) : (trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'นักเรียน');
        $avatarSrc = studentAvatarUrl($u['avatar_url'] ?? null);
        ?>
        <?php if (!empty($avatarSrc)): ?>
          <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover shrink-0 border border-[#e8ecf2]">
        <?php else: ?>
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-400 to-pink-600 flex items-center justify-center text-white font-bold text-[12px] shrink-0">
            <?= htmlspecialchars($initials) ?>
          </div>
        <?php endif; ?>
        <span class="text-[13px] font-bold text-navy-950 max-[640px]:hidden"><?= htmlspecialchars($displayName) ?></span>
      </a>
    </div>
  </div>
</header>

<script>
  (() => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('studentSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    const setMenuOpen = (open) => {
      sidebar?.classList.toggle('max-[1024px]:-translate-x-full', !open);
      overlay?.classList.toggle('hidden', !open);
      overlay?.setAttribute('aria-hidden', open ? 'false' : 'true');
      toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('student-menu-open', open);
    };

    toggle?.addEventListener('click', () => {
      setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    overlay?.addEventListener('click', () => setMenuOpen(false));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') setMenuOpen(false);
    });
  })();
</script>
<?php if (!empty($adminInspectMode)): ?>
<script>
(() => {
  const btn = document.getElementById('btn-exit-inspect');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    btn.textContent = 'กำลังออก...';
    try {
      await fetch('../admin/inspect-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'stop' }),
      });
    } catch(e) {}
    window.location.href = '../admin/students.php';
  });
})();
</script>
<?php endif; ?>
