(() => {
  "use strict";
  const $ = id => document.getElementById(id);
  const esc = v => String(v ?? "").replace(/[&<>'"]/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[c]));
  const search=$("bank-search"), subject=$("bank-subject"), box=$("exam-sets"), tbody=$("questions-tbody"), modal=$("question-modal"), form=$("question-form");
  let exams=[], questions=[], editing=null, examId=new URLSearchParams(location.search).get("exam");

  async function api(url, options={}) {
    const r=await fetch(url,{...options,headers:{"Content-Type":"application/json",...(options.headers||{})}});
    const d=await r.json().catch(()=>({}));
    if(!r.ok) throw new Error(d.error||"โหลดคลังข้อสอบไม่สำเร็จ");
    return d;
  }
  function summary(){
    $("bank-set-count").textContent=exams.length;
    $("bank-question-count").textContent=exams.reduce((n,e)=>n+(e.questionCount||0),0);
    $("bank-ai-count").textContent=exams.filter(e=>e.isAiGenerated).length;
  }
  function renderSets(){
    const q=search.value.trim().toLocaleLowerCase("th"), sub=subject.value;
    const rows=exams.filter(e=>`${e.title} ${e.subject||""} ${e.grade||""}`.toLocaleLowerCase("th").includes(q)&&(!sub||e.subject===sub));
    if(!rows.length){box.innerHTML='<div class="col-span-full bg-white rounded-[20px] border p-12 text-center text-[#65738a]">ยังไม่มีชุดข้อสอบในคลัง</div>';return;}
    box.innerHTML=rows.map(e=>`<article class="bg-white rounded-[20px] border border-[#e8ecf2] p-5 flex flex-col">
      <div class="flex justify-between"><span class="text-[11px] font-bold px-2 py-1 rounded bg-pink-50 text-pink-500">${e.isAiGenerated?"AI":"ADMIN"}</span><span class="text-[11px] text-[#65738a]">#${esc(e.id)}</span></div>
      <h3 class="font-bold text-[16px] mt-4">${esc(e.title)}</h3><p class="text-[12px] text-[#65738a] mt-1">${esc(e.subject||"ทั่วไป")} · ${esc(e.grade||"ทุกระดับ")}</p>
      <div class="grid grid-cols-2 gap-2 mt-5 text-center"><div class="bg-[#f8fafc] rounded-xl p-3"><b>${e.questionCount}</b><div class="text-[10px] text-[#65738a]">คำถาม</div></div><div class="bg-[#f8fafc] rounded-xl p-3"><b class="text-[12px]">${esc(e.status)}</b><div class="text-[10px] text-[#65738a]">สถานะ</div></div></div>
      <button data-open="${esc(e.id)}" class="mt-5 h-10 rounded-xl bg-navy-950 text-white font-bold">เปิดดูคำถาม</button></article>`).join("");
  }
  function renderQuestions(){
    const exam=exams.find(e=>e.id===examId); if(!exam) return;
    $("set-title").textContent=exam.title;
    $("set-meta").textContent=`${exam.subject||"ทั่วไป"} · ${exam.grade||"ทุกระดับ"} · ${questions.length} ข้อ`;
    const q=search.value.trim().toLocaleLowerCase("th");
    const rows=questions.filter(x=>`${x.questionText} ${x.skill||""}`.toLocaleLowerCase("th").includes(q));
    $("question-detail-count").textContent=`แสดง ${rows.length} จาก ${questions.length} ข้อ`;
    tbody.innerHTML=rows.length?rows.map(x=>`<tr class="align-top"><td class="p-4 font-bold text-pink-500">${x.sortOrder}</td>
      <td class="p-4"><div class="font-medium text-[14px] max-w-[520px] whitespace-pre-wrap">${esc(x.questionText)}</div><div class="text-[12px] text-green-600 mt-2">✓ ${esc(x.options[x.correctAnswer]||"—")}</div></td>
      <td class="p-4 text-[12px]">${esc(x.skill||"ไม่ระบุ")}<div class="text-[#65738a]">${esc(x.difficulty||"ไม่ระบุ")}</div></td><td class="p-4 text-[12px]">${x.options.length} ตัวเลือก</td>
      <td class="p-4"><button data-edit="${esc(x.id)}" class="h-9 px-4 rounded-lg bg-blue-50 text-blue-600 font-bold text-[12px]">แก้ไข</button></td></tr>`).join(""):'<tr><td colspan="5" class="p-10 text-center text-[#65738a]">ไม่พบคำถาม</td></tr>';
  }
  async function openExam(id,push=true){
    examId=String(id); questions=(await api(`exams-api?view=questions&exam=${encodeURIComponent(id)}`)).questions||[];
    questions.sort((a,b)=>Number(a.sortOrder)-Number(b.sortOrder)||Number(a.id)-Number(b.id));
    $("sets-view").classList.add("hidden"); $("questions-view").classList.remove("hidden");
    $("bank-back").classList.remove("hidden"); $("bank-back").classList.add("flex"); subject.classList.add("hidden");
    search.placeholder="ค้นหาคำถามในชุดนี้..."; search.value="";
    if(push) history.pushState({},"",`question-bank.php?exam=${encodeURIComponent(id)}`);
    renderQuestions();
  }
  function optionRow(value="",checked=false){
    return `<div data-option-row class="flex items-center gap-3 p-3 border rounded-xl has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
      <input type="radio" name="correctAnswer" ${checked?"checked":""} required aria-label="กำหนดเป็นคำตอบที่ถูกต้อง">
      <span data-option-letter class="w-7 h-7 shrink-0 rounded-full bg-[#f1f5f9] flex items-center justify-center font-bold text-[12px]"></span>
      <input name="option" value="${esc(value)}" required class="flex-1 min-w-0 bg-transparent outline-none text-[14px]" aria-label="ข้อความตัวเลือก">
      <button type="button" data-remove-option class="w-8 h-8 shrink-0 rounded-lg text-red-500 hover:bg-red-50 font-bold" title="ลบตัวเลือก" aria-label="ลบตัวเลือก">×</button>
    </div>`;
  }
  function refreshOptionRows(){
    [...form.querySelectorAll("[data-option-row]")].forEach((row,index)=>{
      row.querySelector("[data-option-letter]").textContent=String.fromCharCode(65+index);
      row.querySelector('[name="correctAnswer"]').value=String(index);
    });
  }
  function openEditor(item){
    editing=item; $("edit-question-number").textContent=`ข้อ ${item.sortOrder} · ${item.options.length} ตัวเลือก`; form.innerHTML=`
      <div><label class="block text-[13px] font-bold mb-2">คำถาม *</label><textarea name="questionText" required rows="7" class="w-full p-4 border rounded-xl focus:border-pink-500 outline-none resize-y">${esc(item.questionText)}</textarea></div>
      <div class="mt-5"><div class="flex items-center justify-between gap-3 mb-2"><div><label class="text-[13px] font-bold">ตัวเลือกและคำตอบที่ถูกต้อง</label><div class="text-[11px] text-[#65738a] mt-0.5">เลือกวงกลมหน้าคำตอบที่ถูก</div></div><button type="button" data-add-option class="h-9 px-3 rounded-lg bg-blue-50 text-blue-600 font-bold text-[12px]">＋ เพิ่มตัวเลือก</button></div><div id="option-editor" class="space-y-2">${item.options.map((o,i)=>optionRow(o,i===item.correctAnswer)).join("")}</div></div>
      <div class="grid grid-cols-2 gap-4 mt-5 max-[640px]:grid-cols-1"><div><label class="block text-[13px] font-bold mb-2">ทักษะ</label><input name="skill" value="${esc(item.skill||"")}" class="field"></div><div><label class="block text-[13px] font-bold mb-2">ระดับความยาก</label><select name="difficulty" class="field"><option value="">ไม่ระบุ</option>${["ง่าย","ปานกลาง","ยาก","ยากมาก"].map(v=>`<option ${item.difficulty===v?"selected":""}>${v}</option>`).join("")}</select></div></div>
      <div class="mt-5"><label class="block text-[13px] font-bold mb-2">คำอธิบายเฉลย</label><textarea name="explanation" rows="4" class="w-full p-4 border rounded-xl outline-none focus:border-pink-500">${esc(item.explanation||"")}</textarea></div>
      <div id="question-error" class="hidden mt-4 p-3 rounded-xl bg-red-50 text-red-600 text-[13px] font-bold"></div>
      <div class="flex justify-between mt-6 pt-5 border-t"><button type="button" id="modal-delete" class="h-10 px-4 text-red-500 font-bold">ลบคำถาม</button><div class="flex gap-3"><button type="button" data-close-question class="h-10 px-5 rounded-xl border font-bold">ยกเลิก</button><button id="save-question" class="h-10 px-6 rounded-xl bg-pink-500 text-white font-bold">บันทึกการแก้ไข</button></div></div>`;
    refreshOptionRows();
    modal.classList.remove("hidden"); modal.classList.add("flex"); form.elements.questionText.focus();
  }
  function closeModal(){modal.classList.add("hidden");modal.classList.remove("flex");editing=null}
  async function removeQuestion(){if(!editing||!confirm("ยืนยันการลบคำถามข้อนี้?"))return;await api(`exams-api?entity=question&id=${encodeURIComponent(editing.id)}`,{method:"DELETE"});const exam=exams.find(e=>e.id===examId);if(exam)exam.questionCount=Math.max(0,exam.questionCount-1);closeModal();summary();await openExam(examId,false)}
  form.onclick=event=>{
    if(event.target.closest("[data-add-option]")){
      $("option-editor").insertAdjacentHTML("beforeend",optionRow());refreshOptionRows();
      $("option-editor").lastElementChild.querySelector('[name="option"]').focus();return;
    }
    const remove=event.target.closest("[data-remove-option]");
    if(remove){
      const rows=form.querySelectorAll("[data-option-row]");
      if(rows.length<=2){const error=$("question-error");error.textContent="ต้องมีตัวเลือกอย่างน้อย 2 ตัวเลือก";error.classList.remove("hidden");return;}
      const wasCorrect=remove.closest("[data-option-row]").querySelector('[name="correctAnswer"]').checked;
      remove.closest("[data-option-row]").remove();refreshOptionRows();
      if(wasCorrect)form.querySelector('[name="correctAnswer"]').checked=true;
    }
  };
  form.onsubmit=async event=>{event.preventDefault();const data=new FormData(form),options=data.getAll("option").map(value=>String(value).trim()),error=$("question-error"),save=$("save-question");if(options.length<2||options.some(v=>!v)){error.textContent="กรุณากรอกตัวเลือกให้ครบอย่างน้อย 2 ตัวเลือก";error.classList.remove("hidden");return}save.disabled=true;try{await api("exams-api",{method:"PATCH",body:JSON.stringify({entity:"question",id:editing.id,questionText:data.get("questionText"),options,correctAnswer:Number(data.get("correctAnswer")),skill:data.get("skill"),difficulty:data.get("difficulty"),explanation:data.get("explanation")})});closeModal();await openExam(examId,false)}catch(err){error.textContent=err.message;error.classList.remove("hidden")}finally{save.disabled=false}};
  modal.onclick=event=>{if(event.target===modal||event.target.closest("[data-close-question]"))closeModal();if(event.target.closest("#modal-delete"))removeQuestion()};
  box.onclick=event=>{const button=event.target.closest("[data-open]");if(button)openExam(button.dataset.open)};
  tbody.onclick=event=>{const button=event.target.closest("[data-edit]");if(button)openEditor(questions.find(q=>q.id===button.dataset.edit))};
  search.oninput=()=>examId?renderQuestions():renderSets(); subject.onchange=renderSets;

  // Export Modal in Question Bank
  const exportModal = $("export-docx-modal"),
        exportForm = $("export-docx-form"),
        exportExamIdInput = $("export-exam-id"),
        exportExamTitleEl = $("export-exam-title"),
        closeExportModalBtn = $("close-export-modal"),
        cancelExportBtn = $("btn-cancel-export"),
        submitExportBtn = $("btn-submit-export"),
        exportBtnText = $("export-btn-text"),
        exportStatusBox = $("export-status-box"),
        optShowTitle = $("opt-show-title"),
        optShowDiagrams = $("opt-show-diagrams"),
        optShowAnswers = $("opt-show-answers"),
        optShowExplanations = $("opt-show-explanations");

  function openBankExportModal() {
    if (!examId) return;
    const exam = exams.find(e => e.id === examId);
    exportExamIdInput.value = examId;
    exportExamTitleEl.textContent = exam ? exam.title : "ชุดข้อสอบ";

    exportForm.reset();
    const studentRadio = exportForm.querySelector('input[name="docType"][value="student"]');
    if (studentRadio) studentRadio.checked = true;
    optShowTitle.checked = true;
    optShowDiagrams.checked = true;
    optShowAnswers.disabled = true;
    optShowAnswers.checked = false;
    optShowExplanations.disabled = true;
    optShowExplanations.checked = false;

    exportStatusBox.className = "hidden mb-4 p-3 rounded-xl text-[13px] font-bold";
    exportStatusBox.textContent = "";
    submitExportBtn.disabled = false;
    exportBtnText.textContent = "สร้างไฟล์ Word";

    exportModal.classList.remove("hidden");
    exportModal.classList.add("flex");
  }

  function closeBankExportModal() {
    exportModal.classList.add("hidden");
    exportModal.classList.remove("flex");
  }

  const bankExportBtn = $("bank-export-docx");
  if (bankExportBtn) bankExportBtn.onclick = openBankExportModal;
  if (closeExportModalBtn) closeExportModalBtn.onclick = closeBankExportModal;
  if (cancelExportBtn) cancelExportBtn.onclick = closeBankExportModal;
  if (exportModal) {
    exportModal.onclick = event => {
      if (event.target === exportModal) closeBankExportModal();
    };
  }

  if (exportForm) {
    exportForm.addEventListener("change", event => {
      if (event.target.name === "docType") {
        const isTeacher = event.target.value === "teacher";
        if (isTeacher) {
          optShowAnswers.disabled = false;
          optShowAnswers.checked = true;
          optShowExplanations.disabled = false;
          optShowExplanations.checked = true;
        } else {
          optShowAnswers.disabled = true;
          optShowAnswers.checked = false;
          optShowExplanations.disabled = true;
          optShowExplanations.checked = false;
        }
      }
    });

    exportForm.onsubmit = async event => {
      event.preventDefault();
      const currentId = exportExamIdInput.value;
      if (!currentId) return;

      const docType = exportForm.querySelector('input[name="docType"]:checked')?.value || "student";
      const payload = {
        examId: Number(currentId),
        docType: docType,
        showTitle: optShowTitle.checked,
        showDiagrams: optShowDiagrams.checked,
        showAnswers: optShowAnswers.checked,
        showExplanations: optShowExplanations.checked,
      };

      submitExportBtn.disabled = true;
      exportBtnText.textContent = "กำลังสร้างไฟล์ Word...";
      exportStatusBox.className = "mb-4 p-3 rounded-xl text-[13px] font-bold bg-blue-50 text-blue-700 border border-blue-200 block";
      exportStatusBox.innerHTML = '<span class="inline-block animate-spin mr-2">⟳</span> กำลังสร้างไฟล์ Word และประมวลผลรูปประกอบ...';

      try {
        const resp = await fetch("export-exam-docx.php", {
          method: "POST",
          headers: {"Content-Type": "application/json"},
          body: JSON.stringify(payload)
        });

        if (!resp.ok) {
          const errData = await resp.json().catch(() => ({}));
          throw new Error(errData.error || `เซิร์ฟเวอร์ตอบกลับรหัสข้อผิดพลาด (${resp.status})`);
        }

        let filename = `exam-${currentId}.docx`;
        const disposition = resp.headers.get("Content-Disposition");
        if (disposition) {
          const matchUtf8 = disposition.match(/filename\*=UTF-8''([^;]+)/i);
          if (matchUtf8) {
            filename = decodeURIComponent(matchUtf8[1]);
          } else {
            const matchAscii = disposition.match(/filename="?([^";]+)"?/i);
            if (matchAscii) filename = matchAscii[1];
          }
        }

        const blob = await resp.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.style.display = "none";
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(downloadUrl);
        a.remove();

        exportStatusBox.className = "mb-4 p-3 rounded-xl text-[13px] font-bold bg-green-50 text-green-700 border border-green-200 block";
        exportStatusBox.innerHTML = '✓ สร้างไฟล์สำเร็จ กำลังดาวน์โหลดเอกสาร...';
        exportBtnText.textContent = "สร้างไฟล์สำเร็จ";

        setTimeout(() => {
          closeBankExportModal();
        }, 1500);

      } catch (err) {
        exportStatusBox.className = "mb-4 p-3 rounded-xl text-[13px] font-bold bg-red-50 text-red-700 border border-red-200 block";
        exportStatusBox.textContent = `เกิดข้อผิดพลาด: ${err.message}`;
        submitExportBtn.disabled = false;
        exportBtnText.textContent = "สร้างไฟล์ Word";
      }
    };
  }

  (async()=>{try{exams=(await api("exams-api")).exams||[];summary();const subjects=[...new Set(exams.map(e=>e.subject).filter(Boolean))].sort();subject.innerHTML='<option value="">ทุกวิชา</option>'+subjects.map(s=>`<option value="${esc(s)}">${esc(s)}</option>`).join("");renderSets();if(examId&&exams.some(e=>e.id===examId))await openExam(examId,false)}catch(err){box.innerHTML=`<div class="col-span-full p-10 text-center text-red-500">${esc(err.message)}</div>`}})();
})();
