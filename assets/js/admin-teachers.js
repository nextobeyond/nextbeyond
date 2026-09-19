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
        <td class="px-4 py-4 text-[13px]">${esc(item.subjects || "ยังไม่ได้กำหนด")}</td>
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

    if (!show) {
      form.reset();
      form.elements.id.value = "";
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
      form.elements.subjects.value = editItem.subjects || "";
      form.elements.phone.value = editItem.phone || "";
      form.elements.email.value = editItem.email || "";
      form.elements.password.value = "";
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
