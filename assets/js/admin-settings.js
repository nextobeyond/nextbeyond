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
      if (!response.ok) throw new Error(data.error || "ดำเนินการไม่สำเร็จ");
      return data;
    }

    function showNotice(message, isError = false) {
      if (!notice) return;
      notice.textContent = message;
      notice.className = "mb-5 px-5 py-4 rounded-xl border text-[13px] font-bold " +
        (isError ? "border-red-200 bg-red-50 text-red-700" : "border-green-200 bg-green-50 text-green-700");
      window.clearTimeout(showNotice.timer);
      showNotice.timer = window.setTimeout(() => notice.classList.add("hidden"), 3500);
    }

    const esc = (v) => String(v ?? "").replace(/[&<>'"]/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"}[c]));

    function showLogo(path) {
      if (!logoPreview) return;
      logoPreview.innerHTML = "";
      if (!path) {
        logoPreview.textContent = "LOGO";
        return;
      }
      const image = document.createElement("img");
      image.src = path + "?v=" + Date.now();
      image.alt = "โลโก้สถาบัน";
      image.className = "w-full h-full object-contain p-2";
      logoPreview.appendChild(image);
    }

    function populate() {
      document.querySelectorAll("[data-setting]").forEach(field => {
        const value = savedSettings[field.dataset.setting];
        if (field.type === "checkbox") field.checked = Boolean(value);
        else field.value = value ?? "";
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
        const data = await request("settings-api", {
          headers: { Accept: "application/json" },
          cache: "no-store"
        });
        savedSettings = data.settings || {};
        populate();
        document.querySelectorAll('.teacher-menu-switch').forEach(field => { field.disabled = false; });
      } catch (error) {
        showNotice(error.message, true);
      }
    }

    document.querySelectorAll('.teacher-menu-switch').forEach(field => {
      field.addEventListener('change', async () => {
        const key = field.dataset.setting;
        const checked = field.checked;
        field.disabled = true;
        try {
          await request('settings-api', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({settings: {[key]: checked}})});
          savedSettings[key] = checked;
          showNotice('บันทึกสิทธิ์ Teacher แล้ว');
        } catch (error) {
          field.checked = Boolean(savedSettings[key]);
          showNotice(error.message, true);
        } finally { field.disabled = false; }
      });
    });

    tabs.forEach(tab => tab.addEventListener("click", (e) => {
      e.preventDefault();
      openTab(tab.dataset.settingsTab);
    }));

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
        button.textContent = "กำลังบันทึก...";
        try {
          await request("settings-api", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ settings })
          });
          Object.assign(savedSettings, settings);
          showNotice("บันทึกการตั้งค่าเรียบร้อยแล้ว");
        } catch (error) {
          showNotice(error.message, true);
        } finally {
          button.disabled = false;
          button.textContent = button.dataset.label || "บันทึกการตั้งค่า";
        }
      });
      const saveButton = form.querySelector(".settings-save");
      if (saveButton) saveButton.dataset.label = saveButton.textContent;
    });

    document.querySelectorAll("[data-settings-reset]").forEach(button => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        populate();
      });
    });

    const chooseLogoBtn = document.getElementById("choose-school-logo");
    if (chooseLogoBtn && logoInput) {
      chooseLogoBtn.addEventListener("click", () => logoInput.click());
    }

    if (logoInput) {
      logoInput.addEventListener("change", async () => {
        const file = logoInput.files?.[0];
        if (!file) return;
        const button = document.getElementById("choose-school-logo");
        const body = new FormData();
        body.append("logo", file);
        if (button) {
          button.disabled = true;
          button.textContent = "กำลังอัปโหลด...";
        }
        try {
          const data = await request("settings-api", { method: "POST", body });
          savedSettings.school_logo = data.path;
          showLogo(data.path);
          showNotice("อัปโหลดโลโก้เรียบร้อยแล้ว");
        } catch (error) {
          showNotice(error.message, true);
        } finally {
          logoInput.value = "";
          if (button) {
            button.disabled = false;
            button.textContent = "เลือกไฟล์";
          }
        }
      });
    }

    const aiForm = document.getElementById("ai-settings-form");
    const aiInput = document.getElementById("settings-api-key");
    const aiStatus = document.getElementById("ai-key-status");
    const aiHint = document.getElementById("ai-key-hint");

    async function loadAiStatus() {
      if (!aiStatus) return;
      try {
        const data = await request("ai-settings-api", { cache: "no-store" });
        aiStatus.textContent = data.configured ? "ตั้งค่าแล้ว" : "ยังไม่ได้ตั้งค่า";
        aiStatus.className = "px-3 py-1 rounded-full text-[11px] font-bold " +
          (data.configured ? "bg-green-50 text-green-700" : "bg-amber-50 text-amber-700");
        if (data.configured && data.maskedKey && aiHint) {
          aiHint.textContent = "API Key ปัจจุบัน: " + data.maskedKey + " — กรอกค่าใหม่เมื่อต้องการเปลี่ยน";
        }
      } catch (error) {
        aiStatus.textContent = "ตรวจสอบไม่สำเร็จ";
        showNotice(error.message, true);
      }
    }

    if (aiForm && aiInput) {
      aiForm.addEventListener("submit", async event => {
        event.preventDefault();
        const apiKey = aiInput.value.trim();
        if (!apiKey) {
          showNotice("กรุณากรอก Gemini API Key", true);
          aiInput.focus();
          return;
        }
        const button = aiForm.querySelector(".settings-save");
        if (button) button.disabled = true;
        try {
          await request("ai-settings-api", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ apiKey })
          });
          aiInput.value = "";
          await loadAiStatus();
          showNotice("บันทึก Gemini API Key เรียบร้อยแล้ว");
        } catch (error) {
          showNotice(error.message, true);
        } finally {
          if (button) button.disabled = false;
        }
      });
    }

    // --- Calculator Tracks ---
    const tbodyTracks = document.getElementById('calculator-tracks-tbody');
    const trackModal = document.getElementById('calculator-track-modal');
    const trackForm = document.getElementById('track-form');

    window.closeTrackModal = function() {
      if(trackModal) trackModal.classList.add('hidden');
    };

    const addTrackBtn = document.getElementById('btn-add-track');
    if(addTrackBtn) {
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
      if(!tbodyTracks) return;
      try {
        const data = await request('calculator-api.php');
        tbodyTracks.innerHTML = '';
        if(!data.tracks || data.tracks.length === 0) {
          tbodyTracks.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-[#65738a]">ไม่มีข้อมูลกลุ่มคณะ</td></tr>';
          return;
        }
        data.tracks.forEach(t => {
          const w = typeof t.weights === 'string' ? JSON.parse(t.weights) : t.weights;
          let weightTxt = Object.entries(w).map(([k,v]) => `${k}:${v}`).join(', ');
          const tr = document.createElement('tr');
          tr.className = 'border-b border-[#e8ecf2] hover:bg-[#f8fafc]';
          tr.innerHTML = `
            <td class="py-3 px-5"><div class="flex items-center gap-2"><span class="text-lg">${t.icon}</span><span class="font-bold">${t.name}</span></div><div class="text-[11px] text-[#65738a] mt-1">${t.description}</div></td>
            <td class="py-3 px-5 text-[11px] text-[#65738a]">${weightTxt}</td>
            <td class="py-3 px-5 font-mono">${t.min_score}%</td>
            <td class="py-3 px-5">${t.is_active ? '<span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700 font-bold text-[11px]">เปิดใช้งาน</span>' : '<span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-bold text-[11px]">ปิด</span>'}</td>
            <td class="py-3 px-5">
              <button onclick="editTrack(${t.id})" class="text-indigo-600 hover:underline mr-2 text-[12px] font-bold">แก้ไข</button>
              <button onclick="deleteTrack(${t.id})" class="text-red-500 hover:underline text-[12px] font-bold">ลบ</button>
            </td>
          `;
          tbodyTracks.appendChild(tr);
        });
        window.allTracksData = data.tracks;
      } catch(e) {
        tbodyTracks.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-red-500">${e.message}</td></tr>`;
      }
    }

    window.editTrack = function(id) {
      const t = window.allTracksData.find(x => x.id == id);
      if(!t) return;
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
      for(const k in w) {
        const el = document.querySelector(`.track-weight-input[data-subject="${k}"]`);
        if(el) el.value = w[k];
      }
      checkWeights();
      trackModal.classList.remove('hidden');
      trackModal.classList.add('flex');
    };

    window.deleteTrack = async function(id) {
      if(!confirm('ยืนยันลบกลุ่มคณะนี้?')) return;
      try {
        await request('calculator-api.php?id='+id, { method: 'DELETE' });
        showNotice('ลบกลุ่มคณะแล้ว');
        loadTracks();
      } catch(e) { showNotice(e.message, true); }
    };

    function checkWeights() {
      let sum = 0;
      document.querySelectorAll('.track-weight-input').forEach(el => {
        const v = parseFloat(el.value);
        if(!isNaN(v)) sum += v;
      });
      const warning = document.getElementById('weight-total-warning');
      document.getElementById('weight-total-val').textContent = sum.toFixed(2);
      if(Math.abs(sum - 1.0) > 0.01) warning.classList.remove('hidden');
      else warning.classList.add('hidden');
      return sum;
    }

    document.querySelectorAll('.track-weight-input').forEach(el => {
      el.addEventListener('input', checkWeights);
    });

    window.saveTrack = async function() {
      const sum = checkWeights();
      if(Math.abs(sum - 1.0) > 0.01) {
        showNotice('ผลรวมน้ำหนักต้องเท่ากับ 1.0', true);
        return;
      }
      const w = {};
      document.querySelectorAll('.track-weight-input').forEach(el => {
        const v = parseFloat(el.value);
        if(!isNaN(v) && v > 0) w[el.dataset.subject] = v;
      });
      
      const payload = {
        id: document.getElementById('track-id').value,
        name: document.getElementById('track-name').value,
        icon: document.getElementById('track-icon').value,
        description: document.getElementById('track-desc').value,
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
        showNotice('บันทึกข้อมูลแล้ว');
        closeTrackModal();
        loadTracks();
      } catch(e) { showNotice(e.message, true); }
    };
    })();

