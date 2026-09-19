/**
 * Nextbeyond Compass - AI Worksheet Generator Controller
 */
(() => {
  "use strict";

  const form = document.getElementById("ai-worksheet-form");
  const countInput = document.getElementById("ai-count");
  const countDisplay = document.getElementById("count-display");
  const errorBox = document.getElementById("ai-form-error");
  const btnGenerate = document.getElementById("btn-generate-ai");
  const btnGenerateText = document.getElementById("btn-generate-text");

  const placeholderView = document.getElementById("preview-placeholder");
  const loadingView = document.getElementById("preview-loading");
  const activeView = document.getElementById("preview-active");
  const questionsList = document.getElementById("generated-questions-list");
  const generatedTitle = document.getElementById("generated-title");
  const liveCountBadge = document.getElementById("live-question-count-badge");

  const successModal = document.getElementById("success-modal");
  const btnOpenWorksheet = document.getElementById("btn-open-worksheet");
  const btnCreateAnother = document.getElementById("btn-create-another");

  let currentGeneration = null;
  let savedWorksheetId = null;

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

  // Update slider question count
  countInput.addEventListener("input", () => {
    countDisplay.textContent = `${countInput.value} ข้อ`;
  });

  // Handle Form Submission -> AI Generate
  form.addEventListener("submit", async e => {
    e.preventDefault();
    errorBox.classList.add("hidden");

    const formData = new FormData(form);
    const qTypes = formData.getAll("question_types[]");
    if (!qTypes.length) {
      errorBox.textContent = "กรุณาเลือกรูปแบบคำถามอย่างน้อย 1 รูปแบบ";
      errorBox.classList.remove("hidden");
      return;
    }

    const payload = {
      subject: formData.get("subject"),
      level: formData.get("level"),
      chapter: formData.get("chapter"),
      topic: formData.get("topic"),
      subtopic: formData.get("subtopic"),
      worksheet_type: formData.get("worksheet_type"),
      difficulty: formData.get("difficulty"),
      count: parseInt(formData.get("count"), 10),
      question_types: qTypes,
      learning_objective: formData.get("learning_objective"),
      instructions: formData.get("instructions")
    };

    placeholderView.classList.add("hidden");
    activeView.classList.add("hidden");
    loadingView.classList.remove("hidden");
    btnGenerate.disabled = true;
    btnGenerateText.textContent = "กำลังสร้างคำถามด้วย AI...";

    try {
      const res = await fetch("worksheets-api.php?action=generate_ai", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || "การสร้างใบงานล้มเหลว");
      }

      currentGeneration = data;
      renderGenerationResult(data);
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.remove("hidden");
      placeholderView.classList.remove("hidden");
    } finally {
      loadingView.classList.add("hidden");
      btnGenerate.disabled = false;
      btnGenerateText.textContent = "สร้างใบงานด้วย AI (Generate)";
    }
  });

  // Render generated questions
  function renderGenerationResult(data) {
    generatedTitle.textContent = data.suggested_title || `${data.topic} (${data.worksheet_type})`;
    document.getElementById("gen-badge-subject").textContent = data.subject;
    document.getElementById("gen-badge-level").textContent = data.level;
    document.getElementById("gen-badge-type").textContent = data.worksheet_type;
    document.getElementById("gen-badge-diff").textContent = `ความยาก: ${data.difficulty}`;

    renderQuestionsList(data.questions || []);
    activeView.classList.remove("hidden");
    activeView.scrollIntoView({ behavior: "smooth" });
  }

  function renderQuestionsList(questions) {
    liveCountBadge.textContent = `${questions.length} ข้อ`;

    questionsList.innerHTML = questions.map((q, idx) => {
      let optionsHtml = "";
      if (Array.isArray(q.options) && q.options.length) {
        optionsHtml = `
          <div class="options-container grid grid-cols-1 sm:grid-cols-2 gap-2.5 my-3">
            ${q.options.map((opt, optIdx) => {
              const isCorrect = (opt === q.correct_answer || String(optIdx) === String(q.correct_answer));
              const optClass = isCorrect
                ? "border-emerald-500 bg-emerald-50/80 text-emerald-950 font-bold"
                : "border-[#e2e8f0] bg-[#f8fafc] text-navy-950";
              return `
                <div class="opt-item p-3 rounded-xl border text-[13px] flex items-center gap-2.5 ${optClass}" data-idx="${optIdx}">
                  <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">
                    ${String.fromCharCode(65 + optIdx)}
                  </span>
                  <span class="opt-text flex-1 outline-none" contenteditable="true">${escapeHtml(opt)}</span>
                  ${isCorrect ? `<span class="text-emerald-600 font-bold text-xs shrink-0">✓ เฉลย</span>` : ""}
                </div>
              `;
            }).join("")}
          </div>
        `;
      }

      return `
        <div class="generated-q-card bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm transition-all" data-order="${idx + 1}">
          <div class="flex items-start gap-3">
            <span class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 font-black text-[14px] flex items-center justify-center shrink-0">
              ${idx + 1}
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 mb-2">
                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[11px]">
                  ${escapeHtml(q.question_type)}
                </span>
                <div class="flex items-center gap-1 text-[13px]">
                  <button type="button" class="btn-q-up p-1 text-[#94a3b8] hover:text-navy-950 transition" title="เลื่อนขึ้น">↑</button>
                  <button type="button" class="btn-q-down p-1 text-[#94a3b8] hover:text-navy-950 transition" title="เลื่อนลง">↓</button>
                  <button type="button" class="btn-q-del p-1 text-red-400 hover:text-red-600 transition" title="ลบข้อนี้">🗑️</button>
                </div>
              </div>

              <!-- Question text editable -->
              <p class="q-text font-bold text-navy-950 text-[15px] leading-relaxed whitespace-pre-wrap outline-none hover:bg-slate-50 p-1.5 rounded-lg transition" contenteditable="true">
                ${escapeHtml(q.question_text)}
              </p>

              ${optionsHtml}

              <!-- Explanation & Meta Box -->
              <div class="mt-3 pt-3 border-t border-[#f1f5f9] text-[12px] bg-[#fbfcfe] -mx-5 -mb-5 p-4 rounded-b-2xl space-y-1.5">
                ${q.correct_answer && (!q.options || !q.options.length) ? `
                  <div class="text-emerald-700 font-bold">
                    <span class="text-[#64748b]">เฉลยคำตอบ:</span>
                    <span class="outline-none" contenteditable="true" data-field="correct_answer">${escapeHtml(q.correct_answer)}</span>
                  </div>
                ` : ""}
                <div class="text-[#475569]">
                  <span class="font-bold text-navy-900">💡 คำอธิบายเฉลย:</span>
                  <span class="outline-none" contenteditable="true" data-field="explanation">${escapeHtml(q.explanation || "—")}</span>
                </div>
                ${q.learning_objective ? `
                  <div class="text-[11px] text-[#64748b] pt-1">
                    เป้าหมาย: <strong class="text-navy-900">${escapeHtml(q.learning_objective)}</strong>
                  </div>
                ` : ""}
              </div>
            </div>
          </div>
        </div>
      `;
    }).join("");
  }

  // Collect modified questions from DOM
  function collectCurrentQuestions() {
    const cards = questionsList.querySelectorAll(".generated-q-card");
    const list = [];
    cards.forEach((card, idx) => {
      const qText = card.querySelector(".q-text")?.innerText.trim() || "";
      const optTexts = Array.from(card.querySelectorAll(".opt-text")).map(o => o.innerText.trim());
      const correctText = card.querySelector("[data-field='correct_answer']")?.innerText.trim() || "";
      const explanation = card.querySelector("[data-field='explanation']")?.innerText.trim() || "";

      list.push({
        sort_order: idx + 1,
        question_type: optTexts.length ? "multipleChoice" : "shortAnswer",
        question_text: qText,
        options: optTexts,
        correct_answer: correctText || optTexts[0] || "",
        explanation: explanation,
        difficulty: currentGeneration?.difficulty || "medium",
        skill: currentGeneration?.topic || ""
      });
    });
    return list;
  }

  // Save to Library Action
  async function saveToLibrary(status = "published") {
    if (!currentGeneration) return;

    const questions = collectCurrentQuestions();
    if (!questions.length) {
      alert("ไม่มีคำถามในใบงาน กรุณาเพิ่มคำถามก่อนบันทึก");
      return;
    }

    const payload = {
      title: generatedTitle.innerText.trim() || "ใบงานสร้างโดย AI",
      subject: currentGeneration.subject,
      level: currentGeneration.level,
      chapter: currentGeneration.chapter,
      topic: currentGeneration.topic,
      subtopic: currentGeneration.subtopic,
      worksheet_type: currentGeneration.worksheet_type,
      difficulty: currentGeneration.difficulty,
      generation_source: "ai",
      status: status,
      questions: questions,
      tags: [currentGeneration.subject, currentGeneration.level, currentGeneration.topic, "AI Generated"]
    };

    const saveBtn = document.getElementById("btn-save-library");
    saveBtn.disabled = true;

    try {
      const res = await fetch("worksheets-api.php?action=create", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "บันทึกไม่สำเร็จ");

      savedWorksheetId = data.id;
      showToast("บันทึกใบงานเข้าคลังแล้ว");

      btnOpenWorksheet.href = `worksheet-detail.php?id=${savedWorksheetId}`;
      successModal.classList.remove("hidden");
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      saveBtn.disabled = false;
    }
  }

  document.getElementById("btn-save-library").addEventListener("click", () => saveToLibrary("published"));
  document.getElementById("btn-save-draft").addEventListener("click", () => saveToLibrary("draft"));

  btnCreateAnother.addEventListener("click", () => {
    successModal.classList.add("hidden");
    placeholderView.classList.remove("hidden");
    activeView.classList.add("hidden");
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  // Questions manipulation: Reorder and Delete
  document.addEventListener("click", e => {
    const delBtn = e.target.closest(".btn-q-del");
    if (delBtn) {
      const card = delBtn.closest(".generated-q-card");
      if (confirm("ต้องการลบคำถามข้อนี้ใช่หรือไม่?")) {
        card?.remove();
        const remaining = questionsList.querySelectorAll(".generated-q-card").length;
        liveCountBadge.textContent = `${remaining} ข้อ`;
      }
      return;
    }

    const upBtn = e.target.closest(".btn-q-up");
    if (upBtn) {
      const card = upBtn.closest(".generated-q-card");
      const prev = card.previousElementSibling;
      if (prev) {
        card.parentNode.insertBefore(card, prev);
      }
      return;
    }

    const downBtn = e.target.closest(".btn-q-down");
    if (downBtn) {
      const card = downBtn.closest(".generated-q-card");
      const next = card.nextElementSibling;
      if (next) {
        card.parentNode.insertBefore(next, card);
      }
      return;
    }
  });
})();
