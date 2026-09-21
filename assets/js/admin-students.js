(() => {
  "use strict";

  const body = document.getElementById("students-body");
  const searchInput = document.getElementById("student-search");
  const gradeFilter = document.getElementById("grade-filter");
  const enrollmentStatusFilter = document.getElementById("enrollment-status-filter");
  const countEl = document.getElementById("student-count");

  // Metrics
  const metricTotalStudents = document.getElementById("metric-total-students");
  const metricActiveEnrollments = document.getElementById("metric-active-enrollments");
  const metricCoursesSold = document.getElementById("metric-courses-sold");
  const metricTrialStudents = document.getElementById("metric-trial-students");
  const metricExpiringSoon = document.getElementById("metric-expiring-soon");

  // Selection & Bulk Elements
  const selectAllCheckbox = document.getElementById("select-all-students");
  const bulkBar = document.getElementById("bulk-bar");
  const bulkSelectedCount = document.getElementById("bulk-selected-count");
  const btnBulkAssignCourse = document.getElementById("btn-bulk-assign-course");
  const btnBulkAssignClass = document.getElementById("btn-bulk-assign-class");
  const btnBulkCancel = document.getElementById("btn-bulk-cancel");

  // Add Student Modal
  const studentModal = document.getElementById("student-modal");
  const studentForm = document.getElementById("student-form");
  const studentError = document.getElementById("student-error");

  // Bulk Course Modal
  const bulkCourseModal = document.getElementById("bulk-assign-course-modal");
  const bulkCourseForm = document.getElementById("bulk-assign-course-form");
  const bulkCourseSelectedCount = document.getElementById("bulk-course-selected-count");
  const bulkCourseSelectedNames = document.getElementById("bulk-course-selected-names");
  const bulkCourseSelect = document.getElementById("bulk-course-id");
  const bulkClassGroupSelect = document.getElementById("bulk-class-group-id");
  const bulkDurationPreset = document.getElementById("bulk-duration-preset");
  const bulkCustomEndDateWrapper = document.getElementById("bulk-custom-end-date-wrapper");
  const bulkCustomEndDate = document.getElementById("bulk-custom-end-date");
  const bulkCourseFeedback = document.getElementById("bulk-course-feedback");

  // Bulk Class Modal
  const bulkClassModal = document.getElementById("bulk-assign-class-modal");
  const bulkClassForm = document.getElementById("bulk-assign-class-form");
  const bulkClassSelectedCount = document.getElementById("bulk-class-selected-count");
  const bulkClassSelectedNames = document.getElementById("bulk-class-selected-names");
  const bulkTargetClassSelect = document.getElementById("bulk-target-class-group-id");
  const bulkTargetClassInfo = document.getElementById("bulk-target-class-info");
  const bulkClassInfoTitle = document.getElementById("bulk-class-info-title");
  const bulkClassInfoDetails = document.getElementById("bulk-class-info-details");
  const bulkClassFeedback = document.getElementById("bulk-class-feedback");

  // Popover
  const popover = document.getElementById("quick-courses-popover");
  const popoverStudentName = document.getElementById("popover-student-name");
  const popoverBadgeCount = document.getElementById("popover-badge-count");
  const popoverCoursesList = document.getElementById("popover-courses-list");
  const popoverDetailLink = document.getElementById("popover-detail-link");

  let allStudents = [];
  let availableCourses = [];
  let availableClassGroups = [];
  let selectedStudentIds = new Set();
  let activePopoverTimeout = null;

  const esc = v => String(v ?? "").replace(/[&<>'"]/g, c => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    "'": "&#39;",
    '"': "&quot;"
  }[c]));

  const resolveAvatarUrl = url => {
    if (!url) return "";
    if (url.startsWith("data:") || url.startsWith("http://") || url.startsWith("https://")) {
      return url;
    }
    let clean = url.replace(/^\/+/, "");
    if (clean.startsWith("../")) {
      clean = clean.substring(3);
    }
    return "../" + clean;
  };

  async function api(url, options = {}) {
    const res = await fetch(url, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        ...(options.headers || {})
      }
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(data.error || "เกิดข้อผิดพลาดในการเชื่อมต่อ");
      err.data = data;
      throw err;
    }
    return data;
  }

  function updateMetrics(metrics) {
    if (!metrics) return;
    if (metricTotalStudents) metricTotalStudents.textContent = Number(metrics.total_students || 0).toLocaleString();
    if (metricActiveEnrollments) metricActiveEnrollments.textContent = Number(metrics.active_enrollments || 0).toLocaleString();
    if (metricCoursesSold) metricCoursesSold.textContent = Number(metrics.courses_sold || 0).toLocaleString();
    if (metricTrialStudents) metricTrialStudents.textContent = Number(metrics.trial_students || 0).toLocaleString();
    if (metricExpiringSoon) metricExpiringSoon.textContent = Number(metrics.expiring_soon || 0).toLocaleString();
  }

  function getFilteredStudents() {
    const q = (searchInput.value || "").trim().toLowerCase();
    const grade = gradeFilter.value;
    const enrollStatus = enrollmentStatusFilter.value;

    return allStudents.filter(st => {
      // Grade filter
      if (grade && (st.grade || "ม.5") !== grade) return false;

      // Enrollment status filter
      if (enrollStatus === "has_active" && Number(st.enrollment_count) <= 0) return false;
      if (enrollStatus === "no_active" && Number(st.enrollment_count) > 0) return false;

      // Search query
      if (q) {
        const fullName = `${st.first_name || ""} ${st.last_name || ""} ${st.nickname || ""}`.toLowerCase();
        const email = (st.email || "").toLowerCase();
        const phone = (st.phone || "").toLowerCase();
        const courses = (st.courses || []).join(" ").toLowerCase();
        const classGroups = (st.class_groups || []).join(" ").toLowerCase();

        const match = fullName.includes(q) ||
          email.includes(q) ||
          phone.includes(q) ||
          courses.includes(q) ||
          classGroups.includes(q);

        if (!match) return false;
      }

      return true;
    });
  }

  function updateBulkBar() {
    const count = selectedStudentIds.size;
    if (count > 0) {
      bulkBar.classList.remove("hidden");
      bulkSelectedCount.textContent = count;
    } else {
      bulkBar.classList.add("hidden");
    }

    // Update select all checkbox state
    const currentList = getFilteredStudents();
    if (currentList.length > 0 && currentList.every(s => selectedStudentIds.has(s.id))) {
      selectAllCheckbox.checked = true;
      selectAllCheckbox.indeterminate = false;
    } else if (currentList.some(s => selectedStudentIds.has(s.id))) {
      selectAllCheckbox.checked = false;
      selectAllCheckbox.indeterminate = true;
    } else {
      selectAllCheckbox.checked = false;
      selectAllCheckbox.indeterminate = false;
    }
  }

  function render() {
    const list = getFilteredStudents();
    countEl.textContent = `${list.length} รายการ (จากทั้งหมด ${allStudents.length} คน)`;

    if (!list.length) {
      body.innerHTML = `
        <tr>
          <td colspan="7" class="p-12 text-center text-slate-400">
            <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <div class="font-medium">ไม่พบข้อมูลนักเรียนที่ตรงกับเงื่อนไข</div>
          </td>
        </tr>
      `;
      updateBulkBar();
      return;
    }

    body.innerHTML = list.map(st => {
      const isSelected = selectedStudentIds.has(st.id);
      const enCount = Number(st.enrollment_count || 0);
      const classGroups = st.class_groups || [];
      const grade = esc(st.grade || "ม.5");
      const rawInitial = st.nickname ? st.nickname.trim() : (st.first_name ? st.first_name.trim() : "?");
      const initial = esc(Array.from(rawInitial)[0] || "?");
      const avatarSrc = resolveAvatarUrl(st.avatar_url);

      const avatarHtml = avatarSrc
        ? `<div class="w-9 h-9 rounded-full overflow-hidden shrink-0 border border-slate-200/80 bg-pink-50 relative flex items-center justify-center shadow-2xs">
            <img src="${esc(avatarSrc)}" alt="${esc(st.first_name)}" class="w-full h-full object-cover" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
            <div class="w-full h-full bg-pink-100 text-pink-600 font-bold text-sm items-center justify-center hidden">
              ${initial}
            </div>
          </div>`
        : `<div class="w-9 h-9 rounded-full bg-pink-100 text-pink-600 flex items-center justify-center font-bold text-sm shrink-0">
            ${initial}
          </div>`;

      return `
        <tr class="hover:bg-slate-50/70 transition-colors ${isSelected ? "bg-pink-50/30" : ""}">
          <td class="p-4 text-center">
            <input type="checkbox" data-select-id="${st.id}" class="w-4 h-4 rounded text-pink-500 focus:ring-pink-400 border-slate-300 cursor-pointer" ${isSelected ? "checked" : ""}>
          </td>
          <td class="p-4">
            <div class="flex items-center gap-3">
              ${avatarHtml}
              <div>
                <a href="student-detail.php?id=${st.id}" class="font-bold text-navy-950 hover:text-pink-600 transition-colors">
                  ${esc(st.first_name)} ${esc(st.last_name)}
                  ${st.nickname ? `<span class="text-xs font-medium text-slate-500">(${esc(st.nickname)})</span>` : ""}
                </a>
                <div class="text-[12px] text-slate-400 flex items-center gap-2">
                  <span>${esc(st.email)}</span>
                  ${st.phone ? `<span>·</span><span>${esc(st.phone)}</span>` : ""}
                </div>
              </div>
            </div>
          </td>
          <td class="p-4">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
              ${grade}
            </span>
          </td>
          <td class="p-4">
            ${enCount > 0 ? `
              <button type="button" data-popover-btn="${st.id}" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-pink-50 text-pink-700 font-bold text-xs hover:bg-pink-100 transition-colors cursor-pointer border border-pink-200/60">
                <span class="w-1.5 h-1.5 rounded-full bg-pink-500"></span>
                <span>${enCount} คอร์ส</span>
                <svg class="w-3 h-3 text-pink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
              </button>
            ` : `
              <span class="text-xs text-slate-400">ไม่มีคอร์ส Active</span>
            `}
          </td>
          <td class="p-4">
            ${classGroups.length > 0 ? `
              <div class="flex flex-wrap gap-1 max-w-[240px]">
                ${classGroups.map(cg => `
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200/60">
                    ${esc(cg)}
                  </span>
                `).join("")}
              </div>
            ` : `
              <span class="text-xs text-slate-400">—</span>
            `}
          </td>
          <td class="p-4">
            <label class="inline-flex items-center gap-2 text-xs font-medium cursor-pointer">
              <input data-active-toggle="${st.id}" type="checkbox" class="w-4 h-4 rounded text-pink-500 focus:ring-pink-400 border-slate-300" ${Number(st.is_active) ? "checked" : ""}>
              <span class="${Number(st.is_active) ? "text-emerald-600 font-bold" : "text-slate-400"}">${Number(st.is_active) ? "Active" : "Inactive"}</span>
            </label>
          </td>
          <td class="p-4 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1.5">
              <a href="student-detail.php?id=${st.id}" class="h-8 px-3 rounded-xl bg-pink-50 hover:bg-pink-100 text-pink-600 font-bold text-xs flex items-center gap-1.5 transition-colors border border-pink-200/50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>จัดการสิทธิ์</span>
              </a>
              <button data-delete-btn="${st.id}" class="h-8 w-8 inline-flex items-center justify-center rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="ลบนักเรียน">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join("");

    updateBulkBar();
  }

  // Quick View Popover handler
  function showCoursesPopover(studentId, triggerEl) {
    const student = allStudents.find(s => s.id == studentId);
    if (!student) return;

    popoverStudentName.textContent = `${student.first_name} ${student.last_name}`;
    popoverBadgeCount.textContent = `${student.enrollment_count} คอร์ส`;
    popoverDetailLink.href = `student-detail.php?id=${student.id}`;

    const list = student.enrollments_detail || [];
    if (list.length === 0) {
      popoverCoursesList.innerHTML = `<div class="text-slate-400 text-center py-2">ไม่มีคอร์สเรียน Active</div>`;
    } else {
      popoverCoursesList.innerHTML = list.map(en => `
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start justify-between gap-2">
          <div>
            <div class="font-bold text-navy-950 text-[12px] leading-tight">${esc(en.course_title)}</div>
            <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1.5">
              <span class="font-semibold text-pink-600">${esc(en.subject || "ทั่วไป")}</span>
              ${en.class_group_name ? `<span>·</span><span class="text-blue-600 font-semibold">${esc(en.class_group_name)}</span>` : ""}
            </div>
          </div>
          <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ${en.status === "trial" ? "bg-amber-100 text-amber-700" : "bg-emerald-100 text-emerald-700"}">
            ${esc(en.status)}
          </span>
        </div>
      `).join("");
    }

    const rect = triggerEl.getBoundingClientRect();
    popover.style.left = `${Math.min(window.innerWidth - 360, Math.max(10, rect.left - 50))}px`;
    popover.style.top = `${rect.bottom + 8}px`;
    popover.classList.remove("hidden");
  }

  function hideCoursesPopover() {
    popover.classList.add("hidden");
  }

  async function loadData() {
    try {
      const [studentsRes, classesRes] = await Promise.all([
        api("students-api.php"),
        api("enrollments-api.php?action=class_groups")
      ]);

      allStudents = studentsRes.students || [];
      updateMetrics(studentsRes.metrics);

      availableClassGroups = classesRes.class_groups || [];
      availableCourses = classesRes.courses || [];

      // Populate Bulk Course dropdown
      bulkCourseSelect.innerHTML = `
        <option value="">-- เลือกคอร์สเรียน --</option>
        ${availableCourses.map(c => `
          <option value="${c.id}">${esc(c.title)} (${esc(c.subject || "ทั่วไป")})</option>
        `).join("")}
      `;

      render();
    } catch (e) {
      body.innerHTML = `
        <tr>
          <td colspan="7" class="p-12 text-center text-rose-500">
            <div class="font-bold">เกิดข้อผิดพลาดในการโหลดข้อมูล:</div>
            <div class="text-xs mt-1">${esc(e.message)}</div>
          </td>
        </tr>
      `;
    }
  }

  // Event Listeners

  // Search & Filters
  searchInput.addEventListener("input", () => render());
  gradeFilter.addEventListener("change", () => render());
  enrollmentStatusFilter.addEventListener("change", () => render());

  // Select all checkbox
  selectAllCheckbox.addEventListener("change", e => {
    const list = getFilteredStudents();
    if (e.target.checked) {
      list.forEach(s => selectedStudentIds.add(s.id));
    } else {
      list.forEach(s => selectedStudentIds.delete(s.id));
    }
    render();
  });

  // Table clicks
  body.addEventListener("change", async e => {
    // Row select
    if (e.target.dataset.selectId) {
      const id = parseInt(e.target.dataset.selectId, 10);
      if (e.target.checked) {
        selectedStudentIds.add(id);
      } else {
        selectedStudentIds.delete(id);
      }
      updateBulkBar();
      // Update row style
      const tr = e.target.closest("tr");
      if (tr) tr.classList.toggle("bg-pink-50/30", e.target.checked);
      return;
    }

    // Toggle active status
    if (e.target.dataset.activeToggle) {
      const id = e.target.dataset.activeToggle;
      try {
        await api("students-api.php", {
          method: "PATCH",
          body: JSON.stringify({ id, isActive: e.target.checked })
        });
        const st = allStudents.find(s => s.id == id);
        if (st) st.is_active = e.target.checked ? 1 : 0;
        render();
      } catch (err) {
        alert("ไม่สามารถเปลี่ยนสถานะบัญชีได้: " + err.message);
        e.target.checked = !e.target.checked;
      }
    }
  });

  body.addEventListener("click", async e => {
    // Delete student
    const delBtn = e.target.closest("[data-delete-btn]");
    if (delBtn) {
      const id = delBtn.dataset.deleteBtn;
      const st = allStudents.find(s => s.id == id);
      const name = st ? `${st.first_name} ${st.last_name}` : "นักเรียน";
      if (confirm(`ยืนยันการลบบัญชีของ ${name}?\nข้อมูลสิทธิ์คอร์สและการเข้าเรียนจะถูกลบด้วย`)) {
        try {
          await api(`students-api.php?id=${id}`, { method: "DELETE" });
          selectedStudentIds.delete(parseInt(id, 10));
          await loadData();
        } catch (err) {
          alert("ไม่สามารถลบได้: " + err.message);
        }
      }
      return;
    }

    // Popover click
    const popBtn = e.target.closest("[data-popover-btn]");
    if (popBtn) {
      e.stopPropagation();
      const id = popBtn.dataset.popoverBtn;
      if (!popover.classList.contains("hidden") && popoverDetailLink.href.includes(`id=${id}`)) {
        hideCoursesPopover();
      } else {
        showCoursesPopover(id, popBtn);
      }
    }
  });

  // Close popover on outside click
  document.addEventListener("click", e => {
    if (!popover.contains(e.target) && !e.target.closest("[data-popover-btn]")) {
      hideCoursesPopover();
    }
  });

  // Cancel bulk selection
  btnBulkCancel.addEventListener("click", () => {
    selectedStudentIds.clear();
    render();
  });

  // Add Student Modal controls
  document.getElementById("add-student").addEventListener("click", () => {
    studentModal.classList.remove("hidden");
    studentModal.classList.add("flex");
    studentError.classList.add("hidden");
    studentForm.reset();
  });

  document.querySelectorAll("[data-close]").forEach(btn => {
    btn.addEventListener("click", () => {
      studentModal.classList.add("hidden");
      studentModal.classList.remove("flex");
    });
  });

  studentForm.addEventListener("submit", async e => {
    e.preventDefault();
    studentError.classList.add("hidden");
    const fd = new FormData(studentForm);
    const payload = Object.fromEntries(fd);

    try {
      await api("students-api.php", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      studentModal.classList.add("hidden");
      studentModal.classList.remove("flex");
      await loadData();
    } catch (err) {
      studentError.textContent = err.message;
      studentError.classList.remove("hidden");
    }
  });

  // Bulk Assign Course Modal
  btnBulkAssignCourse.addEventListener("click", () => {
    const selectedList = allStudents.filter(s => selectedStudentIds.has(s.id));
    if (!selectedList.length) return;

    bulkCourseSelectedCount.textContent = selectedList.length;
    bulkCourseSelectedNames.innerHTML = selectedList.map(s => `
      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-pink-100 text-pink-700 font-semibold text-[11px]">
        ${esc(s.first_name)} ${esc(s.last_name)}
      </span>
    `).join("");

    bulkCourseFeedback.classList.add("hidden");
    bulkCourseForm.reset();
    bulkCustomEndDateWrapper.classList.add("hidden");

    // Reset class groups dropdown
    bulkClassGroupSelect.innerHTML = `<option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>`;

    bulkCourseModal.classList.remove("hidden");
    bulkCourseModal.classList.add("flex");
  });

  document.querySelectorAll(".btn-close-bulk-course").forEach(btn => {
    btn.addEventListener("click", () => {
      bulkCourseModal.classList.add("hidden");
      bulkCourseModal.classList.remove("flex");
    });
  });

  // When course changes in Bulk Course Modal, filter class groups
  bulkCourseSelect.addEventListener("change", () => {
    const courseId = parseInt(bulkCourseSelect.value, 10);
    if (!courseId) {
      bulkClassGroupSelect.innerHTML = `<option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>`;
      return;
    }

    const filtered = availableClassGroups.filter(cg => cg.course_id == courseId);
    bulkClassGroupSelect.innerHTML = `
      <option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>
      ${filtered.map(cg => {
        const occupied = Number(cg.enrolled_count || 0);
        const cap = Number(cg.capacity || 0);
        const sched = cg.schedule_day ? `[${cg.schedule_day} ${cg.schedule_time || ""}]` : "";
        return `
          <option value="${cg.id}">${esc(cg.name)} ${sched} (${occupied}/${cap} ที่นั่ง)</option>
        `;
      }).join("")}
    `;
  });

  // Duration preset logic
  bulkDurationPreset.addEventListener("change", () => {
    if (bulkDurationPreset.value === "custom") {
      bulkCustomEndDateWrapper.classList.remove("hidden");
      bulkCustomEndDate.required = true;
    } else {
      bulkCustomEndDateWrapper.classList.add("hidden");
      bulkCustomEndDate.required = false;
    }
  });

  bulkCourseForm.addEventListener("submit", async e => {
    e.preventDefault();
    bulkCourseFeedback.classList.add("hidden");

    const courseId = parseInt(bulkCourseSelect.value, 10);
    const classGroupId = parseInt(bulkClassGroupSelect.value, 10) || null;
    const accessType = document.getElementById("bulk-access-type").value;
    const learningMode = document.getElementById("bulk-learning-mode").value;
    const startDate = document.getElementById("bulk-start-date").value || null;
    const notes = document.getElementById("bulk-notes").value.trim() || null;

    let endDate = null;
    const preset = bulkDurationPreset.value;
    if (preset === "custom") {
      endDate = bulkCustomEndDate.value || null;
    } else if (preset !== "forever") {
      const d = new Date(startDate || Date.now());
      d.setDate(d.getDate() + parseInt(preset, 10));
      endDate = d.toISOString().split("T")[0];
    }

    const submitBtn = document.getElementById("btn-submit-bulk-course");
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span>กำลังบันทึก...</span>`;

    try {
      const res = await api("enrollments-api.php?action=bulk_assign", {
        method: "POST",
        body: JSON.stringify({
          student_ids: Array.from(selectedStudentIds),
          course_id: courseId,
          class_group_id: classGroupId,
          access_type: accessType,
          learning_mode: learningMode,
          start_date: startDate,
          end_date: endDate,
          notes: notes
        })
      });

      bulkCourseFeedback.className = "text-xs p-3 rounded-xl font-medium bg-emerald-50 text-emerald-700 border border-emerald-200";
      let msg = `มอบสิทธิ์สำเร็จเรียบร้อย ${res.assigned_count} คน`;
      if (res.skipped_count > 0) {
        msg += ` (ข้าม ${res.skipped_count} คน เนื่องจากมีสิทธิ์คอร์สนี้อยู่แล้ว)`;
      }
      bulkCourseFeedback.textContent = msg;
      bulkCourseFeedback.classList.remove("hidden");

      setTimeout(async () => {
        bulkCourseModal.classList.add("hidden");
        bulkCourseModal.classList.remove("flex");
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<span>ยืนยันการมอบสิทธิ์</span>`;
        selectedStudentIds.clear();
        await loadData();
      }, 1200);

    } catch (err) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = `<span>ยืนยันการมอบสิทธิ์</span>`;
      bulkCourseFeedback.className = "text-xs p-3 rounded-xl font-medium bg-rose-50 text-rose-700 border border-rose-200";
      bulkCourseFeedback.textContent = err.message || "มอบสิทธิ์ไม่สำเร็จ";
      bulkCourseFeedback.classList.remove("hidden");
    }
  });

  // Bulk Assign Class Group Modal
  btnBulkAssignClass.addEventListener("click", () => {
    const selectedList = allStudents.filter(s => selectedStudentIds.has(s.id));
    if (!selectedList.length) return;

    bulkClassSelectedCount.textContent = selectedList.length;
    bulkClassSelectedNames.innerHTML = selectedList.map(s => `
      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-semibold text-[11px]">
        ${esc(s.first_name)} ${esc(s.last_name)}
      </span>
    `).join("");

    bulkClassFeedback.classList.add("hidden");
    bulkTargetClassInfo.classList.add("hidden");
    bulkClassForm.reset();

    // Populate all class groups
    bulkTargetClassSelect.innerHTML = `
      <option value="">-- เลือกกลุ่มเรียน --</option>
      ${availableClassGroups.map(cg => {
        const occupied = Number(cg.enrolled_count || 0);
        const cap = Number(cg.capacity || 0);
        return `
          <option value="${cg.id}">${esc(cg.name)} (${esc(cg.course_title)}) - ว่าง ${Math.max(0, cap - occupied)} ที่</option>
        `;
      }).join("")}
    `;

    bulkClassModal.classList.remove("hidden");
    bulkClassModal.classList.add("flex");
  });

  document.querySelectorAll(".btn-close-bulk-class").forEach(btn => {
    btn.addEventListener("click", () => {
      bulkClassModal.classList.add("hidden");
      bulkClassModal.classList.remove("flex");
    });
  });

  bulkTargetClassSelect.addEventListener("change", () => {
    const cgId = parseInt(bulkTargetClassSelect.value, 10);
    const cg = availableClassGroups.find(c => c.id == cgId);
    if (!cg) {
      bulkTargetClassInfo.classList.add("hidden");
      return;
    }

    const occupied = Number(cg.enrolled_count || 0);
    const cap = Number(cg.capacity || 0);
    bulkClassInfoTitle.textContent = `${cg.name} (คอร์ส: ${cg.course_title})`;
    bulkClassInfoDetails.textContent = `ครูผู้สอน: ${cg.teacher_name || "—"} | วัน-เวลา: ${cg.schedule_day || "—"} ${cg.schedule_time || ""} | ความจุ: ${occupied}/${cap} ที่นั่ง`;
    bulkTargetClassInfo.classList.remove("hidden");
  });

  bulkClassForm.addEventListener("submit", async e => {
    e.preventDefault();
    bulkClassFeedback.classList.add("hidden");

    const classGroupId = parseInt(bulkTargetClassSelect.value, 10);
    if (!classGroupId) return;

    const submitBtn = document.getElementById("btn-submit-bulk-class");
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span>กำลังบันทึก...</span>`;

    try {
      const res = await api("enrollments-api.php?action=bulk_assign_class", {
        method: "POST",
        body: JSON.stringify({
          student_ids: Array.from(selectedStudentIds),
          class_group_id: classGroupId
        })
      });

      bulkClassFeedback.className = "text-xs p-3 rounded-xl font-medium bg-emerald-50 text-emerald-700 border border-emerald-200";
      let msg = `กำหนดห้องเรียนสำเร็จ ${res.updated_count} คน`;
      if (res.skipped_count > 0) {
        msg += ` (ข้าม ${res.skipped_count} คน เนื่องจากยังไม่มีสิทธิ์ในคอร์สของกลุ่มเรียนนี้)`;
      }
      bulkClassFeedback.textContent = msg;
      bulkClassFeedback.classList.remove("hidden");

      setTimeout(async () => {
        bulkClassModal.classList.add("hidden");
        bulkClassModal.classList.remove("flex");
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<span>บันทึกห้องเรียน</span>`;
        selectedStudentIds.clear();
        await loadData();
      }, 1200);

    } catch (err) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = `<span>บันทึกห้องเรียน</span>`;
      bulkClassFeedback.className = "text-xs p-3 rounded-xl font-medium bg-rose-50 text-rose-700 border border-rose-200";
      bulkClassFeedback.textContent = err.message || "บันทึกห้องเรียนไม่สำเร็จ";
      bulkClassFeedback.classList.remove("hidden");
    }
  });

  // Initial load
  loadData();
})();
