(() => {
  "use strict";

  function init() {
    const tabs = [...document.querySelectorAll("[data-settings-tab]")];
    const panels = [...document.querySelectorAll("[data-settings-panel]")];
    const forms = [...document.querySelectorAll("[data-settings-form]")];
    const notice = document.getElementById("settings-notice");
    const logoInput = document.getElementById("school-logo-input");
    const logoPreview = document.getElementById("school-logo-preview");
    let savedSettings = {};

    async function request(url, options = {}) {
      const response = await fetch(url, options);
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        const err = new Error(data.error || "ดำเนินการไม่สำเร็จ");
        err.data = data;
        throw err;
      }
      return data;
    }

    function showNotice(message, isError = false) {
      if (!notice) return;
      notice.textContent = message;
      notice.className = "mb-5 px-5 py-4 rounded-xl border text-[13px] font-bold transition-all " +
        (isError ? "border-rose-200 bg-rose-50 text-rose-700" : "border-emerald-200 bg-emerald-50 text-emerald-700");
      notice.classList.remove("hidden");
      window.clearTimeout(showNotice.timer);
      showNotice.timer = window.setTimeout(() => notice.classList.add("hidden"), 4000);
    }

    const toast = document.getElementById("settings-toast");
    function showToast(message, isError = false) {
      showNotice(message, isError);
      if (!toast) return;
      toast.className = "fixed bottom-6 right-6 z-50 transform transition-all duration-300 flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl border text-sm font-bold " +
        (isError ? "border-rose-300 bg-rose-50 text-rose-800" : "border-emerald-300 bg-emerald-50 text-emerald-900");
      toast.innerHTML = isError 
        ? `<svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span>${esc(message)}</span>`
        : `<svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>${esc(message)}</span>`;
      toast.classList.remove("translate-y-20", "opacity-0", "pointer-events-none");
      window.clearTimeout(showToast.timer);
      showToast.timer = window.setTimeout(() => {
        toast.classList.add("translate-y-20", "opacity-0", "pointer-events-none");
      }, 3500);
    }

    const esc = (v) => String(v ?? "").replace(/[&<>'"]/g, c => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      "'": "&#39;",
      '"': "&quot;"
    }[c]));

    function resolveLogoUrl(path) {
      if (!path) return '';
      if (path.startsWith('blob:') || path.startsWith('data:') || path.startsWith('http://') || path.startsWith('https://')) {
        return path;
      }
      const clean = path.replace(/^\/+/, '');
      if (clean.startsWith('../')) return clean;
      return '../' + clean;
    }

    const btnRemoveLogo = document.getElementById("btn-remove-logo");
    const sidebarLogoSlot = document.getElementById("admin-sidebar-logo-slot");
    const defaultSidebarSvg = `<svg class="w-[32px] h-[32px] shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" /><clipPath id="logo-clip-admin"><rect x="0" y="9" width="40" height="22" /></clipPath><g clip-path="url(#logo-clip-admin)"><path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#ffffff" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" /></g></svg>`;

    function showLogo(path) {
      if (!logoPreview) return;
      logoPreview.innerHTML = "";
      if (!path) {
        logoPreview.textContent = "LOGO";
        if (btnRemoveLogo) btnRemoveLogo.classList.add("hidden");
        if (sidebarLogoSlot) sidebarLogoSlot.innerHTML = defaultSidebarSvg;
        return;
      }
      if (btnRemoveLogo) btnRemoveLogo.classList.remove("hidden");
      const fullUrl = resolveLogoUrl(path);
      const isBlobOrData = fullUrl.startsWith('blob:') || fullUrl.startsWith('data:');
      const cacheBustedUrl = isBlobOrData ? fullUrl : fullUrl + (fullUrl.includes('?') ? '&' : '?') + "v=" + Date.now();

      const image = document.createElement("img");
      image.src = cacheBustedUrl;
      image.alt = "โลโก้สถาบัน";
      image.className = "w-full h-full object-contain p-2";
      image.onerror = () => {
        logoPreview.innerHTML = '<span class="text-rose-500 text-[10px] text-center p-1 font-bold">ไม่สามารถโหลดภาพ</span>';
      };
      logoPreview.appendChild(image);

      if (sidebarLogoSlot) {
        sidebarLogoSlot.innerHTML = `<img src="${cacheBustedUrl}" alt="Logo" class="w-8 h-8 object-contain rounded-lg shrink-0">`;
      }
    }

    function populate() {
      document.querySelectorAll("[data-setting]").forEach(field => {
        const key = field.dataset.setting;
        const value = savedSettings[key];
        if (field.type === "checkbox") {
          field.checked = Boolean(value);
        } else {
          field.value = value ?? "";
        }
      });
      showLogo(savedSettings.school_logo || "");
    }

    function openTab(name) {
      const selected = tabs.some(tab => tab.dataset.settingsTab === name) ? name : "general";
      tabs.forEach(tab => {
        const active = tab.dataset.settingsTab === selected;
        tab.classList.toggle("border-pink-500", active);
        tab.classList.toggle("border-transparent", !active);
        tab.classList.toggle("text-navy-950", active);
        tab.classList.toggle("text-[#94a3b8]", !active);
      });
      panels.forEach(panel => {
        panel.classList.toggle("hidden", panel.dataset.settingsPanel !== selected);
      });
      try {
        history.replaceState({}, "", "#" + selected);
      } catch (e) {
        // Ignore if running from file:// or other history restrictions
      }
    }

    async function loadSettings() {
      try {
        const data = await request("settings-api.php", {
          headers: { Accept: "application/json" },
          cache: "no-store"
        });
        savedSettings = data.settings || {};
        populate();
        document.querySelectorAll('.teacher-menu-switch').forEach(field => { field.disabled = false; });
      } catch (error) {
        showToast(error.message, true);
      }
    }

    // Teacher menu toggles
    document.querySelectorAll('.teacher-menu-switch').forEach(field => {
      field.addEventListener('change', async () => {
        const key = field.dataset.setting;
        const checked = field.checked;
        field.disabled = true;
        try {
          await request('settings-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ settings: { [key]: checked } })
          });
          savedSettings[key] = checked;
          showToast('บันทึกสิทธิ์ Teacher สำเร็จ');
        } catch (error) {
          field.checked = Boolean(savedSettings[key]);
          showToast(error.message, true);
        } finally {
          field.disabled = false;
        }
      });
    });

    tabs.forEach(tab => tab.addEventListener("click", (e) => {
      e.preventDefault();
      openTab(tab.dataset.settingsTab);
    }));

    // Form Submissions
    forms.forEach(form => {
      form.addEventListener("submit", async event => {
        event.preventDefault();
        const button = form.querySelector(".settings-save");
        if (!button) return;
        const settings = {};
        form.querySelectorAll("[data-setting]").forEach(field => {
          if (field.classList.contains('teacher-menu-switch')) return;
          settings[field.dataset.setting] = field.type === "checkbox" ? field.checked : field.value.trim();
        });
        button.disabled = true;
        const origText = button.dataset.label || button.textContent;
        button.textContent = "กำลังบันทึก...";
        try {
          await request("settings-api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ settings })
          });
          Object.assign(savedSettings, settings);
          if (settings.school_name) {
            const sidebarSchoolNameEl = document.getElementById("admin-sidebar-school-name");
            if (sidebarSchoolNameEl) sidebarSchoolNameEl.textContent = settings.school_name.toUpperCase();
          }
          showToast("บันทึกการตั้งค่าเรียบร้อยแล้ว");
          button.textContent = "✓ บันทึกสำเร็จ!";
          button.classList.add("bg-emerald-600");
          setTimeout(() => {
            button.classList.remove("bg-emerald-600");
            button.textContent = origText;
            button.disabled = false;
          }, 1500);
        } catch (error) {
          showToast(error.message, true);
          button.disabled = false;
          button.textContent = origText;
        }
      });
      const saveButton = form.querySelector(".settings-save");
      if (saveButton) saveButton.dataset.label = saveButton.textContent;
    });

    // Reset buttons
    document.querySelectorAll("[data-settings-reset]").forEach(button => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        populate();
        showToast("คืนค่าตามข้อมูลที่บันทึกล่าสุด");
      });
    });

    // School logo upload
    const chooseLogoBtn = document.getElementById("choose-school-logo");
    if (chooseLogoBtn && logoInput) {
      chooseLogoBtn.addEventListener("click", () => logoInput.click());
    }

    if (btnRemoveLogo) {
      btnRemoveLogo.addEventListener("click", async () => {
        if (!confirm("ต้องการลบโลโก้และกลับไปใช้โลโก้เริ่มต้นของระบบหรือไม่?")) return;
        btnRemoveLogo.disabled = true;
        try {
          await request("settings-api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "remove_logo" })
          });
          savedSettings.school_logo = "";
          showLogo("");
          showToast("ลบโลโก้เรียบร้อยแล้ว");
        } catch (e) {
          showToast(e.message, true);
        } finally {
          btnRemoveLogo.disabled = false;
        }
      });
    }

    if (logoInput) {
      logoInput.addEventListener("change", async () => {
        const file = logoInput.files?.[0];
        if (!file) return;

        // Show instant local preview
        const localPreview = URL.createObjectURL(file);
        showLogo(localPreview);

        const button = document.getElementById("choose-school-logo");
        const body = new FormData();
        body.append("logo", file);
        if (button) {
          button.disabled = true;
          button.textContent = "กำลังอัปโหลด...";
        }
        try {
          const data = await request("settings-api.php", { method: "POST", body });
          savedSettings.school_logo = data.path;
          showLogo(data.path);
          showToast("อัปโหลดโลโก้เรียบร้อยแล้ว");
        } catch (error) {
          showLogo(savedSettings.school_logo || "");
          showToast(error.message, true);
        } finally {
          logoInput.value = "";
          if (button) {
            button.disabled = false;
            button.textContent = "เลือกไฟล์";
          }
        }
      });
    }

    // AI API Key management
    const aiForm = document.getElementById("ai-settings-form");
    const aiInput = document.getElementById("settings-api-key");
    const aiStatus = document.getElementById("ai-key-status");
    const aiHint = document.getElementById("ai-key-hint");
    const btnTestAi = document.getElementById("btn-test-ai");
    const aiTestResult = document.getElementById("ai-test-result");
    const toggleApiKeyVis = document.getElementById("toggle-api-key-vis");

    if (toggleApiKeyVis && aiInput) {
      toggleApiKeyVis.addEventListener("click", () => {
        const isPass = aiInput.type === "password";
        aiInput.type = isPass ? "text" : "password";
        toggleApiKeyVis.textContent = isPass ? "ซ่อน" : "แสดง";
      });
    }

    async function loadAiStatus() {
      if (!aiStatus) return;
      try {
        const data = await request("ai-settings-api.php", { cache: "no-store" });
        aiStatus.textContent = data.configured ? "ตั้งค่าแล้ว" : "ยังไม่ได้ตั้งค่า";
        aiStatus.className = "px-3 py-1 rounded-full text-[11px] font-bold " +
          (data.configured ? "bg-emerald-50 text-emerald-700 border border-emerald-200" : "bg-amber-50 text-amber-700 border border-amber-200");
        if (data.configured && data.maskedKey && aiHint) {
          aiHint.textContent = "API Key ปัจจุบัน: " + data.maskedKey + " (จัดเก็บแบบเข้ารหัส AES-256) — กรอกค่าใหม่เมื่อต้องการเปลี่ยน";
        }
      } catch (error) {
        aiStatus.textContent = "ตรวจสอบไม่สำเร็จ";
        showNotice(error.message, true);
      }
    }

    if (btnTestAi) {
      btnTestAi.addEventListener("click", async () => {
        const apiKey = (aiInput ? aiInput.value.trim() : "");
        btnTestAi.disabled = true;
        btnTestAi.innerHTML = `
          <div class="w-3.5 h-3.5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <span>กำลังทดสอบการเชื่อมต่อ...</span>
        `;
        if (aiTestResult) aiTestResult.classList.add("hidden");

        try {
          const res = await request("ai-settings-api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "test", apiKey: apiKey || undefined })
          });

          if (aiTestResult) {
            aiTestResult.className = "p-4 rounded-xl text-xs font-medium border bg-emerald-50 text-emerald-800 border-emerald-200 block";
            let modelsHtml = "";
            if (res.models && res.models.length > 0) {
              modelsHtml = `<div class="mt-1 text-[11px] text-emerald-700">โมเดลที่พร้อมใช้งาน: <b>${res.models.join(', ')}</b></div>`;
            }
            aiTestResult.innerHTML = `
              <div class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                <span>${esc(res.message || "เชื่อมต่อสำเร็จ")}</span>
              </div>
              ${modelsHtml}
            `;
          }
        } catch (err) {
          if (aiTestResult) {
            aiTestResult.className = "p-4 rounded-xl text-xs font-medium border bg-rose-50 text-rose-800 border-rose-200 block";
            aiTestResult.innerHTML = `
              <div class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                <span>การเชื่อมต่อล้มเหลว</span>
              </div>
              <div class="mt-1 text-rose-700">${esc(err.message)}</div>
            `;
          }
        } finally {
          btnTestAi.disabled = false;
          btnTestAi.innerHTML = `
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <span>ทดสอบการเชื่อมต่อ API Key</span>
          `;
        }
      });
    }

    if (aiForm && aiInput) {
      aiForm.addEventListener("submit", async event => {
        event.preventDefault();
        const apiKey = aiInput.value.trim();
        if (!apiKey) {
          showToast("กรุณากรอก Gemini API Key", true);
          aiInput.focus();
          return;
        }
        const button = aiForm.querySelector(".settings-save");
        if (button) {
          button.disabled = true;
          button.textContent = "กำลังบันทึก...";
        }
        try {
          await request("ai-settings-api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ apiKey })
          });
          aiInput.value = "";
          await loadAiStatus();
          showToast("บันทึก Gemini API Key เรียบร้อยแล้ว");
          if (button) {
            button.textContent = "✓ บันทึกสำเร็จ!";
            button.classList.add("bg-emerald-600");
            setTimeout(() => {
              button.classList.remove("bg-emerald-600");
              button.textContent = "บันทึก API Key";
              button.disabled = false;
            }, 1500);
          }
        } catch (error) {
          showToast(error.message, true);
          if (button) {
            button.disabled = false;
            button.textContent = "บันทึก API Key";
          }
        }
      });
    }

    // --- Calculator Tracks ---
    const tbodyTracks = document.getElementById('calculator-tracks-tbody');
    const trackModal = document.getElementById('calculator-track-modal');
    const trackForm = document.getElementById('track-form');

    window.closeTrackModal = function() {
      if (trackModal) {
        trackModal.classList.add('hidden');
        trackModal.classList.remove('flex');
      }
    };

    const addTrackBtn = document.getElementById('btn-add-track');
    if (addTrackBtn) {
      addTrackBtn.addEventListener('click', () => {
        trackForm.reset();
        document.getElementById('track-id').value = '';
        document.getElementById('track-modal-title').textContent = 'เพิ่มกลุ่มคณะ';
        document.getElementById('weight-total-warning').classList.add('hidden');
        trackModal.classList.remove('hidden');
        trackModal.classList.add('flex');
      });
    }

    async function loadTracks() {
      if (!tbodyTracks) return;
      try {
        const data = await request('calculator-api.php');
        tbodyTracks.innerHTML = '';
        if (!data.tracks || data.tracks.length === 0) {
          tbodyTracks.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-[#65738a]">ไม่มีข้อมูลกลุ่มคณะ</td></tr>';
          return;
        }
        data.tracks.forEach(t => {
          const w = typeof t.weights === 'string' ? JSON.parse(t.weights) : t.weights;
          let weightTxt = Object.entries(w).map(([k,v]) => `${k}:${v}`).join(', ');
          const tr = document.createElement('tr');
          tr.className = 'border-b border-[#e8ecf2] hover:bg-[#f8fafc]';
          tr.innerHTML = `
            <td class="py-3 px-5"><div class="flex items-center gap-2"><span class="text-lg">${esc(t.icon)}</span><span class="font-bold">${esc(t.name)}</span></div><div class="text-[11px] text-[#65738a] mt-1">${esc(t.description)}</div></td>
            <td class="py-3 px-5 text-[11px] text-[#65738a]">${esc(weightTxt)}</td>
            <td class="py-3 px-5 font-mono">${esc(t.min_score)}%</td>
            <td class="py-3 px-5">${t.is_active ? '<span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px]">เปิดใช้งาน</span>' : '<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-bold text-[11px]">ปิด</span>'}</td>
            <td class="py-3 px-5 whitespace-nowrap">
              <button type="button" onclick="editTrack(${t.id})" class="text-indigo-600 hover:underline mr-2 text-[12px] font-bold">แก้ไข</button>
              <button type="button" onclick="deleteTrack(${t.id})" class="text-rose-500 hover:underline text-[12px] font-bold">ลบ</button>
            </td>
          `;
          tbodyTracks.appendChild(tr);
        });
        window.allTracksData = data.tracks;
      } catch(e) {
        tbodyTracks.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-red-500">${esc(e.message)}</td></tr>`;
      }
    }

    window.editTrack = function(id) {
      const t = (window.allTracksData || []).find(x => x.id == id);
      if (!t) return;
      document.getElementById('track-modal-title').textContent = 'แก้ไขกลุ่มคณะ';
      document.getElementById('track-id').value = t.id;
      document.getElementById('track-name').value = t.name;
      document.getElementById('track-icon').value = t.icon;
      document.getElementById('track-desc').value = t.description;
      document.getElementById('track-min').value = t.min_score;
      document.getElementById('track-sort').value = t.sort_order;
      document.getElementById('track-active').checked = !!t.is_active;

      document.querySelectorAll('.track-weight-input').forEach(el => el.value = '');
      const w = typeof t.weights === 'string' ? JSON.parse(t.weights) : t.weights;
      for (const k in w) {
        const el = document.querySelector(`.track-weight-input[data-subject="${k}"]`);
        if (el) el.value = w[k];
      }
      checkWeights();
      trackModal.classList.remove('hidden');
      trackModal.classList.add('flex');
    };

    window.deleteTrack = async function(id) {
      if (!confirm('ยืนยันลบกลุ่มคณะนี้?')) return;
      try {
        await request('calculator-api.php?id=' + id, { method: 'DELETE' });
        showToast('ลบกลุ่มคณะแล้ว');
        loadTracks();
      } catch(e) {
        showToast(e.message, true);
      }
    };

    function checkWeights() {
      let sum = 0;
      document.querySelectorAll('.track-weight-input').forEach(el => {
        const v = parseFloat(el.value);
        if (!isNaN(v)) sum += v;
      });
      const warning = document.getElementById('weight-total-warning');
      document.getElementById('weight-total-val').textContent = sum.toFixed(2);
      if (Math.abs(sum - 1.0) > 0.01) warning.classList.remove('hidden');
      else warning.classList.add('hidden');
      return sum;
    }

    document.querySelectorAll('.track-weight-input').forEach(el => {
      el.addEventListener('input', checkWeights);
    });

    window.saveTrack = async function() {
      const sum = checkWeights();
      if (Math.abs(sum - 1.0) > 0.01) {
        showToast('ผลรวมน้ำหนักต้องเท่ากับ 1.0 (ปัจจุบัน ' + sum.toFixed(2) + ')', true);
        return;
      }
      const w = {};
      document.querySelectorAll('.track-weight-input').forEach(el => {
        const v = parseFloat(el.value);
        if (!isNaN(v) && v > 0) w[el.dataset.subject] = v;
      });
      
      const payload = {
        id: document.getElementById('track-id').value,
        name: document.getElementById('track-name').value.trim(),
        icon: document.getElementById('track-icon').value.trim(),
        description: document.getElementById('track-desc').value.trim(),
        min_score: document.getElementById('track-min').value,
        sort_order: document.getElementById('track-sort').value,
        is_active: document.getElementById('track-active').checked ? 1 : 0,
        weights: JSON.stringify(w)
      };

      try {
        await request('calculator-api.php', {
          method: payload.id ? 'PUT' : 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        });
        showToast('บันทึกกลุ่มคณะสำเร็จ');
        closeTrackModal();
        loadTracks();
      } catch(e) {
        showToast(e.message, true);
      }
    };

    // Initialize from URL hash
    const initialHash = (window.location.hash || "").replace("#", "");
    if (initialHash) {
      openTab(initialHash);
    } else {
      openTab("general");
    }

    // Load initial data
    loadSettings();
    loadAiStatus();
    loadTracks();
  }

  // Trigger init on DOMContentLoaded or immediately if already loaded
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
