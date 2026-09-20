<?php
$adminLinkPrefix = $adminLinkPrefix ?? '';
$adminMenu = [
  "OVERVIEW" => [
    ["index.php", "ภาพรวม"],
    ["analytics.php", "Analytics และรายงาน"]
  ],
  "LEARNING" => [
    ["courses.php", "คอร์สเรียน"],
    ["curriculum.php", "บทเรียนในคอร์ส"],
    ["roadmap.php", "Study Roadmap"],
    ["teachers.php", "ครูผู้สอน"],
    ["calendar.php", "ปฏิทินและตารางสอน"]
  ],
  "CONTENT" => [
    ["worksheets.php", "คลังใบงาน"],
    ["question-bank.php", "Question Bank"],
    ["ai-worksheet.php", "AI สร้างใบงาน"],
    ["ai-exam-app/", "AI สร้างข้อสอบ"]
  ],
  "ASSESSMENT" => [
    ["tests.php", "แบบทดสอบ"],
    ["live-sessions.php", "⚡ ห้องเรียนสด (Live)"]
  ],
  "OPERATIONS" => [
    ["students.php", "นักเรียน"],
    ["interventions.php", "คิวช่วยเหลือผู้เรียน"],
    ["orders.php", "คำสั่งซื้อและบัญชี"]
  ],
  "SYSTEM" => [
    ["settings.php", "ตั้งค่าระบบ"]
  ]
];
$currentPage = $currentPage ?? 'index.php';
foreach ($adminMenu as $group => $items) {
  $adminMenu[$group] = array_values(array_filter($items, fn($item) => consoleAllowed($item[0])));
  if (!$adminMenu[$group]) unset($adminMenu[$group]);
}
?>
<aside class="fixed inset-y-0 left-0 w-[240px] bg-navy-950 text-white overflow-y-auto flex flex-col z-40 max-[1024px]:-translate-x-full transition-transform duration-300 shadow-[4px_0_24px_rgba(15,42,83,0.1)]" id="adminSidebar">
<?php
require_once __DIR__ . '/../../includes/settings-service.php';
$sidebarLogo = (string) SettingsService::get('school_logo', '', $pdo);
$sidebarSchoolName = (string) SettingsService::get('school_name', 'NEXT BEYOND', $pdo);
?>
  <div class="p-6 border-b border-white/10 shrink-0">
    <a href="<?= htmlspecialchars($adminLinkPrefix, ENT_QUOTES, 'UTF-8') ?>index.php" class="inline-flex items-center gap-3 w-full" aria-label="<?= htmlspecialchars($sidebarSchoolName) ?> Admin">
      <span id="admin-sidebar-logo-slot" class="shrink-0 flex items-center justify-center">
        <?php if (!empty($sidebarLogo)): ?>
          <img src="../<?= htmlspecialchars(ltrim($sidebarLogo, '/')) ?>?v=<?= @filemtime(__DIR__ . '/../../' . ltrim($sidebarLogo, '/')) ?: time() ?>" alt="Logo" class="w-8 h-8 object-contain rounded-lg shrink-0">
        <?php else: ?>
          <svg class="w-[32px] h-[32px] shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" />
            <clipPath id="logo-clip-admin">
              <rect x="0" y="9" width="40" height="22" />
            </clipPath>
            <g clip-path="url(#logo-clip-admin)">
              <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#ffffff" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" />
            </g>
          </svg>
        <?php endif; ?>
      </span>
      <span class="grid leading-[1.15] truncate">
        <strong id="admin-sidebar-school-name" class="text-white text-[14px] tracking-[0.04em] truncate"><?= htmlspecialchars(mb_strtoupper($sidebarSchoolName)) ?></strong>
        <small class="text-pink-500 text-[9px] font-bold tracking-[0.18em]"><?= $consoleUser['role'] === 'teacher' ? 'TEACHER CONSOLE' : 'ADMIN CONSOLE' ?></small>
      </span>
    </a>
  </div>
  <div class="p-4 flex-1">
    <?php foreach ($adminMenu as $groupLabel => $items): ?>
      <div class="mb-6">
        <div class="px-3 mb-2 text-[11px] font-black tracking-[0.1em] text-[#65738a] uppercase"><?= $groupLabel ?></div>
        <div class="flex flex-col gap-1">
          <?php foreach ($items as $item): 
            $isActive = $currentPage === $item[0] || ($item[0] === 'worksheets.php' && in_array($currentPage, ['worksheets.php', 'worksheet-detail.php']));
            $icon = '';
            if ($item[0] === 'worksheets.php') {
                $icon = '<svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>';
            } elseif ($item[0] === 'question-bank.php') {
                $icon = '<svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>';
            } elseif ($item[0] === 'ai-worksheet.php') {
                $icon = '<svg class="w-4 h-4 shrink-0 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>';
            } elseif ($item[0] === 'ai-exam-app/') {
                $icon = '<svg class="w-4 h-4 shrink-0 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4M8 16h.01M16 16h.01"/></svg>';
            }
          ?>
            <a href="<?= htmlspecialchars($adminLinkPrefix . $item[0], ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-2.5 px-3 py-2.5 rounded-[10px] text-[14px] font-medium transition-colors <?= $isActive ? 'bg-pink-500 text-white shadow-[0_4px_12px_rgba(231,45,130,0.3)]' : 'text-[#aebbd0] hover:bg-white/10 hover:text-white' ?>">
              <?= $icon ?>
              <span><?= $item[1] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="p-4 border-t border-white/10 shrink-0">
    <a href="<?= htmlspecialchars($adminLinkPrefix, ENT_QUOTES, 'UTF-8') ?>../index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-[10px] text-[14px] font-medium text-[#aebbd0] hover:bg-white/10 hover:text-white transition-colors">
      ออกจากระบบ
    </a>
  </div>
</aside>
