<script setup lang="ts">
import { computed } from 'vue';
import { formDefinitions, type RowField } from '../idpFormDefinitions';
import { coachingApproachDescriptions } from '../coachingApproachDescriptions';

const props = defineProps<{
  activity: Record<string, any>;
  employee: { name?: string; sso?: string; position?: string };
  competency: { code?: string; name?: string; gap?: number | string | null };
}>();

const definition = computed(() => formDefinitions[props.activity.formCode] || null);
const details = computed(() => props.activity.formDetails || {});
const rows = computed(() => Array.isArray(details.value.planRows) ? details.value.planRows : []);
const timelineKeys = new Set(['developmentStart', 'developmentEnd', 'hours', 'sessionCount', 'sessionDuration']);
const ordinaryFields = computed(() => (definition.value?.rowFields || []).filter((field) =>
  field.type !== 'multi-checkbox' && !timelineKeys.has(field.key)));
const timelineFields = computed(() => (definition.value?.rowFields || []).filter((field) => timelineKeys.has(field.key)));
const multiChoiceFields = computed(() => {
  const fields = new Map<string, RowField[]>();
  (definition.value?.rowFields || []).filter((field) => field.type === 'multi-checkbox').forEach((field) => {
    fields.set(field.key, [...(fields.get(field.key) || []), field]);
  });
  return [...fields.entries()].map(([key, options]) => ({ key, label: options[0]?.group || options[0]?.label || key, options }));
});
const visibleDetailFields = computed(() => (definition.value?.detailFields || []).filter((field) => {
  if (!field.showWhen) return true;
  return details.value.detail?.[field.showWhen.key] === field.showWhen.value;
}));

const rowLabel = computed(() => ({
  form_3_project_assignment: 'งานที่ได้รับมอบหมาย',
  form_4_ojt: 'หัวข้อฝึกปฏิบัติงาน',
  form_5_coaching: 'หัวข้อการสอนงาน',
  form_6_mentoring: 'หัวข้อที่ต้องการพัฒนา',
  form_7_group_activity: 'กิจกรรม',
  form_8_feedback: 'หัวข้อการพัฒนา',
  form_9_field_trip: 'รายการศึกษาดูงาน',
  form_10_training: 'ข้อมูลหลักสูตร',
}[props.activity.formCode] || 'รายการ'));

const isSelected = (row: Record<string, any>, field: RowField) => {
  const values = Array.isArray(row[field.key]) ? row[field.key] : [];
  return values.includes(field.value || field.label);
};
const inputType = (field: RowField) => ['developmentStart', 'developmentEnd'].includes(field.key) ? 'date' : 'text';
const displayGap = computed(() => {
  const value = Number(props.competency.gap);
  if (!Number.isFinite(value)) return '—';
  return value.toFixed(Number.isInteger(value) ? 0 : 2);
});
const isCoachingForm = computed(() => props.activity.formCode === 'form_5_coaching');
const isForm = (code: string) => props.activity.formCode === code;
const hasCoachingApproach = (row: Record<string, any>, code: string) => {
  const values = Array.isArray(row.coachingApproaches) ? row.coachingApproaches : [];
  return values.includes(code);
};
const formatNumber = (value: unknown) => {
  const number = Number(value);
  return Number.isFinite(number) ? number.toFixed(Number.isInteger(number) ? 0 : 2) : '-';
};
const formatCurrency = (value: unknown) => {
  if (value === '' || value === null || value === undefined) return 'ไม่ระบุ';
  const number = Number(value);
  return Number.isFinite(number) ? `${number.toLocaleString('th-TH', { maximumFractionDigits: 2 })} บาท` : 'ไม่ระบุ';
};
</script>

<template>
  <div class="readonly-form">
    <section class="form-block readonly-block">
      <header><h4>ข้อมูลผู้รับการพัฒนา</h4><span>ดึงจากระบบ</span></header>
      <div class="form-grid three">
        <label><span>ชื่อผู้รับการพัฒนา</span><input :value="employee.name || '—'" readonly /></label>
        <label><span>รหัสพนักงาน</span><input :value="employee.sso || '—'" readonly /></label>
        <label><span>ตำแหน่ง</span><input :value="employee.position || '—'" readonly /></label>
      </div>
    </section>

    <section class="form-block readonly-block">
      <header><h4>ข้อมูลประกอบการวางแผน</h4><span>จากสมรรถนะที่ต้องพัฒนา</span></header>
      <div class="form-grid three">
        <label><span>รหัส</span><input :value="competency.code || '—'" readonly /></label>
        <label><span>ชื่อสมรรถนะ</span><input :value="competency.name || '—'" readonly /></label>
        <label><span>Gap</span><input :value="displayGap" readonly /></label>
      </div>
    </section>

    <section v-if="visibleDetailFields.length" class="form-block">
      <header><h4>{{ definition?.detailTitle || 'ข้อมูลกิจกรรม' }}</h4><span>พนักงานกรอกตอนทำแผน</span></header>
      <div class="form-grid">
        <label v-for="field in visibleDetailFields" :key="field.key" :class="{ wide: field.type === 'area' }">
          <span>{{ field.label }}</span>
          <textarea v-if="field.type === 'area'" :value="details.detail?.[field.key] || ''" rows="3" readonly />
          <select v-else-if="field.type === 'choice'" :value="details.detail?.[field.key] || ''" disabled>
            <option value="">ไม่ระบุ</option>
            <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
          </select>
          <input v-else :type="field.type === 'date' ? 'date' : 'text'" :value="details.detail?.[field.key] || ''" readonly />
        </label>
      </div>
    </section>

    <section class="form-block">
      <header><h4>{{ definition?.rowTitle || 'รายละเอียดกิจกรรม' }}</h4><span>{{ rows.length }} รายการ</span></header>
      <div v-if="rows.length && isForm('form_3_project_assignment')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>งานที่ได้รับมอบหมาย {{ rowIndex + 1 }}</strong><button type="button" disabled>ลบ</button></div>
          <div class="project-assignment-grid">
            <label><span>หัวข้องานโครงการ/งานพิเศษที่ได้รับมอบหมาย</span><input :value="row.assignmentTopic || ''" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><input :value="row.developmentGoal || ''" readonly /></label>
            <label class="wide"><span>รายละเอียด</span><textarea :value="row.developmentApproach || ''" rows="2" readonly /></label>
            <div class="coaching-timeline group-activity-timeline wide">
              <div class="coaching-timeline-heading"><strong>ระยะเวลาดำเนินการ</strong><span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isForm('form_4_ojt')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>หัวข้อฝึกปฏิบัติ {{ rowIndex + 1 }}</strong><button type="button" disabled>ลบ</button></div>
          <div class="project-assignment-grid">
            <label><span>หัวข้อทักษะ/ประเด็นการฝึกปฏิบัติงาน</span><textarea :value="row.skillTopic || ''" rows="3" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label class="wide"><span>วิธีการ</span><textarea :value="row.developmentApproach || ''" rows="2" readonly /></label>
            <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>
            <div class="coaching-timeline wide">
              <div class="coaching-timeline-heading"><strong>ระยะเวลาการฝึกปฏิบัติงาน</strong><span>กำหนดช่วงเวลาและจำนวนชั่วโมงรวม</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
              <label><span>จำนวนชั่วโมง</span><input :value="row.hours ?? ''" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isCoachingForm" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head">
            <strong>หัวข้อการสอนงาน {{ rowIndex + 1 }}</strong>
            <button type="button" disabled>ลบ</button>
          </div>
          <div class="project-assignment-grid">
            <label><span>หัวข้อทักษะ/ประเด็นการสอนงาน</span><textarea :value="row.topic || ''" rows="3" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label class="wide"><span>วิธีการ</span><textarea :value="row.developmentApproach || ''" rows="2" readonly /></label>
            <div class="coaching-approach-field wide">
              <div class="coaching-approach-label">
                <div>
                  <span>แนวทางการสอนงาน</span>
                  <small>เลือกได้มากกว่า 1 แนวทาง</small>
                </div>
                <button type="button" disabled>อ่านเพิ่มเติม</button>
              </div>
              <div class="coaching-approach-options">
                <div
                  v-for="approach in coachingApproachDescriptions"
                  :key="approach.code"
                  class="coaching-approach-option"
                  :class="{ selected: hasCoachingApproach(row, approach.code) }"
                >
                  <strong>{{ approach.code }}</strong>
                  <span>{{ approach.title }}</span>
                  <i aria-hidden="true">✓</i>
                </div>
              </div>
            </div>
            <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>

            <div class="coaching-timeline coaching-timeline-four wide">
              <div class="coaching-timeline-heading">
                <strong>ระยะเวลาการพัฒนา</strong>
                <span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span>
              </div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
              <label><span>จำนวนครั้ง</span><input :value="row.sessionCount || ''" readonly /></label>
              <label><span>ระยะเวลาต่อครั้ง</span><input :value="row.sessionDuration || ''" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isForm('form_6_mentoring')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>หัวข้อที่ต้องการพัฒนา {{ rowIndex + 1 }}</strong><button type="button" disabled>ลบ</button></div>
          <div class="project-assignment-grid">
            <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea :value="row.skillTopic || ''" rows="3" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label class="wide"><span>เทคนิค</span><textarea :value="row.technique || ''" rows="2" readonly /></label>
            <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>
            <div class="coaching-timeline coaching-timeline-four wide">
              <div class="coaching-timeline-heading"><strong>ระยะเวลาการพัฒนา</strong><span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
              <label><span>จำนวนครั้ง</span><input :value="row.sessionCount ?? ''" readonly /></label>
              <label><span>ระยะเวลาต่อครั้ง</span><input :value="row.sessionDuration || ''" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isForm('form_7_group_activity')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>กิจกรรม {{ rowIndex + 1 }}</strong><button type="button" disabled>ลบ</button></div>
          <div class="project-assignment-grid">
            <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea :value="row.learningTopic || ''" rows="3" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label><span>เทคนิค</span><textarea :value="row.technique || ''" rows="3" readonly /></label>
            <label><span>เครื่องมือและเงื่อนไขการประเมิน</span><textarea :value="row.assessmentTools || ''" rows="3" readonly /></label>
            <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>
            <div class="coaching-timeline group-activity-timeline wide">
              <div class="coaching-timeline-heading"><strong>กำหนดการจัดกิจกรรม</strong><span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isForm('form_8_feedback')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>หัวข้อการพัฒนา {{ rowIndex + 1 }}</strong><button type="button" disabled>ลบ</button></div>
          <div class="project-assignment-grid">
            <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea :value="row.skillTopic || ''" rows="3" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label class="wide"><span>แหล่งข้อมูลป้อนกลับ</span><textarea :value="row.feedbackSource || ''" rows="2" readonly /></label>
            <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>
            <div class="coaching-timeline coaching-timeline-four wide">
              <div class="coaching-timeline-heading"><strong>ระยะการพัฒนา</strong><span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
              <label><span>จำนวนครั้ง</span><input :value="row.sessionCount ?? ''" readonly /></label>
              <label><span>ระยะเวลาต่อครั้ง</span><input :value="row.sessionDuration || ''" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isForm('form_9_field_trip')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>รายการศึกษาดูงาน {{ rowIndex + 1 }}</strong><button type="button" disabled>ลบ</button></div>
          <div class="project-assignment-grid">
            <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea :value="row.skillTopic || ''" rows="3" readonly /></label>
            <label><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label><span>สถานที่/แหล่งศึกษาดูงาน</span><textarea :value="row.learningPlace || ''" rows="3" readonly /></label>
            <label><span>เครื่องมือและเงื่อนไขการประเมิน</span><textarea :value="row.assessmentTools || ''" rows="3" readonly /></label>
            <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>
            <div class="coaching-timeline group-activity-timeline wide">
              <div class="coaching-timeline-heading"><strong>กำหนดการจัดกิจกรรม</strong><span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length && isForm('form_10_training')" class="project-assignment-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="project-assignment-item">
          <div class="project-assignment-item-head"><strong>ข้อมูลหลักสูตรที่เลือก</strong><span class="catalog-snapshot-note">ดึงจาก Learning Catalog</span></div>
          <div class="project-assignment-grid training-form-grid">
            <section class="training-catalog-summary training-wide">
              <div class="training-course-heading">
                <div><span>{{ row.courseCode || 'ไม่มีรหัสหลักสูตร' }}</span><strong>{{ row.courseName || 'ยังไม่ได้เลือกหลักสูตร' }}</strong></div>
                <em>{{ row.trainingType || '-' }}</em>
              </div>
              <p>{{ row.courseDescription || 'ไม่มีคำอธิบายหลักสูตร' }}</p>
              <dl>
                <div><dt>รูปแบบการอบรม</dt><dd>{{ row.trainingType || '-' }}</dd></div>
                <div><dt>จำนวนชั่วโมง</dt><dd>{{ row.hours !== '' && row.hours != null ? `${formatNumber(row.hours)} ชั่วโมง` : 'ไม่ระบุ' }}</dd></div>
                <div><dt>ค่าใช้จ่าย</dt><dd>{{ formatCurrency(row.cost) }}</dd></div>
              </dl>
            </section>
            <label class="training-wide"><span>เป้าหมายในการพัฒนา</span><textarea :value="row.developmentGoal || ''" rows="3" readonly /></label>
            <label class="training-wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea :value="row.additionalDetails || ''" rows="2" readonly /></label>
            <div class="coaching-timeline training-summary training-wide">
              <div class="coaching-timeline-heading"><strong>กำหนดการอบรม</strong><span>ระบุวันที่ที่ต้องการเข้าร่วมหลักสูตร</span></div>
              <label><span>วันที่เริ่มต้น</span><input :value="row.developmentStart || ''" type="date" readonly /></label>
              <label><span>วันที่สิ้นสุด</span><input :value="row.developmentEnd || ''" type="date" readonly /></label>
            </div>
          </div>
        </article>
      </div>

      <div v-else-if="rows.length" class="row-list">
        <article v-for="(row, rowIndex) in rows" :key="rowIndex" class="row-card">
          <div class="row-card-head"><strong>{{ rowLabel }} {{ rowIndex + 1 }}</strong></div>
          <div class="form-grid">
            <label v-for="field in ordinaryFields" :key="`${field.key}-${field.label}`" :class="{ wide: ['developmentApproach', 'additionalDetails', 'courseDescription'].includes(field.key) }">
              <span>{{ field.label }}</span>
              <textarea v-if="field.type === 'area'" :value="row[field.key] || ''" :rows="field.key.includes('additional') ? 2 : 3" readonly />
              <select v-else-if="field.type === 'choice'" :value="row[field.key] || ''" disabled>
                <option value="">ไม่ระบุ</option>
                <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
              </select>
              <input v-else :value="row[field.key] || ''" readonly />
            </label>

            <div v-for="group in multiChoiceFields" :key="group.key" class="choice-block wide">
              <span>{{ group.label }}</span>
              <div>
                <label v-for="field in group.options" :key="field.value || field.label" :class="{ selected: isSelected(row, field) }">
                  <i>{{ isSelected(row, field) ? '✓' : '' }}</i>
                  <strong>{{ field.label }}</strong>
                </label>
              </div>
            </div>

            <div v-if="timelineFields.length" class="timeline-block wide">
              <header><strong>ระยะเวลาดำเนินการ</strong><span>ข้อมูลตามที่พนักงานกำหนด</span></header>
              <div>
                <label v-for="field in timelineFields" :key="field.key">
                  <span>{{ field.label }}</span>
                  <input :type="inputType(field)" :value="row[field.key] || ''" readonly />
                </label>
              </div>
            </div>
          </div>
        </article>
      </div>
      <div v-else class="empty">ไม่มีรายละเอียดที่บันทึกไว้</div>
    </section>
  </div>
</template>

<style scoped>
.readonly-form { padding: 18px; background: #f8fafc; }
.form-block { margin-top: 14px; overflow: hidden; border: 1px solid #d8e0e9; border-radius: 8px; background: #fff; }
.readonly-form > .form-block:first-child { margin-top: 0; }
.form-block > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #d8e0e9; background: #f8fafc; padding: 10px 12px; }
.form-block > header h4 { margin: 0; font-size: 16px; }
.form-block > header span { color: #667085; font-size: 13px; font-weight: 900; }
.readonly-block > header { background: #edf8f4; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
.form-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.form-grid .wide { grid-column: 1 / -1; }
.form-grid > label, .timeline-block label { display: grid; gap: 6px; min-width: 0; color: #475467; font-size: 13px; font-weight: 900; }
input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid #cfd8e3; border-radius: 7px; background: #f8fafc; padding: 9px 10px; color: #1f2937; font: inherit; opacity: 1; -webkit-text-fill-color: #1f2937; }
textarea { min-height: 80px; resize: none; line-height: 1.55; }
.row-list { display: grid; gap: 12px; padding: 14px; }
.row-card { overflow: hidden; border: 1px solid #d8e0e9; border-radius: 8px; background: #fbfdff; }
.row-card-head { border-bottom: 1px solid #e3e9f0; background: #f8fafc; padding: 10px 12px; }
.row-card-head strong { color: #172033; font-size: 15px; }
.project-assignment-list { display: grid; gap: 12px; padding: 14px; }
.project-assignment-item { overflow: hidden; border: 1px solid #d8e0e9; border-radius: 8px; background: #fbfdff; }
.project-assignment-item-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #e3e9f0; background: #f8fafc; padding: 10px 12px; }
.project-assignment-item-head strong { color: #172033; font-size: 15px; }
.project-assignment-item-head button { border: 1px solid #fecaca; border-radius: 6px; background: #fff; color: #b42318; padding: 6px 9px; font-size: 13px; font-weight: 900; opacity: .4; cursor: not-allowed; }
.project-assignment-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
.project-assignment-grid.training-form-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); align-items: start; }
.training-form-grid > .training-wide { grid-column: 1 / -1; }
.project-assignment-grid > .wide { grid-column: 1 / -1; }
.project-assignment-grid label { display: grid; gap: 6px; min-width: 0; color: #475467; font-size: 13px; font-weight: 900; }
.project-assignment-grid input, .project-assignment-grid textarea { width: 100%; box-sizing: border-box; border: 1px solid #cfd8e3; border-radius: 7px; background: #f8fafc; padding: 9px 10px; color: #1f2937; font: inherit; opacity: 1; -webkit-text-fill-color: #1f2937; }
.project-assignment-grid textarea { min-height: 80px; resize: none; line-height: 1.55; }
.coaching-approach-field { display: grid; gap: 12px; border: 1px solid #dce9e5; border-radius: 9px; background: #f8fcfb; padding: 12px; }
.coaching-approach-label { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #344054; font-size: 14px; font-weight: 900; }
.coaching-approach-label > div { display: grid; gap: 2px; }
.coaching-approach-label small { color: var(--color-text-muted); font-size: 12px; font-weight: 700; }
.coaching-approach-label > button { border: 1px solid #9fd0c4; border-radius: 999px; background: #fff; color: #1d6b59; padding: 6px 11px; font-size: 12px; font-weight: 900; opacity: 1; }
.coaching-approach-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.coaching-approach-option { position: relative; display: flex; align-items: center; gap: 9px; min-height: 48px; border: 1px solid #ccd8e0; border-radius: 8px; background: #fff; color: #4b5565; padding: 9px 30px 9px 10px; }
.coaching-approach-option strong { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 7px; background: #edf1f5; color: #344054; }
.coaching-approach-option span { font-size: 13px; font-weight: 900; }
.coaching-approach-option i { position: absolute; right: 10px; display: none; color: var(--color-primary); font-style: normal; font-weight: 900; }
.coaching-approach-option.selected { border-color: #72b9a7; background: #edf8f4; color: #185f50; }
.coaching-approach-option.selected strong { background: var(--color-primary); color: #fff; }
.coaching-approach-option.selected i { display: block; }
.coaching-timeline { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; border: 1px solid #d8e2e8; border-radius: 9px; background: #fff; padding: 12px; }
.coaching-timeline.coaching-timeline-four { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.coaching-timeline.group-activity-timeline, .coaching-timeline.training-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.coaching-timeline-heading { grid-column: 1 / -1; display: flex; align-items: baseline; justify-content: space-between; gap: 12px; border-bottom: 1px solid #e8edf1; padding: 0 2px 10px; }
.coaching-timeline-heading strong { color: #344054; font-size: 14px; }
.coaching-timeline-heading span { color: var(--color-text-muted); font-size: 12px; font-weight: 700; }
.catalog-snapshot-note { color: #667085; font-size: 13px; font-weight: 800; }
.training-catalog-summary { display: grid; gap: 12px; border: 1px solid #d8e3ef; border-radius: 9px; background: #f8fafc; padding: 14px; }
.training-course-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.training-course-heading > div { display: grid; gap: 3px; min-width: 0; }
.training-course-heading span { color: #667085; font-size: 13px; font-weight: 900; }
.training-course-heading strong { color: #172033; font-size: 15px; line-height: 1.45; }
.training-course-heading em { flex: 0 0 auto; border: 1px solid #cfe0f5; border-radius: 999px; background: #eff6ff; color: #2563eb; padding: 5px 10px; font-size: 13px; font-style: normal; font-weight: 900; }
.training-catalog-summary > p { min-width: 0; margin: 0; color: #475467; font-size: 14px; line-height: 1.7; white-space: pre-line; overflow-wrap: anywhere; }
.training-catalog-summary dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; overflow: hidden; margin: 0; border: 1px solid #e1e8f0; border-radius: 8px; background: #e1e8f0; }
.training-catalog-summary dl > div { display: grid; gap: 3px; background: #fff; padding: 10px 12px; }
.training-catalog-summary dt { color: var(--color-text-muted); font-size: 12px; font-weight: 800; }
.training-catalog-summary dd { margin: 0; color: #273142; font-size: 14px; font-weight: 900; }
.timeline-block { overflow: hidden; border: 1px solid #d8e2e8; border-radius: 9px; background: #fff; }
.timeline-block > header { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #e8edf1; padding: 10px 12px; }
.timeline-block > header strong { color: #344054; font-size: 14px; }
.timeline-block > header span { color: var(--color-text-muted); font-size: 12px; }
.timeline-block > div { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; padding: 12px; }
.choice-block { display: grid; gap: 8px; color: #475467; font-size: 13px; font-weight: 900; }
.choice-block > div { display: flex; flex-wrap: wrap; gap: 8px; }
.choice-block label { display: flex; align-items: center; gap: 8px; min-width: 74px; border: 1px solid #d8e0e9; border-radius: 7px; background: #fff; padding: 8px 10px; }
.choice-block i { display: grid; place-items: center; width: 20px; height: 20px; border: 1px solid #b8c7d9; border-radius: 5px; color: #fff; font-style: normal; }
.choice-block label.selected { border-color: #72b9a7; background: #edf8f4; color: #185f50; }
.choice-block label.selected i { border-color: var(--color-primary); background: var(--color-primary); }
.empty { margin: 14px; border: 1px dashed var(--color-border); border-radius: 8px; padding: 22px; color: var(--color-text-muted); text-align: center; }
@media (max-width: 900px) {
  .readonly-form { padding: 12px; }
  .form-grid, .form-grid.three, .project-assignment-grid, .project-assignment-grid.training-form-grid, .coaching-approach-options, .coaching-timeline, .coaching-timeline.coaching-timeline-four, .coaching-timeline.group-activity-timeline, .coaching-timeline.training-summary { grid-template-columns: 1fr; }
  .training-catalog-summary dl { grid-template-columns: 1fr; }
  .coaching-timeline-heading { align-items: flex-start; flex-direction: column; gap: 3px; }
}
</style>
