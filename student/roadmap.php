<?php
$pageTitle = 'Study Roadmap';
$currentPage = 'roadmap.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/roadmap-service.php';

$requestedRoadmapId = (int)($_GET['id'] ?? 0);
$allRoadmaps = getStudentRoadmaps($pdo, (int)$currentUser['id']);

$currentRoadmap = null;
$tasks = [];
$progress = ['total' => 0, 'completed' => 0, 'percent' => 0];

if ($requestedRoadmapId > 0) {
    $currentRoadmap = getStudentRoadmap($pdo, (int)$currentUser['id'], $requestedRoadmapId);
    if ($currentRoadmap) {
        $tasks = getRoadmapTasks($pdo, $requestedRoadmapId, (int)$currentUser['id']);
        $progress = roadmapProgress($tasks);
    }
}

$categories = [];
foreach ($tasks as $task) {
    $categories[(string)$task['category']] = true;
}

$stageIcons = ['tcas' => '🎓', 'm4' => '📘', 'm1' => '📗'];
$labels = roadmapStageLabels();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <style>
    [hidden]{display:none!important}
    .roadmap-tab{display:inline-flex;align-items:center;min-height:40px;padding:0 15px;border:1px solid #dce3ec;border-radius:9px;background:#fff;color:#65738a;font-size:12px;font-weight:800;text-decoration:none}
    .roadmap-tab.active{border-color:#f54696;background:#f54696;color:#fff}
    .roadmap-filter{min-height:34px;padding:0 12px;border:1px solid #dce3ec;border-radius:999px;background:#fff;color:#65738a;font-size:11px;font-weight:700}
    .roadmap-filter.active{border-color:#f7a1c9;background:#fff0f7;color:#d72c7a}
    .roadmap-task{display:flex;align-items:center;gap:14px;padding:16px;border:1px solid #e3e8ef;border-radius:10px;background:#fff}
    .roadmap-task.done{background:#f8fafc}
    .roadmap-task.done .task-title{text-decoration:line-through;color:#94a3b8}
    .roadmap-check{width:20px;height:20px;accent-color:#f54696;cursor:pointer}
    .roadmap-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:6px;color:#718096;font-size:11px}
    .roadmap-chip{padding:3px 7px;border-radius:6px;background:#eef2f7;color:#4b5e7a;font-weight:700}
    .roadmap-points{margin-left:auto;color:#c97700;font-size:11px;font-weight:800;white-space:nowrap}
    .roadmap-choice-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:24px}
    .roadmap-choice{display:flex;flex-direction:column;min-height:260px;padding:24px;border:1px solid #dfe5ed;border-radius:12px;background:#fff;text-decoration:none;transition:.18s;position:relative}
    .roadmap-choice.enrolled{border-color:#b9d1ef;background:#f0f5fa}
    .roadmap-choice:hover{border-color:#f49ac3;box-shadow:0 8px 24px rgba(15,42,83,.07);transform:translateY(-2px)}
    .choice-icon{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:12px;background:#fff0f7;font-size:24px}
    .roadmap-choice h3{margin-top:18px;color:#061633;font-size:18px;font-weight:800}
    .roadmap-choice p{margin-top:8px;color:#65738a;font-size:12px;line-height:1.7}
    .choice-footer{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:20px;color:#d72c7a;font-size:12px;font-weight:800}
    .choice-banner{padding:32px;border:1px solid #17304f;border-radius:12px;background:#061633;color:#fff}
    .choice-banner h2{color:#fff;font-size:27px;font-weight:800}
    .choice-banner p{margin-top:9px;color:#b9c5d7;font-size:13px;line-height:1.7}
    .task-action-btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;border-radius:8px;font-size:11px;font-weight:800;color:#fff;background:#2369dd;text-decoration:none;transition:.15s}
    .task-action-btn:hover{background:#1a52b3}
    .task-locked{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#718096;background:#f1f5f9;padding:6px 10px;border-radius:6px}
    @media(max-width:900px){.roadmap-choice-grid{grid-template-columns:1fr}}
    @media(max-width:640px){
      .roadmap-task{position:relative;align-items:flex-start;padding:14px;border-radius:15px;box-shadow:0 5px 18px rgba(6,26,64,.045)}
      .roadmap-points{position:absolute;right:12px;bottom:10px;margin-left:0}.roadmap-tabs{display:grid!important;grid-template-columns:1fr}.roadmap-tab{justify-content:center}
      .choice-banner{position:relative;overflow:hidden;min-height:205px;padding:20px;border:0;border-radius:20px;background:linear-gradient(135deg,#061a40,#0b2d68 62%,#1548d8);box-shadow:0 12px 28px rgba(6,26,64,.16)}
      .choice-banner:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 88% 10%,rgba(56,105,245,.42),transparent 34%),repeating-radial-gradient(circle at 100% 0,transparent 0 22px,rgba(255,255,255,.045) 23px 24px);pointer-events:none}.choice-banner>*{position:relative;z-index:1}
      .choice-banner h2{font-size:24px;line-height:1.2;letter-spacing:-.04em}.choice-banner p{font-size:12px}
      .roadmap-choice-grid{gap:10px;margin-top:14px}.roadmap-choice{min-height:225px;padding:17px;border-radius:16px;box-shadow:0 5px 18px rgba(6,26,64,.045)}
      .roadmap-choice h3{margin-top:14px;font-size:17px}.choice-footer{padding-top:15px}.task-action-btn{flex:none}
    }
  </style>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="p-8 max-[640px]:p-4">
      <?php if (!$currentRoadmap): ?>
        <section class="choice-banner">
          <p class="student-kicker text-[#b9c5d7] mb-2">Choose your learning path</p>
          <h2>อยากได้ Roadmap แบบไหน?</h2>
          <p>เลือกระดับและเป้าหมายที่ตรงกับคุณก่อน ระบบจึงจะแสดงภารกิจที่ผู้ดูแลจัดเตรียมไว้<br>คุณสามารถกลับมาเปลี่ยน Roadmap ได้ทุกเมื่อ</p>
        </section>
        
        <div class="mt-8">
            <h3 class="text-[18px] font-bold text-navy-950">Roadmap ของฉัน</h3>
            <section class="roadmap-choice-grid mb-10" aria-label="My Roadmaps">
            <?php 
            $enrolled = array_filter($allRoadmaps, fn($r) => $r['enroll_status'] !== 'not_enrolled');
            if (empty($enrolled)): ?>
                <div class="col-span-full rounded-xl border border-[#dfe5ed] bg-white p-8 text-center text-[13px] text-[#718096]">
                    คุณยังไม่ได้เลือก Roadmap ใดๆ กรุณาเลือกจากรายการด้านล่าง
                </div>
            <?php else: foreach ($enrolled as $r): ?>
                <a class="roadmap-choice enrolled" href="roadmap.php?id=<?= $r['id'] ?>">
                <?php if($r['is_mandatory']): ?><div class="absolute top-4 right-4 bg-pink-100 text-pink-600 text-[10px] font-bold px-2 py-1 rounded">บังคับ</div><?php endif; ?>
                <span class="choice-icon"><?= $stageIcons[$r['stage']] ?? '📘' ?></span>
                <h3><?= htmlspecialchars($r['title']) ?></h3>
                <p><?= htmlspecialchars($r['description']) ?></p>
                <div class="choice-footer"><span class="text-blue-600">กำลังเรียน (<?= (int)$r['progress_percent'] ?>%)</span><span class="text-blue-600">เรียนต่อ →</span></div>
                </a>
            <?php endforeach; endif; ?>
            </section>
        </div>

        <div class="mt-8">
            <h3 class="text-[18px] font-bold text-navy-950">Roadmap แนะนำ</h3>
            <section class="roadmap-choice-grid" aria-label="Available Roadmaps">
            <?php 
            $available = array_filter($allRoadmaps, fn($r) => $r['enroll_status'] === 'not_enrolled');
            if (empty($available)): ?>
                <div class="col-span-full rounded-xl border border-[#dfe5ed] bg-white p-8 text-center text-[13px] text-[#718096]">ไม่มี Roadmap ใหม่ให้เลือกในขณะนี้</div>
            <?php else: foreach ($available as $r): ?>
                <div class="roadmap-choice">
                <span class="choice-icon"><?= $stageIcons[$r['stage']] ?? '📘' ?></span>
                <h3><?= htmlspecialchars($r['title']) ?></h3>
                <p><?= htmlspecialchars($r['description']) ?></p>
                <div class="choice-footer">
                    <span><?= htmlspecialchars($labels[$r['stage']]['badge'] ?? 'ทั่วไป') ?></span>
                    <button class="enroll-btn" data-id="<?= $r['id'] ?>">เริ่ม Roadmap นี้</button>
                </div>
                </div>
            <?php endforeach; endif; ?>
            </section>
        </div>

      <?php else: ?>
        <section class="rounded-xl bg-navy-950 p-7 text-white border border-[#17304f]">
          <div class="flex items-center justify-between gap-7 flex-wrap">
            <div><p class="student-kicker text-[#b9c5d7] mb-2"><?= htmlspecialchars($labels[$currentRoadmap['stage']]['badge'] ?? 'Roadmap') ?></p><h2 class="text-[26px] font-bold" style="color:#fff"><?= htmlspecialchars($currentRoadmap['title']) ?></h2><p class="mt-2 max-w-[650px] text-[13px] leading-6 text-[#b9c5d7]"><?= htmlspecialchars($currentRoadmap['description']) ?></p></div>
            <div class="min-w-[240px] rounded-xl border border-[#405270] bg-[#0d2346] p-4"><div class="flex justify-between text-[12px] font-bold"><span>ความคืบหน้า</span><strong id="progress-percent" class="text-pink-300"><?= $progress['percent'] ?>%</strong></div><div class="mt-3 h-2.5 overflow-hidden rounded-full bg-[#283d5d]"><div id="progress-bar" class="h-full rounded-full bg-pink-500" style="width:<?= $progress['percent'] ?>%"></div></div><div id="progress-detail" class="mt-2 text-[11px] text-[#b9c5d7]">สำเร็จ <?= $progress['completed'] ?> จาก <?= $progress['total'] ?> ภารกิจบังคับ</div></div>
          </div>
        </section>
        <nav class="roadmap-tabs mt-6 flex gap-2 flex-wrap" aria-label="Roadmap ปัจจุบัน">
          <a class="roadmap-tab" href="roadmap.php">← กลับไปหน้ารวม</a>
          <div class="roadmap-tab active"><?= htmlspecialchars($currentRoadmap['title']) ?></div>
        </nav>
        <div class="mt-6 flex items-center justify-between gap-4 flex-wrap"><div><h2 class="student-section-title text-[17px] font-bold">ภารกิจใน Roadmap</h2><p class="mt-1 text-[12px] text-[#718096]">ภารกิจจะสำเร็จเมื่อคุณทำตามเงื่อนไขที่กำหนดไว้</p></div><div class="flex gap-2 flex-wrap"><button class="roadmap-filter active" data-category="all">ทั้งหมด</button><?php foreach (array_keys($categories) as $category): ?><button class="roadmap-filter" data-category="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></button><?php endforeach; ?></div></div>
        <section id="roadmap-list" class="mt-4 space-y-3">
          <?php if (!$tasks): ?><div class="rounded-xl border border-[#dfe5ed] bg-white p-12 text-center text-[14px] text-[#718096]">ผู้ดูแลยังไม่ได้เพิ่มภารกิจใน Roadmap นี้</div><?php endif; ?>
          <?php foreach ($tasks as $task): 
            $isCompleted = $task['progress_status'] === 'completed' || $task['progress_status'] === 'exempted';
            $isManual = canCompleteManually($task);
            $isLocked = $task['progress_status'] === 'locked';
          ?>
            <article class="roadmap-task <?= $isCompleted ? 'done' : '' ?>" data-task-id="<?= (int)$task['id'] ?>" data-category="<?= htmlspecialchars($task['category']) ?>">
              <?php if ($isManual && !$isLocked): ?>
                <input class="roadmap-check" type="checkbox" <?= $isCompleted ? 'checked' : '' ?> aria-label="ทำภารกิจนี้สำเร็จ">
              <?php elseif ($isCompleted): ?>
                <div class="roadmap-check flex items-center justify-center text-pink-500"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>
              <?php else: ?>
                <div class="roadmap-check flex items-center justify-center text-[#cbd5e1]"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg></div>
              <?php endif; ?>
              
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h3 class="task-title text-[14px] font-bold text-navy-950"><?= htmlspecialchars($task['title']) ?></h3>
                    <?php if(!$task['is_required']): ?><span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-bold">เสริม</span><?php endif; ?>
                </div>
                <div class="roadmap-meta">
                    <span class="roadmap-chip"><?= htmlspecialchars($task['category']) ?></span>
                    <span><?= htmlspecialchars($task['subject']) ?></span>
                    <?php if ($task['due_date']): ?><span>กำหนด <?= date('d/m/Y', strtotime($task['due_date'])) ?></span><?php endif; ?>
                    
                    <?php if (!$isManual && !$isCompleted): ?>
                        <span class="text-blue-500 font-bold ml-1">
                            (<?= $task['completion_type'] === 'complete_lesson' ? 'ต้องเรียนให้จบ' : ($task['completion_type'] === 'pass_test' ? 'ต้องสอบให้ผ่าน '.(float)$task['pass_score'].'%' : 'ทำภารกิจอัตโนมัติ') ?>)
                        </span>
                    <?php endif; ?>
                </div>
              </div>
              
              <?php if ($isLocked): ?>
                <div class="task-locked" title="<?= htmlspecialchars($task['adaptive_lock_message'] ?? 'ทำภารกิจก่อนหน้าให้สำเร็จก่อน') ?>"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg><?= !empty($task['adaptive_lock']) ? 'รอขั้นตอนทบทวน' : 'ล็อกอยู่' ?></div>
              <?php elseif (!$isCompleted): ?>
                <?php if ($task['completion_type'] === 'complete_lesson' && $task['ref_lesson_id']): ?>
                    <a href="../lesson.php?id=<?= $task['ref_lesson_id'] ?>" class="task-action-btn">เข้าเรียน</a>
                <?php elseif (($task['completion_type'] === 'submit_test' || $task['completion_type'] === 'pass_test') && $task['ref_exam_id']): ?>
                    <a href="take-test.php?id=<?= $task['ref_exam_id'] ?>" class="task-action-btn">เริ่มทำข้อสอบ</a>
                <?php endif; ?>
              <?php endif; ?>

              <span class="roadmap-points">+<?= (int)$task['points_reward'] ?> NC</span>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>
<script>
<?php if ($currentRoadmap): ?>
const progress = {total: <?= $progress['total'] ?>, completed: <?= $progress['completed'] ?>};
function drawProgress(){const percent=progress.total?Math.round(progress.completed*100/progress.total):0;document.getElementById('progress-percent').textContent=percent+'%';document.getElementById('progress-bar').style.width=percent+'%';document.getElementById('progress-detail').textContent=`สำเร็จ ${progress.completed} จาก ${progress.total} ภารกิจบังคับ`}
document.querySelectorAll('.roadmap-check[type="checkbox"]').forEach(input=>input.addEventListener('change',async()=>{const row=input.closest('.roadmap-task'),completed=input.checked;input.disabled=true;try{const response=await fetch('roadmap-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'toggle',task_id:Number(row.dataset.taskId),is_completed:completed})});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'บันทึกไม่สำเร็จ');row.classList.toggle('done',completed);progress.completed=data.progress.completed;progress.total=data.progress.total;drawProgress()}catch(error){input.checked=!completed;alert(error.message)}finally{input.disabled=false}}));
document.querySelectorAll('.roadmap-filter').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.roadmap-filter').forEach(item=>item.classList.toggle('active',item===button));document.querySelectorAll('.roadmap-task').forEach(row=>row.hidden=button.dataset.category!=='all'&&row.dataset.category!==button.dataset.category)}));
<?php else: ?>
document.querySelectorAll('.enroll-btn').forEach(btn => btn.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!confirm('ยืนยันการเลือก Roadmap นี้?')) return;
    const id = btn.dataset.id;
    try {
        const response = await fetch('roadmap-api.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'enroll', roadmap_id: Number(id)}) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'ดำเนินการไม่สำเร็จ');
        window.location.href = 'roadmap.php?id=' + id;
    } catch (err) {
        alert(err.message);
    }
}));
<?php endif; ?>
</script>
</body>
</html>
