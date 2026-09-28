# SPEC: Live Session — Teacher Command Center

> **วิธีใช้ไฟล์นี้:** Load ไฟล์นี้เข้า Antigravity IDE แล้วสั่ง "Build this feature" ได้เลย  
> ระบบจะอ่าน spec ด้านล่างและสร้าง Live Session ครบทุกส่วน

---

## OVERVIEW

สร้างระบบ **Live Session Teacher Command Center** สำหรับครู ควบคุมห้องเรียนสดแบบ Real-time

**Tech Stack:** Next.js 14 (App Router), TypeScript, Tailwind CSS, Drizzle ORM (SQLite)  
**Data Fetching:** Polling ทุก 3 วินาที (ไม่ใช้ WebSocket)  
**Auth:** Cookie-based (`nb_session_user`) → role: `teacher | admin`

---

## FILE STRUCTURE

```
src/
├── app/
│   ├── teacher/
│   │   └── sessions/
│   │       └── [sessionId]/
│   │           └── page.tsx              ← หน้าหลัก Command Center
│   └── api/
│       ├── sessions/
│       │   └── join/
│       │       └── route.ts              ← Student join via PIN
│       └── teacher/
│           └── sessions/
│               ├── route.ts              ← GET list / POST create / PATCH / DELETE
│               └── [sessionId]/
│                   └── route.ts          ← GET detail / POST actions / DELETE
├── components/
│   └── BossBattleArenaModal.tsx          ← Modal arena แยก component
└── lib/
    ├── session.ts                        ← getSession / setSession / clearSession
    ├── boss-archetypes.ts                ← getBossArchetype(theme)
    └── battle-audio.ts                   ← playBattleSound / setBattleSoundEnabled
```

---

## DATABASE SCHEMA

### Table: `classroomSessions`

```typescript
{
  id: string;                    // 'ses-{timestamp}'
  teacherId: string;
  classroomId: string | null;
  title: string;
  sessionPin: string;            // 6-digit unique numeric PIN
  assignmentId: string | null;
  questionSetVersionId: string | null;
  status: 'active' | 'closed';
  allowLateJoin: boolean;        // default: true
  hasTimeLimit: boolean;         // default: true
  timeLimitMinutes: number | null;
  educationStage: string;        // 'm1'|'m4'|'university'
  startedAt: Date;
  endedAt: Date | null;
  updatedAt: Date;
  joinedStudentsCount: number;   // default: 0

  // Eyes On Me
  eyesOnMeEnabled: boolean;      // default: false
  lockedStudentIds: string[];    // JSON array of studentIds

  // Announcement
  announcementMessage: string | null;

  // Boss Fight
  bossFightActive: boolean;      // default: false
  bossName: string | null;       // 'มังกรเพลิงแห่งความรู้ ไครอส'
  bossTheme: string | null;      // 'dragon'|'golem'|'phoenix'|'shadow'|...
  bossCurrentHp: number;         // default: 100
  bossMaxHp: number;             // default: 100
  bossRewardPoints: number;      // default: 50
  bossDefeated: boolean;         // default: false
  bossCombatLog: object[];        // JSON array ของ hit logs (max 50)
}
```

### Table: `sessionParticipants`

```typescript
{
  id: string;                    // 'sp-{timestamp}-{random}'
  sessionId: string;
  studentId: string;
  attemptId: string | null;      // link to examAttempts
  status: 'joined' | 'in_progress' | 'submitted';
  joinedAt: Date;
}
```

---

## TYPESCRIPT TYPES

```typescript
// Student live status (computed from participants + attempts + answers)
interface StudentLiveStatus {
  participantId: string;
  studentId: string;
  name: string;
  email?: string;
  avatarUrl: string | null;
  joinedAt: string;
  sessionStatus: 'joined' | 'in_progress' | 'submitted';
  attemptId: string | null;
  attemptStatus: 'not_started' | 'in_progress' | 'submitted';
  scorePercentage: number | null;
  rawScore: number | null;
  answeredCount: number;
  totalQuestions: number;
  progressPct: number;           // 0-100
  currentQuestionNumber: number;
  isEyesOnMeLocked: boolean;
  lastActiveAt: string;
}

// Per-question analytics
interface QuestionStat {
  questionId: string;
  questionNumber: number;
  topic: string;
  subject: string;
  difficulty: string;
  totalAttempts: number;
  incorrectCount: number;
  correctPct: number;            // 0-100
}

// Boss combat hit log entry
interface CombatHit {
  id: string;
  studentId: string;
  studentName: string;
  damage: number;
  timestamp: string;
}

// Full session data returned from GET /api/teacher/sessions/[sessionId]
interface SessionDetailResponse {
  session: {
    /* all classroomSessions fields */ &
    classroomName: string;
    classroomSection: string | null;
    assignmentTitle: string | null;
    assignmentMode: string | null;
    postTestOpened: boolean;
    totalQuestions: number;
    topDamageDealers: Array<{
      studentId: string; name: string; totalDamage: number; hitCount: number;
    }>;
  };
  assignment: object | null;
  questionSet: object | null;
  questions: Array<{
    id: string; questionNumber: number; subject: string; topic: string;
    difficulty: string; questionText: string; explanation: string;
    options: Array<{ id: string; optionKey: string; optionText: string; isCorrect: boolean }>;
  }>;
  students: StudentLiveStatus[];
  questionStats: QuestionStat[];
}
```

---

## PAGE COMPONENT: `TeacherLiveSessionPage`

### State

```typescript
const [data, setData] = useState<SessionDetailResponse | null>(null);
const [loading, setLoading] = useState(true);
const [refreshing, setRefreshing] = useState(false);
const [error, setError] = useState<string | null>(null);
const [copiedPin, setCopiedPin] = useState(false);

// Active modal (null = ปิดทุก modal)
const [activeModal, setActiveModal] = useState<
  null | 'exam_overview' | 'student_details' | 'attendance' |
  'announcement' | 'remediation' | 'exam_stats' | 'skill_map' |
  'missed_questions' | 'reset_attempt' | 'boss_fight'
>(null);

// Announcement
const [announcementText, setAnnouncementText] = useState('');
const [sendingAnnouncement, setSendingAnnouncement] = useState(false);

// Eyes On Me per-student accordion
const [isLockPerStudentOpen, setIsLockPerStudentOpen] = useState(false);
const [savingEyesOnMe, setSavingEyesOnMe] = useState(false);
```

### Realtime Polling

```typescript
// Poll ทุก 3 วินาที ตลอดเวลาที่อยู่หน้านี้
useEffect(() => {
  loadSessionData();
  const interval = setInterval(loadSessionData, 3000);
  return () => clearInterval(interval);
}, [params.sessionId]);
```

### ฟังก์ชันทั้งหมด

#### `loadSessionData(isManual = false)`
```typescript
// ถ้า isManual = true → setRefreshing(true) → แสดง animate-spin
const res = await fetch(`/api/teacher/sessions/${params.sessionId}`);
const json = await res.json();
setData(json);
```

#### `handleCopyPin(pin: string)`
```typescript
navigator.clipboard.writeText(pin);
setCopiedPin(true);
setTimeout(() => setCopiedPin(false), 2000);
// UI: Copy icon → Check (emerald) 2 วิ แล้วกลับ
```

#### `handleToggleEyesOnMe()`
```typescript
// Optimistic update → PATCH → rollback ถ้า error
const nextState = !data.session.eyesOnMeEnabled;
await fetch('/api/teacher/sessions', {
  method: 'PATCH',
  body: JSON.stringify({ sessionId, eyesOnMeEnabled: nextState }),
});
setData(prev => ({ ...prev, session: { ...prev.session, eyesOnMeEnabled: nextState } }));
```

#### `handleToggleStudentLock(studentId: string)`
```typescript
// toggle studentId ใน lockedStudentIds array
const currentLocked = data.session.lockedStudentIds || [];
const isLocked = currentLocked.includes(studentId);
const updatedLocked = isLocked
  ? currentLocked.filter(id => id !== studentId)
  : [...currentLocked, studentId];
await fetch('/api/teacher/sessions', {
  method: 'PATCH',
  body: JSON.stringify({ sessionId, lockedStudentIds: updatedLocked }),
});
```

#### `handleSendAnnouncement()`
```typescript
if (!announcementText.trim()) return;
setSendingAnnouncement(true);
await fetch('/api/teacher/sessions', {
  method: 'PATCH',
  body: JSON.stringify({ sessionId, announcementMessage: announcementText.trim() }),
});
setActiveModal(null);
// ปิด modal → Badge "📢 มีประกาศสด" ปรากฏใน Section 2
```

#### `handleClearAnnouncement()`
```typescript
await fetch('/api/teacher/sessions', {
  method: 'PATCH',
  body: JSON.stringify({ sessionId, announcementMessage: null }),
});
setAnnouncementText('');
```

#### `handleResetStudentAttempt(studentId: string, studentName: string)`
```typescript
if (!confirm(`แน่ใจหรือไม่ว่าต้องการรีเซ็ตข้อสอบของ "${studentName}"?`)) return;
const res = await fetch(`/api/teacher/sessions/${sessionId}`, {
  method: 'POST',
  body: JSON.stringify({ action: 'reset_student_attempt', studentId }),
});
const json = await res.json();
if (json.success) { alert(`รีเซ็ต ${studentName} เรียบร้อยแล้ว`); loadSessionData(true); }
```

#### `handleToggleBossFight()`
```typescript
const nextState = !data.session.bossFightActive;
// คำนวณ HP: totalQuestions × max(1, students.length) × 10
const totalQ = (data.session.totalQuestions || 10) * Math.max(1, students.length);
const maxHp = totalQ * 10;
await fetch('/api/teacher/sessions', {
  method: 'PATCH',
  body: JSON.stringify({ sessionId, bossFightActive: nextState, bossMaxHp: maxHp, bossCurrentHp: maxHp }),
});
```

---

## UI LAYOUT

### Page Container
```tsx
<div className="max-w-6xl mx-auto space-y-5 pb-16">
```

### Section 1: Top Navigation Bar
```tsx
<div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
  {/* Left: Back button + PIN + Title */}
  <div className="flex items-center gap-3">
    <Link href="/teacher" className="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
      <ArrowLeft className="w-4 h-4" />
    </Link>
    <div>
      {/* PIN */}
      <span className="text-2xl font-black font-mono tracking-widest text-slate-900 select-all">
        {session.sessionPin}
      </span>
      <button onClick={() => handleCopyPin(session.sessionPin)}
        className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200">
        {copiedPin ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
      </button>
      {/* Title */}
      <p className="text-xs text-slate-500">{session.title} • {session.classroomName}</p>
    </div>
  </div>

  {/* Right: Actions */}
  <div className="flex items-center gap-2">
    <button onClick={() => loadSessionData(true)} className="px-3 py-2 rounded-xl bg-slate-100 text-xs font-semibold">
      <RefreshCw className={refreshing ? 'animate-spin' : ''} /> รีเฟรช
    </button>
    <Link href="/tests" target="_blank" className="px-3 py-2 rounded-xl bg-purple-50 border border-purple-200 text-purple-700 text-xs font-bold">
      <ExternalLink /> เปิดหน้านักเรียน
    </Link>
    {/* LIVE Badge */}
    <div className="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center gap-1.5">
      <span className="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
      LIVE SESSION
    </div>
  </div>
</div>
```

### Section 2: Session Mode Bar
```tsx
<div className="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs flex sm:items-center justify-between gap-3">
  {/* Left: mode + time */}
  <div className="flex items-center gap-3">
    <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
      <Radio />
    </div>
    <div>
      <h4 className="text-xs font-bold text-slate-800">
        {session.assignmentMode === 'pre_post' ? 'แบบทดสอบก่อน/หลังเรียน (Pre/Post)' : 'แบบทดสอบประจำคาบ (Live Quiz)'}
      </h4>
      <p className="text-[11px] text-slate-500">
        {session.hasTimeLimit ? `⏱️ ${session.timeLimitMinutes} นาที` : '🔓 ไม่จำกัดเวลา'}
      </p>
    </div>
  </div>

  {/* Right: Announcement badge + button */}
  <div className="flex items-center gap-2">
    {session.announcementMessage && (
      <div className="px-3 py-1.5 rounded-xl bg-purple-100 text-purple-800 text-xs font-semibold flex items-center gap-2">
        📢 มีประกาศสด
        <button onClick={handleClearAnnouncement} className="text-purple-600 font-bold">✕</button>
      </div>
    )}
    <button onClick={() => setActiveModal('announcement')}
      className="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center gap-1.5">
      <Bell className="w-3.5 h-3.5 text-purple-600" /> ประกาศด่วน
    </button>
  </div>
</div>
```

### Section 3: Eyes On Me Card
```tsx
<div className="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-3">
  <div className="flex items-center justify-between gap-4">
    <div className="flex items-center gap-3">
      {/* Icon */}
      <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${isEyesOnMe ? 'bg-pink-100 text-pink-600' : 'bg-slate-100 text-slate-500'}`}>
        <Eye />
      </div>
      <div>
        <h3 className="text-sm font-bold">👀 Eyes On Me</h3>
        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${isEyesOnMe ? 'bg-pink-100 text-pink-700' : 'bg-emerald-100 text-emerald-700'}`}>
          {isEyesOnMe ? 'หน้าจอนักเรียนถูกล็อก' : 'นักเรียนทำข้อสอบได้ตามปกติ'}
        </span>
        <p className="text-xs text-slate-500">
          {isEyesOnMe ? 'นักเรียนถูกหยุดชั่วคราว เพื่อดูกระดาน' : 'อนุญาตให้ทำข้อสอบและส่งคำตอบ'}
        </p>
      </div>
    </div>

    {/* Toggle Switch */}
    <button onClick={handleToggleEyesOnMe} disabled={savingEyesOnMe}
      className={`relative inline-flex h-7 w-12 rounded-full border-2 border-transparent transition-colors ${isEyesOnMe ? 'bg-pink-500' : 'bg-slate-300'}`}>
      <span className={`inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform ${isEyesOnMe ? 'translate-x-5' : 'translate-x-0'}`} />
    </button>
  </div>

  {/* Per-student lock accordion */}
  <div className="pt-2 border-t border-slate-100">
    <button onClick={() => setIsLockPerStudentOpen(!isLockPerStudentOpen)}
      className="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
      {isLockPerStudentOpen ? <ChevronUp /> : <ChevronDown />}
      ล็อกเป็นรายคน
      {session.lockedStudentIds?.length > 0 && (
        <span className="px-2 rounded-full bg-pink-100 text-pink-700 text-[10px] font-bold">
          ล็อกอยู่ {session.lockedStudentIds.length} คน
        </span>
      )}
    </button>

    {isLockPerStudentOpen && (
      <div className="mt-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
          {students.map(st => {
            const isLocked = (session.lockedStudentIds || []).includes(st.studentId);
            return (
              <div key={st.studentId} className="p-2.5 rounded-xl bg-white border border-slate-200 flex items-center justify-between text-xs">
                <div className="flex items-center gap-2">
                  <div className="w-6 h-6 rounded-full bg-purple-100 text-purple-700 font-bold text-[10px] flex items-center justify-center">
                    {st.name.charAt(0)}
                  </div>
                  <span className="font-semibold">{st.name}</span>
                </div>
                <button onClick={() => handleToggleStudentLock(st.studentId)}
                  className={`p-1.5 rounded-lg ${isLocked ? 'bg-pink-100 text-pink-700' : 'bg-slate-100 text-slate-500'}`}>
                  {isLocked ? <Lock className="w-3.5 h-3.5" /> : <Unlock className="w-3.5 h-3.5" />}
                </button>
              </div>
            );
          })}
        </div>
      </div>
    )}
  </div>
</div>
```

### Section 4: Exam Overview Banner (ปุ่มเปิด Modal)
```tsx
<button onClick={() => setActiveModal('exam_overview')}
  className="w-full p-4 rounded-2xl bg-white hover:bg-slate-50 border border-slate-200 shadow-xs flex items-center justify-between">
  <div className="flex items-center gap-3">
    <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
      <BookOpen />
    </div>
    <div>
      <h4 className="text-xs sm:text-sm font-bold">ภาพรวมข้อสอบ (Exam Overview)</h4>
      <p className="text-[11px] text-slate-500">ดูข้อสอบทั้งหมด ({questions.length} ข้อ) พร้อมเฉลย</p>
    </div>
  </div>
  <span className="text-slate-400 font-bold text-sm">›</span>
</button>
```

### Section 5: Student Action Grid (9 ปุ่ม)

```tsx
// Layout: grid grid-cols-2 md:grid-cols-4 gap-3
// แต่ละปุ่มใช้ pattern นี้:
<button onClick={() => setActiveModal('MODAL_KEY')}
  className="p-4 rounded-2xl bg-{COLOR}-50/80 hover:bg-{COLOR}-100/80 border border-{COLOR}-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-2xs group">
  <div className="w-10 h-10 rounded-2xl bg-{COLOR}-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
    <ICON className="w-5 h-5" />
  </div>
  <span className="text-xs font-bold text-{COLOR}-950">ชื่อปุ่ม</span>
  <span className="text-[10px] text-{COLOR}-700">คำอธิบาย</span>
</button>
```

**9 ปุ่มตามลำดับ:**

| Modal Key | ชื่อ | COLOR | ICON |
|-----------|------|-------|------|
| `student_details` | รายละเอียดนักเรียน | `sky` | `Users` |
| `attendance` | เช็คชื่อนักเรียน | `cyan` | `FileSpreadsheet` |
| `announcement` | การแจ้งเตือนนักเรียน | `pink` | `Bell` |
| `remediation` | ศูนย์บทเรียนเสริม | `amber` | `Lightbulb` |
| `exam_stats` | การสอบ | `purple` | `Award` |
| `skill_map` | แผนที่ทักษะของห้อง | `emerald` | `Layers` |
| `missed_questions` | สรุปคำถามที่ตอบผิด | `orange` | `BarChart3` |
| `reset_attempt` | รีเซ็ตคำตอบนักเรียน | `slate` | `RotateCcw` |
| `boss_fight` | บอสไฟท์ห้องเรียน | gradient `rose→purple` | `Swords` |

> ปุ่ม boss_fight: `className="... sm:col-span-2 md:col-span-1 bg-gradient-to-br from-rose-50 to-purple-50"`

### Section 6: Live Student Progress Pills
```tsx
<div className="p-3.5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-2">
  <div className="flex items-center gap-2">
    <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
    <span className="text-xs font-bold text-slate-700">((•)) สถานะนักเรียนสด:</span>
  </div>
  <div className="flex flex-wrap items-center gap-2">
    {students.map(st => (
      <div key={st.studentId} className={`px-3 py-1.5 rounded-xl border text-xs flex items-center gap-2 ${
        st.sessionStatus === 'submitted' ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : st.sessionStatus === 'in_progress' ? 'bg-sky-50 border-sky-200 text-sky-800'
        : 'bg-slate-50 border-slate-200 text-slate-600'
      }`}>
        <span className="font-bold">{st.name}</span>
        {st.sessionStatus === 'submitted'
          ? <span className="px-1.5 py-0.5 rounded-full bg-emerald-200 text-emerald-900 text-[10px] font-extrabold">ส่งแล้ว {st.scorePercentage !== null ? `(${st.scorePercentage}%)` : ''}</span>
          : st.sessionStatus === 'in_progress'
          ? <span className="px-1.5 py-0.5 rounded-full bg-sky-200 text-sky-900 text-[10px] font-extrabold">ทำข้อ {st.currentQuestionNumber}/{st.totalQuestions}</span>
          : <span className="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[10px] font-semibold">รอเริ่มสอบ</span>
        }
      </div>
    ))}
  </div>
</div>
```

### Section 7: Boss Fight Card
```tsx
<div className="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white border border-purple-500/30 shadow-xl space-y-4 relative overflow-hidden">
  {/* Ambient blur */}
  <div className="absolute top-0 right-0 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl pointer-events-none" />

  <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 relative z-10">
    {/* Boss info */}
    <div className="flex items-center gap-3">
      <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-rose-500 to-purple-600 flex items-center justify-center text-2xl shadow-lg border border-white/20">
        {bossArchetype.emoji}  {/* จาก getBossArchetype(session.bossTheme) */}
      </div>
      <div>
        <div className="flex items-center gap-2">
          <h3 className="text-base font-black">👾 บอสไฟท์ห้องเรียน</h3>
          {session.bossFightActive && (
            <span className="px-2 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[10px] font-bold animate-pulse">LIVE BATTLE</span>
          )}
        </div>
        <p className="text-xs text-slate-300 mt-0.5">
          {session.bossFightActive
            ? `กำลังประลองกับ ${session.bossName} — เลือดบอสลดทันทีที่ตอบถูก!`
            : 'กระตุ้นความร่วมมือด้วยเกมพิชิตบอส'}
        </p>
      </div>
    </div>
    <button onClick={() => setActiveModal('boss_fight')}
      className="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs flex items-center gap-2">
      <Swords className="w-4 h-4 text-pink-300" /> เปิดศูนย์ควบคุมบอส / จอฉาย
    </button>
  </div>

  {/* HP Bar (แสดงเฉพาะเมื่อ bossFightActive) */}
  {session.bossFightActive && (
    <div className="space-y-2 relative z-10 pt-1">
      <div className="flex items-center justify-between text-xs font-bold">
        <div className="flex items-center gap-2">
          <span className="text-purple-300">{session.bossName}</span>
          {topHitter && <span className="text-yellow-400 text-[11px]">👑 ผู้นำดาเมจ: {topHitter.name} ({topHitter.totalDamage} DMG)</span>}
        </div>
        <span className="font-mono"><strong>{bossCurrentHp}</strong> / {bossMaxHp} HP ({hpPct}%)</span>
      </div>
      <div className="w-full h-3.5 bg-slate-950/80 rounded-full overflow-hidden p-0.5 border border-white/20">
        <div className={`h-full rounded-full transition-all duration-500 ${
          hpPct > 50 ? 'bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-500'
          : hpPct > 20 ? 'bg-gradient-to-r from-amber-500 to-rose-500'
          : 'bg-gradient-to-r from-rose-600 to-pink-600 animate-pulse'
        }`} style={{ width: `${hpPct}%` }} />
      </div>
    </div>
  )}

  {/* Quick actions */}
  <div className="flex flex-wrap items-center gap-2.5 pt-1 relative z-10">
    <button onClick={handleToggleBossFight}
      className={`px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 ${
        session.bossFightActive
          ? 'bg-rose-600/80 hover:bg-rose-600 text-white'
          : 'bg-gradient-to-r from-sky-600 to-blue-600 text-white'
      }`}>
      <Sparkles /> {session.bossFightActive ? '⚡ สิ้นสุดบอสไฟท์' : '⚡ 🐲 เริ่มบอสไฟท์ทันที'}
    </button>
    <button onClick={() => setActiveModal('boss_fight')}
      className="px-3.5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-semibold border border-white/15 flex items-center gap-1.5">
      <Tv className="text-yellow-300" /> ฉายจอใหญ่ห้องเรียน (Projector Arena)
    </button>
  </div>
</div>
```

---

## MODALS (ทั้ง 10 Modal)

### Modal Pattern (ใช้ซ้ำทุก Modal)
```tsx
{activeModal === 'MODAL_KEY' && (
  <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div className="bg-white rounded-3xl max-w-[md|xl|2xl] w-full p-6 shadow-2xl border border-slate-100 space-y-4 [max-h-[85vh] flex flex-col]">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 className="text-base font-bold text-slate-900">ชื่อ Modal</h3>
          <p className="text-xs text-slate-500">คำอธิบาย</p>
        </div>
        <button onClick={() => setActiveModal(null)} className="text-slate-400 hover:text-slate-600 font-bold">✕</button>
      </div>
      {/* Content */}
    </div>
  </div>
)}
```

### Modal 1: exam_overview — ภาพรวมข้อสอบ
- Loop `questions` → แสดง: หมายเลข + difficulty badge + questionText + ตัวเลือก
- เฉลยถูก: `bg-emerald-50 border-emerald-300 text-emerald-900 font-semibold` + " ✅"
- ต้องมี `max-h-[85vh] flex flex-col` + `overflow-y-auto` ใน content area

### Modal 2: student_details — รายละเอียดนักเรียน
- Loop `students` → Avatar (อักษรตัวแรก, `bg-sky-100 text-sky-700`) + ชื่อ + เวลาเข้า
- ขวา: คะแนน % ถ้าส่งแล้ว, หรือ "ข้อ X" + "ทำแล้ว X/Y ข้อ (Z%)"

### Modal 3: attendance — เช็คชื่อ
- รายชื่อ + badge "เข้าเรียนแล้ว" (emerald)
- ปุ่ม **"ดาวน์โหลด CSV"** → สร้าง Blob แล้ว trigger download:
```typescript
const csvHeader = 'ลำดับ,ชื่อ-นามสกุล,เวลาเข้าร่วม,สถานะ\n';
const csvRows = students.map((s, idx) =>
  `${idx + 1},"${s.name}","${new Date(s.joinedAt).toLocaleString('th-TH')}",เข้าเรียน`
).join('\n');
const blob = new Blob(['\uFEFF' + csvHeader + csvRows], { type: 'text/csv;charset=utf-8;' });
// BOM \uFEFF สำคัญมากสำหรับ Excel ภาษาไทย
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url; a.download = `attendance-session-${session.sessionPin}.csv`; a.click();
```

### Modal 4: announcement — ส่งประกาศด่วน
- `<textarea rows={3}>` พิมพ์ข้อความ
- ปุ่ม "ส่งประกาศทันที" `bg-purple-600` + `<Send />` icon
- disabled ถ้า `!announcementText.trim()` หรือ `sendingAnnouncement`

### Modal 5: remediation — ศูนย์บทเรียนเสริม
- Card amber: "หัวข้อที่ควรเน้นย้ำ: `{questionStats[0]?.topic}`"
- Card slate: "แบบฝึกหัดเสริมเจาะจง 3 ข้อสั้น" + badge "พร้อมใช้งาน"

### Modal 6: exam_stats — สถิติการสอบ
- Grid 2 ช่อง:
  - **คะแนนเฉลี่ย** (mean ของ students ที่ `scorePercentage !== null`): `bg-purple-50`
  - **ส่งแล้ว X/Y**: `bg-emerald-50`

### Modal 7: skill_map — แผนที่ทักษะ
- Loop `questionStats` → ชื่อหัวข้อ + "X% แม่นยำ" + Progress Bar
- Bar color: ≥70% emerald, ≥40% amber, <40% rose

### Modal 8: missed_questions — สรุปข้อที่ตอบผิด
- Loop `questionStats` (sorted by `incorrectCount DESC`)
- ต่อข้อ: "ข้อที่ N (หัวข้อ)" + badge "ตอบผิด X คน" (rose) + "อัตราตอบถูก: Y%"

### Modal 9: reset_attempt — รีเซ็ตคำตอบ
- Loop `students` → ชื่อ + สถานะ + ปุ่ม "รีเซ็ตข้อสอบ" (rose)
- กด → `handleResetStudentAttempt(studentId, name)`

### Modal 10: boss_fight — Boss Fight Arena
```tsx
{activeModal === 'boss_fight' && (
  <BossBattleArenaModal
    sessionId={params.sessionId}
    session={session}
    students={students}
    onClose={() => setActiveModal(null)}
    onRefresh={() => loadSessionData(true)}
  />
)}
```

---

## API ENDPOINTS

### `GET /api/teacher/sessions/[sessionId]`
**Response:** ดู TypeScript Types ด้านบน (`SessionDetailResponse`)

**Logic หลัก:**
1. Fetch session → classroom → assignment → questionSetVersion → questions + options
2. Fetch participants → users → attempts → answers
3. คำนวณ `progressPct`, `currentQuestionNumber`, `isEyesOnMeLocked` ต่อนักเรียน
4. คำนวณ `questionStats` (correctPct ต่อข้อ, sort by incorrectCount DESC)
5. Parse `bossCombatLog` → `topDamageDealers` (sort by totalDamage DESC)

---

### `PATCH /api/teacher/sessions` (อัปเดต Session fields)

**Request Body** — ทุก field optional ยกเว้น `sessionId`:
```typescript
{
  sessionId: string;              // required
  status?: 'active' | 'closed';
  title?: string;
  allowLateJoin?: boolean;
  hasTimeLimit?: boolean;
  timeLimitMinutes?: number;
  eyesOnMeEnabled?: boolean;
  lockedStudentIds?: string[];
  announcementMessage?: string | null;
  bossFightActive?: boolean;
  bossCurrentHp?: number;
  bossMaxHp?: number;
  bossName?: string;
  bossTheme?: string;
  bossRewardPoints?: number;
  bossDefeated?: boolean;
  assignmentId?: string | null;
}
```

> **Business Rule:** ถ้า `status = 'active'` → ปิด session active อื่นทั้งหมดก่อน (Single Active Session)

---

### `POST /api/teacher/sessions` (สร้าง Session ใหม่)

```typescript
// Request
{
  title: string;           // required
  classroomId?: string;
  assignmentId?: string;
  questionSetVersionId?: string;
  educationStage?: string; // default: 'university'
  allowLateJoin?: boolean; // default: true
  hasTimeLimit?: boolean;  // default: true
  timeLimitMinutes?: number;
  customPin?: string;      // ถ้าไม่ระบุ → สุ่ม 6-digit unique PIN
}

// Response
{ success: true, sessionId: string, sessionPin: string,
  hasTimeLimit: boolean, timeLimitMinutes: number | null }
```

> **Business Rule:** สร้างใหม่ → ปิด active sessions ทั้งหมดก่อน (enforce single active)

---

### `POST /api/teacher/sessions/[sessionId]` (Actions)

#### `reset_student_attempt`
```typescript
Body: { action: 'reset_student_attempt', studentId: string }
// 1. ลบ attemptAnswers ทั้งหมดของ attempt นี้
// 2. ลบ examAttempts record
// 3. อัปเดต sessionParticipants: { attemptId: null, status: 'joined' }
Response: { success: true }
```

#### `teacher_strike` (Boss Fight support hit)
```typescript
Body: { action: 'teacher_strike', damage?: number }  // default damage: 25
// 1. คำนวณ newHp = max(0, currentHp - damage)
// 2. append hit ใหม่ลง bossCombatLog (max 50 entries)
// 3. ถ้า newHp = 0 && ยังไม่ defeated → award NC Points ให้ participants ทุกคน
Response: { success: true, damageDealt, currentHp, isDefeated }
```

#### `reset_boss`
```typescript
Body: { action: 'reset_boss' }
// bossCurrentHp = bossMaxHp, bossDefeated = false, bossCombatLog = []
```

#### `setup_boss`
```typescript
Body: { action: 'setup_boss', bossMaxHp?, bossRewardPoints?, bossName?, bossTheme? }
// Set boss config + bossFightActive = true + reset HP + clear combat log
```

---

### `POST /api/sessions/join` (Student join via PIN)

```typescript
Body: { sessionPin: string }
// 1. ค้นหา session จาก PIN (strip spaces/dashes)
// 2. ตรวจ status = 'active'
// 3. Insert sessionParticipants (ถ้ายังไม่มี)
// 4. Auto-enroll classroomMembers (ถ้า session มี classroomId)
// 5. หา existingAttempt (session attempt หรือ historical)
Response: {
  success: true,
  session: { id, title, sessionPin, teacherName, classroomName,
             educationStage, hasTimeLimit, timeLimitMinutes, assignment, existingAttempt }
}
```

---

### `DELETE /api/teacher/sessions/[sessionId]`
1. ลบ `sessionParticipants` ก่อน
2. ลบ `classroomSessions`

### `DELETE /api/teacher/sessions?action=delete_all_closed`
ลบทุก Session ที่ `status = 'closed'`

---

## BOSS ARCHETYPES LIBRARY

```typescript
// src/lib/boss-archetypes.ts
export interface BossArchetype {
  id: string;
  name: string;       // ชื่อบอส ภาษาไทย
  emoji: string;      // 🐲 | 🗿 | 🔥 | 🌑 | ...
  category: string;   // 'mythical' | 'elemental' | 'cosmic' | ...
  description: string;
  color: string;      // Tailwind color class
}

export const BOSS_ARCHETYPES: BossArchetype[] = [
  { id: 'dragon', name: 'มังกรเพลิงแห่งความรู้ ไครอส', emoji: '🐲', ... },
  { id: 'golem',  name: 'โกเล็มหินแกร่งแห่งปัญญา',    emoji: '🗿', ... },
  { id: 'phoenix',name: 'ฟีนิกซ์แห่งการฟื้นคืน',       emoji: '🔥', ... },
  { id: 'shadow', name: 'เงามืดแห่งความสงสัย',          emoji: '🌑', ... },
  // ... เพิ่มตามต้องการ
];

export function getBossArchetype(theme?: string | null): BossArchetype {
  return BOSS_ARCHETYPES.find(b => b.id === theme) || BOSS_ARCHETYPES[0];
}
```

---

## STUDENT SIDE: Eyes On Me Lock

เมื่อนักเรียน poll หน้าทำข้อสอบ ต้องตรวจสอบ:
```typescript
// หน้าของนักเรียน: poll /api/teacher/sessions/[sessionId] หรือ endpoint แยก
// แล้วตรวจ:
const isLocked = session.eyesOnMeEnabled ||
  (session.lockedStudentIds || []).includes(currentStudentId);

// ถ้า isLocked = true → overlay "👀 ครูกำลังอธิบาย โปรดดูกระดาน"
```

---

## STUDENT SIDE: Announcement Banner

```typescript
// ถ้า session.announcementMessage !== null → แสดง Banner สีม่วงบนสุดของหน้า
// นักเรียนปิด banner เองได้ (local state เท่านั้น ไม่กระทบ state ครู)
```

---

## DESIGN TOKENS สรุป

```css
/* Container */
.page { max-width: 72rem; margin: auto; padding-bottom: 4rem; }
.card { border-radius: 1rem; border: 1px solid rgb(226 232 240 / 0.8); background: white; }
.card-lg { border-radius: 1.5rem; }

/* Colors by feature */
--live-badge:    bg-emerald-50 border-emerald-200 text-emerald-800;
--eyes-on-off:   bg-slate-300;
--eyes-on-on:    bg-pink-500;
--student-done:  bg-emerald-50 border-emerald-200 text-emerald-800;
--student-doing: bg-sky-50 border-sky-200 text-sky-800;
--student-wait:  bg-slate-50 border-slate-200 text-slate-600;
--boss-card:     bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900;
--hp-high:       bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-500;
--hp-mid:        bg-gradient-to-r from-amber-500 to-rose-500;
--hp-low:        bg-gradient-to-r from-rose-600 to-pink-600 animate-pulse;

/* Typography */
--pin-text:    text-2xl font-black font-mono tracking-widest;
--score-text:  text-2xl font-black;
--badge-text:  text-[10px] font-bold uppercase;
--label-text:  text-xs font-semibold;
```

---

## DEPENDENCIES

```typescript
// Required external components/libs:
import BossBattleArenaModal from '@/components/BossBattleArenaModal';
import { getBossArchetype } from '@/lib/boss-archetypes';
import { playBattleSound, setBattleSoundEnabled, getBattleSoundEnabled } from '@/lib/battle-audio';
import FormattedQuestionText from '@/components/FormattedQuestionText';
import { requireRole } from '@/lib/auth';
import { getDb } from '@/db';
import { initTables } from '@/db/init-tables';
import { awardNcPoints } from '@/lib/nc-points';

// Lucide icons used:
ArrowLeft, Copy, Check, RefreshCw, Eye, EyeOff, Radio, BookOpen,
Users, Bell, Clock, ChevronDown, ChevronUp, FileSpreadsheet, Lightbulb,
Award, BarChart3, RotateCcw, Sparkles, Swords, Tv, Layers, CheckCircle2,
AlertCircle, HelpCircle, Send, Lock, Unlock, Play, Pause, ExternalLink
```

---

## NOTES FOR AI

1. **Polling** ทุก 3 วินาที เป็นหัวใจของ realtime — อย่าลืม `clearInterval` ใน cleanup
2. **Single Active Session** — ทุก POST create และ PATCH `status: 'active'` ต้องปิด session อื่นก่อน
3. **CSV Export** ต้องมี BOM `\uFEFF` นำหน้าเสมอ สำหรับ Excel ภาษาไทย
4. **Boss HP คำนวณ:** `totalQuestions × max(1, studentsCount) × 10`
5. **Combat Log** เก็บสูงสุด 50 entries เท่านั้น (`.slice(0, 50)`)
6. **NC Points** award เมื่อบอสตาย — ต้องตรวจ `!currentSes.bossDefeated` ก่อน ป้องกัน award ซ้ำ
7. **Eyes On Me** ทำงาน 2 ระดับ: Global (ทั้งห้อง) + Per-student (รายบุคคล)
8. Modal backdrop ใช้ `backdrop-blur-xs` ไม่ใช่ `backdrop-blur-sm`
