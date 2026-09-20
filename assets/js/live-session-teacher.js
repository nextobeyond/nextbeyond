(() => {
  "use strict";

  // Web Audio Synthesizer for 8-bit / Fantasy Sound Effects
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  let audioCtx = null;
  let battleSoundEnabled = true;

  function getAudioContext() {
    if (!audioCtx && AudioContextClass) {
      audioCtx = new AudioContextClass();
    }
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
    return audioCtx;
  }

  function playBattleSound(type) {
    if (!battleSoundEnabled) return;
    try {
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      if (type === 'strike') {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(440, now);
        osc.frequency.exponentialRampToValueAtTime(110, now + 0.18);
        gain.gain.setValueAtTime(0.3, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.18);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.2);
      } else if (type === 'hit') {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(160, now);
        osc.frequency.exponentialRampToValueAtTime(60, now + 0.25);
        gain.gain.setValueAtTime(0.4, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.26);
      } else if (type === 'victory') {
        const notes = [261.63, 329.63, 392.00, 523.25];
        notes.forEach((freq, idx) => {
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.type = 'sine';
          osc.frequency.value = freq;
          gain.gain.setValueAtTime(0.2, now + idx * 0.1);
          gain.gain.exponentialRampToValueAtTime(0.01, now + (idx + 1) * 0.15);
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(now + idx * 0.1);
          osc.stop(now + (idx + 1) * 0.2);
        });
      } else if (type === 'alert') {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, now);
        osc.frequency.setValueAtTime(880, now + 0.12);
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.3);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.32);
      }
    } catch (e) {
      console.warn("Audio error:", e);
    }
  }

  // Session Data & State
  let sessionData = null;
  let activeModalKey = null;
  let isLockAccordionOpen = false;
  let pollingInterval = null;
  let bossModalTab = "arena";
  let selectedBossTheme = "dragon";

  const sessionId = new URLSearchParams(window.location.search).get("id") || "";
  const esc = (v) => String(v ?? "").replace(/[&<>'"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[c]));

  async function api(url = "live-sessions-api.php", options = {}) {
    const res = await fetch(url, {
      ...options,
      headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.error || "เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
    return json;
  }

  window.flagStudentForFollowup = async (studentId, studentName) => {
    const notes = prompt(`บันทึกเพื่อช่วย ${studentName} หลังคาบ (ไม่บังคับ)`) ?? '';
    try {
      const data = await api('live-sessions-api.php?action=follow_up_intervention', {
        method: 'POST',
        body: JSON.stringify({ sessionId, studentId, notes }),
      });
      alert(data.duplicate ? 'นักเรียนมีรายการช่วยเหลือหัวข้อนี้อยู่แล้ว ระบบไม่ได้สร้างซ้ำ' : 'เพิ่มในคิวช่วยเหลือผู้เรียนแล้ว');
    } catch (error) { alert(error.message); }
  };

  async function loadSessionData(isManual = false) {
    if (!sessionId) return;
    const refreshBtn = document.getElementById("btn-refresh-icon");
    if (isManual && refreshBtn) refreshBtn.classList.add("animate-spin");

    try {
      const data = await api(`live-sessions-api.php?sessionId=${encodeURIComponent(sessionId)}`);
      sessionData = data;
      renderAll();
    } catch (err) {
      console.error("Load session error:", err);
      const errBox = document.getElementById("session-error-notice");
      if (errBox) {
        errBox.textContent = err.message;
        errBox.classList.remove("hidden");
      }
    } finally {
      if (isManual && refreshBtn) {
        setTimeout(() => refreshBtn.classList.remove("animate-spin"), 400);
      }
    }
  }

  function renderAll() {
    if (!sessionData) return;
    const { session, students, questions, questionStats } = sessionData;

    // PIN & Title
    const pinEl = document.getElementById("live-pin-display");
    if (pinEl) pinEl.textContent = session.sessionPin;
    const titleEl = document.getElementById("live-session-title");
    if (titleEl) titleEl.textContent = `${session.title} • ${session.classroomName || "ห้องเรียนมาตรฐาน"}`;

    // Mode Bar
    const modeDesc = document.getElementById("live-mode-desc");
    if (modeDesc) {
      modeDesc.textContent = session.hasTimeLimit ? `⏱️ ${session.timeLimitMinutes} นาที` : "🔓 ไม่จำกัดเวลา";
    }

    // Announcement Badge
    const annBadge = document.getElementById("live-announcement-badge");
    const annText = document.getElementById("live-announcement-text");
    if (annBadge && annText) {
      if (session.announcementMessage) {
        annText.textContent = session.announcementMessage;
        annBadge.classList.remove("hidden");
      } else {
        annBadge.classList.add("hidden");
      }
    }

    // Eyes On Me Switch
    const isEyesOn = !!session.eyesOnMeEnabled;
    const eyesToggle = document.getElementById("eyes-on-me-toggle");
    const eyesTogglePin = document.getElementById("eyes-on-me-pin");
    const eyesStatusBadge = document.getElementById("eyes-status-badge");
    const eyesDesc = document.getElementById("eyes-desc");
    const eyesIconWrap = document.getElementById("eyes-icon-wrap");

    if (eyesToggle && eyesTogglePin) {
      eyesToggle.className = `relative inline-flex h-7 w-12 rounded-full border-2 border-transparent transition-colors cursor-pointer ${isEyesOn ? "bg-pink-500" : "bg-slate-300"}`;
      eyesTogglePin.className = `inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform ${isEyesOn ? "translate-x-5" : "translate-x-0"}`;
    }
    if (eyesStatusBadge) {
      eyesStatusBadge.className = `text-[10px] font-bold px-2 py-0.5 rounded-full ${isEyesOn ? "bg-pink-100 text-pink-700" : "bg-emerald-100 text-emerald-700"}`;
      eyesStatusBadge.textContent = isEyesOn ? "หน้าจอนักเรียนถูกล็อก" : "นักเรียนทำข้อสอบได้ตามปกติ";
    }
    if (eyesDesc) {
      eyesDesc.textContent = isEyesOn ? "นักเรียนถูกหยุดชั่วคราว เพื่อดูกระดาน" : "อนุญาตให้ทำข้อสอบและส่งคำตอบ";
    }
    if (eyesIconWrap) {
      eyesIconWrap.className = `w-10 h-10 rounded-xl flex items-center justify-center ${isEyesOn ? "bg-pink-100 text-pink-600" : "bg-slate-100 text-slate-500"}`;
    }

    // Per-Student Lock Accordion Count
    const lockedCountBadge = document.getElementById("per-student-locked-badge");
    const lockedIds = session.lockedStudentIds || [];
    if (lockedCountBadge) {
      if (lockedIds.length > 0) {
        lockedCountBadge.textContent = `ล็อกอยู่ ${lockedIds.length} คน`;
        lockedCountBadge.classList.remove("hidden");
      } else {
        lockedCountBadge.classList.add("hidden");
      }
    }

    // Render Per-student lock grid
    const studentLockGrid = document.getElementById("student-lock-grid");
    if (studentLockGrid) {
      studentLockGrid.innerHTML = students.length ? students.map(st => {
        const isLocked = lockedIds.includes(st.studentId);
        return `
          <div class="p-2.5 rounded-xl bg-white border border-slate-200 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2 truncate">
              <div class="w-6 h-6 rounded-full bg-purple-100 text-purple-700 font-bold text-[10px] flex items-center justify-center shrink-0">
                ${esc(st.name.charAt(0))}
              </div>
              <span class="font-semibold truncate">${esc(st.name)}</span>
            </div>
            <button type="button" data-toggle-student-lock="${esc(st.studentId)}" class="p-1.5 rounded-lg shrink-0 ${isLocked ? "bg-pink-100 text-pink-700 font-bold" : "bg-slate-100 text-slate-500 hover:bg-slate-200"}">
              ${isLocked ? "🔒 ล็อก" : "🔓 ปลด"}
            </button>
          </div>
        `;
      }).join("") : '<div class="col-span-3 text-center text-xs text-slate-400 py-2">ยังไม่มีนักเรียนเข้าร่วม</div>';
    }

    // Exam Overview count
    const examQCount = document.getElementById("exam-overview-qcount");
    if (examQCount) examQCount.textContent = `ดูข้อสอบทั้งหมด (${questions.length} ข้อ) พร้อมเฉลย`;

    // Live Student Progress Pills
    const pillsWrap = document.getElementById("live-student-pills");
    if (pillsWrap) {
      pillsWrap.innerHTML = students.length ? students.map(st => {
        const isSub = st.sessionStatus === "submitted";
        const isProg = st.sessionStatus === "in_progress";
        const cls = isSub ? "bg-emerald-50 border-emerald-200 text-emerald-800" : (isProg ? "bg-sky-50 border-sky-200 text-sky-800" : "bg-slate-50 border-slate-200 text-slate-600");
        return `
          <div class="px-3 py-1.5 rounded-xl border text-xs flex items-center gap-2 ${cls}">
            <span class="font-bold">${esc(st.name)}</span>
            ${isSub ? `<span class="px-1.5 py-0.5 rounded-full bg-emerald-200 text-emerald-900 text-[10px] font-extrabold">ส่งแล้ว ${st.scorePercentage !== null ? `(${st.scorePercentage}%)` : ""}</span>`
            : isProg ? `<span class="px-1.5 py-0.5 rounded-full bg-sky-200 text-sky-900 text-[10px] font-extrabold">ทำข้อ ${st.currentQuestionNumber}/${st.totalQuestions}</span>`
            : `<span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[10px] font-semibold">รอเริ่มสอบ</span>`}
          </div>
        `;
      }).join("") : '<div class="text-xs text-slate-400">รอรับสัญญาณเข้าร่วมจากนักเรียนผ่าน PIN...</div>';
    }

    renderBossCard(session);
    renderUnderstandingCard(sessionData);

    // If modal is open, re-render its content
    if (activeModalKey) {
      renderModalContent(activeModalKey);
    }
  }

  function renderUnderstandingCard(data) {
    if (!data) return;
    const { understandingStats, sessionTopics } = data;
    const stats = understandingStats || { totals: { got_it: 0, somewhat: 0, confused: 0, total: 0 }, confused_pct: 0, topics: [] };
    const totals = stats.totals || { got_it: 0, somewhat: 0, confused: 0, total: 0 };
    const totalChecks = totals.total || 0;

    const summaryEl = document.getElementById("uc-summary-text");
    const barsWrap = document.getElementById("uc-stats-bars");
    const topicSelector = document.getElementById("uc-topic-selector");
    const topicSelect = document.getElementById("uc-topic-select");

    // Populate topic dropdown if available and not yet populated
    if (topicSelect && sessionTopics && sessionTopics.length) {
      if (topicSelector) topicSelector.style.display = "block";
      const currentVal = topicSelect.value;
      const opts = ['<option value="">-- ทั่วไป (ทั้งคาบ) --</option>'];
      sessionTopics.forEach(t => {
        const name = typeof t === "string" ? t : (t.topic_name || "");
        if (name) opts.push(`<option value="${esc(name)}">${esc(name)}</option>`);
      });
      if (opts.length > 1 && topicSelect.children.length <= 1) {
        topicSelect.innerHTML = opts.join("");
        if (currentVal) topicSelect.value = currentVal;
      }
    }

    if (totalChecks === 0) {
      if (summaryEl) summaryEl.textContent = 'ยังไม่มีข้อมูล — กด "📢 เช็กความเข้าใจ" เพื่อเริ่ม';
      if (barsWrap) barsWrap.style.display = "none";
      return;
    }

    const gotPct = Math.round((totals.got_it / totalChecks) * 100);
    const somePct = Math.round((totals.somewhat / totalChecks) * 100);
    const confPct = Math.round((totals.confused / totalChecks) * 100);

    if (summaryEl) {
      summaryEl.innerHTML = `ตอบแล้ว <strong>${totalChecks}</strong> คน • เข้าใจ <span class="text-emerald-600 font-extrabold">${gotPct}%</span> • สับสน <span class="text-rose-600 font-extrabold">${confPct}%</span>`;
    }
    if (barsWrap) {
      barsWrap.style.display = "block";
      const bg = document.getElementById("uc-bar-got");
      const bs = document.getElementById("uc-bar-some");
      const bc = document.getElementById("uc-bar-conf");
      const pg = document.getElementById("uc-pct-got");
      const ps = document.getElementById("uc-pct-some");
      const pc = document.getElementById("uc-pct-conf");

      if (bg) bg.style.width = `${gotPct}%`;
      if (bs) bs.style.width = `${somePct}%`;
      if (bc) bc.style.width = `${confPct}%`;
      if (pg) pg.textContent = `${gotPct}% (${totals.got_it})`;
      if (ps) ps.textContent = `${somePct}% (${totals.somewhat})`;
      if (pc) pc.textContent = `${confPct}% (${totals.confused})`;
    }
  }

  function renderBossCard(session) {
    const isBossActive = !!session.bossFightActive;
    const curHp = session.bossCurrentHp ?? 100;
    const maxHp = Math.max(1, session.bossMaxHp ?? 100);
    const hpPct = Math.max(0, Math.min(100, Math.round((curHp / maxHp) * 100)));

    const bossNameEl = document.getElementById("boss-card-name");
    if (bossNameEl) bossNameEl.textContent = session.bossName || "มังกรเพลิงแห่งความรู้ ไครอส";

    const bossEmojiEl = document.getElementById("boss-card-emoji");
    if (bossEmojiEl) {
      const arch = (sessionData?.bossArchetypes || []).find(a => a.id === session.bossTheme) || { emoji: "🐲" };
      bossEmojiEl.textContent = arch.emoji;
    }

    const bossSubDesc = document.getElementById("boss-card-subdesc");
    if (bossSubDesc) {
      bossSubDesc.textContent = isBossActive ? `กำลังประลองกับ ${session.bossName} — เลือดบอสลดทันทีที่ตอบถูก!` : "กระตุ้นความร่วมมือด้วยเกมพิชิตบอสประจำห้องเรียน";
    }

    const bossLiveBadge = document.getElementById("boss-live-badge");
    if (bossLiveBadge) {
      bossLiveBadge.classList.toggle("hidden", !isBossActive);
    }

    const bossHpSection = document.getElementById("boss-hp-section");
    if (bossHpSection) {
      bossHpSection.classList.toggle("hidden", !isBossActive);
      if (isBossActive) {
        const hpText = document.getElementById("boss-hp-text");
        if (hpText) hpText.innerHTML = `<strong>${curHp}</strong> / ${maxHp} HP (${hpPct}%)`;

        const topHitter = session.topDamageDealers?.[0];
        const topHitterEl = document.getElementById("boss-top-hitter");
        if (topHitterEl) {
          topHitterEl.innerHTML = topHitter ? `<span class="text-yellow-400 text-[11px]">👑 ผู้นำดาเมจ: ${esc(topHitter.name)} (${topHitter.totalDamage} DMG)</span>` : "";
        }

        const hpBar = document.getElementById("boss-hp-bar");
        if (hpBar) {
          hpBar.style.width = `${hpPct}%`;
          hpBar.className = `h-full rounded-full transition-all duration-500 ${
            hpPct > 50 ? "bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-500"
            : hpPct > 20 ? "bg-gradient-to-r from-amber-500 to-rose-500"
            : "bg-gradient-to-r from-rose-600 to-pink-600 animate-pulse"
          }`;
        }
      }
    }

    const bossToggleBtn = document.getElementById("btn-toggle-boss-fight");
    if (bossToggleBtn) {
      bossToggleBtn.className = `px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 cursor-pointer transition-transform hover:scale-102 ${
        isBossActive ? "bg-rose-600/80 hover:bg-rose-600 text-white" : "bg-gradient-to-r from-sky-600 to-blue-600 text-white"
      }`;
      bossToggleBtn.innerHTML = isBossActive ? "⚡ สิ้นสุดบอสไฟท์" : "⚡ 🐲 เริ่มบอสไฟท์ทันที";
    }
  }

  // Modals Rendering
  function openModal(key) {
    activeModalKey = key;
    const modalWrap = document.getElementById("live-modal-overlay");
    if (modalWrap) modalWrap.classList.remove("hidden");
    renderModalContent(key);
  }

  function closeModal() {
    activeModalKey = null;
    const modalWrap = document.getElementById("live-modal-overlay");
    if (modalWrap) modalWrap.classList.add("hidden");
  }

  function renderModalContent(key) {
    const titleEl = document.getElementById("modal-header-title");
    const descEl = document.getElementById("modal-header-desc");
    const bodyEl = document.getElementById("modal-body-content");
    if (!titleEl || !descEl || !bodyEl || !sessionData) return;

    const { session, students, questions, questionStats } = sessionData;

    switch (key) {
      case "exam_overview": {
        titleEl.textContent = "ภาพรวมข้อสอบ (Exam Overview)";
        descEl.textContent = `ข้อสอบทั้งหมด ${questions.length} ข้อ พร้อมเฉลยและทักษะที่เกี่ยวข้อง`;
        bodyEl.innerHTML = `
          <div class="max-h-[70vh] overflow-y-auto space-y-4 pr-1">
            ${questions.map(q => `
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <div class="flex items-center justify-between text-xs font-bold mb-2">
                  <span class="text-pink-600">ข้อที่ ${q.questionNumber}</span>
                  <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-700">${esc(q.topic)}</span>
                </div>
                <p class="font-bold text-sm text-navy-950 mb-3">${esc(q.questionText)}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  ${q.options.map(opt => `
                    <div class="p-2.5 rounded-xl border ${opt.isCorrect ? "bg-emerald-50 border-emerald-300 text-emerald-900 font-bold" : "bg-white border-slate-200 text-slate-700"}">
                      <b>${opt.optionKey}.</b> ${esc(opt.optionText)} ${opt.isCorrect ? "✅" : ""}
                    </div>
                  `).join("")}
                </div>
                ${q.explanation ? `<div class="mt-2 text-[11px] text-slate-500">💡 เฉลย: ${esc(q.explanation)}</div>` : ""}
              </div>
            `).join("")}
          </div>
        `;
        break;
      }

      case "student_details": {
        titleEl.textContent = "รายละเอียดนักเรียน (Student Details)";
        descEl.textContent = `นักเรียนในเซสชันทั้งหมด ${students.length} คน`;
        bodyEl.innerHTML = `
          <div class="max-h-[70vh] overflow-y-auto divide-y divide-slate-100">
            ${students.length ? students.map(st => `
              <div class="py-3 flex items-center justify-between text-xs">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-700 font-bold flex items-center justify-center">
                    ${esc(st.name.charAt(0))}
                  </div>
                  <div>
                    <strong class="block text-slate-900">${esc(st.name)}</strong>
                    <small class="text-slate-400">${esc(st.email)}</small>
                  </div>
                </div>
                <div class="text-right flex items-center gap-2">
                  ${st.scorePercentage !== null
                    ? `<span class="px-2 py-1 rounded-lg bg-emerald-100 text-emerald-800 font-bold">คะแนน ${st.scorePercentage}%</span>`
                    : `<span class="text-slate-500">ทำข้อ ${st.currentQuestionNumber}/${st.totalQuestions} (${st.progressPct}%)</span>`}
                  <button type="button" onclick="flagStudentForFollowup(${Number(st.studentId)}, decodeURIComponent('${encodeURIComponent(String(st.name)).replace(/'/g, '%27')}'))" class="px-2 py-1 rounded-lg border border-amber-300 text-amber-800 font-bold">ติดตามหลังคาบ</button>
                </div>
              </div>
            `).join("") : '<div class="py-8 text-center text-slate-400">ยังไม่มีนักเรียน</div>'}
          </div>
        `;
        break;
      }

      case "attendance": {
        titleEl.textContent = "เช็คชื่อนักเรียน (Attendance)";
        descEl.textContent = "ตรวจสอบรายชื่อผู้เข้าร่วมห้องเรียนและดาวน์โหลดรายงาน";
        bodyEl.innerHTML = `
          <div class="space-y-4">
            <div class="flex justify-between items-center bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span class="text-xs font-bold text-slate-700">ผู้เข้าเรียน: ${students.length} คน</span>
              <button type="button" id="btn-download-csv" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                📥 ดาวน์โหลด CSV (Excel ภาษาไทย)
              </button>
            </div>
            <div class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100 text-xs">
              ${students.map((st, i) => `
                <div class="py-2.5 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-5 text-slate-400 font-bold">${i + 1}</span>
                    <strong class="text-slate-900">${esc(st.name)}</strong>
                  </div>
                  <div class="flex items-center gap-3">
                    <time class="text-[11px] text-slate-400">${new Date(st.joinedAt).toLocaleTimeString("th-TH")}</time>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">เข้าเรียนแล้ว</span>
                  </div>
                </div>
              `).join("")}
            </div>
          </div>
        `;
        const csvBtn = document.getElementById("btn-download-csv");
        if (csvBtn) csvBtn.onclick = handleExportCsv;
        break;
      }

      case "announcement": {
        titleEl.textContent = "ประกาศด่วนถึงนักเรียน (Urgent Announcement)";
        descEl.textContent = "ข้อความจะปรากฏบนแถบด้านบนของหน้าจอนักเรียนทุกคนทันที";
        bodyEl.innerHTML = `
          <div class="space-y-3">
            <textarea id="announcement-input" rows="3" placeholder="พิมพ์ประกาศ เช่น 'โปรดดูการเฉลยข้อ 5 บนกระดาน', 'เหลือเวลาอีก 5 นาที'..." class="w-full p-3 rounded-2xl border border-slate-300 outline-none text-xs focus:border-pink-500"></textarea>
            <div class="flex justify-end gap-2">
              <button type="button" data-close-modal class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-600">ยกเลิก</button>
              <button type="button" id="btn-submit-announcement" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                🚀 ส่งประกาศทันที
              </button>
            </div>
          </div>
        `;
        const submitBtn = document.getElementById("btn-submit-announcement");
        if (submitBtn) {
          submitBtn.onclick = async () => {
            const input = document.getElementById("announcement-input");
            const text = input ? input.value.trim() : "";
            if (!text) return alert("กรุณาพิมพ์ข้อความประกาศ");
            submitBtn.disabled = true;
            submitBtn.textContent = "กำลังส่ง...";
            try {
              await api("live-sessions-api.php", {
                method: "PATCH",
                body: JSON.stringify({ sessionId, announcementMessage: text }),
              });
              playBattleSound("alert");
              closeModal();
              loadSessionData(true);
            } catch (e) {
              alert(e.message);
            } finally {
              submitBtn.disabled = false;
            }
          };
        }
        break;
      }

      case "post_class_assignment": {
        titleEl.textContent = "📝 มอบหมายงานหลังเรียน (Post-Class Assignment)";
        descEl.textContent = "เลือกใบงาน แบบฝึกหัด หรือการบ้านเพื่อมอบหมายให้นักเรียนในคลาสนี้โดยอัตโนมัติ";
        bodyEl.innerHTML = `
          <div class="space-y-4 text-xs">
            <div class="p-3 rounded-2xl bg-pink-50 text-pink-900 border border-pink-100 flex items-center gap-2">
              <span class="text-base">💡</span>
              <span>ระบบจะเชื่อมโยง <b>Session ID</b> และ <b>Topic</b> ของคาบนี้เข้ากับใบงานเพื่อติดตามความก้าวหน้าอัตโนมัติ</span>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1.5">ประเภทกิจกรรมการเรียนรู้:</label>
              <select id="quick-assign-type" class="w-full h-10 px-3 rounded-xl border border-slate-200 bg-white font-medium text-slate-800">
                <option value="homework">การบ้าน (Homework) — มีกำหนดส่ง</option>
                <option value="worksheet">ใบงาน (Worksheet) — แบบฝึกหัดทั่วไป</option>
                <option value="practice">ฝึกฝน (Practice) — ทบทวนเพิ่มเติม</option>
                <option value="posttest">แบบทดสอบหลังเรียน (Post-Test) — วัดผลหลังเรียน</option>
              </select>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1.5">เลือกใบงานจากคลัง (Worksheet Library):</label>
              <select id="quick-assign-worksheet" class="w-full h-10 px-3 rounded-xl border border-slate-200 bg-white font-medium text-slate-800">
                <option value="">กำลังโหลดรายการใบงาน...</option>
              </select>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1.5">กำหนดส่ง (Due Date):</label>
              <input type="datetime-local" id="quick-assign-due" class="w-full h-10 px-3 rounded-xl border border-slate-200 bg-white font-medium text-slate-800" value="${new Date(Date.now() + 3*86400000).toISOString().slice(0, 16)}">
            </div>

            <div id="quick-assign-result" class="hidden p-3 rounded-xl text-xs font-bold"></div>

            <div class="pt-2 flex justify-end gap-2">
              <button type="button" data-close-modal class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 cursor-pointer">ยกเลิก</button>
              <button type="button" id="btn-submit-quick-assign" class="px-5 py-2.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold shadow-md shadow-pink-500/20 cursor-pointer">มอบหมายทันที</button>
            </div>
          </div>
        `;

        fetch('worksheets-api.php?action=list')
          .then(r => r.json())
          .then(data => {
            const selectEl = document.getElementById('quick-assign-worksheet');
            if (!selectEl) return;
            const list = data.worksheets || [];
            if (!list.length) {
              selectEl.innerHTML = '<option value="">ไม่มีใบงานในคลัง กรุณาสร้างใบงานก่อน</option>';
              return;
            }
            selectEl.innerHTML = list.map(w => `<option value="${w.id}">${esc(w.title)} (${esc(w.topic || w.subject || 'ทั่วไป')}) - ${w.question_count || 0} ข้อ</option>`).join('');
          })
          .catch(() => {
            const selectEl = document.getElementById('quick-assign-worksheet');
            if (selectEl) selectEl.innerHTML = '<option value="1">ใบงานตัวอย่าง #1</option>';
          });

        setTimeout(() => {
          const submitBtn = document.getElementById('btn-submit-quick-assign');
          if (submitBtn) {
            submitBtn.addEventListener('click', async () => {
              const wsId = document.getElementById('quick-assign-worksheet')?.value;
              const actType = document.getElementById('quick-assign-type')?.value;
              const dueDate = document.getElementById('quick-assign-due')?.value;
              const resultEl = document.getElementById('quick-assign-result');

              if (!wsId) {
                alert('กรุณาเลือกใบงาน');
                return;
              }

              submitBtn.disabled = true;
              submitBtn.textContent = 'กำลังมอบหมาย...';

              try {
                const res = await fetch('worksheets-api.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    action: 'quick_assign_session',
                    session_id: session.id,
                    worksheet_id: parseInt(wsId, 10),
                    activity_type: actType,
                    due_date: dueDate
                  })
                });
                const d = await res.json();
                if (d.success) {
                  resultEl.className = 'p-3 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800';
                  resultEl.textContent = '✓ ' + (d.message || 'มอบหมายงานหลังเรียนเรียบร้อยแล้ว');
                  resultEl.classList.remove('hidden');
                  submitBtn.textContent = 'มอบหมายสำเร็จ';
                  setTimeout(() => {
                    document.getElementById('live-modal-overlay')?.classList.add('hidden');
                  }, 1200);
                } else {
                  alert(d.error || 'เกิดข้อผิดพลาด');
                  submitBtn.disabled = false;
                  submitBtn.textContent = 'มอบหมายทันที';
                }
              } catch (e) {
                alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
                submitBtn.disabled = false;
                submitBtn.textContent = 'มอบหมายทันที';
              }
            });
          }
        }, 100);
        break;
      }

      case "remediation": {
        titleEl.textContent = "ศูนย์บทเรียนเสริม (Remediation Hub)";
        descEl.textContent = "วิเคราะห์หัวข้อที่นักเรียนส่วนใหญ่ยังติดขัด เพื่อจัดกิจกรรมเสริม";
        const topMissed = questionStats[0];
        bodyEl.innerHTML = `
          <div class="space-y-4 text-xs">
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200">
              <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">หัวข้อที่ควรเน้นย้ำ</span>
              <h4 class="text-sm font-bold text-amber-950 mt-1.5">${topMissed ? esc(topMissed.topic) : "ยังไม่มีข้อมูลผิดพลาดเด่นชัด"}</h4>
              <p class="text-amber-800 mt-1">อัตราตอบถูกในหัวข้อนี้เฉลี่ย ${topMissed ? topMissed.correctPct : 100}% มีนักเรียนตอบผิด ${topMissed ? topMissed.incorrectCount : 0} คน</p>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
              <div>
                <strong class="block text-slate-800">แบบฝึกหัดเสริมเจาะจง 3 ข้อสั้น</strong>
                <small class="text-slate-500">สร้างด้วยโจทย์ประเภทเดียวกันสำหรับอธิบายซ้ำ</small>
              </div>
              <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">พร้อมใช้งาน</span>
            </div>
          </div>
        `;
        break;
      }

      case "exam_stats": {
        titleEl.textContent = "สถิติการสอบของห้องเรียน";
        descEl.textContent = "ภาพรวมผลคะแนนและอัตราการส่งข้อสอบ";
        const completed = students.filter(s => s.scorePercentage !== null);
        const avg = completed.length ? Math.round(completed.reduce((sum, s) => sum + s.scorePercentage, 0) / completed.length) : 0;
        bodyEl.innerHTML = `
          <div class="grid grid-cols-2 gap-4">
            <div class="p-5 rounded-2xl bg-purple-50 border border-purple-200 text-center">
              <span class="text-xs text-purple-700 font-bold block mb-1">คะแนนเฉลี่ย</span>
              <strong class="text-3xl font-black text-purple-900">${avg}%</strong>
            </div>
            <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-center">
              <span class="text-xs text-emerald-700 font-bold block mb-1">ส่งข้อสอบแล้ว</span>
              <strong class="text-3xl font-black text-emerald-900">${completed.length}/${students.length}</strong>
            </div>
          </div>
        `;
        break;
      }

      case "skill_map": {
        titleEl.textContent = "แผนที่ทักษะของห้อง (Skill Map)";
        descEl.textContent = "วิเคราะห์ความแม่นยำแยกตามหัวข้อทักษะ";
        bodyEl.innerHTML = `
          <div class="max-h-[65vh] overflow-y-auto space-y-3 text-xs">
            ${questionStats.map(stat => {
              const barColor = stat.correctPct >= 70 ? "bg-emerald-500" : (stat.correctPct >= 40 ? "bg-amber-500" : "bg-rose-500");
              return `
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                  <div class="flex justify-between font-bold">
                    <span>${esc(stat.topic)}</span>
                    <span>${stat.correctPct}% แม่นยำ</span>
                  </div>
                  <div class="w-full h-2.5 rounded-full bg-slate-200 overflow-hidden">
                    <div class="h-full rounded-full ${barColor}" style="width: ${stat.correctPct}%"></div>
                  </div>
                </div>
              `;
            }).join("")}
          </div>
        `;
        break;
      }

      case "missed_questions": {
        titleEl.textContent = "สรุปคำถามที่ตอบผิดมากที่สุด";
        descEl.textContent = "จัดอันดับข้อที่นักเรียนมักเข้าใจคลาดเคลื่อน";
        bodyEl.innerHTML = `
          <div class="max-h-[65vh] overflow-y-auto space-y-2.5 text-xs">
            ${questionStats.map(stat => `
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div>
                  <strong class="text-slate-900">ข้อที่ ${stat.questionNumber}</strong>
                  <span class="text-slate-500 text-[11px] ml-1.5">(${esc(stat.topic)})</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold text-[10px]">ผิด ${stat.incorrectCount} คน</span>
                  <span class="text-slate-500 text-[11px]">ถูก ${stat.correctPct}%</span>
                </div>
              </div>
            `).join("")}
          </div>
        `;
        break;
      }

      case "reset_attempt": {
        titleEl.textContent = "รีเซ็ตคำตอบนักเรียน (Reset Student Attempt)";
        descEl.textContent = "อนุญาตให้นักเรียนทำข้อสอบใหม่อีกครั้ง";
        bodyEl.innerHTML = `
          <div class="max-h-[65vh] overflow-y-auto divide-y divide-slate-100 text-xs">
            ${students.map(st => `
              <div class="py-3 flex items-center justify-between">
                <div>
                  <strong class="block text-slate-900">${esc(st.name)}</strong>
                  <small class="text-slate-400">สถานะ: ${st.sessionStatus}</small>
                </div>
                <button type="button" data-reset-student="${esc(st.studentId)}" data-student-name="${esc(st.name)}" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold border border-rose-200 cursor-pointer">
                  🔄 รีเซ็ตข้อสอบ
                </button>
              </div>
            `).join("")}
          </div>
        `;
        break;
      }
    }
  }

  function renderBossArenaModal(titleEl, descEl, bodyEl, session) {
    titleEl.textContent = "👾 ศูนย์ควบคุมบอสไฟท์ / จอฉายใหญ่ (Boss Battle Arena)";
    descEl.textContent = "ใช้สำหรับฉายขึ้นจอโปรเจกเตอร์หน้าห้อง เพื่อสร้างความตื่นเต้นและประลองความรู้";

    const curHp = session.bossCurrentHp ?? 100;
    const maxHp = Math.max(1, session.bossMaxHp ?? 100);
    const hpPct = Math.max(0, Math.min(100, Math.round((curHp / maxHp) * 100)));
    const archetypes = sessionData?.bossArchetypes || [];
    const currentTheme = session.bossTheme || selectedBossTheme || "dragon";
    const arch = archetypes.find(a => a.id === currentTheme) || { emoji: "🐲", name: "บอส" };
    const logs = session.bossCombatLog || [];

    bodyEl.innerHTML = `
      <div class="space-y-4">
        <!-- Top Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-200 pb-2.5">
          <button type="button" id="tab-boss-arena" class="px-3.5 py-1.5 rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-colors ${
            bossModalTab === "arena" ? "bg-purple-600 text-white shadow-xs" : "bg-slate-100 text-slate-600 hover:bg-slate-200"
          }">
            <span>🎮</span> สนามประลอง (Arena View)
          </button>
          <button type="button" id="tab-boss-config" class="px-3.5 py-1.5 rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-colors ${
            bossModalTab === "config" ? "bg-purple-600 text-white shadow-xs" : "bg-slate-100 text-slate-600 hover:bg-slate-200"
          }">
            <span>⚙️</span> เลือกมอนสเตอร์ & ตั้งค่า (Boss Config)
          </button>
        </div>

        ${bossModalTab === "arena" ? `
          <!-- Arena Canvas Banner -->
          <div id="boss-arena-fullscreen-wrap" class="p-6 rounded-3xl bg-gradient-to-b from-slate-950 via-indigo-950 to-slate-900 text-white border border-purple-500/40 text-center relative overflow-hidden shadow-2xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="px-2.5 py-1 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[10px] font-extrabold uppercase animate-pulse">
                ${session.bossFightActive ? "⚔️ BATTLE IN PROGRESS" : "💤 ARENA STANDBY"}
              </span>
              <button type="button" id="btn-fullscreen-arena" class="px-3 py-1 rounded-xl bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-bold border border-white/20 flex items-center gap-1 cursor-pointer">
                <span>🖥️</span> ขยายเต็มจอ
              </button>
            </div>

            <div class="text-7xl my-2 transform hover:scale-110 transition-transform select-none animate-bounce">
              ${arch.emoji}
            </div>
            <h2 class="text-2xl font-black text-purple-200 tracking-wide">${esc(session.bossName)}</h2>
            <div class="text-xs text-slate-300">เลือดบอสจะลดลงทันทีที่นักเรียนตอบถูกในแต่ละข้อ (-10 DMG ต่อข้อ)</div>

            <!-- HP Bar -->
            <div class="max-w-md mx-auto mt-3 space-y-1.5">
              <div class="flex justify-between text-xs font-bold font-mono">
                <span class="text-purple-300">BOSS HEALTH</span>
                <span>${curHp} / ${maxHp} HP (${hpPct}%)</span>
              </div>
              <div class="w-full h-5 rounded-full bg-slate-950 p-0.5 border border-white/30 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 ${
                  hpPct > 50 ? "bg-gradient-to-r from-emerald-400 to-teal-400" : (hpPct > 20 ? "bg-gradient-to-r from-amber-400 to-rose-500" : "bg-gradient-to-r from-rose-600 to-pink-500 animate-pulse")
                }" style="width: ${hpPct}%"></div>
              </div>
            </div>

            ${session.bossDefeated ? `
              <div class="mt-4 p-3.5 rounded-2xl bg-emerald-500/20 border border-emerald-400/50 text-emerald-300 font-black text-sm animate-pulse">
                🎉 พิชิตบอสสำเร็จ! ทุกคนได้รับ ${session.bossRewardPoints} พอยต์!
              </div>
            ` : ""}
          </div>

          <!-- Controls & Combat Log -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <!-- Quick Strikes -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
              <strong class="block text-slate-900 font-bold">⚔️ คำสั่งครูผู้สอน</strong>
              <div class="grid grid-cols-2 gap-2">
                <button type="button" id="btn-teacher-strike" class="p-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold cursor-pointer flex items-center justify-center gap-1.5 shadow-sm transition-transform hover:scale-102">
                  ⚡ ครูโจมตี (-25 HP)
                </button>
                <button type="button" id="btn-reset-boss" class="p-2.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold cursor-pointer transition-colors">
                  🔄 รีเซ็ตเลือดบอส
                </button>
              </div>
              <button type="button" id="btn-sound-toggle" class="w-full p-2.5 rounded-xl border border-slate-300 text-slate-700 font-semibold hover:bg-slate-100 cursor-pointer">
                🔊 เสียงประกอบ: ${battleSoundEnabled ? "เปิดอยู่" : "ปิด"}
              </button>
            </div>

            <!-- Live Combat Log -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
              <strong class="block text-slate-900 font-bold">📜 ประวัติการทำดาเมจล่าสุด</strong>
              <div class="h-32 overflow-y-auto space-y-1.5 pr-1 font-mono text-[11px]">
                ${logs.length ? logs.map(hit => `
                  <div class="p-1.5 rounded-lg bg-white border border-slate-200 flex justify-between items-center">
                    <span class="font-bold text-indigo-700 truncate">${esc(hit.studentName)}</span>
                    <span class="text-rose-600 font-black shrink-0">-${hit.damage} DMG</span>
                  </div>
                `).join("") : '<div class="text-slate-400 text-center py-6">ยังไม่มีการโจมตี</div>'}
              </div>
            </div>
          </div>
        ` : `
          <!-- Boss Config Tab -->
          <div class="space-y-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-2">เลือกมอนสเตอร์ / ธีมบอสประจำห้อง</label>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                ${archetypes.map(a => `
                  <button type="button" data-pick-archetype="${esc(a.id)}" data-archetype-name="${esc(a.name)}" class="p-3 rounded-2xl border-2 text-left cursor-pointer transition-all ${
                    selectedBossTheme === a.id ? "border-purple-600 bg-purple-50 shadow-xs" : "border-slate-200 hover:border-slate-300 bg-white"
                  }">
                    <div class="text-2xl mb-1">${a.emoji}</div>
                    <strong class="block text-slate-900 text-xs font-black truncate">${esc(a.name)}</strong>
                    <span class="text-[10px] text-slate-500 line-clamp-2 mt-0.5">${esc(a.description)}</span>
                  </button>
                `).join("")}
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อบอส</label>
                <input id="cfg-boss-name" type="text" value="${esc(session.bossName || arch.name)}" class="w-full h-10 px-3 rounded-xl border border-slate-300 outline-none focus:border-purple-500 font-semibold text-xs">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">พลังชีวิตสูงสุด (HP)</label>
                <input id="cfg-boss-hp" type="number" min="10" max="10000" value="${maxHp}" class="w-full h-10 px-3 rounded-xl border border-slate-300 outline-none focus:border-purple-500 font-semibold text-xs">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">พอยต์รางวัล (NC Points)</label>
                <input id="cfg-boss-reward" type="number" min="0" max="1000" value="${session.bossRewardPoints ?? 50}" class="w-full h-10 px-3 rounded-xl border border-slate-300 outline-none focus:border-purple-500 font-semibold text-xs">
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button type="button" id="btn-save-boss-config" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-md transition-transform hover:scale-102">
                🚀 บันทึกและเริ่มบอสใหม่
              </button>
            </div>
          </div>
        `}
      </div>
    `;

    // Tab button events
    const tabArena = document.getElementById("tab-boss-arena");
    const tabConfig = document.getElementById("tab-boss-config");
    if (tabArena) tabArena.onclick = () => { bossModalTab = "arena"; renderBossArenaModal(titleEl, descEl, bodyEl, session); };
    if (tabConfig) tabConfig.onclick = () => { bossModalTab = "config"; renderBossArenaModal(titleEl, descEl, bodyEl, session); };

    // Fullscreen Arena
    const fsBtn = document.getElementById("btn-fullscreen-arena");
    if (fsBtn) {
      fsBtn.onclick = () => {
        const wrap = document.getElementById("boss-arena-fullscreen-wrap");
        if (wrap) {
          if (!document.fullscreenElement) {
            wrap.requestFullscreen().catch(() => {});
          } else {
            document.exitFullscreen().catch(() => {});
          }
        }
      };
    }

    // Archetype Pick
    bodyEl.querySelectorAll("[data-pick-archetype]").forEach(btn => {
      btn.onclick = () => {
        selectedBossTheme = btn.dataset.pickArchetype;
        const nameInput = document.getElementById("cfg-boss-name");
        if (nameInput && btn.dataset.archetypeName) {
          nameInput.value = btn.dataset.archetypeName;
        }
        renderBossArenaModal(titleEl, descEl, bodyEl, session);
      };
    });

    // Save Boss Config
    const saveCfgBtn = document.getElementById("btn-save-boss-config");
    if (saveCfgBtn) {
      saveCfgBtn.onclick = async () => {
        const nameInput = document.getElementById("cfg-boss-name");
        const hpInput = document.getElementById("cfg-boss-hp");
        const rewInput = document.getElementById("cfg-boss-reward");
        const bName = nameInput ? nameInput.value.trim() : "";
        const bHp = hpInput ? parseInt(hpInput.value, 10) : 100;
        const bRew = rewInput ? parseInt(rewInput.value, 10) : 50;

        saveCfgBtn.disabled = true;
        saveCfgBtn.textContent = "กำลังบันทึก...";
        try {
          await api(`live-sessions-api.php?action=setup_boss&sessionId=${encodeURIComponent(sessionId)}`, {
            method: "POST",
            body: JSON.stringify({
              bossTheme: selectedBossTheme,
              bossName: bName,
              bossMaxHp: bHp,
              bossRewardPoints: bRew,
            }),
          });
          bossModalTab = "arena";
          playBattleSound("alert");
          loadSessionData(true);
        } catch (e) {
          alert(e.message);
        } finally {
          saveCfgBtn.disabled = false;
        }
      };
    }

    const strikeBtn = document.getElementById("btn-teacher-strike");
    if (strikeBtn) {
      strikeBtn.onclick = async () => {
        playBattleSound("strike");
        try {
          const res = await api(`live-sessions-api.php?action=teacher_strike&sessionId=${encodeURIComponent(sessionId)}`, {
            method: "POST",
            body: JSON.stringify({ damage: 25 }),
          });
          playBattleSound(res.isDefeated ? "victory" : "hit");
          loadSessionData(true);
        } catch (e) {
          alert(e.message);
        }
      };
    }

    const resetBossBtn = document.getElementById("btn-reset-boss");
    if (resetBossBtn) {
      resetBossBtn.onclick = async () => {
        if (!confirm("ต้องการรีเซ็ตเลือดบอสใหม่เต็มหลอดใช่หรือไม่?")) return;
        try {
          await api(`live-sessions-api.php?action=reset_boss&sessionId=${encodeURIComponent(sessionId)}`, { method: "POST" });
          loadSessionData(true);
        } catch (e) {
          alert(e.message);
        }
      };
    }

    const soundBtn = document.getElementById("btn-sound-toggle");
    if (soundBtn) {
      soundBtn.onclick = () => {
        battleSoundEnabled = !battleSoundEnabled;
        soundBtn.textContent = `🔊 เสียงประกอบ: ${battleSoundEnabled ? "เปิดอยู่" : "ปิด"}`;
      };
    }
  }

  function handleExportCsv() {
    if (!sessionData || !sessionData.students) return;
    const { session, students } = sessionData;
    const csvHeader = "ลำดับ,ชื่อ-นามสกุล,อีเมล,เวลาเข้าร่วม,สถานะ,ทำได้(ข้อ),คะแนน(%)\n";
    const csvRows = students.map((s, idx) =>
      `${idx + 1},"${s.name}","${s.email || ""}","${new Date(s.joinedAt).toLocaleString("th-TH")}","${s.sessionStatus}","${s.currentQuestionNumber}/${s.totalQuestions}","${s.scorePercentage ?? ""}"`
    ).join("\n");
    // UTF-8 BOM \uFEFF for proper Thai support in Excel
    const blob = new Blob(["\uFEFF" + csvHeader + csvRows], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `attendance-session-${session.sessionPin}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // Bind Page Global Actions
  document.addEventListener("DOMContentLoaded", () => {
    // Refresh Button
    const refreshBtn = document.getElementById("btn-refresh-session");
    if (refreshBtn) refreshBtn.onclick = () => loadSessionData(true);

    // Copy PIN Button
    const copyBtn = document.getElementById("btn-copy-pin");
    if (copyBtn) {
      copyBtn.onclick = () => {
        const pin = sessionData?.session?.sessionPin || "";
        if (!pin) return;
        navigator.clipboard.writeText(pin);
        const checkIcon = document.getElementById("pin-copy-check");
        const copyIcon = document.getElementById("pin-copy-icon");
        if (checkIcon && copyIcon) {
          checkIcon.classList.remove("hidden");
          copyIcon.classList.add("hidden");
          setTimeout(() => {
            checkIcon.classList.add("hidden");
            copyIcon.classList.remove("hidden");
          }, 2000);
        }
      };
    }

    // Toggle Eyes On Me Global
    const eyesToggle = document.getElementById("eyes-on-me-toggle");
    if (eyesToggle) {
      eyesToggle.onclick = async () => {
        if (!sessionData) return;
        const nextState = !sessionData.session.eyesOnMeEnabled;
        eyesToggle.disabled = true;
        try {
          await api("live-sessions-api.php", {
            method: "PATCH",
            body: JSON.stringify({ sessionId, eyesOnMeEnabled: nextState }),
          });
          sessionData.session.eyesOnMeEnabled = nextState;
          renderAll();
        } catch (e) {
          alert(e.message);
        } finally {
          eyesToggle.disabled = false;
        }
      };
    }

    // Toggle Lock Accordion
    const lockAccBtn = document.getElementById("btn-toggle-lock-accordion");
    const lockAccBody = document.getElementById("student-lock-accordion-body");
    const lockAccChevron = document.getElementById("lock-accordion-chevron");
    if (lockAccBtn && lockAccBody) {
      lockAccBtn.onclick = () => {
        isLockAccordionOpen = !isLockAccordionOpen;
        lockAccBody.classList.toggle("hidden", !isLockAccordionOpen);
        if (lockAccChevron) {
          lockAccChevron.textContent = isLockAccordionOpen ? "▲" : "▼";
        }
      };
    }

    // Clear Announcement
    const clearAnnBtn = document.getElementById("btn-clear-announcement");
    if (clearAnnBtn) {
      clearAnnBtn.onclick = async () => {
        try {
          await api("live-sessions-api.php", {
            method: "PATCH",
            body: JSON.stringify({ sessionId, announcementMessage: null }),
          });
          if (sessionData) sessionData.session.announcementMessage = null;
          renderAll();
        } catch (e) {
          alert(e.message);
        }
      };
    }

    // Phase 2: Trigger Understanding Check
    const triggerCheckBtn = document.getElementById("btn-trigger-check");
    if (triggerCheckBtn) {
      triggerCheckBtn.onclick = async () => {
        triggerCheckBtn.disabled = true;
        const origText = triggerCheckBtn.innerHTML;
        triggerCheckBtn.textContent = "⏳ กำลังส่ง...";
        try {
          const topicSelect = document.getElementById("uc-topic-select");
          const selTopic = topicSelect ? topicSelect.value.trim() : "";
          const msg = "_check_" + (selTopic ? " " + selTopic : "");
          await api("live-sessions-api.php?action=update_announcement", {
            method: "POST",
            body: JSON.stringify({ sessionId, announcementMessage: msg })
          });
          triggerCheckBtn.textContent = "✓ ส่งเช็กแล้ว!";
          playBattleSound("alert");
          setTimeout(() => {
            triggerCheckBtn.disabled = false;
            triggerCheckBtn.innerHTML = origText;
          }, 3000);
        } catch (err) {
          alert(err.message || "ส่งคำถามความเข้าใจไม่สำเร็จ");
          triggerCheckBtn.disabled = false;
          triggerCheckBtn.innerHTML = origText;
        }
      };
    }

    // Toggle Boss Fight Active
    const bossToggleBtn = document.getElementById("btn-toggle-boss-fight");
    if (bossToggleBtn) {
      bossToggleBtn.onclick = async () => {
        if (!sessionData) return;
        const nextState = !sessionData.session.bossFightActive;
        const totalQ = (sessionData.session.totalQuestions || 10) * Math.max(1, sessionData.students.length);
        const maxHp = totalQ * 10;
        try {
          await api("live-sessions-api.php", {
            method: "PATCH",
            body: JSON.stringify({
              sessionId,
              bossFightActive: nextState,
              bossMaxHp: maxHp,
              bossCurrentHp: maxHp,
            }),
          });
          loadSessionData(true);
        } catch (e) {
          alert(e.message);
        }
      };
    }

    // Delegate Clicks for Modals & Student Actions
    document.body.addEventListener("click", async (e) => {
      // Open Modal
      const modalBtn = e.target.closest("[data-open-modal]");
      if (modalBtn) {
        openModal(modalBtn.dataset.openModal);
        return;
      }

      // Close Modal
      if (e.target.closest("[data-close-modal]") || e.target.id === "live-modal-overlay") {
        closeModal();
        return;
      }

      // Toggle Per-Student Lock
      const stLockBtn = e.target.closest("[data-toggle-student-lock]");
      if (stLockBtn) {
        const studentId = stLockBtn.dataset.toggleStudentLock;
        if (!sessionData) return;
        const current = sessionData.session.lockedStudentIds || [];
        const isLocked = current.includes(studentId);
        const updated = isLocked ? current.filter(id => id !== studentId) : [...current, studentId];
        stLockBtn.disabled = true;
        try {
          await api("live-sessions-api.php", {
            method: "PATCH",
            body: JSON.stringify({ sessionId, lockedStudentIds: updated }),
          });
          sessionData.session.lockedStudentIds = updated;
          renderAll();
        } catch (err) {
          alert(err.message);
        } finally {
          stLockBtn.disabled = false;
        }
        return;
      }

      // Reset Student Attempt
      const resetBtn = e.target.closest("[data-reset-student]");
      if (resetBtn) {
        const sid = resetBtn.dataset.resetStudent;
        const sname = resetBtn.dataset.studentName || "นักเรียน";
        if (!confirm(`แน่ใจหรือไม่ว่าต้องการรีเซ็ตข้อสอบของ "${sname}"? ข้อมูลการตอบเดิมจะถูกล้าง`)) return;
        resetBtn.disabled = true;
        try {
          await api(`live-sessions-api.php?action=reset_student_attempt&sessionId=${encodeURIComponent(sessionId)}`, {
            method: "POST",
            body: JSON.stringify({ studentId: sid }),
          });
          alert(`รีเซ็ต ${sname} เรียบร้อยแล้ว`);
          loadSessionData(true);
        } catch (err) {
          alert(err.message);
        } finally {
          resetBtn.disabled = false;
        }
        return;
      }
    });

    // Start Polling every 3 seconds
    loadSessionData();
    pollingInterval = setInterval(() => loadSessionData(false), 3000);
  });

  window.addEventListener("beforeunload", () => {
    if (pollingInterval) clearInterval(pollingInterval);
  });
})();
