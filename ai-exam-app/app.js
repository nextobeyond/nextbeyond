// ============================================================
//  ai-exam-app/app.js — โปรแกรมสร้างข้อสอบ AI แบบแยกโฟลเดอร์
//  Key ถูกเข้ารหัส AES-256 และเก็บในฐานข้อมูลฝั่ง Server
// ============================================================

"use strict";

// ---------- State ----------
let currentExam  = null;
let pendingExamSave = null;
let savingExam = false;
let answers      = {};
let isRevealed   = false;
let _serverKeyConfigured = false;   // true หากมี key ใน DB แล้ว

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);
}

// ---------- Settings API (server-side) ----------
const SETTINGS_API = 'settings-api.php';

async function loadSettingsStatus() {
    try {
        const res  = await fetch(SETTINGS_API, { cache: 'no-store' });
        const data = await res.json();
        _serverKeyConfigured = !!data.configured;
        return data;          // { configured: bool, maskedKey: string|null }
    } catch (e) {
        _serverKeyConfigured = false;
        return { configured: false, maskedKey: null };
    }
}

// ---------- Settings Modal ----------
async function openSettings() {
    const modal      = document.getElementById('settings-modal');
    const statusDiv  = document.getElementById('api-key-status');
    const input      = document.getElementById('api-key-input');

    // Reset input
    input.value       = '';
    input.placeholder = 'AIzaSy...';
    statusDiv.classList.add('hidden');
    statusDiv.textContent = '';

    modal.classList.remove('hidden');

    // โหลดสถานะจาก server
    const status = await loadSettingsStatus();
    if (status.configured && status.maskedKey) {
        statusDiv.className = 'mb-3 rounded-xl border px-3 py-2.5 text-[13px] font-bold flex items-center gap-2 bg-[#f0fdf4] border-[#bbf7d0] text-[#15803d]';
        statusDiv.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            บันทึกในฐานข้อมูลแล้ว (${status.maskedKey})
        `;
        statusDiv.classList.remove('hidden');
        input.value = status.maskedKey;
        input.dataset.maskedValue = status.maskedKey;
        input.addEventListener('focus', () => {
            if (input.value === input.dataset.maskedValue) input.value = '';
        }, { once: true });
    }
}

function closeSettings() {
    document.getElementById('settings-modal').classList.add('hidden');
}

async function saveSettings() {
    const input   = document.getElementById('api-key-input');
    const key     = input.value.trim();
    const saveBtn = document.querySelector('#settings-modal button[onclick="saveSettings()"]');

    if (key && key === input.dataset.maskedValue) {
        closeSettings();
        return;
    }

    if (!key) {
        // ถ้าไม่ได้กรอกอะไรและมี key อยู่แล้ว → ปิด modal เฉยๆ
        if (_serverKeyConfigured) { closeSettings(); return; }
        alert('กรุณากรอก Gemini API Key ก่อนบันทึก');
        return;
    }

    // UI: loading state
    const oldText = saveBtn.textContent;
    saveBtn.disabled    = true;
    saveBtn.textContent = 'กำลังบันทึก...';

    try {
        const res  = await fetch(SETTINGS_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ apiKey: key }),
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.error || 'บันทึกไม่สำเร็จ');

        _serverKeyConfigured = true;
        input.value          = '';
        closeSettings();

        // อัปเดต badge ปุ่ม settings
        updateSettingsBadge(true);

    } catch (err) {
        alert('❌ ' + err.message);
    } finally {
        saveBtn.disabled    = false;
        saveBtn.textContent = oldText;
    }
}

// อัปเดต badge สถานะบนปุ่ม "ตั้งค่า API Key"
function updateSettingsBadge(configured) {
    const btn = document.getElementById('settings-btn');
    if (!btn) return;
    const badge = btn.querySelector('#settings-badge');
    if (configured) {
        if (!badge) {
            const b = document.createElement('span');
            b.id        = 'settings-badge';
            b.className = 'w-2 h-2 rounded-full bg-[#22c55e] inline-block';
            btn.prepend(b);
        }
    } else {
        if (badge) badge.remove();
    }
}

// ---------- View Routing ----------
function goHome() {
    if (pendingExamSave && !confirm("ข้อสอบยังไม่บันทึก ต้องการกลับและทิ้งข้อสอบชุดนี้หรือไม่?")) return;
    pendingExamSave = null;
    document.getElementById('view-form').classList.remove('hidden');
    document.getElementById('view-exam').classList.add('hidden');
    currentExam = null;
    answers     = {};
    isRevealed  = false;
}

function toggleSourceMode() {
    const brief = document.getElementById('sourceMode').value === 'brief';
    document.getElementById('document-source-ui').classList.toggle('hidden', brief);
    document.getElementById('driveUrl').required = !brief;
    document.getElementById('driveUrl').disabled = brief;
    document.getElementById('examTopic').required = brief;
    document.getElementById('examGrade').required = brief;
    const type = document.getElementById('examType');
    type.querySelector('[value="copy"]').disabled = brief;
    type.querySelector('[value="copy"]').hidden = brief;
    type.querySelector('[value="similar"]').textContent = brief ? 'สร้างใหม่ตามหัวข้อ' : 'similar (คล้ายคลึงต้นฉบับ)';
    if (brief && type.value === 'copy') type.value = 'similar';
    document.getElementById('source-mode-help').textContent = brief
        ? 'ระบุหัวข้อและชั้นเรียนได้เลย ระบบมีตัวอย่างรายวิชาประกอบการสร้าง พร้อมตรวจทานโจทย์และเฉลย'
        : 'คัดลอกหรือสร้างตามแนวเอกสาร โดยเปิดแชร์ Google Drive ให้ทุกคนที่มีลิงก์';
    toggleType();
}

function toggleType() {
    const type = document.getElementById('examType').value;
    document.getElementById('difficulty-ui').classList.toggle('hidden', type === 'levels' || document.getElementById('sourceMode').value !== 'brief');
    const shuffle = document.getElementById('shuffle');
    shuffle.disabled = type === 'copy';
    if (type === 'copy') shuffle.checked = false;
    if (type === 'levels') {
        document.getElementById('levels-ui').classList.remove('hidden');
        document.getElementById('normal-count-ui').classList.add('hidden');
    } else {
        document.getElementById('levels-ui').classList.add('hidden');
        document.getElementById('normal-count-ui').classList.remove('hidden');
    }
}

function updateTotal() {
    const e  = parseInt(document.getElementById('lvl-easy').value)   || 0;
    const m  = parseInt(document.getElementById('lvl-medium').value) || 0;
    const h  = parseInt(document.getElementById('lvl-hard').value)   || 0;
    const ex = parseInt(document.getElementById('lvl-expert').value) || 0;
    document.getElementById('total-levels').innerText = e + m + h + ex;
}

async function saveGeneratedExam() {
    if (!pendingExamSave || savingExam) return;
    savingExam = true;
    const notice = document.getElementById('exam-saved-notice');
    notice.classList.remove('hidden');
    notice.textContent = 'กำลังบันทึกข้อสอบ...';
    try {
        const data = await apiRequest('exams-api.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(pendingExamSave),
        });
        if (!data.examId) throw new Error('เซิร์ฟเวอร์ไม่ยืนยันรหัสข้อสอบ');
        pendingExamSave = null;
        const id = encodeURIComponent(data.examId);
        notice.innerHTML = `บันทึกข้อสอบ #${escapeHtml(data.examId)} เป็นฉบับร่างแล้ว ·
            <a class="underline" href="../admin/tests?created=${id}" target="_blank" rel="noopener">เปิดแบบทดสอบ</a> ·
            <a class="underline" href="../admin/question-bank?exam=${id}" target="_blank" rel="noopener">เปิดและแก้ไขคลังข้อสอบ</a>`;
    } catch (error) {
        notice.innerHTML = `ยังไม่ได้ยืนยันการบันทึก ข้อสอบยังอยู่ในหน้านี้: ${escapeHtml(error.message)}
            <button type="button" class="underline" onclick="saveGeneratedExam()">ลองบันทึกใหม่</button>`;
    } finally {
        savingExam = false;
    }
}
window.addEventListener('beforeunload', event => {
    if (pendingExamSave) { event.preventDefault(); event.returnValue = ''; }
});

// ---------- Form Submit — Generate ----------
async function handleGenerate(e) {
    e.preventDefault();

    const subject = document.getElementById('examSubject').value.trim();
    if (!subject) {
        alert('กรุณาเลือกวิชาเรียน');
        document.getElementById('examSubject').focus();
        return;
    }

    // ตรวจสอบว่ามี API Key ใน server ไหม
    const status = await loadSettingsStatus();
    if (!status.configured) {
        alert('กรุณาตั้งค่า Gemini API Key ในเมนูตั้งค่า (ไอคอนฟันเฟือง) ก่อนเริ่มสร้างข้อสอบ');
        openSettings();
        return;
    }

    const driveUrl = document.getElementById('driveUrl').value.trim();
    const sourceMode = document.getElementById('sourceMode').value;
    const topic = document.getElementById('examTopic').value.trim();
    if (sourceMode === 'brief' && (!topic || !document.getElementById('examGrade').value.trim())) {
        alert('กรุณาระบุหัวข้อและระดับชั้น');
        return;
    }
    if (sourceMode === 'document' && !driveUrl) {
        alert('กรุณาใส่ลิงก์ Google Drive (ต้องตั้งค่าการแชร์เป็น Anyone with the link)');
        return;
    }

    const type = document.getElementById('examType').value;
    let finalCount = parseInt(document.getElementById('qCount').value) || 10;
    let countsObj  = null;

    if (type === 'levels') {
        countsObj = {
            easy:   parseInt(document.getElementById('lvl-easy').value)   || 0,
            medium: parseInt(document.getElementById('lvl-medium').value) || 0,
            hard:   parseInt(document.getElementById('lvl-hard').value)   || 0,
            expert: parseInt(document.getElementById('lvl-expert').value) || 0,
        };
        finalCount = Object.values(countsObj).reduce((a, b) => a + b, 0);
        if (finalCount === 0) {
            alert('กรุณาระบุจำนวนข้อสอบอย่างน้อย 1 ระดับ');
            return;
        }
    }

    if (finalCount < 1 || finalCount > 100) {
        alert('จำนวนข้อสอบต้องอยู่ระหว่าง 1 ถึง 100 ข้อ');
        return;
    }

    const details = document.getElementById('details').value;
    const grade = document.getElementById('examGrade').value.trim() || 'อิงตามต้นฉบับ';
    const difficulty = sourceMode === 'brief' ? document.getElementById('examDifficulty').value : '';
    const shuffle = document.getElementById('shuffle').checked;

    const overlay  = document.getElementById('loading-overlay');
    const submitBtn = document.getElementById('btn-submit');
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    submitBtn.disabled = true;

    try {
        // ส่ง useServerKey: true → API จะดึง key จาก DB เอง
        const res = await fetch(API_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                url:          sourceMode === 'document' ? driveUrl : '',
                sourceMode, topic, difficulty,
                type:         type,
                count:        finalCount,
                counts:       countsObj,
                details:      details,
                shuffle:      shuffle,
                subject:      subject,
                grade:        grade,
                useServerKey: true,   // ← ใช้ key จาก server DB
            }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์');
        if (!data.questions || data.questions.length === 0)
            throw new Error(sourceMode === 'brief'
                ? 'AI ไม่สามารถสร้างข้อสอบจากหัวข้อนี้ได้ กรุณาเพิ่มคำอธิบายหรือลองใหม่'
                : 'AI ไม่สามารถสร้างข้อสอบได้ โปรดตรวจสอบเอกสารต้นฉบับ');
        if (data.warning) alert(data.warning);

        currentExam = data.questions;
        answers     = {};
        isRevealed  = false;

        const warning = document.getElementById('exam-generation-warning');
        warning.textContent = data.warning || '';
        warning.classList.toggle('hidden', !data.warning);
        pendingExamSave = {
            requestId: typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : Array.from(crypto.getRandomValues(new Uint8Array(18)), b => b.toString(16).padStart(2, '0')).join(''),
            questions: currentExam,
            title: (topic ? `${topic} — ${grade}` : `แบบทดสอบ${subject}จาก AI ${new Date().toLocaleDateString('th-TH')}`).slice(0, 300),
            subject, grade, topic: topic || details.slice(0, 300), sourceMode, generationMode: type, sourceUrl: sourceMode === 'document' ? driveUrl : '',
            difficulty: type === 'levels' ? 'mixed' : difficulty || null,
        };
        await saveGeneratedExam();

        document.getElementById('exam-meta').innerHTML = `
            <span class="bg-pink-50 text-pink-600 font-bold text-[11px] px-2.5 py-1 rounded-md tracking-wide uppercase">ประเภท: ${sourceMode === 'brief' ? (type === 'levels' ? 'จากหัวข้อ · แยกระดับ' : 'จากหัวข้อ') : type}</span>
            <span class="bg-blue-50 text-blue-700 font-bold text-[11px] px-2.5 py-1 rounded-md tracking-wide">วิชา: ${escapeHtml(subject)}</span>
            <span class="bg-[#f4f7fb] text-[#65738a] font-bold text-[11px] px-2.5 py-1 rounded-md tracking-wide">จำนวน: ${currentExam.length} ข้อ</span>
        `;

        renderExam();

        document.getElementById('view-form').classList.add('hidden');
        document.getElementById('view-exam').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });


    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        submitBtn.disabled = false;
    }
}

// ---------- Exam Rendering ----------
function renderExam() {
    const container = document.getElementById('questions-container');
    container.innerHTML = '';

    currentExam.forEach((q, qIndex) => {
        const isCorrect = answers[qIndex] === q.correctAnswerIndex;
        const qDiv      = document.createElement('div');
        qDiv.className  = 'bg-white rounded-[20px] shadow-[0_4px_24px_rgba(15,42,83,0.03)] border border-[#e8ecf2] p-6';

        let html = `<div class="text-[16px] font-bold text-navy-950 mb-5 whitespace-pre-wrap leading-relaxed"><span class="font-black mr-2 text-pink-500">ข้อ ${qIndex + 1}.</span>${escapeHtml(q.questionText)}</div><div class="space-y-3">`;

        q.options.forEach((opt, oIndex) => {
            const isSelected     = answers[qIndex] === oIndex;
            const isActualAnswer = q.correctAnswerIndex === oIndex;

            let optClass    = 'flex items-center p-3.5 rounded-xl border-2 transition-all cursor-pointer ';
            let markerClass = 'w-5 h-5 rounded-full border-2 mr-3.5 flex items-center justify-center shrink-0 transition-colors ';
            let markerInner = '';

            if (!isRevealed) {
                optClass    += isSelected ? 'border-pink-500 bg-pink-50/50' : 'border-[#e8ecf2] hover:border-pink-300 hover:bg-[#f8fafc]';
                markerClass += isSelected ? 'border-pink-500' : 'border-[#cbd5e1]';
                if (isSelected) markerInner = '<div class="w-2.5 h-2.5 bg-pink-500 rounded-full"></div>';
            } else {
                optClass += ' cursor-default ';
                if (isActualAnswer) {
                    optClass    += 'border-[#22c55e] bg-[#f0fdf4] text-[#166534] font-bold';
                    markerClass += 'border-[#22c55e] bg-[#22c55e] text-white';
                    markerInner  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>';
                } else if (isSelected && !isCorrect) {
                    optClass    += 'border-[#ef4444] bg-[#fef2f2] text-[#b91c1c]';
                    markerClass += 'border-[#ef4444] bg-[#ef4444] text-white';
                    markerInner  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>';
                } else {
                    optClass    += 'border-[#e8ecf2] opacity-50';
                    markerClass += 'border-[#cbd5e1]';
                }
            }

            html += `<div class="${optClass}" onclick="selectOption(${qIndex}, ${oIndex})"><div class="${markerClass}">${markerInner}</div><span class="flex-1 text-[14px] font-medium leading-snug ${isRevealed && isActualAnswer ? 'text-[#166534]' : 'text-navy-950'}">${escapeHtml(opt)}</span></div>`;
        });

        html += '</div>';

        if (isRevealed && q.explanation) {
            html += `
            <div class="mt-5 p-4.5 bg-[#f8fafc] rounded-xl border border-[#e8ecf2]">
              <h4 class="flex items-center font-bold text-navy-950 mb-1.5 text-[14px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5 text-[#65738a]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                คำอธิบายเฉลย
              </h4>
              <p class="text-[#65738a] text-[13px] font-medium whitespace-pre-wrap leading-relaxed ml-[26px]">${escapeHtml(q.explanation)}</p>
            </div>`;
        }

        qDiv.innerHTML = html;
        container.appendChild(qDiv);
    });

    // action buttons
    if (isRevealed) {
        document.getElementById('exam-actions').classList.add('hidden');
        document.getElementById('exam-actions').classList.remove('flex');
        document.getElementById('exam-actions-done').classList.remove('hidden');
        document.getElementById('exam-actions-done').classList.add('flex');
    } else {
        document.getElementById('exam-actions').classList.remove('hidden');
        document.getElementById('exam-actions').classList.add('flex');
        document.getElementById('exam-actions-done').classList.add('hidden');
        document.getElementById('exam-actions-done').classList.remove('flex');
        document.getElementById('exam-result').classList.add('hidden');
        document.getElementById('exam-view-mode').classList.add('hidden');
    }
}

function selectOption(qIndex, oIndex) {
    if (isRevealed) return;
    answers[qIndex] = oIndex;
    renderExam();
}

function submitExam() {
    if (Object.keys(answers).length < currentExam.length) {
        if (!confirm('คุณยังทำข้อสอบไม่ครบทุกข้อ ต้องการส่งคำตอบใช่หรือไม่?')) return;
    }
    let correct = 0;
    currentExam.forEach((q, index) => {
        if (answers[index] === q.correctAnswerIndex) correct++;
    });
    document.getElementById('score-display').innerText = `${correct} / ${currentExam.length}`;
    document.getElementById('exam-result').classList.remove('hidden');
    isRevealed = true;
    renderExam();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showAnswersOnly() {
    if (!confirm('คุณต้องการเปิดดูเฉลยทั้งหมดโดยไม่บันทึกคะแนนใช่หรือไม่?')) return;
    document.getElementById('exam-view-mode').classList.remove('hidden');
    isRevealed = true;
    renderExam();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function downloadPDF() {
    const element        = document.getElementById('view-exam');
    const examActions    = document.getElementById('exam-actions');
    const examActionsDone = document.getElementById('exam-actions-done');
    const backBtn        = element.querySelector('button[onclick="goHome()"]');
    const opt = {
        margin:      10,
        filename:    'AI_Exam_NextBeyond.pdf',
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
    };
    if (examActions)    examActions.style.display    = 'none';
    if (examActionsDone) examActionsDone.style.display = 'none';
    if (backBtn)        backBtn.style.display        = 'none';
    const oldTitle  = document.title;
    document.title  = 'Generating PDF...';
    html2pdf().set(opt).from(element).save().then(() => {
        if (examActions)    examActions.style.display    = '';
        if (examActionsDone) examActionsDone.style.display = '';
        if (backBtn)        backBtn.style.display        = '';
        document.title = oldTitle;
    });
}

// ---------- Init: โหลดสถานะ Key เมื่อหน้าโหลด ----------
(async () => {
    const status = await loadSettingsStatus();
    _serverKeyConfigured = status.configured;
    updateSettingsBadge(status.configured);
})();

// --- AI Prompts Management ---
let aiPrompts = [];
const esc = (v) => String(v ?? "").replace(/[&<>'"]/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"}[c]));

async function apiRequest(url, options = {}) {
    const response = await fetch(url, options);
    const text = await response.text();
    let data = {};
    try {
        data = JSON.parse(text);
    } catch(e) {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ` + text.substring(0, 100));
        } else {
            throw new Error('Invalid JSON from server');
        }
    }
    if (!response.ok) throw new Error(data.error || `HTTP ${response.status} (Unknown Error)`);
    return data;
}

window.openPromptListModal = function() {
    document.getElementById('ai-prompt-list-modal').classList.remove('hidden');
    document.getElementById('ai-prompt-list-modal').classList.add('flex');
    loadAiPrompts();
};

window.closePromptListModal = function() {
    document.getElementById('ai-prompt-list-modal').classList.add('hidden');
    document.getElementById('ai-prompt-list-modal').classList.remove('flex');
};

async function loadAiPrompts() {
  try {
    const data = await apiRequest('prompts-api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'list'})
    });
    aiPrompts = (data.subjects || []).map(p => ({...p, id: Number(p.id), is_active: Number(p.is_active) === 1}));
    renderAiPrompts();
  } catch(e) {
    document.getElementById('ai-prompts-tbody').innerHTML = `<tr><td colspan="4" class="py-10 text-center text-red-500 font-bold">${esc(e.message)}</td></tr>`;
  }
}

function renderAiPrompts() {
  const tbody = document.getElementById('ai-prompts-tbody');
  if(aiPrompts.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="py-10 text-center text-[#65738a]">ยังไม่มีรายวิชา</td></tr>';
    return;
  }
  tbody.innerHTML = aiPrompts.map((p, index) => `
    <tr class="border-b border-[#e8ecf2] hover:bg-[#f8fafc]">
      <td class="px-6 py-4 text-center text-[13px]">${index + 1}</td>
      <td class="px-6 py-4 text-[13px] font-bold text-navy-950">${esc(p.subject_name)}</td>
      <td class="px-6 py-4 text-center">
        ${p.is_active ? '<span class="whitespace-nowrap px-2 py-1 bg-green-100 text-green-700 text-[11px] font-bold rounded-full">เปิดใช้งาน</span>' : '<span class="whitespace-nowrap px-2 py-1 bg-gray-100 text-gray-500 text-[11px] font-bold rounded-full">ปิด</span>'}
      </td>
      <td class="px-6 py-4 text-right space-x-2">
        <button type="button" onclick="editPrompt(${p.id})" class="whitespace-nowrap text-[13px] font-bold text-[#2369dd] hover:underline">แก้ไข (ใส่ Prompt)</button>
      </td>
    </tr>
  `).join('');
}

window.openPromptModal = function() {
  document.getElementById('prompt-modal-title').textContent = 'เพิ่มวิชาใหม่';
  document.getElementById('prompt-id').value = '';
  document.getElementById('prompt-name').value = '';
  document.getElementById('prompt-content').value = '';
  document.getElementById('prompt-active').checked = true;
  document.getElementById('md-file-name').textContent = '';
  document.getElementById('md-file-input').value = '';
  document.getElementById('ai-prompt-modal').classList.remove('hidden');
  document.getElementById('ai-prompt-modal').classList.add('flex');
};

window.closePromptModal = function() {
  document.getElementById('ai-prompt-modal').classList.add('hidden');
  document.getElementById('ai-prompt-modal').classList.remove('flex');
};

window.editPrompt = function(id) {
  const p = aiPrompts.find(x => x.id === id);
  if(!p) return;
  document.getElementById('prompt-modal-title').textContent = 'แก้ไข: ' + p.subject_name;
  document.getElementById('prompt-id').value = p.id;
  document.getElementById('prompt-name').value = p.subject_name;
  document.getElementById('prompt-content').value = p.prompt_md || '';
  document.getElementById('prompt-active').checked = !!p.is_active;
  
  document.getElementById('md-file-name').textContent = '';
  document.getElementById('md-file-input').value = '';

  document.getElementById('ai-prompt-modal').classList.remove('hidden');
  document.getElementById('ai-prompt-modal').classList.add('flex');
};

window.deletePrompt = async function(id) {
  if(!confirm('ยืนยันลบวิชานี้?')) return;
  try {
    await apiRequest('prompts-api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ action: 'delete', id: id })
    });
    alert('ลบวิชาแล้ว');
    location.reload();
  } catch(e) { alert(e.message); }
};

window.savePrompt = async function() {
  const payload = {
    id: document.getElementById('prompt-id').value,
    subject_name: document.getElementById('prompt-name').value,
    prompt_md: document.getElementById('prompt-content').value,
    is_active: document.getElementById('prompt-active').checked ? 1 : 0
  };

  try {
    const res = await apiRequest('prompts-api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    alert('บันทึกสำเร็จ!');
    location.reload();
  } catch(e) {
    alert(e.message);
  }
};

window.handleMdUpload = function() {
  const input = document.getElementById('md-file-input');
  if(!input.files || input.files.length === 0) return;
  const file = input.files[0];
  document.getElementById('md-file-name').textContent = file.name;

  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('prompt-content').value = e.target.result;
  };
  reader.readAsText(file);
};
// Initialize source requirements and available generation modes.
toggleSourceMode();
