<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    module: { type: Object, default: () => ({ enabled: false, items: [] }) },
    compact: { type: Boolean, default: false },
});

const selected = ref(null);
const decision = ref('approve');
const comment = ref('');
const submitting = ref(false);
const detailTopic = ref(null);
const items = computed(() => props.module?.items || []);

const openDecision = (item, nextDecision) => {
    selected.value = item;
    decision.value = nextDecision;
    comment.value = '';
};

const closeDecision = () => {
    if (submitting.value) return;
    selected.value = null;
    comment.value = '';
};

const submitDecision = () => {
    if (!selected.value || (decision.value === 'reject' && !comment.value.trim())) return;

    submitting.value = true;
    router.post(route(decision.value === 'approve' ? 'fc-topic-selections.approve' : 'fc-topic-selections.reject'), {
        selection_id: selected.value.id,
        comment: comment.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            comment.value = '';
        },
        onFinish: () => { submitting.value = false; },
    });
};

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '-';
</script>

<template>
    <div class="approval-page">
        <header v-if="!compact" class="page-heading">
            <div>
                <span class="eyebrow">FC TOPIC REVIEW</span>
                <h1>อนุมัติหัวข้อการประเมิน</h1>
                <p>ตรวจสอบหัวข้อ FC ที่ส่งมาจากบุคลากรซึ่งคุณเป็นหัวหน้าลำดับแรก</p>
            </div>
            <div class="pending-count"><strong>{{ items.length }}</strong><span>รายการรออนุมัติ</span></div>
        </header>

        <section v-if="items.length" class="approval-list">
            <article v-for="item in items" :key="item.id" class="approval-card">
                <aside class="requester">
                    <div class="requester-main">
                        <div class="avatar">{{ item.employeeName?.[0] || 'บ' }}</div>
                        <div class="person-info">
                            <span class="request-label">ผู้ส่งคำขอ</span>
                            <h2>{{ item.employeeName }}</h2>
                            <p>{{ item.position }}</p>
                        </div>
                    </div>
                    <dl class="request-meta">
                        <div v-if="item.department && item.department !== '-'"><dt>{{ item.departmentLabel || 'หน่วยงาน/ภาควิชา' }}</dt><dd>{{ item.department }}</dd></div>
                        <div><dt>ส่งเมื่อ</dt><dd>{{ formatDate(item.submittedAt) }}</dd></div>
                    </dl>
                </aside>

                <div class="review-content">
                    <header class="review-head">
                        <div><span>หัวข้อที่เลือก</span><strong>{{ item.topics.length }} หัวข้อ</strong></div>
                        <span class="waiting-badge">รอการพิจารณา</span>
                    </header>
                    <div class="topic-list">
                        <div v-for="(topic, topicIndex) in item.topics" :key="topic.id" class="topic-row">
                            <span class="topic-index">{{ String(topicIndex + 1).padStart(2, '0') }}</span>
                            <span class="topic-code">{{ topic.code }}</span>
                            <strong>{{ topic.name }}</strong>
                            <button v-if="topic.detail" type="button" @click="detailTopic = topic">อ่านรายละเอียด</button>
                        </div>
                    </div>
                    <footer>
                        <span>ตรวจสอบความเหมาะสมของหัวข้อก่อนยืนยัน</span>
                        <div>
                            <button class="action secondary" type="button" @click="openDecision(item, 'reject')">ส่งกลับให้เลือกใหม่</button>
                            <button class="action primary" type="button" @click="openDecision(item, 'approve')">อนุมัติหัวข้อ</button>
                        </div>
                    </footer>
                </div>
            </article>
        </section>

        <section v-else class="empty-state">
            <div class="empty-icon">✓</div>
            <h2>ไม่มีหัวข้อรออนุมัติ</h2>
            <p>เมื่อบุคลากรที่คุณดูแลส่งหัวข้อ FC รายการจะปรากฏที่หน้านี้</p>
        </section>

        <div v-if="selected" class="modal-backdrop" @click.self="closeDecision">
            <section class="decision-modal" role="dialog" aria-modal="true">
                <header>
                    <div><span>{{ decision === 'approve' ? 'APPROVE' : 'RETURN' }}</span><h2>{{ decision === 'approve' ? 'ยืนยันอนุมัติหัวข้อ' : 'ส่งกลับให้เลือกหัวข้อใหม่' }}</h2></div>
                    <button type="button" @click="closeDecision">×</button>
                </header>
                <div class="modal-body">
                    <div class="modal-person"><strong>{{ selected.employeeName }}</strong><span>{{ selected.position }}</span></div>
                    <div class="modal-topics">
                        <div v-for="topic in selected.topics" :key="topic.id"><span>{{ topic.code }}</span><strong>{{ topic.name }}</strong></div>
                    </div>
                    <label for="approval-comment">{{ decision === 'reject' ? 'เหตุผลที่ส่งกลับ' : 'หมายเหตุเพิ่มเติม (ถ้ามี)' }}</label>
                    <textarea id="approval-comment" v-model="comment" :placeholder="decision === 'reject' ? 'ระบุเหตุผลเพื่อให้เลือกหัวข้อใหม่' : 'กรอกหมายเหตุเพิ่มเติม'" />
                </div>
                <footer>
                    <button class="action secondary" type="button" @click="closeDecision">ยกเลิก</button>
                    <button class="action primary" type="button" :disabled="submitting || (decision === 'reject' && !comment.trim())" @click="submitDecision">
                        {{ submitting ? 'กำลังบันทึก...' : (decision === 'approve' ? 'ยืนยันอนุมัติ' : 'ยืนยันส่งกลับ') }}
                    </button>
                </footer>
            </section>
        </div>

        <div v-if="detailTopic" class="modal-backdrop" @click.self="detailTopic = null">
            <section class="topic-detail-modal" role="dialog" aria-modal="true">
                <header>
                    <div>
                        <div class="detail-meta"><span>{{ detailTopic.type || 'FC' }}</span><strong>{{ detailTopic.code }}</strong></div>
                        <h2>{{ detailTopic.name }}</h2>
                    </div>
                    <button type="button" aria-label="ปิด" @click="detailTopic = null">×</button>
                </header>
                <div class="topic-detail-body">
                    <section class="detail-description">
                        <h3>คำอธิบายสมรรถนะ</h3>
                        <p>{{ detailTopic.detail || 'ยังไม่มีคำอธิบายสำหรับสมรรถนะนี้' }}</p>
                    </section>
                    <section v-if="detailTopic.levels?.length" class="detail-levels">
                        <h3>ระดับและพฤติกรรมบ่งชี้</h3>
                        <article v-for="level in detailTopic.levels" :key="level.id || level.lvl" class="detail-level-card">
                            <header>
                                <strong>{{ level.label || `ระดับที่ ${level.lvl}` }}</strong>
                                <span>{{ level.indicators?.length || 0 }} พฤติกรรมบ่งชี้</span>
                            </header>
                            <ul v-if="level.indicators?.length">
                                <li v-for="(indicator, index) in level.indicators" :key="index">
                                    <span aria-hidden="true"></span>
                                    <p>{{ indicator }}</p>
                                </li>
                            </ul>
                            <p v-else class="no-indicator">ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</p>
                        </article>
                    </section>
                </div>
                <footer><button class="action secondary" type="button" @click="detailTopic = null">ปิด</button></footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.approval-page { --ink: var(--color-text, #111827); --muted: var(--color-text-muted, #4b5563); --line: var(--color-border, #d1d5db); --soft: var(--color-page, #f8f9fa); --accent: var(--color-primary, #75292d); display: grid; gap: 20px; color: var(--ink); }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 4px 0 8px; }
.eyebrow { color: var(--muted); font-size: 11px; font-weight: 900; letter-spacing: .08em; }
.page-heading h1 { margin: 6px 0 3px; color: var(--ink); font-size: 25px; font-weight: 900; }
.page-heading p { margin: 0; color: var(--muted); font-size: 13px; }
.pending-count { display: grid; min-width: 116px; border: 1px solid var(--color-primary-border, #d8b8ba); border-radius: 8px; background: var(--color-primary-soft, #f8eeee); padding: 10px 14px; text-align: center; }
.pending-count strong { color: var(--accent); font-size: 22px; }.pending-count span { color: var(--color-text-secondary, #374151); font-size: 10px; font-weight: 800; }
.approval-list { display: grid; gap: 14px; }
.approval-card { display: grid; grid-template-columns: minmax(230px, 26%) 1fr; overflow: hidden; border: 1px solid var(--line); border-left: 4px solid var(--accent); border-radius: 8px; background: var(--color-surface, #fff); box-shadow: var(--sh, 0 1px 3px rgba(17,24,39,.08)); }
.requester { display: flex; flex-direction: column; justify-content: space-between; gap: 28px; border-right: 1px solid var(--line); background: var(--soft); padding: 22px; }
.requester-main { display: flex; align-items: flex-start; gap: 14px; }.avatar { display: grid; place-items: center; flex: 0 0 46px; width: 46px; height: 46px; border-radius: 50%; background: var(--color-text, #111827); color: #fff; font-size: 16px; font-weight: 900; }
.request-label { color: var(--accent); font-size: 11px; font-weight: 900; letter-spacing: .07em; }.person-info { min-width: 0; }.person-info h2 { margin: 4px 0 2px; font-size: 18px; line-height: 1.35; }.person-info p { margin: 0; color: var(--muted); font-size: 13px; }
.request-meta { display: grid; gap: 13px; margin: 0; }.request-meta div { display: grid; gap: 3px; }.request-meta dt { color: var(--muted); font-size: 12px; }.request-meta dd { overflow: hidden; margin: 0; font-size: 14px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.review-content { min-width: 0; }.review-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; }.review-head > div { display: flex; align-items: baseline; gap: 9px; }.review-head > div span { color: var(--muted); font-size: 12px; font-weight: 800; }.review-head > div strong { color: var(--ink); font-size: 15px; }.waiting-badge { border: 1px solid #f0d28a; border-radius: 999px; background: var(--color-warning-bg, #fef3c7); padding: 6px 10px; color: var(--color-warning, #92400e); font-size: 11px; font-weight: 900; }
.topic-list { border-block: 1px solid var(--line); }.topic-row { display: grid; grid-template-columns: 28px 76px minmax(0,1fr) auto; align-items: center; gap: 12px; min-height: 62px; padding: 11px 18px; }.topic-row + .topic-row { border-top: 1px solid var(--line); }.topic-index { color: var(--color-text-secondary, #374151); font-size: 11px; font-weight: 900; }.topic-code { color: var(--accent); font-size: 12px; font-weight: 900; }.topic-row strong { overflow: hidden; color: var(--ink); font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.topic-row button { border: 0; background: transparent; color: var(--accent); font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }.topic-row button:hover { color: var(--color-primary-hover, #5e2024); text-decoration: underline; }
.review-content > footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 18px; }.review-content > footer > span { color: var(--muted); font-size: 11px; }.review-content > footer > div { display: flex; gap: 8px; }
.decision-modal > footer { display: flex; justify-content: flex-end; gap: 9px; padding: 12px 18px; }
.action { border: 1px solid var(--line); border-radius: 7px; padding: 10px 16px; font-family: inherit; font-size: 12px; font-weight: 900; cursor: pointer; transition: background-color .18s ease, border-color .18s ease, transform .18s ease; }.action.primary { border-color: var(--accent); background: var(--accent); color: #fff; }.action.primary:hover:not(:disabled) { border-color: var(--color-primary-hover, #5e2024); background: var(--color-primary-hover, #5e2024); }.action.primary:active:not(:disabled) { border-color: var(--color-primary-active, #4a191c); background: var(--color-primary-active, #4a191c); }.action.secondary { background: var(--color-surface, #fff); color: var(--color-text-secondary, #374151); }.action:hover:not(:disabled) { transform: translateY(-1px); }.action:focus-visible, .topic-row button:focus-visible { outline: 3px solid var(--color-focus, #1d4ed8); outline-offset: 2px; }.action:disabled { border-color: var(--line); background: var(--color-disabled-bg, #e5e7eb); color: var(--color-disabled-text, #4b5563); cursor: not-allowed; opacity: 1; }
.empty-state { display: grid; justify-items: center; border: 1px dashed var(--line); border-radius: 8px; background: #fff; padding: 70px 24px; text-align: center; }.empty-icon { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 50%; background: var(--color-success-bg, #dcfce7); color: var(--color-success, #166534); font-size: 20px; font-weight: 900; }.empty-state h2 { margin: 14px 0 4px; color: var(--ink); font-size: 16px; }.empty-state p { margin: 0; color: var(--muted); font-size: 12px; }
.modal-backdrop { position: fixed; z-index: 1000; inset: 0; display: grid; place-items: center; background: rgba(14,18,22,.62); padding: 20px; }
.decision-modal { width: min(620px, 100%); overflow: hidden; border-radius: 8px; background: var(--color-surface, #fff); box-shadow: 0 24px 70px rgba(0,0,0,.25); }.decision-modal > header { display: flex; justify-content: space-between; border-bottom: 1px solid var(--line); padding: 18px 20px; }.decision-modal header span { color: var(--accent); font-size: 9px; font-weight: 900; letter-spacing: .08em; }.decision-modal h2 { margin: 4px 0 0; color: var(--ink); font-size: 18px; }.decision-modal header button, .topic-detail-modal header button { width: 34px; height: 34px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--color-text-secondary, #374151); font-size: 20px; cursor: pointer; }
.modal-body { display: grid; gap: 14px; padding: 18px 20px; }.modal-person { display: grid; gap: 2px; }.modal-person span { color: var(--muted); font-size: 11px; }.modal-topics { display: grid; gap: 7px; }.modal-topics div { display: flex; gap: 9px; border: 1px solid var(--line); border-radius: 8px; padding: 10px; }.modal-topics span { color: var(--accent); font-size: 10px; font-weight: 900; }.modal-topics strong { color: var(--ink); font-size: 11px; }.modal-body label { color: var(--color-text-secondary, #374151); font-size: 11px; font-weight: 900; }.modal-body textarea { min-height: 100px; resize: vertical; border: 1px solid var(--line); border-radius: 8px; padding: 11px; color: var(--ink); font: inherit; font-size: 12px; }
.decision-modal > footer { border-top: 1px solid #e5e8eb; background: #fafbfc; }
.topic-detail-modal { display: flex; flex-direction: column; width: min(760px, 100%); max-height: min(860px, calc(100vh - 40px)); overflow: hidden; border-radius: 8px; background: var(--color-surface, #fff); box-shadow: 0 24px 70px rgba(0,0,0,.25); }.topic-detail-modal > header { display: flex; flex: 0 0 auto; align-items: flex-start; justify-content: space-between; gap: 18px; border-bottom: 1px solid var(--line); padding: 20px 24px; }.detail-meta { display: flex; align-items: center; gap: 9px; }.detail-meta span { border-radius: 6px; background: var(--color-primary-soft, #f8eeee); padding: 5px 8px; color: var(--accent); font-size: 11px; font-weight: 900; }.detail-meta strong { color: var(--accent); font-size: 11px; }.topic-detail-modal h2 { max-width: 620px; margin: 9px 0 0; color: var(--ink); font-size: 19px; line-height: 1.4; }.topic-detail-body { display: grid; gap: 16px; overflow-y: auto; padding: 20px 24px; background: var(--soft); }.topic-detail-modal h3 { margin: 0 0 10px; color: var(--ink); font-size: 13px; }.detail-description { border: 1px solid var(--line); border-radius: 8px; background: var(--color-surface, #fff); padding: 16px 18px; }.detail-description p { max-width: 72ch; margin: 0; color: var(--color-text-secondary, #374151); font-size: 13px; line-height: 1.8; white-space: pre-line; }.detail-levels { display: grid; gap: 10px; }.detail-levels > h3 { margin-bottom: 2px; }.detail-level-card { overflow: hidden; border: 1px solid var(--line); border-radius: 8px; background: var(--color-surface, #fff); }.detail-level-card > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--line); background: var(--color-primary-soft, #f8eeee); padding: 12px 16px; }.detail-level-card > header strong { color: var(--accent); font-size: 13px; }.detail-level-card > header span { color: var(--muted); font-size: 10px; font-weight: 800; }.detail-level-card ul { display: grid; gap: 0; margin: 0; padding: 0; list-style: none; }.detail-level-card li { display: grid; grid-template-columns: 8px minmax(0,1fr); align-items: start; gap: 11px; padding: 11px 16px; }.detail-level-card li + li { border-top: 1px solid var(--line); }.detail-level-card li > span { width: 6px; height: 6px; margin-top: 7px; border-radius: 50%; background: var(--accent); }.detail-level-card li p { margin: 0; color: var(--color-text-secondary, #374151); font-size: 12px; line-height: 1.65; }.no-indicator { margin: 0; padding: 14px 16px; color: var(--muted); font-size: 12px; }.topic-detail-modal > footer { display: flex; flex: 0 0 auto; justify-content: flex-end; border-top: 1px solid var(--line); background: var(--soft); padding: 12px 20px; }
@media (max-width: 900px) { .page-heading { align-items: flex-start; }.pending-count { min-width: 90px; }.approval-card { grid-template-columns: 1fr; }.requester { gap: 14px; border-right: 0; border-bottom: 1px solid var(--line); }.request-meta { grid-template-columns: 1fr 1fr; }.topic-row { grid-template-columns: 24px 60px minmax(0,1fr); }.topic-row button { grid-column: 3; justify-self: start; }.review-content > footer { align-items: stretch; flex-direction: column; }.review-content > footer > div { display: grid; grid-template-columns: 1fr 1fr; }.action { width: 100%; } }
</style>
