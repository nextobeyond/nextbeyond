(() => {
  "use strict";
  const tbody=document.getElementById("courses-tbody"), modal=document.getElementById("course-modal"), form=document.getElementById("course-form");
  const search=document.getElementById("courses-search"), subject=document.getElementById("courses-subject"), status=document.getElementById("courses-status"), errorBox=document.getElementById("course-error");
  const esc=v=>String(v??"").replace(/[&<>'"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[c]));
  let courses=[], teachers=[];
  async function api(url="courses-api",options={}){const r=await fetch(url,{...options,headers:{"Content-Type":"application/json",...(options.headers||{})}});const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.error||"ไม่สามารถเชื่อมต่อระบบคอร์สได้");return d}
  const money=v=>new Intl.NumberFormat("th-TH",{minimumFractionDigits:0,maximumFractionDigits:2}).format(v||0);
  function render(){
    const q=search.value.trim().toLocaleLowerCase("th"), sub=subject.value, stat=status.value;
    const rows=courses.filter(c=>(`${c.title} ${c.subject||""} ${c.teacherName||""}`.toLocaleLowerCase("th").includes(q)&&(!sub||c.subject===sub)&&(!stat||c.status===stat)));
    document.getElementById("courses-count").textContent=`แสดง ${rows.length} จาก ${courses.length} รายการ`;
    if(!rows.length){tbody.innerHTML='<tr><td colspan="6" class="p-10 text-center text-[#65738a]">ยังไม่มีข้อมูลคอร์ส</td></tr>';return}
    tbody.innerHTML=rows.map(c=>`<tr class="hover:bg-[#f8fafc]">
      <td class="px-5 py-4"><div class="font-bold text-[14px]">${esc(c.title)}</div><div class="text-[12px] text-[#65738a]">${esc(c.level||"ไม่ระบุระดับ")}</div></td>
      <td class="px-4 py-4"><div class="text-[13px] font-bold">${esc(c.subject||"ไม่ระบุวิชา")}</div><div class="text-[12px] text-[#65738a]">${esc(c.teacherName||"ยังไม่กำหนดครู")}</div></td>
      <td class="px-4 py-4"><div class="font-bold">${c.isFree?"ฟรี":money(c.price)+" บาท"}</div>${c.originalPrice?`<div class="text-[11px] line-through text-[#94a3b8]">${money(c.originalPrice)}</div>`:""}</td>
      <td class="px-4 py-4 text-center font-bold">${c.enrollmentCount}</td>
      <td class="px-4 py-4"><span class="px-2 py-1 rounded text-[11px] font-bold ${c.status==="active"?"bg-green-50 text-green-600":"bg-[#f1f5f9] text-[#65738a]"}">${c.status}</span></td>
      <td class="px-4 py-4 text-right whitespace-nowrap"><button data-edit="${esc(c.id)}" class="text-blue-600 text-[12px] font-bold mr-3">แก้ไข</button><button data-delete="${esc(c.id)}" class="text-red-500 text-[12px] font-bold">ลบ</button></td></tr>`).join("");
  }
  async function load(){try{const d=await api();courses=d.courses||[];teachers=d.teachers||[];document.getElementById("course-teacher").innerHTML='<option value="">ยังไม่กำหนด</option>'+teachers.map(t=>`<option value="${esc(t.id)}">${esc(t.name)}</option>`).join("");const subjects=[...new Set(courses.map(c=>c.subject).filter(Boolean))].sort();subject.innerHTML='<option value="">ทุกวิชา</option>'+subjects.map(s=>`<option value="${esc(s)}">${esc(s)}</option>`).join("");const params=new URLSearchParams(window.location.search);if(params.has("status")&&status)status.value=params.get("status");if(params.has("search")&&search)search.value=params.get("search");if(params.has("subject")&&subject)subject.value=params.get("subject");render()}catch(e){tbody.innerHTML=`<tr><td colspan="6" class="p-10 text-center text-red-500">${esc(e.message)}</td></tr>`}}
  function show(open,item=null){modal.classList.toggle("hidden",!open);modal.classList.toggle("flex",open);if(!open){form.reset();form.elements.id.value="";errorBox.classList.add("hidden");return}document.getElementById("course-modal-title").textContent=item?"แก้ไขคอร์ส":"สร้างคอร์สใหม่";form.reset();for(const [key,value] of Object.entries(item||{})){if(form.elements[key]&&key!=="isFree")form.elements[key].value=value??""}form.elements.isFree.checked=!!item?.isFree;form.elements.title.focus()}
  document.getElementById("add-course").onclick=()=>show(true);document.querySelectorAll("[data-close-modal]").forEach(b=>b.onclick=()=>show(false));modal.onclick=e=>{if(e.target===modal)show(false)};
  [search,subject,status].forEach(el=>el.addEventListener(el===search?"input":"change",render));
  form.onsubmit=async e=>{e.preventDefault();const button=document.getElementById("save-course");button.disabled=true;errorBox.classList.add("hidden");const data=Object.fromEntries(new FormData(form));data.isFree=form.elements.isFree.checked;try{await api("courses-api",{method:data.id?"PATCH":"POST",body:JSON.stringify(data)});show(false);await load()}catch(err){errorBox.textContent=err.message;errorBox.classList.remove("hidden")}finally{button.disabled=false}};
  tbody.onclick=async e=>{const edit=e.target.closest("[data-edit]"),del=e.target.closest("[data-delete]");if(edit){show(true,courses.find(c=>c.id===edit.dataset.edit));return}if(del&&confirm("ยืนยันการลบคอร์สนี้?")){try{await api(`courses-api?id=${encodeURIComponent(del.dataset.delete)}`,{method:"DELETE"});await load()}catch(err){alert(err.message)}}};
  load();
})();
