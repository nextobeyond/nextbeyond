/**
 * Nextbeyond Compass - Worksheet Library System ("คลังใบงาน")
 * Client-side Controller & Interactive Learning Asset Manager
 */
(() => {
  "use strict";

  // DOM Elements
  const searchInput = document.getElementById("ws-search-input");
  const filterSubject = document.getElementById("filter-subject");
  const filterLevel = document.getElementById("filter-level");
  const filterType = document.getElementById("filter-type");
  const filterDifficulty = document.getElementById("filter-difficulty");
  const filterSource = document.getElementById("filter-source");
  const filterStatus = document.getElementById("filter-status");
  const btnClearFilters = document.getElementById("btn-clear-filters");

  const btnViewGrid = document.getElementById("view-mode-grid");
  const btnViewList = document.getElementById("view-mode-list");
  const gridContainer = document.getElementById("ws-grid");
  const listContainer = document.getElementById("ws-list");
  const listTbody = document.getElementById("ws-list-tbody");
  const loadingIndicator = document.getElementById("ws-loading");
  const emptyState = document.getElementById("ws-empty");
  const totalBadge = document.getElementById("header-total-badge");
  const folderTabContainer = document.getElementById("folder-tab-container");
  const btnAddFolder = document.getElementById("btn-add-folder");

  // Modals
  const previewModal = document.getElementById("preview-modal");
  const assignModal = document.getElementById("assign-modal");
  const manualModal = document.getElementById("manual-create-modal");

  // State
  let worksheets = [];
  let folders = [];
  let activeFolderId = null;
  let currentViewMode = localStorage.getItem("nb_worksheet_view_mode") || "grid";
  let searchDebounceTimer = null;
  let activePreviewWorksheet = null;
  let previewMode = "teacher"; // "teacher" | "student"

  const escapeHtml = str => String(str ?? "").replace(/[&<>'"]/g, c => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"
  }[c]));

  const showToast = (message, type = "success") => {
    const container = document.getElementById("toast-container");
    if (!container) return;
    const toast = document.createElement("div");
    const bg = type === "success" ? "bg-navy-950 text-white border-l-4 border-pink-500" : "bg-red-600 text-white";
    toast.className = `p-4 rounded-xl shadow-xl flex items-center gap-3 text-[13px] font-bold transition-all duration-300 transform translate-y-2 opacity-0 ${bg}`;
    toast.innerHTML = `
      <span class="w-5 h-5 flex items-center justify-center shrink-0">
        ${type === "success" ? "✓" : "!"}
      </span>
      <span class="flex-1">${escapeHtml(message)}</span>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => {
      toast.classList.remove("translate-y-2", "opacity-0");
    });
    setTimeout(() => {
      toast.classList.add("opacity-0", "translate-y-2");
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  };

  // Format date to Thai relative / friendly format
  const formatThaiDate = (dateStr) => {
    if (!dateStr) return "—";
    const d = new Date(dateStr.replace(" ", "T"));
    if (isNaN(d.getTime())) return dateStr;
    const day = d.getDate();
    const thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    const month = thaiMonths[d.getMonth()];
    const year = d.getFullYear() + 543;
    const hours = String(d.getHours()).padStart(2, "0");
    const mins = String(d.getMinutes()).padStart(2, "0");
    return `${day} ${month} ${year} ${hours}:${mins} น.`;
  };

  const getDifficultyBadge = (diff) => {
    switch (String(diff).toLowerCase()) {
      case "easy":
        return `<span class="ws-chip bg-emerald-50 text-emerald-700 border border-emerald-200/60">ง่าย</span>`;
      case "medium":
        return `<span class="ws-chip bg-amber-50 text-amber-700 border border-amber-200/60">ปานกลาง</span>`;
      case "hard":
        return `<span class="ws-chip bg-orange-50 text-orange-700 border border-orange-200/60">ยาก</span>`;
      case "expert":
        return `<span class="ws-chip bg-rose-50 text-rose-700 border border-rose-200/60">ยากมาก</span>`;
      default:
        return `<span class="ws-chip bg-slate-100 text-slate-700">${escapeHtml(diff || "ทั่วไป")}</span>`;
    }
  };

  // API Call helper
  async function fetchApi(url, options = {}) {
    const res = await fetch(url, {
      ...options,
      headers: { "Content-Type": "application/json", ...(options.headers || {}) }
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.error || "เกิดข้อผิดพลาดในการเชื่อมต่อ");
    }
    return data;
  }

  // Load Worksheets with current filters
  async function loadWorksheets() {
    loadingIndicator.classList.remove("hidden");
    gridContainer.classList.add("hidden");
    listContainer.classList.add("hidden");
    emptyState.classList.add("hidden");

    try {
      const params = new URLSearchParams();
      params.set("action", "list");
      if (searchInput.value.trim()) params.set("q", searchInput.value.trim());
      if (filterSubject.value) params.set("subject", filterSubject.value);
      if (filterLevel.value) params.set("level", filterLevel.value);
      if (filterType.value) params.set("type", filterType.value);
      if (filterDifficulty.value) params.set("difficulty", filterDifficulty.value);
      if (filterSource.value) params.set("source", filterSource.value);
      if (filterStatus.value) params.set("status", filterStatus.value);
      if (activeFolderId !== null) params.set("folder_id", activeFolderId);

      const data = await fetchApi(`worksheets-api.php?${params.toString()}`);
      worksheets = data.worksheets || [];
      folders = data.folders || [];

      // Update cache in LocalStorage
      localStorage.setItem("nb_cached_worksheets", JSON.stringify(worksheets.slice(0, 15)));

      renderFolderTabs();
      renderWorksheets();
      if (totalBadge) totalBadge.textContent = `${worksheets.length} รายการ`;
    } catch (err) {
      console.warn("API Error, attempting LocalStorage fallback:", err);
      const cached = localStorage.getItem("nb_cached_worksheets");
      if (cached) {
        worksheets = JSON.parse(cached);
        renderWorksheets();
      } else {
        emptyState.classList.remove("hidden");
      }
      showToast(err.message, "error");
    } finally {
      loadingIndicator.classList.add("hidden");
    }
  }

  // Render Folder Tabs
  function renderFolderTabs() {
    const allBtn = `
      <button type="button" data-folder="" class="folder-tab px-3.5 py-1.5 rounded-xl text-[13px] font-bold transition flex items-center gap-1.5 ${activeFolderId === null ? 'bg-pink-50 text-pink-600' : 'text-[#64748b] hover:bg-slate-100'}">
        <svg class="w-3.5 h-3.5 ${activeFolderId === null ? 'text-pink-500' : 'text-[#94a3b8]'}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        <span>ทั้งหมด</span>
      </button>
    `;

    const folderButtons = folders.map(f => {
      const isActive = activeFolderId === f.id;
      return `
        <button type="button" data-folder="${f.id}" class="folder-tab px-3 py-1.5 rounded-xl text-[13px] font-bold transition flex items-center gap-1.5 ${isActive ? 'bg-pink-50 text-pink-600' : 'text-[#64748b] hover:bg-slate-100'}">
          <span>📁</span>
          <span>${escapeHtml(f.name)}</span>
        </button>
      `;
    }).join("");

    folderTabContainer.innerHTML = allBtn + folderButtons;
  }

  // Render Worksheets based on View Mode (Grid or List)
  function renderWorksheets() {
    if (!worksheets.length) {
      gridContainer.classList.add("hidden");
      listContainer.classList.add("hidden");
      emptyState.classList.remove("hidden");
      return;
    }

    emptyState.classList.add("hidden");

    if (currentViewMode === "grid") {
      listContainer.classList.add("hidden");
      gridContainer.classList.remove("hidden");
      renderGrid();
    } else {
      gridContainer.classList.add("hidden");
      listContainer.classList.remove("hidden");
      renderList();
    }

    updateViewButtons();
  }

  // Render Grid Cards
  function renderGrid() {
    gridContainer.innerHTML = worksheets.map(ws => {
      const isAi = ws.generation_source === "ai";
      const sourceBadge = isAi
        ? `<span class="ws-chip bg-pink-50 text-pink-600 border border-pink-200/50">✨ AI Generated</span>`
        : `<span class="ws-chip bg-slate-100 text-slate-700">✍️ ครูสร้างเอง</span>`;

      return `
        <div class="group bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-xs hover:shadow-md hover:border-pink-300 transition-all duration-200 flex flex-col justify-between" data-id="${ws.id}">
          <div>
            <!-- Top Badges & Menu Row -->
            <div class="flex items-center justify-between gap-2 mb-3">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="ws-chip bg-blue-50 text-blue-700 font-bold">${escapeHtml(ws.subject)}</span>
                <span class="ws-chip bg-slate-100 text-slate-700 font-bold">${escapeHtml(ws.level)}</span>
                ${sourceBadge}
              </div>

              <!-- Context Dropdown Menu -->
              <div class="relative">
                <button type="button" class="btn-card-menu p-1.5 rounded-lg text-[#94a3b8] hover:text-navy-950 hover:bg-slate-100 transition" data-id="${ws.id}" title="จัดการใบงาน">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                </button>
                <div id="menu-dropdown-${ws.id}" class="card-dropdown hidden absolute right-0 top-full mt-1 w-48 bg-white rounded-xl shadow-xl border border-[#e2e8f0] py-1.5 z-20 text-[13px] font-bold text-navy-900">
                  <a href="worksheet-detail.php?id=${ws.id}" class="flex items-center gap-2 px-4 py-2 hover:bg-slate-50 text-navy-900">
                    <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    <span>แก้ไขใบงาน</span>
                  </a>
                  <button type="button" class="action-duplicate w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-slate-50 text-navy-900" data-id="${ws.id}">
                    <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span>สร้างสำเนาแล้วแก้ไข</span>
                  </button>
                  <button type="button" class="action-move-folder w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-slate-50 text-navy-900" data-id="${ws.id}">
                    <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    <span>ย้ายเข้าโฟลเดอร์</span>
                  </button>
                  <button type="button" class="action-print w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-slate-50 text-navy-900" data-id="${ws.id}">
                    <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>พิมพ์ / ส่งออก PDF</span>
                  </button>
                  <div class="my-1 border-t border-[#f1f5f9]"></div>
                  <button type="button" class="action-archive w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-amber-50 text-amber-700" data-id="${ws.id}">
                    <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    <span>จัดเก็บ (Archive)</span>
                  </button>
                  <button type="button" class="action-delete w-full text-left flex items-center gap-2 px-4 py-2 hover:bg-red-50 text-red-600" data-id="${ws.id}">
                    <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>ลบใบงาน</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Title & Topic -->
            <h3 class="text-[16px] font-bold text-navy-950 mb-1 leading-snug group-hover:text-pink-600 transition-colors">
              <a href="worksheet-detail.php?id=${ws.id}">${escapeHtml(ws.title)}</a>
            </h3>
            <p class="text-[12px] text-[#64748b] line-clamp-2 mb-3">
              ${escapeHtml(ws.topic || ws.description || "ชุดแบบฝึกหัดสำหรับเสริมทักษะความเข้าใจ")}
            </p>

            <!-- Metadata Row -->
            <div class="flex items-center gap-2 flex-wrap text-[11px] mb-4">
              <span class="ws-chip bg-slate-100 text-slate-700 font-bold">${escapeHtml(ws.worksheet_type)}</span>
              ${getDifficultyBadge(ws.difficulty)}
              <span class="ws-chip bg-slate-100 text-navy-900 font-bold">${ws.question_count || 0} ข้อ</span>
              ${ws.folder_name ? `<span class="ws-chip bg-violet-50 text-violet-700 font-bold">📁 ${escapeHtml(ws.folder_name)}</span>` : ""}
            </div>
          </div>

          <!-- Bottom Footer / Actions -->
          <div class="pt-3 border-t border-[#f1f5f9]">
            <div class="flex items-center justify-between text-[11px] text-[#94a3b8] mb-3">
              <span>โดย ${escapeHtml(ws.creator_name || "Admin")}</span>
              <span>${formatThaiDate(ws.updated_at)}</span>
            </div>

            <div class="flex items-center gap-2">
              <button type="button" class="btn-quick-preview flex-1 h-9 px-2.5 rounded-xl border border-[#dce4ef] hover:bg-slate-50 text-navy-900 font-bold text-[12px] transition flex items-center justify-center gap-1.5" data-id="${ws.id}">
                <svg class="w-3.5 h-3.5 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>ดูตัวอย่าง</span>
              </button>
              <button type="button" class="btn-card-assign flex-1 h-9 px-2.5 rounded-xl bg-pink-50 hover:bg-pink-500 text-pink-600 hover:text-white font-bold text-[12px] transition flex items-center justify-center gap-1.5" data-id="${ws.id}" data-title="${escapeHtml(ws.title)}">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>ใช้กับคลาส</span>
              </button>
              <button type="button" class="action-delete w-9 h-9 rounded-xl border border-red-200 bg-red-50/60 hover:bg-red-600 hover:border-red-600 text-red-500 hover:text-white transition flex items-center justify-center shrink-0" data-id="${ws.id}" data-title="${escapeHtml(ws.title)}" title="ลบใบงาน">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>
        </div>
      `;
    }).join("");
  }

  // Render List View Rows
  function renderList() {
    listTbody.innerHTML = worksheets.map(ws => {
      const isAi = ws.generation_source === "ai";
      const sourceBadge = isAi
        ? `<span class="ws-chip bg-pink-50 text-pink-600">AI</span>`
        : `<span class="ws-chip bg-slate-100 text-slate-700">Manual</span>`;

      return `
        <tr class="hover:bg-[#fafcff] transition-colors" data-id="${ws.id}">
          <td class="py-3.5 px-5">
            <div class="font-bold text-navy-950 text-[14px]">
              <a href="worksheet-detail.php?id=${ws.id}" class="hover:text-pink-600 transition-colors">${escapeHtml(ws.title)}</a>
            </div>
            <div class="text-[12px] text-[#64748b] truncate max-w-[280px]">
              ${escapeHtml(ws.topic || ws.description || "—")}
            </div>
          </td>
          <td class="py-3.5 px-4">
            <div class="flex items-center gap-1.5">
              <span class="ws-chip bg-blue-50 text-blue-700 font-bold">${escapeHtml(ws.subject)}</span>
              <span class="ws-chip bg-slate-100 text-slate-700 font-bold">${escapeHtml(ws.level)}</span>
            </div>
          </td>
          <td class="py-3.5 px-4">
            <div class="flex items-center gap-1.5">
              ${getDifficultyBadge(ws.difficulty)}
              <span class="ws-chip bg-slate-100 text-slate-700">${escapeHtml(ws.worksheet_type)}</span>
            </div>
          </td>
          <td class="py-3.5 px-4 font-bold text-navy-900">
            ${ws.question_count || 0} ข้อ
          </td>
          <td class="py-3.5 px-4">
            <div class="text-[12px] font-bold text-navy-900">${escapeHtml(ws.creator_name || "Admin")}</div>
            <div class="text-[11px]">${sourceBadge}</div>
          </td>
          <td class="py-3.5 px-4 text-[12px] text-[#64748b] whitespace-nowrap">
            ${formatThaiDate(ws.updated_at)}
          </td>
          <td class="py-3.5 px-5 text-right whitespace-nowrap">
            <div class="inline-flex items-center gap-1.5">
              <button type="button" class="btn-quick-preview h-8 px-2.5 rounded-lg border border-[#dce4ef] hover:bg-slate-50 text-navy-900 font-bold text-[12px] transition" data-id="${ws.id}">
                ดูตัวอย่าง
              </button>
              <button type="button" class="btn-card-assign h-8 px-3 rounded-lg bg-pink-50 hover:bg-pink-500 text-pink-600 hover:text-white font-bold text-[12px] transition" data-id="${ws.id}" data-title="${escapeHtml(ws.title)}">
                ใช้กับคลาส
              </button>
              <button type="button" class="action-duplicate p-1.5 rounded-lg text-[#64748b] hover:text-pink-600 hover:bg-pink-50 transition" data-id="${ws.id}" title="สร้างสำเนา">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
              <button type="button" class="action-delete h-8 px-2.5 rounded-lg border border-red-200 bg-red-50/60 hover:bg-red-600 hover:border-red-600 text-red-600 hover:text-white font-bold text-[12px] transition flex items-center gap-1" data-id="${ws.id}" data-title="${escapeHtml(ws.title)}" title="ลบใบงาน">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span>ลบ</span>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join("");
  }

  // Update view mode toggle buttons
  function updateViewButtons() {
    if (currentViewMode === "grid") {
      btnViewGrid.classList.add("bg-white", "text-pink-600", "shadow-xs");
      btnViewGrid.classList.remove("text-[#64748b]");
      btnViewList.classList.remove("bg-white", "text-pink-600", "shadow-xs");
      btnViewList.classList.add("text-[#64748b]");
    } else {
      btnViewList.classList.add("bg-white", "text-pink-600", "shadow-xs");
      btnViewList.classList.remove("text-[#64748b]");
      btnViewGrid.classList.remove("bg-white", "text-pink-600", "shadow-xs");
      btnViewGrid.classList.add("text-[#64748b]");
    }
  }

  // Open Preview Modal with Live Questions
  async function openPreview(id) {
    try {
      const data = await fetchApi(`worksheets-api.php?action=get&id=${id}`);
      const ws = data.worksheet;
      activePreviewWorksheet = ws;

      document.getElementById("preview-title").textContent = ws.title;
      document.getElementById("preview-subject-badge").textContent = ws.subject;
      document.getElementById("preview-level-badge").textContent = ws.level;
      document.getElementById("preview-source-badge").textContent = ws.generation_source === "ai" ? "✨ AI Generated" : "✍️ ครูสร้างเอง";
      document.getElementById("preview-topic").textContent = ws.topic || "—";
      document.getElementById("preview-diff").textContent = ws.difficulty;
      document.getElementById("preview-count").textContent = `${ws.questions?.length || 0} ข้อ`;
      document.getElementById("preview-creator").textContent = ws.creator_name || "Admin";
      document.getElementById("preview-full-detail-link").href = `worksheet-detail.php?id=${ws.id}`;

      renderPreviewQuestions();
      previewModal.classList.remove("hidden");
    } catch (err) {
      showToast("ไม่สามารถโหลดตัวอย่างใบงานได้: " + err.message, "error");
    }
  }

  // Render questions inside Preview Modal according to Teacher/Student View
  function renderPreviewQuestions() {
    const list = document.getElementById("preview-questions-list");
    if (!activePreviewWorksheet || !activePreviewWorksheet.questions?.length) {
      list.innerHTML = `<div class="p-8 text-center text-[#64748b] bg-white rounded-xl">ยังไม่มีข้อคำถามในใบงานนี้</div>`;
      return;
    }

    const isTeacher = previewMode === "teacher";
    list.innerHTML = activePreviewWorksheet.questions.map((q, idx) => {
      let optionsHtml = "";
      if (Array.isArray(q.options) && q.options.length) {
        optionsHtml = `
          <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
            ${q.options.map((opt, optIdx) => {
              const isCorrect = isTeacher && (opt === q.correct_answer || String(optIdx) === q.correct_answer);
              const optClass = isCorrect
                ? "border-emerald-500 bg-emerald-50/80 text-emerald-900 font-bold"
                : "border-[#e2e8f0] bg-white text-navy-950";
              return `
                <div class="p-3 rounded-xl border text-[13px] flex items-center gap-2.5 ${optClass}">
                  <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">
                    ${String.fromCharCode(65 + optIdx)}
                  </span>
                  <span class="flex-1">${escapeHtml(opt)}</span>
                  ${isCorrect ? `<span class="text-emerald-600 font-bold text-xs shrink-0">✓ คำตอบที่ถูก</span>` : ""}
                </div>
              `;
            }).join("")}
          </div>
        `;
      }

      const teacherMetaHtml = isTeacher ? `
        <div class="mt-3 pt-3 border-t border-[#f1f5f9] text-[12px] space-y-1.5 bg-[#fbfcfe] -mx-4 -mb-4 p-4 rounded-b-xl">
          ${q.correct_answer && (!q.options || !q.options.length) ? `
            <div class="text-emerald-700 font-bold">
              <span class="text-[#64748b]">เฉลยคำตอบ:</span> ${escapeHtml(q.correct_answer)}
            </div>
          ` : ""}
          ${q.explanation ? `
            <div class="text-[#475569]">
              <span class="font-bold text-navy-900">💡 คำอธิบายเฉลย:</span> ${escapeHtml(q.explanation)}
            </div>
          ` : ""}
          <div class="flex items-center gap-3 text-[11px] text-[#64748b] pt-1">
            ${q.skill ? `<span>ทักษะ: <strong>${escapeHtml(q.skill)}</strong></span>` : ""}
            ${q.learning_objective ? `<span>วัตถุประสงค์: <strong>${escapeHtml(q.learning_objective)}</strong></span>` : ""}
          </div>
        </div>
      ` : "";

      return `
        <div class="p-4 bg-white rounded-xl border border-[#e8ecf2] shadow-xs">
          <div class="flex items-start gap-3">
            <span class="w-7 h-7 rounded-lg bg-pink-50 text-pink-600 font-black text-[13px] flex items-center justify-center shrink-0">
              ${idx + 1}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-bold text-navy-950 whitespace-pre-wrap leading-relaxed">
                ${escapeHtml(q.question_text)}
              </p>
              ${optionsHtml}
              ${teacherMetaHtml}
            </div>
          </div>
        </div>
      `;
    }).join("");
  }

  // Open Class Assignment Dialog
  async function openAssignment(id, title) {
    document.getElementById("assign-worksheet-id").value = id;
    document.getElementById("assign-worksheet-title").textContent = title;

    // Load available courses for select
    const courseSelect = document.getElementById("assign-course-select");
    try {
      const res = await fetch("curriculum-api");
      if (res.ok) {
        const d = await res.json();
        if (Array.isArray(d.courses)) {
          courseSelect.innerHTML = `<option value="">-- ไม่ระบุคอร์ส / มอบหมายอิสระ --</option>` +
            d.courses.map(c => `<option value="${c.id}">${escapeHtml(c.title)}</option>`).join("");
        }
      }
    } catch {
      // Keep existing option if api unavailable
    }

    assignModal.classList.remove("hidden");
  }

  // Duplicate Worksheet handler
  async function duplicateWorksheet(id) {
    if (!confirm("คุณต้องการสร้างสำเนาใบงานนี้เพื่อนำไปปรับแต่งใช่หรือไม่? (ใบงานต้นฉบับจะไม่ถูกแก้ไข)")) return;
    try {
      const res = await fetchApi("worksheets-api.php?action=duplicate", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      showToast("สร้างสำเนาใบงานสำเร็จแล้ว! กำลังเปิดหน้าแก้ไข...");
      setTimeout(() => {
        window.location.href = `worksheet-detail.php?id=${res.id}`;
      }, 1000);
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  // Archive Worksheet handler
  async function archiveWorksheet(id) {
    if (!confirm("ต้องการจัดเก็บ (Archive) ใบงานนี้ใช่หรือไม่? ใบงานจะไม่ถูกลบ และสามารถเรียกดูได้จากตัวกรองสถานะ")) return;
    try {
      await fetchApi("worksheets-api.php?action=archive", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      showToast("จัดเก็บใบงานเรียบร้อยแล้ว");
      loadWorksheets();
    } catch (err) {
      showToast(err.message, "error");
    }
  }

  // Delete Worksheet with safety warning if assigned
  async function deleteWorksheet(id, title = "") {
    const nameStr = title ? ` "${title}"` : "";
    if (!confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบใบงาน${nameStr}?\n\nคำเตือน: หากลบแล้วจะไม่สามารถเรียกคืนได้`)) return;
    try {
      await fetchApi("worksheets-api.php?action=delete", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      showToast("ลบใบงานเรียบร้อยแล้ว");
      if (previewModal && !previewModal.classList.contains("hidden")) {
        previewModal.classList.add("hidden");
      }
      loadWorksheets();
    } catch (err) {
      if (err.message && err.message.includes("กำลังถูกใช้งาน")) {
        if (confirm(`${err.message}\n\nคุณต้องการลบถาวรแบบบังคับ (Force Delete) หรือไม่?`)) {
          try {
            await fetchApi("worksheets-api.php?action=delete", {
              method: "POST",
              body: JSON.stringify({ id, force: true })
            });
            showToast("ลบใบงานแบบบังคับเรียบร้อยแล้ว");
            if (previewModal && !previewModal.classList.contains("hidden")) {
              previewModal.classList.add("hidden");
            }
            loadWorksheets();
          } catch (forceErr) {
            showToast(forceErr.message, "error");
          }
        }
      } else {
        showToast(err.message, "error");
      }
    }
  }

  // Setup Event Listeners
  function setupEventListeners() {
    // Search input with debounce
    searchInput.addEventListener("input", () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(loadWorksheets, 350);
    });

    // Filters change
    [filterSubject, filterLevel, filterType, filterDifficulty, filterSource, filterStatus].forEach(el => {
      el.addEventListener("change", loadWorksheets);
    });

    // Clear filters
    btnClearFilters.addEventListener("click", () => {
      searchInput.value = "";
      filterSubject.value = "";
      filterLevel.value = "";
      filterType.value = "";
      filterDifficulty.value = "";
      filterSource.value = "";
      filterStatus.value = "";
      activeFolderId = null;
      loadWorksheets();
    });

    // View mode switches
    btnViewGrid.addEventListener("click", () => {
      currentViewMode = "grid";
      localStorage.setItem("nb_worksheet_view_mode", "grid");
      renderWorksheets();
    });

    btnViewList.addEventListener("click", () => {
      currentViewMode = "list";
      localStorage.setItem("nb_worksheet_view_mode", "list");
      renderWorksheets();
    });

    // Folder tabs click
    folderTabContainer.addEventListener("click", e => {
      const tab = e.target.closest(".folder-tab");
      if (!tab) return;
      const fId = tab.dataset.folder;
      activeFolderId = fId ? parseInt(fId, 10) : null;
      renderFolderTabs();
      loadWorksheets();
    });

    // Add Folder button
    btnAddFolder.addEventListener("click", async () => {
      const name = prompt("ตั้งชื่อโฟลเดอร์ใหม่:");
      if (!name || !name.trim()) return;
      try {
        await fetchApi("worksheets-api.php?action=create_folder", {
          method: "POST",
          body: JSON.stringify({ name: name.trim() })
        });
        showToast("สร้างโฟลเดอร์สำเร็จ");
        loadWorksheets();
      } catch (err) {
        showToast(err.message, "error");
      }
    });

    // Preview modal delete button
    document.getElementById("preview-btn-delete")?.addEventListener("click", () => {
      if (activePreviewWorksheet && activePreviewWorksheet.id) {
        deleteWorksheet(activePreviewWorksheet.id, activePreviewWorksheet.title);
      }
    });

    // Delegation for Cards & Table actions
    document.addEventListener("click", e => {
      // Close any open card menus if clicking outside
      if (!e.target.closest(".btn-card-menu") && !e.target.closest(".card-dropdown")) {
        document.querySelectorAll(".card-dropdown").forEach(d => d.classList.add("hidden"));
      }

      // Toggle Card Menu
      const menuBtn = e.target.closest(".btn-card-menu");
      if (menuBtn) {
        const id = menuBtn.dataset.id;
        const dropdown = document.getElementById(`menu-dropdown-${id}`);
        const isHidden = dropdown.classList.contains("hidden");
        document.querySelectorAll(".card-dropdown").forEach(d => d.classList.add("hidden"));
        if (isHidden) dropdown.classList.remove("hidden");
        return;
      }

      // Quick Preview
      const previewBtn = e.target.closest(".btn-quick-preview");
      if (previewBtn) {
        openPreview(previewBtn.dataset.id);
        return;
      }

      // Card Assign
      const assignBtn = e.target.closest(".btn-card-assign");
      if (assignBtn) {
        openAssignment(assignBtn.dataset.id, assignBtn.dataset.title);
        return;
      }

      // Duplicate
      const dupBtn = e.target.closest(".action-duplicate");
      if (dupBtn) {
        duplicateWorksheet(dupBtn.dataset.id);
        return;
      }

      // Archive
      const arcBtn = e.target.closest(".action-archive");
      if (arcBtn) {
        archiveWorksheet(arcBtn.dataset.id);
        return;
      }

      // Delete
      const delBtn = e.target.closest(".action-delete");
      if (delBtn) {
        deleteWorksheet(delBtn.dataset.id, delBtn.dataset.title);
        return;
      }

      // Move folder
      const moveBtn = e.target.closest(".action-move-folder");
      if (moveBtn) {
        const id = moveBtn.dataset.id;
        const folderNames = folders.map((f, i) => `${i + 1}. ${f.name}`).join("\n");
        const pick = prompt(`เลือกหมายเลขโฟลเดอร์ที่ต้องการย้ายไป (หรือพิมพ์ 0 เพื่อยกเลิกออกจากโฟลเดอร์):\n\n${folderNames}`);
        if (pick !== null) {
          const idx = parseInt(pick, 10);
          let targetFolderId = null;
          if (idx > 0 && folders[idx - 1]) targetFolderId = folders[idx - 1].id;
          fetchApi("worksheets-api.php?action=move_folder", {
            method: "POST",
            body: JSON.stringify({ worksheet_id: id, folder_id: targetFolderId })
          }).then(() => {
            showToast("ย้ายโฟลเดอร์สำเร็จ");
            loadWorksheets();
          }).catch(err => showToast(err.message, "error"));
        }
        return;
      }

      // Print / Export
      const printBtn = e.target.closest(".action-print");
      if (printBtn) {
        window.open(`worksheet-detail.php?id=${printBtn.dataset.id}&print=1`, "_blank");
        return;
      }
    });

    // Preview View Mode Toggle
    document.getElementById("btn-view-student").addEventListener("click", () => {
      previewMode = "student";
      document.getElementById("btn-view-student").classList.add("bg-white", "text-pink-600", "shadow-sm");
      document.getElementById("btn-view-student").classList.remove("text-[#64748b]");
      document.getElementById("btn-view-teacher").classList.remove("bg-white", "text-pink-600", "shadow-sm");
      document.getElementById("btn-view-teacher").classList.add("text-[#64748b]");
      renderPreviewQuestions();
    });

    document.getElementById("btn-view-teacher").addEventListener("click", () => {
      previewMode = "teacher";
      document.getElementById("btn-view-teacher").classList.add("bg-white", "text-pink-600", "shadow-sm");
      document.getElementById("btn-view-teacher").classList.remove("text-[#64748b]");
      document.getElementById("btn-view-student").classList.remove("bg-white", "text-pink-600", "shadow-sm");
      document.getElementById("btn-view-student").classList.add("text-[#64748b]");
      renderPreviewQuestions();
    });

    // Preview actions
    document.getElementById("btn-close-preview").addEventListener("click", () => {
      previewModal.classList.add("hidden");
    });
    document.getElementById("preview-btn-print").addEventListener("click", () => {
      if (activePreviewWorksheet) {
        window.open(`worksheet-detail.php?id=${activePreviewWorksheet.id}&print=1`, "_blank");
      }
    });
    document.getElementById("preview-btn-assign").addEventListener("click", () => {
      if (activePreviewWorksheet) {
        previewModal.classList.add("hidden");
        openAssignment(activePreviewWorksheet.id, activePreviewWorksheet.title);
      }
    });

    // Assign form submit
    const assignForm = document.getElementById("assign-form");
    assignForm.addEventListener("submit", async e => {
      e.preventDefault();
      const errBox = document.getElementById("assign-error");
      errBox.classList.add("hidden");
      const data = Object.fromEntries(new FormData(assignForm));
      try {
        await fetchApi("worksheets-api.php?action=assign", {
          method: "POST",
          body: JSON.stringify(data)
        });
        assignModal.classList.add("hidden");
        showToast("มอบหมายใบงานให้คลาสเรียบร้อยแล้ว");
        loadWorksheets();
      } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove("hidden");
      }
    });
    document.getElementById("btn-close-assign").addEventListener("click", () => assignModal.classList.add("hidden"));
    document.getElementById("btn-cancel-assign").addEventListener("click", () => assignModal.classList.add("hidden"));

    // Manual Create Modal
    document.getElementById("btn-create-manual").addEventListener("click", () => {
      manualModal.classList.remove("hidden");
    });
    document.getElementById("btn-close-manual").addEventListener("click", () => manualModal.classList.add("hidden"));
    document.getElementById("btn-cancel-manual").addEventListener("click", () => manualModal.classList.add("hidden"));

    const manualForm = document.getElementById("manual-create-form");
    manualForm.addEventListener("submit", async e => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(manualForm));
      data.generation_source = "manual";
      data.questions = [];
      try {
        const res = await fetchApi("worksheets-api.php?action=create", {
          method: "POST",
          body: JSON.stringify(data)
        });
        manualModal.classList.add("hidden");
        showToast("สร้างใบงานเรียบร้อยแล้ว กำลังเปิดหน้ารายละเอียด...");
        setTimeout(() => {
          window.location.href = `worksheet-detail.php?id=${res.id}`;
        }, 800);
      } catch (err) {
        showToast(err.message, "error");
      }
    });
  }

  // Initialize
  setupEventListeners();
  loadWorksheets();
})();
