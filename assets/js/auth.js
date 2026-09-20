(() => {
  "use strict";

  const root = document.querySelector("[data-auth-app]");
  if (!root) return;

  // State
  const state = {
    firstName: "",
    lastName: "",
    grade: "",
    parentFirstName: "",
    parentLastName: "",
    parentPhone: "",
    accountEmail: ""
  };

  // Tabs
  const tabs = root.querySelectorAll("[data-tab]");
  const tabContents = root.querySelectorAll("[data-tab-content]");

  tabs.forEach(tab => {
    tab.addEventListener("click", () => {
      const target = tab.dataset.tab;
      
      // Update tabs style
      tabs.forEach(t => {
        if (t === tab) {
          t.classList.remove("border-transparent", "text-[#94a3b8]");
          t.classList.add("border-pink-500", "text-navy-950");
          t.setAttribute("data-active", "");
        } else {
          t.classList.remove("border-pink-500", "text-navy-950");
          t.classList.add("border-transparent", "text-[#94a3b8]");
          t.removeAttribute("data-active");
        }
      });

      // Update contents
      tabContents.forEach(content => {
        if (content.dataset.tabContent === target) {
          content.classList.remove("hidden");
        } else {
          content.classList.add("hidden");
        }
      });
    });
  });

  // Navigation (Steps)
  const steps = root.querySelectorAll("[data-step]");
  const gotoButtons = root.querySelectorAll("[data-goto]");

  function showStep(stepId) {
    steps.forEach(step => {
      if (step.dataset.step === stepId) {
        step.classList.remove("hidden");
      } else {
        step.classList.add("hidden");
      }
    });

    if (stepId === "account") {
      updateReviewSummary();
    } else if (stepId === "success") {
      updateSuccessView();
    }
    
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  gotoButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const target = btn.dataset.goto;
      if (target === "success") return;
      
      // Save input values before navigating
      root.querySelectorAll("input[data-field], select[data-field]").forEach(input => {
        state[input.dataset.field] = input.value;
      });

      showStep(target);
    });
  });

  async function callAuthApi(payload) {
    const response = await fetch('auth-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || 'ระบบบัญชีขัดข้อง กรุณาลองใหม่');
    return data;
  }

  const registerBtn = root.querySelector('[data-goto="success"]');
  registerBtn?.addEventListener("click", async () => {
    root.querySelectorAll("input[data-field], select[data-field], textarea[data-field]").forEach(input => {
      state[input.dataset.field] = input.value;
    });
    const email = String(state.accountEmail || "").trim().toLowerCase();
    if (!state.firstName || !state.lastName || !email || !state.password) {
      alert("กรุณากรอกชื่อ นามสกุล บัญชีเข้าสู่ระบบ และรหัสผ่านให้ครบ");
      return;
    }
    if (state.password.length < 8 || !/[a-z]/.test(state.password) || !/[A-Z]/.test(state.password) || !/\d/.test(state.password)) {
      alert("รหัสผ่านต้องมีอย่างน้อย 8 ตัว และมีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข");
      return;
    }
    if (state.password !== state.confirmPassword) {
      alert("รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน");
      return;
    }
    const requiredConsents = [...root.querySelectorAll('[data-step="account"] input[type="checkbox"][required]')];
    if (requiredConsents.some(input => !input.checked)) {
      alert("กรุณายอมรับข้อกำหนดและความยินยอมที่จำเป็น");
      return;
    }
    try {
      registerBtn.disabled = true;
      const result = await callAuthApi({
        action: 'register',
        firstName: state.firstName,
        lastName: state.lastName,
        email,
        phone: state.phone || state.parentPhone || '',
        password: state.password,
        parentName: `${state.parentFirstName || ''} ${state.parentLastName || ''}`.trim(),
        parentPhone: state.parentPhone || '',
        parentEmail: state.parentEmail || '',
        relationship: state.relationship || ''
      });
      state.studentId = result.user.id;
      localStorage.setItem("nb_user_role", "student");
      localStorage.setItem("nb_user", JSON.stringify(result.user));
      showStep("success");
    } catch (error) {
      alert(error.message);
    } finally {
      registerBtn.disabled = false;
    }
  });

  // Chips
  const chipsContainers = root.querySelectorAll("[data-chips]");
  chipsContainers.forEach(container => {
    const isMulti = container.dataset.chips === "subjects" || container.dataset.chips === "contact";
    const chips = container.querySelectorAll("[data-chip]");
    
    chips.forEach(chip => {
      chip.addEventListener("click", () => {
        if (isMulti) {
          chip.classList.toggle("active");
        } else {
          chips.forEach(c => c.classList.remove("active"));
          chip.classList.add("active");
        }
      });
    });
  });

  // Update Review Summary
  function updateReviewSummary() {
    const studentNameEl = root.querySelector("[data-review='studentName']");
    const gradeEl = root.querySelector("[data-review='grade']");
    const parentNameEl = root.querySelector("[data-review='parentName']");
    const parentPhoneEl = root.querySelector("[data-review='parentPhone']");

    if (studentNameEl) studentNameEl.textContent = state.firstName ? `${state.firstName} ${state.lastName}` : "—";
    if (gradeEl) gradeEl.textContent = state.grade || "—";
    if (parentNameEl) parentNameEl.textContent = state.parentFirstName ? `${state.parentFirstName} ${state.parentLastName}` : "—";
    if (parentPhoneEl) parentPhoneEl.textContent = state.parentPhone || "—";
  }



  // Update Success View
  function updateSuccessView() {
    const nameEl = root.querySelector("[data-success='name']");
    const accountEl = root.querySelector("[data-success='account']");
    const parentEl = root.querySelector("[data-success='parent']");

    if (nameEl) nameEl.textContent = state.firstName ? `${state.firstName} ${state.lastName}` : "—";
    if (accountEl) accountEl.textContent = state.accountEmail || state.parentPhone || "—";
    if (parentEl) parentEl.textContent = state.parentFirstName ? `${state.parentFirstName} ${state.parentLastName}` : "—";
    const idEl = root.querySelector("[data-success='id']");
    if (idEl) idEl.textContent = state.studentId || "—";
  }

  // Login state used by the current browser-based account flow.
  const loginBtn = root.querySelector("[data-action='login']");
  if (loginBtn) {
    loginBtn.addEventListener("click", async () => {
      const emailInput = root.querySelector("#login-email");
      const passwordInput = root.querySelector("#login-password");
      
      const email = emailInput ? emailInput.value.trim() : "";
      const password = passwordInput ? passwordInput.value : "";
      if (!email || !password) {
        alert("กรุณากรอกอีเมลและรหัสผ่าน");
        return;
      }
      
      try {
        loginBtn.disabled = true;
        const result = await callAuthApi({ action: 'login', identity: email, password });
        localStorage.setItem('nb_user_role', result.user.role);
        localStorage.setItem('nb_user', JSON.stringify(result.user));
        const params = new URLSearchParams(window.location.search);
        const returnTo = params.get('redirect') || params.get('returnTo');
        if (returnTo && !returnTo.includes(':') && !returnTo.startsWith('//')) {
          window.location.href = returnTo;
        } else {
          window.location.href = ['admin', 'teacher'].includes(result.user.role) ? 'admin/' : 'student/';
        }
      } catch (error) {
        alert(error.message);
      } finally {
        loginBtn.disabled = false;
      }
    });
  }

})();
