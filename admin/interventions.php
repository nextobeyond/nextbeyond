<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/phase5-mastery-service.php';

use NextBeyond\Mastery\AdaptiveConfigService;
use NextBeyond\Mastery\InterventionService;

\NextBeyond\Mastery\ensurePhase5Schema($pdo);

$pageTitle = 'คิวช่วยเหลือผู้เรียน';
$pageDesc = 'จัดลำดับ ติดตาม และนัดหมายการช่วยเหลือแบบรายบุคคล';
$currentPage = 'interventions.php';
$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'severity' => trim((string)($_GET['severity'] ?? '')),
    'course_id' => (int)($_GET['course_id'] ?? 0),
];

try {
    $queue = (new InterventionService($pdo))->queue($filters);
} catch (Throwable $e) {
    error_log('Interventions queue load error: ' . $e->getMessage());
    $queue = [];
}

try {
    $courses = $pdo->query("SELECT id,title FROM courses WHERE status='active' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('Interventions courses load error: ' . $e->getMessage());
    $courses = [];
}

try {
    $config = (new AdaptiveConfigService($pdo))->get();
} catch (Throwable $e) {
    error_log('Interventions config load error: ' . $e->getMessage());
    $config = [
        'mastery_threshold' => 80.0,
        'near_mastery_threshold' => 65.0,
        'developing_threshold' => 50.0,
        'minimum_evidence' => 8,
        'minimum_source_diversity' => 2,
        'recent_evidence_window' => 8,
        'remediation_retry_limit' => 2,
        'teacher_intervention_threshold' => 60.0,
        'regression_drop_threshold' => 20.0,
        'mastery_check_question_count' => 5,
        'blocking_gap_severity' => 'critical',
        'automation_policy' => 'teacher_approved',
        'retention_recheck_days' => 7,
    ];
}
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title><link rel="stylesheet" href="../assets/css/output.css?v=<?= @filemtime(__DIR__.'/../assets/css/output.css') ?: time() ?>"><script src="../assets/js/admin-guard.js"></script></head>
<body class="bg-[#f4f7fb] text-navy-950"><div class="min-h-screen flex"><?php include __DIR__.'/includes/sidebar.php'; ?><div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0"><?php include __DIR__.'/includes/topbar.php'; ?>
<main class="p-8 max-[640px]:p-4 max-w-[1500px] w-full mx-auto space-y-5">
  <section class="rounded-3xl bg-navy-950 text-white p-7 flex justify-between gap-5 flex-wrap"><div><p class="text-[11px] uppercase tracking-widest text-pink-300 font-black">Human intervention loop</p><h1 class="text-2xl font-black mt-1">คิวช่วยเหลือผู้เรียน</h1><p class="text-sm text-slate-300 mt-2">เรียงตามความเร่งด่วนจากหลักฐาน แนวโน้ม และจำนวนรอบการฝึก — ครูเป็นผู้ตัดสินใจเสมอ</p></div><div class="self-center px-4 py-3 rounded-2xl bg-white/10"><b class="text-2xl"><?= count($queue) ?></b><span class="block text-xs text-slate-300">รายการที่ติดตาม</span></div></section>
  <form class="bg-white rounded-2xl border p-4 flex gap-3 flex-wrap items-end"><label class="text-xs font-bold">สถานะ<select name="status" class="block mt-1 h-10 px-3 rounded-xl border"><option value="">ทั้งหมด</option><?php foreach(['recommended','planned','in_progress','monitoring','completed'] as $v): ?><option value="<?= $v ?>" <?= $filters['status']===$v?'selected':'' ?>><?= str_replace('_',' ',$v) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold">ระดับ<select name="severity" class="block mt-1 h-10 px-3 rounded-xl border"><option value="">ทั้งหมด</option><?php foreach(['critical','high','moderate','low'] as $v): ?><option value="<?= $v ?>" <?= $filters['severity']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold">คอร์ส<select name="course_id" class="block mt-1 h-10 px-3 rounded-xl border max-w-[300px]"><option value="0">ทุกคอร์ส</option><?php foreach($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $filters['course_id']==(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['title']) ?></option><?php endforeach; ?></select></label><button class="h-10 px-5 rounded-xl bg-pink-500 text-white font-bold text-sm">กรองคิว</button></form>
  <section class="bg-white rounded-2xl border overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4">Priority</th><th class="p-4">ผู้เรียน / คอร์ส</th><th class="p-4">หัวข้อและหลักฐาน</th><th class="p-4">การช่วยเหลือ</th><th class="p-4">สถานะ</th><th class="p-4 text-right">ดำเนินการ</th></tr></thead><tbody class="divide-y">
  <?php if(!$queue): ?><tr><td colspan="6" class="p-12 text-center text-slate-500">ยังไม่มีรายการในคิวตามตัวกรองนี้</td></tr><?php endif; ?>
  <?php foreach($queue as $item): ?><tr><td class="p-4"><b class="text-lg <?= (float)$item['priority_score']>=80?'text-rose-600':'text-amber-600' ?>"><?= round((float)$item['priority_score']) ?></b><span class="block mt-1 uppercase font-bold"><?= htmlspecialchars($item['severity']) ?></span></td><td class="p-4"><a class="font-black text-indigo-700" href="student-detail.php?id=<?= (int)$item['student_id'] ?>"><?= htmlspecialchars($item['student_name']) ?></a><span class="block text-slate-500 mt-1"><?= htmlspecialchars($item['course_title']) ?></span></td><td class="p-4"><b><?= htmlspecialchars($item['topic_name']) ?></b><?php if($item['skill_name']): ?><span class="block text-slate-500"><?= htmlspecialchars($item['skill_name']) ?></span><?php endif; ?><span class="block mt-1 text-slate-500"><?= (int)$item['evidence_count'] ?> หลักฐาน · <?= (int)$item['remediation_attempts'] ?> รอบฝึก</span></td><td class="p-4"><?= htmlspecialchars(str_replace('_',' ',(string)$item['recommended_action'])) ?></td><td class="p-4"><span class="px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 font-bold"><?= htmlspecialchars(str_replace('_',' ',(string)$item['status'])) ?></span><?php if($item['outcome']): ?><span class="block mt-2 text-emerald-700"><?= htmlspecialchars((string)$item['outcome']) ?></span><?php endif; ?></td><td class="p-4"><div class="flex justify-end gap-2"><button onclick="scheduleSupport(<?= (int)$item['id'] ?>)" class="px-3 py-2 rounded-lg bg-indigo-600 text-white font-bold">นัดหมาย</button><button onclick="recordOutcome(<?= (int)$item['id'] ?>)" class="px-3 py-2 rounded-lg border font-bold">บันทึกผล</button></div></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
  <?php if(($consoleUser['role']??'')==='admin'): ?><details class="bg-white rounded-2xl border p-5"><summary class="font-black cursor-pointer">ตั้งค่าเกณฑ์ Adaptive Learning</summary><form id="config-form" class="grid md:grid-cols-4 gap-3 mt-4 text-xs"><?php foreach(['mastery_threshold'=>'Mastery %','near_mastery_threshold'=>'Near mastery %','minimum_evidence'=>'หลักฐานขั้นต่ำ','minimum_source_diversity'=>'แหล่งหลักฐานขั้นต่ำ','remediation_retry_limit'=>'รอบฝึกก่อนส่งครู','teacher_intervention_threshold'=>'เกณฑ์ส่งครู %','regression_drop_threshold'=>'Regression drop %','mastery_check_question_count'=>'จำนวนข้อ Mastery Check'] as $key=>$label): ?><label class="font-bold"><?= $label ?><input name="<?= $key ?>" type="number" step="1" value="<?= htmlspecialchars((string)$config[$key]) ?>" class="block mt-1 w-full h-10 px-3 rounded-xl border"></label><?php endforeach; ?><label class="font-bold">นโยบายอัตโนมัติ<select name="automation_policy" class="block mt-1 w-full h-10 px-3 rounded-xl border"><?php foreach(['manual','teacher_approved','auto_assign'] as $v): ?><option <?= $config['automation_policy']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><button class="md:col-span-4 h-10 rounded-xl bg-navy-950 text-white font-bold">บันทึกค่าเกณฑ์</button></form></details><?php endif; ?>
</main></div></div>
<script>
async function api(payload){const r=await fetch('adaptive-learning-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}),d=await r.json();if(!r.ok||!d.success)throw new Error(d.error||d.detail||'ดำเนินการไม่สำเร็จ');return d}
async function scheduleSupport(id){const date=prompt('วันที่นัด (YYYY-MM-DD)',new Date(Date.now()+86400000).toISOString().slice(0,10));if(!date)return;const start=prompt('เวลาเริ่ม (HH:MM)','16:00');if(!start)return;const duration=prompt('ระยะเวลา (นาที)','30');try{const d=await api({action:'schedule_intervention',intervention_id:id,event_date:date,start_time:start,duration_minutes:Number(duration||30),mode:'online'});alert(`สร้างนัดและห้องเรียนแล้ว · PIN ${d.session_pin}`);location.reload()}catch(e){alert(e.message)}}
async function recordOutcome(id){const outcome=prompt('ผลลัพธ์: improved, needs_more_practice, needs_another_session, prerequisite_problem_found, resolved','improved');if(!outcome)return;const notes=prompt('บันทึกของครู','')||'';const payload={action:'update_intervention',intervention_id:id,status:outcome==='resolved'?'completed':'monitoring',outcome,teacher_notes:notes};if(outcome==='resolved')payload.resolution_reason=notes||'ครูประเมินหลังการช่วยเหลือแล้ว';try{await api(payload);location.reload()}catch(e){alert(e.message)}}
document.getElementById('config-form')?.addEventListener('submit',async e=>{e.preventDefault();const body=Object.fromEntries(new FormData(e.currentTarget));body.action='adaptive_config';try{await api(body);alert('บันทึกค่าเกณฑ์แล้ว')}catch(err){alert(err.message)}});
</script></body></html>
