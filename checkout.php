<?php
require_once __DIR__ . '/includes/settings-service.php';
$settings = SettingsService::getAll($pdo);

$pageTitle = "ตะกร้าและชำระเงิน | " . htmlspecialchars($settings['school_name'] ?? 'Nextbeyond Compass');
$pageDesc = "ชำระเงินค่าคอร์สเรียน " . htmlspecialchars($settings['school_name'] ?? 'Nextbeyond Compass');
$currentPage = 'checkout.php';
$extraHead = '<script defer src="assets/js/checkout.js"></script>';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" data-checkout-app>

  <!-- ══════════════════════════════════════════════
       STEP 1 : CART
  ═══════════════════════════════════════════════ -->
  <section data-step="cart" class="bg-[#f6f8fc] py-10 pb-20 min-h-[calc(100vh-78px)]">
    <div class="container max-w-[1140px]">
      
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-[13px] text-[#65738a] font-medium mb-6">
        <a href="index.php" class="hover:text-[#2369dd] transition-colors">หน้าแรก</a>
        <span>/</span>
        <span class="text-navy-950 font-bold">ตะกร้าของฉัน</span>
      </div>

      <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-1"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>SHOPPING CART</p>
      <h2 class="text-[28px] font-bold text-navy-950 tracking-tight mb-8">ตะกร้าของฉัน</h2>

      <div class="grid grid-cols-[1fr_360px] gap-8 items-start max-[1024px]:grid-cols-1">
        
        <!-- Cart Items -->
        <div class="space-y-4">
          <!-- Item 1 -->
          <article class="flex gap-5 p-5 border border-[#dce4ef] rounded-[22px] bg-white shadow-[0_8px_24px_rgba(15,42,83,.06)] max-[640px]:flex-col">
            <div class="w-[180px] h-[120px] shrink-0 rounded-[14px] bg-gradient-to-br from-[#2369dd] to-[#144bb2] overflow-hidden relative max-[640px]:w-full">
              <span class="absolute top-2 left-2 px-2 py-1 rounded-md bg-white/20 backdrop-blur-sm text-white text-[11px] font-bold">ภาษาอังกฤษ</span>
            </div>
            <div class="flex-1 flex flex-col justify-between py-1">
              <div>
                <h3 class="text-[18px] font-bold text-navy-950 mb-1">English Communication Starter</h3>
                <p class="text-[13px] text-[#65738a] mb-0">16 ชั่วโมง · สอนสดผ่าน Zoom · เข้าเรียนได้ 90 วัน</p>
              </div>
              <div class="flex items-center gap-4 mt-4 text-[13px]">
                <button class="text-[#f54696] font-bold hover:underline">ลบออก</button>
                <span class="w-px h-3 bg-[#dce4ef]"></span>
                <button class="text-[#65738a] font-medium hover:text-navy-950">บันทึกไว้ภายหลัง</button>
              </div>
            </div>
            <div class="text-right py-1 max-[640px]:text-left max-[640px]:mt-2">
              <div class="text-[22px] font-black text-navy-950 tracking-tight">1,200 <span class="text-[15px]">บาท</span></div>
            </div>
          </article>

          <!-- Item 2 -->
          <article class="flex gap-5 p-5 border border-[#dce4ef] rounded-[22px] bg-white shadow-[0_8px_24px_rgba(15,42,83,.06)] max-[640px]:flex-col">
            <div class="w-[180px] h-[120px] shrink-0 rounded-[14px] bg-gradient-to-br from-[#f54696] to-[#d41c71] overflow-hidden relative max-[640px]:w-full">
              <span class="absolute top-2 left-2 px-2 py-1 rounded-md bg-white/20 backdrop-blur-sm text-white text-[11px] font-bold">แนวข้อสอบ</span>
            </div>
            <div class="flex-1 flex flex-col justify-between py-1">
              <div>
                <h3 class="text-[18px] font-bold text-navy-950 mb-1">Exam Practice Bundle</h3>
                <p class="text-[13px] text-[#65738a] mb-0">20 ชุดข้อสอบจำลอง · ระบบจับเวลาจริง</p>
              </div>
              <div class="flex items-center gap-4 mt-4 text-[13px]">
                <button class="text-[#f54696] font-bold hover:underline">ลบออก</button>
                <span class="w-px h-3 bg-[#dce4ef]"></span>
                <button class="text-[#65738a] font-medium hover:text-navy-950">บันทึกไว้ภายหลัง</button>
              </div>
            </div>
            <div class="text-right py-1 max-[640px]:text-left max-[640px]:mt-2">
              <div class="text-[22px] font-black text-navy-950 tracking-tight">490 <span class="text-[15px]">บาท</span></div>
            </div>
          </article>
        </div>

        <!-- Order Summary -->
        <aside class="border border-[#dce4ef] rounded-[22px] bg-white p-6 shadow-[0_12px_32px_rgba(15,42,83,.08)] sticky top-[98px]">
          <h3 class="text-[18px] font-bold text-navy-950 mb-5">สรุปคำสั่งซื้อ</h3>
          
          <div class="flex justify-between items-center text-[14px] text-navy-950 mb-3">
            <span>ราคาคอร์ส (2 รายการ)</span>
            <span class="font-bold">1,690 บาท</span>
          </div>
          <div class="flex justify-between items-center text-[14px] text-[#f54696] mb-5">
            <span>ส่วนลด</span>
            <span class="font-bold">– 0 บาท</span>
          </div>

          <div class="relative mb-5">
            <input type="text" placeholder="กรอกรหัสส่วนลด" class="w-full h-11 pl-4 pr-[84px] border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 outline-none focus:border-pink-500">
            <button class="absolute right-1 top-1 bottom-1 px-4 rounded-lg bg-navy-950 text-white text-[12px] font-bold transition-transform hover:-translate-y-0.5">ใช้โค้ด</button>
          </div>

          <hr class="border-[#e8ecf2] mb-5">

          <div class="flex justify-between items-end mb-6">
            <span class="text-[16px] font-bold text-navy-950">ยอดรวม</span>
            <div class="text-[26px] font-black text-navy-950 tracking-tight leading-none">1,690 <span class="text-[16px]">บาท</span></div>
          </div>

          <button class="w-full h-[48px] rounded-xl bg-pink-500 text-white font-bold text-[15px] shadow-[0_8px_20px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5 mb-4 flex items-center justify-center gap-2" data-goto="checkout">ดำเนินการชำระเงิน →</button>
          
          <div class="text-center text-[12px] text-[#65738a]">
            <span>ชำระเงินปลอดภัยด้วยระบบ Stripe</span>
            <div class="mt-1"><a href="#" class="underline hover:text-navy-950">ดูเงื่อนไขการคืนเงิน</a></div>
          </div>
        </aside>

      </div>

      <!-- Cross-sell -->
      <section class="mt-16 pt-12 border-t border-[#dce4ef]">
        <h3 class="text-[20px] font-bold text-navy-950 mb-6">คอร์สที่คุณอาจสนใจ</h3>
        <div class="grid grid-cols-3 gap-5 max-[980px]:grid-cols-2 max-[640px]:grid-cols-1">
          <div class="border border-[#dce4ef] rounded-[18px] bg-white overflow-hidden group cursor-pointer hover:shadow-lg transition-shadow">
            <div class="h-[140px] bg-[#e8ecf2]"></div>
            <div class="p-5">
              <span class="inline-block px-2 py-1 rounded text-[10px] font-bold bg-[#e8f1ff] text-[#2369dd] mb-2">ภาษาอังกฤษ</span>
              <h4 class="text-[16px] font-bold text-navy-950 mb-1 group-hover:text-pink-500 transition-colors">Grammar Foundation</h4>
              <p class="text-[13px] text-[#65738a] mb-3 line-clamp-2">ปูพื้นฐานไวยากรณ์ภาษาอังกฤษตั้งแต่เริ่มต้น เข้าใจง่าย นำไปใช้ได้จริง</p>
              <div class="font-bold text-navy-950 text-[15px]">990 บาท</div>
            </div>
          </div>
          <div class="border border-[#dce4ef] rounded-[18px] bg-white overflow-hidden group cursor-pointer hover:shadow-lg transition-shadow">
            <div class="h-[140px] bg-[#e8ecf2]"></div>
            <div class="p-5">
              <span class="inline-block px-2 py-1 rounded text-[10px] font-bold bg-[#e8f1ff] text-[#2369dd] mb-2">วิทยาศาสตร์</span>
              <h4 class="text-[16px] font-bold text-navy-950 mb-1 group-hover:text-pink-500 transition-colors">Biology Foundation</h4>
              <p class="text-[13px] text-[#65738a] mb-3 line-clamp-2">สรุปเนื้อหาชีววิทยา ม.ต้น ครอบคลุมทุกหัวข้อที่ออกสอบบ่อย</p>
              <div class="font-bold text-navy-950 text-[15px]">1,200 บาท</div>
            </div>
          </div>
          <div class="border border-[#dce4ef] rounded-[18px] bg-white overflow-hidden group cursor-pointer hover:shadow-lg transition-shadow">
            <div class="h-[140px] bg-[#e8ecf2]"></div>
            <div class="p-5">
              <span class="inline-block px-2 py-1 rounded text-[10px] font-bold bg-[#fce4ec] text-[#f54696] mb-2">คณิตศาสตร์ · เรียนฟรี</span>
              <h4 class="text-[16px] font-bold text-navy-950 mb-1 group-hover:text-pink-500 transition-colors">Math Mini Test</h4>
              <p class="text-[13px] text-[#65738a] mb-3 line-clamp-2">ทดสอบความพร้อมวิชาคณิตศาสตร์ พร้อมเฉลยละเอียดแบบวิดีโอ</p>
              <div class="font-bold text-navy-950 text-[15px]">ฟรี</div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════
       STEP 2 : CHECKOUT
  ═══════════════════════════════════════════════ -->
  <section data-step="checkout" class="hidden bg-[#f6f8fc] py-10 pb-20 min-h-[calc(100vh-78px)]">
    <div class="container max-w-[1140px]">
      
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-[13px] text-[#65738a] font-medium mb-6">
        <button class="hover:text-[#2369dd] transition-colors" data-goto="cart">ตะกร้า</button>
        <span>/</span>
        <span class="text-navy-950 font-bold">ชำระเงิน</span>
      </div>

      <div class="flex items-end justify-between mb-8 max-[640px]:flex-col max-[640px]:items-start max-[640px]:gap-2">
        <div>
          <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-1"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>CHECKOUT</p>
          <h2 class="text-[28px] font-bold text-navy-950 tracking-tight">ชำระเงิน</h2>
        </div>
        <div class="text-[13px] font-bold text-[#65738a]">
          <span class="text-pink-500">1 ข้อมูลผู้เรียน</span> <span class="mx-1">→</span> <span class="text-pink-500">2 ชำระเงิน</span> <span class="mx-1">→</span> <span>3 สำเร็จ</span>
        </div>
      </div>

      <div class="grid grid-cols-[1fr_360px] gap-8 items-start max-[1024px]:grid-cols-1">
        
        <!-- Forms -->
        <div class="space-y-6">
          <!-- 1. Student Info -->
          <div class="border border-[#dce4ef] rounded-[22px] bg-white p-7 shadow-[0_8px_24px_rgba(15,42,83,.06)]">
            <h3 class="text-[18px] font-bold text-navy-950 mb-5">1. ข้อมูลผู้เรียน</h3>
            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ชื่อ–นามสกุล *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 outline-none focus:border-pink-500" type="text" value="ด.ช. ตัวอย่าง นักเรียน"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ระดับชั้น</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 outline-none focus:border-pink-500 bg-[#f8fafc]" type="text" value="ม.3" readonly></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">อีเมล *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 outline-none focus:border-pink-500" type="email" value="student@email.com"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">เบอร์โทรศัพท์ *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 outline-none focus:border-pink-500" type="tel" value="08x-xxx-1234"></div>
            </div>
          </div>

          <!-- 2. Billing Info -->
          <div class="border border-[#dce4ef] rounded-[22px] bg-white p-7 shadow-[0_8px_24px_rgba(15,42,83,.06)]">
            <div class="flex items-center justify-between mb-5">
              <h3 class="text-[18px] font-bold text-navy-950">2. ข้อมูลออกใบเสร็จ</h3>
              <label class="flex items-center gap-2 text-[13px] font-medium text-navy-950 cursor-pointer">
                <input type="checkbox" class="w-4 h-4 rounded accent-pink-500" checked> ใช้ข้อมูลเดียวกับผู้เรียน
              </label>
            </div>
            <div>
              <label class="block mb-1.5 text-[13px] font-bold text-navy-900">ที่อยู่สำหรับออกใบเสร็จ</label>
              <textarea class="w-full h-[80px] px-3.5 py-3 border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 outline-none focus:border-pink-500 resize-y" placeholder="ชื่อ / นิติบุคคล, ที่อยู่, เลขประจำตัวผู้เสียภาษี"></textarea>
            </div>
          </div>

          <!-- 3. Payment Method -->
          <div class="border border-[#dce4ef] rounded-[22px] bg-white p-7 shadow-[0_8px_24px_rgba(15,42,83,.06)]">
            <h3 class="text-[18px] font-bold text-navy-950 mb-5">3. ช่องทางชำระเงิน</h3>
            <div class="space-y-3">
              <label class="block p-4 border-2 border-pink-500 rounded-xl bg-pink-50/50 cursor-pointer">
                <div class="flex items-center gap-3">
                  <input type="radio" name="paymentMethod" value="promptpay" class="w-4 h-4 accent-pink-500" checked>
                  <div class="flex-1 text-[15px] font-bold text-navy-950">QR PromptPay</div>
                  <img src="https://upload.wikimedia.org/wikipedia/commons/e/e1/PromptPay_logo.png" alt="PromptPay" class="h-6 object-contain">
                </div>
              </label>

              <!-- PromptPay Details -->
              <div id="promptpay-info" class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-navy-950 space-y-1.5">
                <div class="font-bold flex items-center justify-between">
                  <span>หมายเลข PromptPay:</span>
                  <span class="font-mono text-sm text-pink-600 font-black"><?= htmlspecialchars($settings['promptpay_number'] ?: '0105566123456') ?></span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                  <span>ชื่อบัญชี:</span>
                  <span class="font-semibold text-navy-950"><?= htmlspecialchars($settings['bank_account_name'] ?: 'บจก. เน็กซ์ บียอนด์ เอ็ดดูเคชั่น') ?></span>
                </div>
              </div>

              <label class="block p-4 border border-[#dce4ef] rounded-xl bg-white cursor-pointer hover:border-[#b8c5d8]">
                <div class="flex items-center gap-3">
                  <input type="radio" name="paymentMethod" value="card" class="w-4 h-4 accent-pink-500">
                  <div class="flex-1 text-[15px] font-bold text-navy-950">บัตรเครดิต / เดบิต</div>
                  <div class="flex gap-1">
                    <div class="w-8 h-5 bg-[#e8ecf2] rounded border border-[#dce4ef]"></div>
                    <div class="w-8 h-5 bg-[#e8ecf2] rounded border border-[#dce4ef]"></div>
                  </div>
                </div>
              </label>
              <label class="block p-4 border border-[#dce4ef] rounded-xl bg-white cursor-pointer hover:border-[#b8c5d8]">
                <div class="flex items-center gap-3">
                  <input type="radio" name="paymentMethod" value="transfer" class="w-4 h-4 accent-pink-500">
                  <div class="flex-1 text-[15px] font-bold text-navy-950">โอนเงินผ่านบัญชีธนาคาร</div>
                </div>
              </label>

              <!-- Bank Transfer Details -->
              <div id="transfer-info" class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-navy-950 space-y-1.5">
                <div class="flex items-center justify-between text-slate-600">
                  <span>ธนาคาร:</span>
                  <span class="font-bold text-navy-950"><?= htmlspecialchars($settings['bank_name'] ?: 'ธนาคารกสิกรไทย (KBANK)') ?></span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                  <span>ชื่อบัญชี:</span>
                  <span class="font-semibold text-navy-950"><?= htmlspecialchars($settings['bank_account_name'] ?: 'บจก. เน็กซ์ บียอนด์ เอ็ดดูเคชั่น') ?></span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                  <span>เลขที่บัญชี:</span>
                  <span class="font-mono text-sm text-pink-600 font-black"><?= htmlspecialchars($settings['bank_account_number'] ?: '123-4-56789-0') ?></span>
                </div>
                <?php if (!empty($settings['payment_instructions'])): ?>
                  <div class="pt-2 border-t border-slate-200 text-slate-500 text-[11px] leading-relaxed">
                    <b>คำแนะนำการชำระเงิน:</b> <?= nl2br(htmlspecialchars($settings['payment_instructions'])) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <aside class="border border-[#dce4ef] rounded-[22px] bg-white p-6 shadow-[0_12px_32px_rgba(15,42,83,.08)] sticky top-[98px]">
          <h3 class="text-[18px] font-bold text-navy-950 mb-4">รายการสั่งซื้อ</h3>
          
          <div class="space-y-3 mb-5 border-b border-[#e8ecf2] pb-5">
            <div>
              <p class="text-[14px] font-bold text-navy-950 mb-0">English Communication Starter</p>
              <p class="text-[12px] text-[#65738a] mb-0">1,200 บาท</p>
            </div>
            <div>
              <p class="text-[14px] font-bold text-navy-950 mb-0">Exam Practice Bundle</p>
              <p class="text-[12px] text-[#65738a] mb-0">490 บาท</p>
            </div>
          </div>

          <div class="flex justify-between items-center text-[14px] text-navy-950 mb-3">
            <span>ราคารวม</span>
            <span class="font-bold">1,690 บาท</span>
          </div>
          <div class="flex justify-between items-center text-[14px] text-[#f54696] mb-5">
            <span>ส่วนลด</span>
            <span class="font-bold">0 บาท</span>
          </div>

          <hr class="border-[#e8ecf2] mb-5">

          <div class="flex justify-between items-end mb-6">
            <span class="text-[16px] font-bold text-navy-950">ยอดชำระ</span>
            <div class="text-[26px] font-black text-navy-950 tracking-tight leading-none">1,690 <span class="text-[16px]">บาท</span></div>
          </div>

          <button class="w-full h-[48px] rounded-xl bg-pink-500 text-white font-bold text-[15px] shadow-[0_8px_20px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5 mb-3" data-goto="success">ยืนยันและชำระเงิน</button>
          <div class="text-center"><small class="text-[12px] text-[#65738a]">เมื่อคลิกชำระเงิน ถือว่ายอมรับข้อกำหนดการให้บริการ</small></div>
        </aside>

      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════
       STEP 3 : SUCCESS
  ═══════════════════════════════════════════════ -->
  <section data-step="success" class="hidden bg-[#f6f8fc] py-16 min-h-[calc(100vh-78px)] flex items-center justify-center">
    <div class="container max-w-[540px]">
      <div class="border border-[#dce4ef] rounded-[24px] bg-white p-8 shadow-[0_16px_40px_rgba(15,42,83,.08)] text-center relative overflow-hidden">
        
        <div class="absolute top-0 left-0 right-0 h-[6px] bg-gradient-to-r from-[#2369dd] to-pink-500"></div>

        <div class="w-[84px] h-[84px] mx-auto mt-4 mb-6 rounded-full bg-[#168765] text-white flex items-center justify-center text-[40px] font-black shadow-[0_12px_32px_rgba(22,135,101,.3)]">✓</div>
        
        <p class="text-[12px] font-black tracking-[0.15em] text-[#168765] uppercase mb-2">PAYMENT SUCCESS</p>
        <h2 class="text-[28px] font-bold text-navy-950 tracking-tight mb-2">ชำระเงินเรียบร้อยแล้ว</h2>
        <p class="text-[#65738a] text-[15px] mb-8">คอร์สถูกเพิ่มในบัญชีของคุณ สามารถเริ่มเรียนได้ทันที</p>

        <hr class="border-[#e8ecf2] border-dashed mb-6">

        <div class="text-left space-y-4 mb-8">
          <div class="flex justify-between items-center text-[14px]">
            <span class="text-[#65738a]">เลขที่คำสั่งซื้อ</span>
            <strong class="text-navy-950 font-mono">NB-000001</strong>
          </div>
          <div class="flex justify-between items-center text-[14px]">
            <span class="text-[#65738a]">คอร์สที่สั่งซื้อ</span>
            <strong class="text-navy-950">2 รายการ</strong>
          </div>
          <div class="flex justify-between items-center text-[15px]">
            <span class="text-[#65738a]">ยอดชำระสุทธิ</span>
            <strong class="text-navy-950 text-[18px]">1,690 บาท</strong>
          </div>
        </div>

        <div class="flex flex-col gap-3">
          <a href="courses.php" class="inline-flex h-[48px] justify-center rounded-xl bg-pink-500 text-white font-bold text-[15px] shadow-[0_8px_20px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5 items-center">ไปที่หน้าคอร์สเรียนของฉัน</a>
          <div class="grid grid-cols-2 gap-3">
            <button class="inline-flex h-[48px] justify-center rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] transition-transform hover:-translate-y-0.5 items-center" data-goto="receipt">ดูใบเสร็จรับเงิน</button>
            <a href="index.php" class="inline-flex h-[48px] justify-center rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] transition-transform hover:-translate-y-0.5 items-center">กลับ Dashboard</a>
          </div>
        </div>

        <p class="text-[12px] text-[#94a3b8] mt-6">ระบบได้ส่งใบเสร็จและรายละเอียดไปยังอีเมล student@email.com แล้ว</p>
      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════
       STEP 4 : RECEIPT
  ═══════════════════════════════════════════════ -->
  <section data-step="receipt" class="hidden bg-[#f6f8fc] py-10 pb-20 min-h-[calc(100vh-78px)]">
    <div class="container max-w-[820px]">
      
      <div class="flex items-center justify-between mb-6 max-[640px]:flex-col max-[640px]:items-start max-[640px]:gap-4">
        <div>
          <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-1"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>RECEIPT</p>
          <h2 class="text-[28px] font-bold text-navy-950 tracking-tight">ใบเสร็จรับเงิน</h2>
        </div>
        <div class="flex gap-2">
          <button class="h-9 px-4 rounded-lg border border-[#dce4ef] bg-white text-[13px] font-bold text-navy-950 transition-transform hover:-translate-y-0.5 flex items-center gap-2"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> ส่งซ้ำ</button>
          <button class="h-9 px-4 rounded-lg border border-[#dce4ef] bg-white text-[13px] font-bold text-navy-950 transition-transform hover:-translate-y-0.5 flex items-center gap-2"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> PDF</button>
          <button class="h-9 px-4 rounded-lg bg-navy-950 text-white text-[13px] font-bold transition-transform hover:-translate-y-0.5 flex items-center gap-2" onclick="window.print()"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> พิมพ์</button>
        </div>
      </div>

      <!-- Receipt Paper -->
      <article class="bg-white border border-[#dce4ef] rounded-lg shadow-[0_12px_32px_rgba(15,42,83,.05)] p-10 max-[640px]:p-5">
        
        <!-- Header -->
        <div class="flex justify-between items-start mb-10 pb-10 border-b border-[#e8ecf2] max-[640px]:flex-col max-[640px]:gap-6">
          <div>
            <div class="text-[20px] font-black text-navy-950 tracking-tight mb-2"><?= htmlspecialchars($settings['school_name'] ?: 'NEXTBEYOND COMPASS') ?></div>
            <p class="text-[13px] text-[#65738a] leading-relaxed mb-0">
              <?= nl2br(htmlspecialchars($settings['school_address'] ?: 'อาคาร Next Beyond ชั้น 3 เขตปทุมวัน กรุงเทพมหานคร 10330')) ?><br>
              <?php if (!empty($settings['tax_id'])): ?>
                เลขประจำตัวผู้เสียภาษี: <?= htmlspecialchars($settings['tax_id']) ?><br>
              <?php endif; ?>
              โทร: <?= htmlspecialchars($settings['school_phone'] ?: '02-123-4567') ?> | อีเมล: <?= htmlspecialchars($settings['school_email'] ?: 'contact@nextbeyond.com') ?>
            </p>
          </div>
          <div class="text-right max-[640px]:text-left">
            <h2 class="text-[24px] font-bold text-navy-950 mb-3">ใบเสร็จรับเงิน</h2>
            <div class="text-[13px] text-navy-950 space-y-1">
              <div><span class="text-[#65738a] inline-block w-16">เลขที่:</span> <strong>RC-2026-000001</strong></div>
              <div><span class="text-[#65738a] inline-block w-16">วันที่:</span> <strong>31 สิงหาคม 2026</strong></div>
              <div class="pt-2"><span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold text-[#168765] bg-[#e8f5e9]">ชำระแล้ว</span></div>
            </div>
          </div>
        </div>

        <!-- Info -->
        <div class="grid grid-cols-2 gap-8 mb-10 max-[640px]:grid-cols-1">
          <div>
            <p class="text-[11px] text-[#94a3b8] font-bold tracking-wider uppercase mb-1">ออกให้ (ผู้ชำระเงิน)</p>
            <h3 class="text-[16px] font-bold text-navy-950 mb-1">คุณผู้ปกครอง ตัวอย่าง</h3>
            <p class="text-[13px] text-[#65738a] leading-relaxed">
              123/45 ถนนพัฒนาการ แขวงสวนหลวง<br>
              เขตสวนหลวง กรุงเทพมหานคร 10250
            </p>
          </div>
          <div>
            <p class="text-[11px] text-[#94a3b8] font-bold tracking-wider uppercase mb-1">ข้อมูลนักเรียนอ้างอิง</p>
            <h3 class="text-[16px] font-bold text-navy-950 mb-1">ด.ช. ตัวอย่าง นักเรียน</h3>
            <p class="text-[13px] text-[#65738a] leading-relaxed">
              ระดับชั้น ม.3 · รหัสนักเรียน NB-ST-0001<br>
              student@email.com
            </p>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto mb-6">
          <table class="w-full text-[13px]">
            <thead>
              <tr class="border-y border-[#dce4ef] text-left">
                <th class="py-3 font-bold text-navy-950 w-full">รายการ</th>
                <th class="py-3 font-bold text-navy-950 text-center px-4">จำนวน</th>
                <th class="py-3 font-bold text-navy-950 text-right px-4 whitespace-nowrap">ราคาต่อหน่วย</th>
                <th class="py-3 font-bold text-navy-950 text-right pl-4 whitespace-nowrap">รวม (บาท)</th>
              </tr>
            </thead>
            <tbody class="border-b border-[#e8ecf2]">
              <tr class="border-b border-[#f1f5f9]">
                <td class="py-4">
                  <strong class="text-navy-950 text-[14px]">English Communication Starter</strong>
                  <div class="text-[#65738a] text-[12px] mt-1">16 ชั่วโมง · เข้าเรียนได้ 90 วัน</div>
                </td>
                <td class="py-4 text-center text-navy-950">1</td>
                <td class="py-4 text-right text-navy-950">1,200.00</td>
                <td class="py-4 text-right text-navy-950 font-bold">1,200.00</td>
              </tr>
              <tr>
                <td class="py-4">
                  <strong class="text-navy-950 text-[14px]">Exam Practice Bundle</strong>
                  <div class="text-[#65738a] text-[12px] mt-1">20 ชุดข้อสอบจำลอง</div>
                </td>
                <td class="py-4 text-center text-navy-950">1</td>
                <td class="py-4 text-right text-navy-950">490.00</td>
                <td class="py-4 text-right text-navy-950 font-bold">490.00</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Total -->
        <div class="max-w-[320px] ml-auto mb-10">
          <div class="flex justify-between items-center text-[13px] text-navy-950 py-1.5">
            <span>รวมเป็นเงิน</span>
            <span>1,690.00</span>
          </div>
          <div class="flex justify-between items-center text-[13px] text-navy-950 py-1.5">
            <span>ส่วนลด</span>
            <span>0.00</span>
          </div>
          <div class="flex justify-between items-center text-[13px] text-navy-950 py-1.5 border-b border-[#dce4ef] pb-3 mb-3">
            <span>ราคาสุทธิที่เสียภาษีมูลค่าเพิ่ม</span>
            <span>1,579.44</span>
          </div>
          <div class="flex justify-between items-center text-[13px] text-navy-950 py-1.5">
            <span>จำนวนภาษีมูลค่าเพิ่ม 7%</span>
            <span>110.56</span>
          </div>
          <div class="flex justify-between items-center py-4 border-t-2 border-navy-950 mt-3">
            <span class="text-[15px] font-bold text-navy-950">จำนวนเงินรวมทั้งสิ้น</span>
            <span class="text-[20px] font-black text-navy-950">1,690.00</span>
          </div>
          <div class="text-right text-[12px] text-[#65738a]">(หนึ่งพันหกร้อยเก้าสิบบาทถ้วน)</div>
        </div>

        <!-- Footer -->
        <div class="flex justify-between items-end border-t border-[#e8ecf2] pt-8 max-[640px]:flex-col max-[640px]:items-center max-[640px]:text-center max-[640px]:gap-8">
          <div>
            <p class="text-[11px] text-[#94a3b8] font-bold tracking-wider uppercase mb-1">ช่องทางชำระเงิน</p>
            <p class="text-[13px] font-bold text-navy-950 mb-0">QR PromptPay</p>
            <p class="text-[12px] text-[#65738a]">Ref: TXN-2026-991204</p>
          </div>
          <div class="text-center">
            <div class="w-[140px] h-[60px] mx-auto border-b border-[#dce4ef] mb-2 relative">
              <span class="absolute bottom-2 inset-x-0 text-[16px] font-custom text-blue-600 opacity-60" style="font-family: cursive;">Next Beyond</span>
            </div>
            <p class="text-[12px] text-navy-950 font-bold mb-0">ผู้รับเงิน / ผู้มีอำนาจลงนาม</p>
            <p class="text-[11px] text-[#94a3b8]">เอกสารออกโดยระบบอิเล็กทรอนิกส์</p>
          </div>
        </div>

      </article>

      <div class="text-center mt-6">
        <button class="text-[#2369dd] text-[14px] font-bold hover:underline" data-goto="success">← กลับหน้าระบุผล</button>
      </div>
      
    </div>
  </section>

</main>

<?php include 'includes/footer.php'; ?>
