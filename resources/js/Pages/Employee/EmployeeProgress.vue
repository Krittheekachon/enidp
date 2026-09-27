<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

type PlanRow = Record<string, string | string[] | undefined>;
type Evidence = { publicId: string; kind: 'link' | 'image' | 'file'; name: string; description: string; url: string };
type ProgressUpdate = {
  publicId: string;
  topicIndex: number;
  periodStart: string;
  periodEnd: string;
  progressNote: string;
  status: string;
  submittedAt?: string;
  evidences: Evidence[];
};
type CompletionReview = {
  reviewerName: string;
  decision: 'approved' | 'rejected';
  comment: string;
  operationStatus?: string | null;
  operationReason?: string;
  achievementStatus?: string | null;
  achievementNote?: string;
  decidedAt?: string;
};
type Completion = { publicId: string; status: string; result?: 'passed' | 'failed' | null; currentReviewStep: number | null; currentReviewerName?: string | null; submissionVersion: number; submittedAt?: string; approvedAt?: string; review?: CompletionReview | null };
type ActivityGroup = { idpItemId: number; code: string; name: string; activities: ApprovedActivity[]; completion: Completion | null };
type ApprovedActivity = {
  id: number;
  idpItemId: number;
  competencyCode: string;
  competencyName: string;
  name: string;
  weightPercent: number;
  startDate: string;
  endDate: string;
  documentReferenceNumber: string;
  formCode: string;
  planDetails: { planRows?: PlanRow[] };
  goal: string;
  successCriteria: string;
  updates: ProgressUpdate[];
  completion: Completion | null;
};

const props = defineProps<{ activities?: ApprovedActivity[] }>();
const page = usePage();
const processing = ref(false);
const updateDialog = ref<{ activity: ApprovedActivity; topicIndex: number } | null>(null);
const confirmUpdateOpen = ref(false);
const completionConfirmGroup = ref<ActivityGroup | null>(null);
const updateValidationError = ref('');
const fileSelectionError = ref('');
const MAX_EVIDENCE_FILES = 10;
const MAX_EVIDENCE_FILE_BYTES = 10 * 1024 * 1024;
const updateForm = reactive({
  periodStart: '', periodEnd: '', progressNote: '',
  evidenceLinks: [{ url: '' }], evidenceFiles: [] as File[],
});

const activities = computed(() => props.activities || []);
const groupedActivities = computed(() => {
  const groups = new Map<number, ActivityGroup>();
  activities.value.forEach((activity) => {
    if (!groups.has(activity.idpItemId)) groups.set(activity.idpItemId, {
      idpItemId: activity.idpItemId,
      code: activity.competencyCode,
      name: activity.competencyName,
      activities: [],
      completion: activity.completion,
    });
    groups.get(activity.idpItemId)?.activities.push(activity);
  });
  return [...groups.values()];
});
const selectedGroupId = ref<number | null>(null);
watch(groupedActivities, (groups) => {
  if (!groups.length) {
    selectedGroupId.value = null;
    return;
  }
  if (!groups.some((group) => group.idpItemId === selectedGroupId.value)) {
    selectedGroupId.value = groups[0].idpItemId;
  }
}, { immediate: true });
const selectedGroup = computed(() => groupedActivities.value.find((group) => group.idpItemId === selectedGroupId.value) || null);

const planRows = (activity: ApprovedActivity): PlanRow[] => activity.planDetails?.planRows?.length
  ? activity.planDetails.planRows
  : [{}];
const topicTitle = (activity: ApprovedActivity, index: number) => {
  const row = planRows(activity)[index] || {};
  return String(row.assignmentTopic || row.skillTopic || row.topic || row.learningTopic || row.courseName || activity.name || 'กิจกรรมพัฒนา').trim();
};
const activityTypeLabel = (activity: ApprovedActivity) => {
  if (['form_3_project_assignment', 'form_4_ojt'].includes(activity.formCode)) return 'Experiential Learning';
  if (['form_5_coaching', 'form_6_mentoring', 'form_7_group_activity', 'form_8_feedback', 'form_9_field_trip'].includes(activity.formCode)) return 'Social Learning';
  if (activity.formCode !== 'form_10_training') return 'กิจกรรมพัฒนา';
  const type = String(planRows(activity)[0]?.trainingType || '').toLowerCase();
  return type.includes('in-class') || type.includes('in class')
    ? 'Training Roadmap (In-class Learning)'
    : 'e-Learning';
};
const updatesFor = (activity: ApprovedActivity, topicIndex: number) =>
  (activity.updates || []).filter((update) => update.topicIndex === topicIndex);
const submittedUpdatesFor = (activity: ApprovedActivity, topicIndex: number) =>
  updatesFor(activity, topicIndex).filter((update) => update.status !== 'draft');
const isLocked = (completion: Completion | null) => completion?.status === 'approved' || /^review_step_\d+$/.test(completion?.status || '');
const topicTotal = (group: { activities: ApprovedActivity[] }) => group.activities.reduce((sum, activity) => sum + planRows(activity).length, 0);
const updatedTopicTotal = (group: { activities: ApprovedActivity[] }) => group.activities.reduce((sum, activity) =>
  sum + planRows(activity).filter((_, topicIndex) => submittedUpdatesFor(activity, topicIndex).length > 0).length, 0);
const remainingTopicTotal = (group: { activities: ApprovedActivity[] }) => Math.max(topicTotal(group) - updatedTopicTotal(group), 0);
const canSubmitCompletion = (group: { activities: ApprovedActivity[]; completion: Completion | null }) =>
  topicTotal(group) > 0 && topicTotal(group) === updatedTopicTotal(group) && !isLocked(group.completion);

const completionMeta = (completion: Completion | null) => {
  if (!completion) return { label: 'กำลังดำเนินการ', className: 'active' };
  if (completion.status === 'approved' && completion.result === 'failed') return { label: 'ไม่ผ่าน', className: 'returned' };
  if (completion.status === 'approved') return { label: 'อนุมัติผลแล้ว', className: 'done' };
  if (completion.status === 'revision_required') return { label: 'ถูกส่งกลับให้แก้ไข', className: 'returned' };
  if (/^review_step_\d+$/.test(completion.status)) {
    const step = completion.currentReviewStep || 1;
    return {
      label: completion.currentReviewerName
        ? `รอลำดับที่ ${step} · ${completion.currentReviewerName} ตรวจสอบ`
        : `รอลำดับที่ ${step} · ผู้ตรวจสอบ`,
      className: 'pending',
    };
  }
  return { label: 'กำลังดำเนินการ', className: 'active' };
};

const openNewUpdate = (activity: ApprovedActivity, topicIndex: number) => {
  Object.assign(updateForm, {
    periodStart: '', periodEnd: '', progressNote: '',
    evidenceLinks: [{ url: '' }], evidenceFiles: [],
  });
  updateValidationError.value = '';
  fileSelectionError.value = '';
  confirmUpdateOpen.value = false;
  updateDialog.value = { activity, topicIndex };
};
const closeUpdate = () => {
  if (processing.value) return;
  confirmUpdateOpen.value = false;
  updateDialog.value = null;
};
const addLink = () => { if (updateForm.evidenceLinks.length < 10) updateForm.evidenceLinks.push({ url: '' }); };
const removeLink = (index: number) => updateForm.evidenceLinks.splice(index, 1);
const chooseFiles = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const selected = Array.from(input.files || []);
  const oversizedFiles = selected.filter((file) => file.size > MAX_EVIDENCE_FILE_BYTES);
  const acceptedFiles = selected.filter((file) => file.size <= MAX_EVIDENCE_FILE_BYTES);
  const uniqueFiles = [...updateForm.evidenceFiles, ...acceptedFiles].filter((file, index, files) =>
    files.findIndex((candidate) => candidate.name === file.name && candidate.size === file.size && candidate.lastModified === file.lastModified) === index
  );
  updateForm.evidenceFiles = uniqueFiles.slice(0, MAX_EVIDENCE_FILES);
  if (oversizedFiles.length) {
    fileSelectionError.value = `ไฟล์ ${oversizedFiles.map((file) => file.name).join(', ')} มีขนาดเกิน 10 MB`;
  } else if (uniqueFiles.length > MAX_EVIDENCE_FILES) {
    fileSelectionError.value = 'แนบไฟล์ได้สูงสุด 10 ไฟล์';
  } else {
    fileSelectionError.value = '';
  }
  input.value = '';
};
const removeEvidenceFile = (index: number) => {
  updateForm.evidenceFiles.splice(index, 1);
  fileSelectionError.value = '';
};
const requestSaveUpdate = () => {
  if (!updateForm.periodStart || !updateForm.periodEnd || !updateForm.progressNote.trim()) {
    updateValidationError.value = 'กรุณากรอกวันที่เริ่มต้น วันที่สิ้นสุด และสิ่งที่ทำไปแล้วให้ครบ';
    return;
  }
  updateValidationError.value = '';
  confirmUpdateOpen.value = true;
};
const saveUpdate = () => {
  if (!updateDialog.value) return;
  processing.value = true;
  const { activity, topicIndex } = updateDialog.value;
  router.post(route('employee.idp-activities.update-progress'), {
    activityId: activity.id,
    action: 'submit',
    topicIndex,
    periodStart: updateForm.periodStart || null,
    periodEnd: updateForm.periodEnd || null,
    progressNote: updateForm.progressNote || null,
    evidenceLinks: updateForm.evidenceLinks.filter((link) => link.url.trim()),
    evidenceFiles: updateForm.evidenceFiles,
  }, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      confirmUpdateOpen.value = false;
      updateDialog.value = null;
    },
    onFinish: () => { processing.value = false; },
  });
};

const requestSubmitCompletion = (group: ActivityGroup) => {
  if (!canSubmitCompletion(group) || processing.value) return;
  completionConfirmGroup.value = group;
};
const submitCompletion = () => {
  if (!completionConfirmGroup.value) return;
  const idpItemId = completionConfirmGroup.value.idpItemId;
  processing.value = true;
  router.post(route('employee.idp-items.submit-completion'), { idpItemId }, {
    preserveScroll: true,
    onSuccess: () => { completionConfirmGroup.value = null; },
    onFinish: () => { processing.value = false; },
  });
};

const firstError = computed(() => Object.values(page.props.errors || {})[0] as string | undefined);
const formatDate = (value?: string) => {
  if (!value) return 'ไม่ระบุ';
  const source = value.length > 10 ? value : `${value}T00:00:00`;
  const date = new Date(source);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
  <section class="employee-page">
    <header class="page-head">
      <div><h1>อัปเดตความก้าวหน้า</h1><p>บันทึกสิ่งที่ทำในแต่ละหัวข้อ แล้วส่งเมื่อเสร็จครบทั้งสมรรถนะ</p></div>
    </header>

    <div v-if="firstError" class="page-error">{{ firstError }}</div>
    <div v-if="!activities.length" class="empty-card">
      <strong>ยังไม่มีกิจกรรมที่อัปเดตได้</strong>
      <span>กิจกรรมจะแสดงหลังแผน IDP ผ่านการอนุมัติครบทุกลำดับ</span>
    </div>

    <nav v-if="groupedActivities.length" class="competency-switcher" aria-label="เลือกสมรรถนะที่ต้องการอัปเดต">
      <button
        v-for="group in groupedActivities"
        :key="group.idpItemId"
        type="button"
        class="competency-tab"
        :class="{ selected: group.idpItemId === selectedGroupId }"
        :aria-pressed="group.idpItemId === selectedGroupId"
        :title="group.name"
        @click="selectedGroupId = group.idpItemId"
      >
        <span class="competency-tab-code">{{ group.code }}</span>
        <span class="competency-tab-copy">
          <strong>{{ group.name }}</strong>
          <small>{{ completionMeta(group.completion).label }} · อัปเดตแล้ว {{ updatedTopicTotal(group) }}/{{ topicTotal(group) }} หัวข้อ</small>
        </span>
      </button>
    </nav>

    <section v-if="selectedGroup" :key="selectedGroup.idpItemId" class="competency-section">
      <header class="competency-head">
        <div class="competency-copy"><span>{{ selectedGroup.code }}</span><div><small>สมรรถนะที่เลือก</small><h2>{{ selectedGroup.name }}</h2></div></div>
        <div class="completion-actions">
          <span class="status-pill" :class="completionMeta(selectedGroup.completion).className">{{ completionMeta(selectedGroup.completion).label }}</span>
          <span class="topic-count">อัปเดตแล้ว {{ updatedTopicTotal(selectedGroup) }}/{{ topicTotal(selectedGroup) }} หัวข้อ</span>
          <span
            v-if="!isLocked(selectedGroup.completion)"
            class="submit-completion-wrap"
            :class="{ 'has-tooltip': !canSubmitCompletion(selectedGroup) }"
            :tabindex="canSubmitCompletion(selectedGroup) ? undefined : 0"
          >
            <button
              class="submit-completion"
              type="button"
              :disabled="processing || !canSubmitCompletion(selectedGroup)"
              @click="requestSubmitCompletion(selectedGroup)"
            >ส่งให้หัวหน้าตรวจ</button>
            <span v-if="!canSubmitCompletion(selectedGroup)" class="submit-tooltip" role="tooltip">
              ต้องเพิ่มอัปเดตให้ครบอีก {{ remainingTopicTotal(selectedGroup) }} หัวข้อ
            </span>
          </span>
        </div>
      </header>

      <section v-if="selectedGroup.completion?.review" class="review-feedback" :class="selectedGroup.completion.review.decision">
        <header>
          <div><small>ความคิดเห็นจากหัวหน้า</small><strong>{{ selectedGroup.completion.review.reviewerName }}</strong></div>
          <time>{{ formatDate(selectedGroup.completion.review.decidedAt) }}</time>
        </header>
        <p v-if="selectedGroup.completion.review.comment">{{ selectedGroup.completion.review.comment }}</p>
        <p v-if="selectedGroup.completion.review.operationReason"><b>เหตุผลที่ไม่เป็นไปตามแผน:</b> {{ selectedGroup.completion.review.operationReason }}</p>
        <p v-if="selectedGroup.completion.review.achievementNote"><b>สิ่งที่ควรพัฒนาต่อ:</b> {{ selectedGroup.completion.review.achievementNote }}</p>
      </section>

      <article v-for="(activity, activityIndex) in selectedGroup.activities" :key="activity.id" class="activity-block">
        <header class="activity-head">
          <span class="activity-number">{{ activityIndex + 1 }}</span>
          <div><small>{{ activityTypeLabel(activity) }}</small><h3>{{ activity.name }}</h3></div>
          <strong>น้ำหนัก {{ activity.weightPercent }}%</strong>
        </header>

        <section v-for="(row, topicIndex) in planRows(activity)" :key="topicIndex" class="topic-row" :class="{ 'has-updates': submittedUpdatesFor(activity, topicIndex).length }">
          <div class="topic-summary">
            <span class="topic-index">หัวข้อ {{ topicIndex + 1 }}</span>
            <h4>{{ topicTitle(activity, topicIndex) }}</h4>
            <p>{{ formatDate(String(row.developmentStart || activity.startDate)) }}  ถึง  {{ formatDate(String(row.developmentEnd || activity.endDate)) }}</p>
          </div>

          <div class="timeline">
            <article v-for="update in submittedUpdatesFor(activity, topicIndex)" :key="update.publicId" class="timeline-entry">
              <div class="timeline-date">
                <span><small>เริ่มต้น</small><strong>{{ formatDate(update.periodStart) }}</strong></span>
                <b aria-hidden="true">→</b>
                <span><small>สิ้นสุด</small><strong>{{ formatDate(update.periodEnd) }}</strong></span>
              </div>
              <div class="timeline-note"><small>สิ่งที่ทำไปแล้ว</small><p>{{ update.progressNote }}</p></div>
              <div v-if="update.evidences.length" class="evidence-list">
                <a v-for="evidence in update.evidences" :key="evidence.publicId" :href="evidence.url" target="_blank" rel="noopener noreferrer">
                  {{ evidence.kind === 'link' ? 'ลิงก์' : evidence.kind === 'image' ? 'รูปภาพ' : 'ไฟล์' }}: {{ evidence.name }}
                </a>
              </div>
              <small>ส่งเมื่อ {{ formatDate(update.submittedAt) }}</small>
            </article>
            <div v-if="!submittedUpdatesFor(activity, topicIndex).length" class="no-update">
              <span aria-hidden="true">○</span>
              <div><strong>ยังไม่มีประวัติการอัปเดต</strong><small>เริ่มบันทึกสิ่งที่ทำและแนบหลักฐานได้จากปุ่มด้านขวา</small></div>
            </div>
          </div>

          <div class="topic-actions" v-if="!isLocked(activity.completion)">
            <button class="update-button" type="button" @click="openNewUpdate(activity, topicIndex)">+ เพิ่มอัปเดต</button>
          </div>
        </section>
      </article>
    </section>

    <div v-if="completionConfirmGroup" class="modal-backdrop confirm-backdrop" @click.self="!processing ? completionConfirmGroup = null : null">
      <article class="confirm-dialog completion-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="completion-confirm-title" aria-describedby="completion-confirm-warning">
        <header>
          <span class="confirm-icon is-submit" aria-hidden="true">✓</span>
          <div>
            <h2 id="completion-confirm-title">ส่งผลการพัฒนาให้หัวหน้าตรวจ?</h2>
            <p>ตรวจสอบความครบถ้วนของสมรรถนะนี้ก่อนส่ง</p>
          </div>
        </header>
        <div class="confirm-body">
          <div class="completion-confirm-name">
            <span>{{ completionConfirmGroup.code }}</span>
            <strong>{{ completionConfirmGroup.name }}</strong>
          </div>
          <p id="completion-confirm-warning" class="confirm-warning"><strong>หลังจากส่งแล้ว</strong> คุณจะเพิ่มหรือแก้ไขข้อมูลของสมรรถนะนี้ไม่ได้ จนกว่าหัวหน้าจะส่งกลับให้แก้ไข</p>
        </div>
        <footer>
          <button class="secondary" type="button" :disabled="processing" @click="completionConfirmGroup = null">กลับไปตรวจสอบ</button>
          <button class="primary" type="button" :disabled="processing" @click="submitCompletion">{{ processing ? 'กำลังส่ง...' : 'ยืนยันส่งให้หัวหน้าตรวจ' }}</button>
        </footer>
      </article>
    </div>

    <div v-if="updateDialog" class="modal-backdrop" @click.self="closeUpdate">
      <article class="form-dialog" role="dialog" aria-modal="true" aria-labelledby="update-title">
        <header><div class="dialog-title"><small>{{ updateDialog.activity.competencyCode }} · หัวข้อ {{ updateDialog.topicIndex + 1 }}</small><h2 id="update-title">{{ topicTitle(updateDialog.activity, updateDialog.topicIndex) }}</h2><p>บันทึกผลงานในช่วงนี้ โดยสามารถกลับมาเพิ่มครั้งถัดไปได้</p></div><button type="button" aria-label="ปิด" @click="closeUpdate">×</button></header>
        <div class="dialog-body">
          <section class="update-details">
            <div class="date-grid">
              <label><span>วันที่เริ่มต้น <b>*</b></span><input v-model="updateForm.periodStart" type="date" @change="updateForm.periodEnd && updateForm.periodEnd < updateForm.periodStart ? updateForm.periodEnd = '' : null" /></label>
              <label><span>วันที่สิ้นสุด <b>*</b></span><input v-model="updateForm.periodEnd" type="date" :min="updateForm.periodStart || undefined" :disabled="!updateForm.periodStart" /></label>
            </div>
            <label class="field"><span>สิ่งที่ทำไปแล้ว <b>*</b></span><textarea v-model="updateForm.progressNote" rows="4" placeholder="อธิบายสิ่งที่ดำเนินการเสร็จในช่วงวันที่นี้" /></label>
            <p v-if="updateValidationError" class="form-error">{{ updateValidationError }}</p>
          </section>
          <section class="evidence-section">
            <header><div><h3>หลักฐานประกอบ <em>ไม่บังคับ</em></h3><span>แนบไฟล์ รูปภาพ หรือลิงก์ที่เกี่ยวข้อง</span></div></header>
            <label class="file-picker">
              <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" @change="chooseFiles" />
              <span class="file-icon" aria-hidden="true">↑</span>
              <span class="file-copy">
                <strong>{{ updateForm.evidenceFiles.length ? `เลือกแล้ว ${updateForm.evidenceFiles.length} ไฟล์` : 'ยังไม่ได้เลือกไฟล์' }}</strong>
                <small>รองรับรูปภาพ PDF และเอกสาร สูงสุด 10 ไฟล์, ไฟล์ละไม่เกิน 10 MB</small>
              </span>
              <span class="file-action">{{ updateForm.evidenceFiles.length ? 'เลือกไฟล์เพิ่ม' : 'เลือกไฟล์หรือรูปภาพ' }}</span>
            </label>
            <p v-if="fileSelectionError" class="file-error" role="alert">{{ fileSelectionError }}</p>
            <div v-if="updateForm.evidenceFiles.length" class="selected-file-list" aria-label="ไฟล์ที่เลือก">
              <div v-for="(file, index) in updateForm.evidenceFiles" :key="`${file.name}-${file.size}-${file.lastModified}`" class="selected-file">
                <span aria-hidden="true">✓</span>
                <div><strong>{{ file.name }}</strong><small>{{ (file.size / 1024 / 1024).toFixed(2) }} MB</small></div>
                <button type="button" :aria-label="`ลบไฟล์ ${file.name}`" @click="removeEvidenceFile(index)">×</button>
              </div>
            </div>
            <div class="link-list">
              <div v-for="(link, index) in updateForm.evidenceLinks" :key="index" class="link-row">
                <input v-model="link.url" type="url" placeholder="https:// ลิงก์หลักฐาน" />
                <button type="button" aria-label="ลบลิงก์" @click="removeLink(index)">×</button>
              </div>
              <button class="add-link" type="button" @click="addLink">+ เพิ่มลิงก์</button>
            </div>
          </section>
        </div>
        <footer><button class="secondary" type="button" @click="closeUpdate">ยกเลิก</button><button class="primary" type="button" :disabled="processing" @click="requestSaveUpdate">บันทึกอัปเดต</button></footer>
      </article>
    </div>

    <div v-if="confirmUpdateOpen && updateDialog" class="modal-backdrop confirm-backdrop" @click.self="confirmUpdateOpen = false">
      <article class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-update-title" aria-describedby="confirm-update-warning">
        <header>
          <span class="confirm-icon" aria-hidden="true">!</span>
          <div>
            <h2 id="confirm-update-title">ยืนยันการบันทึกอัปเดต</h2>
            <p>ตรวจสอบข้อมูลให้เรียบร้อยก่อนยืนยัน</p>
          </div>
        </header>
        <div class="confirm-body">
          <div class="confirm-summary">
            <div><small>หัวข้อ</small><strong>{{ topicTitle(updateDialog.activity, updateDialog.topicIndex) }}</strong></div>
            <div><small>ช่วงวันที่</small><strong>{{ formatDate(updateForm.periodStart) }} ถึง {{ formatDate(updateForm.periodEnd) }}</strong></div>
            <div><small>สิ่งที่ทำไปแล้ว</small><p>{{ updateForm.progressNote }}</p></div>
            <div><small>หลักฐาน</small><strong>{{ updateForm.evidenceFiles.length }} ไฟล์ · {{ updateForm.evidenceLinks.filter((link) => link.url.trim()).length }} ลิงก์</strong></div>
          </div>
          <p id="confirm-update-warning" class="confirm-warning"><strong>โปรดตรวจสอบให้เรียบร้อย</strong> เมื่อยืนยันแล้ว รายการอัปเดตนี้จะยกเลิกหรือแก้ไขไม่ได้</p>
        </div>
        <footer>
          <button class="secondary" type="button" :disabled="processing" @click="confirmUpdateOpen = false">กลับไปตรวจสอบ</button>
          <button class="primary" type="button" :disabled="processing" @click="saveUpdate">{{ processing ? 'กำลังบันทึก...' : 'ยืนยันและบันทึก' }}</button>
        </footer>
      </article>
    </div>

  </section>
</template>

<style scoped>
.employee-page {
  --progress-green: var(--color-success, #166534);
  --progress-green-dark: #185c4b;
  --progress-line: #d9e3df;
  display: grid;
  gap: 14px;
  color: #22302b;
}
.page-head { padding: 0 0 4px; }
.page-head h1 { margin: 0; color: var(--text); font-size: 22px; font-weight: 900; }
.page-head p { max-width: 70ch; margin: 5px 0 0; color: var(--text3); font-size: 13px; }
.page-error { border: 1px solid #f0aaa0; border-radius: 8px; background: #fff4f2; padding: 10px 13px; color: #b93827; font-size: 13px; font-weight: 800; }
.form-error { margin: -5px 0 0; color: #b93827; font-size: 11px; font-weight: 800; }
.file-error { margin: -3px 0 0; color: #b93827; font-size: 11px; font-weight: 800; }
.empty-card { display: grid; place-items: center; min-height: 220px; border: 1px dashed #cbd8d3; border-radius: 10px; background: #fbfdfc; padding: 28px; text-align: center; }
.empty-card strong { font-size: 16px; }
.empty-card span { margin-top: 5px; color: #768395; font-size: 13px; }
.competency-switcher { display: flex; gap: 8px; max-width: 100%; overflow-x: auto; padding: 2px 1px 9px; scroll-snap-type: x proximity; }
.competency-tab { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 10px; flex: 0 0 min(390px, 86vw); min-height: 72px; border: 1px solid #d7e1dd; border-radius: 8px; background: #fff; padding: 10px 12px; color: #34443e; font: inherit; text-align: left; cursor: pointer; scroll-snap-align: start; transition: border-color .18s ease, background .18s ease, box-shadow .18s ease; }
.competency-tab:hover { border-color: #91baad; background: #f8fbfa; }
.competency-tab.selected { border-color: var(--progress-green); background: #eaf4f0; box-shadow: inset 0 -2px 0 var(--progress-green); }
.competency-tab:focus-visible { outline: 3px solid rgba(36, 114, 96, .2); outline-offset: 2px; }
.competency-tab-code { display: grid; place-items: center; min-width: 62px; min-height: 40px; border-radius: 7px; background: #edf3f1; padding: 0 10px; color: var(--progress-green-dark); font-size: 13px; font-weight: 900; }
.competency-tab.selected .competency-tab-code { background: var(--progress-green); color: #f9fffc; }
.competency-tab-copy { display: grid; min-width: 0; gap: 3px; }
.competency-tab-copy strong { font-size: 13px; font-weight: 800; line-height: 1.45; overflow-wrap: anywhere; }
.competency-tab-copy small { color: #74827c; font-size: 10px; font-weight: 700; line-height: 1.4; white-space: normal; }
.competency-section { border: 1px solid #cbdad5; border-radius: 10px; background: #eef3f1; box-shadow: 0 5px 18px rgba(35, 67, 57, .05); }
.competency-head { position: relative; z-index: 2; display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 18px; border-radius: 10px 10px 0 0; border-bottom: 1px solid var(--progress-line); background: #edf5f2; padding: 13px 16px; }
.competency-copy { display: flex; align-items: center; gap: 12px; min-width: 0; }
.competency-copy > span { flex: 0 0 auto; border: 1px solid #9fc9bb; border-radius: 6px; background: #fbfdfc; padding: 6px 9px; color: var(--progress-green-dark); font-size: 13px; font-weight: 900; }
.competency-copy small { color: #6e7f79; font-size: 11px; font-weight: 800; }
.competency-copy h2 { overflow: hidden; margin: 2px 0 0; font-size: 16px; font-weight: 800; line-height: 1.45; text-overflow: ellipsis; white-space: nowrap; }
.completion-actions { display: flex; align-items: center; justify-content: flex-end; gap: 9px; flex-wrap: wrap; }
.review-feedback { margin: 14px 16px 0; border: 1px solid #efc4bc; border-left: 4px solid #cf5b48; border-radius: 8px; background: #fff8f6; padding: 12px 14px; color: #49342f; }
.review-feedback.approved { border-color: #b9d9cf; border-left-color: var(--progress-green); background: #f5fbf8; }
.review-feedback > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 8px; }
.review-feedback > header div { display: grid; gap: 2px; }
.review-feedback small, .review-feedback time { color: #7d8984; font-size: 10px; font-weight: 700; }
.review-feedback strong { font-size: 13px; }
.review-feedback p { margin: 5px 0 0; font-size: 12px; line-height: 1.6; white-space: pre-wrap; }
.topic-count { color: #667085; font-size: 12px; font-weight: 800; }
.status-pill { border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 900; }
.status-pill.active { background: #dfeee9; color: #216c59; }
.status-pill.pending { background: #fff4d9; color: #98600c; }
.status-pill.returned { background: #ffebe8; color: #b93827; }
.status-pill.done { background: #dff5e7; color: #147745; }
.submit-completion-wrap { position: relative; display: inline-flex; border-radius: 7px; }
.submit-completion-wrap.has-tooltip { cursor: not-allowed; }
.submit-tooltip { position: absolute; right: 0; top: calc(100% + 9px); width: max-content; max-width: 240px; border-radius: 7px; background: #2d3834; padding: 7px 10px; color: #f8fbfa; font-size: 11px; font-weight: 700; line-height: 1.45; pointer-events: none; opacity: 0; transform: translateY(-3px); transition: opacity .16s ease, transform .16s ease; }
.submit-tooltip::before { position: absolute; right: 22px; bottom: 100%; border: 5px solid transparent; border-bottom-color: #2d3834; content: ''; }
.submit-completion-wrap.has-tooltip:hover .submit-tooltip,
.submit-completion-wrap.has-tooltip:focus-visible .submit-tooltip { opacity: 1; transform: translateY(0); }
.submit-completion-wrap:focus-visible { outline: 3px solid rgba(36, 114, 96, .2); outline-offset: 2px; }
.submit-completion { border: 1px solid var(--progress-green); border-radius: 7px; background: var(--progress-green); padding: 7px 11px; color: #f9fffc; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; transition: background .18s ease, border-color .18s ease; }
.submit-completion:hover { border-color: var(--progress-green-dark); background: var(--progress-green-dark); }
.submit-completion:disabled { border-color: #cbd4d0; background: #e6ebe9; color: #7a8782; pointer-events: none; }
.activity-block { overflow: hidden; margin: 12px; border: 1px solid #d2ded9; border-radius: 9px; background: #fff; box-shadow: 0 2px 8px rgba(35, 67, 57, .04); }
.activity-block + .activity-block { margin-top: 16px; }
.activity-head { display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; align-items: center; gap: 12px; border-bottom: 1px solid #dbe5e1; background: #f6f9f8; padding: 12px 14px; }
.activity-number { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 6px; background: #dcece6; color: #226d59; font-size: 12px; font-weight: 900; }
.activity-head small { color: #28735f; font-size: 11px; font-weight: 900; }
.activity-head h3 { margin: 1px 0 0; font-size: 14px; font-weight: 800; line-height: 1.4; }
.activity-head > strong { border-radius: 999px; background: #e8f2ee; padding: 4px 8px; color: #286c5b; font-size: 11px; font-weight: 900; }
.topic-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: 'summary actions' 'timeline timeline'; align-items: start; gap: 14px 18px; padding: 15px 16px 16px; background: #fff; }
.topic-row + .topic-row { border-top: 8px solid #eef3f1; }
.topic-summary { grid-area: summary; display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 2px 10px; min-width: 0; }
.topic-index { color: var(--progress-green); font-size: 11px; font-weight: 900; }
.topic-summary h4 { margin: 0; font-size: 14px; font-weight: 800; line-height: 1.5; overflow-wrap: anywhere; }
.topic-summary p { grid-column: 2; margin: 0; color: #7a8792; font-size: 11px; }
.timeline { grid-area: timeline; display: grid; gap: 8px; min-width: 0; }
.timeline-entry { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px 16px; border: 1px solid #d7e2de; border-radius: 8px; background: #f8fbfa; padding: 13px; }
.timeline-date { display: flex; align-items: center; gap: 10px; }
.timeline-date > span { display: grid; gap: 1px; border-radius: 6px; background: #edf5f2; padding: 6px 9px; }
.timeline-date small { color: #718079; font-size: 9px; font-weight: 700; }
.timeline-date strong { color: #286c5b; font-size: 11px; font-weight: 900; white-space: nowrap; }
.timeline-date > b { color: #809089; font-size: 13px; font-weight: 700; }
.timeline-note { grid-column: 1 / -1; display: grid; gap: 3px; }
.timeline-note > small { color: #6d7b76; font-size: 10px; font-weight: 800; }
.timeline-note p { margin: 0; color: #34443e; font-size: 13px; line-height: 1.55; white-space: pre-wrap; }
.timeline-entry > small { grid-column: 2; grid-row: 1; color: #8a95a2; font-size: 10px; white-space: nowrap; }
.no-update { display: flex; align-items: center; gap: 10px; min-height: 48px; border: 1px dashed #cbd8d3; border-radius: 8px; background: #fafcfb; padding: 9px 11px; color: #687871; }
.no-update > span { display: grid; place-items: center; flex: 0 0 24px; width: 24px; height: 24px; border: 1px solid #cbd8d3; border-radius: 50%; color: #91a29b; font-size: 13px; }
.no-update div { display: grid; gap: 1px; }
.no-update strong { font-size: 12px; font-weight: 800; }
.no-update small { color: #8a9691; font-size: 10px; }
.evidence-list { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 6px; margin: 0; }
.evidence-list a { border-radius: 5px; background: #e8f2ee; padding: 4px 7px; color: #216b58; font-size: 11px; font-weight: 800; text-decoration: none; }
.evidence-list a:hover { text-decoration: underline; }
.topic-actions { grid-area: actions; display: flex; align-content: start; gap: 7px; }
.topic-actions button { min-width: 126px; border-radius: 7px; padding: 8px 11px; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; transition: background .18s ease, border-color .18s ease, color .18s ease; }
.update-button { border: 1px solid var(--progress-green); background: var(--progress-green); color: #f9fffc; }
.update-button:hover { border-color: var(--progress-green-dark); background: var(--progress-green-dark); }
.topic-actions button:focus-visible, .submit-completion:focus-visible, .form-dialog button:focus-visible, .result-dialog button:focus-visible { outline: 3px solid rgba(36, 114, 96, .2); outline-offset: 2px; }
.modal-backdrop { position: fixed; z-index: 1200; inset: 0; display: grid; place-items: center; background: rgba(18, 28, 25, .68); padding: 18px; }
.confirm-backdrop { z-index: 1300; background: rgba(18, 28, 25, .76); }
.form-dialog, .result-dialog { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; width: min(760px, 100%); max-height: calc(100vh - 36px); overflow: hidden; border: 1px solid #d5dfdb; border-radius: 12px; background: #f7faf9; box-shadow: 0 28px 80px rgba(12, 27, 22, .28); }
.confirm-dialog { width: min(500px, 100%); overflow: hidden; border: 1px solid #d7e0dc; border-radius: 12px; background: #fcfefd; box-shadow: 0 28px 80px rgba(12, 27, 22, .35); }
.confirm-dialog > header { display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #e0e6e3; padding: 17px 18px; }
.confirm-dialog > header h2 { margin: 0; font-size: 17px; }
.confirm-dialog > header p { margin: 3px 0 0; color: #74817c; font-size: 11px; }
.confirm-icon { display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border-radius: 50%; background: #fff1d6; color: #a7650c; font-size: 18px; font-weight: 900; }
.confirm-icon.is-submit { background: #dfeee9; color: #216c59; }
.confirm-body { display: grid; gap: 13px; padding: 16px 18px; }
.completion-confirm-name { display: grid; gap: 4px; }
.completion-confirm-name span { color: var(--progress-green); font-size: 11px; font-weight: 900; }
.completion-confirm-name strong { color: #263831; font-size: 15px; line-height: 1.45; }
.confirm-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.confirm-summary > div { display: grid; align-content: start; gap: 3px; min-width: 0; }
.confirm-summary > div:nth-child(3) { grid-column: 1 / -1; }
.confirm-summary small { color: #78857f; font-size: 10px; font-weight: 800; }
.confirm-summary strong, .confirm-summary p { margin: 0; color: #30413a; font-size: 12px; font-weight: 700; line-height: 1.5; overflow-wrap: anywhere; }
.confirm-summary p { max-height: 5.8em; overflow-y: auto; white-space: pre-wrap; }
.confirm-warning { margin: 0; border: 1px solid #efcf91; border-radius: 8px; background: #fff8e8; padding: 10px 12px; color: #79500b; font-size: 12px; line-height: 1.55; }
.confirm-dialog > footer { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #e0e6e3; padding: 12px 18px; }
.confirm-dialog > footer button { border-radius: 7px; padding: 8px 12px; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
.confirm-dialog > footer button:disabled { opacity: .55; cursor: wait; }
.result-dialog { width: min(680px, 100%); }
.form-dialog > header, .result-dialog > header { display: flex; justify-content: space-between; gap: 20px; border-bottom: 1px solid #dae3df; background: #fcfefd; padding: 18px 20px 16px; }
.form-dialog header small, .result-dialog header small { color: var(--progress-green); font-size: 11px; font-weight: 900; }
.form-dialog header h2, .result-dialog header h2 { margin: 4px 0 0; font-size: 18px; }
.dialog-title p { margin: 5px 0 0; color: #76837e; font-size: 11px; font-weight: 600; }
.form-dialog header button, .result-dialog header button { flex: none; width: 34px; height: 34px; border: 1px solid #ccd8d3; border-radius: 7px; background: #fff; color: #53635d; font-size: 20px; cursor: pointer; }
.dialog-body { overflow-y: auto; display: grid; gap: 18px; padding: 18px 20px 20px; }
.dialog-help { margin: 0; color: #65756f; font-size: 13px; line-height: 1.6; }
.update-details { display: grid; gap: 14px; }
.date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field, .date-grid label, .file-picker { display: grid; gap: 6px; color: #45534e; font-size: 12px; font-weight: 900; }
.field b, .date-grid b { color: #c43d2b; }
input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #cbd7d2; border-radius: 7px; background: #fff; padding: 9px 10px; color: #25332e; font: inherit; font-size: 13px; font-weight: 400; }
textarea { resize: vertical; line-height: 1.6; }
input:focus, textarea:focus { outline: none; border-color: #3f8b76; box-shadow: 0 0 0 3px rgba(63, 139, 118, .14); }
input:disabled { background: #eef2f0; color: #9aa5a0; }
.evidence-section { display: grid; gap: 11px; border-top: 1px solid #dbe4e0; padding-top: 17px; }
.evidence-section h3 { margin: 0; font-size: 15px; }
.evidence-section h3 em { margin-left: 5px; border-radius: 999px; background: #e7eeeb; padding: 3px 7px; color: #66736e; font-size: 9px; font-style: normal; vertical-align: 2px; }
.evidence-section header span { color: #78867f; font-size: 11px; }
.file-picker { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; align-items: center; gap: 12px; border: 1px dashed #8fb5a8; border-radius: 9px; background: #f2f8f5; padding: 14px 15px; cursor: pointer; transition: border-color .18s ease, background .18s ease; }
.file-picker:hover { border-color: var(--progress-green); background: #edf6f2; }
.file-picker input { position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0; }
.file-icon { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: #dcece6; color: var(--progress-green-dark); font-size: 19px; font-weight: 700; }
.file-copy { display: grid; gap: 2px; min-width: 0; }
.file-copy strong { color: #34443e; font-size: 12px; font-weight: 800; }
.file-copy small { color: #697872; font-size: 11px; font-weight: 500; line-height: 1.45; }
.file-action { border: 1px solid #87ad9f; border-radius: 7px; background: #fff; padding: 8px 11px; color: var(--progress-green-dark); font-size: 11px; font-weight: 900; white-space: nowrap; }
.selected-file-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; }
.selected-file { display: grid; grid-template-columns: 22px minmax(0, 1fr) 30px; align-items: center; gap: 8px; border: 1px solid #d6e2de; border-radius: 7px; background: #fff; padding: 7px 8px; }
.selected-file > span { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: #e1f1eb; color: var(--progress-green); font-size: 10px; font-weight: 900; }
.selected-file > div { display: grid; min-width: 0; gap: 1px; }
.selected-file strong { overflow: hidden; color: #34443e; font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.selected-file small { color: #7a8782; font-size: 9px; font-weight: 500; }
.selected-file button { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: #b93827; font-size: 16px; cursor: pointer; }
.selected-file button:hover { background: #fff0ed; }
.link-list { display: grid; gap: 7px; }
.link-row { display: grid; grid-template-columns: minmax(0, 1fr) 36px; gap: 7px; }
.link-row button { border: 1px solid #e3b2ab; border-radius: 7px; background: #fff; color: #b93827; font-size: 18px; cursor: pointer; }
.add-link { justify-self: start; border: 0; background: transparent; color: var(--progress-green); font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
.form-dialog > footer, .result-dialog > footer { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #dae3df; background: #fcfefd; padding: 12px 20px; }
.form-dialog footer button, .result-dialog footer button { border-radius: 7px; padding: 8px 12px; font: inherit; font-size: 12px; font-weight: 900; cursor: pointer; }
.secondary { border: 1px solid #cbd6d2; background: #fff; color: #53615c; }
.primary { border: 1px solid var(--progress-green); background: var(--progress-green); color: #f9fffc; }
.form-dialog footer button:disabled, .result-dialog footer button:disabled { opacity: .55; cursor: wait; }
.result-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.result-options label { display: grid; grid-template-columns: auto 1fr; gap: 3px 8px; border: 1px solid #ced9d5; border-radius: 8px; background: #fff; padding: 12px; cursor: pointer; }
.result-options label.selected { border-color: #4c9a83; background: #edf7f3; }
.result-options input { grid-row: 1 / 3; width: auto; align-self: start; margin-top: 4px; accent-color: var(--progress-green); }
.result-options strong { font-size: 14px; }
.result-options span { color: #74817c; font-size: 11px; line-height: 1.45; }
@media (max-width: 1100px) {
  .topic-row { grid-template-columns: minmax(0, 1fr) auto; }
}
@media (max-width: 900px) {
  .competency-head { grid-template-columns: 1fr; }
  .completion-actions { justify-content: flex-start; }
  .topic-row { grid-template-columns: 1fr; grid-template-areas: 'summary' 'timeline' 'actions'; gap: 12px; }
  .topic-actions { justify-content: flex-start; }
  .timeline-entry { grid-template-columns: 1fr; }
  .timeline-note, .evidence-list, .timeline-entry > small { grid-column: 1; grid-row: auto; }
  .activity-head { grid-template-columns: 30px minmax(0, 1fr); }
  .activity-head > strong { grid-column: 2; width: max-content; }
  .date-grid { grid-template-columns: 1fr; }
}
@media (max-width: 620px) {
  .page-head h1 { font-size: 20px; }
  .activity-block { margin: 8px; }
  .activity-block + .activity-block { margin-top: 12px; }
  .competency-head, .activity-head, .topic-row { padding: 12px; }
  .competency-copy { align-items: flex-start; }
  .completion-actions, .submit-completion-wrap, .submit-completion { width: 100%; }
  .submit-completion { text-align: center; }
  .topic-actions { display: grid; grid-template-columns: 1fr; }
  .topic-actions button { width: 100%; }
  .modal-backdrop { padding: 0; }
  .form-dialog, .result-dialog { width: 100%; height: 100vh; max-height: 100vh; border: 0; border-radius: 0; }
  .confirm-dialog { width: calc(100% - 24px); }
  .confirm-summary { grid-template-columns: 1fr; }
  .confirm-summary > div:nth-child(3) { grid-column: auto; }
  .confirm-dialog > footer { display: grid; grid-template-columns: 1fr 1fr; }
  .link-row, .result-options { grid-template-columns: 1fr; }
  .file-picker { grid-template-columns: 38px 1fr; }
  .file-action { grid-column: 1 / -1; text-align: center; }
  .selected-file-list { grid-template-columns: 1fr; }
  .link-row button { width: 36px; }
  .form-dialog > footer { display: grid; grid-template-columns: 1fr 1fr; }
  .form-dialog > footer .primary { grid-column: 1 / -1; }
}
</style>
