<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    rounds: { type: Array, default: () => [] },
});

const nameInput = ref(null);
const editingId = ref(null);
const isEditorOpen = ref(props.rounds.length === 0);
const form = useForm({
    name: '',
    self_assess_start: '',
    self_assess_end: '',
    supervisor_assess_end: '',
    is_active: true,
    copy_from_round_id: props.rounds[0]?.id || '',
});

const activeRound = computed(() => props.rounds.find((round) => round.isActive) || null);
const isEditing = computed(() => editingId.value !== null);
const activeSubmittedCount = computed(() => Number(activeRound.value?.submittedUserCount || 0));
const activeEligibleCount = computed(() => Number(activeRound.value?.eligibleUserCount || 0));
const activeRemainingCount = computed(() => Math.max(
    activeEligibleCount.value - activeSubmittedCount.value,
    0,
));

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.is_active = !activeRound.value;
    form.copy_from_round_id = props.rounds[0]?.id || '';
    form.clearErrors();
};

const showEditor = async () => {
    isEditorOpen.value = true;
    await nextTick();
    nameInput.value?.focus();
};

const openCreate = () => {
    resetForm();
    showEditor();
};

const closeEditor = () => {
    resetForm();
    isEditorOpen.value = false;
};

const editRound = (round) => {
    editingId.value = round.id;
    form.name = round.name;
    form.self_assess_start = round.selfAssessStart || '';
    form.self_assess_end = round.selfAssessEnd || '';
    form.supervisor_assess_end = round.supervisorAssessEnd || '';
    form.is_active = round.isActive;
    form.copy_from_round_id = '';
    form.clearErrors();
    showEditor();
};

const submit = () => {
    form.clearErrors();
    const labels = {
        self_assess_start: 'วันเริ่มการประเมิน',
        self_assess_end: 'วันสิ้นสุดการประเมิน',
        supervisor_assess_end: 'วันสิ้นสุดการตรวจของหัวหน้า',
    };

    for (const field of Object.keys(labels)) {
        if (!form[field] && form.is_active) {
            form.setError(field, `กรุณาระบุ${labels[field]}`);
        }
    }

    if (Object.keys(form.errors).length) return;
    if (form.self_assess_start && form.self_assess_end && form.self_assess_end < form.self_assess_start) {
        form.setError('self_assess_end', 'วันสิ้นสุดการประเมินต้องไม่ก่อนวันเริ่มการประเมิน');
        return;
    }
    if (form.self_assess_end && form.supervisor_assess_end && form.supervisor_assess_end < form.self_assess_end) {
        form.setError('supervisor_assess_end', 'วันสิ้นสุดการตรวจของหัวหน้าต้องไม่ก่อนวันสิ้นสุดการประเมิน');
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: closeEditor,
    };

    if (isEditing.value) {
        form.put(route('hr.assessment-rounds.update', editingId.value), options);
        return;
    }

    form.post(route('hr.assessment-rounds.store'), options);
};

const activate = (round) => {
    if (round.isActive) return;
    if (round.readiness && !round.readiness.ready) return;
    if (!window.confirm(`เปิดใช้ “${round.name}” เป็นรอบปัจจุบัน? รอบที่เปิดใช้อยู่จะถูกปิดทันที`)) return;
    router.patch(route('hr.assessment-rounds.activate', round.id), {}, { preserveScroll: true });
};

const readinessMessage = (round) => (round.readiness?.issues || [])
    .map((issue) => issue.message)
    .join(' · ');

const dateLabel = (value) => value
    ? new Intl.DateTimeFormat('th-TH-u-ca-gregory', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Bangkok' }).format(new Date(`${value}T00:00:00+07:00`))
    : 'ยังไม่กำหนด';
</script>

<template>
    <section class="round-page">
        <header class="page-heading">
            <div>
                <h1>รอบการประเมิน <span aria-hidden="true">▣</span></h1>
                <p>เปิด-ปิดรอบ กำหนดช่วงเวลา และตรวจสอบสถานะ</p>
            </div>
            <button type="button" class="create-button" @click="openCreate">
                <span aria-hidden="true">+</span> เปิดรอบใหม่
            </button>
        </header>

        <div class="summary-grid">
            <section class="summary-card active-card">
                <span class="summary-label">รอบที่กำลังเปิดอยู่</span>
                <template v-if="activeRound">
                    <strong class="active-name">{{ activeRound.name }}</strong>
                </template>
                <template v-else>
                    <strong class="active-name empty">ยังไม่มีรอบที่เปิดใช้งาน</strong>
                    <span class="active-hint">กด “เปิดรอบใหม่” เพื่อเริ่มต้น</span>
                </template>
            </section>

            <section class="summary-card count-card">
                <span class="summary-label">ส่งแบบประเมินแล้ว</span>
                <strong class="assessment-total">
                    {{ activeSubmittedCount.toLocaleString('th-TH') }}<small>/{{ activeEligibleCount.toLocaleString('th-TH') }}</small>
                </strong>
                <span v-if="activeRound && activeRemainingCount" class="assessment-caption pending">เหลือ {{ activeRemainingCount.toLocaleString('th-TH') }} คน</span>
                <span v-else-if="activeRound" class="assessment-caption complete">ส่งแบบประเมินครบแล้ว</span>
                <span v-else class="assessment-caption">ยังไม่มีรอบที่เปิดใช้งาน</span>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="isEditorOpen"
                class="round-modal-backdrop"
                role="presentation"
                @click.self="closeEditor"
                @keydown.esc="closeEditor"
            >
                <form
                    class="round-editor"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="round-editor-title"
                    @submit.prevent="submit"
                >
                    <header class="editor-heading">
                        <div>
                            <span>{{ isEditing ? 'แก้ไขข้อมูล' : 'เปิดรอบใหม่' }}</span>
                            <h2 id="round-editor-title">{{ isEditing ? form.name || 'แก้ไขรอบการประเมิน' : 'กำหนดรอบการประเมิน' }}</h2>
                            <p>ระบุชื่อและช่วงเวลาที่บุคลากรกับหัวหน้าดำเนินการได้</p>
                        </div>
                        <button type="button" class="icon-close" aria-label="ปิดหน้าต่าง" @click="closeEditor">×</button>
                    </header>

                    <div class="editor-grid">
                        <label class="field name-field">
                            <span>ชื่อรอบการประเมิน *</span>
                            <input ref="nameInput" v-model="form.name" type="text" autocomplete="off" placeholder="เช่น รอบประเมินครั้งที่ 1">
                            <em v-if="form.errors.name">{{ form.errors.name }}</em>
                        </label>
                        <div class="date-fields">
                            <p class="date-hint">เลือกวันที่จากปฏิทิน (ค.ศ.)</p>
                            <label class="field">
                                <span>วันเริ่มการประเมิน (ค.ศ.){{ form.is_active ? ' *' : '' }}</span>
                                <input v-model="form.self_assess_start" type="date" lang="en-GB">
                                <em v-if="form.errors.self_assess_start">{{ form.errors.self_assess_start }}</em>
                            </label>
                            <label class="field">
                                <span>วันสิ้นสุดการประเมิน (ค.ศ.){{ form.is_active ? ' *' : '' }}</span>
                                <input v-model="form.self_assess_end" type="date" lang="en-GB" :min="form.self_assess_start || undefined">
                                <em v-if="form.errors.self_assess_end">{{ form.errors.self_assess_end }}</em>
                            </label>
                            <label class="field">
                                <span>หัวหน้าตรวจผลได้ถึง (ค.ศ.){{ form.is_active ? ' *' : '' }}</span>
                                <input v-model="form.supervisor_assess_end" type="date" lang="en-GB" :min="form.self_assess_end || form.self_assess_start || undefined">
                                <em v-if="form.errors.supervisor_assess_end">{{ form.errors.supervisor_assess_end }}</em>
                            </label>
                        </div>
                        <label v-if="!isEditing && rounds.length" class="field copy-field">
                            <span>คัดลอกชุดสมรรถนะจาก</span>
                            <select v-model="form.copy_from_round_id">
                                <option value="">ไม่คัดลอกข้อมูล</option>
                                <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.name }}</option>
                            </select>
                            <em v-if="form.errors.copy_from_round_id">{{ form.errors.copy_from_round_id }}</em>
                        </label>
                    </div>
                    <div v-if="form.errors.round" class="round-readiness-error">
                        <strong>ยังเปิดใช้รอบนี้ไม่ได้</strong>
                        <span>{{ form.errors.round }}</span>
                    </div>

                    <footer class="editor-footer">
                        <label class="active-toggle">
                            <input v-model="form.is_active" type="checkbox">
                            <span><b>เปิดใช้งานรอบนี้ทันที</b><small>รอบเดิมจะถูกปิดอัตโนมัติ</small></span>
                        </label>
                        <div>
                            <button type="button" class="secondary-button" @click="closeEditor">ยกเลิก</button>
                            <button type="submit" class="save-button" :disabled="form.processing">
                                {{ form.processing ? 'กำลังบันทึก…' : (isEditing ? 'บันทึกการแก้ไข' : 'เปิดรอบการประเมิน') }}
                            </button>
                        </div>
                    </footer>
                </form>
            </div>
        </Teleport>

        <section class="round-table-card">
            <header><h2>รอบประเมินทั้งหมด</h2><span>{{ rounds.length }} รอบ</span></header>

            <div v-if="rounds.length" class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>รอบประเมิน</th>
                            <th>รอบประเมินตนเอง</th>
                            <th>หัวหน้าตรวจถึง</th>
                            <th class="number-column">ผู้ส่ง / ทั้งหมด</th>
                            <th>สถานะ</th>
                            <th><span class="sr-only">การทำงาน</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="round in rounds" :key="round.id" :class="{ current: round.isActive }">
                            <td>
                                <strong>{{ round.name }}</strong>
                                <span
                                    v-if="round.readiness && !round.readiness.ready"
                                    class="readiness-badge"
                                    :title="readinessMessage(round)"
                                >ยังไม่พร้อม</span>
                            </td>
                            <td>{{ dateLabel(round.selfAssessStart) }} <span class="date-separator">ถึง</span> {{ dateLabel(round.selfAssessEnd) }}</td>
                            <td>{{ dateLabel(round.supervisorAssessEnd) }}</td>
                            <td class="number-column assessment-cell">
                                {{ Number(round.submittedUserCount || 0).toLocaleString('th-TH') }}
                                <span>/ {{ Number(round.eligibleUserCount || 0).toLocaleString('th-TH') }} คน</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="round.isActive ? 'open' : 'closed'">
                                    <i v-if="round.isActive" aria-hidden="true"></i>{{ round.isActive ? 'เปิดอยู่' : 'ปิดแล้ว' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="edit-button" @click="editRound(round)">แก้ไข</button>
                                    <button
                                        v-if="!round.isActive"
                                        type="button"
                                        class="activate-button"
                                        :disabled="round.readiness && !round.readiness.ready"
                                        :title="round.readiness && !round.readiness.ready ? readinessMessage(round) : 'เปิดใช้รอบนี้'"
                                        @click="activate(round)"
                                    >เปิดใช้</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="empty-state">
                <span aria-hidden="true">▣</span>
                <strong>ยังไม่มีรอบการประเมิน</strong>
                <p>สร้างรอบแรกเพื่อกำหนดช่วงเวลาประเมินของคณะ</p>
                <button type="button" class="create-button" @click="openCreate">+ เปิดรอบใหม่</button>
            </div>
        </section>
    </section>
</template>

<style scoped>
.round-page { --ink: oklch(29% .035 253); --muted: oklch(61% .035 253); --line: oklch(89% .018 253); --surface: oklch(99% .005 253); --canvas: oklch(97% .012 253); --accent: oklch(53% .145 165); --accent-dark: oklch(43% .12 165); --accent-soft: oklch(95% .045 165); width: 100%; color: var(--ink); font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; font-synthesis-weight: none; }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 24px; }.page-heading h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -.025em; }.page-heading h1 span { margin-left: 5px; color: oklch(59% .16 35); font-size: 16px; }.page-heading p { margin: 5px 0 0; color: var(--muted); font-size: 13px; }
.create-button,.save-button,.activate-button { border: 1px solid var(--accent); border-radius: 8px; background: var(--accent); color: oklch(98% .008 165); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }.create-button { min-height: 42px; padding: 9px 17px; }.create-button span { margin-right: 3px; font-size: 16px; line-height: 0; }.create-button:hover,.save-button:hover,.activate-button:hover { border-color: var(--accent-dark); background: var(--accent-dark); }
.summary-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px; margin-bottom: 20px; }.summary-card { display: grid; align-content: center; min-height: 138px; box-sizing: border-box; border: 1px solid var(--line); border-radius: 13px; background: var(--surface); padding: 24px 28px; box-shadow: 0 4px 10px oklch(32% .03 253 / .055); }.summary-label { margin-bottom: 12px; color: var(--muted); font-size: 12px; font-weight: 700; }.active-name { overflow: hidden; color: var(--accent-dark); font-size: 23px; font-weight: 800; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }.active-name.empty { color: var(--ink); font-size: 16px; }.active-hint,.assessment-caption { margin-top: 5px; color: var(--muted); font-size: 12px; font-weight: 600; }.assessment-total { color: var(--accent); font-size: 32px; font-weight: 800; line-height: 1; }.assessment-total small { margin-left: 3px; color: var(--muted); font-size: 16px; font-weight: 700; }.assessment-caption.pending { color: oklch(54% .17 31); }.assessment-caption.complete { color: var(--accent-dark); }
.round-modal-backdrop { --ink: oklch(29% .035 253); --muted: oklch(61% .035 253); --line: oklch(89% .018 253); --surface: oklch(99% .005 253); --canvas: oklch(97% .012 253); --accent: oklch(53% .145 165); --accent-dark: oklch(43% .12 165); position: fixed; z-index: 1000; inset: 0; display: grid; align-items: center; overflow-y: auto; background: oklch(20% .025 253 / .52); padding: 28px; font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; font-synthesis-weight: none; }
.round-editor { width: min(960px,100%); max-height: calc(100vh - 56px); margin: auto; overflow-y: auto; border: 1px solid oklch(84% .045 165); border-radius: 14px; background: var(--surface); box-shadow: 0 24px 70px oklch(18% .025 253 / .28); animation: modal-in .18s cubic-bezier(.22,1,.36,1); }.editor-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; border-bottom: 1px solid var(--line); padding: 18px 22px; }.editor-heading span { color: var(--accent-dark); font-size: 11px; font-weight: 700; letter-spacing: .04em; }.editor-heading h2 { margin: 3px 0 0; font-size: 16px; font-weight: 800; }.editor-heading p { margin: 3px 0 0; color: var(--muted); font-size: 12px; }.icon-close { width: 34px; height: 34px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); color: var(--muted); font: inherit; font-size: 19px; cursor: pointer; }.icon-close:hover { background: var(--canvas); color: var(--ink); }
.editor-grid { display: grid; grid-template-columns: minmax(0,1fr); row-gap: 22px; padding: 20px 22px 24px; }.field { display: grid; align-content: start; gap: 6px; min-width: 0; }.field > span { font-size: 12px; font-weight: 700; }.field input,.field select { width: 100%; height: 40px; box-sizing: border-box; border: 1px solid oklch(83% .025 253); border-radius: 7px; padding: 0 10px; background: var(--surface); color: var(--ink); font: inherit; font-size: 13px; outline: 0; }.field input:hover,.field select:hover { border-color: oklch(72% .045 253); }.field input:focus-visible,.field select:focus-visible,.round-page button:focus-visible { border-color: var(--accent); outline: 0; box-shadow: 0 0 0 3px oklch(72% .09 165 / .22); }.field em { color: oklch(52% .18 28); font-size: 11px; font-style: normal; }.date-fields { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 14px; }.date-hint { grid-column: 1 / -1; margin: 0 0 -4px; color: var(--muted); font-size: 11px; }.copy-field { max-width: 460px; }
.editor-footer { display: flex; align-items: center; justify-content: space-between; gap: 18px; border-top: 1px solid var(--line); background: var(--canvas); padding: 13px 22px; }.active-toggle { display: flex; align-items: center; gap: 9px; cursor: pointer; }.active-toggle input { width: 16px; height: 16px; accent-color: var(--accent); }.active-toggle > span { display: grid; gap: 1px; }.active-toggle b { font-size: 12px; }.active-toggle small { color: var(--muted); font-size: 11px; }.editor-footer > div { display: flex; gap: 8px; }.secondary-button,.save-button { min-height: 38px; border-radius: 7px; padding: 8px 14px; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }.secondary-button { border: 1px solid oklch(83% .025 253); background: var(--surface); color: var(--ink); }.secondary-button:hover { background: var(--canvas); }.save-button:disabled { opacity: .55; cursor: wait; }
.round-readiness-error { display: grid; gap: 3px; margin: 0 22px 18px; border: 1px solid oklch(78% .11 29); border-radius: 8px; background: oklch(96% .035 29); padding: 10px 12px; color: oklch(46% .16 29); font-size: 12px; }.round-readiness-error strong { font-weight: 800; }
.round-table-card { overflow: hidden; border: 1px solid var(--line); border-radius: 13px; background: var(--surface); box-shadow: 0 4px 10px oklch(32% .03 253 / .055); }.round-table-card > header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; }.round-table-card > header h2 { margin: 0; font-size: 14px; font-weight: 700; }.round-table-card > header span { color: var(--muted); font-size: 12px; }.table-scroll { overflow-x: auto; border-top: 1px solid var(--line); }table { width: 100%; min-width: 820px; border-collapse: collapse; }th,td { padding: 14px 16px; border-bottom: 1px solid var(--line); text-align: left; white-space: nowrap; }th { background: var(--canvas); color: var(--muted); font-size: 11px; font-weight: 700; }td { color: oklch(49% .03 253); font-size: 13px; }tbody tr:last-child td { border-bottom: 0; }tbody tr:hover { background: oklch(98% .008 253); }tbody tr.current { background: var(--accent-soft); }td strong { color: var(--ink); font-size: 13px; font-weight: 700; }.date-separator { margin: 0 3px; color: oklch(72% .025 253); }.number-column { text-align: center; }.assessment-cell { color: var(--accent-dark); font-weight: 800; }.assessment-cell span { color: var(--muted); font-weight: 600; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 700; }.status-badge.open { background: oklch(93% .06 145); color: oklch(42% .13 145); }.status-badge.closed { background: oklch(94% .012 253); color: var(--muted); }.status-badge i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }.row-actions { display: flex; justify-content: flex-end; gap: 6px; }.edit-button,.activate-button { min-height: 32px; padding: 5px 10px; }.edit-button { border: 1px solid oklch(83% .025 253); border-radius: 7px; background: var(--surface); color: var(--ink); font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }.edit-button:hover { border-color: oklch(70% .045 253); background: var(--canvas); }.activate-button { font-size: 12px; }
.readiness-badge { display: inline-flex; margin-left: 8px; border-radius: 999px; background: oklch(94% .045 29); padding: 3px 7px; color: oklch(49% .17 29); font-size: 10px; font-weight: 700; }.activate-button:disabled { border-color: var(--line); background: var(--canvas); color: var(--muted); cursor: not-allowed; }
.empty-state { display: grid; justify-items: center; border-top: 1px solid var(--line); padding: 54px 20px; text-align: center; }.empty-state > span { margin-bottom: 10px; color: var(--muted); font-size: 24px; }.empty-state strong { font-size: 14px; }.empty-state p { margin: 4px 0 14px; color: var(--muted); font-size: 12px; }.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
@media (max-width: 760px) { .page-heading { align-items: flex-start; }.page-heading h1 { font-size: 22px; }.page-heading p { max-width: 34ch; line-height: 1.5; }.create-button { min-height: 40px; padding: 8px 12px; white-space: nowrap; }.summary-grid { grid-template-columns: 1fr; gap: 12px; }.summary-card { min-height: 118px; padding: 20px; }.active-name { font-size: 19px; }.editor-grid { row-gap: 16px; padding: 18px; }.date-fields { grid-template-columns: 1fr; gap: 14px; }.date-hint { grid-column: 1; margin-bottom: -4px; }.editor-footer { align-items: stretch; flex-direction: column; }.editor-footer > div { justify-content: flex-end; }.round-table-card > header { padding: 16px 18px; } }
@media (max-width: 430px) { .page-heading { flex-direction: column; }.create-button { width: 100%; }.round-modal-backdrop { align-items: end; padding: 12px; }.round-editor { max-height: calc(100vh - 24px); border-radius: 14px 14px 10px 10px; }.editor-footer > div { display: grid; grid-template-columns: 1fr 1fr; }.empty-state .create-button { width: auto; } }
@keyframes modal-in { from { opacity: 0; transform: translateY(10px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
@media (prefers-reduced-motion: reduce) { .round-page *,.round-modal-backdrop * { scroll-behavior: auto!important; animation: none!important; transition-duration: .01ms!important; } }
</style>
