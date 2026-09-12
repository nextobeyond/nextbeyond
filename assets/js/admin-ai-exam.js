// ============================================================
//  admin-ai-exam.js  — ใช้ Server-side API Key (ไม่ใช้ localStorage)
//  Key ถูกเข้ารหัส AES-256 และเก็บใน DB ผ่าน /admin/ai-settings-api.php
// ============================================================

"use strict";

// ---------- State ----------
let currentExam  = null;
let answers      = {};
let isRevealed   = false;
let _serverKeyConfigured = false;   // true หากมี key ใน DB แล้ว

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);
}

// ---------- Settings API (server-side) ----------
const SETTINGS_API = '../admin/ai-settings-api';

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
    document.getElementById('view-form').classList.remove('hidden');
    document.getElementById('view-exam').classList.add('hidden');
    currentExam = null;
    answers     = {};
    isRevealed  = false;
}

function toggleType() {
    const type = document.getElementById('examType').value;
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
    if (!driveUrl) {
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
                url:          driveUrl,
                type:         type,
                count:        finalCount,
                counts:       countsObj,
                details:      details,
                shuffle:      shuffle,
                subject:      subject,
                useServerKey: true,   // ← ใช้ key จาก server DB
            }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์');
        if (!data.questions || data.questions.length === 0)
            throw new Error('AI ไม่สามารถสร้างข้อสอบได้ โปรดตรวจสอบเอกสารต้นฉบับ');
        if (data.warning) alert(data.warning);

        currentExam = data.questions;
        answers     = {};
        isRevealed  = false;

        // ── บันทึกลง Database อัตโนมัติ ──
        try {
            const saveRes = await fetch('../admin/exams-api', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    questions:      currentExam,
                    title:          `แบบทดสอบ${subject}จาก AI ${new Date().toLocaleDateString('th-TH')}`,
                    subject:        subject,
                    grade:          'ทุกระดับ',
                    generationMode: type,
                    sourceUrl:      driveUrl,
                }),
            });
            const saveData = await saveRes.json();
            if (saveRes.ok && saveData.examId) {
                // redirect ไปหน้า Tests พร้อมแจ้งว่าบันทึกสำเร็จ
                window.location.href = `/admin/tests?created=${saveData.examId}`;
                return;
            }
        } catch (_) {
            // ถ้าบันทึก DB ไม่สำเร็จ ยังให้ดูข้อสอบได้ใน session
            console.warn('ไม่สามารถบันทึกลงฐานข้อมูลได้ แสดงผลแบบ session เท่านั้น');
        }

        // fallback: แสดงผลใน session (กรณีบันทึก DB ไม่สำเร็จ)
        document.getElementById('exam-meta').innerHTML = `
            <span class="bg-pink-50 text-pink-600 font-bold text-[11px] px-2.5 py-1 rounded-md tracking-wide uppercase">ประเภท: ${type}</span>
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

        let html = `<div class="text-[16px] font-bold text-navy-950 mb-5 whitespace-pre-wrap leading-relaxed"><span class="font-black mr-2 text-pink-500">ข้อ ${qIndex + 1}.</span>${q.questionText}</div><div class="space-y-3">`;

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

            html += `<div class="${optClass}" onclick="selectOption(${qIndex}, ${oIndex})"><div class="${markerClass}">${markerInner}</div><span class="flex-1 text-[14px] font-medium leading-snug ${isRevealed && isActualAnswer ? 'text-[#166534]' : 'text-navy-950'}">${opt}</span></div>`;
        });

        html += '</div>';

        if (isRevealed && q.explanation) {
            html += `
            <div class="mt-5 p-4.5 bg-[#f8fafc] rounded-xl border border-[#e8ecf2]">
              <h4 class="flex items-center font-bold text-navy-950 mb-1.5 text-[14px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1.5 text-[#65738a]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                คำอธิบายเฉลย
              </h4>
              <p class="text-[#65738a] text-[13px] font-medium whitespace-pre-wrap leading-relaxed ml-[26px]">${q.explanation}</p>
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
