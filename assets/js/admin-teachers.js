(() => {
  "use strict";
  const tbody = document.getElementById("teachers-tbody");
  if (!tbody) return;
  const modal = document.getElementById("teacher-modal");
  const form = document.getElementById("teacher-form");
  const search = document.getElementById("teachers-search");
  const errorBox = document.getElementById("teacher-form-error");
  const esc = value => String(value ?? "").replace(/[&<>'"]/g, char => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[char]));
  let teachers = [];

  const COMMON_SUBJECTS = [
    "คณิตศาสตร์",
    "วิทยาศาสตร์",
    "ฟิสิกส์",
    "เคมี",
    "ชีววิทยา",
    "ภาษาอังกฤษ",
    "ภาษาไทย",
    "สังคมศึกษา",
    "คอมพิวเตอร์และเทคโนโลยี"
  ];
  let currentSubjects = [];

  function syncSubjectsUI() {
    const chipsContainer = document.getElementById("selected-subject-chips");
    const pillsContainer = document.getElementById("quick-subject-pills");
    const hiddenInput = document.getElementById("teacher-subjects-val");
    if (!hiddenInput) return;

    hiddenInput.value = currentSubjects.join(", ");

    if (chipsContainer) {
      chipsContainer.innerHTML = currentSubjects.map(s => 
        `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-pink-50 text-pink-700 border border-pink-200 text-[12px] font-bold">
          ${esc(s)}
          <button type="button" data-remove-subj="${esc(s)}" class="text-pink-400 hover:text-pink-800 text-[14px] leading-none ml-1 cursor-pointer font-black" title="ลบวิชานี้">×</button>
        </span>`
      ).join("");
    }

    if (pillsContainer) {
      pillsContainer.innerHTML = COMMON_SUBJECTS.map(s => {
        const isSelected = currentSubjects.includes(s);
        return isSelected
          ? `<button type="button" data-toggle-subj="${esc(s)}" class="px-2.5 py-1 rounded-lg text-[12px] font-bold bg-pink-500 text-white shadow-sm transition-all flex items-center gap-1 cursor-pointer hover:bg-pink-600">✓ ${esc(s)}</button>`
          : `<button type="button" data-toggle-subj="${esc(s)}" class="px-2.5 py-1 rounded-lg text-[12px] font-medium bg-[#f1f5f9] text-[#475569] hover:bg-[#e2e8f0] transition-all cursor-pointer">+ ${esc(s)}</button>`;
      }).join("");
    }
  }

  function toggleSubject(subj) {
    const trimmed = subj.trim();
    if (!trimmed) return;
    const idx = currentSubjects.indexOf(trimmed);
    if (idx >= 0) {
      currentSubjects.splice(idx, 1);
    } else {
      currentSubjects.push(trimmed);
    }
    syncSubjectsUI();
  }

  function addCustomSubject(subj) {
    const parts = subj.split(",").map(s => s.trim()).filter(Boolean);
    parts.forEach(p => {
      if (!currentSubjects.includes(p)) {
        currentSubjects.push(p);
      }
    });
    syncSubjectsUI();
  }

  // Event delegation for subject chips and quick pills
  modal.addEventListener("click", event => {
    const toggleBtn = event.target.closest("[data-toggle-subj]");
    if (toggleBtn) {
      event.preventDefault();
      toggleSubject(toggleBtn.dataset.toggleSubj);
      return;
    }
    const removeBtn = event.target.closest("[data-remove-subj]");
    if (removeBtn) {
      event.preventDefault();
      const idx = currentSubjects.indexOf(removeBtn.dataset.removeSubj);
      if (idx >= 0) {
        currentSubjects.splice(idx, 1);
        syncSubjectsUI();
      }
      return;
    }
  });

  const customInput = document.getElementById("custom-subject-input");
  if (customInput) {
    customInput.addEventListener("keydown", event => {
      if (event.key === "Enter" || event.key === ",") {
        event.preventDefault();
        if (customInput.value.trim()) {
          addCustomSubject(customInput.value);
          customInput.value = "";
        }
      }
    });
    customInput.addEventListener("blur", () => {
      if (customInput.value.trim()) {
        addCustomSubject(customInput.value);
        customInput.value = "";
      }
    });
  }

  async function api(url = "teachers-api", options = {}) {
    const response = await fetch(url, {...options, headers: {"Content-Type":"application/json", ...(options.headers || {})}});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || "ไม่สามารถเชื่อมต่อระบบข้อมูลคุณครูได้");
    return data;
  }

  function initials(item) {
    const name = (item.nickname || item.firstName || item.lastName || "ครู").trim();
    return esc(name.slice(0, 2));
  }

  function renderSubjects(str) {
    if (!str || !str.trim()) {
      return '<span class="text-[#94a3b8] text-[13px]">ยังไม่ได้กำหนด</span>';
    }
    const list = str.split(",").map(s => s.trim()).filter(Boolean);
    if (!list.length) {
      return '<span class="text-[#94a3b8] text-[13px]">ยังไม่ได้กำหนด</span>';
    }
    return `<div class="flex flex-wrap gap-1">` + list.map(s => 
      `<span class="inline-block px-2.5 py-0.5 rounded-lg text-[12px] font-semibold bg-pink-50 text-pink-600 border border-pink-100">${esc(s)}</span>`
    ).join("") + `</div>`;
  }

  function render() {
    const query = search.value.trim().toLocaleLowerCase("th");
    const rows = teachers.filter(item => {
      const haystack = `${item.firstName || ""} ${item.lastName || ""} ${item.nickname || ""} ${item.subjects || ""} ${item.email || ""} ${item.phone || ""}`.toLocaleLowerCase("th");
      return haystack.includes(query);
    });
    document.getElementById("teachers-count").textContent = `แสดง ${rows.length} จาก ${teachers.length} รายการ`;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="p-10 text-center text-[#65738a]">ยังไม่มีข้อมูลคุณครู</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(item => {
      const nameParts = [item.firstName, item.lastName].filter(Boolean).join(" ");
      const isNicknameSameAsFirst = item.nickname && item.firstName === item.nickname;
      const displayName = nameParts || item.nickname || "คุณครู";
      const nicknameBadge = item.nickname && nameParts && !isNicknameSameAsFirst
        ? ` <span class="text-pink-600 font-semibold">(${esc(item.nickname)})</span>`
        : "";

      const metaParts = [];
      if (item.email) metaParts.push(esc(item.email));
      else metaParts.push('<span class="text-[#94a3b8]">ไม่ได้ระบุอีเมล</span>');
      if (item.phone) metaParts.push(esc(item.phone));

      return `<tr class="hover:bg-[#f8fafc]">
        <td class="px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-full bg-pink-50 text-pink-500 flex items-center justify-center font-black text-[13px]">${initials(item)}</div>
            <div>
              <div class="font-bold text-[14px]">
                ${esc(displayName)}${nicknameBadge}
              </div>
              <div class="text-[12px] text-[#65738a]">${metaParts.join(" · ")}</div>
            </div>
          </div>
        </td>
        <td class="px-4 py-4 max-w-[280px]">${renderSubjects(item.subjects)}</td>
        <td class="px-4 py-4 text-center font-bold text-[14px]">${item.courseCount}</td>
        <td class="px-4 py-4"><label class="flex items-center gap-2 text-[12px] font-bold"><input type="checkbox" data-active="${esc(item.id)}" ${item.isActive ? "checked" : ""}> ${item.isActive ? "Active" : "Inactive"}</label></td>
        <td class="px-4 py-4 text-right whitespace-nowrap">
          <button type="button" data-edit="${esc(item.id)}" class="text-pink-500 hover:text-pink-600 text-[12px] font-bold mr-3 cursor-pointer">แก้ไข</button>
          <button type="button" data-delete="${esc(item.id)}" class="text-red-500 hover:text-red-600 text-[12px] font-bold cursor-pointer">ลบ</button>
        </td>
      </tr>`;
    }).join("");
  }

  async function load() {
    try {
      teachers = (await api()).teachers || [];
      render();
    } catch (error) {
      tbody.innerHTML = `<tr><td colspan="5" class="p-10 text-center text-red-500">${esc(error.message)}</td></tr>`;
    }
  }

  function showModal(show, editItem = null) {
    modal.classList.toggle("hidden", !show);
    modal.classList.toggle("flex", show);
    errorBox.classList.add("hidden");

    const modalTitle = document.getElementById("modal-title");
    const saveBtn = document.getElementById("save-teacher");
    const pwdLabel = document.getElementById("password-label");
    const pwdInput = document.getElementById("teacher-password");
    if (customInput) customInput.value = "";

    if (!show) {
      form.reset();
      form.elements.id.value = "";
      currentSubjects = [];
      syncSubjectsUI();
      return;
    }

    if (editItem) {
      if (modalTitle) modalTitle.textContent = "แก้ไขข้อมูลคุณครู";
      if (saveBtn) saveBtn.textContent = "บันทึกการแก้ไข";
      if (pwdLabel) pwdLabel.textContent = "รหัสผ่านใหม่ (เว้นว่างไว้หากไม่เปลี่ยน)";
      if (pwdInput) {
        pwdInput.removeAttribute("required");
        pwdInput.placeholder = "เว้นว่างไว้หากไม่เปลี่ยน";
      }

      form.elements.id.value = editItem.id;
      form.elements.firstName.value = (editItem.firstName === editItem.nickname) ? "" : (editItem.firstName || "");
      form.elements.lastName.value = editItem.lastName || "";
      form.elements.nickname.value = editItem.nickname || "";
      form.elements.phone.value = editItem.phone || "";
      form.elements.email.value = editItem.email || "";
      form.elements.password.value = "";

      currentSubjects = (editItem.subjects || "").split(",").map(s => s.trim()).filter(Boolean);
      syncSubjectsUI();
      form.elements.firstName.focus();
    } else {
      if (modalTitle) modalTitle.textContent = "เพิ่มคุณครูผู้สอน";
      if (saveBtn) saveBtn.textContent = "บันทึกคุณครู";
      if (pwdLabel) pwdLabel.textContent = "รหัสผ่านเริ่มต้น";
      if (pwdInput) {
        pwdInput.removeAttribute("required");
        pwdInput.placeholder = "เว้นว่างได้ (ค่าเริ่มต้น: 12345678)";
      }

      form.reset();
      form.elements.id.value = "";
      currentSubjects = [];
      syncSubjectsUI();
      form.elements.firstName.focus();
    }
  }

  document.getElementById("add-teacher").addEventListener("click", () => showModal(true));
  document.querySelectorAll("[data-close-modal]").forEach(button => button.addEventListener("click", () => showModal(false)));
  modal.addEventListener("click", event => { if (event.target === modal) showModal(false); });
  search.addEventListener("input", render);

  form.addEventListener("submit", async event => {
    event.preventDefault();
    const save = document.getElementById("save-teacher");
    save.disabled = true;
    errorBox.classList.add("hidden");

    // Ensure custom input text is captured before submitting
    if (customInput && customInput.value.trim()) {
      addCustomSubject(customInput.value);
      customInput.value = "";
    }

    const formData = Object.fromEntries(new FormData(form));
    const isEdit = Boolean(formData.id);

    try {
      if (isEdit) {
        await api("teachers-api", {method: "PATCH", body: JSON.stringify(formData)});
      } else {
        await api("teachers-api", {method: "POST", body: JSON.stringify(formData)});
      }
      showModal(false);
      await load();
    } catch (error) {
      errorBox.textContent = error.message;
      errorBox.classList.remove("hidden");
    } finally {
      save.disabled = false;
    }
  });

  tbody.addEventListener("change", async event => {
    const input = event.target.closest("[data-active]");
    if (!input) return;
    try {
      await api("teachers-api", {method: "PATCH", body: JSON.stringify({id: input.dataset.active, isActive: input.checked})});
      await load();
    } catch (error) {
      alert(error.message);
      await load();
    }
  });

  tbody.addEventListener("click", async event => {
    const editBtn = event.target.closest("[data-edit]");
    if (editBtn) {
      const teacher = teachers.find(t => String(t.id) === String(editBtn.dataset.edit));
      if (teacher) showModal(true, teacher);
      return;
    }

    const button = event.target.closest("[data-delete]");
    if (!button || !confirm("ยืนยันการลบบัญชีคุณครูนี้?")) return;
    try {
      await api(`teachers-api?id=${encodeURIComponent(button.dataset.delete)}`, {method: "DELETE"});
      await load();
    } catch (error) {
      alert(error.message);
    }
  });

  load();
})();
