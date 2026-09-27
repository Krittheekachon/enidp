<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { formDefinitions } from '../../idpFormDefinitions';

const props = defineProps({
    items: { type: Array, default: () => [] },
    mode: { type: String, default: 'tracking' },
    dialogOnly: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const page = usePage();
const isReviewMode = computed(() => props.mode === 'review');
const selectedId = ref(null);
const selectedActivityId = ref(null);
const showPlanDetails = ref(false);
const processing = ref(false);
const form = reactive({ operationStatus: '', operationReason: '', achievementStatus: '', achievementNote: '', comment: '' });
const formErrors = reactive({});
const previewUpdate = [{ publicId: 'preview-update', topicIndex: 0, periodStart: '2026-09-01', periodEnd: '2026-09-05', progressNote: 'ข้อมูลอัปเดตตัวอย่าง', status: 'submitted', submittedAt: '2026-09-05T09:30:00', evidences: [] }];
const previewActivity = (id, withUpdate = true) => [{
    id,
    name: 'การมอบหมายงานโครงการ / งานพิเศษ (Project Assignment)',
    methodLabel: 'Experiential Learning',
    formCode: 'form_3_project_assignment',
    startDate: '2026-09-01',
    endDate: '2026-09-30',
    planDetails: { planRows: [{ assignmentTopic: 'หัวข้อพัฒนาตัวอย่าง' }] },
    updates: withUpdate ? previewUpdate : [],
}];
const previewItem = (id, code, overrides = {}) => ({
    id,
    userId: 'status-preview',
    userName: 'ตัวอย่างสถานะ IDP',
    userPosition: 'ข้อมูลจำลอง · ไม่ได้บันทึกลงฐานข้อมูล',
    competencyCode: code,
    competencyName: 'สมรรถนะตัวอย่างสำหรับดูสถานะ',
    planStatus: 'approved',
    completionStatus: 'in_progress',
    completionResult: null,
    completionUpdatedAt: null,
    currentReviewStep: null,
    currentReviewerName: null,
    canReview: false,
    dueDate: '2026-09-30',
    isOverdue: false,
    daysOverdue: 0,
    goal: 'พัฒนาสมรรถนะให้ถึงระดับที่คาดหวัง',
    successCriteria: 'สามารถนำไปใช้ในการทำงานได้',
    activities: previewActivity(id * -10),
    reviewHistory: [],
    isStatusPreview: true,
    ...overrides,
});
const statusPreviewItems = [
    previewItem(-101, 'ST-01', { competencyName: 'ยังไม่มีการอัปเดต', activities: previewActivity(1010, false) }),
    previewItem(-102, 'ST-02', { competencyName: 'กำลังดำเนินการ' }),
    previewItem(-103, 'ST-03', { competencyName: 'รอคุณตรวจผล', completionStatus: 'review_step_1', currentReviewStep: 1, currentReviewerName: 'นายหัวหน้า หน่วย', submittedAt: '2026-09-06T09:00:00', canReview: true }),
    previewItem(-104, 'ST-04', { competencyName: 'รอหัวหน้าตรวจผล', completionStatus: 'review_step_1', currentReviewStep: 1, currentReviewerName: 'นายหัวหน้า หน่วย', submittedAt: '2026-09-06T09:00:00' }),
    previewItem(-105, 'ST-05', { competencyName: 'ถูกส่งกลับให้แก้ไข', completionStatus: 'revision_required', submittedAt: '2026-09-06T09:00:00', completionUpdatedAt: '2026-09-07T13:30:00' }),
    previewItem(-106, 'ST-06', { competencyName: 'อนุมัติผลแล้ว', completionStatus: 'approved', completionResult: 'passed', submittedAt: '2026-09-06T09:00:00', completionUpdatedAt: '2026-09-07T13:30:00' }),
    previewItem(-107, 'ST-07', { competencyName: 'ไม่ผ่านและต้องวางแผนใหม่', completionStatus: 'approved', completionResult: 'failed', submittedAt: '2026-09-06T09:00:00', completionUpdatedAt: '2026-09-07T13:30:00' }),
    previewItem(-108, 'ST-08', { competencyName: 'ล่าช้า', dueDate: '2026-09-10', isOverdue: true, daysOverdue: 9 }),
];
const trackingItems = computed(() => {
    if (isReviewMode.value || props.dialogOnly) return props.items;
    return [...props.items, ...statusPreviewItems];
});
const visibleItems = computed(() => {
    if (isReviewMode.value) {
        return props.items
            .filter((item) => item.canReview)
            .slice()
            .sort((a, b) => new Date(a.submittedAt || 0).getTime() - new Date(b.submittedAt || 0).getTime());
    }

    return trackingItems.value.slice().sort((left, right) => {
        if (Boolean(left.isOverdue) !== Boolean(right.isOverdue)) return left.isOverdue ? -1 : 1;
        return String(left.dueDate || '9999-12-31').localeCompare(String(right.dueDate || '9999-12-31'));
    });
});
const emptyCopy = computed(() => isReviewMode.value
    ? 'ยังไม่มีผลการพัฒนาที่รอคุณตรวจ'
    : 'ยังไม่มีข้อมูล IDP ของบุคลากรในสายที่ติดตาม');
const selectedItem = computed(() => trackingItems.value.find((item) => item.id === selectedId.value)
    || visibleItems.value.find((item) => item.id === selectedId.value)
    || null);
const selectedActivity = computed(() => {
    const activities = selectedItem.value?.activities || [];
    return activities.find((activity) => activity.id === selectedActivityId.value) || activities[0] || null;
});
const groupedItems = computed(() => {
    const groups = new Map();

    visibleItems.value.forEach((item) => {
        const key = String(item.userId || item.userName);
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                userName: item.userName,
                userPosition: item.userPosition,
                items: [],
            });
        }
        groups.get(key).items.push(item);
    });

    return [...groups.values()];
});
const firstError = computed(() => Object.values(page.props.errors || {})[0] || '');
const planRows = (activity) => activity.planDetails?.planRows?.length ? activity.planDetails.planRows : [{}];
const topicTitle = (activity, index) => {
    const row = planRows(activity)[index] || {};
    return row.assignmentTopic || row.skillTopic || row.topic || row.learningTopic || row.courseName || activity.name;
};
const topicFieldKeys = new Set(['assignmentTopic', 'skillTopic', 'topic', 'learningTopic', 'courseName']);
const hasPlanValue = (value) => Array.isArray(value)
    ? value.length > 0
    : value !== null && value !== undefined && String(value).trim() !== '';
const displayPlanValue = (field, value) => {
    if (Array.isArray(value)) return value.join(', ');
    if (field.type === 'date' || ['developmentStart', 'developmentEnd'].includes(field.key)) return formatDate(String(value));
    return String(value);
};
const activityPlanFields = (activity) => {
    const definition = formDefinitions[activity?.formCode];
    const detail = activity?.planDetails?.detail || {};

    return (definition?.detailFields || [])
        .filter((field) => !field.showWhen || detail[field.showWhen.key] === field.showWhen.value)
        .filter((field) => hasPlanValue(detail[field.key]))
        .map((field) => ({ key: field.key, label: field.label, value: displayPlanValue(field, detail[field.key]) }));
};
const rowPlanFields = (activity, row) => {
    const fields = formDefinitions[activity?.formCode]?.rowFields || [];
    const seen = new Set();

    return fields.reduce((entries, field) => {
        if (seen.has(field.key) || topicFieldKeys.has(field.key) || !hasPlanValue(row?.[field.key])) return entries;
        seen.add(field.key);
        entries.push({
            key: field.key,
            label: field.group || field.label,
            value: displayPlanValue(field, row[field.key]),
            wide: field.type === 'area' || ['developmentApproach', 'additionalDetails', 'courseDescription', 'assessmentTools'].includes(field.key),
        });
        return entries;
    }, []);
};
const updateSubmittedTime = (update) => {
    const timestamp = new Date(update.submittedAt || '').getTime();
    return Number.isNaN(timestamp) ? Number.MAX_SAFE_INTEGER : timestamp;
};
const updatesFor = (activity, topicIndex) => (activity.updates || [])
    .filter((update) => update.topicIndex === topicIndex)
    .slice()
    .sort((left, right) => updateSubmittedTime(left) - updateSubmittedTime(right));
const hasUpdates = (item) => (item.activities || []).some((activity) => (activity.updates || []).length > 0);
const hasViewablePlan = (item) => item.planStatus === 'approved' && (item.activities || []).length > 0;
const activityUpdateCount = (activity) => (activity.updates || []).length;
const activityTopicCount = (activity) => planRows(activity).length;
const operationResultLabel = (value) => value === 'as_planned'
    ? 'เป็นไปตามแผน'
    : value === 'not_as_planned'
        ? 'ไม่เป็นไปตามแผน'
        : '';
const achievementResultLabel = (value) => ({
    exceeded: 'บรรลุเกินเป้าหมาย',
    met: 'บรรลุตามเป้าหมาย',
    not_met: 'ไม่บรรลุผล ควรพัฒนาต่อ',
}[value] || '');
const completionFlowEvents = (item) => {
    const submissionVersion = Number(item.submissionVersion || 0);
    const showRound = submissionVersion > 1 || (item.reviewHistory || []).some((review) => Number(review.submissionVersion) > 1);
    const events = (item.reviewHistory || [])
        .map((review) => ({
            key: `${review.submissionVersion}-${review.reviewStep}-${review.decidedAt}`,
            type: review.decision === 'approved' ? 'approved' : 'rejected',
            title: (review.decision === 'approved'
                ? 'ผู้บังคับบัญชาอนุมัติ'
                : 'ผู้บังคับบัญชาส่งกลับให้แก้ไข') + (showRound ? ` · รอบที่ ${review.submissionVersion}` : ''),
            actor: review.reviewerName || 'ผู้บังคับบัญชา',
            detail: review.comment || '',
            operationResult: operationResultLabel(review.reviewData?.operationStatus),
            operationReason: review.reviewData?.operationReason || '',
            achievementResult: achievementResultLabel(review.reviewData?.achievementStatus),
            achievementNote: review.reviewData?.achievementNote || '',
            at: review.decidedAt,
            submissionVersion: Number(review.submissionVersion || 0),
            state: review.decision === 'approved' ? 'อนุมัติแล้ว' : 'ส่งกลับแก้ไข',
        }));

    const terminalType = item.completionStatus === 'approved'
        ? 'approved'
        : item.completionStatus === 'revision_required'
            ? 'rejected'
            : null;
    if (terminalType && !events.some((event) => event.type === terminalType && event.submissionVersion === submissionVersion)) {
        events.push({
            key: `fallback-${item.id}-${terminalType}`,
            type: terminalType,
            title: (terminalType === 'approved'
                ? 'ผู้บังคับบัญชาอนุมัติ'
                : 'ผู้บังคับบัญชาส่งกลับให้แก้ไข') + (showRound ? ` · รอบที่ ${submissionVersion}` : ''),
            actor: item.currentReviewerName || 'ผู้บังคับบัญชา',
            detail: '',
            operationResult: '',
            operationReason: '',
            achievementResult: '',
            achievementNote: '',
            at: item.completionUpdatedAt || null,
            submissionVersion,
            state: terminalType === 'approved' ? 'อนุมัติแล้ว' : 'ส่งกลับแก้ไข',
        });
    }

    return events.sort((left, right) => new Date(left.at || 0).getTime() - new Date(right.at || 0).getTime());
};
const pendingCompletionEvent = (item) => /^review_step_\d+$/.test(item.completionStatus || '')
    ? {
        key: `pending-${item.id}-${item.currentReviewStep || 1}`,
        type: 'pending',
        title: Number(item.submissionVersion || 0) > 1
            ? `รอผู้บังคับบัญชาตรวจสอบ · รอบที่ ${item.submissionVersion}`
            : 'รอผู้บังคับบัญชาตรวจสอบ',
        actor: item.currentReviewerName || 'ผู้บังคับบัญชา',
        detail: '',
        at: null,
    }
    : null;
const updateSequence = (activity, topicIndex, update) => updatesFor(activity, topicIndex)
    .findIndex((candidate) => candidate.publicId === update.publicId) + 1;
const timelineTimestamp = (value) => {
    const timestamp = new Date(value || '').getTime();
    return Number.isNaN(timestamp) ? Number.MAX_SAFE_INTEGER : timestamp;
};
const activityTimelineEntries = (activity, item) => {
    const entries = planRows(activity).flatMap((_row, topicIndex) => updatesFor(activity, topicIndex).map((update) => ({
        kind: 'update',
        key: `update-${update.publicId}`,
        at: update.submittedAt,
        priority: 0,
        topicIndex,
        update,
    })));

    if (item?.submittedAt) {
        entries.push({
            kind: 'submitted',
            key: `submitted-${item.submissionVersion}-${item.submittedAt}`,
            at: item.submittedAt,
            priority: 1,
            round: Number(item.submissionVersion || 0),
        });
    }
    completionFlowEvents(item).forEach((event) => entries.push({
        kind: 'review',
        key: `review-${event.key}`,
        at: event.at,
        priority: 2,
        event,
    }));
    const pending = pendingCompletionEvent(item);
    if (pending) entries.push({ kind: 'pending', key: pending.key, at: item.submittedAt, priority: 3, event: pending });

    return entries.sort((left, right) => timelineTimestamp(left.at) - timelineTimestamp(right.at) || left.priority - right.priority);
};
const planStatusLabel = (item) => {
    if (item.planStatus === 'approved') return 'แผนอนุมัติแล้ว';
    if (item.planStatus === 'revision_required') return 'แผนถูกส่งกลับให้แก้ไข';
    if (/^review_step_\d+$/.test(item.planStatus || '')) return 'แผนอยู่ระหว่างอนุมัติ';
    return 'ยังไม่ได้ส่งแผน';
};
const statusLabel = (item) => {
    if (item.completionStatus === 'approved' && item.completionResult === 'failed') return 'ไม่ผ่าน';
    if (item.completionStatus === 'approved') return 'อนุมัติผลแล้ว';
    if (item.completionStatus === 'revision_required') return 'ถูกส่งกลับให้แก้ไข';
    if (item.canReview) return 'รอคุณตรวจผล';
    if (/^review_step_\d+$/.test(item.completionStatus || '')) {
        return item.currentReviewerName
            ? `รอลำดับที่ ${item.currentReviewStep} · ${item.currentReviewerName} ตรวจผล`
            : `รอตรวจผลลำดับที่ ${item.currentReviewStep}`;
    }
    if (!hasUpdates(item)) return 'ยังไม่มีการอัปเดต';
    return 'กำลังดำเนินการ';
};
const statusTone = (item) => {
    if (item.completionStatus === 'approved' && item.completionResult === 'failed') return 'revision';
    if (item.completionStatus === 'approved') return 'done';
    if (item.completionStatus === 'revision_required') return 'revision';
    if (item.canReview) return 'awaiting';
    if (/^review_step_\d+$/.test(item.completionStatus || '')) return 'queued';
    if (!hasUpdates(item)) return 'empty';
    return 'active';
};
const detailButtonLabel = (item) => {
    if (!hasViewablePlan(item)) return 'ยังไม่มีแผนที่อนุมัติ';
    return isReviewMode.value && item.canReview ? 'ตรวจผล' : 'ดูรายละเอียด';
};
const open = (item) => {
    Object.assign(form, { operationStatus: '', operationReason: '', achievementStatus: '', achievementNote: '', comment: '' });
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
    selectedId.value = item.id;
    selectedActivityId.value = item.activities?.[0]?.id ?? null;
    showPlanDetails.value = false;
};
const selectActivity = (activityId) => {
    if (selectedActivityId.value !== activityId) showPlanDetails.value = false;
    selectedActivityId.value = activityId;
};
const close = () => {
    if (processing.value) return;
    selectedId.value = null;
    selectedActivityId.value = null;
    showPlanDetails.value = false;
    if (props.dialogOnly) emit('close');
};
watch(() => props.items, (items) => {
    if (!props.dialogOnly) return;
    const item = items?.[0];
    if (item) open(item);
    else selectedId.value = null;
}, { immediate: true });
watch(() => [form.operationStatus, form.operationReason, form.achievementStatus, form.achievementNote, form.comment], () => {
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
});
const validateDecision = (decision) => {
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
    if (decision === 'reject') {
        if (!form.comment.trim()) formErrors.comment = 'กรุณาระบุความคิดเห็นก่อนส่งกลับให้แก้ไข';
    } else {
        if (!form.operationStatus) formErrors.operationStatus = 'กรุณาเลือกผลการดำเนินงาน';
        if (!form.achievementStatus) formErrors.achievementStatus = 'กรุณาเลือกผลการพัฒนา';
        if (form.operationStatus === 'not_as_planned' && !form.operationReason.trim()) formErrors.operationReason = 'กรุณาระบุเหตุผลที่ไม่เป็นไปตามแผน';
        if (form.achievementStatus === 'not_met' && !form.achievementNote.trim()) formErrors.achievementNote = 'กรุณาระบุสิ่งที่ควรพัฒนาต่อ';
    }
    return Object.keys(formErrors).length === 0;
};
const submitDecision = (decision) => {
    if (!selectedItem.value?.completionPublicId) return;
    if (!validateDecision(decision)) return;
    processing.value = true;
    const reviewDetails = {
        completionPublicId: selectedItem.value.completionPublicId,
        operationStatus: form.operationStatus || null,
        operationReason: form.operationReason || null,
        achievementStatus: form.achievementStatus || null,
        achievementNote: form.achievementNote || null,
    };
    const payload = decision === 'approve'
        ? { ...reviewDetails, comment: form.comment || null }
        : { ...reviewDetails, comment: form.comment };
    router.post(route(`idp-completions.${decision}`), payload, {
        preserveScroll: true,
        onSuccess: () => {
            selectedId.value = null;
            selectedActivityId.value = null;
            showPlanDetails.value = false;
        },
        onError: (errors) => Object.assign(formErrors, errors),
        onFinish: () => { processing.value = false; },
    });
};
const formatDate = (value) => {
    if (!value) return 'ไม่ระบุ';
    const date = new Date(value.length > 10 ? value : `${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
};
const formatDateTime = (value) => {
    if (!value) return 'ไม่มีข้อมูลวันเวลาเดิม';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('th-TH', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};
</script>

<template>
    <section v-if="!dialogOnly" class="review-panel">
        <div v-if="!visibleItems.length" class="empty">{{ emptyCopy }}</div>
        <div v-else class="review-list">
            <section v-for="group in groupedItems" :key="group.key" class="person-group">
                <header class="person-heading">
                    <div class="person-identity">
                        <strong>{{ group.userName }}</strong>
                        <span>{{ group.userPosition || 'ไม่ระบุตำแหน่ง' }}</span>
                    </div>
                    <div class="person-heading-meta">
                        <span v-if="group.items.some((item) => item.isStatusPreview)" class="preview-badge">ข้อมูลจำลอง</span>
                        <span class="competency-total">{{ group.items.length }} สมรรถนะ</span>
                    </div>
                </header>

                <div class="competency-list">
                    <article v-for="item in group.items" :key="item.id" class="competency-row">
                        <div class="competency-summary">
                            <span>{{ item.competencyCode }}</span>
                            <h3>{{ item.competencyName }}</h3>
                            <small v-if="item.planStatus !== 'approved'">{{ planStatusLabel(item) }}</small>
                        </div>

                        <div class="activity-summary">
                            <span class="column-label">กิจกรรมในสมรรถนะนี้</span>
                            <ul v-if="item.activities.length">
                                <li v-for="activity in item.activities" :key="activity.id">
                                    <strong>{{ activity.name || 'ไม่ระบุชื่อกิจกรรม' }}</strong>
                                    <small v-if="activity.methodLabel">{{ activity.methodLabel }}</small>
                                </li>
                            </ul>
                            <p v-else>ยังไม่มีกิจกรรม</p>
                        </div>

                        <div class="competency-action">
                            <span v-if="item.isOverdue" class="schedule-status">ล่าช้า {{ item.daysOverdue }} วัน</span>
                            <span class="review-status" :class="statusTone(item)">{{ statusLabel(item) }}</span>
                            <small v-if="item.dueDate" class="due-date">ครบกำหนด {{ formatDate(item.dueDate) }}</small>
                            <button type="button" :disabled="!hasViewablePlan(item)" @click="open(item)">
                                {{ detailButtonLabel(item) }}
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </section>

    <div v-if="selectedItem" class="modal-backdrop" @click.self="close">
        <article class="review-dialog" :class="{ 'dialog-only': dialogOnly }" role="dialog" aria-modal="true" aria-labelledby="progress-dialog-title">
            <header class="dialog-header">
                <div class="dialog-heading">
                    <div class="dialog-person">
                        <strong>{{ selectedItem.userName }}</strong>
                        <span>{{ selectedItem.userPosition || 'ไม่ระบุตำแหน่ง' }}</span>
                    </div>
                    <div class="dialog-title-row">
                        <span class="dialog-code">{{ selectedItem.competencyCode }}</span>
                        <h2 id="progress-dialog-title">{{ selectedItem.competencyName }}</h2>
                    </div>
                </div>
                <button class="dialog-close" type="button" aria-label="ปิด" @click="close">×</button>
            </header>
            <nav v-if="dialogOnly && items.length > 1" class="dialog-competency-switcher" aria-label="เลือกสมรรถนะที่ต้องพัฒนา">
                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    :class="{ active: selectedItem?.id === item.id }"
                    :aria-pressed="selectedItem?.id === item.id"
                    @click="open(item)"
                >
                    <span>{{ item.competencyCode }}</span>
                    <strong>{{ item.competencyName }}</strong>
                </button>
            </nav>
            <div class="dialog-body">
                <div v-if="firstError" class="form-error">{{ firstError }}</div>
                <section class="plan-summary">
                    <div><span>เป้าหมายการพัฒนา</span><p>{{ selectedItem.goal || 'ไม่ระบุ' }}</p></div>
                    <div><span>ตัวชี้วัดผลสำเร็จ</span><p>{{ selectedItem.successCriteria || 'ไม่ระบุ' }}</p></div>
                </section>

                <section v-if="selectedItem.activities.length" class="activity-workspace">
                    <aside class="activity-nav" aria-label="รายการกิจกรรมพัฒนา">
                        <header>
                            <div><span>กิจกรรมพัฒนา</span><strong>{{ selectedItem.activities.length }} กิจกรรม</strong></div>
                            <small>เลือกเพื่อดูรายละเอียด</small>
                        </header>
                        <div class="activity-nav-list">
                            <button
                                v-for="(activity, activityIndex) in selectedItem.activities"
                                :key="activity.id"
                                type="button"
                                :class="{ active: selectedActivity?.id === activity.id }"
                                :aria-pressed="selectedActivity?.id === activity.id"
                                @click="selectActivity(activity.id)"
                            >
                                <span class="activity-number">{{ activityIndex + 1 }}</span>
                                <span class="activity-nav-copy">
                                    <strong>{{ activity.name || 'ไม่ระบุชื่อกิจกรรม' }}</strong>
                                    <small>{{ formatDate(activity.startDate) }} – {{ formatDate(activity.endDate) }}</small>
                                </span>
                                <span class="activity-count" :class="{ empty: !activityUpdateCount(activity) }">{{ activityUpdateCount(activity) }} อัปเดต</span>
                            </button>
                        </div>
                    </aside>

                    <main v-if="selectedActivity" class="activity-detail">
                        <header class="activity-detail-header">
                            <div>
                                <span>กิจกรรมที่เลือก</span>
                                <h3>{{ selectedActivity.name || 'ไม่ระบุชื่อกิจกรรม' }}</h3>
                                <small v-if="selectedActivity.methodLabel">{{ selectedActivity.methodLabel }}</small>
                            </div>
                            <div class="activity-period">
                                <span>ช่วงดำเนินการ</span>
                                <strong>{{ formatDate(selectedActivity.startDate) }} – {{ formatDate(selectedActivity.endDate) }}</strong>
                            </div>
                        </header>

                        <div class="activity-detail-meta">
                            <span>{{ activityTopicCount(selectedActivity) }} หัวข้อ</span>
                            <span>{{ activityUpdateCount(selectedActivity) }} รายการอัปเดต</span>
                            <button
                                class="plan-toggle"
                                type="button"
                                aria-haspopup="dialog"
                                @click="showPlanDetails = true"
                            >
                                ดูแผนที่กรอก
                                <span aria-hidden="true">›</span>
                            </button>
                        </div>

                        <section class="chronological-history" aria-label="ประวัติความก้าวหน้าและการตรวจสอบ">
                            <header>
                                <strong>ประวัติความก้าวหน้าและการตรวจสอบ</strong>
                                <span>เรียงตามวันและเวลาที่เกิดขึ้น</span>
                            </header>
                            <template v-for="entry in activityTimelineEntries(selectedActivity, selectedItem)" :key="entry.key">
                                <article v-if="entry.kind === 'update'" class="timeline-update-card">
                                    <div class="update-heading">
                                        <span>อัปเดตครั้งที่ {{ updateSequence(selectedActivity, entry.topicIndex, entry.update) }}</span>
                                        <time>{{ formatDate(entry.update.periodStart) }} – {{ formatDate(entry.update.periodEnd) }}</time>
                                    </div>
                                    <div v-if="planRows(selectedActivity).length > 1" class="timeline-topic-label">หัวข้อ {{ entry.topicIndex + 1 }} · {{ topicTitle(selectedActivity, entry.topicIndex) }}</div>
                                    <div class="update-content"><span>สิ่งที่ทำไปแล้ว</span><p>{{ entry.update.progressNote }}</p></div>
                                    <div v-if="entry.update.evidences.length" class="evidence-block">
                                        <span>หลักฐานประกอบ</span>
                                        <div class="evidences"><a v-for="evidence in entry.update.evidences" :key="evidence.publicId" :href="evidence.url" target="_blank" rel="noopener noreferrer">{{ evidence.kind === 'link' ? 'ลิงก์' : evidence.kind === 'image' ? 'รูปภาพ' : 'ไฟล์' }}: {{ evidence.name }}</a></div>
                                    </div>
                                    <div v-if="entry.update.submittedAt" class="update-submitted-at"><span>อัปเดตเมื่อ</span><time>{{ formatDateTime(entry.update.submittedAt) }}</time></div>
                                </article>
                                <div v-else-if="entry.kind === 'submitted'" class="completion-flow-line submitted">
                                    <div class="flow-line-copy"><strong>ส่งให้ผู้บังคับบัญชา<template v-if="entry.round > 1"> · รอบที่ {{ entry.round }}</template></strong><span>{{ selectedItem.userName }} · {{ formatDateTime(entry.at) }}</span></div>
                                </div>
                                <div v-else-if="entry.kind === 'review'" class="completion-flow-line" :class="entry.event.type">
                                    <div class="flow-line-copy">
                                        <strong>{{ entry.event.title }}</strong>
                                        <span>{{ entry.event.actor }} · {{ formatDateTime(entry.event.at) }}</span>
                                        <div class="flow-review-results"><span>ผลการดำเนินงาน <b>{{ entry.event.operationResult || 'ไม่ได้ระบุ' }}</b></span><span>ผลการพัฒนา <b>{{ entry.event.achievementResult || 'ไม่ได้ระบุ' }}</b></span></div>
                                        <small v-if="entry.event.operationReason">เหตุผลการดำเนินการ: {{ entry.event.operationReason }}</small>
                                        <small v-if="entry.event.achievementNote">สิ่งที่ควรพัฒนาต่อ: {{ entry.event.achievementNote }}</small>
                                        <small>ความคิดเห็น: {{ entry.event.detail || 'ไม่ได้ระบุ' }}</small>
                                    </div>
                                </div>
                                <div v-else class="completion-flow-line pending"><div class="flow-line-copy"><strong>{{ entry.event.title }}</strong><span>{{ entry.event.actor }}</span></div></div>
                            </template>
                            <div v-if="!activityTimelineEntries(selectedActivity, selectedItem).length" class="no-update"><span>ยังไม่มีประวัติ</span><small>บุคลากรยังไม่ได้ส่งความก้าวหน้าหรือส่งตรวจ</small></div>
                        </section>
                    </main>
                </section>

                <div v-else class="activities-empty">ยังไม่มีกิจกรรมในแผนพัฒนานี้</div>

                <section v-if="isReviewMode && selectedItem.canReview" class="evaluation-section">
                    <header>
                        <span>อนุมัติโดย {{ selectedItem.currentReviewerName || 'ผู้ตรวจสอบ' }}</span>
                        <h3>ผลการดำเนินการพัฒนาแผนพัฒนารายบุคคล</h3>
                    </header>
                    <div class="evaluation-grid">
                        <label><span>ผลการดำเนินงาน <b>*</b></span><select v-model="form.operationStatus"><option value="">เลือกผลการดำเนินงาน</option><option value="as_planned">เป็นไปตามแผน</option><option value="not_as_planned">ไม่เป็นไปตามแผน</option></select><small v-if="formErrors.operationStatus" class="field-error">{{ formErrors.operationStatus }}</small></label>
                        <label><span>ผลการพัฒนา <b>*</b></span><select v-model="form.achievementStatus"><option value="">เลือกผลการพัฒนา</option><option value="exceeded">บรรลุเกินเป้าหมาย</option><option value="met">บรรลุตามเป้าหมาย</option><option value="not_met">ไม่บรรลุผล ควรพัฒนาต่อ</option></select><small v-if="formErrors.achievementStatus" class="field-error">{{ formErrors.achievementStatus }}</small></label>
                        <label v-if="form.operationStatus === 'not_as_planned'" class="wide"><span>เหตุผลที่ไม่เป็นไปตามแผน <b>*</b></span><textarea v-model="form.operationReason" rows="3" /><small v-if="formErrors.operationReason" class="field-error">{{ formErrors.operationReason }}</small></label>
                        <label v-if="form.achievementStatus === 'not_met'" class="wide"><span>สิ่งที่ควรพัฒนาต่อ <b>*</b></span><textarea v-model="form.achievementNote" rows="3" /><small v-if="formErrors.achievementNote" class="field-error">{{ formErrors.achievementNote }}</small></label>
                        <label class="wide"><span>ความคิดเห็น</span><textarea v-model="form.comment" rows="3" /><small v-if="formErrors.comment" class="field-error">{{ formErrors.comment }}</small><small v-else class="field-help">จำเป็นเฉพาะเมื่อต้องการส่งกลับให้แก้ไข</small></label>
                        <p v-if="formErrors.completionPublicId" class="form-error wide">{{ formErrors.completionPublicId }}</p>
                    </div>
                </section>
            </div>
            <footer><button class="secondary" type="button" @click="close">ปิด</button><template v-if="isReviewMode && selectedItem.canReview"><button class="reject" type="button" :disabled="processing" @click="submitDecision('reject')">ส่งกลับให้แก้ไข</button><button class="approve" type="button" :disabled="processing" @click="submitDecision('approve')">อนุมัติผลการพัฒนา</button></template></footer>
        </article>

        <div v-if="showPlanDetails && selectedActivity" class="plan-modal-backdrop" @click.self="showPlanDetails = false">
            <article class="plan-dialog" role="dialog" aria-modal="true" aria-labelledby="activity-plan-dialog-title">
                <header class="plan-dialog-header">
                    <div>
                        <span>{{ selectedItem.competencyCode }} · แผนกิจกรรม</span>
                        <h2 id="activity-plan-dialog-title">แผนที่ผู้ใช้กรอก</h2>
                        <p>{{ selectedActivity.name || 'ไม่ระบุชื่อกิจกรรม' }}</p>
                    </div>
                    <button type="button" aria-label="ปิดแผนที่ผู้ใช้กรอก" @click="showPlanDetails = false">×</button>
                </header>

                <div class="plan-dialog-body">
                    <section class="plan-dialog-summary">
                        <div><span>เป้าหมายการพัฒนา</span><p>{{ selectedItem.goal || 'ไม่ระบุ' }}</p></div>
                        <div><span>ตัวชี้วัดผลสำเร็จ</span><p>{{ selectedItem.successCriteria || 'ไม่ระบุ' }}</p></div>
                        <div class="period"><span>ช่วงดำเนินการ</span><p>{{ formatDate(selectedActivity.startDate) }} – {{ formatDate(selectedActivity.endDate) }}</p></div>
                    </section>

                    <section v-if="activityPlanFields(selectedActivity).length" class="activity-plan-context-wrap">
                        <header>
                            <strong>ข้อมูลกิจกรรม</strong>
                            <small>ข้อมูลร่วมที่ผู้ใช้ระบุไว้</small>
                        </header>
                        <dl class="activity-plan-context">
                            <div v-for="field in activityPlanFields(selectedActivity)" :key="field.key">
                                <dt>{{ field.label }}</dt>
                                <dd>{{ field.value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="activity-plan-sheet">
                        <header>
                            <div>
                                <span>รายละเอียดแผน</span>
                                <strong>{{ formDefinitions[selectedActivity.formCode]?.rowTitle || 'รายละเอียดกิจกรรม' }}</strong>
                            </div>
                            <small>{{ activityTopicCount(selectedActivity) }} หัวข้อ</small>
                        </header>
                        <div class="activity-plan-rows">
                            <article v-for="(row, topicIndex) in planRows(selectedActivity)" :key="topicIndex">
                                <header>
                                    <span>หัวข้อ {{ topicIndex + 1 }}</span>
                                    <strong>{{ topicTitle(selectedActivity, topicIndex) }}</strong>
                                </header>
                                <dl v-if="rowPlanFields(selectedActivity, row).length" class="plan-field-grid">
                                    <div v-for="field in rowPlanFields(selectedActivity, row)" :key="field.key" :class="{ wide: field.wide }">
                                        <dt>{{ field.label }}</dt>
                                        <dd>{{ field.value }}</dd>
                                    </div>
                                </dl>
                                <p v-else class="no-plan-detail">ผู้ใช้ไม่ได้ระบุรายละเอียดอื่นเพิ่มเติมจากชื่อหัวข้อ</p>
                            </article>
                        </div>
                    </section>
                </div>

                <footer><button type="button" @click="showPlanDetails = false">ปิด</button></footer>
            </article>
        </div>
    </div>
</template>

<style scoped>
.review-panel { overflow: hidden; margin: 0; border: 0; background: #f3f7f5; }
.empty { padding: 22px; color: #78857f; font-size: 13px; text-align: center; }
.review-list { display: grid; gap: 16px; padding: 16px; }
.person-group { overflow: hidden; border: 1px solid #d8e3df; border-radius: 12px; background: #fff; box-shadow: 0 4px 14px rgba(26, 61, 50, .045); }
.person-group + .person-group { border-top-width: 1px; }
.person-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #dfe6e3; background: #eef5f2; padding: 14px 18px; }
.person-identity { display: grid; gap: 2px; }
.person-identity strong { color: #23352f; font-size: 16px; }
.person-identity span { color: #6f7e78; font-size: 12px; }
.person-heading-meta { display: flex; align-items: center; gap: 8px; }
.preview-badge { border: 1px dashed #d5aa55; border-radius: 18px; background: #fff8e8; padding: 5px 9px; color: #8b5b0d; font-size: 11px; font-weight: 900; }
.competency-total { border-radius: 18px; background: #fff; padding: 6px 10px; color: #246e5b; font-size: 12px; font-weight: 900; white-space: nowrap; }
.competency-list { display: grid; }
.competency-row { display: grid; grid-template-columns: minmax(230px, .85fr) minmax(320px, 1.45fr) 220px; align-items: start; gap: 24px; padding: 18px; }
.competency-row + .competency-row { border-top: 1px solid #e1e8e5; }
.competency-summary { display: grid; gap: 4px; }
.competency-summary > span { color: var(--color-primary); font-size: 12px; font-weight: 900; }
.competency-summary h3 { margin: 0; color: #26352f; font-size: 15px; line-height: 1.5; }
.competency-summary small { color: #5f776d; font-size: 11px; font-weight: 700; }
.activity-summary { display: grid; gap: 7px; }
.column-label { color: #74817c; font-size: 11px; font-weight: 800; }
.activity-summary ul { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
.activity-summary li { position: relative; display: grid; gap: 1px; padding-left: 14px; }
.activity-summary li::before { position: absolute; top: 8px; left: 1px; width: 5px; height: 5px; border-radius: 50%; background: #559080; content: ''; }
.activity-summary li strong { color: #34463f; font-size: 13px; line-height: 1.45; }
.activity-summary li small { color: #7a8782; font-size: 11px; }
.activity-summary p { margin: 0; color: var(--color-text-muted); font-size: 13px; }
.competency-action { display: flex; align-items: flex-end; flex-direction: column; gap: 9px; width: 100%; }
.schedule-status { border: 1px solid #efb8ae; border-radius: 20px; background: #fff0ed; padding: 6px 10px; color: #ad3929; font-size: 11px; font-weight: 900; white-space: nowrap; }
.due-date { color: #7a8782; font-size: 10px; font-weight: 700; white-space: nowrap; }
.competency-action button { min-width: 116px; border: 1px solid #8eb5a9; border-radius: 7px; background: #fff; padding: 8px 12px; color: #216b58; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
.competency-action button:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }
.competency-action button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
.competency-action button:disabled { border-color: #d7dfdc; background: #f5f7f6; color: #8a9590; cursor: not-allowed; }
.review-status { box-sizing: border-box; max-width: 100%; border: 1px solid transparent; border-radius: 20px; padding: 6px 10px; font-size: 11px; font-weight: 900; text-align: center; white-space: nowrap; }
.review-status.empty { border-color: #e0e5e3; background: #f1f3f2; color: #737f7a; }
.review-status.active { border-color: #cfe5de; background: #e8f4f0; color: #176b56; }
.review-status.awaiting { border-color: #f1d899; background: #fff3d8; color: #925b08; }
.review-status.queued { border-color: #cfdded; background: #edf4fb; color: #35638f; }
.review-status.revision { border-color: #f1c7c0; background: #fff0ed; color: #b13b2b; }
.review-status.done { border-color: #c4e8d1; background: #e2f6e9; color: #147745; }
.modal-backdrop {
    position: fixed;
    z-index: 1200;
    inset: 0;
    display: grid;
    place-items: center;
    background: rgba(18, 28, 25, .68);
    padding: 20px;
}
.review-dialog {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    width: min(1240px, 100%);
    height: min(920px, calc(100vh - 40px));
    height: min(920px, calc(100dvh - 40px));
    max-height: calc(100vh - 40px);
    max-height: calc(100dvh - 40px);
    overflow: hidden;
    border: 1px solid rgba(210, 222, 217, .9);
    border-radius: 16px;
    background: #f5f8f7;
    box-shadow: 0 32px 90px rgba(9, 25, 20, .32);
}
.review-dialog.dialog-only { grid-template-rows: auto auto minmax(0, 1fr) auto; }
.dialog-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    border-bottom: 1px solid #dbe4e1;
    background: #fff;
    padding: 20px 24px;
}
.dialog-heading { display: grid; gap: 12px; min-width: 0; }
.dialog-person { display: flex; align-items: center; gap: 10px; color: #65736d; font-size: 13px; }
.dialog-person strong { color: #235f50; font-size: 14px; }
.dialog-person span::before { margin-right: 10px; color: #b3beb9; content: '•'; }
.dialog-title-row { display: flex; align-items: center; gap: 12px; min-width: 0; }
.dialog-code { flex: none; border-radius: 7px; background: #e7f2ee; padding: 7px 10px; color: #176b56; font-size: 14px; font-weight: 900; letter-spacing: .02em; }
.dialog-title-row h2 { margin: 0; color: #22332d; font-size: 22px; line-height: 1.4; }
.dialog-close { flex: none; width: 40px; height: 40px; border: 1px solid #cbd7d2; border-radius: 9px; background: #fff; color: #53615c; font: inherit; font-size: 25px; line-height: 1; cursor: pointer; }
.dialog-close:hover { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
.dialog-close:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
.dialog-competency-switcher { display: flex; gap: 7px; overflow-x: auto; border-bottom: 1px solid #d8e2de; background: #eef5f2; padding: 9px 16px; scrollbar-gutter: stable; }
.dialog-competency-switcher button { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 7px; min-width: 190px; max-width: 320px; border: 1px solid #d4dfdb; border-radius: 8px; background: #fff; padding: 8px 10px; color: #44534d; font: inherit; text-align: left; cursor: pointer; }
.dialog-competency-switcher button:hover { border-color: #82ae9f; background: #f7fbf9; }
.dialog-competency-switcher button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
.dialog-competency-switcher button.active { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
.dialog-competency-switcher span { border-radius: 6px; background: var(--color-primary-soft); padding: 4px 6px; color: var(--color-primary); font-size: 10px; font-weight: 900; white-space: nowrap; }
.dialog-competency-switcher strong { overflow: hidden; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.dialog-body { min-height: 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 14px; padding: 16px; scrollbar-gutter: stable; -webkit-overflow-scrolling: touch; }
.dialog-body > * { flex: 0 0 auto; min-width: 0; }
.form-error { border: 1px solid #f0aaa0; border-radius: 8px; background: #fff3f1; padding: 11px 13px; color: #b93827; font-weight: 800; }
.plan-summary { display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; border: 1px solid #d9e2df; border-radius: 10px; background: #fff; }
.plan-summary div { padding: 13px 16px; }
.plan-summary div + div { border-left: 1px solid #dfe6e3; }
.plan-summary span { color: #6f7d77; font-size: 12px; font-weight: 800; }
.plan-summary p { margin: 5px 0 0; color: #2d3d37; font-size: 15px; line-height: 1.55; }
.activity-workspace { display: grid; grid-template-columns: 370px minmax(0, 1fr); align-items: stretch; min-height: 410px; overflow: hidden; border: 1px solid #d6e1dd; border-radius: 12px; background: #fff; }
.activity-nav { border-right: 1px solid #dbe4e1; background: #f7faf9; }
.activity-nav > header { display: grid; gap: 4px; border-bottom: 1px solid #dbe4e1; padding: 16px; }
.activity-nav > header div { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.activity-nav > header span { color: #263a33; font-size: 15px; font-weight: 900; }
.activity-nav > header strong { border-radius: 999px; background: #e4f1ec; padding: 4px 8px; color: #1c705b; font-size: 11px; }
.activity-nav > header small { color: #82908a; font-size: 12px; }
.activity-nav-list { display: grid; padding: 8px; }
.activity-nav-list button { display: grid; grid-template-columns: 30px minmax(0, 1fr) 62px; align-items: start; gap: 10px; width: 100%; min-height: 68px; border: 1px solid transparent; border-radius: 9px; background: transparent; padding: 12px 10px; color: inherit; font: inherit; text-align: left; cursor: pointer; }
.activity-nav-list button + button { margin-top: 3px; }
.activity-nav-list button:hover { background: #edf4f1; }
.activity-nav-list button.active { border-color: #8db9ad; background: #e2f1eb; }
.activity-nav-list button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 1px; }
.activity-number { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 8px; background: #e7eeeb; color: #586862; font-size: 13px; font-weight: 900; }
.activity-nav-list button.active .activity-number { background: var(--color-primary); color: #fff; }
.activity-nav-copy { display: grid; gap: 4px; min-width: 0; }
.activity-nav-copy strong { color: #2e4039; font-size: 13px; line-height: 1.45; overflow-wrap: anywhere; }
.activity-nav-copy small { color: #75837d; font-size: 11px; line-height: 1.4; }
.activity-count { align-self: start; margin-top: 6px; color: #23705d; font-size: 10px; font-weight: 900; text-align: right; white-space: nowrap; }
.activity-count.empty { color: var(--color-text-muted); }
.activity-detail { min-width: 0; background: #fff; }
.activity-detail-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; border-bottom: 1px solid #e0e7e4; padding: 17px 20px 14px; }
.activity-detail-header > div:first-child { display: grid; gap: 3px; min-width: 0; }
.activity-detail-header > div:first-child > span { color: var(--color-primary); font-size: 12px; font-weight: 900; }
.activity-detail-header h3 { margin: 0; color: #24362f; font-size: 19px; line-height: 1.45; }
.activity-detail-header small { color: #74817c; font-size: 12px; }
.activity-period { flex: none; display: grid; gap: 4px; border-left: 1px solid #dce5e2; padding-left: 18px; text-align: right; }
.activity-period span { color: #7b8883; font-size: 11px; }
.activity-period strong { color: #3e5049; font-size: 13px; }
.activity-detail-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 11px 20px 0; }
.activity-detail-meta > span { border-radius: 999px; background: #f0f4f2; padding: 5px 9px; color: #61716a; font-size: 11px; font-weight: 800; }
.plan-toggle { display: inline-flex; align-items: center; gap: 9px; margin-left: auto; border: 1px solid var(--color-primary); border-radius: 8px; background: var(--color-primary); padding: 9px 14px; color: #fff; box-shadow: 0 3px 9px rgba(117, 41, 45, .18); font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; transition: transform .18s ease-out, border-color .18s ease-out, background-color .18s ease-out, box-shadow .18s ease-out; }
.plan-toggle:hover { border-color: var(--color-primary-hover); background: var(--color-primary-hover); box-shadow: 0 5px 13px rgba(117, 41, 45, .22); transform: translateY(-1px); }
.plan-toggle:active { box-shadow: 0 2px 6px rgba(28, 95, 77, .18); transform: translateY(0); }
.plan-toggle:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 3px; }
.plan-toggle > span { display: inline-block; font-size: 17px; line-height: .8; transition: transform .18s ease-out; }
.plan-toggle:hover > span { transform: translateX(2px); }
.plan-modal-backdrop { position: fixed; z-index: 1300; inset: 0; display: grid; place-items: center; background: rgba(13, 24, 20, .64); padding: 28px; }
.plan-dialog { width: min(880px, 100%); height: min(900px, calc(100vh - 56px)); height: min(900px, calc(100dvh - 56px)); max-height: calc(100vh - 56px); overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; -webkit-overflow-scrolling: touch; border: 1px solid #cfdcd7; border-radius: 14px; background: #f5f8f7; box-shadow: 0 30px 85px rgba(8, 23, 18, .36); }
.plan-dialog-header { position: sticky; z-index: 2; top: 0; display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; border-bottom: 1px solid #d8e2de; background: #fff; padding: 18px 20px; }
.plan-dialog-header > div { display: grid; gap: 3px; min-width: 0; }
.plan-dialog-header span { color: var(--color-primary); font-size: 11px; font-weight: 900; }
.plan-dialog-header h2 { margin: 0; color: #24352f; font-size: 21px; }
.plan-dialog-header p { margin: 2px 0 0; color: #66756f; font-size: 14px; line-height: 1.5; }
.plan-dialog-header button { flex: none; width: 38px; height: 38px; border: 1px solid #cbd7d2; border-radius: 8px; background: #fff; color: #53615c; font: inherit; font-size: 23px; line-height: 1; cursor: pointer; }
.plan-dialog-header button:hover { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
.plan-dialog-body { display: grid; align-content: start; gap: 12px; padding: 14px; }
.plan-dialog-summary { display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; border: 1px solid #d8e2de; border-radius: 9px; background: #dfe7e4; gap: 1px; }
.plan-dialog-summary > div { min-width: 0; background: #fff; padding: 11px 13px; }
.plan-dialog-summary > div.period { grid-column: 1 / -1; background: #f8faf9; }
.plan-dialog-summary span { color: #77847f; font-size: 11px; font-weight: 800; }
.plan-dialog-summary p { margin: 4px 0 0; color: #2c3d37; font-size: 13px; line-height: 1.55; white-space: pre-wrap; }
.activity-plan-context-wrap { overflow: hidden; border: 1px solid #d5e2dd; border-radius: 9px; background: #fff; }
.activity-plan-context-wrap > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #dbe6e2; background: #f7faf9; padding: 9px 12px; }
.activity-plan-context-wrap > header strong { color: #2c3d37; font-size: 13px; }
.activity-plan-context-wrap > header small { color: #7a8782; font-size: 11px; }
.activity-plan-sheet { overflow: hidden; border: 1px solid #cfe0da; border-radius: 9px; background: #f7faf9; }
.activity-plan-sheet > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #dbe7e3; background: #eaf4f0; padding: 11px 13px; }
.activity-plan-sheet > header > div { display: grid; gap: 2px; }
.activity-plan-sheet > header span { color: var(--color-primary); font-size: 11px; font-weight: 900; }
.activity-plan-sheet > header strong { color: #2c3d37; font-size: 14px; }
.activity-plan-sheet > header small { color: #74817c; font-size: 11px; }
.activity-plan-context { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; margin: 0; border-bottom: 1px solid #dce8e4; background: #dce8e4; }
.activity-plan-context > div { min-width: 0; background: #f9fcfb; padding: 10px 12px; }
.activity-plan-rows { display: grid; }
.activity-plan-rows > article { padding: 12px; }
.activity-plan-rows > article + article { border-top: 1px solid #dce6e2; }
.activity-plan-rows > article > header { display: flex; align-items: baseline; gap: 9px; margin-bottom: 9px; }
.activity-plan-rows > article > header span { flex: none; color: var(--color-primary); font-size: 11px; font-weight: 900; }
.activity-plan-rows > article > header strong { color: #2c3d37; font-size: 13px; line-height: 1.5; }
.activity-plan-context dt, .plan-field-grid dt { color: #77847f; font-size: 11px; font-weight: 800; }
.activity-plan-context dd, .plan-field-grid dd { margin: 4px 0 0; color: #2c3e37; font-size: 13px; line-height: 1.55; overflow-wrap: anywhere; white-space: pre-wrap; }
.plan-dialog > footer { position: sticky; z-index: 2; bottom: 0; display: flex; justify-content: flex-end; border-top: 1px solid #d8e2de; background: #fff; padding: 11px 14px; }
.plan-dialog > footer button { border: 1px solid #9eb7af; border-radius: 8px; background: #fff; padding: 8px 15px; color: #40544c; font: inherit; font-size: 13px; font-weight: 900; cursor: pointer; }
.plan-dialog > footer button:hover { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
.topic-list { display: grid; gap: 12px; padding: 13px 20px 20px; }
.topic-section { overflow: hidden; border: 1px solid #dce5e2; border-radius: 10px; background: #fff; }
.topic-title { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 12px; border-bottom: 1px solid #e1e8e5; background: #fafcfb; padding: 11px 13px; }
.topic-title > span { border-radius: 6px; background: #e5f1ed; padding: 5px 8px; color: #1f725e; font-size: 11px; font-weight: 900; white-space: nowrap; }
.topic-title h4 { margin: 0; color: #2d3e38; font-size: 15px; line-height: 1.5; }
.topic-title > strong { border-radius: 20px; padding: 5px 8px; font-size: 12px; }
.plan-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 0; }
.plan-field-grid > div { min-width: 0; border: 1px solid #dce7e3; border-radius: 7px; background: #fff; padding: 9px 10px; }
.plan-field-grid > div.wide { grid-column: 1 / -1; }
.no-plan-detail { margin: 0; border-radius: 7px; background: #edf3f0; padding: 10px 11px; color: #74817c; font-size: 12px; }
.topic-progress { border-top: 1px solid #dfe8e4; padding-top: 12px; }
.topic-progress > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 12px 9px; }
.topic-progress > header span { color: #246e5b; font-size: 12px; font-weight: 900; }
.topic-progress > header small { color: #7b8883; font-size: 11px; }
.timeline { display: grid; gap: 10px; padding: 12px; }
.timeline > article { overflow: hidden; border: 1px solid #e0e7e4; border-radius: 9px; background: #fff; }
.chronological-history { display: grid; gap: 14px; padding: 16px 20px 24px; }
.chronological-history > header { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; }
.chronological-history > header strong { color: #2d3e38; font-size: 13px; }
.chronological-history > header span { color: #7b8883; font-size: 11px; }
.timeline-update-card { overflow: hidden; border: 1px solid #dce5e2; border-radius: 10px; background: #fff; }
.timeline-topic-label { border-bottom: 1px solid #e8eeeb; background: #f8fbfa; padding: 7px 12px; color: #507067; font-size: 11px; font-weight: 800; }
.chronological-history .completion-flow-line { margin: 5px 0; }
.update-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; border-bottom: 1px solid #e7ecea; background: #f7faf9; padding: 8px 11px; }
.update-heading span { color: #236d5a; font-size: 12px; font-weight: 900; }
.update-heading time { color: #66756f; font-size: 12px; font-weight: 700; }
.update-content { padding: 11px 12px; }
.update-content > span, .evidence-block > span { color: #6f7d77; font-size: 11px; font-weight: 800; }
.update-content p { margin: 5px 0 0; color: #283a33; font-size: 15px; line-height: 1.65; white-space: pre-wrap; }
.evidence-block { border-top: 1px solid #e8eeeb; padding: 9px 12px 11px; }
.evidences { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 7px; }
.evidences a { overflow: hidden; max-width: 100%; border: 1px solid #d3e5de; border-radius: 7px; background: #edf6f2; padding: 6px 9px; color: #176b56; font-size: 12px; font-weight: 800; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.evidences a:hover { border-color: #7eae9f; text-decoration: underline; }
.update-submitted-at { display: flex; align-items: center; justify-content: flex-end; gap: 6px; border-top: 1px solid #edf1ef; padding: 7px 11px; color: #7b8883; font-size: 10px; }
.update-submitted-at span { font-weight: 700; }
.update-submitted-at time { color: #56665f; font-weight: 800; }
.no-update { display: grid; gap: 3px; border: 1px dashed #ccd8d3; border-radius: 8px; background: #fbfcfc; padding: 25px 16px; text-align: center; }
.no-update span { color: #66756f; font-size: 14px; font-weight: 800; }
.no-update small { color: #8a9590; font-size: 12px; }
.activity-review-flow { display: grid; gap: 20px; margin: 8px 20px 26px; padding: 8px 4px 4px; }
.completion-flow-line { display: flex; align-items: flex-start; gap: 16px; color: oklch(48% .12 220); }
.completion-flow-line::before, .completion-flow-line::after { content: ''; min-width: 36px; height: 2px; flex: 1; margin-top: 9px; background: currentColor; opacity: .72; }
.completion-flow-line.pending { color: oklch(58% .12 82); }
.completion-flow-line.approved { color: oklch(50% .14 148); }
.completion-flow-line.rejected { color: oklch(53% .17 28); }
.flow-line-copy { display: grid; min-width: 220px; max-width: min(68%, 480px); flex: 0 1 auto; gap: 3px; padding: 0 4px; text-align: center; }
.flow-line-copy strong { color: currentColor; font-size: 13px; line-height: 1.4; }
.flow-line-copy span { color: oklch(50% .02 165); font-size: 11px; line-height: 1.45; }
.flow-line-copy small { margin-top: 2px; color: currentColor; font-size: 11px; line-height: 1.5; }
.flow-review-results { display: flex; flex-wrap: wrap; justify-content: center; gap: 5px 12px; margin-top: 3px; }
.flow-review-results span { color: oklch(43% .025 165); }
.flow-review-results b { color: currentColor; }
.post-review-updates { margin: 2px 20px 24px; border-top: 1px solid #dce6e2; padding-top: 15px; }
.post-review-updates > header { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 0 1px 5px; }
.post-review-updates > header strong { color: #2d3e38; font-size: 13px; }
.post-review-updates > header span { color: #7b8883; font-size: 11px; }
.post-review-topic-title { display: flex; align-items: baseline; gap: 9px; padding: 11px 12px 0; }
.post-review-topic-title span { color: var(--color-primary); font-size: 11px; font-weight: 900; }
.post-review-topic-title strong { color: #43554e; font-size: 12px; }
.activities-empty { border: 1px dashed #ccd8d3; border-radius: 10px; background: #fff; padding: 32px; color: #84908b; text-align: center; }
.evaluation-section { border: 1px solid #d8e2de; border-radius: 10px; background: #fff; padding: 14px; }
.evaluation-section { overflow: hidden; padding: 0; }
.evaluation-section > header { border-bottom: 1px solid #dfe6e3; padding: 12px 14px; }
.evaluation-section header span { color: #a2600a; font-size: 12px; font-weight: 900; }
.evaluation-section header h3 { margin: 3px 0 0; font-size: 16px; }
.evaluation-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 14px; }
.evaluation-grid label { display: grid; gap: 6px; color: #53615c; font-size: 13px; font-weight: 900; }
.evaluation-grid b { color: #bd3827; }
.evaluation-grid .wide { grid-column: 1 / -1; }
.field-help { color: #7a8782; font-size: 11px; font-weight: 700; }
.field-error, .form-error { margin: 0; color: #bd3827; font-size: 11px; font-weight: 800; }
select, textarea { width: 100%; box-sizing: border-box; border: 1px solid #cbd6d2; border-radius: 8px; background: #fff; padding: 10px 11px; color: #26332e; font: inherit; font-size: 14px; }
textarea { resize: vertical; }
.review-dialog > footer { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #d9e2df; background: #fff; padding: 13px 17px; }
.review-dialog footer button { border-radius: 8px; padding: 10px 13px; font: inherit; font-size: 14px; font-weight: 900; cursor: pointer; }
.secondary { border: 1px solid #cbd6d2; background: #fff; color: #53615c; }
.reject { border: 1px solid #e7afa7; background: #fff; color: #b93827; }
.approve { border: 1px solid var(--color-primary); background: var(--color-primary); color: #fff; }
.review-dialog footer button:disabled { opacity: .5; cursor: not-allowed; }
@media (max-width: 900px) {
    .competency-row { grid-template-columns: minmax(200px, .8fr) minmax(260px, 1.2fr); }
    .competency-action { grid-column: 1 / -1; align-items: center; flex-direction: row; justify-content: flex-end; }
    .plan-summary, .evaluation-grid { grid-template-columns: 1fr; }
    .plan-summary div + div { border-top: 1px solid #dfe6e3; border-left: 0; }
    .evaluation-grid .wide { grid-column: 1; }
    .activity-workspace { grid-template-columns: 1fr; }
    .activity-nav { border-right: 0; border-bottom: 1px solid #dbe4e1; }
    .activity-nav-list { grid-auto-columns: minmax(260px, 42%); grid-auto-flow: column; overflow-x: auto; }
    .activity-nav-list button + button { margin-top: 0; }
}
@media (max-width: 620px) {
    .review-list { gap: 10px; padding: 10px; }
    .competency-row { grid-template-columns: 1fr; gap: 14px; padding: 16px 14px; }
    .competency-action { grid-column: 1; justify-content: space-between; }
    .person-heading { padding: 13px 14px; }
    .modal-backdrop { padding: 0; }
    .review-dialog { width: 100%; height: 100vh; height: 100dvh; max-height: 100vh; max-height: 100dvh; border-radius: 0; }
    .dialog-header { padding: 15px; }
    .dialog-person { align-items: flex-start; flex-direction: column; gap: 1px; }
    .dialog-person span::before { display: none; }
    .dialog-title-row { align-items: flex-start; flex-direction: column; gap: 7px; }
    .dialog-title-row h2 { font-size: 18px; }
    .dialog-body { padding: 10px; }
    .activity-nav-list { grid-auto-columns: minmax(245px, 85%); }
    .activity-detail-header { align-items: stretch; flex-direction: column; gap: 12px; padding: 15px; }
    .activity-period { border-top: 1px solid #dce5e2; border-left: 0; padding-top: 10px; padding-left: 0; text-align: left; }
    .activity-detail-meta { padding-right: 15px; padding-left: 15px; }
    .activity-plan-context, .plan-field-grid { grid-template-columns: 1fr; }
    .plan-field-grid > div.wide { grid-column: 1; }
    .topic-list, .chronological-history { padding: 12px 15px 15px; }
    .activity-review-flow { gap: 18px; margin: 5px 15px 20px; padding-top: 7px; }
    .completion-flow-line { gap: 10px; }
    .completion-flow-line::before, .completion-flow-line::after { min-width: 18px; }
    .flow-line-copy { min-width: 0; max-width: 80%; }
    .topic-title { grid-template-columns: auto minmax(0, 1fr); }
    .topic-title > strong { grid-column: 2; justify-self: start; }
    .update-heading { align-items: flex-start; flex-direction: column; gap: 2px; }
    .plan-modal-backdrop { padding: 0; }
    .plan-dialog { width: 100%; height: 100vh; height: 100dvh; max-height: 100vh; max-height: 100dvh; border-radius: 0; }
    .plan-dialog-header { padding: 15px; }
    .plan-dialog-summary { grid-template-columns: 1fr; }
    .plan-dialog-summary > div.period { grid-column: 1; }
    .review-dialog > footer { display: grid; grid-template-columns: 1fr 1fr; }
    .review-dialog > footer .approve { grid-column: 1 / -1; }
}
</style>
