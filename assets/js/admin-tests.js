(() => {
  "use strict";
  const grid = document.getElementById("exam-grid"),
        search = document.getElementById("tests-search"),
        type = document.getElementById("tests-type-filter"),
        subject = document.getElementById("subject-filter"),
        status = document.getElementById("status-filter");
  const esc = v => String(v ?? "").replace(/[&<>'"]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[c]));
  const labels = {quiz:"Quiz",placement:"Placement",pretest:"Pre-test",posttest:"Post-test"};
  let exams = [];

  // Export Modal Elements
  const exportModal = document.getElementById("export-docx-modal"),
        exportForm = document.getElementById("export-docx-form"),
        exportExamIdInput = document.getElementById("export-exam-id"),
        exportExamTitleEl = document.getElementById("export-exam-title"),
        closeExportModalBtn = document.getElementById("close-export-modal"),
        cancelExportBtn = document.getElementById("btn-cancel-export"),
        submitExportBtn = document.getElementById("btn-submit-export"),
        exportBtnText = document.getElementById("export-btn-text"),
        exportStatusBox = document.getElementById("export-status-box"),
        optShowTitle = document.getElementById("opt-show-title"),
        optShowDiagrams = document.getElementById("opt-show-diagrams"),
        optShowAnswers = document.getElementById("opt-show-answers"),
        optShowExplanations = document.getElementById("opt-show-explanations");

  async function api(url="exams-api", options={}) {
    const r = await fetch(url, {
      ...options,
      headers: {"Content-Type":"application/json", ...(options.headers || {})},
      cache: "no-store"
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || "ไม่สามารถเชื่อมต่อฐานข้อมูลข้อสอบได้");
    return d;
  }

  function stats() {
    document.getElementById("stat-total").textContent = exams.length;
    document.getElementById("stat-published").textContent = exams.filter(e => e.isPublished).length;
    document.getElementById("stat-questions").textContent = exams.reduce((n, e) => n + (e.questionCount || 0), 0);
    document.getElementById("stat-attempts").textContent = exams.reduce((n, e) => n + (e.attemptCount || 0), 0);
  }

  function render() {
    const q = search.value.trim().toLocaleLowerCase("th"),
          t = type.value,
          s = subject.value,
          st = status.value;
    const rows = exams.filter(e => {
      const text = `${e.title} ${e.subject || ""} ${e.grade || ""}`.toLocaleLowerCase("th");
      return (!q || text.includes(q)) && (!t || e.type === t) && (!s || e.subject === s) && (!st || (st === "published" ? e.isPublished : !e.isPublished));
    });

    document.getElementById("tests-count").textContent = `แสดง ${rows.length} จาก ${exams.length} รายการ`;

    if (!rows.length) {
      grid.innerHTML = '<div class="empty-state"><div style="font-size:34px;margin-bottom:8px">☷</div><b>ไม่พบข้อสอบที่ตรงกับตัวกรอง</b><div style="font-size:12px;margin-top:5px">สร้างข้อสอบใหม่ด้วย AI หรือเปลี่ยนตัวกรอง</div></div>';
      return;
    }

    grid.innerHTML = rows.map(e => `<article class="exam-card">
      <div class="card-top"><span class="type-badge type-${esc(e.type)}">${esc(labels[e.type] || e.type)}</span>${e.isAiGenerated ? '<span class="ai-badge">✦ AI GENERATED</span>' : ''}</div>
      <h3 class="exam-title">${esc(e.title)}</h3><p class="exam-desc">${esc(e.topic || "ชุดข้อสอบคัดสรรสำหรับการวัดผลและฝึกทักษะ")}</p>
      <div class="exam-meta"><div class="meta"><b>${esc(e.questionCount || 0)}</b><small>คำถาม</small></div><div class="meta"><b>${e.timeLimitMinutes ? esc(e.timeLimitMinutes) : "∞"}</b><small>${e.timeLimitMinutes ? "นาที" : "ไม่จำกัดเวลา"}</small></div><div class="meta"><b>${esc(e.attemptCount || 0)}</b><small>ผู้ทำ</small></div></div>
      <div style="font-size:11px;color:#65738a;margin-bottom:13px"><b style="color:#28415f">${esc(e.subject || "ทั่วไป")}</b> · ${esc(e.grade || "ทุกระดับ")}</div>
      <div class="card-settings">
        <div class="time-setting">
          <label for="exam-time-${esc(e.id)}">เวลาทำ (นาที)</label>
          <input id="exam-time-${esc(e.id)}" data-time-input="${esc(e.id)}" type="number" min="1" max="600" step="1" value="${e.timeLimitMinutes ? esc(e.timeLimitMinutes) : ""}" placeholder="ไม่จำกัด">
          <button type="button" data-save-time="${esc(e.id)}">บันทึก</button>
        </div>
        <div class="setting-row">
          <span>เปิดให้นักเรียนทำ</span>
          <button type="button" class="switch ${e.isPublished ? "on" : ""}" role="switch" aria-checked="${e.isPublished}" data-toggle="isPublished" data-id="${esc(e.id)}"></button>
        </div>
        <div class="setting-row">
          <span>ต้องเข้าสู่ระบบ</span>
          <button type="button" class="switch ${e.requiresLogin ? "on" : ""}" role="switch" aria-checked="${e.requiresLogin}" data-toggle="requiresLogin" data-id="${esc(e.id)}"></button>
        </div>
      </div>
      <div class="card-actions">
        <a class="exam-btn" href="question-bank.php?exam=${esc(e.id)}">ดูคำถาม</a>
        <div class="export-dropdown-container">
          <button type="button" class="exam-btn" data-toggle-export="${esc(e.id)}" title="ส่งออกข้อสอบ">ส่งออก ▼</button>
          <div class="export-menu" id="export-menu-${esc(e.id)}">
            <button type="button" class="export-menu-item" data-export-docx="${esc(e.id)}" data-exam-title="${esc(e.title)}">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              ส่งออก Word (.docx)
            </button>
            <button type="button" class="export-menu-item" data-export-pdf="${esc(e.id)}" data-exam-title="${esc(e.title)}">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
              พิมพ์ / PDF
            </button>
          </div>
        </div>
        <button class="exam-btn more-btn" data-rename="${esc(e.id)}" title="เปลี่ยนชื่อ">✎</button>
        <button class="exam-btn more-btn" data-delete="${esc(e.id)}" title="ลบ">⌫</button>
      </div>
    </article>`).join("");
  }

  async function load() {
    try {
      const data = await api();
      exams = data.exams || [];
      const subjects = [...new Set(exams.map(e => e.subject).filter(Boolean))].sort();
      subject.innerHTML = '<option value="">ทุกวิชา</option>' + subjects.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join("");
      const params = new URLSearchParams(window.location.search);
      if (params.has("status") && status) status.value = params.get("status");
      if (params.has("type") && type) type.value = params.get("type");
      if (params.has("search") && search) search.value = params.get("search");
      if (params.has("subject") && subject) subject.value = params.get("subject");
      stats();
      render();
    } catch (e) {
      grid.innerHTML = `<div class="empty-state" style="color:#dc2e62">${esc(e.message)}<br><button class="exam-btn" data-retry style="margin-top:14px">ลองใหม่</button></div>`;
    }
  }

  // Close open dropdowns
  function closeAllDropdowns() {
    document.querySelectorAll(".export-menu.show").forEach(m => m.classList.remove("show"));
  }

  document.addEventListener("click", event => {
    if (!event.target.closest(".export-dropdown-container")) {
      closeAllDropdowns();
    }
  });

  // Modal Functions
  function openExportModal(id, title) {
    closeAllDropdowns();
    exportExamIdInput.value = id;
    exportExamTitleEl.textContent = title;
    
    // Reset state
    exportForm.reset();
    const studentRadio = exportForm.querySelector('input[name="docType"][value="student"]');
    if (studentRadio) studentRadio.checked = true;
    optShowTitle.checked = true;
    optShowDiagrams.checked = true;
    optShowAnswers.disabled = true;
    optShowAnswers.checked = false;
    optShowExplanations.disabled = true;
    optShowExplanations.checked = false;

    exportStatusBox.className = "hidden mb-4 p-3 rounded-xl text-[13px] font-bold";
    exportStatusBox.textContent = "";
    submitExportBtn.disabled = false;
    exportBtnText.textContent = "สร้างไฟล์ Word";

    exportModal.classList.remove("hidden");
    exportModal.classList.add("flex");
  }

  function closeExportModal() {
    exportModal.classList.add("hidden");
    exportModal.classList.remove("flex");
  }

  if (closeExportModalBtn) closeExportModalBtn.onclick = closeExportModal;
  if (cancelExportBtn) cancelExportBtn.onclick = closeExportModal;
  if (exportModal) {
    exportModal.onclick = event => {
      if (event.target === exportModal) closeExportModal();
    };
  }

  // Radio button change handler (Student vs Teacher mode)
  if (exportForm) {
    exportForm.addEventListener("change", event => {
      if (event.target.name === "docType") {
        const isTeacher = event.target.value === "teacher";
        if (isTeacher) {
          optShowAnswers.disabled = false;
          optShowAnswers.checked = true;
          optShowExplanations.disabled = false;
          optShowExplanations.checked = true;
        } else {
          optShowAnswers.disabled = true;
          optShowAnswers.checked = false;
          optShowExplanations.disabled = true;
          optShowExplanations.checked = false;
        }
      }
    });

    // Handle Form Submit & DOCX Download
    exportForm.onsubmit = async event => {
      event.preventDefault();
      const examId = exportExamIdInput.value;
      if (!examId) return;

      const docType = exportForm.querySelector('input[name="docType"]:checked')?.value || "student";
      const payload = {
        examId: Number(examId),
        docType: docType,
        showTitle: optShowTitle.checked,
        showDiagrams: optShowDiagrams.checked,
        showAnswers: optShowAnswers.checked,
        showExplanations: optShowExplanations.checked,
      };

      // Loading state
      submitExportBtn.disabled = true;
      exportBtnText.textContent = "กำลังสร้างไฟล์ Word...";
      exportStatusBox.className = "mb-4 p-3 rounded-xl text-[13px] font-bold bg-blue-50 text-blue-700 border border-blue-200 block";
      exportStatusBox.innerHTML = '<span class="inline-block animate-spin mr-2">⟳</span> กำลังสร้างไฟล์ Word และประมวลผลรูปประกอบ...';

      try {
        const resp = await fetch("export-exam-docx.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(payload)
        });

        if (!resp.ok) {
          const errData = await resp.json().catch(() => ({}));
          throw new Error(errData.error || `เซิร์ฟเวอร์ตอบกลับรหัสข้อผิดพลาด (${resp.status})`);
        }

        // Parse filename from Content-Disposition header if available
        let filename = `exam-${examId}.docx`;
        const disposition = resp.headers.get("Content-Disposition");
        if (disposition) {
          const matchUtf8 = disposition.match(/filename\*=UTF-8''([^;]+)/i);
          if (matchUtf8) {
            filename = decodeURIComponent(matchUtf8[1]);
          } else {
            const matchAscii = disposition.match(/filename="?([^";]+)"?/i);
            if (matchAscii) filename = matchAscii[1];
          }
        }

        const blob = await resp.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.style.display = "none";
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(downloadUrl);
        a.remove();

        // Success state
        exportStatusBox.className = "mb-4 p-3 rounded-xl text-[13px] font-bold bg-green-50 text-green-700 border border-green-200 block";
        exportStatusBox.innerHTML = '✓ สร้างไฟล์สำเร็จ กำลังดาวน์โหลดเอกสาร...';
        exportBtnText.textContent = "สร้างไฟล์สำเร็จ";

        setTimeout(() => {
          closeExportModal();
        }, 1500);

      } catch (err) {
        exportStatusBox.className = "mb-4 p-3 rounded-xl text-[13px] font-bold bg-red-50 text-red-700 border border-red-200 block";
        exportStatusBox.textContent = `เกิดข้อผิดพลาด: ${err.message}`;
        submitExportBtn.disabled = false;
        exportBtnText.textContent = "สร้างไฟล์ Word";
      }
    };
  }

  const created = new URLSearchParams(location.search).get("created");
  if (created) {
    const box = document.getElementById("tests-created-notice");
    box.textContent = `บันทึกข้อสอบ #${created} เรียบร้อยแล้ว ข้อสอบอยู่ในสถานะฉบับร่าง`;
    box.classList.add("show");
    history.replaceState({}, "", location.pathname);
  }

  [search, type, subject, status].forEach(el => el.addEventListener(el === search ? "input" : "change", render));

  grid.onclick = async event => {
    if (event.target.closest("[data-retry]")) return load();

    // Export Dropdown Toggle
    const exportToggle = event.target.closest("[data-toggle-export]");
    if (exportToggle) {
      const menu = document.getElementById(`export-menu-${exportToggle.dataset.toggleExport}`);
      if (menu) {
        const wasOpen = menu.classList.contains("show");
        closeAllDropdowns();
        if (!wasOpen) menu.classList.add("show");
      }
      return;
    }

    // Open Word Export Modal
    const wordExport = event.target.closest("[data-export-docx]");
    if (wordExport) {
      openExportModal(wordExport.dataset.exportDocx, wordExport.dataset.examTitle);
      return;
    }

    // PDF / Print Export
    const pdfExport = event.target.closest("[data-export-pdf]");
    if (pdfExport) {
      closeAllDropdowns();
      window.open(`question-bank.php?exam=${encodeURIComponent(pdfExport.dataset.exportPdf)}&print=1`, "_blank");
      return;
    }

    // Save Time
    const saveTime = event.target.closest("[data-save-time]");
    if (saveTime) {
      const exam = exams.find(e => e.id === saveTime.dataset.saveTime),
            input = [...grid.querySelectorAll("[data-time-input]")].find(el => el.dataset.timeInput === saveTime.dataset.saveTime);
      if (!exam || !input) return;
      const raw = input.value.trim(),
            value = raw === "" ? null : Number(raw);
      if (value !== null && (!Number.isInteger(value) || value < 1 || value > 600)) {
        alert("กรุณากำหนดเวลาระหว่าง 1-600 นาที หรือเว้นว่างหากไม่จำกัดเวลา");
        input.focus();
        return;
      }
      saveTime.disabled = true;
      saveTime.textContent = "กำลังบันทึก";
      try {
        await api("exams-api", {method: "PATCH", body: JSON.stringify({id: exam.id, field: "timeLimitMinutes", value})});
        exam.timeLimitMinutes = value;
        render();
      } catch (e) {
        alert(e.message);
        saveTime.disabled = false;
        saveTime.textContent = "บันทึก";
      }
      return;
    }

    // Toggle switch
    const toggle = event.target.closest("[data-toggle]");
    if (toggle) {
      const exam = exams.find(e => e.id === toggle.dataset.id);
      if (!exam) return;
      const field = toggle.dataset.toggle,
            value = !exam[field];
      toggle.disabled = true;
      try {
        const result = await api("exams-api", {method: "PATCH", body: JSON.stringify({id: exam.id, field, value})});
        exam[field] = value;
        if (field === "isPublished") exam.status = result.status || (value ? "active" : "closed");
        stats();
        render();
      } catch (e) {
        alert(e.message);
        render();
      }
      return;
    }

    // Rename
    const rename = event.target.closest("[data-rename]");
    if (rename) {
      const exam = exams.find(e => e.id === rename.dataset.rename);
      if (!exam) return;
      const value = prompt("ชื่อแบบทดสอบ?", exam.title);
      if (value === null || !value.trim()) return;
      try {
        await api("exams-api", {method: "PATCH", body: JSON.stringify({id: exam.id, field: "title", value})});
        exam.title = value.trim();
        render();
      } catch (e) {
        alert(e.message);
      }
      return;
    }

    // Delete
    const del = event.target.closest("[data-delete]");
    if (del && confirm("ยืนยันการลบข้อสอบนี้และคำถามทั้งหมด?")) {
      try {
        await api(`exams-api?id=${encodeURIComponent(del.dataset.delete)}`, {method: "DELETE"});
        exams = exams.filter(e => e.id !== del.dataset.delete);
        stats();
        render();
      } catch (e) {
        alert(e.message);
      }
    }
  };

  load();
})();
