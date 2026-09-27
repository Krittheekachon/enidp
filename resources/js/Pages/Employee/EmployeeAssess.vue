<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
  user: any;
  setUsers: any;
  competencies?: any[];
  fcTopicSelection?: any;
  blocked?: boolean;
  blockReasons?: string[];
}>();

const assignedCompetencies = computed(() => props.competencies || []);
const fcTopicSelection = computed(() => props.fcTopicSelection || {});
const requiredFcCount = computed(() => Number(fcTopicSelection.value.requiredCount || 0));
const fcSelectionRequired = computed(() => requiredFcCount.value > 0);
const fcSelectionStatus = computed(() => fcTopicSelection.value.status || (fcSelectionRequired.value ? 'draft' : 'not_required'));
const isFcSelectionApproved = computed(() => !fcSelectionRequired.value || fcSelectionStatus.value === 'approved');
const availableFcCompetencies = computed(() => fcTopicSelection.value.availableCompetencies || []);
const selectedFcIds = ref<number[]>([]);
const selectedFcDetail = ref<any | null>(null);
const isSubmittingFcSelection = ref(false);
const fcSelectionSubmitError = ref('');
const blockReasons = computed(() => props.blockReasons || []);
const reviewerSteps = computed(() => {
  if (Array.isArray(props.user?.reviewerSteps) && props.user.reviewerSteps.length) return props.user.reviewerSteps;
  return Array.isArray(props.user?.supervisorChain) ? props.user.supervisorChain : [];
});
const hasAssessmentReviewer = computed(() => reviewerSteps.value.length > 0);
const structureIssues = computed(() => blockReasons.value.filter((reason) => !/ผู้ประเมิน|หัวหน้า/.test(reason)));
const hasCompleteStructure = computed(() => structureIssues.value.length === 0);
const hasAssignedCompetencies = computed(() => assignedCompetencies.value.length > 0);
const readinessItems = computed(() => [
  {
    key: 'reviewer',
    label: 'หัวหน้าตามลำดับการอนุมัติ',
    ready: hasAssessmentReviewer.value,
    detail: hasAssessmentReviewer.value ? `กำหนดแล้ว ${reviewerSteps.value.length} ลำดับ` : 'ยังไม่ได้กำหนดหัวหน้า',
    owner: 'Admin',
  },
  {
    key: 'competencies',
    label: 'สมรรถนะประจำตำแหน่ง',
    ready: hasCompleteStructure.value && hasAssignedCompetencies.value,
    detail: !hasCompleteStructure.value
      ? structureIssues.value.join(' · ')
      : (hasAssignedCompetencies.value ? `มีหัวข้อสำหรับประเมิน ${assignedCompetencies.value.length} รายการ` : 'ยังไม่ได้กำหนดสมรรถนะให้ตำแหน่งนี้'),
    owner: 'HR',
  },
]);
const readinessCompletedCount = computed(() => readinessItems.value.filter((item) => item.ready).length);
const isBaseAssessmentReady = computed(() => readinessItems.value.every((item) => item.ready));
const fcSelectionBlockReason = computed(() => {
  if (!fcSelectionRequired.value || isFcSelectionApproved.value) return '';
  if (fcSelectionStatus.value === 'submitted') return 'ส่งหัวข้อ FC ให้หัวหน้าแล้ว รออนุมัติก่อนเริ่มประเมิน';
  if (fcSelectionStatus.value === 'revision_required') return 'หัวหน้าส่งหัวข้อ FC กลับให้เลือกใหม่ กรุณาเลือกและส่งอนุมัติอีกครั้ง';
  return `กรุณาเลือกหัวข้อ FC ${requiredFcCount.value} ข้อ และส่งให้หัวหน้าอนุมัติก่อนเริ่มประเมิน`;
});
const allBlockReasons = computed(() => [
  ...blockReasons.value,
  fcSelectionBlockReason.value,
].filter(Boolean));
const isAssessmentBlocked = computed(() => Boolean(props.blocked) || allBlockReasons.value.length > 0);
const selectedCompetency = ref<any | null>(null);
const checkedIndicators = ref<Record<string, boolean>>({});
const competencyNotes = ref<Record<string, string>>({});
const isSaving = ref(false);
const showSubmitConfirm = ref(false);
const lockedCompetencies = ref<Record<string, boolean>>({});
const competencyStatuses = ref<Record<string, string>>({});
const competencyDraftSavedAt = ref<Record<string, string>>({});
const competencyRejectComments = ref<Record<string, string>>({});
const competencyRejectReviewerNames = ref<Record<string, string>>({});
const autoSaveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const draftSavedAt = ref<string>('');
const suppressAutoSave = ref(false);
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

watch(fcTopicSelection, (next) => {
  selectedFcIds.value = [...(next?.selectedCompetencyIds || [])].map((id) => Number(id));
}, { immediate: true });

const fcSelectionStatusLabel = computed(() => {
  if (!fcSelectionRequired.value) return 'ไม่ต้องเลือก FC';
  if (fcSelectionStatus.value === 'approved') return 'อนุมัติหัวข้อแล้ว';
  if (fcSelectionStatus.value === 'submitted') return 'รอหัวหน้าอนุมัติหัวข้อ';
  if (fcSelectionStatus.value === 'revision_required') return 'ส่งกลับให้เลือกใหม่';
  return 'ยังไม่ส่งหัวข้อ';
});

const selectedFcCount = computed(() => selectedFcIds.value.length);
const isFcSelectionEditable = computed(() => ['draft', 'revision_required'].includes(fcSelectionStatus.value));
const canSubmitFcSelection = computed(() =>
  fcSelectionRequired.value
  && !isSubmittingFcSelection.value
  && isFcSelectionEditable.value
  && selectedFcCount.value === requiredFcCount.value,
);
const canClickSubmitFcSelection = computed(() =>
  fcSelectionRequired.value
  && !isSubmittingFcSelection.value
  && isFcSelectionEditable.value,
);
const fcSelectionSubmitHint = computed(() => {
  if (!fcSelectionRequired.value) return '';
  if (!isFcSelectionEditable.value) return 'รายการนี้ถูกส่งหรืออนุมัติแล้ว ไม่สามารถส่งซ้ำได้';
  if (selectedFcCount.value === requiredFcCount.value) return '';
  return `กรุณาเลือกหัวข้อ FC ให้ครบ ${requiredFcCount.value} ข้อก่อนส่งให้หัวหน้า`;
});

const toggleFcSelection = (id: number) => {
  if (!isFcSelectionEditable.value) return;

  const current = new Set(selectedFcIds.value);
  if (current.has(id)) {
    current.delete(id);
  } else {
    if (current.size >= requiredFcCount.value) return;
    current.add(id);
  }

  selectedFcIds.value = [...current];
  fcSelectionSubmitError.value = '';
};

const openFcDetail = (item: any) => {
  selectedFcDetail.value = item;
};

const closeFcDetail = () => {
  selectedFcDetail.value = null;
};

const submitFcSelection = () => {
  if (!canSubmitFcSelection.value) {
    fcSelectionSubmitError.value = fcSelectionSubmitHint.value;
    return;
  }

  isSubmittingFcSelection.value = true;
  fcSelectionSubmitError.value = '';
  router.post(route('employee.fc-topic-selection.submit'), {
    competency_ids: selectedFcIds.value,
  }, {
    preserveScroll: true,
    onError: (errors) => {
      fcSelectionSubmitError.value = String(errors.competency_ids || errors.supervisor || errors.position || errors.assessment || 'ส่งหัวข้อ FC ไม่สำเร็จ กรุณาตรวจสอบข้อมูลอีกครั้ง');
    },
    onFinish: () => {
      isSubmittingFcSelection.value = false;
    },
  });
};

const isLockedStatus = (status: string) => !['draft', 'revision_required'].includes(status);
const isFinalApprovedStatus = (status: string) => ['approved', 'dean_approved'].includes(status);
const reviewStepFromStatus = (status: string) => {
  if (status === 'self_submitted') return 1;
  if (status === 'unit_evaluated') return 2;
  if (status === 'dept_evaluated') return 3;
  const dynamicStep = status.match(/^review_step_(\d+)$/);
  return dynamicStep ? Number(dynamicStep[1]) : null;
};
const isReviewInProgressStatus = (status: string) => reviewStepFromStatus(status) !== null;
const reviewProgressLabel = (status: string) => {
  const step = reviewStepFromStatus(status);
  if (step === null) return '';
  const total = Math.max(reviewerSteps.value.length, ...reviewerSteps.value.map((item: any) => Number(item.step || 0)));
  return `รอการอนุมัติผลการประเมินจากหัวหน้าลำดับที่ ${step}${total > 0 ? ` จาก ${total}` : ''}`;
};
const competencyStatus = (item: any) => competencyStatuses.value[String(item?.id || '')] || item?.assessmentStatus || 'draft';
const isAssessmentFullyApproved = computed(() =>
  assignedCompetencies.value.length > 0
  && assignedCompetencies.value.every((item: any) => isFinalApprovedStatus(competencyStatus(item))),
);
const submittedAssessmentCount = computed(() =>
  assignedCompetencies.value.reduce((total: number, item: any) => {
    const status = competencyStatus(item);
    if (status === 'revision_required') return total - 1;
    if (status === 'self_submitted' || isFinalApprovedStatus(status) || isReviewInProgressStatus(status)) return total + 1;
    return total;
  }, 0),
);
const competencyStatusLabel = (item: any) => {
  const status = competencyStatus(item);
  if (isReviewInProgressStatus(status)) return reviewProgressLabel(status);
  if (isFinalApprovedStatus(status)) return 'อนุมัติครบทุกลำดับแล้ว';
  if (status === 'revision_required') return 'ถูกส่งกลับ · รอแก้ไขและส่งประเมินใหม่';
  return '';
};

const draftStatusText = (item: any) => {
  const status = competencyStatus(item);
  if (!['draft', 'revision_required'].includes(status)) return '';
  const value = competencyDraftSavedAt.value[String(item?.id || '')] || item?.lastDraftSavedAt || '';
  const date = value ? new Date(value) : null;
  if (!date || Number.isNaN(date.getTime())) return '';

  return `บันทึกฉบับร่างเมื่อ ${new Intl.DateTimeFormat('th-TH', {
    day: '2-digit',
    month: 'short',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)}`;
};

const formatDraftSavedAt = (value: string) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return new Intl.DateTimeFormat('th-TH', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

const autoSaveText = computed(() => {
  if (isSelectedCompetencyLocked.value) return '';
  if (autoSaveState.value === 'saving') return 'กำลังบันทึกฉบับร่าง...';
  if (autoSaveState.value === 'error') return 'บันทึกฉบับร่างไม่สำเร็จ';
  const time = formatDraftSavedAt(draftSavedAt.value);
  return time ? `บันทึกฉบับร่าง เมื่อ ${time}` : 'ยังไม่ได้บันทึกฉบับร่าง';
});

const clearAutoSaveTimer = () => {
  if (!autoSaveTimer) return;
  clearTimeout(autoSaveTimer);
  autoSaveTimer = null;
};

const checkedIndicatorsForCompetency = (competencyId: string | number) => {
  const prefix = `${competencyId}:`;
  return Object.fromEntries(
    Object.entries(checkedIndicators.value).filter(([key, checked]) => key.startsWith(prefix) && checked),
  );
};

const autosaveSignature = computed(() => {
  if (!selectedCompetency.value || isSelectedCompetencyLocked.value || isAssessmentBlocked.value) return '';
  const competencyId = selectedCompetency.value.id;

  return JSON.stringify({
    competencyId,
    checked: checkedIndicatorsForCompetency(competencyId),
    note: competencyNotes.value[noteKey.value] || '',
    score: currentScore.value,
  });
});

const saveDraft = async () => {
  if (!selectedCompetency.value || isSelectedCompetencyLocked.value || isAssessmentBlocked.value) return;

  autoSaveState.value = 'saving';

  try {
    const { data } = await (window as any).axios.post(route('assessments.draft'), {
      competency_id: selectedCompetency.value.id,
      checked_indicators: checkedIndicators.value,
      note: competencyNotes.value[noteKey.value] || '',
      score: parseFloat(currentScore.value),
    });

    draftSavedAt.value = data.lastDraftSavedAt || new Date().toISOString();
    competencyDraftSavedAt.value[String(selectedCompetency.value.id)] = draftSavedAt.value;
    selectedCompetency.value.lastDraftSavedAt = draftSavedAt.value;
    competencyStatuses.value[String(selectedCompetency.value.id)] = data.status || 'draft';
    autoSaveState.value = 'saved';
  } catch {
    autoSaveState.value = 'error';
  }
};

const openCompetencyDetail = async (item: any) => {
  if (isAssessmentBlocked.value) return;

  clearAutoSaveTimer();
  suppressAutoSave.value = true;
  autoSaveState.value = 'idle';
  draftSavedAt.value = '';
  selectedCompetency.value = item;
  competencyStatuses.value[String(item.id)] = item.assessmentStatus || 'draft';
  lockedCompetencies.value[String(item.id)] = isLockedStatus(competencyStatuses.value[String(item.id)]);

  try {
    const res = await fetch(
      route('assessments.load')
      + `?competency_id=${item.id}`
    );
    const data = await res.json();
    const prefix = `${item.id}:`;
    checkedIndicators.value = {
      ...Object.fromEntries(Object.entries(checkedIndicators.value).filter(([key]) => !key.startsWith(prefix))),
      ...(data.checked || {}),
    };
    competencyNotes.value[String(item.id)] = data.note || '';
    competencyStatuses.value[String(item.id)] = data.status || 'draft';
    lockedCompetencies.value[String(item.id)] = Boolean(data.locked);
    draftSavedAt.value = data.lastDraftSavedAt || '';
    competencyRejectComments.value[String(item.id)] = data.reject_comment || item.rejectComment || '';
    competencyRejectReviewerNames.value[String(item.id)] = data.reject_reviewer_name || item.rejectReviewerName || '';
    if (data.lastDraftSavedAt) {
      competencyDraftSavedAt.value[String(item.id)] = data.lastDraftSavedAt;
      selectedCompetency.value.lastDraftSavedAt = data.lastDraftSavedAt;
    }
    autoSaveState.value = data.lastDraftSavedAt ? 'saved' : 'idle';
  } catch {
    // ถ้า load ไม่ได้ ใช้ค่าเดิม
  } finally {
    await nextTick();
    suppressAutoSave.value = false;
  }
};

const closeCompetencyDetail = () => {
  clearAutoSaveTimer();
  showSubmitConfirm.value = false;
  selectedCompetency.value = null;
  autoSaveState.value = 'idle';
  draftSavedAt.value = '';
};

const openSubmitConfirm = () => {
  if (!selectedCompetency.value || isSaving.value || isAssessmentBlocked.value) return;
  if (isSelectedCompetencyLocked.value) return;
  showSubmitConfirm.value = true;
};

const cancelSubmitConfirm = () => {
  showSubmitConfirm.value = false;
};

const saveAndClose = async () => {
  if (!selectedCompetency.value || isSaving.value || isAssessmentBlocked.value || isSelectedCompetencyLocked.value) return;
  clearAutoSaveTimer();
  isSaving.value = true;
  showSubmitConfirm.value = false;

  router.post(route('assessments.save'), {
    competency_id: selectedCompetency.value.id,
    checked_indicators: checkedIndicators.value,
    note: competencyNotes.value[noteKey.value] || '',
    score: parseFloat(currentScore.value),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      competencyStatuses.value[String(selectedCompetency.value?.id || '')] = 'self_submitted';
      lockedCompetencies.value[String(selectedCompetency.value?.id || '')] = true;
      closeCompetencyDetail();
    },
    onError: () => {
      alert('ไม่สามารถบันทึกได้ กรุณาลองใหม่');
    },
    onFinish: () => {
      isSaving.value = false;
    },
  });
};

const indicatorKey = (level: any, index: number) => `${selectedCompetency.value?.id || 'item'}:${level.id || level.lvl}:${index}`;
const selectedCount = (level: any) => (level.indicators || []).filter((_: any, index: number) => checkedIndicators.value[indicatorKey(level, index)]).length;
const totalIndicators = computed(() => (selectedCompetency.value?.levels || []).reduce((total: number, level: any) => total + (level.indicators?.length || 0), 0));
const selectedIndicators = computed(() => (selectedCompetency.value?.levels || []).reduce((total: number, level: any) => total + selectedCount(level), 0));
const currentScore = computed(() => (selectedIndicators.value * 0.25).toFixed(2));
const noteKey = computed(() => String(selectedCompetency.value?.id || ''));
const isSelectedCompetencyLocked = computed(() => Boolean(lockedCompetencies.value[noteKey.value]));
const selectedCompetencyStatus = computed(() => competencyStatuses.value[noteKey.value] || selectedCompetency.value?.assessmentStatus || 'draft');
const isSelectedCompetencyApproved = computed(() => isFinalApprovedStatus(selectedCompetencyStatus.value));
const selectedCompetencyStatusCopy = computed(() => {
  if (isReviewInProgressStatus(selectedCompetencyStatus.value)) return reviewProgressLabel(selectedCompetencyStatus.value);
  if (isFinalApprovedStatus(selectedCompetencyStatus.value)) {
    return 'อนุมัติครบทุกลำดับแล้ว';
  }
  if (selectedCompetencyStatus.value === 'revision_required') return 'ถูกส่งกลับ · รอแก้ไขและส่งประเมินใหม่';
  return '';
});
const selectedRejectComment = computed(() => competencyRejectComments.value[noteKey.value] || selectedCompetency.value?.rejectComment || '');
const selectedRejectReviewerName = computed(() => competencyRejectReviewerNames.value[noteKey.value] || selectedCompetency.value?.rejectReviewerName || '');

const flattenedIndicators = computed(() => {
  const rows: any[] = [];
  (selectedCompetency.value?.levels || []).forEach((level: any, levelIndex: number) => {
    (level.indicators || []).forEach((indicator: string, indicatorIndex: number) => {
      rows.push({ level, levelIndex, indicator, indicatorIndex, key: indicatorKey(level, indicatorIndex) });
    });
  });
  return rows;
});

const isIndicatorUnlocked = (level: any, indicatorIndex: number) => {
  if (isSelectedCompetencyLocked.value) return false;
  const key = indicatorKey(level, indicatorIndex);
  const position = flattenedIndicators.value.findIndex((row) => row.key === key);
  if (position <= 0) return true;
  return Boolean(checkedIndicators.value[flattenedIndicators.value[position - 1].key]);
};

const handleIndicatorChange = (level: any, indicatorIndex: number) => {
  if (isSelectedCompetencyLocked.value) return;
  const key = indicatorKey(level, indicatorIndex);
  const position = flattenedIndicators.value.findIndex((row) => row.key === key);

  if (!checkedIndicators.value[key]) {
    flattenedIndicators.value.slice(position + 1).forEach((row) => {
      checkedIndicators.value[row.key] = false;
    });
  }
};

watch(autosaveSignature, (signature) => {
  clearAutoSaveTimer();
  if (!signature || suppressAutoSave.value) return;

  autoSaveTimer = setTimeout(() => {
    saveDraft();
  }, 1000);
});

onBeforeUnmount(() => {
  clearAutoSaveTimer();
});
</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>{{ fcSelectionRequired && !isFcSelectionApproved ? 'เริ่มกระบวนการประเมิน' : 'ประเมินตนเอง' }}</h1>
        <p>ดำเนินการตามขั้นตอนให้ครบ ก่อนส่งผลให้หัวหน้าตรวจสอบ</p>
      </div>
      <span class="b" :class="!isBaseAssessmentReady || isAssessmentBlocked ? 'br' : 'bgr'">
        {{ !isBaseAssessmentReady || isAssessmentBlocked ? 'ยังไม่พร้อมประเมิน' : 'พร้อมประเมิน' }}
      </span>
    </div>

    <div
      v-if="fcSelectionRequired && isBaseAssessmentReady"
      class="assessment-process"
      :class="{ complete: isAssessmentFullyApproved }"
    >
      <div class="process-content" :aria-hidden="isAssessmentFullyApproved">
        <div class="process-intro">
          <strong>ขั้นตอนก่อนเริ่มทำแบบประเมิน</strong>
          <span>ขณะนี้คุณอยู่ที่ขั้นตอน {{ isFcSelectionApproved ? '3' : (fcSelectionStatus === 'submitted' ? '2' : '1') }} จาก 3</span>
        </div>
        <ol class="process-steps">
          <li :class="{ active: ['draft', 'revision_required'].includes(fcSelectionStatus), done: ['submitted', 'approved'].includes(fcSelectionStatus) }">
            <span>1</span>
            <div><strong>เลือกหัวข้อ FC</strong><small>เลือก {{ requiredFcCount }} ข้อที่เหมาะสมกับงานของตน</small></div>
          </li>
          <li :class="{ active: fcSelectionStatus === 'submitted', done: fcSelectionStatus === 'approved' }">
            <span>2</span>
            <div><strong>รออนุมัติหัวข้อการประเมิน</strong><small>หัวหน้าตรวจหัวข้อที่เลือก</small></div>
          </li>
          <li :class="{ active: isFcSelectionApproved }">
            <span>3</span>
            <div><strong>ทำแบบประเมิน</strong><small>ประเมินสมรรถนะทีละหัวข้อ</small></div>
          </li>
        </ol>
      </div>
      <div v-if="isAssessmentFullyApproved" class="process-complete" role="status">
        <span class="process-complete-icon" aria-hidden="true">✓</span>
        <div>
          <strong>อนุมัติผลการประเมินแล้ว</strong>
          <small>เริ่มทำแผน IDP ได้</small>
        </div>
      </div>
    </div>

    <section v-if="hasAssessmentReviewer && isBaseAssessmentReady" class="review-route">
      <header>
        <div>
          <h2>ลำดับการประเมินของคุณ</h2>
        </div>
        <span>ทั้งหมด {{ reviewerSteps.length }} ลำดับ</span>
      </header>
      <div class="review-route-track">
        <div class="review-route-person employee">
          <span>คุณ</span>
          <div>
            <strong>{{ props.user?.t || '' }}{{ props.user?.n || props.user?.name || 'ผู้รับการประเมิน' }}</strong>
            <small>ประเมินตนเองและส่งผล</small>
          </div>
        </div>
        <template v-for="reviewer in reviewerSteps" :key="`${reviewer.step}-${reviewer.id}`">
          <span class="review-route-arrow" aria-hidden="true">→</span>
          <div
            class="review-route-person"
            :class="{ current: fcSelectionStatus === 'submitted' && Number(reviewer.step) === 1 }"
          >
            <span>{{ reviewer.step }}</span>
            <div>
              <strong>{{ reviewer.name }}</strong>
              <small>{{ reviewer.position || reviewer.label }}</small>
            </div>
          </div>
        </template>
      </div>
      <footer v-if="fcSelectionRequired && !isFcSelectionApproved">
        หัวข้อ FC จะถูกส่งให้หัวหน้าอนุมัติก่อนเริ่มทำแบบประเมิน
      </footer>
      <div class="review-route-help">
        <span aria-hidden="true">!</span>
        <p>หากข้อมูลลำดับการประเมินไม่ถูกต้อง โปรดติดต่อผู้ดูแลระบบ (Admin)</p>
      </div>
    </section>

    <section v-if="!isBaseAssessmentReady" class="readiness-panel">
      <header class="readiness-head">
        <div class="readiness-symbol" aria-hidden="true">!</div>
        <div>
          <div class="readiness-kicker">ASSESSMENT READINESS</div>
          <h2>ยังไม่สามารถเริ่มประเมินได้</h2>
          <p>ระบบกำลังรอข้อมูลจากผู้ดูแล เมื่อครบแล้วหน้านี้จะเปิดขั้นตอนประเมินให้อัตโนมัติ</p>
        </div>
        <div class="readiness-count">
          <strong>{{ readinessCompletedCount }}/{{ readinessItems.length }}</strong>
          <span>รายการพร้อม</span>
        </div>
      </header>

      <div class="readiness-list">
        <div v-for="item in readinessItems" :key="item.key" class="readiness-row" :class="{ ready: item.ready }">
          <span class="readiness-state" aria-hidden="true">{{ item.ready ? '✓' : '!' }}</span>
          <div>
            <strong>{{ item.label }}</strong>
            <small>{{ item.detail }}</small>
          </div>
          <span class="readiness-owner">{{ item.ready ? 'พร้อม' : `รอ ${item.owner}` }}</span>
        </div>
      </div>

      <footer class="readiness-footer">
        <div>
          <strong>ต้องดำเนินการอย่างไร?</strong>
          <span>ติดต่อ Admin หรือ HR พร้อมแจ้งชื่อและตำแหน่งของคุณ จากนั้นกลับมาตรวจสอบหน้านี้อีกครั้ง</span>
        </div>
      </footer>
    </section>

    <div v-if="fcSelectionRequired && isBaseAssessmentReady" class="fc-selection-card">
      <div class="fc-selection-head">
        <div>
          <div class="fc-kicker">ขั้นตอนที่ 1</div>
          <h2>{{ fcSelectionStatus === 'submitted' ? 'ส่งหัวข้อให้ผู้หัวหน้าตรวจสอบแล้ว' : (fcSelectionStatus === 'approved' ? 'หัวข้อ FC ได้รับอนุมัติแล้ว' : 'เลือกหัวข้อ FC ที่ตรงกับงานของคุณ') }}</h2>
          <p>{{ fcSelectionStatus === 'submitted' ? 'ระบบจะเปิดแบบประเมินทันทีหลังหัวหน้าอนุมัติ' : 'อ่านชื่อและรายละเอียด แล้วเลือกให้ครบตามจำนวนที่กำหนด' }}</p>
        </div>
        <span
          class="fc-status-pill"
          :class="{
            approved: fcSelectionStatus === 'approved',
            waiting: fcSelectionStatus === 'submitted',
            revision: fcSelectionStatus === 'revision_required',
          }"
        >
          {{ fcSelectionStatusLabel }}
        </span>
      </div>

      <div v-if="fcTopicSelection.reviewComment && fcSelectionStatus === 'revision_required'" class="fc-revision-note">
        <strong>เหตุผลที่ส่งกลับ:</strong>
        <span>{{ fcTopicSelection.reviewComment }}</span>
      </div>

      <div class="fc-selection-meter">
        <div>
          <strong>{{ selectedFcCount }}</strong>
          <span>/ {{ requiredFcCount }} หัวข้อที่เลือก</span>
        </div>
        <span>{{ selectedFcCount === requiredFcCount ? 'เลือกครบแล้ว พร้อมส่งอนุมัติ' : `เลือกเพิ่มอีก ${Math.max(requiredFcCount - selectedFcCount, 0)} หัวข้อ` }}</span>
      </div>

      <div class="fc-choice-list">
        <article
          v-for="item in availableFcCompetencies"
          :key="item.id"
          class="fc-choice"
          :class="{ selected: selectedFcIds.includes(Number(item.id)), locked: !isFcSelectionEditable }"
          role="button"
          tabindex="0"
          @click="toggleFcSelection(Number(item.id))"
          @keydown.enter.prevent="toggleFcSelection(Number(item.id))"
          @keydown.space.prevent="toggleFcSelection(Number(item.id))"
        >
          <div class="fc-choice-main">
            <span class="fc-choice-copy">
              <span class="fc-code">{{ item.cd }}</span>
              <strong>{{ item.n }}</strong>
              <button class="fc-detail-link" type="button" @click.stop="openFcDetail(item)" @keydown.stop>อ่านรายละเอียด</button>
            </span>
          </div>
          <button
            class="fc-choice-state"
            type="button"
            :disabled="!isFcSelectionEditable"
            @click.stop="toggleFcSelection(Number(item.id))"
            @keydown.stop
          >
            <span class="fc-check" aria-hidden="true">{{ selectedFcIds.includes(Number(item.id)) ? '✓' : '' }}</span>
            {{ selectedFcIds.includes(Number(item.id)) ? 'เลือกแล้ว' : 'เลือก' }}
          </button>
        </article>
      </div>

      <div v-if="!availableFcCompetencies.length" class="empty-card compact">
        <div class="empty-title">ยังไม่มี FC สำหรับตำแหน่งนี้</div>
        <div class="empty-copy">กรุณาให้ HR ผูก FC กับตำแหน่งก่อน</div>
      </div>

      <div class="fc-selection-actions">
        <div>
          <strong>{{ fcSelectionStatus === 'submitted' ? 'กำลังรอการอนุมัติ' : 'เมื่อส่งแล้วจะเปลี่ยนหัวข้อไม่ได้' }}</strong>
          <span>{{ fcSelectionStatus === 'submitted' ? 'กลับมาที่หน้านี้เพื่อตรวจสอบสถานะได้' : 'ตรวจสอบหัวข้อที่เลือกก่อนส่งให้หัวหน้า' }}</span>
          <span v-if="fcSelectionSubmitError" class="fc-submit-warning">{{ fcSelectionSubmitError }}</span>
        </div>
        <button
          class="btn btn-p"
          type="button"
          :disabled="!canClickSubmitFcSelection"
          :aria-disabled="!canSubmitFcSelection"
          @click="submitFcSelection"
        >
          {{ isSubmittingFcSelection ? 'กำลังส่ง...' : (fcSelectionStatus === 'submitted' ? 'ส่งหัวข้อแล้ว' : (fcSelectionStatus === 'approved' ? 'อนุมัติแล้ว' : 'ส่งหัวข้อให้หัวหน้า')) }}
        </button>
      </div>
    </div>

    <div v-if="selectedFcDetail" class="fc-detail-backdrop" @click.self="closeFcDetail">
      <section class="fc-detail-modal" role="dialog" aria-modal="true" aria-labelledby="fc-detail-title">
        <header>
          <div>
            <div class="fc-detail-meta">
              <span>{{ selectedFcDetail.t || 'FC' }}</span>
              <strong>{{ selectedFcDetail.cd }}</strong>
            </div>
            <h2 id="fc-detail-title">{{ selectedFcDetail.n }}</h2>
          </div>
          <button type="button" aria-label="ปิดหน้าต่าง" @click="closeFcDetail">×</button>
        </header>
        <div class="fc-detail-body">
          <section class="fc-detail-description">
            <h3>คำอธิบายสมรรถนะ</h3>
            <p>{{ selectedFcDetail.det || 'ยังไม่มีคำอธิบายสำหรับสมรรถนะนี้' }}</p>
          </section>
          <section v-if="selectedFcDetail.levels?.length" class="fc-detail-levels">
            <h3>ระดับและพฤติกรรมบ่งชี้</h3>
            <div v-for="level in selectedFcDetail.levels" :key="level.id || level.lvl" class="fc-detail-level">
              <div class="fc-detail-level-head">
                <strong>{{ level.label || `ระดับที่ ${level.lvl}` }}</strong>
                <span>{{ level.indicators?.length || 0 }} พฤติกรรมบ่งชี้</span>
              </div>
              <ul v-if="level.indicators?.length">
                <li v-for="(indicator, index) in level.indicators" :key="index">{{ indicator }}</li>
              </ul>
              <p v-else>ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</p>
            </div>
          </section>
        </div>
        <footer>
          <span>{{ selectedFcIds.includes(Number(selectedFcDetail.id)) ? 'หัวข้อนี้ถูกเลือกแล้ว' : 'เลือกหัวข้อนี้เพื่อส่งให้หัวหน้าอนุมัติ' }}</span>
          <div>
            <button class="btn btn-s" type="button" @click="closeFcDetail">ปิด</button>
            <button
              class="btn btn-p"
              type="button"
              :disabled="!isFcSelectionEditable"
              @click="toggleFcSelection(Number(selectedFcDetail.id))"
            >
              {{ selectedFcIds.includes(Number(selectedFcDetail.id)) ? 'ยกเลิกการเลือก' : 'เลือกหัวข้อนี้' }}
            </button>
          </div>
        </footer>
      </section>
    </div>

    <div v-if="assignedCompetencies.length && !isAssessmentBlocked && isBaseAssessmentReady" class="summary-grid">
      <div class="summary-card">
        <div class="summary-label">สมรรถนะที่ต้องประเมิน</div>
        <div class="summary-value">{{ assignedCompetencies.length }}</div>
        <div class="summary-copy">รายการ</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">ประเมินตนเองแล้ว</div>
        <div class="summary-value">{{ Math.max(0, submittedAssessmentCount) }}</div>
        <div class="summary-copy">รายการ</div>
      </div>
    </div>

    <div v-if="assignedCompetencies.length && !isAssessmentBlocked && isBaseAssessmentReady" class="content-card">
      <div class="card-head">
        <div>
          <h2>หัวข้อสมรรถนะที่ต้องประเมิน</h2>
          <p>รายการเหล่านี้มาจากการกำหนดของ HR</p>

        </div>
      </div>
      <div class="competency-list">
        <button
          v-for="item in assignedCompetencies"
          :key="item.id"
          class="competency-row"
          :class="{
            'status-approved': isFinalApprovedStatus(competencyStatus(item)),
            'status-pending': competencyStatus(item) === 'self_submitted' || isReviewInProgressStatus(competencyStatus(item)),
            'status-revision': competencyStatus(item) === 'revision_required',
            'status-draft': competencyStatus(item) === 'draft',
          }"
          type="button"
          @click="openCompetencyDetail(item)"
        >
          <div class="competency-title">
            <strong>{{ item.n }}</strong>
          </div>
          <div class="row-actions">
            <span v-if="draftStatusText(item)" class="draft-status">{{ draftStatusText(item) }}</span>
            <span
              v-if="competencyStatusLabel(item)"
              class="assessment-status"
              :class="{
                approved: isFinalApprovedStatus(competencyStatus(item)),
                pending: competencyStatus(item) === 'self_submitted' || isReviewInProgressStatus(competencyStatus(item)),
                revision: competencyStatus(item) === 'revision_required'
              }"
            >
              {{ competencyStatusLabel(item) }}
            </span>
            <span class="chevron-btn" aria-label="รายละเอียด">›</span>
          </div>
        </button>
      </div>
    </div>

    <div v-if="selectedCompetency" class="modal-backdrop" @click.self="closeCompetencyDetail">
      <div class="detail-modal">
        <div class="modal-head">
          <div>
            <div class="modal-code">
              <span class="type-tag">{{ selectedCompetency.t || '-' }}</span>
              <span>{{ selectedCompetency.cd }}</span>
            </div>
            <h2>{{ selectedCompetency.n }}</h2>
            <p>เลือกพฤติกรรมที่ทำได้จริงตามลำดับสะสม ระบบยังไม่แสดงคะแนน Gap ในขั้นนี้</p>
            <p v-if="isReviewInProgressStatus(selectedCompetencyStatus)" class="approved-copy">{{ selectedCompetencyStatusCopy }}</p>
            <p v-else-if="isSelectedCompetencyApproved" class="approved-copy">{{ selectedCompetencyStatusCopy }}</p>
            <p v-else-if="isSelectedCompetencyLocked" class="locked-copy">ผลการประเมินนี้ถูกส่งให้หัวหน้างานแล้ว จะแก้ไขได้เมื่อหัวหน้างานส่งกลับมาแก้ไข</p>
            <p v-else-if="selectedCompetencyStatus === 'revision_required'" class="revision-copy">{{ selectedCompetencyStatusCopy }}</p>
          </div>
          <button class="btn btn-s btn-sm" type="button" @click="closeCompetencyDetail">ปิด</button>
        </div>

        <div class="modal-body">
          <section class="competency-detail-card">
            <div class="detail-card-top">
              <div class="detail-card-meta">
                <span class="type-tag">{{ selectedCompetency.t || '-' }}</span>
                <span>{{ selectedCompetency.cd || '-' }}</span>
              </div>
              <span class="level-pill">ความคาดหวังคะแนนของคุณ : {{ selectedCompetency.expectedLevel || '-' }}</span>
            </div>
            <h3>{{ selectedCompetency.n }}</h3>
            <div class="competency-description-box">
              <div class="description-label">คำอธิบายสมรรถนะ</div>
              <p>{{ selectedCompetency.det || 'ไม่มีคำอธิบาย' }}</p>
            </div>
          </section>

          <div v-if="!selectedCompetency.levels?.length" class="empty-card compact">
            <div class="empty-title">ยังไม่มีรายละเอียดระดับ</div>
            <div class="empty-copy">เมื่อ Admin เพิ่มระดับและพฤติกรรม รายละเอียดจะแสดงที่นี่</div>
          </div>

          <section v-for="level in selectedCompetency.levels" v-else :key="level.id || level.lvl" class="emp-level-section">
            <div class="emp-level-head">
              <div>
                <h3>{{ level.label || `ระดับ ${level.lvl}` }}</h3>
                <p>เลือกแล้ว {{ selectedCount(level) }}/{{ level.indicators?.length || 0 }} พฤติกรรม</p>
              </div>
              <span
                class="emp-level-status"
                :class="{
                  idle: selectedCount(level) === 0,
                  done: selectedCount(level) === (level.indicators?.length || 0) && (level.indicators?.length || 0) > 0
                }"
              >
                {{
                  selectedCount(level) === 0
                    ? 'ยังไม่เริ่ม'
                    : selectedCount(level) === (level.indicators?.length || 0) && (level.indicators?.length || 0) > 0
                      ? 'ครบระดับ'
                      : 'กำลังประเมิน'
                }}
              </span>
            </div>

            <div class="emp-indicator-list">
              <label v-for="(indicator, index) in level.indicators" :key="index" class="emp-indicator-row">
                <input
                  v-model="checkedIndicators[indicatorKey(level, index)]"
                  :disabled="isSelectedCompetencyLocked || !isIndicatorUnlocked(level, index)"
                  type="checkbox"
                  @change="handleIndicatorChange(level, index)"
                />
                <span class="emp-indicator-copy">
                  <strong>ข้อ {{ level.lvl }}.{{ index + 1 }}</strong>
                  <small>{{ indicator }}</small>
                </span>

                <em
                  v-if="isIndicatorUnlocked(level, index) && !checkedIndicators[indicatorKey(level, index)]"
                  class="next-pill"
                >
                  ลำดับถัดไป
                </em>
              </label>

              <div v-if="!level.indicators?.length" class="emp-indicator-empty">
                ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้
              </div>
            </div>
          </section>

          <section v-if="selectedCompetencyStatus === 'revision_required' && selectedRejectComment" class="reviewer-comment-section">
            <div class="reviewer-comment-label">ความคิดเห็นจาก {{ selectedRejectReviewerName || 'ผู้ประเมิน' }}</div>
            <p>{{ selectedRejectComment }}</p>
          </section>

          <section v-if="selectedCompetency.levels?.length" class="emp-note-section">
            <label for="competency-note">ความคิดเห็นต่อสมรรถนะนี้</label>
            <textarea
              id="competency-note"
              v-model="competencyNotes[noteKey]"
              :disabled="isSelectedCompetencyLocked"
              placeholder="อธิบายเหตุผลประกอบการประเมินสมรรถนะนี้..."
            />
          </section>
        </div>

        <div class="modal-foot">
          <div class="assessment-save-meta">
            <span>คะแนนปัจจุบัน {{ currentScore }}/5.00 จาก {{ selectedIndicators }}/{{ totalIndicators }} พฤติกรรม</span>
            <small
              v-if="autoSaveText"
              class="autosave-status"
              :class="{ saving: autoSaveState === 'saving', saved: autoSaveState === 'saved', error: autoSaveState === 'error' }"
            >
              {{ autoSaveText }}
            </small>
          </div>
          <button class="btn btn-t" type="button" :disabled="isSaving || isSelectedCompetencyLocked" @click="openSubmitConfirm">
            {{ selectedCompetencyStatusCopy || (isSelectedCompetencyLocked ? 'ส่งแล้ว' : (isSaving ? 'กำลังบันทึก...' : 'บันทึกและส่งตรวจ')) }}
            <small>{{ selectedIndicators }}/{{ totalIndicators }}</small>
          </button>
        </div>
      </div>
    </div>

    <div v-if="showSubmitConfirm" class="confirm-backdrop" @click.self="cancelSubmitConfirm">
      <div class="confirm-modal">
        <div class="confirm-title">ยืนยันการส่งผลการประเมิน</div>
        <p>หากกดบันทึกและส่งตรวจจะเป็นการยืนยันผลการประเมินและส่งต่อไปยังหัวหน้างาน ต้องการยืนยันหรือไม่</p>
        <div class="confirm-actions">
          <button class="btn btn-s" type="button" @click="cancelSubmitConfirm">ยกเลิก</button>
          <button class="btn btn-t" type="button" :disabled="isSaving" @click="saveAndClose">ยืนยัน</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.employee-page { display: grid; gap: 20px; }
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-eyebrow { margin-bottom: 6px; color: var(--blue); font-size: 10px; font-weight: 900; letter-spacing: .12em; }
.page-head h1 { margin: 0; color: var(--text); font-size: 24px; font-weight: 900; }
.page-head p { margin: 7px 0 0; color: var(--text3); font-size: 12px; }
.readiness-panel { overflow: hidden; border: 1px solid #d9dee5; border-radius: 12px; background: #fbfcfd; box-shadow: 0 12px 32px rgba(21, 25, 29, .07); }
.readiness-head { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 15px; border-bottom: 1px solid #e2e6eb; padding: 20px 22px; }
.readiness-symbol { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; background: #f7ded7; color: #a63824; font-size: 17px; font-weight: 900; }
.readiness-kicker { margin-bottom: 4px; color: var(--blue); font-size: 10px; font-weight: 900; letter-spacing: .1em; }
.readiness-head h2 { margin: 0; color: var(--text); font-size: 18px; font-weight: 900; }
.readiness-head p { margin: 5px 0 0; color: var(--text3); font-size: 12px; }
.readiness-count { display: grid; min-width: 88px; border: 1px solid #e0e4e9; border-radius: 8px; background: #f3f5f7; padding: 9px 12px; text-align: center; }
.readiness-count strong { color: var(--text); font-size: 18px; font-weight: 900; }
.readiness-count span { color: var(--text3); font-size: 9px; font-weight: 800; }
.readiness-list { display: grid; padding: 8px 22px; }
.readiness-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 13px 0; }
.readiness-row + .readiness-row { border-top: 1px solid #e7eaee; }
.readiness-state { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #fff0eb; color: #ad3b26; font-size: 11px; font-weight: 900; }
.readiness-row.ready .readiness-state { background: #e3f1ec; color: #24624f; }
.readiness-row > div { display: grid; gap: 3px; min-width: 0; }
.readiness-row strong { color: var(--text); font-size: 12px; font-weight: 900; }
.readiness-row small { color: #778391; font-size: 11px; line-height: 1.45; }
.readiness-owner { border-radius: 999px; background: #fff0eb; color: #a63824; padding: 5px 9px; font-size: 10px; font-weight: 900; white-space: nowrap; }
.readiness-row.ready .readiness-owner { background: #e3f1ec; color: #24624f; }
.readiness-footer { border-top: 1px solid #e2e6eb; background: #f3f5f7; padding: 13px 22px; }
.readiness-footer > div { display: grid; gap: 3px; }
.readiness-footer strong { color: var(--text); font-size: 11px; font-weight: 900; }
.readiness-footer span { color: var(--text3); font-size: 10px; line-height: 1.5; }
.assessment-process {
  position: relative;
  overflow: hidden;
  border: 1px solid #d9dee5;
  border-radius: 12px;
  background: #fbfcfd;
  box-shadow: 0 8px 24px rgba(21, 25, 29, .05);
}
.process-content { transition: filter .2s ease, opacity .2s ease; }
.assessment-process.complete .process-content {
  filter: blur(3px);
  opacity: .34;
  pointer-events: none;
  user-select: none;
}
.process-complete {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 13px;
  background: rgba(248, 252, 250, .7);
  padding: 16px;
  text-align: left;
}
.process-complete-icon {
  display: grid;
  place-items: center;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: #2f735f;
  color: #fff;
  font-size: 20px;
  font-weight: 900;
}
.process-complete > div { display: grid; gap: 3px; }
.process-complete strong { color: #194f40; font-size: 16px; font-weight: 900; }
.process-complete small { color: #496c61; font-size: 11px; font-weight: 800; }
.process-intro { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 18px; border-bottom: 1px solid #e2e6eb; }
.process-intro strong { color: var(--text); font-size: 13px; font-weight: 900; }
.process-intro span { color: var(--text3); font-size: 11px; font-weight: 800; }
.process-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; padding: 0; list-style: none; }
.process-steps li { position: relative; display: flex; align-items: center; gap: 11px; min-width: 0; padding: 16px 18px; color: #8b96a5; }
.process-steps li + li { border-left: 1px solid #e2e6eb; }
.process-steps li > span { display: grid; place-items: center; flex: 0 0 28px; width: 28px; height: 28px; border: 1px solid #d8dee6; border-radius: 50%; background: #f1f3f6; color: #7d8794; font-size: 11px; font-weight: 900; }
.process-steps li div { display: grid; gap: 2px; min-width: 0; }
.process-steps li strong { color: #667180; font-size: 12px; font-weight: 900; }
.process-steps li small { overflow: hidden; color: #929cab; font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.process-steps li.active { background: #fff5f1; }
.process-steps li.active > span { border-color: var(--blue); background: var(--blue); color: #fffaf8; }
.process-steps li.active strong { color: #a63824; }
.process-steps li.done > span { border-color: #2f735f; background: #e5f1ed; color: #24624f; }
.process-steps li.done > span::after { content: '✓'; }
.process-steps li.done > span { font-size: 0; }
.process-steps li.done strong { color: #24624f; }
.review-route { overflow: hidden; border: 1px solid #d9dee5; border-radius: 12px; background: #fbfcfd; box-shadow: 0 8px 24px rgba(21, 25, 29, .05); }
.review-route > header { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #e2e6eb; padding: 13px 18px; }
.review-route-kicker { margin-bottom: 3px; color: var(--blue); font-size: 9px; font-weight: 900; letter-spacing: .1em; }
.review-route h2 { margin: 0; color: var(--text); font-size: 14px; font-weight: 900; }
.review-route > header > span { color: var(--text3); font-size: 10px; font-weight: 800; }
.review-route-track { display: flex; align-items: center; gap: 10px; overflow-x: auto; padding: 15px 18px; }
.review-route-person { display: flex; align-items: center; gap: 10px; min-width: 210px; border: 1px solid #dce2e8; border-radius: 8px; background: #fdfefe; padding: 10px 12px; }
.review-route-person > span { display: grid; place-items: center; flex: 0 0 28px; width: 28px; height: 28px; border-radius: 50%; background: #edf0f3; color: #687482; font-size: 10px; font-weight: 900; }
.review-route-person > div { display: grid; gap: 2px; min-width: 0; }
.review-route-person strong { overflow: hidden; color: var(--text); font-size: 11px; font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
.review-route-person small { overflow: hidden; color: var(--text3); font-size: 9px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.review-route-person.employee { border-color: #c7ddd5; background: #f0f7f4; }
.review-route-person.employee > span { background: #2f735f; color: #f8fcfa; }
.review-route-person.current { border-color: #d8816e; background: #fff4f0; }
.review-route-person.current > span { background: var(--blue); color: #fffaf8; }
.review-route-arrow { flex: 0 0 auto; color: #a2abb6; font-size: 17px; font-weight: 900; }
.review-route > footer { border-top: 1px solid #e2e6eb; background: #f5f7f8; padding: 9px 18px; color: #7c503f; font-size: 10px; font-weight: 800; }
.review-route-help { display: flex; align-items: center; gap: 7px; border-top: 1px solid #eadfd9; background: #fff9f6; padding: 9px 18px; color: #8b4c3a; }
.review-route-help > span { display: grid; place-items: center; flex: 0 0 18px; width: 18px; height: 18px; border: 1px solid #e3a28f; border-radius: 50%; font-size: 10px; font-weight: 900; }
.review-route-help p { margin: 0; font-size: 10px; font-weight: 800; }
.blocked-card {
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fff7f7;
  padding: 18px 20px;
  color: #991b1b;
}
.blocked-title { font-size: 16px; font-weight: 900; }
.blocked-copy { margin-top: 6px; color: #b91c1c; font-size: 13px; }
.blocked-list { margin: 12px 0 0; padding-left: 20px; color: #7f1d1d; font-size: 13px; }
.blocked-list li + li { margin-top: 4px; }
.fc-selection-card {
  border: 1px solid #d9dee5;
  border-radius: 12px;
  background: #fbfcfd;
  padding: 20px;
  box-shadow: var(--shadow);
}
.fc-selection-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  border-bottom: 1px solid #e2e6eb;
  padding-bottom: 16px;
}
.fc-kicker {
  color: var(--blue);
  font-size: 10px;
  font-weight: 900;
  letter-spacing: .08em;
}
.fc-selection-head h2 {
  margin: 5px 0 0;
  color: var(--text);
  font-size: 18px;
  font-weight: 900;
}
.fc-selection-head p {
  margin: 5px 0 0;
  color: var(--text3);
  font-size: 13px;
}
.fc-status-pill {
  border-radius: 999px;
  border: 1px solid #efc6bd;
  background: #fff2ee;
  color: #a63824;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}
.fc-status-pill.approved { background: #dcfce7; color: #166534; }
.fc-status-pill.waiting { background: #fef3c7; color: #92400e; }
.fc-status-pill.revision { background: #fee2e2; color: #991b1b; }
.fc-revision-note {
  display: flex;
  gap: 8px;
  margin-top: 14px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fff7f7;
  padding: 10px 12px;
  color: #991b1b;
  font-size: 13px;
}
.fc-selection-meter {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 16px;
  border-radius: 8px;
  background: #f1f3f5;
  padding: 10px 12px;
  color: var(--text3);
  font-size: 12px;
  font-weight: 800;
}
.fc-selection-meter > div { display: flex; align-items: baseline; gap: 5px; }
.fc-selection-meter strong { color: var(--text); font-size: 18px; font-weight: 900; }
.fc-selection-meter > span { color: #7c503f; }
.fc-choice-list {
  display: grid;
  gap: 8px;
  margin-top: 12px;
}
.fc-choice {
  display: flex;
  align-items: center;
  gap: 14px;
  border: 1px solid #dce1e7;
  border-radius: 8px;
  background: #fdfefe;
  padding: 13px 14px;
  cursor: pointer;
  transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
}
.fc-choice:hover { border-color: #d7a79b; background: #fffaf8; }
.fc-choice:focus-visible { outline: 3px solid rgba(199, 67, 43, .16); border-color: var(--blue); }
.fc-choice-main {
  min-width: 0;
  flex: 1;
}
.fc-choice.selected {
  border-color: #d46a53;
  background: #fff7f4;
  box-shadow: 0 4px 14px rgba(199, 67, 43, .07);
}
.fc-choice.locked {
  cursor: default;
}
.fc-check {
  display: grid;
  place-items: center;
  width: 19px;
  height: 19px;
  border: 2px solid #bcc5d0;
  border-radius: 5px;
  color: #fffaf8;
  font-size: 11px;
  font-weight: 900;
}
.fc-choice.selected .fc-check {
  border-color: var(--blue);
  background: var(--blue);
}
.fc-choice-copy { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: baseline; gap: 5px 10px; min-width: 0; }
.fc-code { color: var(--blue); font-size: 11px; font-weight: 900; }
.fc-choice-copy strong {
  min-width: 0;
  font-size: 13px;
  font-weight: 900;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fc-detail-link {
  grid-column: 1 / -1;
  justify-self: start;
  border: 0;
  background: transparent;
  padding: 0;
  color: #a63824;
  font-size: 10px;
  font-weight: 900;
  cursor: pointer;
}
.fc-detail-link:hover { text-decoration: underline; }
.fc-detail-link:focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; }
.fc-choice-state { display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto; border: 0; border-radius: 999px; background: #eef1f4; color: #687381; padding: 7px 10px; font-size: 10px; font-weight: 900; cursor: pointer; }
.fc-choice.selected .fc-choice-state { background: #f7ded7; color: #a63824; }
.fc-choice-state:disabled { cursor: default; opacity: .72; }
.fc-choice-state .fc-check { width: 15px; height: 15px; border-width: 1.5px; border-radius: 4px; }
.fc-detail-backdrop { position: fixed; inset: 0; z-index: 70; display: grid; place-items: center; background: rgba(21, 25, 29, .56); padding: 16px; }
.fc-detail-modal { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; width: min(760px, 100%); max-height: min(760px, calc(100vh - 32px)); overflow: hidden; border: 1px solid #d9dee5; border-radius: 12px; background: #fbfcfd; box-shadow: 0 28px 80px rgba(21, 25, 29, .28); }
.fc-detail-modal > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; border-bottom: 1px solid #e1e5ea; padding: 20px 22px; }
.fc-detail-modal > header h2 { max-width: 34ch; margin: 7px 0 0; color: var(--text); font-size: 19px; line-height: 1.4; }
.fc-detail-meta { display: flex; align-items: center; gap: 8px; color: var(--blue); font-size: 11px; font-weight: 900; }
.fc-detail-meta span { border-radius: 5px; background: #f7ded7; padding: 4px 7px; }
.fc-detail-modal > header > button { display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border: 1px solid #d7dde4; border-radius: 7px; background: #f6f7f8; color: #626d79; font-size: 22px; cursor: pointer; }
.fc-detail-body { overflow-y: auto; padding: 20px 22px; }
.fc-detail-body > section > h3 { margin: 0 0 9px; color: var(--text); font-size: 12px; font-weight: 900; }
.fc-detail-body > section > p { max-width: 72ch; margin: 0; color: #586574; font-size: 13px; line-height: 1.75; white-space: pre-line; }
.fc-detail-description { border: 1px solid #dce2e8; border-radius: 9px; background: #f7f9fa; padding: 15px 16px; }
.fc-detail-levels { margin-top: 18px; }
.fc-detail-level { overflow: hidden; border: 1px solid #ead5cc; border-radius: 9px; background: #fdfefe; }
.fc-detail-level + .fc-detail-level { margin-top: 10px; }
.fc-detail-level-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; border-bottom: 1px solid #ead5cc; background: #fff3ed; padding: 13px 16px; }
.fc-detail-level-head strong { color: #b63820; font-size: 13px; font-weight: 900; }
.fc-detail-level-head span { color: #8b7d77; font-size: 11px; font-weight: 800; white-space: nowrap; }
.fc-detail-level ul { display: grid; gap: 9px; margin: 0; padding: 13px 16px; color: #586574; font-size: 12px; line-height: 1.6; list-style: none; }
.fc-detail-level li { position: relative; padding-left: 18px; }
.fc-detail-level li::before { content: ''; position: absolute; top: .62em; left: 2px; width: 6px; height: 6px; border-radius: 50%; background: var(--blue); }
.fc-detail-level > p { margin: 0; padding: 13px 16px; color: var(--text3); font-size: 11px; }
.fc-detail-modal > footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-top: 1px solid #e1e5ea; background: #f5f7f8; padding: 13px 22px; }
.fc-detail-modal > footer > span { color: var(--text3); font-size: 11px; font-weight: 800; }
.fc-detail-modal > footer > div { display: flex; gap: 9px; }
.fc-selection-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 18px;
  border-top: 1px solid #e2e6eb;
  padding-top: 16px;
}
.fc-selection-actions > div { display: grid; gap: 3px; }
.fc-selection-actions strong { color: var(--text); font-size: 11px; font-weight: 900; }
.fc-selection-actions span { color: var(--text3); font-size: 10px; }
.fc-selection-actions .fc-submit-warning { color: #b42318; font-weight: 800; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.summary-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 18px 20px;
  box-shadow: var(--shadow);
}
.summary-label { color: var(--text3); font-size: 12px; font-weight: 700; }
.summary-value { color: var(--text); font-size: 28px; font-weight: 900; line-height: 1.1; margin-top: 10px; }
.summary-copy { color: var(--text3); font-size: 12px; margin-top: 6px; }
.content-card {
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.card-head { padding: 18px 20px; border-bottom: 1px solid var(--border); }
.card-head h2 { margin: 0; color: var(--text); font-size: 16px; font-weight: 900; }
.card-head p { margin: 6px 0 0; color: var(--text3); font-size: 12px; }
.competency-list { display: grid; gap: 10px; padding: 16px 20px; }
.competency-row {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(280px, 48%);
  align-items: center;
  gap: 16px;
  width: 100%;
  border: 1px solid var(--border);
  border-left: 3px solid #94a3b8;
  border-radius: 12px;
  background: #fff;
  padding: 14px 16px;
  text-align: left;
  cursor: pointer;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.competency-row.status-approved { border-left-color: #10b981; }
.competency-row.status-pending { border-left-color: #f59e0b; }
.competency-row.status-revision { border-left-color: #ef4444; }
.competency-row.status-draft { border-left-color: #94a3b8; }
.competency-row:hover { border-color: rgba(37, 99, 235, .35); box-shadow: 0 12px 28px rgba(15, 23, 42, .08); }
.competency-row.status-approved:hover { border-left-color: #10b981; }
.competency-row.status-pending:hover { border-left-color: #f59e0b; }
.competency-row.status-revision:hover { border-left-color: #ef4444; }
.competency-row.status-draft:hover { border-left-color: #94a3b8; }
.competency-title {
  display: grid;
  gap: 4px;
  min-width: 0;
  color: var(--text);
  line-height: 1.35;
}
.competency-title strong {
  min-width: 0;
  font-size: 14px;
  font-weight: 900;
  overflow: hidden;
  text-overflow: ellipsis;
}
.competency-title small {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}
.type-tag {
  min-width: 28px;
  border-radius: 8px;
  background: var(--blue-lt);
  color: var(--blue);
  padding: 4px 7px;
  text-align: center;
  font-size: 11px;
  font-weight: 900;
}
.row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  min-width: 0;
}
.draft-status {
  min-width: 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.assessment-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  border: 1px solid #dbe5f1;
  border-radius: 999px;
  background: #f8fafc;
  color: #475569;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 900;
  max-width: 100%;
  line-height: 1.35;
  white-space: normal;
  text-align: center;
}
.assessment-status.approved {
  border-color: #bbf7d0;
  background: #dcfce7;
  color: #15803d;
}
.assessment-status.pending {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}
.assessment-status.revision {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}
.chevron-btn {
  grid-column: 4;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #475569;
  padding: 0;
  font-size: 22px;
  font-weight: 900;
  line-height: 1;
  justify-self: end;
}
.chevron-btn { font-weight: 900; }
.empty-card {
  display: grid;
  place-items: center;
  min-height: 260px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 32px;
  text-align: center;
}
.empty-title { color: var(--text); font-size: 16px; font-weight: 900; }
.empty-copy { color: var(--text3); font-size: 13px; margin-top: 8px; }
.compact { min-height: 160px; }
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, .48);
  backdrop-filter: blur(2px);
  padding: 12px;
}
.detail-modal {
  display: flex;
  flex-direction: column;
  width: min(820px, calc(100vw - 24px));
  height: min(760px, calc(100vh - 24px));
  max-height: calc(100vh - 24px);
  min-height: 0;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
  overflow: hidden;
}
.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  flex: 0 0 auto;
  padding: 24px 28px 22px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
}
.modal-code { display: flex; align-items: center; gap: 10px; color: var(--text3); font-size: 12px; font-weight: 900; }
.modal-head h2 { margin: 12px 0 0; color: var(--text); font-size: 24px; font-weight: 900; line-height: 1.15; }
.modal-head p { margin: 6px 0 0; color: var(--text3); font-size: 12px; }
.modal-head .locked-copy {
  color: #b45309;
  font-weight: 800;
}
.modal-head .approved-copy {
  color: #047857;
  font-weight: 900;
}
.modal-head .revision-copy {
  color: #b91c1c;
  font-weight: 900;
}
.modal-body {
  flex: 1 1 auto;
  min-height: 0;
  padding: 18px 28px;
  overflow-y: auto;
  background: #f6f8fb;
}
.competency-detail-card {
  display: grid;
  gap: 10px;
  margin-bottom: 14px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  padding: 16px 18px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.detail-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.detail-card-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text3);
  font-size: 12px;
  font-weight: 900;
}
.competency-detail-card h3 {
  margin: 0;
  color: var(--text);
  font-size: 17px;
  font-weight: 900;
  line-height: 1.35;
}
.competency-description-box {
  border: 1px solid #dbe5f1;
  border-radius: 12px;
  background: #f8fafc;
  padding: 12px 14px;
}
.description-label {
  margin-bottom: 6px;
  color: #64748b;
  font-size: 12px;
  font-weight: 900;
}
.competency-description-box p {
  margin: 0;
  color: var(--text2, #475569);
  font-size: 13px;
  line-height: 1.65;
}
.level-pill {
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--green-bg, #ecfdf5);
  color: var(--green, #047857);
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}
.emp-level-section {
  display: block;
  margin-bottom: 14px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.emp-level-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  background: #eefafa;
  border-bottom: 1px solid var(--border);
}
.emp-level-head h3 { margin: 0; color: var(--text); font-size: 14px; font-weight: 900; }
.emp-level-head p { margin: 5px 0 0; color: var(--text3); font-size: 12px; }
.emp-level-status {
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.emp-level-status.idle {
  background: #e2e8f0;
  color: #475569;
}

.emp-level-status.done {
  background: var(--color-success-bg);
  color: var(--color-success);
}
.emp-indicator-list { display: block; width: 100%; background: #fff; }
.emp-indicator-row {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  width: 100%;
  padding: 15px 18px;
  border-bottom: 1px solid var(--border);
  background: #fff;
  cursor: pointer;
  box-sizing: border-box;
}
.emp-indicator-row:hover { background: #f8fafc; }
.emp-indicator-row:last-child { border-bottom: 0; }
.emp-indicator-row:has(input:disabled) {
  background: #fafbfc;
  color: var(--color-disabled-text);
  cursor: not-allowed;
}
.emp-indicator-row input {
  flex: 0 0 auto;
  width: 14px;
  height: 14px;
  margin-top: 2px;
  accent-color: var(--teal);
}
.emp-indicator-row input:focus,
.emp-indicator-row input:focus-visible {
  outline: none;
  box-shadow: none;
}
.emp-indicator-row input:disabled {
  opacity: 1;
  cursor: not-allowed;
}
.emp-indicator-copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  width: 100%;
}
.emp-indicator-row strong { display: block; color: var(--text); font-size: 12px; font-weight: 900; }
.emp-indicator-row small {
  display: block;
  color: var(--text);
  font-size: 13px;
  line-height: 1.65;
  white-space: normal;
  overflow: visible;
  text-overflow: initial;
  word-break: break-word;
}
.emp-indicator-row:has(input:disabled) small { color: var(--color-disabled-text); }
.emp-indicator-row em {
  display: block;
  margin-top: 6px;
  color: var(--color-text-muted);
  font-size: 11px;
  font-style: normal;
  font-weight: 700;
}
.emp-indicator-empty { padding: 14px 16px; color: var(--text3); font-size: 12px; }
.emp-note-section {
  display: grid;
  gap: 8px;
  margin-top: 12px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  padding: 16px 18px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
}
.emp-note-section label { color: var(--text); font-size: 13px; font-weight: 900; }
.emp-note-section textarea {
  min-height: 110px;
  width: 100%;
  resize: vertical;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  color: var(--text);
  font: inherit;
  font-size: 13px;
  line-height: 1.55;
  outline: none;
}
.emp-note-section textarea:focus {
  border-color: var(--color-focus);
  box-shadow: 0 0 0 2px var(--color-focus);
}
.emp-note-section textarea:disabled {
  background: var(--color-disabled-bg);
  color: var(--color-disabled-text);
  cursor: not-allowed;
}
.reviewer-comment-section {
  display: grid;
  gap: 8px;
  border: 1px solid #fed7aa;
  border-radius: 14px;
  background: #fff7ed;
  padding: 16px 18px;
  color: #7c2d12;
  box-shadow: 0 8px 22px rgba(194, 65, 12, .08);
}
.reviewer-comment-label {
  color: #c2410c;
  font-size: 13px;
  font-weight: 900;
}
.reviewer-comment-section p {
  margin: 0;
  white-space: pre-wrap;
  color: #431407;
  font-size: 14px;
  line-height: 1.65;
}
.modal-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex: 0 0 auto;
  min-height: 64px;
  padding: 12px 26px;
  border-top: 1px solid var(--border);
  background: #fff;
  color: var(--text3);
  font-size: 12px;
}
.assessment-save-meta {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.assessment-save-meta > span {
  color: var(--text2);
  font-size: 12px;
  font-weight: 700;
}

.autosave-status {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  line-height: 1.35;
}

.autosave-status.saving {
  color: #d97706;
}

.autosave-status.saved {
  color: #16a34a;
}

.autosave-status.error {
  color: #dc2626;
}

.modal-foot .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 132px;
  border-radius: 10px;
  background: var(--teal, #75292d);
  color: #fff;
  border-color: var(--teal, #75292d);
  opacity: 1;
  visibility: visible;
}
.next-pill {
  flex: 0 0 auto;
  margin-left: auto;
  align-self: center;
  border-radius: 999px;
  background: #eff6ff;
  color: #2563eb;
  padding: 4px 10px;
  font-size: 11px;
  font-style: normal;
  font-weight: 800;
  white-space: nowrap;
}
.modal-foot .btn small { font-size: 11px; opacity: .85; }
.confirm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(15, 23, 42, .42);
}
.confirm-modal {
  width: min(440px, 100%);
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
  padding: 22px 24px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
}
.confirm-title {
  color: var(--text);
  font-size: 17px;
  font-weight: 900;
}
.confirm-modal p {
  margin: 10px 0 0;
  color: var(--text2);
  font-size: 13px;
  line-height: 1.65;
}
.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
}
.confirm-actions .btn {
  min-width: 96px;
  border-radius: 8px;
  font-weight: 900;
  opacity: 1;
  visibility: visible;
  justify-content: center;
  text-align: center;
}
.confirm-actions .btn-s {
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--text);
    border-radius: 8px;
}

.confirm-actions .btn-s:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: var(--text);
}
.confirm-actions .btn-t {
  border: 1px solid var(--color-primary);
  background: var(--color-primary);
  color: #fff;
}
.confirm-actions .btn-t:hover:not(:disabled) {
  border-color: var(--color-primary-hover);
  background: var(--color-primary-hover);
  color: #fff;
}
.confirm-actions .btn:disabled {
  opacity: 1;
  cursor: not-allowed;
}
@media (max-width: 760px) {
  .page-head { align-items: flex-start; flex-direction: column; }
  .readiness-head { grid-template-columns: auto minmax(0, 1fr); padding: 17px; }
  .readiness-count { grid-column: 1 / -1; grid-template-columns: auto auto; align-items: baseline; justify-content: center; gap: 5px; }
  .readiness-list { padding-right: 17px; padding-left: 17px; }
  .readiness-row { grid-template-columns: 28px minmax(0, 1fr); }
  .readiness-owner { grid-column: 2; justify-self: start; }
  .readiness-footer { padding-right: 17px; padding-left: 17px; }
  .process-intro,
  .fc-selection-head,
  .fc-selection-actions { align-items: stretch; flex-direction: column; }
  .process-steps { grid-template-columns: 1fr; }
  .process-steps li + li { border-top: 1px solid #e2e6eb; border-left: 0; }
  .review-route-person { min-width: 190px; }
  .fc-selection-meter { align-items: flex-start; flex-direction: column; }
  .fc-choice { align-items: flex-start; }
  .fc-choice-copy { grid-template-columns: 1fr; }
  .fc-code,
  .fc-detail-link { grid-column: 1; }
  .fc-selection-actions .btn { width: 100%; }
  .fc-detail-modal > footer { align-items: stretch; flex-direction: column; }
  .fc-detail-modal > footer > div { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .summary-grid { grid-template-columns: 1fr; }
  .competency-row {
    grid-template-columns: minmax(0, 1fr) 42px;
    align-items: flex-start;
  }
  .draft-status {
    grid-column: 1 / -1;
    grid-row: 2;
  }
  .assessment-status {
    grid-column: 1;
    grid-row: 3;
    justify-self: start;
  }
  .chevron-btn {
    grid-column: 2;
    grid-row: 1;
  }
  .modal-head,
  .modal-foot { flex-direction: column; align-items: stretch; }
  .modal-body,
  .modal-head,
  .modal-foot { padding-left: 16px; padding-right: 16px; }
}
</style>
