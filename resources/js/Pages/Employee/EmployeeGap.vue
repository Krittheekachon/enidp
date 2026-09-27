<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
  setPage: (p: string) => void;
  competencies?: any[];
  gaps?: any[];
  evalStatus?: string;
  user?: any;
}>();

const approvedStatuses = ['approved', 'dean_approved'];
const competencyKey = (row: any) => String(row?.competencyId ?? row?.id ?? row?.cd ?? '');
const rows = computed(() => {
  const assigned = Array.isArray(props.competencies) ? props.competencies : [];
  const gaps = Array.isArray(props.gaps) ? props.gaps : [];
  const gapsByCompetency = new Map(gaps.map((row) => [competencyKey(row), row]));
  const assignedKeys = new Set(assigned.map((row) => competencyKey(row)));

  const assignedRows = assigned.map((competency) => {
    const result = gapsByCompetency.get(competencyKey(competency));
    const status = competency.assessmentStatus || result?.status || 'draft';

    return {
      ...competency,
      ...(result || {}),
      competencyId: result?.competencyId ?? competency.id,
      expected: result?.expected ?? competency.expectedLevel ?? null,
      actual: result?.actual ?? null,
      gap: result?.gap ?? null,
      status,
      lastDraftSavedAt: competency.lastDraftSavedAt || result?.updatedAt || null,
    };
  });
  const unassignedResults = gaps.filter((row) => !assignedKeys.has(competencyKey(row)));

  return [...assignedRows, ...unassignedResults].sort((a, b) => String(a.cd || '').localeCompare(String(b.cd || '')));
});
const isApproved = (row: any) => approvedStatuses.includes(row?.status || '');
const hasFinalResult = (row: any) => isApproved(row) && row.gap !== null && row.gap !== undefined;
const completedRows = computed(() => rows.value.filter((row) => hasFinalResult(row)));
const pendingRows = computed(() => rows.value.filter((row) => !hasFinalResult(row)));
const passedRows = computed(() => completedRows.value.filter((row) => Number(row.gap) >= 0));
const failedRows = computed(() => completedRows.value.filter((row) => Number(row.gap) < 0));
const selectedRow = ref<any | null>(null);
const rowKey = (row: any) => String(row.id ?? row.competencyId ?? row.cd);
const openDetails = (row: any) => { selectedRow.value = row; };
const closeDetails = () => { selectedRow.value = null; };
const employeeName = computed(() => {
  const name = `${props.user?.t || ''}${props.user?.n || ''}`.trim();
  return name || 'ผู้รับการประเมิน';
});
const indicatorKey = (row: any, level: any, index: number) =>
  `${row.competencyId}:${level.id || level.lvl}:${index}`;
const isIndicatorChecked = (row: any, level: any, index: number) =>
  new Set(row.checkedIndicatorKeys || []).has(indicatorKey(row, level, index));
const selectedLevels = computed(() => {
  if (!selectedRow.value) return [];

  return (selectedRow.value.levels || []).map((level: any, levelIndex: number) => {
    const levelNumber = Number(level.lvl ?? level.level ?? levelIndex + 1);
    const indicators = (level.indicators || []).map((indicator: any, indicatorIndex: number) => ({
      index: indicatorIndex,
      text: typeof indicator === 'string' ? indicator : (indicator?.description || indicator?.text || ''),
      checked: isIndicatorChecked(selectedRow.value, level, indicatorIndex),
    }));

    return {
      id: level.id || levelNumber,
      number: levelNumber,
      indicators,
      checkedCount: indicators.filter((indicator: any) => indicator.checked).length,
    };
  });
});
const reviewerComments = (row: any) => Array.isArray(row?.reviewerComments) ? row.reviewerComments : [];
const formatCommentDate = (value: unknown) => {
  if (!value) return '';
  const date = new Date(String(value));
  if (Number.isNaN(date.getTime())) return '';

  return new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};
const developmentStatusLabel = (row: any) => {
  const status = row?.status || 'draft';

  if (hasFinalResult(row)) return Number(row.gap) >= 0 ? 'ผ่านเกณฑ์' : 'ไม่ผ่านเกณฑ์';
  if (isApproved(row)) return 'อนุมัติแล้ว';
  if (status === 'revision_required') return 'ถูกส่งกลับให้แก้ไข';
  if (status === 'self_submitted') return 'รอผู้ประเมินลำดับที่ 1';
  if (status === 'unit_evaluated') return 'รอผู้ประเมินลำดับที่ 2';
  if (status === 'dept_evaluated') return 'รอผู้ประเมินลำดับที่ 3';

  const reviewStep = String(status).match(/^review_step_(\d+)$/)?.[1];
  if (reviewStep) return `รอผู้ประเมินลำดับที่ ${reviewStep}`;
  if (status === 'draft' && row?.lastDraftSavedAt) return 'บันทึกร่างแล้ว';

  return 'ยังไม่เริ่มประเมิน';
};

const statusTone = (row: any) => {
  if (hasFinalResult(row)) return Number(row.gap) >= 0 ? 'passed' : 'failed';
  if (isApproved(row)) return 'pending';
  if (row?.status === 'revision_required') return 'revision';
  if (row?.status === 'draft' && !row?.lastDraftSavedAt) return 'not-started';
  if (row?.status === 'draft') return 'draft';
  return 'pending';
};

const formatLevel = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '-';
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return '-';

  return numberValue.toFixed(2).replace(/\.00$/, '');
};

const formatGap = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '-';
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return '-';

  return `${numberValue > 0 ? '+' : ''}${numberValue.toFixed(2).replace(/\.00$/, '')}`;
};

</script>

<template>
  <section class="employee-page">
    <div class="page-head">
      <div>
        <h1>ผลการประเมิน</h1>
        <p>คำนวณจากคะแนนการประเมินตนเองลบด้วยค่าความคาดหวัง</p>
      </div>
      <button class="btn btn-s btn-sm" type="button" @click="setPage('emp-assess')">กลับไปประเมิน</button>
    </div>

    <div v-if="rows.length" class="summary-grid">
      <div class="summary-card">
        <div class="summary-label">สมรรถนะทั้งหมด</div>
        <div class="summary-value">{{ rows.length }}</div>
        <div class="summary-copy">รายการที่ต้องประเมิน</div>
      </div>
      <div class="summary-card pending">
        <div class="summary-label">ยังไม่เสร็จสิ้น</div>
        <div class="summary-value">{{ pendingRows.length }}</div>
        <div class="summary-copy">ยังไม่เริ่ม ร่าง หรือรอตรวจ</div>
      </div>
      <div class="summary-card success">
        <div class="summary-label">ผ่านเกณฑ์</div>
        <div class="summary-value">{{ passedRows.length }}</div>
        <div class="summary-copy">Gap ≥ 0</div>
      </div>
      <div class="summary-card danger">
        <div class="summary-label">ไม่ผ่านเกณฑ์</div>
        <div class="summary-value">{{ failedRows.length }}</div>
        <div class="summary-copy">Gap ติดลบ</div>
      </div>
    </div>

    <section v-if="rows.length" class="table-section">
      <div class="section-head compact">
        <div>
          <h2>ผลการประเมิน</h2>
          <p>แสดงสมรรถนะที่ได้รับมอบหมายทั้งหมด พร้อมคะแนนและสถานะล่าสุด</p>
        </div>
      </div>
      <div class="result-table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th>สมรรถนะ</th>
              <th>คะแนนที่คาดหวัง</th>
              <th>คะแนนที่ได้</th>
              <th>Gap</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="`table-${rowKey(row)}`"
              class="result-row"
              :class="{ 'is-clickable': hasFinalResult(row) }"
              :tabindex="hasFinalResult(row) ? 0 : undefined"
              :role="hasFinalResult(row) ? 'button' : undefined"
              @click="hasFinalResult(row) && openDetails(row)"
              @keydown.enter.prevent="hasFinalResult(row) && openDetails(row)"
              @keydown.space.prevent="hasFinalResult(row) && openDetails(row)"
            >
              <td>
                <div class="table-competency">
                  <span class="type-tag">{{ row.t || '-' }}</span>
                  <div class="competency-copy">
                    <strong>{{ row.cd }}</strong>
                    <small>{{ row.n }}</small>
                  </div>
                </div>
              </td>
              <td>{{ formatLevel(row.expected) }}</td>
              <td>{{ hasFinalResult(row) ? formatLevel(row.actual) : '—' }}</td>
              <td>
                <span v-if="hasFinalResult(row)" class="gap-pill" :class="{ negative: Number(row.gap) < 0, positive: Number(row.gap) >= 0 }">
                  {{ formatGap(row.gap) }}
                </span>
                <span v-else class="empty-value">—</span>
              </td>
              <td>
                <span class="status-pill" :class="statusTone(row)">
                  {{ developmentStatusLabel(row) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="selectedRow" class="competency-modal-backdrop" @click.self="closeDetails" @keydown.esc="closeDetails">
        <section class="competency-modal" role="dialog" aria-modal="true" :aria-labelledby="`competency-title-${rowKey(selectedRow)}`">
          <header class="competency-modal-head">
            <div>
              <div class="modal-code-line">
                <span class="type-tag">{{ selectedRow.t || '-' }}</span>
                <strong>{{ selectedRow.cd }}</strong>
              </div>
              <h2 :id="`competency-title-${rowKey(selectedRow)}`">{{ selectedRow.n }}</h2>
              <p>{{ employeeName }} · ระดับความคาดหวัง {{ formatLevel(selectedRow.expected) }}</p>
              <span class="workflow-complete">อนุมัติแล้ว</span>
            </div>
            <button class="btn btn-s btn-sm" type="button" @click="closeDetails">ปิด</button>
          </header>

          <div class="competency-modal-body">
            <section class="modal-section competency-description">
              <h3>คำอธิบายสมรรถนะ</h3>
              <p>{{ selectedRow.det || 'ไม่มีคำอธิบายเพิ่มเติม' }}</p>
            </section>

            <section v-if="reviewerComments(selectedRow).length" class="modal-section reviewer-comment-section">
              <div class="comment-section-head">
                <h3>ความคิดเห็นจากผู้ประเมิน</h3>
                <span>{{ reviewerComments(selectedRow).length }} คน</span>
              </div>
              <div class="comment-list">
                <article v-for="(comment, index) in reviewerComments(selectedRow)" :key="`${comment.reviewerId || index}-${comment.reviewStep || index}`" class="comment-item">
                  <div class="comment-head">
                    <div>
                      <strong>{{ comment.reviewerName || 'ผู้ประเมิน' }}</strong>
                      <small>
                        <template v-if="comment.reviewStep">ผู้ประเมินลำดับที่ {{ comment.reviewStep }}</template>
                        <template v-if="comment.reviewStep && comment.reviewerPosition"> · </template>
                        <template v-if="comment.reviewerPosition">{{ comment.reviewerPosition }}</template>
                      </small>
                    </div>
                    <time v-if="formatCommentDate(comment.submittedAt)">{{ formatCommentDate(comment.submittedAt) }}</time>
                  </div>
                  <p>{{ comment.comment }}</p>
                </article>
              </div>
            </section>

            <article v-for="level in selectedLevels" :key="level.id" class="level-card">
              <header>
                <div>
                  <strong>ระดับที่ {{ level.number }}</strong>
                  <span>เลือกแล้ว {{ level.checkedCount }}/{{ level.indicators.length }} พฤติกรรม</span>
                </div>
                <b :class="{ complete: level.indicators.length > 0 && level.checkedCount === level.indicators.length, partial: level.checkedCount > 0 && level.checkedCount < level.indicators.length }">
                  {{ level.checkedCount === 0 ? 'ยังไม่เลือก' : (level.checkedCount === level.indicators.length ? 'เลือกครบ' : 'เลือกบางส่วน') }}
                </b>
              </header>
              <div class="indicator-list">
                <div v-for="indicator in level.indicators" :key="indicator.index" :class="{ selected: indicator.checked }">
                  <span>{{ indicator.checked ? '✓' : '' }}</span>
                  <p><strong>ข้อ {{ level.number }}.{{ indicator.index + 1 }}</strong>{{ indicator.text }}</p>
                </div>
                <p v-if="level.indicators.length === 0" class="detail-empty">ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</p>
              </div>
            </article>

            <section v-if="selectedRow.note" class="modal-section">
              <h3>ความคิดเห็นจากการประเมินตนเอง</h3>
              <p>{{ selectedRow.note }}</p>
            </section>

          </div>

          <footer class="competency-modal-foot">ดูได้อย่างเดียว · ผลการประเมินได้รับการอนุมัติแล้ว</footer>
        </section>
      </div>
    </Teleport>

    <div v-if="!rows.length" class="empty-card">
      <div class="empty-title">ยังไม่มีสมรรถนะที่ต้องประเมิน</div>
      <div class="empty-copy">เมื่อ HR กำหนดสมรรถนะสำหรับตำแหน่งแล้ว รายการจะปรากฏในหน้านี้</div>
      <button class="btn btn-t btn-sm" type="button" @click="setPage('emp-assess')">
        กลับไปหน้าประเมินตนเอง
      </button>
    </div>
  </section>
</template>

<style scoped>
.employee-page { display: grid; gap: 18px; }
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.page-head h1 { margin: 0; color: var(--text); font-size: 22px; font-weight: 900; }
.page-head p { margin: 6px 0 0; color: var(--text3); font-size: 13px; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.summary-card,
.result-section,
.table-section {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.summary-card { padding: 18px 20px; }
.summary-card.pending .summary-value { color: #b7791f; }
.summary-card.success .summary-value { color: #059669; }
.summary-card.danger .summary-value { color: #dc2626; }
.summary-label { color: var(--text3); font-size: 12px; font-weight: 800; }
.summary-value { margin-top: 8px; color: var(--text); font-size: 28px; font-weight: 900; line-height: 1.1; }
.summary-copy { margin-top: 6px; color: var(--text3); font-size: 12px; }
.result-section.passed { border-color: #86efac; background: #f0fdf4; }
.result-section.failed { border-color: #fecaca; background: #fef2f2; }
.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(148, 163, 184, .28);
}
.section-head.compact { border-bottom-color: var(--border); }
.section-head h2 { margin: 0; color: var(--text); font-size: 16px; font-weight: 900; }
.section-head p { margin: 6px 0 0; color: var(--text3); font-size: 13px; }
.section-head > span { color: inherit; font-size: 12px; font-weight: 900; white-space: nowrap; }
.passed .section-head > span { color: #059669; }
.failed .section-head > span { color: #dc2626; }
.competency-card-list,
.failed-card-list { display: grid; gap: 10px; padding: 16px 20px; }
.competency-card,
.failed-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
}
.passed .competency-card { border-color: #86efac; }
.failed-card { border-color: #fecaca; }
.competency-card,
.failed-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 16px;
}
.failed-card-head { border-bottom: 1px solid #fecaca; }
.competency-main,
.table-competency {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;
}
.competency-main strong,
.table-competency strong { display: block; color: var(--text); font-size: 14px; font-weight: 900; }
.competency-main small,
.table-competency small { display: block; margin-top: 5px; color: var(--text3); font-size: 12px; line-height: 1.4; }
.type-tag {
  flex: 0 0 auto;
  min-width: 28px;
  border-radius: 6px;
  background: var(--blue-lt);
  color: var(--blue);
  padding: 3px 6px;
  text-align: center;
  font-size: 11px;
  font-weight: 900;
}
.gap-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 26px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}
.gap-badge.passed { background: #ecfdf5; color: #059669; }
.gap-badge.failed { background: #fff1f2; color: #dc2626; }
.missing-block { padding: 14px 16px 16px; }
.missing-title { margin-bottom: 10px; color: var(--text); font-size: 13px; font-weight: 900; }
.level-blocks { display: grid; gap: 10px; }
.level-block {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
}
.level-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}
.level-head strong { color: var(--text); font-size: 13px; font-weight: 900; }
.level-head span {
  display: inline-flex;
  align-items: center;
  min-height: 22px;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fff;
  color: var(--text3);
  font-size: 11px;
  font-weight: 900;
}
.indicator-lines { display: grid; }
.indicator-line {
  display: grid;
  grid-template-columns: 82px minmax(0, 1fr);
  gap: 12px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
}
.indicator-line:last-child { border-bottom: 0; }
.indicator-line span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  border-radius: 999px;
  background: var(--teal-lt);
  color: var(--teal);
  font-size: 11px;
  font-weight: 900;
}
.indicator-line p { margin: 0; color: var(--text); font-size: 13px; line-height: 1.6; }
.missing-empty { padding: 14px 16px 16px; color: var(--text3); font-size: 13px; }
.result-table-wrap { overflow-x: auto; padding: 16px 20px 20px; }
.result-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.result-table th {
  background: var(--bg);
  color: var(--text3);
  font-size: 12px;
  font-weight: 900;
  padding: 12px 14px;
  text-align: left;
}
.result-table td {
  border-top: 1px solid var(--border);
  color: var(--text);
  font-size: 13px;
  padding: 14px;
  vertical-align: middle;
}
.result-row { transition: background-color .16s ease, box-shadow .16s ease; }
.result-row.is-clickable { cursor: pointer; }
.result-row.is-clickable:hover { background: #f4faf8; }
.result-row:focus-visible { background: #f4faf8; outline: 2px solid rgba(15, 118, 110, .24); outline-offset: -2px; }
.competency-copy { min-width: 0; }
.competency-modal-backdrop {
  position: fixed;
  z-index: 1300;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 20, 25, .7);
}
.competency-modal {
  display: flex;
  flex-direction: column;
  width: min(1040px, 100%);
  max-height: calc(100vh - 48px);
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .34);
}
.competency-modal-head {
  display: flex;
  flex: 0 0 auto;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  border-bottom: 1px solid var(--border);
  padding: 20px 24px;
}
.modal-code-line { display: flex; align-items: center; gap: 10px; }
.modal-code-line > strong { color: var(--text); font-size: 14px; font-weight: 900; }
.competency-modal-head h2 { margin: 8px 0 2px; color: var(--text); font-size: 20px; line-height: 1.4; }
.competency-modal-head p { margin: 0; color: var(--text3); font-size: 12px; }
.workflow-complete {
  display: inline-flex;
  margin-top: 10px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #15803d;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 900;
}
.competency-modal-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  background: #f8fafc;
  padding: 20px 24px;
}
.competency-modal-body > * + * { margin-top: 14px; }
.modal-section {
  border: 1px solid #dbe3ea;
  border-radius: 10px;
  background: #fff;
  padding: 16px;
}
.modal-section h3 { margin: 0 0 7px; color: var(--text); font-size: 13px; font-weight: 900; }
.modal-section > p,
.comment-item > p { margin: 0; color: var(--text2); font-size: 13px; line-height: 1.65; white-space: pre-wrap; }
.comment-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}
.comment-section-head h3 { margin: 0; }
.comment-section-head > span {
  border-radius: 999px;
  background: var(--teal-lt);
  color: var(--teal);
  padding: 3px 9px;
  font-size: 10px;
  font-weight: 900;
}
.level-card {
  overflow: hidden;
  border: 1px solid #e1d8d2;
  border-radius: 10px;
  background: #fff;
}
.level-card > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-height: 58px;
  border-bottom: 1px solid #eadfd9;
  background: #fff3ed;
  padding: 12px 16px;
}
.level-card > header > div { display: grid; gap: 3px; }
.level-card > header strong { color: #b93d28; font-size: 14px; }
.level-card > header span { color: #8d817a; font-size: 11px; }
.level-card > header b {
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  padding: 5px 10px;
  font-size: 11px;
}
.level-card > header b.complete { border-color: #bbf7d0; background: #ecfdf5; color: #047857; }
.level-card > header b.partial { border-color: #fed7aa; background: #fff7ed; color: #b45309; }
.indicator-list { display: grid; padding: 10px 12px; }
.indicator-list > div {
  display: grid;
  grid-template-columns: 20px minmax(0, 1fr);
  gap: 10px;
  border: 1px solid #edf0f2;
  border-radius: 8px;
  background: #fff;
  padding: 10px 12px;
  color: #8c949d;
}
.indicator-list > div + div { margin-top: 7px; }
.indicator-list > div.selected { border-color: #f2b7a9; background: #fff8f5; color: #302b28; }
.indicator-list > div > span {
  display: grid;
  place-items: center;
  width: 18px;
  height: 18px;
  border: 1px solid #d6dbe0;
  border-radius: 5px;
  color: #fff;
  font-size: 11px;
  font-weight: 900;
}
.indicator-list > div.selected > span { border-color: var(--color-primary); background: var(--color-primary); }
.indicator-list p { margin: 0; font-size: 12px; line-height: 1.6; }
.indicator-list p strong { display: inline-block; margin-right: 8px; color: inherit; }
.comment-list { display: grid; gap: 8px; }
.comment-item { border-top: 1px solid var(--border); padding-top: 12px; }
.comment-item:first-child { border-top: 0; padding-top: 0; }
.comment-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 7px; }
.comment-head strong { display: block; color: var(--text); font-size: 12px; font-weight: 900; }
.comment-head small { display: block; margin-top: 2px; color: var(--text3); font-size: 11px; }
.comment-head time { color: var(--text3); font-size: 10px; white-space: nowrap; }
.detail-empty { margin: 0; color: var(--text3) !important; font-size: 12px; }
.competency-modal-foot {
  flex: 0 0 auto;
  border-top: 1px solid var(--border);
  color: var(--text3);
  padding: 14px 20px;
  font-size: 11px;
}
.result-table th:not(:first-child),
.result-table td:not(:first-child) { text-align: center; }
.gap-pill,
.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 24px;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 12px;
  font-weight: 900;
}
.gap-pill.negative,
.status-pill.failed { background: #fef2f2; color: #dc2626; }
.gap-pill.positive,
.status-pill.passed { background: #ecfdf5; color: #047857; }
.status-pill.pending { background: #fffbeb; color: #a16207; }
.status-pill.revision { background: #fff7ed; color: #c2410c; }
.status-pill.draft { background: #eff6ff; color: #1d4ed8; }
.status-pill.not-started { background: #f1f5f9; color: #64748b; }
.empty-value { color: var(--text3); }
.empty-card {
  display: grid;
  place-items: center;
  gap: 10px;
  min-height: 260px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: #fff;
  padding: 32px;
  text-align: center;
}
.empty-title { color: var(--text); font-size: 16px; font-weight: 900; }
.empty-copy { color: var(--text3); font-size: 13px; }
@media (max-width: 900px) {
  .summary-grid { grid-template-columns: 1fr; }
  .page-head,
  .competency-card,
  .failed-card-head { flex-direction: column; align-items: stretch; }
  .competency-modal-backdrop { padding: 12px; }
  .competency-modal { max-height: calc(100vh - 24px); }
}
@media (max-width: 560px) {
  .section-head { flex-direction: column; }
  .indicator-line { grid-template-columns: 1fr; }
  .competency-modal-head,
  .competency-modal-body { padding: 16px; }
}
</style>
