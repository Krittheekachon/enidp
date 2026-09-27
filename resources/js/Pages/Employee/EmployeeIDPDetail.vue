<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = defineProps<{ activities?: any[] }>();
const activities = computed(() => props.activities || []);
const groups = computed(() => {
  const map = new Map<number, any>();
  activities.value.forEach((activity) => {
    if (!map.has(activity.idpItemId)) map.set(activity.idpItemId, {
      id: activity.idpItemId,
      code: activity.competencyCode,
      name: activity.competencyName,
      goal: activity.goal,
      successCriteria: activity.successCriteria,
      completion: activity.completion,
      activities: [],
    });
    map.get(activity.idpItemId).activities.push(activity);
  });
  return [...map.values()];
});

const selectedGroupId = ref<number | null>(null);
const selectedActivityId = ref<number | null>(null);
const selectedTopicIndex = ref(0);

watch(groups, (value) => {
  if (!value.length) {
    selectedGroupId.value = null;
    return;
  }
  if (!value.some((group) => group.id === selectedGroupId.value)) selectedGroupId.value = value[0].id;
}, { immediate: true });

const selectedGroup = computed(() => groups.value.find((group) => group.id === selectedGroupId.value) || null);
watch(selectedGroup, (group) => {
  if (!group) {
    selectedActivityId.value = null;
    return;
  }
  if (!group.activities.some((activity: any) => activity.id === selectedActivityId.value)) {
    selectedActivityId.value = group.activities[0]?.id ?? null;
  }
}, { immediate: true });

const selectedActivity = computed(() => selectedGroup.value?.activities.find((activity: any) => activity.id === selectedActivityId.value) || null);
watch(selectedActivityId, () => { selectedTopicIndex.value = 0; });

const planRows = (activity: any) => activity?.planDetails?.planRows?.length ? activity.planDetails.planRows : [{}];
const topicTitle = (activity: any, index: number) => {
  const row = planRows(activity)[index] || {};
  return row.assignmentTopic || row.skillTopic || row.topic || row.learningTopic || row.courseName || activity.name;
};
const updateSubmittedTime = (update: any) => {
  const timestamp = new Date(update?.submittedAt || '').getTime();
  return Number.isNaN(timestamp) ? Number.MAX_SAFE_INTEGER : timestamp;
};
const visibleUpdates = (activity: any, index: number) => (activity?.updates || [])
  .filter((item: any) => item.topicIndex === index && item.status !== 'draft')
  .slice()
  .sort((left: any, right: any) => updateSubmittedTime(left) - updateSubmittedTime(right));
const activityUpdateCount = (activity: any) => planRows(activity).reduce((sum: number, _row: any, index: number) => sum + visibleUpdates(activity, index).length, 0);
const activityEvidenceCount = (activity: any) => (activity?.updates || []).reduce((sum: number, update: any) => sum + (update.evidences || []).length, 0);
const groupUpdateCount = (group: any) => (group?.activities || []).reduce((sum: number, activity: any) => sum + activityUpdateCount(activity), 0);
const groupEvidenceCount = (group: any) => (group?.activities || []).reduce((sum: number, activity: any) => sum + activityEvidenceCount(activity), 0);

const completionMeta = (completion: any) => {
  if (!completion) return { label: 'กำลังดำเนินการ', tone: 'active' };
  if (completion.status === 'approved' && completion.result === 'failed') return { label: 'ไม่ผ่าน · วางแผน IDP ใหม่', tone: 'failed' };
  if (completion.status === 'approved') return { label: 'อนุมัติผลแล้ว', tone: 'done' };
  if (completion.status === 'revision_required') return { label: 'ถูกส่งกลับให้แก้ไข', tone: 'returned' };
  if (/^review_step_\d+$/.test(completion.status || '')) return { label: `รอตรวจลำดับที่ ${completion.currentReviewStep || 1}`, tone: 'pending' };
  return { label: 'กำลังดำเนินการ', tone: 'active' };
};
const evidenceLabel = (kind: string) => kind === 'link' ? 'ลิงก์' : kind === 'image' ? 'รูปภาพ' : 'ไฟล์';
const formatDate = (value?: string) => {
  if (!value) return 'ไม่ระบุ';
  const date = new Date(value.length > 10 ? value : `${value}T00:00:00`);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
};
const formatDateTime = (value?: string | null) => {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleString('th-TH', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
const operationResultLabel = (value?: string | null) => value === 'as_planned'
  ? 'เป็นไปตามแผน'
  : value === 'not_as_planned'
    ? 'ไม่เป็นไปตามแผน'
    : '';
const achievementResultLabel = (value?: string | null) => ({
  exceeded: 'บรรลุเกินเป้าหมาย',
  met: 'บรรลุตามเป้าหมาย',
  not_met: 'ไม่บรรลุผล ควรพัฒนาต่อ',
}[value || ''] || '');
const selectedReviewSummary = computed(() => {
  const completion = selectedGroup.value?.completion;
  if (!['approved', 'revision_required'].includes(completion?.status || '')) return null;

  const decision = completion.status === 'approved' ? 'approved' : 'rejected';
  const matchingHistory = (completion.reviewHistory || [])
    .filter((review: any) => review.decision === decision)
    .slice()
    .sort((left: any, right: any) => new Date(right.decidedAt || 0).getTime() - new Date(left.decidedAt || 0).getTime());
  const review = matchingHistory[0];
  const reviewData = review?.reviewData || {};
  const currentReview = completion.review || {};

  return {
    decision,
    submissionVersion: Number(review?.submissionVersion || completion.submissionVersion || 0),
    reviewerName: review?.reviewerName || currentReview.reviewerName || 'ผู้บังคับบัญชา',
    decidedAt: review?.decidedAt || currentReview.decidedAt || completion.approvedAt || completion.updatedAt,
    operationResult: operationResultLabel(reviewData.operationStatus || currentReview.operationStatus) || 'ไม่ได้ระบุ',
    achievementResult: achievementResultLabel(reviewData.achievementStatus || currentReview.achievementStatus) || 'ไม่ได้ระบุ',
    operationReason: reviewData.operationReason || currentReview.operationReason || '',
    achievementNote: reviewData.achievementNote || currentReview.achievementNote || '',
    comment: review?.comment || currentReview.comment || '',
  };
});
const completionFlowEvents = (completion: any) => {
  if (!completion) return [];

  const submissionVersion = Number(completion.submissionVersion || 0);
  const showRound = submissionVersion > 1 || (completion.reviewHistory || []).some((review: any) => Number(review.submissionVersion) > 1);
  const events = (completion.reviewHistory || [])
    .map((review: any) => ({
      key: `${review.submissionVersion}-${review.reviewStep}-${review.decidedAt}`,
      type: review.decision === 'approved' ? 'approved' : 'rejected',
      title: (review.decision === 'approved' ? 'ผู้บังคับบัญชาอนุมัติ' : 'ผู้บังคับบัญชาส่งกลับให้แก้ไข') + (showRound ? ` · รอบที่ ${review.submissionVersion}` : ''),
      actor: review.reviewerName || 'ผู้บังคับบัญชา',
      detail: review.comment || '',
      operationResult: operationResultLabel(review.reviewData?.operationStatus),
      operationReason: review.reviewData?.operationReason || '',
      achievementResult: achievementResultLabel(review.reviewData?.achievementStatus),
      achievementNote: review.reviewData?.achievementNote || '',
      at: review.decidedAt,
      submissionVersion: Number(review.submissionVersion || 0),
    }));

  const terminalType = completion.status === 'approved'
    ? 'approved'
    : completion.status === 'revision_required'
      ? 'rejected'
      : null;
  if (terminalType && !events.some((event: any) => event.type === terminalType && event.submissionVersion === submissionVersion)) {
    events.push({
      key: `fallback-${completion.publicId}-${terminalType}`,
      type: terminalType,
      title: (terminalType === 'approved' ? 'ผู้บังคับบัญชาอนุมัติ' : 'ผู้บังคับบัญชาส่งกลับให้แก้ไข') + (showRound ? ` · รอบที่ ${submissionVersion}` : ''),
      actor: completion.review?.reviewerName || 'ผู้บังคับบัญชา',
      detail: completion.review?.comment || '',
      operationResult: operationResultLabel(completion.review?.operationStatus),
      operationReason: completion.review?.operationReason || '',
      achievementResult: achievementResultLabel(completion.review?.achievementStatus),
      achievementNote: completion.review?.achievementNote || '',
      at: completion.review?.decidedAt || completion.updatedAt || null,
      submissionVersion,
    });
  }

  return events.sort((left: any, right: any) => new Date(left.at || 0).getTime() - new Date(right.at || 0).getTime());
};
const pendingCompletionEvent = (completion: any) => /^review_step_\d+$/.test(completion?.status || '')
  ? {
      type: 'pending',
      title: Number(completion.submissionVersion || 0) > 1
        ? `รอผู้บังคับบัญชาตรวจสอบ · รอบที่ ${completion.submissionVersion}`
        : 'รอผู้บังคับบัญชาตรวจสอบ',
      actor: completion.currentReviewerName || 'ผู้บังคับบัญชา',
    }
  : null;
const updateSequence = (activity: any, topicIndex: number, update: any) => visibleUpdates(activity, topicIndex)
  .findIndex((candidate: any) => candidate.publicId === update.publicId) + 1;
const timelineTimestamp = (value?: string | null) => {
  const timestamp = new Date(value || '').getTime();
  return Number.isNaN(timestamp) ? Number.MAX_SAFE_INTEGER : timestamp;
};
const topicTimelineEntries = (activity: any, topicIndex: number, completion: any) => {
  const entries: any[] = visibleUpdates(activity, topicIndex).map((update: any) => ({
    kind: 'update', key: `update-${update.publicId}`, at: update.submittedAt, priority: 0, update,
  }));

  if (completion?.submittedAt) entries.push({
    kind: 'submitted',
    key: `submitted-${completion.submissionVersion}-${completion.submittedAt}`,
    at: completion.submittedAt,
    priority: 1,
    round: Number(completion.submissionVersion || 0),
  });
  completionFlowEvents(completion).forEach((event: any) => entries.push({
    kind: 'review', key: `review-${event.key}`, at: event.at, priority: 2, event,
  }));
  const pending = pendingCompletionEvent(completion);
  if (pending) entries.push({ kind: 'pending', key: `pending-${completion.publicId}`, at: completion.submittedAt, priority: 3, event: pending });

  return entries.sort((left, right) => timelineTimestamp(left.at) - timelineTimestamp(right.at) || left.priority - right.priority);
};
</script>

<template>
  <section class="detail-page">
    <header class="page-head">
      <div><h1>รายละเอียด IDP</h1><p>ทบทวนแผน ประวัติที่เคยทำ หลักฐาน และผลตรวจของหัวหน้า</p></div>
      <span>อ่านอย่างเดียว</span>
    </header>

    <div v-if="!groups.length" class="empty">
      <strong>ยังไม่มีรายละเอียด IDP</strong>
      <span>ข้อมูลจะแสดงเมื่อมีแผนที่ผ่านการอนุมัติแล้ว</span>
    </div>

    <template v-else>
      <nav class="competency-picker" aria-label="เลือกสมรรถนะ">
        <button
          v-for="group in groups"
          :key="group.id"
          type="button"
          :class="{ active: group.id === selectedGroupId }"
          :aria-pressed="group.id === selectedGroupId"
          @click="selectedGroupId = group.id"
        >
          <span>{{ group.code }}</span>
          <div><strong>{{ group.name }}</strong><small>{{ completionMeta(group.completion).label }}</small></div>
        </button>
      </nav>

      <section v-if="selectedGroup" class="record">
        <header class="record-head">
          <div class="record-identity">
            <span>{{ selectedGroup.code }}</span>
            <div><small>สมรรถนะที่ต้องพัฒนา</small><h2>{{ selectedGroup.name }}</h2></div>
          </div>
          <strong class="status" :class="completionMeta(selectedGroup.completion).tone">{{ completionMeta(selectedGroup.completion).label }}</strong>
        </header>

        <div class="record-facts">
          <div><span>กิจกรรม</span><strong>{{ selectedGroup.activities.length }}</strong></div>
          <div><span>อัปเดตที่ส่งแล้ว</span><strong>{{ groupUpdateCount(selectedGroup) }}</strong></div>
          <div><span>หลักฐาน</span><strong>{{ groupEvidenceCount(selectedGroup) }}</strong></div>
          <div class="plan-fact"><span>เป้าหมายการพัฒนา</span><p>{{ selectedGroup.goal || 'ไม่ระบุ' }}</p></div>
          <div class="plan-fact"><span>ตัวชี้วัดผลสำเร็จ</span><p>{{ selectedGroup.successCriteria || 'ไม่ระบุ' }}</p></div>
        </div>

        <section
          v-if="selectedReviewSummary"
          class="approval-summary"
          :class="{
            'needs-development': selectedReviewSummary.decision === 'approved' && selectedGroup.completion?.result === 'failed',
            returned: selectedReviewSummary.decision === 'rejected',
          }"
          aria-labelledby="review-summary-title"
        >
          <header class="approval-summary-head">
            <span class="approval-mark" aria-hidden="true">{{ selectedReviewSummary.decision === 'rejected' ? '↩' : selectedGroup.completion?.result === 'failed' ? '!' : '✓' }}</span>
            <div>
              <small>ผลการตรวจสอบล่าสุด</small>
              <h3 id="review-summary-title">
                <template v-if="selectedReviewSummary.decision === 'rejected'">ถูกส่งกลับให้แก้ไข<template v-if="selectedReviewSummary.submissionVersion > 1"> · รอบที่ {{ selectedReviewSummary.submissionVersion }}</template></template>
                <template v-else>{{ selectedGroup.completion?.result === 'failed' ? 'ตรวจผลแล้ว · ต้องพัฒนาต่อ' : 'อนุมัติผลการพัฒนาแล้ว' }}</template>
              </h3>
              <p>{{ selectedReviewSummary.reviewerName }} · {{ formatDateTime(selectedReviewSummary.decidedAt) }}</p>
            </div>
          </header>

          <div v-if="selectedReviewSummary.decision === 'rejected'" class="latest-return-comment">
            <span>ความคิดเห็นรอบล่าสุด</span>
            <p>{{ selectedReviewSummary.comment || 'ไม่ได้ระบุความคิดเห็น' }}</p>
          </div>
          <dl v-else class="approval-results">
            <div><dt>ผลการดำเนินงาน</dt><dd>{{ selectedReviewSummary.operationResult }}</dd></div>
            <div><dt>ผลการพัฒนา</dt><dd>{{ selectedReviewSummary.achievementResult }}</dd></div>
          </dl>

          <div v-if="selectedReviewSummary.decision === 'approved' && (selectedReviewSummary.operationReason || selectedReviewSummary.achievementNote || selectedReviewSummary.comment)" class="approval-notes">
            <p v-if="selectedReviewSummary.operationReason"><span>เหตุผลการดำเนินงาน</span>{{ selectedReviewSummary.operationReason }}</p>
            <p v-if="selectedReviewSummary.achievementNote"><span>สิ่งที่ควรพัฒนาต่อ</span>{{ selectedReviewSummary.achievementNote }}</p>
            <p v-if="selectedReviewSummary.comment"><span>ความคิดเห็นจากผู้ตรวจ</span>{{ selectedReviewSummary.comment }}</p>
          </div>
        </section>

        <div class="history-workspace">
          <aside class="activity-nav">
            <header><span>กิจกรรมในแผน</span><small>เลือกเพื่อดูประวัติ</small></header>
            <button
              v-for="(activity, activityIndex) in selectedGroup.activities"
              :key="activity.id"
              type="button"
              :class="{ active: activity.id === selectedActivityId }"
              :aria-pressed="activity.id === selectedActivityId"
              @click="selectedActivityId = activity.id"
            >
              <span>{{ activityIndex + 1 }}</span>
              <div><strong>{{ activity.name }}</strong><small>{{ activityUpdateCount(activity) }} อัปเดต · {{ activityEvidenceCount(activity) }} หลักฐาน</small></div>
              <b>{{ activity.weightPercent }}%</b>
            </button>
          </aside>

          <main v-if="selectedActivity" class="history-panel">
            <header class="activity-head">
              <div><span>กิจกรรมที่เลือก</span><h3>{{ selectedActivity.name }}</h3></div>
              <p>{{ formatDate(selectedActivity.startDate) }} ถึง {{ formatDate(selectedActivity.endDate) }}</p>
            </header>

            <nav v-if="planRows(selectedActivity).length > 1" class="topic-picker" aria-label="เลือกหัวข้อกิจกรรม">
              <button
                v-for="(_row, topicIndex) in planRows(selectedActivity)"
                :key="topicIndex"
                type="button"
                :class="{ active: topicIndex === selectedTopicIndex }"
                @click="selectedTopicIndex = topicIndex"
              >หัวข้อ {{ topicIndex + 1 }}</button>
            </nav>

            <section class="topic-overview">
              <span>หัวข้อ {{ selectedTopicIndex + 1 }}</span>
              <h4>{{ topicTitle(selectedActivity, selectedTopicIndex) }}</h4>
            </section>

            <section class="timeline-section chronological-history">
              <header><div><span>ประวัติความก้าวหน้าและการตรวจสอบ</span><small>เรียงตามวันและเวลาที่เกิดขึ้น</small></div><strong>{{ visibleUpdates(selectedActivity, selectedTopicIndex).length }} อัปเดต</strong></header>
              <div class="chronological-entries">
                <template v-for="entry in topicTimelineEntries(selectedActivity, selectedTopicIndex, selectedGroup.completion)" :key="entry.key">
                  <div v-if="entry.kind === 'update'" class="timeline-update-row">
                    <span class="timeline-number">{{ updateSequence(selectedActivity, selectedTopicIndex, entry.update) }}</span>
                    <article>
                      <header><strong>อัปเดตครั้งที่ {{ updateSequence(selectedActivity, selectedTopicIndex, entry.update) }}</strong><time>{{ formatDate(entry.update.periodStart) }} ถึง {{ formatDate(entry.update.periodEnd) }}</time></header>
                      <div class="update-note"><span>สิ่งที่ทำไปแล้ว</span><p>{{ entry.update.progressNote }}</p></div>
                      <div v-if="entry.update.evidences?.length" class="evidences"><span>หลักฐานประกอบ</span><div><a v-for="evidence in entry.update.evidences" :key="evidence.publicId" :href="evidence.url" target="_blank" rel="noopener noreferrer">{{ evidenceLabel(evidence.kind) }}: {{ evidence.name }}</a></div></div>
                      <small v-if="entry.update.submittedAt" class="submitted-at">อัปเดตเมื่อ {{ formatDateTime(entry.update.submittedAt) }}</small>
                    </article>
                  </div>
                  <div v-else-if="entry.kind === 'submitted'" class="completion-flow-line submitted"><div class="flow-line-copy"><strong>ส่งให้ผู้บังคับบัญชา<template v-if="entry.round > 1"> · รอบที่ {{ entry.round }}</template></strong><span>คุณ · {{ formatDateTime(entry.at) }}</span></div></div>
                  <div v-else-if="entry.kind === 'review'" class="completion-flow-line" :class="entry.event.type">
                    <div class="flow-line-copy">
                      <strong>{{ entry.event.title }}</strong><span>{{ entry.event.actor }} · {{ formatDateTime(entry.event.at) }}</span>
                      <div class="flow-review-results"><span>ผลการดำเนินงาน <b>{{ entry.event.operationResult || 'ไม่ได้ระบุ' }}</b></span><span>ผลการพัฒนา <b>{{ entry.event.achievementResult || 'ไม่ได้ระบุ' }}</b></span></div>
                      <small v-if="entry.event.operationReason">เหตุผลการดำเนินการ: {{ entry.event.operationReason }}</small>
                      <small v-if="entry.event.achievementNote">สิ่งที่ควรพัฒนาต่อ: {{ entry.event.achievementNote }}</small>
                      <small>ความคิดเห็น: {{ entry.event.detail || 'ไม่ได้ระบุ' }}</small>
                    </div>
                  </div>
                  <div v-else class="completion-flow-line pending"><div class="flow-line-copy"><strong>{{ entry.event.title }}</strong><span>{{ entry.event.actor }}</span></div></div>
                </template>
                <div v-if="!topicTimelineEntries(selectedActivity, selectedTopicIndex, selectedGroup.completion).length" class="no-update"><strong>ยังไม่มีประวัติของหัวข้อนี้</strong><span>เมื่อมีการส่งความก้าวหน้า รายการจะเรียงตามเวลาในส่วนนี้</span></div>
              </div>
            </section>
          </main>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.detail-page { --green: var(--color-primary); --green-deep: var(--color-primary-hover); --ink: var(--color-text); --muted: var(--color-text-muted); --line: var(--color-border); display: grid; gap: 16px; color: var(--ink); }
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
.page-head h1 { margin: 0; font-size: 22px; font-weight: 900; }
.page-head p { margin: 6px 0 0; color: var(--muted); font-size: 13px; }
.page-head > span { border-radius: 999px; background: #edf2f0; padding: 6px 10px; color: #64726c; font-size: 11px; font-weight: 900; }
.empty { display: grid; place-items: center; min-height: 260px; border: 1px dashed #c8d6d1; border-radius: 10px; background: #fafcfb; padding: 30px; }
.empty strong { font-size: 16px; }.empty span { margin-top: 7px; color: var(--muted); }
.competency-picker { display: flex; gap: 8px; max-width: 100%; overflow-x: auto; padding: 1px 1px 7px; }
.competency-picker button { display: grid; grid-template-columns: auto minmax(220px, 1fr); align-items: center; gap: 11px; flex: 0 0 min(390px, 84vw); border: 1px solid var(--line); border-radius: 9px; background: #fafcfb; padding: 10px 12px; color: inherit; font: inherit; text-align: left; cursor: pointer; }
.competency-picker button:hover { border-color: #95bbae; background: #f4f9f7; }
.competency-picker button:focus-visible, .activity-nav button:focus-visible, .topic-picker button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
.competency-picker button.active { border-color: var(--green); background: #eaf4f0; box-shadow: inset 0 -2px 0 var(--green); }
.competency-picker button > span { border-radius: 7px; background: #e7f0ed; padding: 9px 10px; color: var(--green-deep); font-size: 13px; font-weight: 900; }
.competency-picker button.active > span { background: var(--green); color: #f8fcfa; }
.competency-picker button div { display: grid; gap: 3px; min-width: 0; }
.competency-picker strong { overflow: hidden; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
.competency-picker small { color: var(--muted); font-size: 10px; font-weight: 700; }
.record { overflow: hidden; border: 1px solid #cad9d3; border-radius: 12px; background: #f4f8f6; box-shadow: 0 8px 24px rgba(31, 67, 55, .06); }
.record-head { display: flex; align-items: center; justify-content: space-between; gap: 20px; border-bottom: 1px solid var(--line); background: #eaf3f0; padding: 15px 18px; }
.record-identity { display: flex; align-items: center; gap: 12px; min-width: 0; }
.record-identity > span { flex: none; border: 1px solid #9fc8bb; border-radius: 7px; background: #fbfdfc; padding: 8px 10px; color: var(--green-deep); font-weight: 900; }
.record-identity small { color: #6f7e78; font-size: 11px; font-weight: 800; }
.record-identity h2 { margin: 2px 0 0; font-size: 17px; line-height: 1.4; }
.status { flex: none; border: 1px solid transparent; border-radius: 999px; padding: 6px 10px; font-size: 11px; }
.status.active { border-color: #cfe5de; background: #f1f8f5; color: #176b56; }.status.pending { border-color: #ecd69c; background: #fff6df; color: #8b5b0d; }.status.returned, .status.failed { border-color: #efc5bd; background: #fff0ed; color: #ad3929; }.status.done { border-color: #c3e2ce; background: #e6f6eb; color: #147745; }
.record-facts { display: grid; grid-template-columns: repeat(3, minmax(90px, .36fr)) repeat(2, minmax(220px, 1fr)); border-bottom: 1px solid var(--line); background: #f9fbfa; }
.record-facts > div { min-width: 0; padding: 12px 15px; }
.record-facts > div + div { border-left: 1px solid #dfe7e4; }
.record-facts span { color: #72807a; font-size: 10px; font-weight: 800; }
.record-facts strong { display: block; margin-top: 3px; color: var(--green-deep); font-size: 19px; }
.record-facts p { margin: 4px 0 0; font-size: 12px; line-height: 1.5; }
.approval-summary { display: grid; grid-template-columns: minmax(260px, .8fr) minmax(380px, 1.2fr); gap: 14px 18px; margin: 14px 16px 0; overflow: hidden; border: 1px solid #acd5c4; border-left: 5px solid #24835f; border-radius: 10px; background: #edf8f3; padding: 15px 16px; box-shadow: 0 4px 14px rgba(27, 104, 78, .07); }
.approval-summary.needs-development { border-color: #e5c78e; border-left-color: #c98417; background: #fff8e9; }
.approval-summary.returned { border-color: #edbdb5; border-left-color: #c94b3d; background: #fff3f1; box-shadow: 0 4px 14px rgba(154, 56, 43, .06); }
.approval-summary-head { display: flex; align-items: center; gap: 12px; min-width: 0; }
.approval-mark { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 50%; background: #24835f; color: #fff; font-size: 20px; font-weight: 900; }
.needs-development .approval-mark { background: #c98417; }
.approval-summary.returned .approval-mark { background: #c94b3d; }
.approval-summary-head > div { min-width: 0; }
.approval-summary-head small { color: #598070; font-size: 10px; font-weight: 900; letter-spacing: .02em; }
.approval-summary-head h3 { margin: 2px 0 1px; color: #175e46; font-size: 15px; line-height: 1.4; }
.needs-development .approval-summary-head h3 { color: #925c08; }
.approval-summary.returned .approval-summary-head h3 { color: #a3372d; }
.approval-summary-head p { margin: 0; color: #6c7a74; font-size: 10px; }
.latest-return-comment { min-width: 0; border: 1px solid #edc5be; border-radius: 8px; background: rgba(255, 255, 255, .78); padding: 10px 13px; }
.latest-return-comment span { color: #9d4a40; font-size: 10px; font-weight: 900; }
.latest-return-comment p { margin: 4px 0 0; color: #6d3029; font-size: 13px; font-weight: 800; line-height: 1.55; overflow-wrap: anywhere; white-space: pre-wrap; }
.approval-results { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; overflow: hidden; border: 1px solid rgba(67, 133, 109, .2); border-radius: 8px; background: rgba(255, 255, 255, .76); }
.approval-results > div { min-width: 0; padding: 10px 13px; }
.approval-results > div + div { border-left: 1px solid rgba(67, 133, 109, .18); }
.approval-results dt { color: #6f7e78; font-size: 10px; font-weight: 800; }
.approval-results dd { margin: 4px 0 0; color: #1c5d49; font-size: 13px; font-weight: 900; line-height: 1.45; }
.needs-development .approval-results dd { color: #8e5b0d; }
.approval-notes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-column: 1 / -1; gap: 8px; border-top: 1px solid rgba(67, 133, 109, .18); padding-top: 11px; }
.approval-notes p { min-width: 0; margin: 0; color: #465750; font-size: 11px; line-height: 1.55; overflow-wrap: anywhere; }
.approval-notes span { display: block; margin-bottom: 2px; color: #6a7a73; font-size: 9px; font-weight: 900; }
.history-workspace { display: grid; grid-template-columns: 310px minmax(0, 1fr); align-items: stretch; min-height: 520px; margin: 14px 16px 16px; overflow: hidden; border: 1px solid #d1ded9; border-radius: 10px; background: #fafcfb; }
.activity-nav { border-right: 1px solid var(--line); background: #f3f7f5; }
.activity-nav > header { display: grid; gap: 2px; border-bottom: 1px solid var(--line); padding: 14px; }.activity-nav > header span { font-size: 14px; font-weight: 900; }.activity-nav > header small { color: var(--muted); font-size: 11px; }
.activity-nav button { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; align-items: start; gap: 10px; width: 100%; border: 0; border-bottom: 1px solid #dfe7e4; background: transparent; padding: 13px; color: inherit; font: inherit; text-align: left; cursor: pointer; }
.activity-nav button:hover { background: #eaf2ef; }.activity-nav button.active { background: #deeee8; }
.activity-nav button > span { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; background: #e3ece8; color: #53645d; font-size: 12px; font-weight: 900; }.activity-nav button.active > span { background: var(--green); color: #f8fcfa; }
.activity-nav button div { display: grid; gap: 4px; min-width: 0; }.activity-nav button strong { font-size: 12px; line-height: 1.45; overflow-wrap: anywhere; }.activity-nav button small { color: var(--muted); font-size: 10px; }.activity-nav button > b { margin-top: 6px; color: var(--green); font-size: 10px; }
.history-panel { min-width: 0; background: #fbfdfc; }
.activity-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; border-bottom: 1px solid var(--line); padding: 16px 18px; }.activity-head > div { display: grid; gap: 3px; }.activity-head span { color: var(--green); font-size: 11px; font-weight: 900; }.activity-head h3 { margin: 0; font-size: 17px; line-height: 1.45; }.activity-head p { flex: none; margin: 3px 0 0; color: var(--muted); font-size: 11px; }
.topic-picker { display: flex; gap: 6px; overflow-x: auto; border-bottom: 1px solid #e0e8e5; padding: 10px 18px; }.topic-picker button { flex: none; border: 1px solid #d3ded9; border-radius: 7px; background: #f4f7f6; padding: 6px 9px; color: #66756f; font: inherit; font-size: 10px; font-weight: 800; cursor: pointer; }.topic-picker button.active { border-color: #8fb9ac; background: #e3f1ec; color: var(--green-deep); }
.topic-overview { padding: 14px 18px 12px; }.topic-overview span { color: var(--green); font-size: 10px; font-weight: 900; }.topic-overview h4 { margin: 3px 0 0; font-size: 14px; line-height: 1.55; }
.timeline-section { border-top: 1px solid #e0e8e5; padding: 14px 18px 20px; }.timeline-section > header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }.timeline-section > header div { display: grid; gap: 2px; }.timeline-section > header span { font-size: 13px; font-weight: 900; }.timeline-section > header small { color: var(--muted); font-size: 10px; }.timeline-section > header > strong { border-radius: 999px; background: #e8f1ee; padding: 4px 8px; color: var(--green); font-size: 10px; }
.timeline { display: grid; gap: 12px; margin: 15px 0 0; padding: 0; list-style: none; }.timeline li { display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: 10px; }.timeline-number { display: grid; place-items: center; align-self: start; width: 28px; height: 28px; border-radius: 50%; background: var(--green); color: #f8fcfa; font-size: 11px; font-weight: 900; }.timeline article { overflow: hidden; border: 1px solid #d6e1dd; border-radius: 9px; background: #fdfefd; }.timeline article > header { display: flex; align-items: center; justify-content: space-between; gap: 14px; border-bottom: 1px solid #e2e9e6; background: #f4f8f6; padding: 9px 12px; }.timeline article > header strong { color: var(--green-deep); font-size: 11px; }.timeline article > header time { color: #6e7c76; font-size: 10px; font-weight: 700; }
.chronological-entries { display: grid; gap: 14px; margin-top: 15px; }
.timeline-update-row { display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: 10px; }
.timeline-update-row > article { overflow: hidden; border: 1px solid #d6e1dd; border-radius: 9px; background: #fdfefd; }
.timeline-update-row > article > header { display: flex; align-items: center; justify-content: space-between; gap: 14px; border-bottom: 1px solid #e2e9e6; background: #f4f8f6; padding: 9px 12px; }
.timeline-update-row > article > header strong { color: var(--green-deep); font-size: 11px; }
.timeline-update-row > article > header time { color: #6e7c76; font-size: 10px; font-weight: 700; }
.chronological-history .completion-flow-line { margin: 5px 0; }
.update-note, .evidences { padding: 11px 12px; }.update-note > span, .evidences > span { color: var(--color-text-muted); font-size: 10px; font-weight: 800; }.update-note p { margin: 5px 0 0; font-size: 13px; line-height: 1.6; white-space: pre-wrap; }.evidences { border-top: 1px solid #e5ebe8; }.evidences > div { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }.evidences a { border: 1px solid var(--color-primary-border); border-radius: 6px; background: var(--color-primary-soft); padding: 5px 8px; color: var(--green-deep); font-size: 10px; font-weight: 800; text-decoration: none; }.evidences a:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }.submitted-at { display: block; border-top: 1px solid #edf1ef; padding: 7px 12px; color: var(--color-text-muted); font-size: 9px; text-align: right; }
.no-update { display: grid; place-items: center; min-height: 160px; margin-top: 14px; border: 1px dashed #cbd8d3; border-radius: 8px; padding: 24px; text-align: center; }.no-update strong { font-size: 13px; }.no-update span { margin-top: 4px; color: var(--muted); font-size: 11px; }
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
.post-review-section { border-top-color: #d5e1dc; padding-top: 16px; }
@media (max-width: 1100px) { .record-facts { grid-template-columns: repeat(3, 1fr); }.record-facts .plan-fact { grid-column: span 3; border-top: 1px solid #dfe7e4; border-left: 0; }.record-facts .plan-fact + .plan-fact { border-left: 0; }.history-workspace { grid-template-columns: 260px minmax(0, 1fr); } }
@media (max-width: 900px) { .approval-summary { grid-template-columns: 1fr; }.approval-notes { grid-template-columns: 1fr; }.history-workspace { grid-template-columns: 1fr; }.activity-nav { border-right: 0; border-bottom: 1px solid var(--line); }.record-head, .activity-head { align-items: flex-start; flex-direction: column; }.activity-head p { flex: initial; }.status { align-self: flex-start; } }
@media (max-width: 620px) { .page-head { align-items: flex-start; }.page-head > span { display: none; }.record-facts { grid-template-columns: repeat(3, 1fr); }.record-facts > div { padding: 10px; }.record-facts .plan-fact { grid-column: span 3; }.record-head { padding: 13px; }.record-identity { align-items: flex-start; }.record-identity h2 { font-size: 15px; }.approval-summary { margin-right: 10px; margin-left: 10px; padding: 13px; }.approval-results { grid-template-columns: 1fr; }.approval-results > div + div { border-top: 1px solid rgba(67, 133, 109, .18); border-left: 0; }.history-workspace { margin-right: 10px; margin-left: 10px; }.timeline article > header { align-items: flex-start; flex-direction: column; }.timeline-section, .activity-head, .topic-overview { padding-right: 13px; padding-left: 13px; }.activity-review-flow { gap: 18px; margin: 5px 15px 20px; padding-top: 7px; }.completion-flow-line { gap: 10px; }.completion-flow-line::before, .completion-flow-line::after { min-width: 18px; }.flow-line-copy { min-width: 0; max-width: 80%; } }
</style>
