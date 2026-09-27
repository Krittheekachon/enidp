<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import IdpActivityFormReadonly from '../../Components/IdpActivityFormReadonly.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const comments = ref({});
const processingId = ref(null);
const selectedId = ref(null);
const selectedActivityId = ref(null);
const fetchedActivityDetails = ref({});
const activityDetailLoading = ref(false);
const activityDetailError = ref('');
const selectedUserKey = ref(null);
let previousBodyOverflow = '';
const userKey = (item) => String(item.userId || item.userSso || item.userName);
const approvalItems = computed(() => props.items);
const visibleGroups = computed(() => {
    const groups = new Map();

    approvalItems.value.filter((item) => item.canReview).forEach((item) => {
        const key = userKey(item);
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                userName: item.userName,
                userSso: item.userSso,
                userPosition: item.userPosition,
                userDepartment: item.userDepartment,
                items: [],
            });
        }
        groups.get(key).items.push(item);
    });

    return [...groups.values()].map((group) => ({ ...group, visibleItems: group.items }));
});
const selectedGroup = computed(() => visibleGroups.value.find((group) => group.key === selectedUserKey.value) || null);
const selectedItem = computed(() => selectedGroup.value?.items.find((item) => item.id === selectedId.value) || null);
const selectedActivity = computed(() => {
    const activity = selectedItem.value?.activities.find((entry) => entry.id === selectedActivityId.value);
    if (!activity) return null;
    return { ...activity, ...(fetchedActivityDetails.value[activity.id] || {}) };
});
const modalOpen = computed(() => selectedUserKey.value !== null);
const statusLabel = (item) => item.canReview ? `รอคุณอนุมัติ · ลำดับ ${item.currentReviewStep}`
    : item.status === 'approved' ? 'แผนอนุมัติครบแล้ว'
    : item.status === 'revision_required'
        ? `ถูกส่งกลับ${item.rejectReviewerName ? `โดย ${item.rejectReviewerName}` : 'ให้แก้ไข'}`
    : /^review_step_/.test(item.status)
        ? `รอการอนุมัติลำดับที่ ${item.currentReviewStep}${item.currentReviewerName ? ` · ${item.currentReviewerName}` : ''}`
        : 'ยังไม่ได้ส่งแผน';
const statusTone = (item) => item.status === 'approved' ? 'success'
    : item.status === 'revision_required' ? 'danger'
    : item.canReview ? 'pending'
    : /^review_step_/.test(item.status || '') ? 'info'
    : 'muted';
const groupStatusMeta = (group) => {
    const items = Array.isArray(group?.items) ? group.items : [];
    if (items.length === 0 || items.every((item) => !item.status || item.status === 'draft')) {
        return { label: 'ยังไม่ได้ส่งแผน', tone: 'muted' };
    }
    if (items.every((item) => item.status === 'approved')) {
        return { label: 'แผนอนุมัติครบแล้ว', tone: 'success' };
    }
    if (items.some((item) => item.canReview)) {
        return { label: 'รอคุณอนุมัติ', tone: 'pending' };
    }
    if (items.some((item) => item.status === 'revision_required')) {
        const rejectReviewerNames = [...new Set(items
            .filter((item) => item.status === 'revision_required')
            .map((item) => item.rejectReviewerName)
            .filter(Boolean))];
        return {
            label: rejectReviewerNames.length > 0
                ? `ถูกส่งกลับโดย ${rejectReviewerNames.join(', ')}`
                : 'ถูกส่งกลับให้แก้ไข',
            tone: 'danger',
        };
    }

    const hasNotSubmitted = items.some((item) => !item.status || item.status === 'draft');
    const hasStarted = items.some((item) => item.status && item.status !== 'draft');
    if (hasNotSubmitted && hasStarted) {
        return { label: 'อยู่ระหว่างดำเนินการ', tone: 'info' };
    }

    const waitingItems = items.filter((item) => /^review_step_/.test(item.status || ''));
    const reviewerNames = [...new Set(waitingItems.map((item) => item.currentReviewerName).filter(Boolean))];
    const reviewSteps = [...new Set(waitingItems.map((item) => Number(item.currentReviewStep)).filter((step) => step > 0))];

    if (reviewerNames.length === 1 && reviewSteps.length === 1) {
        return {
            label: `รอการอนุมัติลำดับที่ ${reviewSteps[0]} · ${reviewerNames[0]}`,
            tone: 'info',
        };
    }
    if (reviewerNames.length > 0) {
        return { label: `รอการอนุมัติ · ${reviewerNames.join(', ')}`, tone: 'info' };
    }

    return { label: 'อยู่ระหว่างการอนุมัติ', tone: 'info' };
};
const formatWeight = (value) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return '0';
    return number.toLocaleString('th-TH', { maximumFractionDigits: 2 });
};

const openEmployee = (group) => {
    selectedUserKey.value = group.key;
    selectedId.value = null;
    selectedActivityId.value = null;
};

const openItem = (itemId) => {
    selectedId.value = itemId;
    selectedActivityId.value = null;
};

const openActivity = async (activityId) => {
    selectedActivityId.value = activityId;
    activityDetailLoading.value = true;
    activityDetailError.value = '';

    try {
        const response = await fetch(`/idp-activities/${activityId}/review-detail`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const detail = await response.json();
        fetchedActivityDetails.value = {
            ...fetchedActivityDetails.value,
            [activityId]: detail,
        };
    } catch (error) {
        activityDetailError.value = 'โหลดรายละเอียดไม่สำเร็จ กรุณาลองใหม่';
    } finally {
        activityDetailLoading.value = false;
    }
};

const backOneLevel = () => {
    if (selectedActivityId.value !== null) {
        selectedActivityId.value = null;
        return;
    }
    selectedId.value = null;
};

const closeDetails = () => {
    selectedActivityId.value = null;
    selectedId.value = null;
    selectedUserKey.value = null;
};

const handleEscape = (event) => {
    if (event.key === 'Escape') closeDetails();
};

watch(modalOpen, (value) => {
    if (value) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = previousBodyOverflow;
    }
});

onMounted(() => window.addEventListener('keydown', handleEscape));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscape);
    document.body.style.overflow = previousBodyOverflow;
});

const approve = (item) => {
    if (!item.canReview) return;
    processingId.value = item.id;
    router.post(route('idp-items.approve'), {
        idpItemId: item.id,
        comment: String(comments.value[item.id] || '').trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: closeDetails,
        onFinish: () => { processingId.value = null; },
    });
};

const reject = (item) => {
    if (!item.canReview) return;
    const comment = String(comments.value[item.id] || '').trim();
    if (!comment) {
        window.alert('กรุณาระบุเหตุผลที่ส่งกลับ');
        return;
    }

    processingId.value = item.id;
    router.post(route('idp-items.reject'), {
        idpItemId: item.id,
        comment,
    }, {
        preserveScroll: true,
        onSuccess: closeDetails,
        onFinish: () => { processingId.value = null; },
    });
};
</script>

<template>
    <section class="approval-panel">
        <header>
            <div>
                <h2>รอคุณอนุมัติแผน</h2>
                <p>แผน IDP ที่มาถึงลำดับของคุณและสามารถตรวจอนุมัติได้ทันที</p>
            </div>
            <span>{{ visibleGroups.length }} คน</span>
        </header>

        <div v-if="visibleGroups.length === 0" class="approval-empty">ยังไม่มีแผนสมรรถนะที่รอคุณอนุมัติ</div>

        <div v-if="visibleGroups.length" class="approval-queue">
            <article v-for="group in visibleGroups" :key="group.key" class="queue-person-card">
                <div class="queue-person">
                    <strong>{{ group.userName }}</strong>
                    <span>{{ group.userPosition || group.userDepartment }}</span>
                </div>

                <div class="queue-plan-summary">
                    <span class="queue-column-label">แผนที่รอตรวจ</span>
                    <strong>{{ group.visibleItems.length }} สมรรถนะ · {{ group.visibleItems.reduce((total, item) => total + item.activities.length, 0) }} กิจกรรม</strong>
                    <div class="queue-competency-codes">
                        <span v-for="item in group.visibleItems" :key="item.id">{{ item.competencyCode }}</span>
                    </div>
                </div>

                <div class="queue-submitted">
                    <span class="queue-column-label">ส่งแผนล่าสุด</span>
                    <strong>{{ group.visibleItems[group.visibleItems.length - 1]?.submittedAt || '—' }}</strong>
                </div>

                <div class="queue-action">
                    <span class="queue-status" :class="`is-${groupStatusMeta(group).tone}`">{{ groupStatusMeta(group).label }}</span>
                    <button type="button" @click="openEmployee(group)">ตรวจแผน <span aria-hidden="true">›</span></button>
                </div>
            </article>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="selectedGroup" class="approval-modal-backdrop" @click.self="closeDetails">
        <article class="approval-card" :class="{ 'is-form-view': selectedActivity }" role="dialog" aria-modal="true" aria-labelledby="idp-approval-dialog-title">
            <div class="detail-heading" :class="{ 'activity-form-heading': selectedActivity }">
                <template v-if="selectedActivity">
                    <strong id="idp-approval-dialog-title">ดูรายละเอียดฟอร์มกิจกรรม (อ่านอย่างเดียว)</strong>
                    <button class="activity-form-close" type="button" aria-label="กลับไปยังแผนสมรรถนะ" @click="backOneLevel">×</button>
                </template>
                <template v-else>
                    <div>
                        <button v-if="selectedItem" class="back-button" type="button" @click="backOneLevel">← หัวข้อ IDP ทั้งหมด</button>
                    </div>
                    <button type="button" @click="closeDetails">ปิด</button>
                </template>
            </div>

            <template v-if="!selectedItem">
                <div class="employee-summary">
                    <div><span>{{ selectedGroup.userSso }}</span><strong>{{ selectedGroup.userName }}</strong><small>{{ selectedGroup.userPosition || selectedGroup.userDepartment }}</small></div>
                    <span>{{ selectedGroup.items.length }} หัวข้อ IDP</span>
                </div>
                <div class="topic-list">
                    <button
                        v-for="item in selectedGroup.items"
                        :key="item.id"
                        class="topic-row"
                        type="button"
                        @click="openItem(item.id)"
                    >
                        <span class="topic-code">{{ item.competencyCode }}</span>
                        <span class="topic-copy"><strong>{{ item.competencyName }}</strong><small>{{ item.activities.length }} กิจกรรม</small></span>
                        <span class="topic-status" :class="`is-${statusTone(item)}`">{{ statusLabel(item) }}</span>
                        <span class="topic-action">ตรวจสอบ ›</span>
                    </button>
                </div>
            </template>

            <template v-else-if="!selectedActivity">
            <div class="approval-title">
                <div>
                    <span>{{ selectedItem.userSso }}</span>
                    <strong>{{ selectedItem.userName }}</strong>
                    <small>{{ selectedItem.userPosition || selectedItem.userDepartment }}</small>
                </div>
                <div class="competency">
                    <span class="review-step">{{ statusLabel(selectedItem) }}</span>
                    <span>{{ selectedItem.competencyCode }}</span>
                    <strong>{{ selectedItem.competencyName }}</strong>
                </div>
            </div>

            <div class="plan-copy">
                <div><span>เป้าหมาย</span><p>{{ selectedItem.goal }}</p></div>
                <div><span>ตัวชี้วัดความสำเร็จ</span><p>{{ selectedItem.successCriteria }}</p></div>
            </div>

            <div class="activity-list">
                <button v-for="(activity, index) in selectedItem.activities" :key="activity.id" class="activity-row" type="button" @click="openActivity(activity.id)">
                    <b>{{ index + 1 }}</b>
                    <div>
                        <strong>{{ activity.name }}</strong>
                        <span>{{ activity.methodLabel }} · {{ activity.weightPercent }}%</span>
                        <small v-if="activity.documentReferenceNumber">เอกสาร {{ activity.documentReferenceNumber }}</small>
                    </div>
                    <span>{{ activity.startDate }} - {{ activity.endDate }}</span>
                    <em>ดูรายละเอียด ›</em>
                </button>
            </div>

            <textarea
                v-if="selectedItem.canReview"
                v-model="comments[selectedItem.id]"
                rows="2"
                placeholder="ความคิดเห็นเพิ่มเติม (ไม่บังคับสำหรับอนุมัติ)"
            />

            <details v-if="selectedItem.reviewHistory?.length" class="review-history">
                <summary>ประวัติการพิจารณา {{ selectedItem.reviewHistory.length }} รายการ</summary>
                <div
                    v-for="review in selectedItem.reviewHistory"
                    :key="`${review.submissionVersion}-${review.reviewStep}`"
                    class="history-row"
                >
                    <strong>
                        ครั้งที่ {{ review.submissionVersion }} · {{ review.reviewerName }}
                        <span
                            class="review-decision"
                            :class="review.decision === 'approved' ? 'is-approved' : 'is-returned'"
                        >
                            {{ review.decision === 'approved' ? 'อนุมัติ' : 'ส่งกลับ' }}
                        </span>
                    </strong>
                    <span>ผู้พิจารณาลำดับที่ {{ review.reviewStep }} · {{ review.decidedAt }}</span>
                    <p v-if="review.comment">{{ review.comment }}</p>
                </div>
            </details>

            <footer v-if="selectedItem.canReview">
                <button class="reject" type="button" :disabled="processingId === selectedItem.id" @click="reject(selectedItem)">ไม่อนุมัติ</button>
                <button class="approve" type="button" :disabled="processingId === selectedItem.id" @click="approve(selectedItem)">อนุมัติ</button>
            </footer>
            </template>

            <template v-else>
                <div class="activity-detail-head">
                    <div class="activity-detail-copy">
                        <div class="activity-detail-meta">
                            <span>{{ selectedItem.competencyCode }}</span>
                            <span v-if="selectedActivity.documentReferenceNumber" class="document-reference">เอกสาร {{ selectedActivity.documentReferenceNumber }}</span>
                        </div>
                        <strong>{{ selectedActivity.name }}</strong>
                    </div>
                    <div class="activity-facts">
                        <div class="activity-learning-type">
                            <small>ประเภทการเรียนรู้</small>
                            <strong>{{ selectedActivity.methodLabel || '—' }}</strong>
                        </div>
                        <div class="activity-weight" aria-label="สัดส่วนกิจกรรม">
                            <small>สัดส่วนกิจกรรม</small>
                            <div><strong>{{ formatWeight(selectedActivity.weightPercent) }}/100</strong><span>%</span></div>
                        </div>
                    </div>
                </div>

                <div v-if="activityDetailLoading" class="activity-detail-state">
                    <span class="loading-dot" />
                    <strong>กำลังโหลดรายละเอียดกิจกรรม...</strong>
                </div>

                <div v-else-if="activityDetailError" class="activity-detail-state is-error">
                    <strong>{{ activityDetailError }}</strong>
                    <button type="button" @click="openActivity(selectedActivity.id)">ลองใหม่</button>
                </div>

                <IdpActivityFormReadonly
                    v-if="!activityDetailLoading && !activityDetailError"
                    :activity="selectedActivity"
                    :employee="{ name: selectedItem.userName, sso: selectedItem.userSso, position: selectedItem.userPosition || selectedItem.userDepartment }"
                    :competency="{ code: selectedItem.competencyCode, name: selectedItem.competencyName, gap: selectedItem.gap }"
                />
            </template>
        </article>
        </div>
    </Teleport>
</template>

<style scoped>
.approval-panel { margin-bottom: 16px; border: 1px solid #d8e2de; border-radius: 10px; background: #fff; overflow: hidden; box-shadow: 0 3px 12px rgba(35, 67, 57, .04); }
.approval-panel > header { display: flex; justify-content: space-between; gap: 16px; padding: 16px 18px; border-bottom: 1px solid #e2e7ec; background: #f7faf9; }
.approval-queue { display: grid; gap: 12px; padding: 14px; background: #f3f7f5; }
.queue-person-card { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr) minmax(0, .7fr) minmax(0, 1fr); align-items: center; gap: 22px; border: 1px solid #d8e3df; border-radius: 10px; background: #fff; padding: 15px 16px; box-shadow: 0 3px 10px rgba(26, 61, 50, .035); }
.queue-person { display: grid; gap: 4px; min-width: 0; }
.queue-person strong { color: #263d35; font-size: 14px; }
.queue-person span { color: var(--color-text-muted); font-size: 11px; }
.queue-plan-summary { display: grid; gap: 5px; min-width: 0; }
.queue-plan-summary > strong, .queue-submitted strong { color: #34463f; font-size: 12px; font-weight: 800; }
.queue-column-label { color: #7a8782; font-size: 10px; font-weight: 800; }
.queue-competency-codes { display: flex; flex-wrap: wrap; gap: 5px; }
.queue-competency-codes span { border-radius: 5px; background: #e8f3ef; padding: 3px 7px; color: #1f705c; font-size: 10px; font-weight: 900; }
.queue-submitted { display: grid; gap: 5px; }
.queue-action { display: grid; min-width: 0; justify-items: end; gap: 8px; }
.queue-action button { display: inline-flex; align-items: center; gap: 8px; min-width: 108px; justify-content: center; border: 1px solid #8eb5a9; background: #fff; color: #216b58; }
.queue-action button:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }.queue-action button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
.queue-action button span { font-size: 17px; line-height: .8; }
.queue-status { display: inline-block; max-width: 100%; padding: 3px 8px; border-radius: 999px; font-size: 11px; line-height: 1.5; font-weight: 600; white-space: normal; overflow-wrap: anywhere; text-align: right; }
.queue-status.is-pending { background: #fff8e8; color: #a7650c; }
.queue-status.is-success { background: #eaf8f1; color: #14805e; }
.queue-status.is-danger { background: #fff0f0; color: #d43737; }
.queue-status.is-info { background: #eef5fb; color: #426789; }
.queue-status.is-muted { background: #f1f4f7; color: #667085; }
.detail-heading { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--color-primary-border); background: var(--color-primary-soft); color: var(--color-primary); font-size: 16px; }
.detail-heading > div { display: grid; gap: 3px; }
.detail-heading button { border: 1px solid var(--color-primary-border); background: #fff; color: var(--color-primary); }
.detail-heading .back-button { justify-self: start; border: 0; padding: 4px 0; background: transparent; color: #53687a; font-size: 16px; line-height: 1.35; }
.detail-heading .back-button:hover, .detail-heading .back-button:focus-visible { color: #185f50; text-decoration: underline; text-underline-offset: 3px; outline: none; }
.detail-heading.activity-form-heading { background: #fff; color: #172033; padding: 14px 18px; }
.activity-form-heading > strong { font-size: 16px; }
.detail-heading .activity-form-close { display: grid; place-items: center; width: 36px; height: 36px; border-color: #d8e0e9; padding: 0; color: #b42318; font-size: 24px; font-weight: 500; line-height: 1; }
@media (max-width: 900px) {
    .queue-person-card { grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr) minmax(0, 1fr); gap: 16px; }
    .queue-submitted { display: none; }
}
h2, p { margin: 0; }
h2 { font-size: 16px; }
header p { margin-top: 4px; color: #62756f; font-size: 12px; }
header > span { align-self: center; padding: 5px 9px; border-radius: 5px; background: #fff; color: #216b59; font-size: 12px; font-weight: 900; }
.approval-empty { padding: 22px; color: var(--color-text-muted); text-align: center; font-size: 13px; }
.approval-modal-backdrop { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 24px; background: rgba(17, 24, 39, .58); backdrop-filter: blur(2px); }
.approval-card { width: min(960px, 100%); max-height: calc(100vh - 48px); border: 1px solid #d4dde5; border-radius: 12px; background: #fff; overflow: auto; box-shadow: 0 24px 70px rgba(15, 23, 42, .28); }
.approval-card.is-form-view { width: min(1680px, 100%); background: #f8fafc; }
.employee-summary { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 18px 20px; border-bottom: 1px solid #e2e7ec; }
.employee-summary div > span, .employee-summary div > strong, .employee-summary div > small { display: block; }
.employee-summary div > span, .employee-summary div > small { color: var(--color-text-muted); font-size: 13px; }
.employee-summary div > strong { margin: 3px 0; color: #263d35; font-size: 15px; }
.employee-summary > span { padding: 6px 10px; border-radius: 999px; background: var(--color-primary-soft); color: var(--color-primary); font-size: 13px; font-weight: 900; white-space: nowrap; }
.topic-list { display: grid; gap: 10px; padding: 18px 20px 22px; }
.topic-row { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; gap: 13px; width: 100%; padding: 15px; border: 1px solid #dce4eb; border-radius: 8px; background: #fff; color: inherit; text-align: left; transition: border-color .15s ease, background .15s ease, transform .15s ease; }
.topic-row:hover, .topic-row:focus-visible { border-color: #8fc6b6; background: #f5faf8; transform: translateY(-1px); outline: none; }
.topic-code { padding: 6px 8px; border-radius: 6px; background: #fff0eb; color: #c9472c; font-size: 13px; font-weight: 900; }
.topic-copy { min-width: 0; }
.topic-copy strong, .topic-copy small { display: block; }
.topic-copy strong { overflow: hidden; color: #26352f; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.topic-copy small { margin-top: 5px; color: var(--color-text-muted); font-size: 12px; }
.topic-status { padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.topic-status.is-success { background: #eaf9f0; color: #159447; }
.topic-status.is-pending { background: #fff7df; color: #c57600; }
.topic-status.is-danger { background: #fff0ef; color: #d92d20; }
.topic-status.is-info { background: #eef5fb; color: #426789; }
.topic-status.is-muted { background: #f1f4f7; color: #667085; }
.topic-action { color: var(--color-primary); font-size: 13px; font-weight: 900; white-space: nowrap; }
.approval-title { display: flex; justify-content: space-between; gap: 18px; padding: 13px 15px; border-bottom: 1px solid #e2e7ec; }
.approval-title span, .approval-title strong, .approval-title small { display: block; }
.approval-title span { color: #68768a; font-size: 11px; }
.approval-title strong { margin-top: 3px; font-size: 13px; }
.approval-title small { margin-top: 3px; color: var(--color-text-muted); font-size: 11px; }
.competency { max-width: 55%; text-align: right; }
.competency span { color: var(--color-primary); font-weight: 900; }
.competency .review-step { margin-bottom: 4px; color: #8a5a08; }
.plan-copy { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 13px 15px; }
.plan-copy > div { padding: 10px; border: 1px solid #d9e3ec; border-radius: 6px; background: #f8fafc; }
.plan-copy span { color: #64748b; font-size: 11px; font-weight: 800; }
.plan-copy p { margin-top: 5px; font-size: 12px; line-height: 1.55; }
.activity-list { display: grid; gap: 7px; padding: 0 15px 13px; }
.activity-row { display: grid; grid-template-columns: 25px minmax(0, 1fr) auto auto; align-items: center; gap: 9px; padding: 10px; border: 1px solid #dce4eb; border-radius: 6px; background: #fff; color: inherit; text-align: left; font-weight: 400; transition: border-color .15s ease, background .15s ease; }
.activity-row:hover, .activity-row:focus-visible { border-color: #8fc6b6; background: #f5faf8; outline: none; }
.activity-row > b { display: grid; place-items: center; width: 25px; height: 25px; border-radius: 5px; background: var(--color-primary-soft); color: var(--color-primary); }
.activity-row strong, .activity-row span, .activity-row small { display: block; }
.activity-row strong { font-size: 12px; }
.activity-row span, .activity-row small { margin-top: 2px; color: var(--color-text-muted); font-size: 10px; }
.activity-row > em { color: var(--color-primary); font-size: 12px; font-style: normal; font-weight: 900; white-space: nowrap; }
.activity-detail-head { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 24px; padding: 20px 24px; border-bottom: 1px solid #e2e7ec; background: #fff; }
.activity-facts { display: flex; align-items: stretch; border: 1px solid #dce5e1; border-radius: 9px; background: #f8fbfa; }
.activity-learning-type, .activity-weight { display: grid; align-content: center; gap: 5px; padding: 10px 16px; text-align: right; }
.activity-learning-type { min-width: 156px; }
.activity-learning-type small, .activity-weight > small { color: var(--color-text-muted); font-size: 12px; font-weight: 800; }
.activity-learning-type strong { color: #344c44; font-size: 14px; white-space: nowrap; }
.activity-weight { min-width: 138px; border-left: 1px solid #dce5e1; color: #1f705d; }
.activity-weight > div { display: flex; align-items: baseline; justify-content: flex-end; gap: 5px; }
.activity-weight strong { font-size: 23px; line-height: 1; }
.activity-weight span { font-size: 15px; font-weight: 900; }
.activity-detail-copy { min-width: 0; }
.activity-detail-copy > strong, .activity-detail-copy > small { display: block; }
.activity-detail-copy > small { color: var(--color-text-muted); font-size: 13px; }
.activity-detail-copy > strong { margin: 5px 0; color: #26352f; font-size: 15px; }
.activity-detail-meta { display: flex; align-items: center; gap: 8px; color: var(--color-text-muted); font-size: 13px; }
.document-reference { padding: 3px 7px; border-radius: 5px; background: #f1f4f7; color: #667085; font-size: 12px; font-weight: 800; white-space: nowrap; }
.form-detail-section { padding: 18px 20px; border-bottom: 1px solid #e2e7ec; }
.form-detail-section:last-child { border-bottom: 0; }
.form-detail-section h3 { margin: 0 0 12px; color: #344054; font-size: 15px; }
.detail-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.detail-field-grid > div { min-width: 0; padding: 10px 12px; border: 1px solid #dce4eb; border-radius: 6px; background: #f8fafc; }
.detail-field-grid span, .detail-field-grid strong { display: block; }
.detail-field-grid span { color: var(--color-text-muted); font-size: 12px; }
.detail-field-grid strong { margin-top: 4px; color: #26352f; font-size: 13px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
.plan-row-list { display: grid; gap: 10px; }
.plan-row-card { overflow: hidden; border: 1px solid #dce4eb; border-radius: 8px; background: #fff; }
.plan-row-card > header { padding: 8px 11px; border-bottom: 1px solid var(--color-primary-border); background: var(--color-primary-soft); color: var(--color-primary); font-size: 12px; font-weight: 900; }
.plan-row-card .detail-field-grid { padding: 10px; }
.activity-detail-empty { padding: 22px; border: 1px dashed var(--color-border); border-radius: 7px; color: var(--color-text-muted); text-align: center; font-size: 13px; }
.activity-detail-state { display: flex; min-height: 150px; align-items: center; justify-content: center; gap: 10px; color: #64748b; font-size: 14px; }
.activity-detail-state.is-error { flex-direction: column; color: #b42318; }
.activity-detail-state.is-error button { border: 1px solid var(--color-border); background: #fff; color: var(--color-primary); }
.loading-dot { width: 14px; height: 14px; border: 2px solid var(--color-primary-border); border-top-color: var(--color-primary); border-radius: 50%; animation: activity-spin .7s linear infinite; }
@keyframes activity-spin { to { transform: rotate(360deg); } }
.form-note { margin-top: 10px; padding: 9px 11px; border-radius: 6px; background: #fff8e8; color: #8a5a08; font-size: 12px; }
textarea { width: calc(100% - 30px); margin: 0 15px 13px; border: 1px solid #d4dde6; border-radius: 6px; padding: 9px; box-sizing: border-box; font: inherit; font-size: 12px; resize: vertical; }
.review-history { margin: 0 15px 13px; border: 1px solid #d9e3ec; border-radius: 6px; background: #f8fafc; padding: 9px 11px; }
.review-history summary { color: #344054; font-size: 11px; font-weight: 900; cursor: pointer; }
.history-row { display: grid; gap: 3px; padding: 9px 0; border-top: 1px solid #e2e7ec; }
.history-row:first-of-type { margin-top: 8px; }
.history-row strong { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; font-size: 13px; }
.history-row span, .history-row p { margin: 0; color: var(--color-text-muted); font-size: 10px; }
.history-row .review-decision { display: inline-flex; align-items: center; min-height: 22px; padding: 2px 9px; border: 1px solid; border-radius: 999px; font-weight: 900; line-height: 1; }
.history-row .review-decision.is-approved { border-color: #abefc6; background: #ecfdf3; color: #067647; }
.history-row .review-decision.is-returned { border-color: #fecdca; background: #fef3f2; color: #b42318; }
footer { position: sticky; bottom: 0; z-index: 2; display: flex; justify-content: flex-end; gap: 8px; padding: 11px 15px; border-top: 1px solid #e2e7ec; background: #f8fafb; }
button { border-radius: 6px; padding: 8px 12px; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
button:disabled { opacity: .55; cursor: wait; }
.reject { border: 1px solid #efb8b8; background: #fff; color: #b42318; }
.approve { border: 1px solid var(--color-primary); background: var(--color-primary); color: #fff; }
@media (max-width: 900px) {
    .approval-modal-backdrop { align-items: end; padding: 12px; }
    .approval-card { max-height: calc(100vh - 24px); border-radius: 12px 12px 8px 8px; }
    .employee-summary { align-items: flex-start; padding: 15px; }
    .topic-list { padding: 14px; }
    .topic-row { grid-template-columns: auto minmax(0, 1fr); }
    .topic-status, .topic-action { grid-column: 2; justify-self: start; }
    .approval-title, .plan-copy { grid-template-columns: 1fr; flex-direction: column; }
    .competency { max-width: none; text-align: left; }
    .activity-row { grid-template-columns: 25px minmax(0, 1fr); }
    .activity-row > span, .activity-row > em { grid-column: 2; }
    .activity-detail-head { grid-template-columns: minmax(0, 1fr); gap: 14px; padding: 15px; }
    .activity-facts { justify-self: stretch; }
    .activity-learning-type, .activity-weight { min-width: 0; flex: 1; text-align: left; }
    .activity-weight > div { justify-content: flex-start; }
    .form-detail-section { padding: 15px; }
    .detail-field-grid { grid-template-columns: 1fr; }
}
@media (max-width: 620px) {
    .approval-queue { gap: 10px; padding: 10px; }
    .queue-person-card { grid-template-columns: 1fr; gap: 12px; padding: 14px; }
    .queue-plan-summary { border-top: 1px solid #e3eae7; padding-top: 11px; }
    .queue-action { grid-template-columns: 1fr auto; align-items: center; justify-items: start; }
    .queue-action button { justify-self: end; }
}
</style>
