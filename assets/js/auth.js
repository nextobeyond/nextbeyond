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

  // Password Visibility Toggle
  root.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.previousElementSibling;
      if (input && input.tagName === 'INPUT') {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const svg = btn.querySelector('svg');
        if (svg) {
          if (type === 'text') {
            svg.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />`;
          } else {
            svg.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
          }
        }
      }
    });
  });

  // Initialization Check for Reset Password Flow
  const urlParams = new URLSearchParams(window.location.search);
  const authAction = urlParams.get('action');
  const resetToken = urlParams.get('token');

  if (authAction === 'reset-password' && resetToken) {
    const tokenInput = root.querySelector('#reset-token');
    if (tokenInput) tokenInput.value = resetToken;
    showStep('reset-password');
  }

  // Forgot Password
  const forgotBtn = root.querySelector("[data-action='forgot-password']");
  if (forgotBtn) {
    forgotBtn.addEventListener("click", async () => {
      const emailInput = root.querySelector("#forgot-email");
      const email = emailInput ? emailInput.value.trim() : "";
      if (!email) {
        alert("กรุณากรอกอีเมลบัญชีผู้ใช้");
        return;
      }
      
      try {
        forgotBtn.disabled = true;
        const result = await callAuthApi({ action: 'forgot-password', email });
        const msgEl = root.querySelector("#forgot-password-message");
        if (msgEl) {
          msgEl.className = "p-4 mb-4 rounded-xl text-[14px] bg-[#e8f5e9] text-[#168765] border border-[#168765]/20";
          msgEl.innerHTML = `ส่งลิงก์รีเซ็ตรหัสผ่านแล้ว! (Simulation: <a href="${result.resetLink}" class="underline font-bold">คลิกที่นี่เพื่อรีเซ็ต</a>)`;
          msgEl.classList.remove("hidden");
        }
      } catch (error) {
        const msgEl = root.querySelector("#forgot-password-message");
        if (msgEl) {
          msgEl.className = "p-4 mb-4 rounded-xl text-[14px] bg-red-50 text-red-600 border border-red-200";
          msgEl.textContent = error.message;
          msgEl.classList.remove("hidden");
        } else {
          alert(error.message);
        }
      } finally {
        forgotBtn.disabled = false;
      }
    });
  }

  // Reset Password
  const resetBtn = root.querySelector("[data-action='reset-password']");
  if (resetBtn) {
    resetBtn.addEventListener("click", async () => {
      const tokenInput = root.querySelector("#reset-token");
      const newPasswordInput = root.querySelector("#reset-new-password");
      const confirmPasswordInput = root.querySelector("#reset-confirm-password");
      
      const token = tokenInput ? tokenInput.value : "";
      const password = newPasswordInput ? newPasswordInput.value : "";
      const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : "";
      
      if (!password || !confirmPassword) {
        alert("กรุณากรอกรหัสผ่านใหม่และยืนยันรหัสผ่าน");
        return;
      }
      
      if (password.length < 8 || !/[a-z]/.test(password) || !/[A-Z]/.test(password) || !/\d/.test(password)) {
        alert("รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร และประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข");
        return;
      }
      
      if (password !== confirmPassword) {
        alert("รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน");
        return;
      }
      
      try {
        resetBtn.disabled = true;
        await callAuthApi({ action: 'reset-password', token, password });
        const msgEl = root.querySelector("#reset-password-message");
        if (msgEl) {
          msgEl.className = "p-4 mb-4 rounded-xl text-[14px] bg-[#e8f5e9] text-[#168765] border border-[#168765]/20";
          msgEl.textContent = "เปลี่ยนรหัสผ่านสำเร็จ! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
          msgEl.classList.remove("hidden");
        }
        setTimeout(() => {
          window.location.href = "auth.php";
        }, 2000);
      } catch (error) {
        const msgEl = root.querySelector("#reset-password-message");
        if (msgEl) {
          msgEl.className = "p-4 mb-4 rounded-xl text-[14px] bg-red-50 text-red-600 border border-red-200";
          msgEl.textContent = error.message;
          msgEl.classList.remove("hidden");
        } else {
          alert(error.message);
        }
      } finally {
        resetBtn.disabled = false;
      }
    });
  }

})();
