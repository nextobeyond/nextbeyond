(() => {
  "use strict";
  const courseSelect = document.getElementById("curriculum-course");
  const list = document.getElementById("lessons-list");
  const editor = document.getElementById("lesson-editor");
  const form = document.getElementById("lesson-form");
  const errorBox = document.getElementById("lesson-error");
  const btnOpenPicker = document.getElementById("btn-open-worksheet-picker");
  const pickerModal = document.getElementById("worksheet-picker-modal");
  const pickerSearch = document.getElementById("picker-search-input");
  const pickerSubject = document.getElementById("picker-filter-subject");
  const pickerResults = document.getElementById("picker-results-container");
  const pickerAttachMode = document.getElementById("picker-attach-mode");
  const btnClosePicker = document.getElementById("btn-close-picker");

  const esc = v => String(v ?? "").replace(/[&<>'"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[c]));
  let courses = [], lessons = [], pickerWorksheets = [];

  async function api(url = "curriculum-api", options = {}) {
    const r = await fetch(url, {
      ...options,
      headers: { "Content-Type": "application/json", ...(options.headers || {}) }
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || "ไม่สามารถเชื่อมต่อระบบบทเรียนได้");
    return d;
  }

  const typeLabel = { video: "วิดีโอ", document: "เอกสาร", worksheet: "ใบงาน (Worksheet)", quiz: "แบบทดสอบ", live: "เรียนสด" };

  function render() {
    if (!lessons.length) {
      list.innerHTML = '<div class="p-8 text-center text-[#65738a]">ยังไม่มีบทเรียนในคอร์สนี้</div>';
      return;
    }
    list.innerHTML = lessons.map((l, i) => {
      const isWs = l.contentType === "worksheet";
      const icon = isWs ? "📄" : (i + 1);
      return `
        <div class="p-3 rounded-xl border border-[#e8ecf2] hover:border-pink-300 transition">
          <div class="flex gap-3 items-start">
            <span class="w-7 h-7 shrink-0 rounded-lg ${isWs ? 'bg-pink-100 text-pink-600' : 'bg-pink-50 text-pink-500'} flex items-center justify-center text-[12px] font-bold">
              ${icon}
            </span>
            <button data-edit="${esc(l.id)}" class="flex-1 text-left">
              <div class="text-[13px] font-bold text-navy-950">${esc(l.title)}</div>
              <div class="text-[11px] text-[#65738a] mt-1">
                ${esc(typeLabel[l.contentType] || l.contentType)}${l.durationMinutes ? " · " + l.durationMinutes + " นาที" : ""}${l.isPreview ? " · ดูฟรี" : ""}
              </div>
            </button>
            <div class="flex">
              <button data-move="up" data-id="${esc(l.id)}" class="p-1 text-[#65738a]" ${i === 0 ? "disabled" : ""}>↑</button>
              <button data-move="down" data-id="${esc(l.id)}" class="p-1 text-[#65738a]" ${i === lessons.length - 1 ? "disabled" : ""}>↓</button>
            </div>
          </div>
        </div>
      `;
    }).join("");
  }

  async function load(courseId = "") {
    try {
      const d = await api("curriculum-api" + (courseId ? `?course=${encodeURIComponent(courseId)}` : ""));
      courses = d.courses || [];
      lessons = d.lessons || [];
      if (!courseId) {
        courseSelect.innerHTML = '<option value="">เลือกคอร์สเรียน</option>' +
          courses.map(c => `<option value="${esc(c.id)}">${esc(c.title)} (${esc(c.status)})</option>`).join("");
        document.getElementById("no-courses").classList.toggle("hidden", courses.length > 0);
        document.getElementById("curriculum-workspace").classList.toggle("hidden", !courses.length);
      }
      render();
    } catch (e) {
      list.innerHTML = `<div class="p-8 text-center text-red-500">${esc(e.message)}</div>`;
    }
  }

  function openEditor(item = null) {
    editor.classList.remove("hidden");
    form.reset();
    form.elements.id.value = item?.id || "";
    form.elements.title.value = item?.title || "";
    form.elements.contentType.value = item?.contentType || "video";
    form.elements.durationMinutes.value = item?.durationMinutes ?? "";
    form.elements.contentUrl.value = item?.contentUrl || "";
    form.elements.isPreview.checked = !!item?.isPreview;
    document.getElementById("editor-title").textContent = item ? "แก้ไขบทเรียน" : "เพิ่มบทเรียน";
    document.getElementById("delete-lesson").classList.toggle("hidden", !item);
    errorBox.classList.add("hidden");
    form.elements.title.focus();
  }

  courseSelect.onchange = async () => {
    const id = courseSelect.value;
    document.getElementById("add-lesson").disabled = !id;
    if (btnOpenPicker) btnOpenPicker.disabled = !id;
    editor.classList.add("hidden");
    const preview = document.getElementById("student-preview");
    preview.classList.toggle("hidden", !id);
    preview.classList.toggle("flex", !!id);
    preview.href = id ? `../courses.php?course=${encodeURIComponent(id)}` : "#";
    await load(id);
  };

  document.getElementById("add-lesson").onclick = () => openEditor();
  document.getElementById("cancel-editor").onclick = () => editor.classList.add("hidden");

  form.onsubmit = async e => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    data.courseId = courseSelect.value;
    data.isPreview = form.elements.isPreview.checked;
    const save = document.getElementById("save-lesson");
    save.disabled = true;
    try {
      await api("curriculum-api", { method: data.id ? "PATCH" : "POST", body: JSON.stringify(data) });
      editor.classList.add("hidden");
      await load(courseSelect.value);
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.remove("hidden");
    } finally {
      save.disabled = false;
    }
  };

  list.onclick = async e => {
    const edit = e.target.closest("[data-edit]");
    const move = e.target.closest("[data-move]");
    if (edit) {
      openEditor(lessons.find(l => String(l.id) === String(edit.dataset.edit)));
      return;
    }
    if (move) {
      await api("curriculum-api", {
        method: "PATCH",
        body: JSON.stringify({ id: move.dataset.id, courseId: courseSelect.value, direction: move.dataset.move })
      });
      await load(courseSelect.value);
    }
  };

  document.getElementById("delete-lesson").onclick = async () => {
    const id = form.elements.id.value;
    if (id && confirm("ยืนยันการลบบทเรียนนี้?")) {
      try {
        await api(`curriculum-api?id=${encodeURIComponent(id)}`, { method: "DELETE" });
        editor.classList.add("hidden");
        await load(courseSelect.value);
      } catch (e) {
        alert(e.message);
      }
    }
  };

  // ─────────────────────────────────────────────────────────────
  // WORKSHEET PICKER LOGIC (Section 18 & 19)
  // ─────────────────────────────────────────────────────────────
  async function loadPickerWorksheets() {
    pickerResults.innerHTML = '<div class="p-8 text-center text-[#65738a]">กำลังค้นหาใบงานในคลัง...</div>';
    try {
      const q = pickerSearch.value.trim();
      const s = pickerSubject.value;
      const params = new URLSearchParams({ action: "list" });
      if (q) params.set("q", q);
      if (s) params.set("subject", s);

      const r = await fetch(`worksheets-api.php?${params.toString()}`);
      const d = await r.json();
      pickerWorksheets = d.worksheets || [];
      renderPickerResults();
    } catch (err) {
      pickerResults.innerHTML = `<div class="p-8 text-center text-red-500">${esc(err.message)}</div>`;
    }
  }

  function renderPickerResults() {
    if (!pickerWorksheets.length) {
      pickerResults.innerHTML = '<div class="p-8 text-center text-[#65738a]">ไม่พบใบงานที่ค้นหา</div>';
      return;
    }
    pickerResults.innerHTML = pickerWorksheets.map(ws => `
      <div class="py-3 flex items-center justify-between gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-50 text-blue-700">${esc(ws.subject)}</span>
            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">${esc(ws.level)}</span>
            <span class="text-[11px] font-bold text-pink-600">${ws.question_count || 0} ข้อ</span>
          </div>
          <h4 class="text-[14px] font-bold text-navy-950 truncate">${esc(ws.title)}</h4>
          <p class="text-[12px] text-[#64748b] truncate">${esc(ws.topic || ws.description || "—")}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="worksheet-detail.php?id=${ws.id}" target="_blank" class="h-8 px-3 rounded-lg border border-[#dce4ef] text-[12px] font-bold hover:bg-slate-50 flex items-center">
            ดูตัวอย่าง
          </a>
          <button type="button" class="btn-attach-ws h-8 px-3.5 rounded-lg bg-pink-500 hover:bg-pink-600 text-white text-[12px] font-bold shadow-xs transition" data-id="${ws.id}">
            แนบใบงานนี้
          </button>
        </div>
      </div>
    `).join("");
  }

  if (btnOpenPicker) {
    btnOpenPicker.onclick = () => {
      pickerModal.classList.remove("hidden");
      loadPickerWorksheets();
    };
  }

  if (btnClosePicker) {
    btnClosePicker.onclick = () => pickerModal.classList.add("hidden");
  }

  if (pickerSearch) {
    let t = null;
    pickerSearch.oninput = () => {
      clearTimeout(t);
      t = setTimeout(loadPickerWorksheets, 300);
    };
  }

  if (pickerSubject) {
    pickerSubject.onchange = () => loadPickerWorksheets();
  }

  if (pickerResults) {
    pickerResults.onclick = async e => {
      const attachBtn = e.target.closest(".btn-attach-ws");
      if (!attachBtn) return;
      const wsId = parseInt(attachBtn.dataset.id, 10);
      const ws = pickerWorksheets.find(w => w.id === wsId);
      if (!ws) return;

      const mode = pickerAttachMode.value; // 'original' | 'duplicate'
      let finalId = ws.id;

      if (mode === "duplicate") {
        attachBtn.disabled = true;
        attachBtn.textContent = "กำลังคัดลอก...";
        try {
          const dupRes = await fetch("worksheets-api.php?action=duplicate", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: ws.id })
          });
          const dupData = await dupRes.json();
          if (dupData.success) {
            finalId = dupData.id;
          }
        } catch {
          // fallback to original
        }
      }

      // Populate lesson editor
      pickerModal.classList.add("hidden");
      openEditor();
      form.elements.title.value = `ใบงาน: ${ws.title}`;
      form.elements.contentType.value = "worksheet";
      form.elements.durationMinutes.value = "30";
      form.elements.contentUrl.value = `worksheet-detail.php?id=${finalId}`;
      form.elements.title.focus();
    };
  }

  load();
})();
