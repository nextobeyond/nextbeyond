/**
 * Nextbeyond Compass - Smart Question Search ("ค้นจากคลังข้อสอบ")
 * Hybrid Retrieval Client Controller (Semantic + Metadata + Keyword + MMR Diversity)
 */
(() => {
  "use strict";

  // Elements
  const modal = document.getElementById("smart-question-search-modal");
  const btnOpenModal = document.getElementById("btn-open-question-search");
  const btnCloseModal = document.getElementById("btn-close-qs-modal");
  const modalBackdrop = document.getElementById("qs-modal-backdrop");

  const queryInput = document.getElementById("qs-query-input");
  const btnSearch = document.getElementById("btn-run-qs-search");
  const filterSubject = document.getElementById("qs-filter-subject");
  const filterLevel = document.getElementById("qs-filter-level");
  const filterDifficulty = document.getElementById("qs-filter-difficulty");
  const filterTargetCount = document.getElementById("qs-target-count");
  const checkExcludeExisting = document.getElementById("qs-exclude-existing");

  const intentBanner = document.getElementById("qs-intent-banner");
  const intentTags = document.getElementById("qs-intent-tags");
  const resultsContainer = document.getElementById("qs-results-container");
  const loadingIndicator = document.getElementById("qs-loading");
  const emptyState = document.getElementById("qs-empty");
  const insufficientNotice = document.getElementById("qs-insufficient-notice");
  const insufficientText = document.getElementById("qs-insufficient-text");

  const btnSmartSelect = document.getElementById("btn-qs-smart-select");
  const btnSelectAll = document.getElementById("btn-qs-select-all");
  const btnClearSelect = document.getElementById("btn-qs-clear-select");

  const stickyBar = document.getElementById("qs-sticky-bar");
  const selectedCountDisplay = document.getElementById("qs-selected-count");
  const targetCountDisplay = document.getElementById("qs-target-display");
  const btnInsertQuestions = document.getElementById("btn-qs-insert");
  const btnInsertCount = document.getElementById("btn-qs-insert-count");
  const insertPositionSelect = document.getElementById("qs-insert-position");

  // Worksheet context
  const currentWorksheetId = window.NB_CURRENT_WORKSHEET_ID || (new URLSearchParams(window.location.search).get("id")) || 0;

  // Search State
  let currentCandidates = [];
  let currentRecommended = [];
  let selectedQuestionIds = new Set();
  let searchDebounce = null;
  let isSearching = false;

  const escapeHtml = str => String(str ?? "").replace(/[&<>'"]/g, c => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"
  }[c]));

  // Open Modal
  function openModal(presetQuery = "") {
    if (!modal) return;
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";

    if (presetQuery) {
      queryInput.value = presetQuery;
      triggerSearch();
    } else if (currentCandidates.length === 0 && !queryInput.value.trim()) {
      // If empty, auto populate placeholder / default search based on worksheet subject/topic
      const wsTopic = window.NB_WORKSHEET_TOPIC || "";
      const wsSubject = window.NB_WORKSHEET_SUBJECT || "";
      if (wsSubject && filterSubject && !filterSubject.value) {
        filterSubject.value = wsSubject;
      }
      triggerSearch();
    }

    setTimeout(() => {
      queryInput?.focus();
    }, 100);
  }

  // Close Modal
  function closeModal() {
    if (!modal) return;
    modal.classList.add("hidden");
    document.body.style.overflow = "";
  }

  // Update selection UI
  function updateSelectionUI() {
    const count = selectedQuestionIds.size;
    const target = parseInt(filterTargetCount?.value || "10", 10);

    if (selectedCountDisplay) selectedCountDisplay.textContent = count;
    if (targetCountDisplay) targetCountDisplay.textContent = target;
    if (btnInsertCount) btnInsertCount.textContent = count;

    if (count > 0) {
      stickyBar?.classList.remove("translate-y-full", "opacity-0", "pointer-events-none");
      stickyBar?.classList.add("translate-y-0", "opacity-100");
    } else {
      stickyBar?.classList.add("translate-y-full", "opacity-0", "pointer-events-none");
      stickyBar?.classList.remove("translate-y-0", "opacity-100");
    }

    // Highlight selected cards
    document.querySelectorAll(".qs-card").forEach(card => {
      const qId = parseInt(card.dataset.id, 10);
      const chk = card.querySelector(".qs-checkbox");
      if (selectedQuestionIds.has(qId)) {
        card.classList.add("border-pink-500", "bg-pink-50/20", "ring-1", "ring-pink-400");
        card.classList.remove("border-[#e2e8f0]");
        if (chk) chk.checked = true;
      } else {
        card.classList.remove("border-pink-500", "bg-pink-50/20", "ring-1", "ring-pink-400");
        card.classList.add("border-[#e2e8f0]");
        if (chk) chk.checked = false;
      }
    });
  }

  // Render question candidates
  function renderCandidates(candidates, recommended, intent) {
    if (!resultsContainer) return;
    resultsContainer.innerHTML = "";

    // Show Intent breakdown tags if available
    if (intent && (intent.level || intent.topic || intent.difficulty || intent.keywords?.length || intent.contexts?.length)) {
      intentBanner?.classList.remove("hidden");
      const tagsHtml = [];
      if (intent.subject) tagsHtml.push(`<span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 text-[11px] font-bold">วิชา: ${escapeHtml(intent.subject)}</span>`);
      if (intent.level) tagsHtml.push(`<span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 text-[11px] font-bold">ระดับ: ${escapeHtml(intent.level)}</span>`);
      if (intent.topic) tagsHtml.push(`<span class="px-2 py-0.5 rounded-md bg-pink-100 text-pink-800 text-[11px] font-bold">หัวข้อ: ${escapeHtml(intent.topic)}</span>`);
      if (intent.difficulty) tagsHtml.push(`<span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 text-[11px] font-bold">ความยาก: ${escapeHtml(intent.difficulty)}</span>`);
      if (intent.contexts && intent.contexts.length) {
        intent.contexts.forEach(ctx => {
          tagsHtml.push(`<span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[11px] font-bold">เน้น: ${escapeHtml(ctx)}</span>`);
        });
      }
      if (intentTags) intentTags.innerHTML = tagsHtml.join(" ");
    } else {
      intentBanner?.classList.add("hidden");
    }

    // Check empty state
    if (!candidates || candidates.length === 0) {
      emptyState?.classList.remove("hidden");
      insufficientNotice?.classList.add("hidden");
      return;
    }
    emptyState?.classList.add("hidden");

    // Check insufficient results notice (Section 16)
    const targetCount = parseInt(filterTargetCount?.value || "10", 10);
    if (candidates.length < targetCount) {
      insufficientNotice?.classList.remove("hidden");
      if (insufficientText) {
        insufficientText.textContent = `พบข้อสอบที่ตรงกับเงื่อนไข ${candidates.length} ข้อ (ต้องการ ${targetCount} ข้อ)`;
      }
    } else {
      insufficientNotice?.classList.add("hidden");
    }

    // Set of recommended IDs for visual tagging
    const recIdSet = new Set(recommended.map(r => r.id));

    candidates.forEach((cand, idx) => {
      const qId = cand.id;
      const isSelected = selectedQuestionIds.has(qId);
      const isRecommended = recIdSet.has(qId);
      const opts = Array.isArray(cand.options) ? cand.options : [];
      const reasons = Array.isArray(cand.match_reasons) ? cand.match_reasons : [];

      const card = document.createElement("div");
      card.className = `qs-card bg-white rounded-2xl border transition-all p-5 shadow-xs hover:shadow-md cursor-pointer ${
        isSelected ? "border-pink-500 bg-pink-50/20 ring-1 ring-pink-400" : "border-[#e2e8f0] hover:border-pink-300"
      }`;
      card.dataset.id = qId;

      // Difficulty styling
      let diffBadge = "";
      const diff = (cand.difficulty || "").toLowerCase();
      if (diff === "easy" || diff === "ง่าย") {
        diffBadge = '<span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-[11px] font-bold border border-emerald-200">ง่าย</span>';
      } else if (diff === "hard" || diff === "ยาก") {
        diffBadge = '<span class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 text-[11px] font-bold border border-rose-200">ยาก</span>';
      } else if (diff === "expert") {
        diffBadge = '<span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 text-[11px] font-bold border border-purple-200">ขั้นสูง (A-Level)</span>';
      } else {
        diffBadge = '<span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[11px] font-bold border border-amber-200">ปานกลาง</span>';
      }

      // Semantic match reasons
      const reasonBadges = reasons.map(r => `<span class="px-2 py-0.5 rounded-md bg-pink-50 text-pink-700 text-[10px] font-bold">🎯 ${escapeHtml(r)}</span>`).join(" ");

      // Options HTML
      let optionsHtml = "";
      if (opts.length > 0) {
        optionsHtml = `
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 my-3 text-[13px]">
            ${opts.map((opt, oIdx) => {
              const letter = String.fromCharCode(65 + oIdx);
              const isCorrect = (cand.correct_answer_text === opt || String(cand.correct_answer) === String(oIdx));
              return `
                <div class="p-2.5 rounded-xl border flex items-center gap-2 ${
                  isCorrect ? "opt-correct-choice border-emerald-400 bg-emerald-50/70 text-emerald-950 font-semibold" : "border-[#e2e8f0] bg-[#f8fafc] text-navy-950"
                }">
                  <span class="w-5 h-5 rounded-full border border-current flex items-center justify-center shrink-0 text-[10px] font-bold">${letter}</span>
                  <span class="truncate flex-1">${escapeHtml(opt)}</span>
                  ${isCorrect ? '<span class="text-[10px] text-emerald-600 font-bold ml-auto shrink-0">✓ เฉลย</span>' : ''}
                </div>
              `;
            }).join("")}
          </div>
        `;
      }

      card.innerHTML = `
        <div class="flex items-start gap-3">
          <!-- Selection Checkbox -->
          <div class="pt-0.5 shrink-0" onclick="event.stopPropagation()">
            <input type="checkbox" class="qs-checkbox w-5 h-5 rounded-md text-pink-600 border-[#cbd5e1] focus:ring-pink-500 cursor-pointer" ${isSelected ? "checked" : ""}>
          </div>

          <div class="flex-1 min-w-0">
            <!-- Top Badges -->
            <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="w-6 h-6 rounded-lg bg-navy-950 text-white text-[11px] font-black flex items-center justify-center">Q${idx + 1}</span>
                ${cand.subject ? `<span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 text-[11px] font-bold">${escapeHtml(cand.subject)}</span>` : ""}
                ${cand.grade || cand.level ? `<span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-bold">${escapeHtml(cand.grade || cand.level)}</span>` : ""}
                ${cand.topic ? `<span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[11px] font-bold">${escapeHtml(cand.topic)}</span>` : ""}
                ${diffBadge}
                ${isRecommended ? '<span class="px-2 py-0.5 rounded-md bg-pink-500 text-white text-[10px] font-black tracking-wide">⭐ แนะนำสูงสุด</span>' : ''}
              </div>
              <div class="flex items-center gap-1">
                ${reasonBadges}
              </div>
            </div>

            <!-- Question Text -->
            <p class="font-bold text-navy-950 text-[14px] leading-relaxed mb-2 select-text">${escapeHtml(cand.question_text)}</p>

            <!-- Options Preview -->
            ${optionsHtml}

            <!-- Expandable Answer & Explanation -->
            <div class="qs-answer-container hidden mt-3 pt-3 border-t border-[#f1f5f9] text-[12px] bg-[#fbfcfe] p-3 rounded-xl space-y-1.5">
              ${cand.correct_answer_text ? `
                <div class="text-emerald-800 font-bold">
                  <span class="text-[#64748b] font-normal">เฉลย:</span> ${escapeHtml(cand.correct_answer_text)}
                </div>
              ` : ''}
              ${cand.explanation ? `
                <div class="text-[#475569] leading-relaxed">
                  <span class="font-bold text-navy-900">💡 คำอธิบาย:</span> ${escapeHtml(cand.explanation)}
                </div>
              ` : ''}
              ${cand.learning_objective ? `
                <div class="text-[#64748b] text-[11px]">
                  <span>เป้าหมายการเรียนรู้:</span> <strong class="text-navy-900">${escapeHtml(cand.learning_objective)}</strong>
                </div>
              ` : ''}
            </div>

            <!-- Card Actions -->
            <div class="flex items-center justify-between pt-2 text-[12px] text-[#64748b]" onclick="event.stopPropagation()">
              <button type="button" class="btn-toggle-answer text-pink-600 hover:text-pink-700 font-bold flex items-center gap-1">
                <span>ดูเฉลยและคำอธิบาย</span>
                <svg class="w-3.5 h-3.5 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>

              <span class="text-[11px] text-[#94a3b8]">
                ที่มา: ${cand.source === 'ai' ? 'AI Generated' : 'คลังข้อสอบ'}
              </span>
            </div>
          </div>
        </div>
      `;

      // Toggle Selection on Card Click
      card.addEventListener("click", () => {
        if (selectedQuestionIds.has(qId)) {
          selectedQuestionIds.delete(qId);
        } else {
          selectedQuestionIds.add(qId);
        }
        updateSelectionUI();
      });

      // Checkbox click
      const chk = card.querySelector(".qs-checkbox");
      chk?.addEventListener("change", e => {
        if (e.target.checked) {
          selectedQuestionIds.add(qId);
        } else {
          selectedQuestionIds.delete(qId);
        }
        updateSelectionUI();
      });

      // Toggle Answer & Explanation
      const btnToggle = card.querySelector(".btn-toggle-answer");
      const answerBox = card.querySelector(".qs-answer-container");
      btnToggle?.addEventListener("click", e => {
        e.stopPropagation();
        const isHidden = answerBox.classList.contains("hidden");
        if (isHidden) {
          answerBox.classList.remove("hidden");
          btnToggle.querySelector("span").textContent = "ซ่อนเฉลย";
          btnToggle.querySelector("svg").classList.add("rotate-180");
        } else {
          answerBox.classList.add("hidden");
          btnToggle.querySelector("span").textContent = "ดูเฉลยและคำอธิบาย";
          btnToggle.querySelector("svg").classList.remove("rotate-180");
        }
      });

      resultsContainer.appendChild(card);
    });

    updateSelectionUI();
  }

  // Execute Hybrid Search API Call
  async function triggerSearch() {
    if (isSearching) return;
    isSearching = true;

    loadingIndicator?.classList.remove("hidden");
    resultsContainer?.classList.add("hidden");
    emptyState?.classList.add("hidden");
    insufficientNotice?.classList.add("hidden");

    const query = queryInput?.value.trim() || "";
    const subject = filterSubject?.value || "";
    const level = filterLevel?.value || "";
    const difficulty = filterDifficulty?.value || "";
    const count = parseInt(filterTargetCount?.value || "10", 10);
    const excludeExisting = checkExcludeExisting ? checkExcludeExisting.checked : true;

    try {
      const params = new URLSearchParams({
        action: "search",
        query: query,
        subject: subject,
        level: level,
        difficulty: difficulty,
        count: count,
        worksheet_id: currentWorksheetId,
        exclude_existing: excludeExisting ? "1" : "0"
      });

      const res = await fetch(`question-search-api.php?${params.toString()}`);
      if (!res.ok) {
        throw new Error(`HTTP Error: ${res.status}`);
      }
      const data = await res.json();

      if (data.success) {
        currentCandidates = data.candidates || [];
        currentRecommended = data.recommended || [];
        renderCandidates(currentCandidates, currentRecommended, data.parsedIntent);

        // Update total counter in UI
        const totalCountBadge = document.getElementById("qs-total-found-badge");
        if (totalCountBadge) {
          totalCountBadge.textContent = `${data.totalFound || 0} ข้อ`;
        }
      } else {
        alert(data.error || "ไม่สามารถค้นหาข้อสอบได้");
      }
    } catch (err) {
      console.error("Search error:", err);
      if (emptyState) {
        emptyState.classList.remove("hidden");
      }
    } finally {
      isSearching = false;
      loadingIndicator?.classList.add("hidden");
      resultsContainer?.classList.remove("hidden");
    }
  }

  // Smart Select ("เลือกให้ฉัน" - Section 12)
  function runSmartSelect() {
    if (!currentRecommended || currentRecommended.length === 0) return;
    const targetCount = parseInt(filterTargetCount?.value || "10", 10);

    selectedQuestionIds.clear();
    const toPick = currentRecommended.slice(0, targetCount);
    toPick.forEach(q => selectedQuestionIds.add(q.id));
    updateSelectionUI();
  }

  // Insert Selected Questions to Worksheet (Section 18, 19, 20)
  async function insertSelectedQuestions() {
    if (selectedQuestionIds.size === 0) {
      alert("กรุณาเลือกข้อสอบอย่างน้อย 1 ข้อ");
      return;
    }

    const questionIds = Array.from(selectedQuestionIds);
    const position = insertPositionSelect?.value || "end";

    btnInsertQuestions.disabled = true;
    const originalText = btnInsertQuestions.innerHTML;
    btnInsertQuestions.innerHTML = "กำลังเพิ่มเข้าใบงาน...";

    try {
      const res = await fetch("question-search-api.php?action=insert_to_worksheet", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          worksheet_id: currentWorksheetId,
          question_ids: questionIds,
          position: position
        })
      });

      const data = await res.json();
      if (data.success) {
        closeModal();
        // Trigger live refresh or update Topic Distribution
        if (window.refreshWorksheetView) {
          window.refreshWorksheetView(data);
        } else {
          window.location.reload();
        }
      } else {
        alert(data.error || "เกิดข้อผิดพลาดในการเพิ่มคำถาม");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ: " + err.message);
    } finally {
      btnInsertQuestions.disabled = false;
      btnInsertQuestions.innerHTML = originalText;
    }
  }

  // Event Listeners
  btnOpenModal?.addEventListener("click", () => openModal());
  btnCloseModal?.addEventListener("click", () => closeModal());
  modalBackdrop?.addEventListener("click", () => closeModal());

  // Search input typing debounce & Enter key
  queryInput?.addEventListener("input", () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(triggerSearch, 400);
  });
  queryInput?.addEventListener("keydown", e => {
    if (e.key === "Enter") {
      e.preventDefault();
      clearTimeout(searchDebounce);
      triggerSearch();
    }
  });

  btnSearch?.addEventListener("click", triggerSearch);

  // Filters change
  filterSubject?.addEventListener("change", triggerSearch);
  filterLevel?.addEventListener("change", triggerSearch);
  filterDifficulty?.addEventListener("change", triggerSearch);
  filterTargetCount?.addEventListener("change", () => {
    updateSelectionUI();
    triggerSearch();
  });
  checkExcludeExisting?.addEventListener("change", triggerSearch);

  // Preset search chips
  document.querySelectorAll(".qs-chip").forEach(chip => {
    chip.addEventListener("click", () => {
      const text = chip.dataset.query || chip.textContent.trim();
      if (queryInput) queryInput.value = text;
      triggerSearch();
    });
  });

  // Smart Select & Bulk Select buttons
  btnSmartSelect?.addEventListener("click", runSmartSelect);

  btnSelectAll?.addEventListener("click", () => {
    currentCandidates.forEach(c => selectedQuestionIds.add(c.id));
    updateSelectionUI();
  });

  btnClearSelect?.addEventListener("click", () => {
    selectedQuestionIds.clear();
    updateSelectionUI();
  });

  btnInsertQuestions?.addEventListener("click", insertSelectedQuestions);

  // Quick action from Insufficient Notice button
  document.getElementById("btn-insufficient-use")?.addEventListener("click", () => {
    selectedQuestionIds.clear();
    currentCandidates.forEach(c => selectedQuestionIds.add(c.id));
    updateSelectionUI();
  });

  // Expose global controller
  window.SmartQuestionSearch = {
    open: openModal,
    close: closeModal,
    search: triggerSearch,
    smartSelect: runSmartSelect
  };
})();
