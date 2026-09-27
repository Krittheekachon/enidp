export type DetailField = {
  key: string;
  label: string;
  type?: 'text' | 'date' | 'choice' | 'area' | 'supervisor-chain' | 'expert-name';
  choices?: string[];
  showWhen?: { key: string; value: string };
};

export type RowField = {
  key: string;
  label: string;
  type?: 'text' | 'choice' | 'checkbox-choice' | 'multi-checkbox' | 'area';
  choices?: string[];
  value?: string;
  placeholder?: string;
  owner?: string;
  group?: string;
};

export type IdpFormDefinition = {
  number: string;
  title: string;
  focus: string;
  detailTitle: string;
  detailFields: DetailField[];
  rowTitle: string;
  rowFields: RowField[];
  note?: string;
};

export const formDefinitions: Record<string, IdpFormDefinition> = {
  form_1_idp_plan: {
    number: '1',
    title: 'แบบฟอร์มกำหนดแผนพัฒนารายบุคคล (Individual Development Plan: IDP)',
    focus: 'IDP Master Plan',
    detailTitle: 'คำรับรอง',
    detailFields: [
      { key: 'employeeAcknowledgement', label: 'คำรับรองผู้รับการพัฒนา', type: 'area' },
      { key: 'supervisorAcknowledgement', label: 'คำรับรองหัวหน้างาน', type: 'area' },
    ],
    rowTitle: 'ข้อมูลประกอบการวางแผน',
    rowFields: [
      { key: 'competency', label: 'ความสามารถที่ต้องการพัฒนา', type: 'area' },
      { key: 'behaviorResult', label: 'เป้าหมายในการพัฒนา (Behavior Result)', type: 'area' },
      { key: 'successCriteria', label: 'ตัวชี้วัดสำเร็จของการพัฒนา', type: 'area' },
      { key: 'developmentMethod', label: 'เครื่องมือ/แนวทางการพัฒนา', type: 'area' },
      { key: 'documentReference', label: 'เอกสารประกอบหมายเลข' },
      { key: 'timeline', label: 'ระยะเวลาที่คาดว่าจะดำเนินการ' },
    ],
  },
  form_2_performance_report: {
    number: '2',
    title: 'แบบฟอร์มรายงานผลการพัฒนาตามแผนพัฒนารายบุคคล (IDP Performance Report)',
    focus: 'IDP Performance Report',
    detailTitle: 'ข้อมูลสรุปผล',
    detailFields: [
      { key: 'summary', label: 'สรุปผลรายงานการพัฒนา', type: 'area' },
    ],
    rowTitle: 'รายงานผลการพัฒนา',
    rowFields: [
      { key: 'competency', label: 'ความสามารถที่ต้องการพัฒนา', type: 'area' },
      { key: 'behaviorResult', label: 'เป้าหมายในการพัฒนา (Behavior Result)', type: 'area' },
      { key: 'developmentMethod', label: 'วิธีการพัฒนา', type: 'area' },
      { key: 'documentReference', label: 'เอกสารประกอบหมายเลข' },
      { key: 'operationResult', label: 'ผลการดำเนินการพัฒนาตามแผน', type: 'checkbox-choice', choices: ['เป็นไปตามแผน', 'ไม่เป็นไปตามแผน'] },
      { key: 'developmentResult', label: 'ผลการพัฒนา', type: 'checkbox-choice', choices: ['บรรลุเกินเป้าหมาย', 'บรรลุตามเป้าหมาย', 'ไม่บรรลุผล'] },
    ],
  },
  form_3_project_assignment: {
    number: '3',
    title: 'แบบฟอร์มการมอบหมายงานโครงการ/งานพิเศษ',
    focus: '',
    detailTitle: '',
    detailFields: [],
    rowTitle: 'รายละเอียดงานที่ได้รับมอบหมาย',
    rowFields: [
      { key: 'assignmentTopic', label: 'หัวข้องานโครงการ/งานพิเศษที่ได้รับมอบหมาย', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'developmentApproach', label: 'รายละเอียด', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
    ],
  },
  form_4_ojt: {
    number: '4',
    title: 'แบบฟอร์มการเรียนรู้จากการปฏิบัติงานจริง (On the Job Training/Learning: OJT)',
    focus: '',
    detailTitle: 'ผู้สอนงาน',
    detailFields: [
      { key: 'trainerType', label: 'ผู้สอนงาน', type: 'choice', choices: ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'] },
      { key: 'trainerExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'trainerType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'รายละเอียดการฝึกปฏิบัติงาน',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นการฝึกปฏิบัติงาน', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'developmentApproach', label: 'วิธีการ', type: 'area' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'hours', label: 'จำนวนชั่วโมง' },
    ],
  },
  form_5_coaching: {
    number: '5',
    title: 'แบบฟอร์มการสอนงาน (Coaching)',
    focus: '',
    detailTitle: 'ผู้สอนงาน',
    detailFields: [
      { key: 'coachType', label: 'ผู้สอนงาน', type: 'choice', choices: ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'] },
      { key: 'coachExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'coachType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'รายละเอียดหัวข้อการสอนงาน',
    rowFields: [
      { key: 'topic', label: 'หัวข้อทักษะ/ประเด็นการสอนงาน', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'developmentApproach', label: 'วิธีการ', type: 'area' },
      { key: 'coachingApproaches', label: 'A', type: 'multi-checkbox', value: 'A', group: 'แนวทางการสอนงาน' },
      { key: 'coachingApproaches', label: 'B', type: 'multi-checkbox', value: 'B', group: 'แนวทางการสอนงาน' },
      { key: 'coachingApproaches', label: 'C', type: 'multi-checkbox', value: 'C', group: 'แนวทางการสอนงาน' },
      { key: 'coachingApproaches', label: 'D', type: 'multi-checkbox', value: 'D', group: 'แนวทางการสอนงาน' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'sessionCount', label: 'จำนวนครั้ง' },
      { key: 'sessionDuration', label: 'ระยะเวลาต่อครั้ง', placeholder: 'เช่น ครั้งละ 1 ชั่วโมง' },
    ],
  },
  form_6_mentoring: {
    number: '6',
    title: 'แบบฟอร์มการเป็นพี่เลี้ยง (Mentoring)',
    focus: '',
    detailTitle: 'ผู้สอนงาน',
    detailFields: [
      { key: 'mentorType', label: 'ผู้สอนงาน', type: 'choice', choices: ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'] },
      { key: 'mentorExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'mentorType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'รายละเอียดหัวข้อที่ต้องการพัฒนา',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'technique', label: 'เทคนิค', type: 'area' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'sessionCount', label: 'จำนวนครั้ง' },
      { key: 'sessionDuration', label: 'ระยะเวลาต่อครั้ง', placeholder: 'เช่น ครั้งละ 1 ชั่วโมง' },
    ],
  },
  form_7_group_activity: {
    number: '7',
    title: 'แบบฟอร์มการเรียนรู้แบบกระบวนการกลุ่ม (Group Activity)',
    focus: '',
    detailTitle: 'ผู้อำนวยการ/ผู้นำกิจกรรม',
    detailFields: [
      { key: 'facilitatorType', label: 'ผู้อำนวยการ/ผู้นำกิจกรรม', type: 'choice', choices: ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'] },
      { key: 'facilitatorExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'facilitatorType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'รายละเอียดกิจกรรม',
    rowFields: [
      { key: 'learningTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'technique', label: 'เทคนิค', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'assessmentTools', label: 'เครื่องมือและเงื่อนไขการประเมิน', type: 'area' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
    ],
  },
  form_8_feedback: {
    number: '8',
    title: 'แบบฟอร์มการเรียนรู้ผ่านการให้ข้อมูลป้อนกลับ (Feedback)',
    focus: '',
    detailTitle: 'ผู้ให้ข้อมูล',
    detailFields: [
      { key: 'feedbackProviderType', label: 'ผู้ให้ข้อมูล', type: 'choice', choices: ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'] },
      { key: 'feedbackExpertName', label: 'ชื่อผู้เชี่ยวชาญ', type: 'expert-name', showWhen: { key: 'feedbackProviderType', value: 'ผู้เชี่ยวชาญ' } },
    ],
    rowTitle: 'รายละเอียดหัวข้อการพัฒนา',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'feedbackSource', label: 'แหล่งข้อมูลป้อนกลับ', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'sessionCount', label: 'จำนวนครั้ง' },
      { key: 'sessionDuration', label: 'ระยะเวลาต่อครั้ง', placeholder: 'เช่น ครั้งละ 1 ชั่วโมง' },
    ],
  },
  form_9_field_trip: {
    number: '9',
    title: 'แบบฟอร์มการเรียนรู้นอกสถานที่ (Field Trip)',
    focus: '',
    detailTitle: '',
    detailFields: [],
    rowTitle: 'รายละเอียดการศึกษาดูงาน',
    rowFields: [
      { key: 'skillTopic', label: 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา', type: 'area' },
      { key: 'learningPlace', label: 'สถานที่/แหล่งศึกษาดูงาน', type: 'area' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'assessmentTools', label: 'เครื่องมือและเงื่อนไขการประเมิน', type: 'area' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
    ],
  },
  form_10_training: {
    number: '10',
    title: 'แบบฟอร์มการฝึกอบรม',
    focus: '',
    detailTitle: '',
    detailFields: [],
    rowTitle: 'รายละเอียดหลักสูตรอบรม',
    rowFields: [
      { key: 'trainingType', label: 'รูปแบบการอบรม', type: 'choice', choices: ['In-class Training', 'e-Learning'] },
      { key: 'courseCode', label: 'รหัสหลักสูตร' },
      { key: 'courseName', label: 'ชื่อหลักสูตร', type: 'area' },
      { key: 'courseDescription', label: 'คำอธิบายหลักสูตร', type: 'area' },
      { key: 'developmentStart', label: 'วันที่เริ่มต้น' },
      { key: 'developmentEnd', label: 'วันที่สิ้นสุด' },
      { key: 'hours', label: 'จำนวนชั่วโมง' },
      { key: 'cost', label: 'ค่าใช้จ่าย (บาท)' },
      { key: 'developmentGoal', label: 'เป้าหมายในการพัฒนา', type: 'area' },
      { key: 'additionalDetails', label: 'รายละเอียดเพิ่มเติม (ถ้ามี)', type: 'area' },
    ],
  },
};

export const formOptionFor = (code: string) => {
  const form = formDefinitions[code];
  return form ? { value: code, label: form.title } : null;
};

export const allPreviewFormOptions = Object.keys(formDefinitions).map((code) => ({
  value: code,
  label: formDefinitions[code].title,
}));

export const hasGroupedRowFields = (fields: RowField[]) => fields.some((field) => Boolean(field.group));

export const rowHeaderCells = (fields: RowField[]) => {
  const cells: Array<{ key: string; label: string; owner?: string; colspan: number; rowspan: number }> = [];
  for (let index = 0; index < fields.length; index += 1) {
    const field = fields[index];
    if (!field.group) {
      cells.push({ key: field.key, label: field.label, owner: field.owner, colspan: 1, rowspan: hasGroupedRowFields(fields) ? 2 : 1 });
      continue;
    }

    const grouped = fields.slice(index).filter((item, itemIndex) =>
      itemIndex === 0 || (item.group === field.group && fields[index + itemIndex - 1]?.group === field.group)
    );
    const colspan = grouped.findIndex((item) => item.group !== field.group);
    const groupSize = colspan === -1 ? grouped.length : colspan;
    cells.push({ key: `${field.group}-${index}`, label: field.group, colspan: groupSize, rowspan: 1 });
    index += groupSize - 1;
  }

  return cells;
};

export const rowSubHeaderFields = (fields: RowField[]) => fields.filter((field) => field.group);
