<?php
/**
 * student/includes/sidebar.php
 * Sidebar นักเรียน — ใช้ include จากทุกหน้าใน /student/
 */
$studentMenu = [
    'หน้าหลัก'  => [
        ['index.php',        'ภาพรวม',           'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ],
    'การเรียน'   => [
        ['my-courses.php',   'คอร์สของฉัน',       'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['courses.php',      'ค้นหาคอร์ส',        'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
        ['learning-path.php','เส้นทางการเรียน',   'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
        ['roadmap.php',      'Study Roadmap',     'M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437A1 1 0 0021 17.305V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.125 1.125 0 01-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689A1.125 1.125 0 003 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.125 1.125 0 011.006 0l4.994 2.497a1.125 1.125 0 001.006 0z'],
    ],
    'แบบทดสอบ'  => [
        ['live-session.php', '⚡ ห้องเรียนสด (Live)', 'M13 10V3L4 14h7v7l9-11h-7z'],
        ['tests.php',        'ข้อสอบที่เปิดอยู่',  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['my-tests.php',     'ประวัติข้อสอบ',      'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ['score-calculator.php', 'คำนวณคะแนน TCAS', 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
    ],
    'บัญชี'     => [
        ['profile.php',      'โปรไฟล์ของฉัน',     'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ],
];

$stmtSidebarSettings = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'calculator_enabled'");
if ($stmtSidebarSettings->fetchColumn() === '0') {
    array_pop($studentMenu['แบบทดสอบ']);
}

$currentPage = $currentPage ?? 'index.php';
$u = $currentUser ?? [];
$displayName = !empty($u['nickname']) ? trim($u['nickname']) : (trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'นักเรียน');
$initials = mb_strtoupper(mb_substr($u['first_name'] ?? 'N', 0, 1) . mb_substr($u['last_name'] ?? 'B', 0, 1));
$avatarSrc = studentAvatarUrl($u['avatar_url'] ?? null);
?>
<aside id="studentSidebar" class="fixed inset-y-0 left-0 w-[240px] bg-white border-r border-[#e8ecf2] overflow-y-auto flex flex-col z-40 shadow-[2px_0_16px_rgba(15,42,83,0.06)] max-[1024px]:-translate-x-full transition-transform duration-300">

  <!-- Logo -->
  <div class="p-5 border-b border-[#e8ecf2] shrink-0">
    <a href="/index.php" class="inline-flex items-center gap-3 w-full" aria-label="Nextbeyond Compass">
      <svg class="w-8 h-8 shrink-0" viewBox="0 0 40 40" fill="none">
        <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round"/>
        <clipPath id="logo-clip-student"><rect x="0" y="9" width="40" height="22"/></clipPath>
        <g clip-path="url(#logo-clip-student)">
          <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#061633" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8"/>
        </g>
      </svg>
      <span class="grid leading-tight">
        <strong class="text-navy-950 text-[13px] tracking-[0.04em]">NEXT BEYOND</strong>
        <small class="text-pink-500 text-[9px] font-bold tracking-[0.18em]">STUDENT</small>
      </span>
    </a>
  </div>

  <!-- User Profile Chip -->
  <div class="px-4 py-4 border-b border-[#e8ecf2] shrink-0">
    <div class="flex items-center gap-3">
      <?php if (!empty($avatarSrc)): ?>
        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="avatar" class="w-9 h-9 rounded-full object-cover shrink-0">
      <?php else: ?>
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-pink-400 to-pink-600 flex items-center justify-center text-white font-bold text-[13px] shrink-0">
          <?= htmlspecialchars($initials) ?>
        </div>
      <?php endif; ?>
      <div class="min-w-0">
        <div class="text-[13px] font-bold text-navy-950 truncate"><?= htmlspecialchars($displayName) ?></div>
        <div class="text-[11px] text-[#65738a] truncate"><?= htmlspecialchars($u['email'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- Nav Links -->
  <nav class="p-4 flex-1">
    <?php foreach ($studentMenu as $groupLabel => $items): ?>
      <div class="mb-5">
        <div class="px-3 mb-1.5 text-[10px] font-black tracking-[0.12em] text-[#94a3b8] uppercase"><?= $groupLabel ?></div>
        <div class="flex flex-col gap-0.5">
          <?php foreach ($items as [$page, $label, $icon]): ?>
            <?php $isActive = $currentPage === $page; ?>
            <a href="<?= $page ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-[10px] text-[13px] font-medium transition-colors <?= $isActive ? 'bg-pink-500 text-white shadow-[0_4px_12px_rgba(231,45,130,0.25)]' : 'text-[#4b5e7a] hover:bg-[#f4f7fb] hover:text-navy-950' ?>">
              <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/>
              </svg>
              <?= htmlspecialchars($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="p-4 border-t border-[#e8ecf2] shrink-0">
    <a href="/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-[10px] text-[13px] font-medium text-[#65738a] hover:bg-[#f4f7fb] hover:text-navy-950 transition-colors mb-1">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      กลับหน้าเว็บหลัก
    </a>
    <a href="/student/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-[10px] text-[13px] font-medium text-[#ef4444] hover:bg-red-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      ออกจากระบบ
    </a>
  </div>
</aside>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-navy-950/50 z-30 hidden" aria-hidden="true"></div>
