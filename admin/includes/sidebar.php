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
  "ASSESSMENT" => [
    ["tests.php", "แบบทดสอบ"],
    ["live-sessions.php", "⚡ ห้องเรียนสด (Live)"],
    ["question-bank.php", "Question Bank"],
    ["ai-exam-app/", "🤖 AI สร้างข้อสอบ"]
  ],
  "OPERATIONS" => [
    ["students.php", "นักเรียน"],
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
  <div class="p-6 border-b border-white/10 shrink-0">
    <a href="<?= htmlspecialchars($adminLinkPrefix, ENT_QUOTES, 'UTF-8') ?>index.php" class="inline-flex items-center gap-3 w-full" aria-label="Next Beyond Admin">
      <svg class="w-[32px] h-[32px] shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" />
        <clipPath id="logo-clip-admin">
          <rect x="0" y="9" width="40" height="22" />
        </clipPath>
        <g clip-path="url(#logo-clip-admin)">
          <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#ffffff" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" />
        </g>
      </svg>
      <span class="grid leading-[1.15]">
        <strong class="text-white text-[14px] tracking-[0.04em]">NEXT BEYOND</strong>
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
            $isActive = $currentPage === $item[0];
          ?>
            <a href="<?= htmlspecialchars($adminLinkPrefix . $item[0], ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-[10px] text-[14px] font-medium transition-colors <?= $isActive ? 'bg-pink-500 text-white shadow-[0_4px_12px_rgba(231,45,130,0.3)]' : 'text-[#aebbd0] hover:bg-white/10 hover:text-white' ?>">
              <?= $item[1] ?>
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
