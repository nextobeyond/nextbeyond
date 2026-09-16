<?php
/**
 * student/includes/topbar.php — topbar สำหรับหน้า Student
 */
$pageTitle = $pageTitle ?? 'Student Dashboard';
?>
<header class="student-topbar sticky top-0 z-30 bg-white border-b border-[#e8ecf2]">
  <div class="flex items-center h-[60px] px-6 gap-4">
    <!-- Mobile hamburger -->
    <button id="sidebarToggle" class="hidden max-[1024px]:flex items-center justify-center w-9 h-9 rounded-lg text-[#65738a] hover:bg-[#f4f7fb] transition-colors" type="button" aria-label="เปิดเมนู" aria-controls="studentSidebar" aria-expanded="false">
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>

    <a href="index.php" class="student-mobile-brand" aria-label="Next Beyond Student">
      <svg viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <line x1="25" y1="32" x2="31" y2="8" stroke="#ff168b" stroke-width="8" stroke-linecap="round"/>
        <clipPath id="logo-clip-topbar"><rect x="0" y="9" width="40" height="22"/></clipPath>
        <g clip-path="url(#logo-clip-topbar)"><path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#061a40" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8"/></g>
      </svg>
      <span><strong>NEXT BEYOND</strong><small>STUDENT</small></span>
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
        $displayName = !empty($u['nickname']) ? trim($u['nickname']) : trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'นักเรียน';
        ?>
        <?php if (!empty($u['avatar_url'])): ?>
          <img src="<?= htmlspecialchars($u['avatar_url']) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover shrink-0 border border-[#e8ecf2]">
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
