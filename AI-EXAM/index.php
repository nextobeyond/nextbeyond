<?php
/**
 * AI-EXAM/index.php — Fragment สำหรับ include ใน admin/ai-exam.php
 */
$apiPath = '../AI-EXAM/api.php';
$defaultExamSubjects = ['คณิตศาสตร์', 'วิทยาศาสตร์', 'ภาษาอังกฤษ', 'ภาษาไทย', 'สังคมศึกษา'];
$examSubjects = $defaultExamSubjects;
try {
    $subjectRows = $pdo->query(
        "SELECT DISTINCT subject FROM (
            SELECT subject FROM courses WHERE subject IS NOT NULL AND TRIM(subject) <> ''
            UNION
            SELECT subject FROM exams WHERE subject IS NOT NULL AND TRIM(subject) <> ''
        ) available_subjects ORDER BY subject"
    )->fetchAll(PDO::FETCH_COLUMN);
    $examSubjects = array_values(array_unique(array_merge(
        $defaultExamSubjects,
        array_filter(array_map('trim', $subjectRows))
    )));
} catch (Throwable $error) {
    error_log('AI exam subjects: ' . $error->getMessage());
}
?>

<style>
  .animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<!-- View: Generator Form -->
<div id="view-form" class="max-w-2xl mx-auto p-6 bg-white rounded-[20px] shadow-[0_4px_24px_rgba(15,42,83,0.03)] border border-[#e8ecf2] font-sans relative">
    
    <!-- Header with Settings Button -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-navy-950">คำสั่งทำใบงาน (AI Exam)</h2>
        <button id="settings-btn" onclick="openSettings()" class="text-[13px] font-bold text-[#65738a] hover:text-pink-500 flex items-center gap-1 transition-colors bg-[#f4f7fb] hover:bg-pink-50 px-3 py-1.5 rounded-lg border border-[#e8ecf2] hover:border-pink-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            ตั้งค่า API Key
        </button>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="hidden absolute inset-0 bg-white/95 backdrop-blur-sm flex-col items-center justify-center z-50 rounded-[20px] p-6 text-center animate-fade-in">
        <div class="relative mb-6">
            <div class="animate-spin rounded-full h-16 w-16 border-4 border-pink-100 border-t-pink-500"></div>
        </div>
        <h3 class="text-xl font-bold text-navy-950 mb-2">กำลังให้ AI สร้างข้อสอบ...</h3>
        <p class="text-pink-500 font-medium mb-1">กำลังรวบรวมข้อสอบคุณภาพสูงตามระดับความยาก</p>
        <p class="text-xs text-[#65738a]">อาจใช้เวลา 10-40 วินาที ขึ้นอยู่กับจำนวนข้อที่กำหนด</p>
    </div>

    <form id="generate-form" class="space-y-5" onsubmit="handleGenerate(event)">
        <!-- Subject -->
        <div class="relative">
            <label for="examSubject" class="absolute -top-2 left-3 bg-white px-1 text-xs text-pink-500 font-bold z-10">วิชาเรียน *</label>
            <select id="examSubject" required class="w-full p-3 border-2 border-[#e8ecf2] focus:border-pink-500 rounded-xl outline-none appearance-none bg-white text-navy-950 font-medium transition-colors cursor-pointer">
                <option value="">เลือกวิชาเรียน</option>
                <?php foreach ($examSubjects as $subject): ?>
                    <option value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-[#65738a]">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" /></svg>
            </div>
        </div>

        <!-- Exam Type -->
        <div class="relative">
            <label class="absolute -top-2 left-3 bg-white px-1 text-xs text-pink-500 font-bold z-10">ประเภทการสร้าง</label>
            <select id="examType" onchange="toggleType()" class="w-full p-3 border-2 border-[#e8ecf2] focus:border-pink-500 rounded-xl outline-none appearance-none bg-transparent relative z-0 text-navy-950 font-medium transition-colors cursor-pointer">
                <option value="copy">copy (คัดลอกต้นฉบับ)</option>
                <option value="similar">similar (คล้ายคลึงต้นฉบับ)</option>
                <option value="levels">levels (กำหนดจำนวนข้อในแต่ละระดับ)</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-[#65738a]">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" /></svg>
            </div>
        </div>

        <!-- Levels UI -->
        <div id="levels-ui" class="hidden bg-pink-50/50 p-5 rounded-xl border border-pink-100 animate-fade-in space-y-4">
            <h3 class="font-bold text-navy-950 text-[14px]">กำหนดจำนวนข้อในแต่ละระดับความยาก</h3>
            <div class="grid grid-cols-2 gap-4 max-[480px]:grid-cols-1">
                <div class="flex items-center space-x-3"><label class="text-[13px] font-medium text-[#65738a] w-20">ง่าย:</label><input type="number" min="0" max="100" value="0" id="lvl-easy" oninput="updateTotal()" class="w-full p-2.5 border border-[#e8ecf2] rounded-lg focus:border-pink-500 outline-none text-navy-950 font-medium" /></div>
                <div class="flex items-center space-x-3"><label class="text-[13px] font-medium text-[#65738a] w-20">ปานกลาง:</label><input type="number" min="0" max="100" value="0" id="lvl-medium" oninput="updateTotal()" class="w-full p-2.5 border border-[#e8ecf2] rounded-lg focus:border-pink-500 outline-none text-navy-950 font-medium" /></div>
                <div class="flex items-center space-x-3"><label class="text-[13px] font-medium text-[#65738a] w-20">ยาก:</label><input type="number" min="0" max="100" value="0" id="lvl-hard" oninput="updateTotal()" class="w-full p-2.5 border border-[#e8ecf2] rounded-lg focus:border-pink-500 outline-none text-navy-950 font-medium" /></div>
                <div class="flex items-center space-x-3"><label class="text-[13px] font-medium text-[#65738a] w-20">ยากมาก:</label><input type="number" min="0" max="100" value="0" id="lvl-expert" oninput="updateTotal()" class="w-full p-2.5 border border-[#e8ecf2] rounded-lg focus:border-pink-500 outline-none text-navy-950 font-medium" /></div>
            </div>
            <div class="text-right text-[13px] font-bold text-pink-600 pt-3 border-t border-pink-100 mt-2">รวมทั้งหมด: <span id="total-levels">0</span> ข้อ</div>
        </div>

        <!-- Normal Count UI -->
        <div id="normal-count-ui">
            <input type="number" id="qCount" min="1" max="100" placeholder="จำนวนข้อ (1-100 ข้อ)" value="10" class="w-full p-3 bg-white border-2 border-[#e8ecf2] rounded-xl outline-none focus:border-pink-500 text-navy-950 font-medium transition-colors" />
        </div>

        <!-- Shuffle -->
        <label class="flex items-center space-x-3 cursor-pointer p-4 bg-[#f8fafc] rounded-xl border border-[#e8ecf2] hover:border-pink-300 transition-colors">
            <input type="checkbox" id="shuffle" class="w-5 h-5 text-pink-500 border-gray-300 rounded focus:ring-pink-500 focus:ring-offset-0" />
            <span class="text-navy-950 font-bold text-[14px]">สลับข้อสอบ (Shuffle) <span class="text-[12px] font-medium text-[#65738a] ml-1 block sm:inline">สุ่มลำดับข้อหลังจากสร้างเสร็จ</span></span>
        </label>

        <!-- Details -->
        <div>
            <textarea id="details" placeholder="รายละเอียดเพิ่มเติม (เช่น เน้นคำนวณ 50%, หรือเน้นทฤษฎีบทที่ 2)" rows="3" class="w-full p-3 bg-white border-2 border-[#e8ecf2] rounded-xl outline-none focus:border-pink-500 resize-none text-navy-950 font-medium transition-colors"></textarea>
        </div>

        <!-- Google Drive URL -->
        <div class="pt-2">
            <div class="flex justify-between items-center mb-2">
                <h3 class="font-bold text-navy-950 text-[14px]">ลิงก์ไฟล์ Google Drive (Docs / PDF)</h3>
                <span class="text-[11px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-md uppercase tracking-wider">แชร์สิทธิ์: Anyone with the link</span>
            </div>
            <div class="flex bg-white border-2 border-[#e8ecf2] rounded-xl overflow-hidden focus-within:border-pink-500 transition-colors">
                <input type="text" id="driveUrl" placeholder="https://docs.google.com/document/d/... หรือ drive.google.com/file/d/..." class="flex-1 p-3 bg-transparent outline-none text-navy-950 font-medium" />
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end pt-5 border-t border-[#e8ecf2] mt-5">
            <button type="submit" id="btn-submit" class="px-8 py-3 bg-pink-500 text-white font-bold rounded-xl hover:bg-pink-600 shadow-[0_4px_12px_rgba(231,45,130,0.3)] transition-all w-full sm:w-auto text-[15px]">บันทึกและสร้างข้อสอบ</button>
        </div>
    </form>
</div>

<!-- View: Take Exam -->
<div id="view-exam" class="hidden max-w-3xl mx-auto font-sans pb-12 animate-fade-in">
    <button onclick="goHome()" class="mb-5 flex items-center text-[#65738a] hover:text-navy-950 font-bold transition-colors text-[14px]">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        กลับไปตั้งค่าสร้างข้อสอบ
    </button>

    <div class="bg-white rounded-[20px] shadow-[0_4px_24px_rgba(15,42,83,0.03)] border border-[#e8ecf2] p-6 mb-6">
        <h2 class="text-2xl font-bold text-navy-950 mb-3">แบบทดสอบจาก AI</h2>
        <div class="flex flex-wrap gap-2" id="exam-meta"></div>
        <div id="exam-saved-notice" class="hidden mt-4 p-3 rounded-xl bg-[#eff6ff] border border-[#bfdbfe] text-[#1d4ed8] text-[13px] font-bold"></div>
    </div>

    <!-- Results Banner -->
    <div id="exam-result" class="hidden bg-[#f0fdf4] border border-[#bbf7d0] rounded-[20px] p-6 mb-6 text-center animate-fade-in">
        <h3 class="text-xl font-bold text-[#166534] mb-2">ส่งคำตอบเรียบร้อยแล้ว!</h3>
        <p class="text-[#15803d] font-medium mb-2">คุณทำคะแนนได้</p>
        <div class="text-5xl font-black text-[#16a34a] mb-2 tracking-tighter" id="score-display">0 / 0</div>
    </div>

    <div id="exam-view-mode" class="hidden bg-[#fffbeb] border border-[#fde68a] rounded-[20px] p-6 mb-6 text-center animate-fade-in">
        <h3 class="text-xl font-bold text-[#854d0e] mb-2">โหมดดูเฉลย</h3>
        <p class="text-[#a16207] font-medium text-[14px]">ระบบแสดงคำตอบที่ถูกต้องพร้อมคำอธิบายให้แล้วด้านล่าง</p>
    </div>

    <!-- Questions Container -->
    <div id="questions-container" class="space-y-6"></div>

    <!-- Actions Before Submit -->
    <div id="exam-actions" class="mt-10 flex flex-col sm:flex-row justify-center items-center gap-4">
        <button onclick="submitExam()" class="px-8 py-3.5 bg-pink-500 text-white font-bold rounded-xl hover:bg-pink-600 shadow-[0_4px_12px_rgba(231,45,130,0.3)] transition-all transform hover:-translate-y-1 w-full sm:w-auto text-[15px]">ส่งคำตอบและตรวจข้อสอบ</button>
        <button onclick="showAnswersOnly()" class="px-8 py-3.5 bg-[#f59e0b] text-white font-bold rounded-xl hover:bg-[#d97706] shadow-sm transition-all w-full sm:w-auto text-[15px]">ดูเฉลยทั้งหมด</button>
        <button onclick="downloadPDF()" class="px-6 py-3.5 bg-navy-950 text-white font-bold rounded-xl hover:bg-navy-800 shadow-sm transition-all w-full sm:w-auto flex items-center justify-center text-[15px]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg> โหลด PDF
        </button>
    </div>
    
    <!-- Actions After Submit -->
    <div id="exam-actions-done" class="hidden mt-10 flex flex-col sm:flex-row justify-center items-center gap-4 animate-fade-in">
        <button onclick="goHome()" class="px-8 py-3.5 bg-[#f8fafc] text-navy-950 font-bold border border-[#e8ecf2] rounded-xl hover:bg-[#e8ecf2] transition-colors w-full sm:w-auto text-[15px]">กลับหน้าตั้งค่า</button>
        <button onclick="downloadPDF()" class="px-6 py-3.5 bg-navy-950 text-white font-bold rounded-xl hover:bg-navy-800 shadow-sm transition-all w-full sm:w-auto flex items-center justify-center text-[15px]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg> โหลด PDF
        </button>
    </div>
</div>

<!-- Settings Modal -->
<div id="settings-modal" class="hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-navy-950/40 backdrop-blur-sm" onclick="closeSettings()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-[20px] text-left overflow-hidden shadow-[0_10px_40px_rgba(15,42,83,0.1)] transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-[#e8ecf2]">
            <div class="px-6 pt-6 pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-pink-50 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-5 w-5 text-pink-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-bold text-navy-950">ตั้งค่าระบบ (Settings)</h3>
                        <div class="mt-4">
                            <label class="block text-[13px] font-bold text-[#65738a] mb-2 uppercase tracking-wide">Gemini API Key</label>
                            <div id="api-key-status" class="hidden mb-3 rounded-xl border px-3 py-2.5 text-[13px] font-bold"></div>
                            <input type="password" id="api-key-input" class="w-full p-3 border-2 border-[#e8ecf2] rounded-xl focus:border-pink-500 outline-none transition-colors text-navy-950 font-medium" placeholder="AIzaSy..." />
                            <p class="mt-2 text-[12px] text-[#8e9baf] font-medium leading-relaxed">ระบบไม่แสดง API Key ตัวเต็มกลับมาที่เบราว์เซอร์ หากต้องการเปลี่ยนให้กรอก Key ใหม่แล้วกดบันทึก</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-[#f8fafc] sm:flex sm:flex-row-reverse border-t border-[#e8ecf2]">
                <button type="button" onclick="saveSettings()" class="w-full inline-flex justify-center rounded-xl shadow-[0_4px_12px_rgba(231,45,130,0.2)] px-6 py-2.5 bg-pink-500 text-[14px] font-bold text-white hover:bg-pink-600 sm:ml-3 sm:w-auto transition-colors">บันทึก</button>
                <button type="button" onclick="closeSettings()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-[#e8ecf2] px-6 py-2.5 bg-white text-[14px] font-bold text-navy-950 hover:bg-[#f4f7fb] sm:mt-0 sm:ml-3 sm:w-auto transition-colors">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>

<script>
  // Pass API_URL from PHP to JavaScript
  const API_URL = '<?= $apiPath ?>';
</script>
<script src="../assets/js/admin-ai-exam.js?v=<?= rawurlencode((string) filemtime(__DIR__ . '/../assets/js/admin-ai-exam.js')) ?>"></script>
