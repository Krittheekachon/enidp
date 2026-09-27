<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
  formDefinitions,
  hasGroupedRowFields,
  rowHeaderCells,
  rowSubHeaderFields,
  type RowField,
} from '../../idpFormDefinitions';
import { coachingApproachDescriptions as fullCoachingApproachDescriptions } from '../../coachingApproachDescriptions';

type Gap = {
  id: number;
  competencyId: number;
  cd: string;
  n: string;
  t?: string;
  expected?: number;
  actual?: number;
  gap?: number;
  note?: string;
  status?: string;
  requiresIdp?: boolean;
  missingIndicators?: Array<{
    level: number;
    label: string;
    indicators: Array<{ code: string; description: string }>;
  }>;
};

type Method = { key: string; label: string; desc?: string };
type DevelopmentTool = { id: number; focusType: string; title: string; formCode?: string; isActive?: boolean };
type Catalog = {
  id: number;
  code?: string;
  name: string;
  deliveryType?: string;
  formCode?: string;
  competencyIds?: number[];
  description?: string;
  hours?: number | string | null;
  cost?: number | string | null;
  expectedLevels?: number[];
  isActive?: boolean;
};
type SupervisorChainOption = {
  id: number;
  step: number;
  label: string;
  name: string;
  position?: string;
};
type FormDetails = Record<string, any>;
type Activity = {
  clientKey: string;
  methodKey: string;
  developmentToolId: number | null;
  learningCatalogId: number | null;
  activityName: string;
  activityDescription: string;
  documentReferenceNumber: string;
  weightPercent: number | '';
  startDate: string;
  endDate: string;
  formCode: string;
  formDetails: FormDetails;
};
type Plan = {
  competencyGapId: number;
  goal: string;
  successCriteria: string;
  status: string;
  submissionVersion: number;
  currentReviewStep: number | null;
  rejectComment: string;
  rejectReviewerName: string;
  activities: Activity[];
};

const props = defineProps<{
  learningMethods?: Method[];
  idpLearningMethods?: DevelopmentTool[];
  learningCatalogs?: Catalog[];
  gaps?: Gap[];
  idp?: { status?: string; items?: Array<Omit<Plan, 'activities'> & { activities?: Activity[] }> } | null;
  user?: any;
}>();

const page = usePage();
const plans = ref<Plan[]>([]);
const selectedGapId = ref<number | null>(null);
const activeFormActivityKey = ref<string | null>(null);
type BuilderFocus = 'experiential' | 'social' | 'formal';
const activityBuilderOpen = ref(false);
const activityBuilderFocus = ref<BuilderFocus>('experiential');
const replacementActivityKey = ref<string | null>(null);
const showCoachingApproachHelp = ref(false);
const saveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const submitError = ref('');
const lastSavedSignature = ref('');
const queuedSave = ref(false);
const forceQueuedSave = ref(false);
let activitySequence = 0;
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

const idpGaps = computed(() => (props.gaps || [])
  .filter((gap) => gap.requiresIdp && Number(gap.gap) < 0 && ['approved', 'dean_approved'].includes(gap.status || ''))
  .sort((a, b) => String(a.cd).localeCompare(String(b.cd))));
const methods = computed(() => props.learningMethods || []);
const activeTools = computed(() => (props.idpLearningMethods || []).filter((tool) => tool.isActive !== false));
const activeCatalogs = computed(() => (props.learningCatalogs || []).filter((catalog) => catalog.isActive !== false));
const supervisorChainOptions = computed<SupervisorChainOption[]>(() => props.user?.supervisorChain || []);
const idpApprovalSteps = computed<SupervisorChainOption[]>(() =>
  Array.isArray(props.user?.idpReviewerSteps) ? props.user.idpReviewerSteps : [],
);
const hasIdpReviewerSteps = computed(() => idpApprovalSteps.value.length > 0);
const selectedGap = computed(() => idpGaps.value.find((gap) => gap.id === selectedGapId.value) || null);
const selectedPlan = computed(() => plans.value.find((plan) => plan.competencyGapId === selectedGapId.value) || null);
const isReviewStatus = (status: string) => /^review_step_\d+$/.test(status);
const isPlanLocked = (plan: Plan | null) =>
  plan?.status === 'approved' || isReviewStatus(plan?.status || '');
const selectedPlanLocked = computed(() => isPlanLocked(selectedPlan.value));
const errors = computed(() => page.props.errors || {});
const coachingApproachDescriptions = [
  {
    code: 'A',
    title: 'ส่งเสริม',
    summary: 'แนวทางที่เน้นการส่งเสริมคุณลักษณะและพฤติกรรมที่เหมาะสม',
    items: [
      'ความรับผิดชอบ: รับผิดชอบต่อหน้าที่และการกระทำของตนเอง',
      'ความซื่อสัตย์และจริยธรรม: ยึดมั่นในจริยธรรมในการทำงาน',
      'ความขยันหมั่นเพียร: มีความพยายามและมุ่งมั่นในการทำงานเพื่อบรรลุเป้าหมาย',
      'ความอดทนและการแก้ไขปัญหา: อดทนและมีทักษะในการแก้ไขปัญหาเมื่อพบความท้าทาย',
      'การทำงานเป็นทีม: ทำงานร่วมกับผู้อื่นได้อย่างมีประสิทธิภาพ',
      'ความคิดสร้างสรรค์และนวัตกรรม: คิดค้นวิธีการใหม่ ๆ ในการทำงาน',
      'การพัฒนาตนเอง: เรียนรู้และพัฒนาทักษะใหม่อย่างต่อเนื่อง',
      'การสื่อสารที่มีประสิทธิภาพ: สื่อสารได้อย่างชัดเจน',
      'ความมั่นใจในตนเอง: มั่นใจในความสามารถของตนเองและไม่กลัวที่จะเผชิญความท้าทาย',
      'การคิดเชิงวิพากษ์: วิเคราะห์และประเมินสถานการณ์ได้รอบคอบ',
      'ความยืดหยุ่นและการปรับตัว: ปรับตัวได้ดีในสถานการณ์ที่เปลี่ยนแปลง',
      'การให้และรับข้อเสนอแนะ: รับข้อเสนอแนะเพื่อการพัฒนาอย่างสร้างสรรค์',
      'ความเป็นผู้นำ: นำทีมและมีอิทธิพลเชิงบวกต่อผู้อื่น',
      'ความสามารถในการตัดสินใจ: ตัดสินใจอย่างรอบคอบและรวดเร็วในสถานการณ์ที่ต้องการ',
    ],
  },
  {
    code: 'B',
    title: 'สร้างสรรค์',
    summary: 'แนวทางที่เน้นความคิดสร้างสรรค์และการสร้างแนวทางใหม่',
    items: [
      'ความคิดนอกกรอบ: คิดต่างจากคนทั่วไปและหาแนวทางใหม่ในการแก้ปัญหา',
      'การมีจินตนาการสูง: สร้างภาพในจินตนาการและสร้างสรรค์สิ่งใหม่',
      'การยอมรับความเสี่ยง: กล้าลองสิ่งใหม่แม้มีความเสี่ยง',
      'การมองเห็นโอกาส: มองเห็นโอกาสในสถานการณ์ต่าง ๆ',
      'ความยืดหยุ่น: ปรับตัวและปรับวิธีทำงานตามสถานการณ์',
      'ความอยากรู้อยากเห็น: สนใจเรียนรู้สิ่งใหม่อยู่เสมอ',
      'การมองโลกในแง่บวก: เห็นโอกาสและไม่ย่อท้อต่ออุปสรรค',
      'การคิดวิเคราะห์: วิเคราะห์และใช้ข้อมูลสร้างสรรค์ผลงาน',
      'การทำงานร่วมกับผู้อื่น: รับฟังและแลกเปลี่ยนความคิดเห็นได้ดี',
      'ความอดทนและความพยายาม: มุ่งมั่นแม้เจออุปสรรค',
      'ความมั่นใจในตนเอง: เชื่อมั่นในความคิดและความสามารถของตนเอง',
      'การสื่อสารที่มีประสิทธิภาพ: สื่อสารแนวคิดสร้างสรรค์ให้ผู้อื่นเข้าใจได้อย่างชัดเจน',
      'การรับข้อเสนอแนะ: รับฟังข้อเสนอแนะเพื่อปรับปรุงผลงาน',
    ],
  },
  {
    code: 'C',
    title: 'กระตุ้น',
    summary: 'แนวทางที่เน้นการกระตุ้นความมั่นใจ แรงจูงใจ และการลงมือทำ',
    items: [
      'ความรับผิดชอบ: กระตุ้นให้รับผิดชอบต่อหน้าที่และการกระทำของตนเอง',
      'ความคิดสร้างสรรค์: ส่งเสริมให้คิดนอกกรอบและเสนอแนวคิดใหม่',
      'การทำงานเป็นทีม: กระตุ้นการทำงานร่วมกันและแบ่งปันข้อมูล',
      'การพัฒนาตนเอง: สนับสนุนให้เรียนรู้และพัฒนาทักษะใหม่อย่างต่อเนื่อง',
      'ความขยันหมั่นเพียร: กระตุ้นให้มุ่งมั่นและพยายามบรรลุเป้าหมาย',
      'การคิดเชิงวิพากษ์: ส่งเสริมการวิเคราะห์และประเมินสถานการณ์อย่างรอบคอบ',
      'ความมั่นใจในตนเอง: กระตุ้นให้กล้าตัดสินใจในสถานการณ์ต่าง ๆ',
      'การให้และรับข้อเสนอแนะ: สนับสนุนการแลกเปลี่ยนข้อเสนอแนะเพื่อพัฒนา',
      'การจัดการเวลา: กระตุ้นให้บริหารเวลาอย่างมีประสิทธิภาพ',
      'ความยืดหยุ่นและการปรับตัว: ส่งเสริมการปรับตัวต่อสถานการณ์เปลี่ยนแปลง',
      'ความเป็นผู้นำ: กระตุ้นศักยภาพด้านการนำทีม',
      'การสื่อสารที่มีประสิทธิภาพ: สนับสนุนการสื่อสารอย่างชัดเจน',
    ],
  },
  {
    code: 'D',
    title: 'แก้ไขปัญหา',
    summary: 'แนวทางที่เน้นการวิเคราะห์ปัญหา เลือกวิธีแก้ และตัดสินใจอย่างมีข้อมูล',
    items: [
      'การคิดเชิงวิพากษ์: วิเคราะห์ข้อมูลและสถานการณ์อย่างรอบคอบ',
      'ความคิดสร้างสรรค์: คิดนอกกรอบและเสนอวิธีใหม่ในการแก้ปัญหา',
      'ความสามารถในการวิเคราะห์: ใช้เครื่องมือ เช่น SWOT เพื่อหาสาเหตุและผลกระทบของปัญหา',
      'ความมั่นใจในตนเอง: มั่นใจในการตัดสินใจและการกระทำแม้ในสถานการณ์ยาก',
      'ความยืดหยุ่น: ปรับวิธีหรือแผนตามสถานการณ์และข้อมูลใหม่',
      'การรวบรวมข้อมูล: จัดการข้อมูลที่เกี่ยวข้องเพื่อสนับสนุนการตัดสินใจ',
      'การทำงานเป็นทีม: ทำงานร่วมกับผู้อื่นเพื่อหาวิธีแก้ปัญหา',
      'ทักษะการสื่อสาร: สื่อสารปัญหาและแนวทางแก้ไขให้ทีมเข้าใจ',
      'การจัดลำดับความสำคัญ: กำหนดลำดับการแก้ไขอย่างมีประสิทธิภาพ',
      'การใช้เครื่องมือและเทคนิค: ใช้เครื่องมือที่ช่วยแก้ปัญหา เช่น การวิเคราะห์ข้อมูลหรือการสร้างแผนภูมิ',
      'ความอดทนและความพยายาม: ไม่ย่อท้อต่อปัญหาและความท้าทาย',
      'การเรียนรู้จากประสบการณ์: นำบทเรียนจากความผิดพลาดมาปรับปรุงแนวทางในอนาคต',
    ],
  },
];

const nextClientKey = () => `activity-${Date.now()}-${++activitySequence}`;
const blankActivity = (): Activity => ({
  clientKey: nextClientKey(),
  methodKey: '',
  developmentToolId: null,
  learningCatalogId: null,
  activityName: '',
  activityDescription: '',
  documentReferenceNumber: '',
  weightPercent: '',
  startDate: '',
  endDate: '',
  formCode: '',
  formDetails: {},
});
const savedPlans = () => new Map((props.idp?.items || []).map((item) => [item.competencyGapId, item]));

const hydratePlans = () => {
  const saved = savedPlans();
  plans.value = idpGaps.value.map((gap) => {
    const item = saved.get(gap.id);
    return {
      competencyGapId: gap.id,
      goal: item?.goal || '',
      successCriteria: item?.successCriteria || '',
      status: item?.status || 'draft',
      submissionVersion: item?.submissionVersion || 0,
      currentReviewStep: item?.currentReviewStep || null,
      rejectComment: item?.rejectComment || '',
      rejectReviewerName: item?.rejectReviewerName || '',
      activities: (item?.activities || [])
        .map((activity) => {
          const normalizedActivity = {
          ...activity,
          clientKey: nextClientKey(),
          formCode: activity.formCode || '',
          formDetails: normalizeFormDetails(activity.formCode || '', activity.formDetails || {}),
          };
          return ['draft', 'revision_required'].includes(item?.status || 'draft')
            ? syncCatalogSnapshot(normalizedActivity)
            : normalizedActivity;
        }),
    };
  });
  if (!selectedGapId.value || !plans.value.some((plan) => plan.competencyGapId === selectedGapId.value)) {
    selectedGapId.value = plans.value[0]?.competencyGapId || null;
  }
  lastSavedSignature.value = signature();
};

const gapForPlan = (plan: Plan) => idpGaps.value.find((gap) => gap.id === plan.competencyGapId);
const weightTotal = (plan: Plan) => plan.activities.reduce((sum, item) => sum + Number(item.weightPercent || 0), 0);
const updateActivityWeight = (activity: Activity, event: Event) => {
  const input = event.target as HTMLInputElement;
  const digits = input.value.replace(/\D/g, '');
  const normalized = digits === '' ? '' : String(Math.min(Number(digits), 100));
  input.value = normalized;
  activity.weightPercent = normalized === '' ? '' : Number(normalized);
};
const missingIndicators = (gap: Gap) => (gap.missingIndicators || []).flatMap((group) =>
  group.indicators.map((indicator) => ({ ...indicator, level: group.level })));
const formatNumber = (value: unknown) => {
  const number = Number(value);
  return Number.isFinite(number) ? number.toFixed(Number.isInteger(number) ? 0 : 2) : '-';
};
const formatCurrency = (value: unknown) => {
  if (value === '' || value === null || value === undefined) return 'ไม่ระบุ';
  const number = Number(value);
  return Number.isFinite(number) ? `${number.toLocaleString('th-TH', { maximumFractionDigits: 2 })} บาท` : 'ไม่ระบุ';
};
const focusType = (methodKey: string) => {
  const normalized = methodKey.toLowerCase();
  if (normalized.includes('experiential') || normalized.includes('experience')) return 'experiential';
  if (normalized.includes('social')) return 'social';
  if (normalized.includes('formal')) return 'formal';
  return normalized;
};
const catalogsFor = (gap: Gap) => activeCatalogs.value.filter((catalog) =>
  (catalog.competencyIds || []).includes(gap.competencyId));
const catalogMatchesExpectedLevel = (catalog: Catalog, gap: Gap | null) => {
  const levels = catalog.expectedLevels || [];
  return !levels.length || !gap?.expected || levels.includes(Number(gap.expected));
};
const methodLabel = (key: string) => methods.value.find((method) => method.key === key)?.label || key;
const toolFor = (activity: Activity) => activeTools.value.find((item) => item.id === Number(activity.developmentToolId));
const catalogFor = (activity: Activity) => activeCatalogs.value.find((item) => item.id === Number(activity.learningCatalogId));
const syncCatalogSnapshot = (activity: Activity): Activity => {
  if (!activity.learningCatalogId) return activity;
  const catalog = activeCatalogs.value.find((item) => item.id === Number(activity.learningCatalogId));
  if (!catalog) return activity;
  const details = normalizeFormDetails(activity.formCode || catalog.formCode || 'form_10_training', activity.formDetails);
  const currentRow = details.planRows?.[0] || {};
  return {
    ...activity,
    activityName: catalog.name,
    activityDescription: catalog.description || '',
    formCode: catalog.formCode || 'form_10_training',
    formDetails: {
      ...details,
      planRows: [{
        ...currentRow,
        trainingType: catalog.deliveryType === 'in_class' ? 'In-class Training' : 'e-Learning',
        courseCode: catalog.code || '',
        courseName: catalog.name || '',
        courseDescription: catalog.description || '',
        hours: catalog.hours ?? null,
        cost: catalog.cost ?? null,
      }],
    },
  };
};
const formCodeForActivity = (activity: Activity) => {
  if (['experiential', 'social'].includes(focusType(activity.methodKey))) {
    const tool = toolFor(activity);
    if (tool && typeof tool.formCode === 'string') {
      return tool.formCode;
    }
    return activity.formCode || '';
  }

  if (focusType(activity.methodKey) === 'formal') {
    return catalogFor(activity)?.formCode || activity.formCode || 'form_10_training';
  }

  return '';
};
const methodKeyForFocus = (focus: BuilderFocus) =>
  methods.value.find((method) => focusType(method.key) === focus)?.key || `${focus}-learning`;
const builderTools = computed(() => activeTools.value.filter((tool) => tool.focusType === activityBuilderFocus.value));
const builderCatalogs = computed(() => selectedGap.value ? catalogsFor(selectedGap.value) : []);
const effectiveFormCode = (activity: Activity) => {
  if (['experiential', 'social'].includes(focusType(activity.methodKey)) && activity.developmentToolId) {
    return formCodeForActivity(activity);
  }
  return activity.formCode || formCodeForActivity(activity);
};
const formDefinitionFor = (activity: Activity) => formDefinitions[effectiveFormCode(activity)] || null;
const isProjectAssignmentForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_3_project_assignment');
const isOjtForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_4_ojt');
const isCoachingForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_5_coaching');
const isMentoringForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_6_mentoring');
const isGroupActivityForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_7_group_activity');
const isFeedbackForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_8_feedback');
const isFieldTripForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_9_field_trip');
const isTrainingForm = (activity: Activity | null) =>
  Boolean(activity && effectiveFormCode(activity) === 'form_10_training');
const isReworkedActivityForm = (activity: Activity | null) =>
  isProjectAssignmentForm(activity) || isOjtForm(activity) || isCoachingForm(activity) || isMentoringForm(activity) || isGroupActivityForm(activity) || isFeedbackForm(activity) || isFieldTripForm(activity) || isTrainingForm(activity);
const hasActivityForm = (activity: Activity) => Boolean(effectiveFormCode(activity) && formDefinitionFor(activity));
const isFormSaved = (activity: Activity) => Boolean(activity.formDetails?._saved);
const shouldShowDetailField = (field: { showWhen?: { key: string; value: string } }, detail: Record<string, any>) =>
  !field.showWhen || detail[field.showWhen.key] === field.showWhen.value;
const toggleChoice = (row: Record<string, any>, key: string, choice: string) => {
  row[key] = row[key] === choice ? '' : choice;
};
const multiChoices = (row: Record<string, any>, key: string) => {
  if (!Array.isArray(row[key])) row[key] = [];
  return row[key] as string[];
};
const toggleMultiChoice = (row: Record<string, any>, key: string, choice: string, checked: boolean) => {
  const choices = multiChoices(row, key);
  row[key] = checked
    ? Array.from(new Set([...choices, choice]))
    : choices.filter((item) => item !== choice);
};
const fixedTopicLabel = (activity: Activity | null, row: Record<string, any>, rowIndex: number) => {
  const formCode = activity ? effectiveFormCode(activity) : '';
  return row.fixedTopicLabel || '';
};
const hasFixedFormRows = (_activity: Activity | null) => false;
const hasLockedFormRows = (activity: Activity | null) => isTrainingForm(activity);
const hasDetailFields = (activity: Activity | null) =>
  activity ? Boolean(formDefinitionFor(activity)?.detailFields.length) : false;
const shouldPlaceDetailAtBottom = (activity: Activity | null) =>
  activity ? effectiveFormCode(activity) === 'form_9_field_trip' && hasDetailFields(activity) : false;
const isFixedTopicField = (field: RowField, rowIndex: number) =>
  activeFormActivity.value &&
  hasFixedFormRows(activeFormActivity.value) &&
  ['learningTopic', 'skillTopic'].includes(field.key) &&
  rowIndex < 2;
const handleDetailChoiceChange = (fieldKey: string) => {
  if (!activeFormActivity.value) return;
  const detail = activeFormActivity.value.formDetails.detail;
  const selectedValue = detail[fieldKey];

  formDefinitionFor(activeFormActivity.value)?.detailFields
    .filter((field) => field.showWhen?.key === fieldKey && field.showWhen.value !== selectedValue)
    .forEach((field) => {
      detail[field.key] = '';
    });
};
const reviewStepForPlan = (plan: Plan | null) => {
  if (!plan || !isReviewStatus(plan.status || '')) return null;
  return Number(plan.currentReviewStep || plan.status.match(/^review_step_(\d+)$/)?.[1] || 0) || null;
};
const reviewerForPlan = (plan: Plan | null) => {
  const step = reviewStepForPlan(plan);
  return step ? idpApprovalSteps.value.find((reviewer) => Number(reviewer.step) === step) : null;
};
const planStatusLabel = (plan: Plan | null) => {
  if (!plan) return 'ร่าง';
  if (plan.status === 'approved') return 'อนุมัติครบทุกลำดับแล้ว';
  if (plan.status === 'revision_required') {
    return plan.rejectReviewerName ? `ส่งกลับโดย ${plan.rejectReviewerName}` : 'ส่งกลับให้แก้ไข';
  }
  if (isReviewStatus(plan.status || '')) {
    const reviewer = reviewerForPlan(plan);
    return reviewer?.name
      ? `รอ ${reviewer.name} อนุมัติ`
      : `รอผู้อนุมัติลำดับ ${reviewStepForPlan(plan) || '-'}`;
  }
  return 'ร่าง';
};

const closeActivityBuilder = () => {
  activityBuilderOpen.value = false;
  replacementActivityKey.value = null;
};
const openActivityBuilder = (activity?: Activity) => {
  if (activity && isFormSaved(activity) && !window.confirm('การเปลี่ยนเครื่องมือจะล้างรายละเอียดฟอร์มเดิม ต้องการดำเนินการต่อหรือไม่?')) {
    return;
  }
  const availableFocus = (['experiential', 'social'] as BuilderFocus[])
    .find((focus) => activeTools.value.some((tool) => tool.focusType === focus));
  activityBuilderFocus.value = availableFocus || (builderCatalogs.value.length ? 'formal' : 'experiential');
  replacementActivityKey.value = activity?.clientKey || null;
  activityBuilderOpen.value = true;
};
const removeActivity = (plan: Plan, clientKey: string) => {
  plan.activities = plan.activities.filter((activity) => activity.clientKey !== clientKey);
};
const chooseTool = (activity: Activity) => {
  const tool = activeTools.value.find((item) => item.id === Number(activity.developmentToolId));
  activity.activityName = tool?.title || '';
  activity.formCode = formCodeForActivity(activity);
  activity.formDetails = defaultFormDetails(activity.formCode);
};
const chooseCatalog = (activity: Activity) => {
  const catalog = activeCatalogs.value.find((item) => item.id === Number(activity.learningCatalogId));
  activity.activityName = catalog?.name || '';
  activity.activityDescription = catalog?.description || '';
  activity.formCode = formCodeForActivity(activity);
  activity.formDetails = {
    ...defaultFormDetails(activity.formCode),
    planRows: defaultPlanRows(activity.formCode).map((row, index) => index === 0 ? {
      ...row,
      trainingType: catalog?.deliveryType === 'in_class' ? 'In-class Training' : 'e-Learning',
      courseCode: catalog?.code || '',
      courseName: catalog?.name || '',
      courseDescription: catalog?.description || '',
      hours: catalog?.hours ?? '',
      cost: catalog?.cost ?? '',
    } : row),
  };
};
const placeSelectedActivity = (plan: Plan, activity: Activity) => {
  const replacementIndex = replacementActivityKey.value
    ? plan.activities.findIndex((item) => item.clientKey === replacementActivityKey.value)
    : -1;
  if (replacementIndex >= 0) {
    activity.weightPercent = plan.activities[replacementIndex].weightPercent;
    plan.activities.splice(replacementIndex, 1, activity);
  } else {
    plan.activities.push(activity);
  }
  closeActivityBuilder();
};
const addToolActivity = (plan: Plan, tool: DevelopmentTool) => {
  const activity = blankActivity();
  activity.methodKey = methodKeyForFocus(tool.focusType as BuilderFocus);
  activity.developmentToolId = tool.id;
  chooseTool(activity);
  placeSelectedActivity(plan, activity);
};
const addCatalogActivity = (plan: Plan, catalog: Catalog) => {
  const activity = blankActivity();
  activity.methodKey = methodKeyForFocus('formal');
  activity.learningCatalogId = catalog.id;
  chooseCatalog(activity);
  placeSelectedActivity(plan, activity);
};
const defaultPlanRows = (formCode: string) => {
  return [{}];
};
const defaultFormDetails = (formCode: string): FormDetails => ({
  detail: {},
  planRows: defaultPlanRows(formCode),
  developmentResult: {},
  assessment: {},
  acknowledgements: {},
  _saved: false,
  _formCode: formCode,
});
const normalizeFormDetails = (formCode: string, details?: FormDetails): FormDetails => {
  const source = details || {};
  const rows = Array.isArray(source.planRows) && source.planRows.length
    ? source.planRows
    : defaultPlanRows(formCode);
  const rowsWithFormDefaults = rows;
  const normalizedRows = rowsWithFormDefaults.map((row: Record<string, any>) => {
    const cloned = { ...(row || {}) };
    const legacyApproaches = ['A', 'B', 'C', 'D'].filter((code) => cloned[`approach${code}`]);
    if (!Array.isArray(cloned.coachingApproaches) && legacyApproaches.length) {
      cloned.coachingApproaches = legacyApproaches;
    } else if (!Array.isArray(cloned.coachingApproaches) && ['A', 'B', 'C', 'D'].includes(cloned.approach)) {
      cloned.coachingApproaches = [cloned.approach];
    }
    return cloned;
  });

  return {
    ...defaultFormDetails(formCode),
    ...source,
    detail: { ...(source.detail || {}) },
    planRows: normalizedRows,
    developmentResult: { ...(source.developmentResult || {}) },
    assessment: { ...(source.assessment || {}) },
    acknowledgements: { ...(source.acknowledgements || {}) },
  };
};
const openActivityForm = (activity: Activity) => {
  clearAutoSaveTimer();
  if (!selectedPlanLocked.value) {
    if (!activity.formCode) {
      activity.formCode = effectiveFormCode(activity);
    }
    activity.formDetails = normalizeFormDetails(activity.formCode, activity.formDetails);
  }
  showCoachingApproachHelp.value = false;
  activeFormActivityKey.value = activity.clientKey;
};
const closeActivityForm = (resumeAutoSave = true) => {
  showCoachingApproachHelp.value = false;
  activeFormActivityKey.value = null;
  if (resumeAutoSave) {
    void nextTick(() => scheduleAutoSave());
  }
};
const activeFormActivity = computed(() =>
  selectedPlan.value?.activities.find((activity) => activity.clientKey === activeFormActivityKey.value) || null);
const activityUserFormIssue = (activity: Activity | null): string => {
  if (!activity) return 'ไม่พบกิจกรรม';
  const rows = Array.isArray(activity.formDetails?.planRows) ? activity.formDetails.planRows : [];
  if (!rows.length) return 'กรุณาเพิ่มรายละเอียดกิจกรรมอย่างน้อย 1 รายการ';
  const detail = activity.formDetails?.detail || {};
  const missingInRows = (keys: string[]) => rows.some((row: Record<string, any>) =>
    keys.some((key) => !String(row[key] ?? '').trim()));
  const invalidDateOrder = rows.some((row: Record<string, any>) =>
    row.developmentStart && row.developmentEnd && row.developmentEnd < row.developmentStart);

  if (isProjectAssignmentForm(activity)
    && missingInRows(['assignmentTopic', 'developmentGoal', 'developmentApproach', 'developmentStart', 'developmentEnd'])) {
    return 'กรุณากรอกรายละเอียดงาน เป้าหมาย วิธีดำเนินการ และวันที่ให้ครบ';
  }
  if (isOjtForm(activity)) {
    if (!detail.trainerType || (detail.trainerType === 'ผู้เชี่ยวชาญ' && !String(detail.trainerExpertName || '').trim())) {
      return 'กรุณาระบุผู้สอนงานให้ครบ';
    }
    if (missingInRows(['skillTopic', 'developmentGoal', 'developmentApproach', 'developmentStart', 'developmentEnd', 'hours'])) {
      return 'กรุณากรอกหัวข้อ เป้าหมาย วิธีการ ช่วงเวลา และจำนวนชั่วโมงให้ครบ';
    }
    if (rows.some((row: Record<string, any>) => !Number.isFinite(Number(row.hours)) || Number(row.hours) <= 0)) {
      return 'จำนวนชั่วโมงต้องมากกว่า 0';
    }
  }
  if (isCoachingForm(activity)) {
    if (!detail.coachType || (detail.coachType === 'ผู้เชี่ยวชาญ' && !String(detail.coachExpertName || '').trim())) {
      return 'กรุณาระบุผู้สอนงานให้ครบ';
    }
    if (missingInRows(['topic', 'developmentGoal', 'developmentApproach', 'developmentStart', 'developmentEnd', 'sessionCount', 'sessionDuration'])
      || rows.some((row: Record<string, any>) => !Array.isArray(row.coachingApproaches) || !row.coachingApproaches.length)) {
      return 'กรุณากรอกหัวข้อ เป้าหมาย วิธีการ แนวทาง และระยะเวลาให้ครบ';
    }
  }
  if (isMentoringForm(activity)) {
    if (!detail.mentorType || (detail.mentorType === 'ผู้เชี่ยวชาญ' && !String(detail.mentorExpertName || '').trim())) {
      return 'กรุณาระบุผู้สอนงานให้ครบ';
    }
    if (missingInRows(['skillTopic', 'developmentGoal', 'technique', 'developmentStart', 'developmentEnd', 'sessionCount', 'sessionDuration'])) {
      return 'กรุณากรอกหัวข้อ เป้าหมาย เทคนิค และระยะเวลาให้ครบ';
    }
  }
  if (isGroupActivityForm(activity)) {
    if (!detail.facilitatorType || (detail.facilitatorType === 'ผู้เชี่ยวชาญ' && !String(detail.facilitatorExpertName || '').trim())) {
      return 'กรุณาระบุผู้อำนวยการหรือผู้นำกิจกรรมให้ครบ';
    }
    if (missingInRows(['learningTopic', 'developmentGoal', 'technique', 'assessmentTools', 'developmentStart', 'developmentEnd'])) {
      return 'กรุณากรอกหัวข้อ เป้าหมาย เทคนิค การประเมิน และวันที่ให้ครบ';
    }
  }
  if (isFeedbackForm(activity)) {
    if (!detail.feedbackProviderType || (detail.feedbackProviderType === 'ผู้เชี่ยวชาญ' && !String(detail.feedbackExpertName || '').trim())) {
      return 'กรุณาระบุผู้ให้ข้อมูลให้ครบ';
    }
    if (missingInRows(['skillTopic', 'developmentGoal', 'feedbackSource', 'developmentStart', 'developmentEnd', 'sessionCount', 'sessionDuration'])) {
      return 'กรุณากรอกหัวข้อ เป้าหมาย แหล่งข้อมูล และระยะเวลาให้ครบ';
    }
  }
  if (isFieldTripForm(activity)
    && missingInRows(['skillTopic', 'developmentGoal', 'learningPlace', 'assessmentTools', 'developmentStart', 'developmentEnd'])) {
    return 'กรุณากรอกหัวข้อ เป้าหมาย สถานที่ การประเมิน และวันที่ให้ครบ';
  }
  if (isTrainingForm(activity)
    && missingInRows(['developmentGoal', 'developmentStart', 'developmentEnd'])) {
    return 'กรุณากรอกเป้าหมายและวันที่ที่ต้องการเข้าร่วมให้ครบ';
  }
  if ([isCoachingForm(activity), isMentoringForm(activity), isFeedbackForm(activity)].some(Boolean)
    && rows.some((row: Record<string, any>) => !Number.isInteger(Number(row.sessionCount)) || Number(row.sessionCount) < 1)) {
    return 'จำนวนครั้งต้องเป็นจำนวนเต็มตั้งแต่ 1 ขึ้นไป';
  }
  if (invalidDateOrder) return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
  return '';
};
const activeFormUserIssue = computed(() => activityUserFormIssue(activeFormActivity.value));
const saveActivityForm = () => {
  if (selectedPlanLocked.value) return;
  if (activeFormUserIssue.value) return;
  if (activeFormActivity.value) {
    if (isReworkedActivityForm(activeFormActivity.value)) {
      const rows = activeFormActivity.value.formDetails.planRows || [];
      const starts = rows.map((row: Record<string, any>) => row.developmentStart).filter(Boolean).sort();
      const ends = rows.map((row: Record<string, any>) => row.developmentEnd).filter(Boolean).sort();
      activeFormActivity.value.startDate = starts[0] || '';
      activeFormActivity.value.endDate = ends[ends.length - 1] || '';
    }
    activeFormActivity.value.formDetails = {
      ...activeFormActivity.value.formDetails,
      _saved: true,
    };
  }
  submitError.value = '';
  closeActivityForm(false);
  void performAutoSave(true);
};
const addFormRow = (activity: Activity) => {
  if (hasLockedFormRows(activity)) return;
  const rows = Array.isArray(activity.formDetails?.planRows) ? activity.formDetails.planRows : [];
  activity.formDetails.planRows = [...rows, {}];
};
const removeFormRow = (activity: Activity, index: number) => {
  if (hasLockedFormRows(activity)) return;
  const rows = Array.isArray(activity.formDetails?.planRows) ? activity.formDetails.planRows : [];
  activity.formDetails.planRows = rows.filter((_, rowIndex) => rowIndex !== index);
};
const formRows = (activity: Activity) => {
  if (!Array.isArray(activity.formDetails?.planRows) || activity.formDetails.planRows.length === 0) {
    activity.formDetails.planRows = [{}];
  }
  return activity.formDetails.planRows;
};
const enforceDateOrder = (row: Record<string, any>) => {
  if (row.developmentStart && row.developmentEnd && row.developmentEnd < row.developmentStart) {
    row.developmentEnd = '';
  }
};

const requestPayload = () => ({
  items: plans.value.map((plan) => ({
    competencyGapId: plan.competencyGapId,
    goal: plan.goal,
    successCriteria: plan.successCriteria,
    activities: plan.activities.map(({ clientKey, ...activity }) => ({
      ...activity,
      weightPercent: activity.weightPercent === '' ? null : Number(activity.weightPercent),
    })),
  })),
});
const signature = () => JSON.stringify(requestPayload());

watch(() => [props.gaps, props.idp], hydratePlans, { immediate: true });
watch(selectedGapId, () => {
  submitError.value = '';
  closeActivityBuilder();
});

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
const firstValidationMessage = (errorBag: unknown, fallback: string) => {
  if (!errorBag || typeof errorBag !== 'object') return fallback;
  const first = Object.values(errorBag as Record<string, unknown>)[0];
  if (Array.isArray(first)) return String(first[0] || fallback);
  return first ? String(first) : fallback;
};

const clearAutoSaveTimer = () => {
  if (autoSaveTimer === null) return;
  window.clearTimeout(autoSaveTimer);
  autoSaveTimer = null;
};

const scheduleAutoSave = (delay = 1000, force = false) => {
  clearAutoSaveTimer();
  autoSaveTimer = window.setTimeout(() => {
    autoSaveTimer = null;
    void performAutoSave(force);
  }, delay);
};

const performAutoSave = async (force = false): Promise<boolean> => {
  if (!force && (activeFormActivityKey.value !== null
    || (selectedPlan.value && !selectedPlanLocked.value && !planIssue(selectedPlan.value)))) {
    queuedSave.value = false;
    return false;
  }
  if (plans.value.every((plan) => isPlanLocked(plan)) || saveState.value === 'saving') {
    queuedSave.value = saveState.value === 'saving';
    forceQueuedSave.value = forceQueuedSave.value || (saveState.value === 'saving' && force);
    return false;
  }
  const nextSignature = signature();
  if (nextSignature === lastSavedSignature.value) {
    queuedSave.value = false;
    forceQueuedSave.value = false;
    return true;
  }

  let saved = false;
  saveState.value = 'saving';
  try {
    const response = await fetch(route('employee.idp.draft'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(requestPayload()),
    });

    if (!response.ok) {
      const body = await response.json().catch(() => null);
      throw new Error(firstValidationMessage(body?.errors, 'บันทึกข้อมูลแผนไม่สำเร็จ กรุณาตรวจสอบข้อมูลอีกครั้ง'));
    }

    lastSavedSignature.value = nextSignature;
    submitError.value = '';
    saveState.value = 'saved';
    saved = true;
  } catch (error) {
    submitError.value = error instanceof Error
      ? error.message
      : 'บันทึกข้อมูลแผนไม่สำเร็จ กรุณาลองอีกครั้ง';
    saveState.value = 'error';
  } finally {
    const retryWithForce = forceQueuedSave.value;
    const shouldRetry = retryWithForce || (
      !(selectedPlan.value && !selectedPlanLocked.value && !planIssue(selectedPlan.value))
      && (queuedSave.value || signature() !== lastSavedSignature.value)
    );
    queuedSave.value = false;
    forceQueuedSave.value = false;
    if (shouldRetry) {
      scheduleAutoSave(250, retryWithForce);
    }
  }

  return saved;
};

watch(plans, () => {
  if (activeFormActivityKey.value !== null
    || plans.value.every((plan) => isPlanLocked(plan))
    || (selectedPlan.value && !selectedPlanLocked.value && !planIssue(selectedPlan.value))
    || signature() === lastSavedSignature.value) return;
  saveState.value = 'idle';
  scheduleAutoSave();
}, { deep: true });

onBeforeUnmount(clearAutoSaveTimer);

const planIssue = (plan: Plan): string => {
  if (!plan.goal.trim()) return 'ยังไม่ได้ระบุเป้าหมาย';
  if (!plan.successCriteria.trim()) return 'ยังไม่ได้ระบุตัวชี้วัดความสำเร็จ';
  if (plan.activities.length === 0) return 'ยังไม่มีกิจกรรม';
  if (Math.round(weightTotal(plan) * 100) / 100 !== 100) return 'น้ำหนักกิจกรรมต้องรวม 100%';

  for (const [activityIndex, activity] of plan.activities.entries()) {
    if (!activity.methodKey || activity.weightPercent === '' || Number(activity.weightPercent) < 1) {
      return 'ข้อมูลกิจกรรมยังไม่ครบ';
    }
    if (hasActivityForm(activity) && !isFormSaved(activity)) {
      return 'ยังไม่ได้กรอกรายละเอียดฟอร์มกิจกรรม';
    }
    if (isProjectAssignmentForm(activity)) {
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['assignmentTopic', 'developmentGoal', 'developmentApproach', 'developmentStart', 'developmentEnd']
          .every((key) => String(row[key] || '').trim()))) {
        return 'กรุณากรอกแบบฟอร์มการมอบหมายงานโครงการ/งานพิเศษให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
    }
    if (isOjtForm(activity)) {
      const detail = activity.formDetails?.detail || {};
      if (!detail.trainerType
        || (detail.trainerType === 'ผู้เชี่ยวชาญ' && !String(detail.trainerExpertName || '').trim())) {
        return 'กรุณาระบุผู้สอนงานให้ครบ';
      }
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['skillTopic', 'developmentStart', 'developmentEnd', 'hours', 'developmentGoal', 'developmentApproach']
          .every((key) => String(row[key] || '').trim()))) {
        return 'กรุณากรอกแบบฟอร์มการเรียนรู้จากการปฏิบัติงานจริงให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
    }
    if (isCoachingForm(activity)) {
      const detail = activity.formDetails?.detail || {};
      if (!detail.coachType
        || (detail.coachType === 'ผู้เชี่ยวชาญ' && !String(detail.coachExpertName || '').trim())) {
        return 'กรุณาระบุผู้สอนงานให้ครบ';
      }
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['topic', 'developmentStart', 'developmentEnd', 'sessionCount', 'sessionDuration', 'developmentGoal', 'developmentApproach']
          .every((key) => String(row[key] || '').trim())
        || !Array.isArray(row.coachingApproaches)
        || row.coachingApproaches.length === 0)) {
        return 'กรุณากรอกแบบฟอร์มการสอนงานให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
      if (rows.some((row: Record<string, any>) => Number(row.sessionCount) < 1)) {
        return 'จำนวนครั้งต้องไม่น้อยกว่า 1';
      }
    }
    if (isMentoringForm(activity)) {
      const detail = activity.formDetails?.detail || {};
      if (!detail.mentorType
        || (detail.mentorType === 'ผู้เชี่ยวชาญ' && !String(detail.mentorExpertName || '').trim())) {
        return 'กรุณาระบุผู้สอนงานให้ครบ';
      }
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['skillTopic', 'technique', 'developmentStart', 'developmentEnd', 'sessionCount', 'sessionDuration', 'developmentGoal']
          .every((key) => String(row[key] || '').trim()))) {
        return 'กรุณากรอกแบบฟอร์มการเป็นพี่เลี้ยงให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
      if (rows.some((row: Record<string, any>) => Number(row.sessionCount) < 1)) {
        return 'จำนวนครั้งต้องไม่น้อยกว่า 1';
      }
    }
    if (isGroupActivityForm(activity)) {
      const detail = activity.formDetails?.detail || {};
      if (!detail.facilitatorType
        || (detail.facilitatorType === 'ผู้เชี่ยวชาญ' && !String(detail.facilitatorExpertName || '').trim())) {
        return 'กรุณาระบุผู้อำนวยการ/ผู้นำกิจกรรมให้ครบ';
      }
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['learningTopic', 'technique', 'developmentStart', 'developmentEnd', 'assessmentTools', 'developmentGoal']
          .every((key) => String(row[key] || '').trim()))) {
        return 'กรุณากรอกแบบฟอร์มการเรียนรู้แบบกระบวนการกลุ่มให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
    }
    if (isFeedbackForm(activity)) {
      const detail = activity.formDetails?.detail || {};
      if (!detail.feedbackProviderType
        || (detail.feedbackProviderType === 'ผู้เชี่ยวชาญ' && !String(detail.feedbackExpertName || '').trim())) {
        return 'กรุณาระบุผู้ให้ข้อมูลให้ครบ';
      }
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['skillTopic', 'feedbackSource', 'developmentStart', 'developmentEnd', 'sessionCount', 'sessionDuration', 'developmentGoal']
          .every((key) => String(row[key] || '').trim()))) {
        return 'กรุณากรอกแบบฟอร์มการให้ข้อมูลป้อนกลับให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
      if (rows.some((row: Record<string, any>) => Number(row.sessionCount) < 1)) {
        return 'จำนวนครั้งต้องไม่น้อยกว่า 1';
      }
    }
    if (isFieldTripForm(activity)) {
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length || rows.some((row: Record<string, any>) =>
        !['skillTopic', 'learningPlace', 'developmentStart', 'developmentEnd', 'assessmentTools', 'developmentGoal']
          .every((key) => String(row[key] || '').trim()))) {
        return 'กรุณากรอกแบบฟอร์มการเรียนรู้นอกสถานที่ให้ครบ';
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
    }
    if (isTrainingForm(activity)) {
      const rows = activity.formDetails?.planRows || [];
      if (!rows.length) {
        return `กิจกรรมที่ ${activityIndex + 1} · ${activity.activityName || 'Formal Learning'} ยังไม่มีรายละเอียดหลักสูตร`;
      }
      const requiredTrainingFields = [
        ['developmentStart', 'วันที่เริ่มต้น'],
        ['developmentEnd', 'วันที่สิ้นสุด'],
        ['developmentGoal', 'เป้าหมายในการพัฒนา'],
      ];
      const missingFields = requiredTrainingFields
        .filter(([key]) => rows.some((row: Record<string, any>) => !String(row[key] || '').trim()))
        .map(([, label]) => label);
      if (missingFields.length) {
        return `กิจกรรมที่ ${activityIndex + 1} · ${activity.activityName || 'Formal Learning'} ยังขาด: ${missingFields.join(', ')}`;
      }
      if (rows.some((row: Record<string, any>) => row.developmentEnd < row.developmentStart)) {
        return 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
      }
    }
    if (['experiential', 'social'].includes(focusType(activity.methodKey)) && !activity.developmentToolId) {
      return 'ยังไม่ได้เลือกเครื่องมือพัฒนา';
    }
    if (focusType(activity.methodKey) === 'formal' && !activity.learningCatalogId) {
      return 'ยังไม่ได้เลือกหลักสูตร';
    }
  }
  return '';
};
const planNavStatus = (plan: Plan) => {
  if (plan.status === 'approved') return { label: 'อนุมัติแล้ว', tone: 'approved' };
  if (isReviewStatus(plan.status || '')) return { label: 'รออนุมัติ', tone: 'review' };
  if (plan.status === 'revision_required') return { label: 'ส่งกลับ', tone: 'returned' };
  if (planIssue(plan)) return { label: 'ข้อมูลไม่ครบ', tone: 'incomplete' };
  return { label: 'พร้อมส่ง', tone: 'ready' };
};
const planNavDetail = (plan: Plan) => {
  if (plan.status === 'approved' || plan.status === 'revision_required' || isReviewStatus(plan.status || '')) {
    return planStatusLabel(plan);
  }
  return planIssue(plan) || 'ข้อมูลครบแล้ว พร้อมส่งอนุมัติ';
};
const selectedPlanIssue = computed(() => selectedPlan.value && !selectedPlanLocked.value
  ? planIssue(selectedPlan.value)
  : '');
const submitSelectedPlan = async () => {
  if (!selectedPlan.value || selectedPlanLocked.value) return;
  const issue = planIssue(selectedPlan.value);
  if (issue) {
    window.alert(issue);
    return;
  }
  clearAutoSaveTimer();
  submitError.value = '';
  const draftSaved = await performAutoSave(true);
  if (!draftSaved) return;

  saveState.value = 'saving';
  router.post(route('employee.idp.submit-item'), {
    competencyGapId: selectedPlan.value.competencyGapId,
    item: {
      competencyGapId: selectedPlan.value.competencyGapId,
      goal: selectedPlan.value.goal,
      successCriteria: selectedPlan.value.successCriteria,
      activities: selectedPlan.value.activities.map(({ clientKey, ...activity }) => ({
        ...activity,
        weightPercent: activity.weightPercent === '' ? null : Number(activity.weightPercent),
      })),
    },
  }, {
    preserveScroll: true,
    onError: (errorBag) => {
      submitError.value = firstValidationMessage(errorBag, 'ส่งแผนให้หัวหน้าไม่สำเร็จ กรุณาตรวจสอบข้อมูลอีกครั้ง');
      saveState.value = 'error';
    },
    onSuccess: () => { submitError.value = ''; },
    onFinish: () => { saveState.value = 'idle'; },
  });
};
</script>

<template>
  <section class="idp-page">
    <header class="page-header">
      <div>
        <h1>แผนพัฒนา IDP</h1>
        <p class="subtitle">หนึ่งสมรรถนะต่อหนึ่งแผน และเพิ่มกิจกรรมพัฒนาได้หลายรายการ</p>
      </div>
    </header>

    <section v-if="!hasIdpReviewerSteps" class="idp-readiness-panel">
      <header>
        <span class="idp-readiness-symbol" aria-hidden="true">!</span>
        <div>
          <h2>ยังไม่สามารถจัดทำแผนพัฒนา IDP ได้</h2>
          <p>ระบบยังไม่ได้กำหนดลำดับผู้อนุมัติ IDP สำหรับคุณ</p>
        </div>
        <div class="idp-readiness-count"><strong>0/1</strong><span>รายการพร้อม</span></div>
      </header>
      <div class="idp-readiness-row">
        <span class="idp-readiness-state" aria-hidden="true">!</span>
        <div>
          <strong>ลำดับผู้อนุมัติแผน IDP</strong>
          <small>ยังไม่ได้กำหนดลำดับ IDP</small>
        </div>
        <span class="idp-readiness-owner">รอ Admin</span>
      </div>
      <footer>
        <strong>ต้องดำเนินการอย่างไร?</strong>
        <span>ติดต่อผู้ดูแลระบบ (Admin) พร้อมแจ้งชื่อและตำแหน่ง เมื่อกำหนดลำดับแล้วหน้านี้จะเปิดให้ใช้งานอัตโนมัติ</span>
      </footer>
    </section>

    <div v-else-if="idpGaps.length === 0" class="empty-state">
      <strong>ยังไม่มีสมรรถนะที่ต้องจัดทำ IDP</strong>
      <span>ระบบจะแสดงรายการเมื่อผลประเมินได้รับอนุมัติและมี Gap ติดลบ</span>
    </div>

    <div v-else class="workspace">
      <aside class="plan-nav">
        <div class="plan-nav-meta">
          <div class="person">
            <span>{{ props.user?.sso || '-' }}</span>
            <strong>{{ props.user?.t }}{{ props.user?.n || '-' }}</strong>
            <small>{{ props.user?.p || props.user?.d || '-' }}</small>
          </div>
          <div class="nav-heading">
            <strong>สมรรถนะที่ต้องพัฒนา</strong>
            <span>{{ idpGaps.length }}</span>
          </div>
        </div>
        <div class="plan-nav-scroll">
          <button
            v-for="plan in plans"
            :key="plan.competencyGapId"
            class="plan-nav-item"
            :class="{ active: selectedGapId === plan.competencyGapId }"
            :aria-current="selectedGapId === plan.competencyGapId ? 'true' : undefined"
            type="button"
            @click="selectedGapId = plan.competencyGapId"
          >
            <span class="competency-code">{{ gapForPlan(plan)?.cd }}</span>
            <span class="competency-copy">
              <strong>{{ gapForPlan(plan)?.n }}</strong>
              <small>{{ planNavDetail(plan) }}</small>
            </span>
            <span class="plan-status-badge" :class="`is-${planNavStatus(plan).tone}`">
              {{ planNavStatus(plan).label }}
            </span>
          </button>
        </div>
      </aside>

      <main class="plan-content">
        <div v-if="!selectedPlan || !selectedGap" class="select-prompt">
          <strong>เลือกสมรรถนะเพื่อจัดการแผน</strong>
          <span>แต่ละสมรรถนะจะมีเป้าหมายและรายการกิจกรรมของตัวเอง</span>
        </div>

        <template v-else>
          <section class="competency-header">
            <div>
              <span class="type-tag">{{ selectedGap.cd }}</span>
              <h2>{{ selectedGap.n }}</h2>
            </div>
            <div class="score-row">
              <div><span>Expected</span><strong>{{ formatNumber(selectedGap.expected) }}</strong></div>
              <div><span>Actual</span><strong>{{ formatNumber(selectedGap.actual) }}</strong></div>
              <div class="negative"><span>Gap</span><strong>{{ formatNumber(selectedGap.gap) }}</strong></div>
            </div>
          </section>

          <section class="plan-review-route" aria-label="ลำดับอนุมัติ IDP">
            <header>
              <div><h3>ลำดับอนุมัติ IDP</h3><p>สถานะของ {{ selectedGap.cd }} · แต่ละสมรรถนะส่งอนุมัติแยกกัน</p></div>
            </header>
            <ol>
              <li v-for="step in idpApprovalSteps" :key="`${step.step}-${step.id}`"
                :class="{ current: isReviewStatus(selectedPlan.status) && Number(selectedPlan.currentReviewStep || selectedPlan.status.split('_').pop()) === Number(step.step) }">
                <b>{{ step.step }}</b>
                <div>
                  <strong>{{ step.name }}</strong>
                  <small>{{ step.position || step.label }}</small>
                  <em v-if="isReviewStatus(selectedPlan.status) && Number(selectedPlan.currentReviewStep || selectedPlan.status.split('_').pop()) === Number(step.step)">กำลังรออนุมัติ</em>
                </div>
              </li>
            </ol>
            <footer><strong>หมายเหตุ</strong><span>หากลำดับผู้อนุมัติไม่ถูกต้อง กรุณาติดต่อ Admin</span></footer>
          </section>

          <div v-if="selectedPlan.status === 'revision_required' && selectedPlan.rejectComment" class="revision-alert">
            <strong>{{ selectedPlan.rejectReviewerName ? `ถูกส่งกลับโดย ${selectedPlan.rejectReviewerName}` : 'ถูกส่งกลับให้แก้ไข' }}</strong>
            <span>{{ selectedPlan.rejectComment }}</span>
          </div>

          <section class="section-card behavior-section">
            <header class="section-title">
              <div>
                <span class="section-number">01</span>
                <div><h3>พฤติกรรมที่ต้องพัฒนา</h3><p>ข้อมูลจากผลประเมิน แก้ไขไม่ได้</p></div>
              </div>
              <b class="section-badge">ต้องพัฒนา · {{ missingIndicators(selectedGap).length }} ข้อ</b>
            </header>
            <div class="section-body indicator-list">
              <div v-for="indicator in missingIndicators(selectedGap)" :key="`${indicator.level}-${indicator.code}`" class="indicator-row">
                <span>ระดับ {{ indicator.level }} · ข้อ {{ indicator.code }}</span>
                <p>{{ indicator.description }}</p>
              </div>
              <div v-if="missingIndicators(selectedGap).length === 0" class="indicator-row">
                <span>Competency Gap</span>
                <p>พัฒนา {{ selectedGap.n }} ให้ถึงระดับที่คาดหวัง</p>
              </div>
            </div>
          </section>

          <section class="section-card goal-section">
            <header class="section-title">
              <div>
                <span class="section-number">02</span>
                <div><h3>เป้าหมายของแผน</h3><p>ใช้ร่วมกันทุกกิจกรรมในสมรรถนะนี้</p></div>
              </div>
              <b class="section-badge">ข้อมูลแผน</b>
            </header>
            <div class="section-body goal-grid">
              <label>
                <span>เป้าหมายในการพัฒนา</span>
                <textarea v-model="selectedPlan.goal" :disabled="selectedPlanLocked" rows="3" placeholder="ผลลัพธ์ด้านพฤติกรรมที่ต้องการเห็นหลังจบแผน" />
              </label>
              <label>
                <span>ตัวชี้วัดความสำเร็จ</span>
                <textarea v-model="selectedPlan.successCriteria" :disabled="selectedPlanLocked" rows="3" placeholder="ระบุหลักฐานหรือผลลัพธ์ที่ใช้ยืนยันความสำเร็จ" />
              </label>
            </div>
          </section>

          <section class="section-card activities-section">
            <header class="section-title">
              <div>
                <span class="section-number">03</span>
                <div><h3>กิจกรรมการพัฒนา</h3><p>เพิ่มได้หลายกิจกรรม น้ำหนักรวมต้องครบ 100%</p></div>
              </div>
              <div class="weight-total" :class="{ complete: weightTotal(selectedPlan) === 100 }">
                น้ำหนักรวม {{ formatNumber(weightTotal(selectedPlan)) }}%
              </div>
            </header>

            <div class="section-body">
              <div v-if="selectedPlan.activities.length === 0" class="activities-empty">
                <strong>ยังไม่มีกิจกรรมในแผนนี้</strong>
                <span>เริ่มจากเลือก Experiential, Social หรือ Formal Learning</span>
              </div>

              <article v-for="(activity, index) in selectedPlan.activities" :key="activity.clientKey" class="activity">
                <header>
                  <div class="activity-identity">
                    <span class="activity-number">{{ index + 1 }}</span>
                    <div>
                      <span>{{ methodLabel(activity.methodKey) || 'ยังไม่เลือกรูปแบบ' }}</span>
                      <strong>{{ activity.activityName || 'กิจกรรมใหม่' }}</strong>
                    </div>
                  </div>
                  <div v-if="!selectedPlanLocked" class="activity-header-actions">
                    <button class="change-tool" type="button" @click="openActivityBuilder(activity)">เปลี่ยนเครื่องมือ</button>
                    <button class="remove-activity" type="button" title="ลบกิจกรรม" aria-label="ลบกิจกรรม" @click="removeActivity(selectedPlan, activity.clientKey)">×</button>
                  </div>
                </header>
                <div class="activity-form">
                <label>
                  <span>สัดส่วนกิจกรรม (%)</span>
                  <input
                    :value="activity.weightPercent"
                    :disabled="selectedPlanLocked"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="3"
                    placeholder="เช่น 30"
                    @input="updateActivityWeight(activity, $event)"
                  />
                </label>
                <div class="activity-readonly-meta">
                  <strong>{{ isFormSaved(activity) ? 'บันทึกรายละเอียดกิจกรรมแล้ว' : 'กรุณากรอกรายละเอียดกิจกรรม' }}</strong>
                  <small>{{ isFormSaved(activity) ? 'ตรวจสอบรายละเอียดในแบบฟอร์มด้านล่าง' : 'กดปุ่มด้านล่างเพื่อกรอกแบบฟอร์มกิจกรรมให้ครบถ้วน' }}</small>
                </div>
                <div class="activity-detail-action wide">
                  <div>
                    <strong>{{ formDefinitionFor(activity)?.title || 'ยังไม่มีแบบฟอร์มรายละเอียดสำหรับหัวข้อนี้' }}</strong>
                    <span :class="{ saved: isFormSaved(activity) }">
                      {{ !hasActivityForm(activity) ? 'หัวข้อนี้ยังไม่เปิดให้กรอกรายละเอียดฟอร์ม' : isFormSaved(activity) ? 'กรอกรายละเอียดฟอร์มแล้ว' : 'ยังไม่ได้กรอกรายละเอียดฟอร์ม' }}
                    </span>
                  </div>
                  <button
                    type="button"
                    :disabled="!hasActivityForm(activity)"
                    @click="openActivityForm(activity)"
                  >
                    {{ selectedPlanLocked ? 'ดูรายละเอียดฟอร์ม' : 'กรอกรายละเอียดฟอร์ม' }}
                  </button>
                </div>
                </div>
              </article>

              <div v-if="activityBuilderOpen && !selectedPlanLocked" class="activity-builder">
                <header class="activity-builder-head">
                  <div>
                    <strong>เลือกเครื่องมือสำหรับกิจกรรม</strong>
                    <span>รายการกำหนดโดยผู้ดูแลระบบ และจะเปิดแบบฟอร์มให้ตรงกับเครื่องมือที่เลือก</span>
                  </div>
                  <button type="button" aria-label="ปิดตัวเลือกกิจกรรม" @click="closeActivityBuilder">×</button>
                </header>

                <nav class="activity-builder-tabs" aria-label="ประเภทแนวทางการพัฒนา">
                  <button
                    v-for="focus in ([
                      { key: 'experiential', label: 'Experiential', description: 'เรียนรู้จากการลงมือทำ', count: activeTools.filter((tool) => tool.focusType === 'experiential').length },
                      { key: 'social', label: 'Social', description: 'เรียนรู้จากบุคคลและทีม', count: activeTools.filter((tool) => tool.focusType === 'social').length },
                      { key: 'formal', label: 'Formal', description: 'หลักสูตรที่ตรงกับสมรรถนะ', count: builderCatalogs.length },
                    ] as const)"
                    :key="focus.key"
                    type="button"
                    :class="{ active: activityBuilderFocus === focus.key }"
                    @click="activityBuilderFocus = focus.key"
                  >
                    <span>{{ focus.label }}</span>
                    <small>{{ focus.description }}</small>
                    <b>{{ focus.count }}</b>
                  </button>
                </nav>

                <div v-if="activityBuilderFocus !== 'formal'" class="activity-builder-list">
                  <button
                    v-for="(tool, toolIndex) in builderTools"
                    :key="tool.id"
                    class="activity-choice"
                    type="button"
                    @click="addToolActivity(selectedPlan, tool)"
                  >
                    <span class="activity-choice-code">{{ toolIndex + 1 }}</span>
                    <span class="activity-choice-copy">
                      <strong>{{ tool.title }}</strong>
                      <small>{{ tool.formCode ? 'มีแบบฟอร์มประกอบตามเครื่องมือนี้' : 'กิจกรรมตามแนวทางที่ผู้ดูแลระบบกำหนด' }}</small>
                    </span>
                    <span class="activity-choice-add">เลือก</span>
                  </button>
                  <div v-if="builderTools.length === 0" class="activity-builder-empty">
                    ผู้ดูแลระบบยังไม่ได้เปิดใช้เครื่องมือในหมวดนี้
                  </div>
                </div>

                <div v-else class="activity-builder-list">
                  <button
                    v-for="catalog in builderCatalogs"
                    :key="catalog.id"
                    class="activity-choice"
                    :class="{ unavailable: !catalogMatchesExpectedLevel(catalog, selectedGap) }"
                    :disabled="!catalogMatchesExpectedLevel(catalog, selectedGap)"
                    type="button"
                    @click="addCatalogActivity(selectedPlan, catalog)"
                  >
                    <span class="activity-choice-code formal">{{ catalog.code || 'COURSE' }}</span>
                    <span class="activity-choice-copy">
                      <strong>{{ catalog.name }}</strong>
                      <small v-if="catalogMatchesExpectedLevel(catalog, selectedGap)">{{ catalog.deliveryType === 'in_class' ? 'In-class Training' : 'e-Learning' }}<template v-if="catalog.hours"> · {{ catalog.hours }} ชั่วโมง</template></small>
                      <small v-else>ใช้ไม่ได้ · รองรับระดับความคาดหวัง {{ catalog.expectedLevels?.join(', ') || '-' }}</small>
                    </span>
                    <span class="activity-choice-add">เลือก</span>
                  </button>
                  <div v-if="builderCatalogs.length === 0" class="activity-builder-empty formal-empty">
                    <strong>ยังไม่มีหลักสูตรสำหรับสมรรถนะนี้</strong>
                    <span>ระบบแสดงเฉพาะหลักสูตรที่ผู้ดูแลระบบผูกกับ {{ selectedGap?.cd }}</span>
                  </div>
                </div>
              </div>

              <button v-if="!selectedPlanLocked && !activityBuilderOpen" class="add-activity" type="button" @click="openActivityBuilder()">
                <span>+</span> เลือกเครื่องมือ / หลักสูตร
              </button>
            </div>
          </section>

          <div v-if="Object.keys(errors).length" class="error-box">
            กรุณาตรวจสอบข้อมูลที่กรอก ระบบยังไม่สามารถบันทึกหรือส่งแผนได้
          </div>
        </template>
      </main>
    </div>

    <div v-if="activeFormActivity && formDefinitionFor(activeFormActivity) && selectedGap && selectedPlan" class="form-modal-backdrop">
      <div class="form-modal">
        <header class="form-modal-top">
          <div>
            <strong>{{ selectedPlanLocked ? 'ดูรายละเอียดฟอร์มกิจกรรม (อ่านอย่างเดียว)' : 'กรอกรายละเอียดฟอร์มกิจกรรม' }}</strong>
          </div>
          <button type="button" @click="closeActivityForm()">×</button>
        </header>

        <fieldset class="form-paper" :disabled="selectedPlanLocked" aria-label="รายละเอียดฟอร์มกิจกรรม">
          <section class="form-title-band">
            <h3>{{ formDefinitionFor(activeFormActivity)?.title }}</h3>
          </section>

          <section class="form-block readonly-block">
            <header>
              <h4>ข้อมูลผู้รับการพัฒนา</h4>
              <span>ดึงจากระบบ</span>
            </header>
            <div class="form-grid three">
              <label>
                <span>ชื่อผู้รับการพัฒนา</span>
                <input :value="`${props.user?.t || ''}${props.user?.n || '-'}`" disabled />
              </label>
              <label>
                <span>รหัสพนักงาน</span>
                <input :value="props.user?.sso || '-'" disabled />
              </label>
              <label>
                <span>ตำแหน่ง</span>
                <input :value="props.user?.p || '-'" disabled />
              </label>
            </div>
          </section>

          <section class="form-block readonly-block">
            <header>
              <h4>ข้อมูลประกอบการวางแผน</h4>
              <span>จากสมรรถนะที่ต้องพัฒนา</span>
            </header>
            <div class="form-grid three">
              <label>
                <span>รหัส</span>
                <input :value="selectedGap.cd" disabled />
              </label>
              <label>
                <span>ชื่อสมรรถนะ</span>
                <input :value="selectedGap.n" disabled />
              </label>
              <label>
                <span>Gap</span>
                <input :value="formatNumber(selectedGap.gap)" disabled />
              </label>
            </div>
          </section>

          <section v-if="hasDetailFields(activeFormActivity) && !shouldPlaceDetailAtBottom(activeFormActivity)" class="form-block">
            <header>
              <h4>{{ formDefinitionFor(activeFormActivity)?.detailTitle }}</h4>
              <span>พนักงานกรอกตอนทำแผน</span>
            </header>
            <div class="form-grid">
              <label
                v-for="field in formDefinitionFor(activeFormActivity)?.detailFields"
                :key="field.key"
                v-show="shouldShowDetailField(field, activeFormActivity.formDetails.detail)"
                :class="{ wide: field.type === 'area' }"
              >
                <span>{{ field.label }}</span>
                <textarea
                  v-if="field.type === 'area'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  rows="3"
                />
                <input
                  v-else-if="field.type === 'date'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  type="date"
                />
                <select
                  v-else-if="field.type === 'choice'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  @change="handleDetailChoiceChange(field.key)"
                >
                  <option value="">เลือกจากรายการ</option>
                  <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
                </select>
                <select
                  v-else-if="field.type === 'supervisor-chain'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  :disabled="supervisorChainOptions.length === 0"
                >
                  <option value="">
                    {{ supervisorChainOptions.length ? 'เลือกผู้บังคับบัญชา' : 'ยังไม่ได้กำหนดผู้บังคับบัญชา' }}
                  </option>
                  <option v-for="person in supervisorChainOptions" :key="person.id" :value="person.id">
                    {{ person.label }} · {{ person.name }}{{ person.position ? ` (${person.position})` : '' }}
                  </option>
                </select>
                <input
                  v-else-if="field.type === 'expert-name'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  placeholder="กรอกชื่อผู้เชี่ยวชาญ"
                />
                <input v-else v-model="activeFormActivity.formDetails.detail[field.key]" />
              </label>
            </div>
          </section>

          <section v-if="formDefinitionFor(activeFormActivity)?.rowFields.length" class="form-block">
            <header>
              <h4>{{ formDefinitionFor(activeFormActivity)?.rowTitle }}</h4>
              <button v-if="!hasLockedFormRows(activeFormActivity)" type="button" @click="addFormRow(activeFormActivity)">
                {{ isProjectAssignmentForm(activeFormActivity) ? '+ เพิ่มงานที่ได้รับมอบหมาย' : isOjtForm(activeFormActivity) ? '+ เพิ่มหัวข้อฝึกปฏิบัติ' : isCoachingForm(activeFormActivity) ? '+ เพิ่มหัวข้อการสอนงาน' : isMentoringForm(activeFormActivity) ? '+ เพิ่มหัวข้อที่ต้องการพัฒนา' : isGroupActivityForm(activeFormActivity) ? '+ เพิ่มกิจกรรม' : isFeedbackForm(activeFormActivity) ? '+ เพิ่มหัวข้อการพัฒนา' : isFieldTripForm(activeFormActivity) ? '+ เพิ่มรายการศึกษาดูงาน' : isTrainingForm(activeFormActivity) ? '+ เพิ่มหลักสูตรอบรม' : '+ เพิ่มแถว' }}
              </button>
            </header>
            <div v-if="isProjectAssignmentForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>งานที่ได้รับมอบหมาย {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้องานโครงการ/งานพิเศษที่ได้รับมอบหมาย</span><input v-model="row.assignmentTopic" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><input v-model="row.developmentGoal" /></label>
                  <label class="wide"><span>รายละเอียด</span><textarea v-model="row.developmentApproach" rows="2" /></label>
                  <div class="coaching-timeline group-activity-timeline wide">
                    <div class="coaching-timeline-heading">
                      <strong>ระยะเวลาดำเนินการ</strong>
                      <span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isOjtForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>หัวข้อฝึกปฏิบัติ {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้อทักษะ/ประเด็นการฝึกปฏิบัติงาน</span><textarea v-model="row.skillTopic" rows="3" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label class="wide"><span>วิธีการ</span><textarea v-model="row.developmentApproach" rows="2" /></label>
                  <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline wide">
                    <div class="coaching-timeline-heading">
                      <strong>ระยะเวลาการฝึกปฏิบัติงาน</strong>
                      <span>กำหนดช่วงเวลาและจำนวนชั่วโมงรวม</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                    <label><span>จำนวนชั่วโมง</span><input v-model="row.hours" min="0.01" step="0.5" type="number" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isCoachingForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>หัวข้อการสอนงาน {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้อทักษะ/ประเด็นการสอนงาน</span><textarea v-model="row.topic" rows="3" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label class="wide"><span>วิธีการ</span><textarea v-model="row.developmentApproach" rows="2" /></label>
                  <div class="coaching-approach-field wide">
                    <div class="coaching-approach-label">
                      <div>
                        <span>แนวทางการสอนงาน</span>
                        <small>เลือกได้มากกว่า 1 แนวทาง</small>
                      </div>
                      <button type="button" @click="showCoachingApproachHelp = true">อ่านเพิ่มเติม</button>
                    </div>
                    <div class="coaching-approach-options">
                      <button
                        v-for="approach in fullCoachingApproachDescriptions"
                        :key="approach.code"
                        type="button"
                        :class="{ selected: multiChoices(row, 'coachingApproaches').includes(approach.code) }"
                        @click="toggleMultiChoice(row, 'coachingApproaches', approach.code, !multiChoices(row, 'coachingApproaches').includes(approach.code))"
                      >
                        <strong>{{ approach.code }}</strong>
                        <span>{{ approach.title }}</span>
                        <i aria-hidden="true">✓</i>
                      </button>
                    </div>
                  </div>
                  <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline coaching-timeline-four wide">
                    <div class="coaching-timeline-heading">
                      <strong>ระยะเวลาการพัฒนา</strong>
                      <span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                    <label><span>จำนวนครั้ง</span><input v-model="row.sessionCount" min="1" step="1" type="number" /></label>
                    <label><span>ระยะเวลาต่อครั้ง</span><input v-model="row.sessionDuration" placeholder="เช่น ครั้งละ 1 ชั่วโมง" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isMentoringForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>หัวข้อที่ต้องการพัฒนา {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea v-model="row.skillTopic" rows="3" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label class="wide"><span>เทคนิค</span><textarea v-model="row.technique" rows="2" /></label>
                  <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline coaching-timeline-four wide">
                    <div class="coaching-timeline-heading">
                      <strong>ระยะเวลาการพัฒนา</strong>
                      <span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                    <label><span>จำนวนครั้ง</span><input v-model="row.sessionCount" min="1" step="1" type="number" /></label>
                    <label><span>ระยะเวลาต่อครั้ง</span><input v-model="row.sessionDuration" placeholder="เช่น ครั้งละ 1 ชั่วโมง" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isGroupActivityForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>กิจกรรม {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea v-model="row.learningTopic" rows="3" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label><span>เทคนิค</span><textarea v-model="row.technique" rows="3" /></label>
                  <label><span>เครื่องมือและเงื่อนไขการประเมิน</span><textarea v-model="row.assessmentTools" rows="3" /></label>
                  <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline group-activity-timeline wide">
                    <div class="coaching-timeline-heading">
                      <strong>กำหนดการจัดกิจกรรม</strong>
                      <span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isFeedbackForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>หัวข้อการพัฒนา {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea v-model="row.skillTopic" rows="3" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label class="wide"><span>แหล่งข้อมูลป้อนกลับ</span><textarea v-model="row.feedbackSource" rows="2" /></label>
                  <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline coaching-timeline-four wide">
                    <div class="coaching-timeline-heading">
                      <strong>ระยะการพัฒนา</strong>
                      <span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                    <label><span>จำนวนครั้ง</span><input v-model="row.sessionCount" min="1" step="1" type="number" /></label>
                    <label><span>ระยะเวลาต่อครั้ง</span><input v-model="row.sessionDuration" placeholder="เช่น ครั้งละ 1 ชั่วโมง" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isFieldTripForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>รายการศึกษาดูงาน {{ rowIndex + 1 }}</strong>
                  <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                </div>
                <div class="project-assignment-grid">
                  <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea v-model="row.skillTopic" rows="3" /></label>
                  <label><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label><span>สถานที่/แหล่งศึกษาดูงาน</span><textarea v-model="row.learningPlace" rows="3" /></label>
                  <label><span>เครื่องมือและเงื่อนไขการประเมิน</span><textarea v-model="row.assessmentTools" rows="3" /></label>
                  <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline group-activity-timeline wide">
                    <div class="coaching-timeline-heading">
                      <strong>กำหนดการจัดกิจกรรม</strong>
                      <span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else-if="isTrainingForm(activeFormActivity)" class="project-assignment-list">
              <article v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex" class="project-assignment-item">
                <div class="project-assignment-item-head">
                  <strong>ข้อมูลหลักสูตรที่เลือก</strong>
                  <span class="catalog-snapshot-note">ดึงจาก Learning Catalog</span>
                </div>
                <div class="project-assignment-grid training-form-grid">
                  <section class="training-catalog-summary training-wide">
                    <div class="training-course-heading">
                      <div>
                        <span>{{ row.courseCode || 'ไม่มีรหัสหลักสูตร' }}</span>
                        <strong>{{ row.courseName || 'ยังไม่ได้เลือกหลักสูตร' }}</strong>
                      </div>
                      <em>{{ row.trainingType || '-' }}</em>
                    </div>
                    <p>{{ row.courseDescription || 'ไม่มีคำอธิบายหลักสูตร' }}</p>
                    <dl>
                      <div><dt>รูปแบบการอบรม</dt><dd>{{ row.trainingType || '-' }}</dd></div>
                      <div><dt>จำนวนชั่วโมง</dt><dd>{{ row.hours !== '' && row.hours != null ? `${formatNumber(row.hours)} ชั่วโมง` : 'ไม่ระบุ' }}</dd></div>
                      <div><dt>ค่าใช้จ่าย</dt><dd>{{ formatCurrency(row.cost) }}</dd></div>
                    </dl>
                  </section>
                  <label class="training-wide"><span>เป้าหมายในการพัฒนา</span><textarea v-model="row.developmentGoal" rows="3" /></label>
                  <label class="training-wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea v-model="row.additionalDetails" rows="2" /></label>
                  <div class="coaching-timeline training-summary training-wide">
                    <div class="coaching-timeline-heading">
                      <strong>กำหนดการอบรม</strong>
                      <span>ระบุวันที่ที่ต้องการเข้าร่วมหลักสูตร</span>
                    </div>
                    <label><span>วันที่เริ่มต้น</span><input v-model="row.developmentStart" type="date" @change="enforceDateOrder(row)" /></label>
                    <label><span>วันที่สิ้นสุด</span><input v-model="row.developmentEnd" type="date" :min="row.developmentStart || undefined" /></label>
                  </div>
                </div>
              </article>
            </div>
            <div v-else class="form-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th
                      v-if="!hasFixedFormRows(activeFormActivity)"
                      class="row-number"
                      :rowspan="hasGroupedRowFields(formDefinitionFor(activeFormActivity)?.rowFields || []) ? 2 : 1"
                    >ที่</th>
                    <th
                      v-for="cell in rowHeaderCells(formDefinitionFor(activeFormActivity)?.rowFields || [])"
                      :key="cell.key"
                      :colspan="cell.colspan"
                      :rowspan="cell.rowspan"
                    >
                      {{ cell.label }}
                      <small v-if="cell.owner">{{ cell.owner }}</small>
                    </th>
                    <th
                      v-if="!hasLockedFormRows(activeFormActivity)"
                      class="row-control"
                      :rowspan="hasGroupedRowFields(formDefinitionFor(activeFormActivity)?.rowFields || []) ? 2 : 1"
                    ></th>
                  </tr>
                  <tr v-if="hasGroupedRowFields(formDefinitionFor(activeFormActivity)?.rowFields || [])">
                    <th
                      v-for="(field, fieldIndex) in rowSubHeaderFields(formDefinitionFor(activeFormActivity)?.rowFields || [])"
                      :key="`sub-${field.key}-${field.label}-${fieldIndex}`"
                    >
                      {{ field.label }}
                      <small v-if="field.owner">{{ field.owner }}</small>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, rowIndex) in formRows(activeFormActivity)" :key="rowIndex">
                    <td v-if="!hasFixedFormRows(activeFormActivity)" class="row-number">{{ rowIndex + 1 }}</td>
                    <td
                      v-for="(field, fieldIndex) in formDefinitionFor(activeFormActivity)?.rowFields"
                      :key="`${field.key}-${field.label}-${fieldIndex}`"
                    >
                      <select v-if="field.type === 'choice'" v-model="row[field.key]">
                        <option value="">เลือกจากรายการ</option>
                        <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
                      </select>
                      <div v-else-if="field.type === 'checkbox-choice'" class="checkbox-choice-group">
                        <label v-for="choice in field.choices" :key="choice">
                          <input
                            type="checkbox"
                            :checked="row[field.key] === choice"
                            @change="toggleChoice(row, field.key, choice)"
                          />
                          <span>{{ choice }}</span>
                        </label>
                      </div>
                      <button
                        v-else-if="field.type === 'multi-checkbox'"
                        type="button"
                        class="multi-check-button"
                        :class="{ checked: multiChoices(row, field.key).includes(field.value || field.label) }"
                        :aria-pressed="multiChoices(row, field.key).includes(field.value || field.label)"
                        @click.stop="toggleMultiChoice(row, field.key, field.value || field.label, !multiChoices(row, field.key).includes(field.value || field.label))"
                      >
                        <span>✓</span>
                      </button>
                      <div v-else-if="isFixedTopicField(field, rowIndex)" class="fixed-topic-cell">
                        <strong>{{ fixedTopicLabel(activeFormActivity, row, rowIndex) }}</strong>
                        <label v-if="rowIndex === 0">
                          <span>ระบุ</span>
                          <textarea v-model="row[field.key]" class="table-textarea" rows="2" />
                        </label>
                      </div>
                      <textarea
                        v-else-if="field.type === 'area'"
                        v-model="row[field.key]"
                        class="table-textarea"
                        rows="3"
                      />
                      <input v-else v-model="row[field.key]" :placeholder="field.placeholder || ''" />
                    </td>
                    <td v-if="!hasLockedFormRows(activeFormActivity)" class="row-control">
                      <button type="button" :disabled="formRows(activeFormActivity).length === 1" @click="removeFormRow(activeFormActivity, rowIndex)">ลบ</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section v-if="!isReworkedActivityForm(activeFormActivity)" class="form-block result-block">
            <header>
              <h4>ผลการดำเนินการ</h4>
              <span>ใช้หลังแผนอนุมัติแล้ว</span>
            </header>
            <div class="form-grid three">
              <label>
                <span>เป้าหมายในการพัฒนา (Behavior Result)</span>
                <textarea :value="selectedPlan.goal" disabled rows="3" />
              </label>
              <label>
                <span>ตัวชี้วัดผลสำเร็จของการพัฒนา</span>
                <textarea :value="selectedPlan.successCriteria" disabled rows="3" />
              </label>
              <label>
                <span>ผลลัพธ์จากการพัฒนา</span>
                <textarea v-model="activeFormActivity.formDetails.developmentResult.outcome" rows="3" />
              </label>
            </div>
          </section>

          <section v-if="!isReworkedActivityForm(activeFormActivity)" class="form-block assessment-block">
            <header>
              <h4>การติดตามประเมินผล / คำรับรอง</h4>
              <span>หัวหน้าใช้ตอนติดตามผล</span>
            </header>
            <div class="form-grid">
              <label>
                <span>ผลดำเนินการ</span>
                <select v-model="activeFormActivity.formDetails.assessment.operationStatus">
                  <option value="">เลือกจากรายการ</option>
                  <option value="ตามแผน">เป็นไปตามแผน</option>
                  <option value="ไม่ตามแผน">ไม่เป็นไปตามแผน</option>
                </select>
              </label>
              <label>
                <span>ผลการพัฒนา</span>
                <select v-model="activeFormActivity.formDetails.assessment.developmentStatus">
                  <option value="">เลือกจากรายการ</option>
                  <option value="บรรลุ">บรรลุเป้าหมายที่กำหนด</option>
                  <option value="ไม่บรรลุ">ไม่บรรลุผล ควรพัฒนาต่อ</option>
                </select>
              </label>
              <label class="wide">
                <span>เหตุผล / หมายเหตุ</span>
                <textarea v-model="activeFormActivity.formDetails.assessment.comment" rows="2" />
              </label>
            </div>
          </section>

          <section v-if="shouldPlaceDetailAtBottom(activeFormActivity)" class="form-block">
            <header>
              <h4>{{ formDefinitionFor(activeFormActivity)?.detailTitle }}</h4>
              <span>กรอกตอนท้าย</span>
            </header>
            <div class="form-grid">
              <label
                v-for="field in formDefinitionFor(activeFormActivity)?.detailFields"
                :key="field.key"
                v-show="shouldShowDetailField(field, activeFormActivity.formDetails.detail)"
                :class="{ wide: field.type === 'area' }"
              >
                <span>{{ field.label }}</span>
                <textarea
                  v-if="field.type === 'area'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  rows="3"
                />
                <input
                  v-else-if="field.type === 'date'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  type="date"
                />
                <select
                  v-else-if="field.type === 'choice'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  @change="handleDetailChoiceChange(field.key)"
                >
                  <option value="">เลือกจากรายการ</option>
                  <option v-for="choice in field.choices" :key="choice" :value="choice">{{ choice }}</option>
                </select>
                <select
                  v-else-if="field.type === 'supervisor-chain'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  :disabled="supervisorChainOptions.length === 0"
                >
                  <option value="">
                    {{ supervisorChainOptions.length ? 'เลือกผู้บังคับบัญชา' : 'ยังไม่ได้กำหนดผู้บังคับบัญชา' }}
                  </option>
                  <option v-for="person in supervisorChainOptions" :key="person.id" :value="person.id">
                    {{ person.label }} · {{ person.name }}{{ person.position ? ` (${person.position})` : '' }}
                  </option>
                </select>
                <input
                  v-else-if="field.type === 'expert-name'"
                  v-model="activeFormActivity.formDetails.detail[field.key]"
                  placeholder="กรอกชื่อผู้เชี่ยวชาญ"
                />
                <input v-else v-model="activeFormActivity.formDetails.detail[field.key]" />
              </label>
            </div>
          </section>
        </fieldset>

        <footer class="form-modal-footer">
          <button type="button" @click="closeActivityForm()">{{ selectedPlanLocked ? 'ปิด' : 'ยกเลิก' }}</button>
          <span v-if="!selectedPlanLocked && activeFormUserIssue" class="form-save-error" role="status">{{ activeFormUserIssue }}</span>
          <button v-if="!selectedPlanLocked" type="button" class="primary" :disabled="!!activeFormUserIssue" @click="saveActivityForm">บันทึกฟอร์ม</button>
        </footer>
      </div>
    </div>

    <div v-if="showCoachingApproachHelp" class="approach-modal-backdrop">
      <div class="approach-modal" role="dialog" aria-modal="true" aria-labelledby="coaching-approach-title">
        <header class="approach-modal-header">
          <div>
            <h3 id="coaching-approach-title">คำอธิบายแนวทางการสอนงาน</h3>
            <p>ใช้ประกอบการเลือกแนวทาง A, B, C, D ในแบบฟอร์มการสอนงาน</p>
          </div>
          <span class="approach-modal-badge">4 แนวทาง</span>
          <button type="button" aria-label="ปิดคำอธิบายแนวทาง" @click="showCoachingApproachHelp = false">×</button>
        </header>
        <div class="approach-modal-guide">
          <strong>วิธีใช้:</strong>
          <span>อ่านความหมายของแต่ละแนวทาง แล้วเลือก A, B, C หรือ D ในช่องแนวทางการสอนงานของแต่ละแถว</span>
        </div>
        <div class="approach-modal-body">
          <article
            v-for="approach in fullCoachingApproachDescriptions"
            :key="approach.code"
            class="approach-help-card"
            :class="`approach-${approach.code.toLowerCase()}`"
          >
            <header>
              <b>{{ approach.code }}</b>
              <div>
                <strong>{{ approach.title }}</strong>
                <span>{{ approach.summary }}</span>
              </div>
            </header>
            <ul>
              <li v-for="item in approach.items" :key="item">{{ item.replace(/^[-▪]\s*/, '') }}</li>
            </ul>
          </article>
        </div>
        <footer class="approach-modal-footer">
          <button type="button" @click="showCoachingApproachHelp = false">ปิด</button>
        </footer>
      </div>
    </div>

    <footer v-if="hasIdpReviewerSteps && idpGaps.length" class="submit-bar">
      <div>
        <strong>{{ selectedPlan ? `${selectedGap?.cd} · ${planStatusLabel(selectedPlan)}` : 'เลือกสมรรถนะ' }}</strong>
        <span v-if="submitError" id="idp-submit-reason" class="submit-reason" role="alert">
          {{ submitError }}
        </span>
        <span v-else-if="selectedPlanIssue" id="idp-submit-reason" class="submit-reason" role="status">
          ยังส่งไม่ได้: {{ selectedPlanIssue }}
        </span>
        <span v-else-if="saveState === 'saving'" id="idp-submit-reason" role="status">กำลังบันทึกข้อมูล กรุณารอสักครู่</span>
        <span v-else-if="selectedPlan && !selectedPlanLocked" class="submit-ready" role="status">ข้อมูลครบแล้ว พร้อมส่งอนุมัติ</span>
        <span v-if="selectedPlan?.status === 'revision_required'">เมื่อส่งใหม่ ระบบจะเริ่มตรวจจากผู้อนุมัติลำดับแรกอีกครั้ง</span>
        <span v-else-if="selectedPlanLocked">สมรรถนะอื่นยังสามารถจัดทำและส่งแยกได้</span>
        <span v-else-if="selectedPlanIssue">ข้อมูลร่างจะบันทึกอัตโนมัติหลังหยุดกรอก</span>
        <span v-else>บันทึกฟอร์มล่าสุดแล้ว และหยุดบันทึกอัตโนมัติ</span>
      </div>
      <button type="button" :aria-describedby="submitError || selectedPlanIssue || saveState === 'saving' ? 'idp-submit-reason' : undefined" :disabled="!selectedPlan || selectedPlanLocked || saveState === 'saving' || !!selectedPlanIssue" @click="submitSelectedPlan">
        {{ selectedPlanLocked ? planStatusLabel(selectedPlan) : 'ส่งสมรรถนะนี้ให้หัวหน้า' }}
      </button>
    </footer>
  </section>
</template>

<style scoped>
.idp-page { display: grid; gap: 16px; color: #172033; }
.plan-review-route { margin-top: 14px; border: 1px solid #d2dfda; border-radius: 8px; background: #fff; overflow: hidden; }
.plan-review-route > header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid #e4ebe7; }
.plan-review-route h3 { margin: 0; font-size: 14px; color: #245d4e; }
.plan-review-route p { margin: 4px 0 0; font-size: 11px; color: var(--color-text-muted); }
.plan-review-route ol { display: flex; gap: 12px; list-style: none; margin: 0; padding: 16px; overflow-x: auto; }
.plan-review-route li { display: flex; align-items: flex-start; gap: 10px; flex: 1 0 190px; padding: 12px; border-top: 3px solid #dce5e1; background: #f8faf9; }
.plan-review-route li.current { border-color: var(--color-primary); background: var(--color-primary-soft); }
.plan-review-route li > b { display: grid; place-items: center; flex: 0 0 28px; height: 28px; border-radius: 50%; background: #e2e9e5; color: #60746c; font-size: 12px; }
.plan-review-route li.current > b { background: var(--color-primary); color: #fff; }
.plan-review-route li strong, .plan-review-route li small, .plan-review-route li em { display: block; }
.plan-review-route li strong { font-size: 12px; }
.plan-review-route li small { margin-top: 3px; color: var(--color-text-muted); font-size: 10px; }
.plan-review-route li em { margin-top: 7px; color: var(--color-primary); font-size: 10px; font-style: normal; font-weight: 900; }
.plan-review-route > footer { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 8px; padding: 11px 16px; border-top: 1px solid #eee0c9; background: #fffaf0; color: #785b2c; font-size: 12px; line-height: 1.6; }
.plan-review-route > footer strong { color: #80591f; font-weight: 800; }
@media (max-width: 900px) { .plan-review-route > header { align-items: flex-start; flex-direction: column; } .plan-review-route ol { flex-direction: column; } .plan-review-route li { flex-basis: auto; } }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 16px; border-bottom: 1px solid #dfe5ed; }
.eyebrow { margin: 0 0 5px; color: #28705f; font-size: 11px; font-weight: 900; }
.page-header h1 { margin: 0; font-size: 24px; font-weight: 900; }
.subtitle { margin: 6px 0 0; color: #68768a; font-size: 13px; }
.idp-readiness-panel { overflow: hidden; border: 1px solid #d9dee5; border-radius: 10px; background: #fbfcfd; box-shadow: 0 12px 32px rgba(21, 25, 29, .07); }
.idp-readiness-panel > header { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 15px; border-bottom: 1px solid #e2e6eb; padding: 20px 22px; }
.idp-readiness-panel h2, .idp-readiness-panel p { margin: 0; }
.idp-readiness-panel h2 { color: #172033; font-size: 18px; }
.idp-readiness-panel p { margin-top: 5px; color: var(--color-text-muted); font-size: 12px; }
.idp-readiness-panel header small { display: block; margin-bottom: 4px; color: #3979b7; font-size: 10px; font-weight: 900; letter-spacing: .1em; }
.idp-readiness-symbol, .idp-readiness-state { display: grid; place-items: center; border-radius: 50%; background: #f7ded7; color: #a63824; font-weight: 900; }
.idp-readiness-symbol { width: 38px; height: 38px; font-size: 17px; }
.idp-readiness-count { display: grid; min-width: 88px; border: 1px solid #e0e4e9; border-radius: 8px; background: #f3f5f7; padding: 9px 12px; text-align: center; }
.idp-readiness-count strong { color: #172033; font-size: 18px; }
.idp-readiness-count span { color: var(--color-text-muted); font-size: 9px; font-weight: 800; }
.idp-readiness-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; align-items: center; gap: 12px; margin: 0 22px; padding: 15px 0; }
.idp-readiness-state { width: 26px; height: 26px; font-size: 11px; }
.idp-readiness-row > div { display: grid; gap: 3px; }
.idp-readiness-row strong { color: #172033; font-size: 12px; }
.idp-readiness-row small { color: #778391; font-size: 11px; }
.idp-readiness-owner { border-radius: 999px; background: #fff0eb; color: #a63824; padding: 5px 9px; font-size: 10px; font-weight: 900; }
.idp-readiness-panel > footer { display: grid; gap: 3px; border-top: 1px solid #e2e6eb; background: #f3f5f7; padding: 13px 22px; }
.idp-readiness-panel > footer strong { color: #172033; font-size: 11px; }
.idp-readiness-panel > footer span { color: var(--color-text-muted); font-size: 10px; line-height: 1.5; }
.workspace { min-height: 620px; border: 1px solid #ccd5df; border-radius: 8px; background: #eef2f5; overflow: hidden; }
.plan-nav { border-bottom: 1px solid #cfd8e2; background: #f7f9fb; }
.plan-nav-meta { display: flex; align-items: center; justify-content: space-between; gap: 20px; border-bottom: 1px solid #d8e0e8; background: #fff; }
.person { display: grid; gap: 3px; min-width: 220px; padding: 13px 17px; }
.person span, .person small { color: var(--color-text-muted); font-size: 11px; }
.person strong { font-size: 14px; }
.nav-heading { display: flex; align-items: center; gap: 9px; padding: 13px 17px; font-size: 14px; }
.nav-heading span { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; background: #dfe8e5; color: #216b59; font-weight: 900; }
.plan-nav-scroll { display: flex; gap: 10px; overflow-x: auto; padding: 12px 16px 14px; overscroll-behavior-x: contain; scrollbar-width: thin; scrollbar-color: #a9bbb5 transparent; }
.plan-nav-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 8px; flex: 0 0 280px; min-height: 108px; border: 1px solid #dce3e9; border-top: 3px solid #dce3e9; border-radius: 7px; background: #fff; padding: 12px 13px; text-align: left; cursor: pointer; }
.plan-nav-item:hover { border-color: #9cbfb3; background: #f5faf8; }
.plan-nav-item.active { border-color: var(--color-primary-border); border-top-color: var(--color-primary); background: var(--color-primary-soft); }
.plan-nav-item:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
.competency-code { min-width: 51px; color: var(--color-primary); font-size: 11px; font-weight: 900; }
.competency-copy { min-width: 0; grid-column: 1 / -1; grid-row: 2; }
.competency-copy strong, .competency-copy small { display: block; white-space: normal; overflow-wrap: anywhere; }
.competency-copy strong { overflow: hidden; font-size: 14px; line-height: 1.55; color: #273142; text-overflow: ellipsis; white-space: nowrap; }
.competency-copy small { overflow: hidden; margin-top: 8px; padding-top: 7px; border-top: 1px solid #dce6e1; color: #657287; font-size: 12px; line-height: 1.5; text-overflow: ellipsis; white-space: nowrap; }
.plan-status-badge { grid-column: 2; grid-row: 1; padding: 4px 7px; border: 1px solid; border-radius: 999px; font-size: 12px; font-weight: 900; line-height: 1.2; white-space: nowrap; }
.plan-status-badge.is-incomplete { border-color: #fecdd3; background: #fff1f2; color: #b42318; }
.plan-status-badge.is-ready { border-color: #b7dacc; background: #eaf5f0; color: #176b55; }
.plan-status-badge.is-review { border-color: #f1d49a; background: #fff7e6; color: #945b05; }
.plan-status-badge.is-returned { border-color: #f7b4a8; background: #fff0ed; color: #b42318; }
.plan-status-badge.is-approved { border-color: #abefc6; background: #eaf7ef; color: #067647; }
.plan-content { min-width: 0; padding: 20px; background: #eef2f5; }
.select-prompt, .empty-state, .activities-empty { display: grid; place-items: center; align-content: center; gap: 7px; min-height: 260px; color: var(--color-text-muted); text-align: center; }
.select-prompt strong, .empty-state strong, .activities-empty strong { color: #344054; }
.competency-header { display: flex; justify-content: space-between; gap: 18px; padding: 16px; border: 1px solid #cfd8e2; border-radius: 7px; background: #fff; box-shadow: 0 3px 12px rgba(26, 45, 59, .04); }
.competency-header h2 { margin: 8px 0 0; font-size: 20px; }
.competency-header p { max-width: 660px; margin: 7px 0 0; color: #69778a; font-size: 12px; line-height: 1.6; }
.type-tag { display: inline-flex; padding: 4px 7px; border-radius: 4px; background: #e3eeeb; color: #246b5a; font-size: 11px; font-weight: 900; }
.score-row { display: grid; grid-template-columns: repeat(3, 72px); gap: 7px; flex: 0 0 auto; }
.score-row > div { padding: 9px; border: 1px solid #e0e6ed; border-radius: 6px; text-align: center; }
.score-row span, .score-row strong { display: block; }
.score-row span { color: var(--color-text-muted); font-size: 10px; }
.score-row strong { margin-top: 4px; font-size: 17px; }
.score-row .negative { border-color: #fecaca; background: #fff7f7; color: #b42318; }
.revision-alert { display: grid; gap: 4px; margin-top: 14px; padding: 11px 13px; border: 1px solid #efb8b8; border-radius: 7px; background: #fff5f5; color: #9f2520; }
.revision-alert strong { font-size: 12px; }
.revision-alert span { font-size: 11px; line-height: 1.55; }
.section-card { margin-top: 14px; overflow: hidden; border: 1px solid; border-radius: 7px; background: #fff; box-shadow: 0 3px 12px rgba(26, 45, 59, .04); }
.behavior-section { border-color: #e8c4aa; }
.goal-section { border-color: #b8d0e8; }
.activities-section { border-color: #a9d4c5; }
.section-title { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin: 0; padding: 11px 13px; border-bottom: 1px solid; }
.behavior-section .section-title { border-color: #ecd2be; background: #fff4ec; }
.goal-section .section-title { border-color: #c9dced; background: #eef6fc; }
.activities-section .section-title { border-color: #c1e0d5; background: #edf8f4; }
.section-title > div:first-child { display: flex; align-items: center; gap: 10px; }
.section-number { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 5px; color: #fff; font-size: 11px; font-weight: 900; flex: 0 0 auto; }
.behavior-section .section-number { background: #c6682a; }
.goal-section .section-number { background: #3979b7; }
.activities-section .section-number { background: #28745f; }
.section-title h3, .section-title p { margin: 0; }
.section-title h3 { font-size: 14px; }
.section-title p { margin-top: 3px; color: var(--color-text-muted); font-size: 11px; }
.section-badge { padding: 5px 8px; border: 1px solid currentColor; border-radius: 5px; background: rgba(255,255,255,.78); font-size: 10px; font-weight: 900; white-space: nowrap; }
.behavior-section .section-badge { color: #a7531d; }
.goal-section .section-badge { color: #3979b7; }
.section-body { padding: 13px; }
.indicator-list { display: grid; gap: 7px; }
.indicator-row { display: grid; grid-template-columns: 125px 1fr; gap: 12px; padding: 10px 12px; border: 1px solid #ead6c7; border-radius: 5px; background: #fffcf9; }
.indicator-row span { color: #9b501f; font-size: 11px; font-weight: 900; }
.indicator-row p { margin: 0; font-size: 12px; line-height: 1.5; }
.goal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label { display: grid; gap: 6px; min-width: 0; color: #59677a; font-size: 11px; font-weight: 800; }
input, select, textarea { width: 100%; border: 1px solid #d5dde7; border-radius: 6px; background: #fff; padding: 9px 10px; color: #172033; font: inherit; font-size: 12px; box-sizing: border-box; }
select {
  appearance: none;
  padding-right: 34px;
  background-image:
    linear-gradient(45deg, transparent 50%, #667085 50%),
    linear-gradient(135deg, #667085 50%, transparent 50%);
  background-position:
    calc(100% - 17px) 50%,
    calc(100% - 12px) 50%;
  background-size: 5px 5px, 5px 5px;
  background-repeat: no-repeat;
  cursor: pointer;
}
textarea { resize: vertical; line-height: 1.5; }
input:focus, select:focus, textarea:focus { outline: 3px solid var(--color-focus); outline-offset: 1px; border-color: var(--color-focus); }
input:disabled, select:disabled, textarea:disabled { background: var(--color-disabled-bg); color: var(--color-disabled-text); }
select:disabled { cursor: not-allowed; }
.goal-section label > span { color: #3970a6; }
.goal-section textarea { border-color: #c8d8e8; background: #fbfdff; }
.weight-total { min-width: 112px; padding: 6px 9px; border: 1px solid #e8b7b7; border-radius: 5px; background: #fff; color: #b42318; text-align: center; font-size: 11px; font-weight: 900; }
.weight-total.complete { border-color: #87c8b0; background: #e6f6ef; color: #16835d; }
.activities-empty { min-height: 130px; border: 1px dashed #9fc3b8; border-radius: 6px; background: #f7fbfa; font-size: 12px; }
.activity { margin-top: 10px; border: 1px solid #cfd9e2; border-radius: 7px; overflow: hidden; background: #fff; box-shadow: 0 3px 10px rgba(26, 45, 59, .05); }
.activity:first-of-type { margin-top: 0; }
.activity > header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 11px 13px; border-bottom: 1px solid #e2e7ed; background: #f7f9fa; }
.activity-identity { display: flex; align-items: center; gap: 9px; min-width: 0; }
.activity-identity > div { min-width: 0; }
.activity-number { display: grid !important; place-items: center; width: 28px; height: 28px; border-radius: 5px; background: var(--color-primary-soft); color: var(--color-primary) !important; font-size: 11px !important; flex: 0 0 auto; }
.activity > header span, .activity > header strong { display: block; }
.activity > header span { color: var(--color-primary); font-size: 10px; font-weight: 900; }
.activity > header strong { margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
.activity-header-actions { display: flex; align-items: center; gap: 7px; flex: 0 0 auto; }
.activity > header .change-tool { width: auto; height: 30px; border: 1px solid var(--color-primary-border); border-radius: 5px; background: #fff; padding: 0 10px; color: var(--color-primary); font-size: 10px; font-weight: 900; cursor: pointer; }
.activity > header .remove-activity { width: 30px; height: 30px; border: 1px solid #e3c5c8; border-radius: 5px; background: #fff; color: #b42318; font-size: 18px; cursor: pointer; }
.activity-form { display: grid; grid-template-columns: minmax(180px, 240px) minmax(0, 1fr); gap: 11px; padding: 13px; }
.activity-form .wide { grid-column: 1 / -1; }
.activity-readonly-meta { display: grid; align-content: center; gap: 3px; min-height: 58px; border: 1px solid #dce4eb; border-radius: 6px; background: #f8fafc; padding: 8px 11px; }
.activity-readonly-meta span { color: #59677a; font-size: 11px; font-weight: 800; }
.activity-readonly-meta strong { color: #273142; font-size: 12px; }
.activity-readonly-meta small { color: #8793a3; font-size: 9px; font-weight: 700; }
.activity-detail-action { display: flex; align-items: center; justify-content: space-between; gap: 14px; border: 1px solid #d7e4ee; border-radius: 7px; background: #fbfdff; padding: 12px; }
.activity-detail-action strong, .activity-detail-action span { display: block; }
.activity-detail-action strong { color: #172033; font-size: 12px; }
.activity-detail-action span { margin-top: 4px; color: #9a4d00; font-size: 11px; font-weight: 900; }
.activity-detail-action span.saved { color: #16835d; }
.activity-detail-action button { border: 0; border-radius: 6px; background: var(--color-primary); padding: 9px 12px; color: #fff; font-size: 12px; font-weight: 900; cursor: pointer; white-space: nowrap; }
.activity-detail-action button:disabled { background: #aab5c2; cursor: not-allowed; }
.activity-builder { margin-top: 12px; overflow: hidden; border: 1px solid #a9cfc3; border-radius: 8px; background: #fff; box-shadow: 0 8px 24px rgba(28, 78, 65, .08); }
.activity-builder-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border-bottom: 1px solid #dbe9e4; background: #f3faf7; padding: 13px 14px; }
.activity-builder-head strong, .activity-builder-head span { display: block; }
.activity-builder-head strong { color: #174f41; font-size: 13px; }
.activity-builder-head span { margin-top: 4px; color: #667085; font-size: 11px; font-weight: 700; }
.activity-builder-head button { flex: 0 0 auto; width: 28px; height: 28px; border: 1px solid #c8d9d3; border-radius: 5px; background: #fff; color: #59677a; font-size: 18px; cursor: pointer; }
.activity-builder-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; border-bottom: 1px solid #e2e8ef; padding: 10px 12px; }
.activity-builder-tabs button { position: relative; display: grid; gap: 2px; min-width: 0; border: 1px solid #dbe2ea; border-radius: 7px; background: #fff; padding: 10px 38px 10px 11px; color: #344054; text-align: left; cursor: pointer; }
.activity-builder-tabs button.active { border-color: #28745f; background: #edf8f4; box-shadow: inset 0 0 0 1px #28745f; color: #175c4a; }
.activity-builder-tabs span { font-size: 12px; font-weight: 900; }
.activity-builder-tabs small { overflow: hidden; color: var(--color-text-muted); font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.activity-builder-tabs b { position: absolute; top: 50%; right: 10px; display: grid; place-items: center; min-width: 21px; height: 21px; border-radius: 999px; background: #edf1f5; color: #667085; font-size: 10px; transform: translateY(-50%); }
.activity-builder-tabs button.active b { background: #28745f; color: #fff; }
.activity-builder-list { display: grid; gap: 7px; padding: 12px; }
.activity-choice { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; align-items: center; gap: 11px; width: 100%; border: 1px solid #dce4eb; border-radius: 7px; background: #fff; padding: 9px 10px; color: #273142; text-align: left; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
.activity-choice:hover { border-color: #75ad9d; background: #f8fcfa; }
.activity-choice.unavailable { opacity: .45; background: #f3f5f7; cursor: not-allowed; filter: grayscale(.35); }
.activity-choice.unavailable:hover { border-color: #dce4eb; background: #f3f5f7; }
.activity-choice-code { display: grid; place-items: center; min-height: 34px; border-radius: 6px; background: var(--color-primary-soft); color: var(--color-primary); font-size: 10px; font-weight: 900; }
.activity-choice-code.formal { background: #eef4ff; color: #315f9f; }
.activity-choice-copy { min-width: 0; }
.activity-choice-copy strong, .activity-choice-copy small { display: block; }
.activity-choice-copy strong { overflow: hidden; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.activity-choice-copy small { margin-top: 3px; color: var(--color-text-muted); font-size: 10px; font-weight: 700; }
.activity-choice-add { border: 1px solid var(--color-primary-border); border-radius: 5px; padding: 6px 9px; color: var(--color-primary); font-size: 10px; font-weight: 900; }
.activity-builder-empty { padding: 22px 14px; border: 1px dashed #cbd5df; border-radius: 7px; background: #f8fafc; color: #667085; font-size: 11px; font-weight: 800; text-align: center; }
.activity-builder-empty strong, .activity-builder-empty span { display: block; }
.activity-builder-empty span { margin-top: 5px; font-weight: 700; }
.formal-empty { background: #f8faff; }
.add-activity { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; margin-top: 11px; border: 1px dashed #9dbbb3; border-radius: 6px; background: #f6fbf9; padding: 11px; color: #246b5a; font-size: 12px; font-weight: 900; cursor: pointer; }
.add-activity span { font-size: 18px; }
.error-box { margin-top: 12px; padding: 10px 12px; border: 1px solid #fecaca; border-radius: 6px; background: #fff7f7; color: #b42318; font-size: 12px; }
.form-modal-backdrop { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; background: rgba(15, 23, 42, .48); padding: 8px; }
.form-modal { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; width: min(1680px, calc(100vw - 16px)); max-height: calc(100vh - 28px); overflow: hidden; border-radius: 10px; background: #f8fafc; box-shadow: 0 30px 90px rgba(15, 23, 42, .3); }
.form-modal-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid #d8e0e9; background: #fff; padding: 14px 18px; }
.form-modal-top strong, .form-modal-top span { display: block; }
.form-modal-top strong { font-size: 16px; }
.form-modal-top span { margin-top: 4px; color: #667085; font-size: 12px; font-weight: 800; }
.form-modal-top button { width: 36px; height: 36px; border: 1px solid #d8e0e9; border-radius: 7px; background: #fff; color: #b42318; font-size: 22px; cursor: pointer; }
.form-paper { min-width: 0; min-height: 0; margin: 0; border: 0; overflow: auto; padding: 18px; }
.form-title-band { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border: 1px solid #f3c8b7; border-radius: 8px; background: #fff1e8; padding: 13px 15px; }
.form-title-band h3, .form-title-band p { margin: 0; }
.form-title-band h3 { font-size: 20px; }
.form-title-band p { margin-top: 5px; color: #667085; font-size: 12px; font-weight: 800; }
.form-title-band label { width: 260px; }
.form-block { margin-top: 14px; overflow: hidden; border: 1px solid #d8e0e9; border-radius: 8px; background: #fff; }
.form-block > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #d8e0e9; background: #f8fafc; padding: 10px 12px; }
.form-block > header h4 { margin: 0; font-size: 14px; }
.form-block > header span { color: #667085; font-size: 11px; font-weight: 900; }
.form-block > header button { border: 1px solid var(--color-primary-border); border-radius: 6px; background: var(--color-primary-soft); color: var(--color-primary); padding: 7px 10px; font-size: 11px; font-weight: 900; cursor: pointer; }
.readonly-block > header { background: #edf8f4; }
.approval-route-list { display: flex; align-items: center; gap: 10px; overflow-x: auto; padding: 14px; }
.approval-route-step { display: flex; align-items: center; gap: 10px; min-width: 220px; border: 1px solid #cfe3dc; border-radius: 8px; background: #f8fcfa; padding: 10px 12px; }
.approval-route-step > span { display: grid; place-items: center; flex: 0 0 30px; width: 30px; height: 30px; border-radius: 50%; background: var(--color-primary); color: #fff; font-size: 12px; font-weight: 900; }
.approval-route-step div, .approval-route-step strong, .approval-route-step small { display: block; min-width: 0; }
.approval-route-step strong { overflow: hidden; color: #172033; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.approval-route-step small { margin-top: 3px; overflow: hidden; color: #667085; font-size: 10px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.approval-route-arrow { flex: 0 0 auto; color: #8090a3; font-size: 18px; font-weight: 900; }
.approval-route-empty { margin: 14px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; padding: 14px; color: #667085; font-size: 12px; font-weight: 800; text-align: center; }
.approval-route-notice { margin: 0; border-top: 1px solid #e3e9f0; background: #fffaf0; padding: 9px 14px; color: #9a4d00; font-size: 11px; font-weight: 900; }
.result-block > header { background: #fff0fb; }
.assessment-block > header { background: #eef6ff; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
.form-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.form-grid .wide { grid-column: 1 / -1; }
.form-table-wrap { overflow-x: auto; padding: 12px; }
.form-table-wrap table { width: 100%; min-width: 1180px; border-collapse: collapse; }
.form-table-wrap th, .form-table-wrap td { border: 1px solid #cfd8e3; padding: 8px; vertical-align: top; }
.form-table-wrap th { background: #f8fafc; color: #475467; font-size: 11px; font-weight: 900; text-align: left; }
.form-table-wrap th small { display: block; margin-top: 3px; color: #9a4d00; font-size: 10px; }
.form-table-wrap input, .form-table-wrap select { min-width: 120px; }
.form-table-wrap .table-textarea { min-width: 240px; min-height: 72px; }
.project-assignment-list { display: grid; gap: 12px; padding: 14px; }
.project-assignment-item { overflow: hidden; border: 1px solid #d8e0e9; border-radius: 8px; background: #fbfdff; }
.project-assignment-item-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #e3e9f0; background: #f8fafc; padding: 10px 12px; }
.project-assignment-item-head strong { color: #172033; font-size: 13px; }
.project-assignment-item-head button { border: 1px solid #fecaca; border-radius: 6px; background: #fff; color: #b42318; padding: 6px 9px; font-size: 11px; font-weight: 900; cursor: pointer; }
.project-assignment-item-head button:disabled { opacity: .4; cursor: not-allowed; }
.project-assignment-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
.project-assignment-grid.training-form-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); align-items: start; }
.training-form-grid > .training-meta { grid-column: span 2; }
.training-form-grid > .training-wide { grid-column: 1 / -1; }
.coaching-timeline.training-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.catalog-snapshot-note { color: #667085; font-size: 11px; font-weight: 800; }
.training-catalog-summary { display: grid; gap: 12px; border: 1px solid #d8e3ef; border-radius: 9px; background: #f8fafc; padding: 14px; }
.training-course-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.training-course-heading > div { display: grid; gap: 3px; min-width: 0; }
.training-course-heading span { color: #667085; font-size: 11px; font-weight: 900; }
.training-course-heading strong { color: #172033; font-size: 15px; line-height: 1.45; }
.training-course-heading em { flex: 0 0 auto; border: 1px solid #cfe0f5; border-radius: 999px; background: #eff6ff; color: #2563eb; padding: 5px 10px; font-size: 11px; font-style: normal; font-weight: 900; }
.training-catalog-summary > p { min-width: 0; margin: 0; color: #475467; font-size: 12px; line-height: 1.7; white-space: pre-line; overflow-wrap: anywhere; }
.training-catalog-summary dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; overflow: hidden; margin: 0; border: 1px solid #e1e8f0; border-radius: 8px; background: #e1e8f0; }
.training-catalog-summary dl > div { display: grid; gap: 3px; background: #fff; padding: 10px 12px; }
.training-catalog-summary dt { color: var(--color-text-muted); font-size: 10px; font-weight: 800; }
.training-catalog-summary dd { margin: 0; color: #273142; font-size: 12px; font-weight: 900; }
.project-assignment-grid label { display: grid; gap: 6px; color: #475467; font-size: 11px; font-weight: 900; }
.project-assignment-grid > .wide { grid-column: 1 / -1; }
.project-assignment-grid input, .project-assignment-grid textarea, .project-assignment-grid select { width: 100%; border: 1px solid #cfd8e3; border-radius: 7px; background-color: #fff; padding: 9px 10px; font: inherit; color: #1f2937; transition: border-color .16s ease, box-shadow .16s ease; }
.project-assignment-grid select { min-height: 42px; appearance: none; cursor: pointer; padding-right: 38px; background-image: linear-gradient(45deg, transparent 50%, #7a8798 50%), linear-gradient(135deg, #7a8798 50%, transparent 50%); background-position: calc(100% - 18px) 50%, calc(100% - 12px) 50%; background-repeat: no-repeat; background-size: 6px 6px, 6px 6px; }
.project-assignment-grid input:focus, .project-assignment-grid textarea:focus, .project-assignment-grid select:focus { outline: none; border-color: #e26b50; box-shadow: 0 0 0 3px rgba(226, 107, 80, .12); }
.project-assignment-grid textarea { min-height: 80px; resize: vertical; }
.coaching-approach-field { display: grid; gap: 12px; border: 1px solid #dce9e5; border-radius: 9px; background: #f8fcfb; padding: 12px; }
.coaching-approach-label { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #344054; font-size: 12px; font-weight: 900; }
.coaching-approach-label > div { display: grid; gap: 2px; }
.coaching-approach-label small { color: var(--color-text-muted); font-size: 10px; font-weight: 700; }
.coaching-approach-label button { border: 1px solid #9fd0c4; border-radius: 999px; background: #fff; color: #1d6b59; padding: 6px 11px; font-size: 10px; font-weight: 900; cursor: pointer; }
.coaching-approach-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.coaching-approach-options button { position: relative; display: flex; align-items: center; gap: 9px; min-height: 48px; border: 1px solid #ccd8e0; border-radius: 8px; background: #fff; color: #4b5565; padding: 9px 30px 9px 10px; cursor: pointer; transition: border-color .15s ease, background .15s ease, box-shadow .15s ease; }
.coaching-approach-options button:hover { border-color: #76bbaa; box-shadow: 0 3px 10px rgba(36, 114, 96, .08); }
.coaching-approach-options button strong { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 7px; background: #edf1f5; color: #344054; }
.coaching-approach-options button span { font-size: 11px; font-weight: 900; }
.coaching-approach-options button i { position: absolute; right: 10px; display: none; color: var(--color-primary); font-style: normal; font-weight: 900; }
.coaching-approach-options button.selected { border-color: #72b9a7; background: #edf8f4; color: #185f50; }
.coaching-approach-options button.selected strong { background: var(--color-primary); color: #fff; }
.coaching-approach-options button.selected i { display: block; }
.coaching-timeline { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; border: 1px solid #d8e2e8; border-radius: 9px; background: #fff; padding: 12px; }
.coaching-timeline.coaching-timeline-four { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.coaching-timeline.group-activity-timeline { grid-template-columns: repeat(2, minmax(0, 1fr)); align-self: stretch; }
.coaching-timeline-heading { grid-column: 1 / -1; display: flex; align-items: baseline; justify-content: space-between; gap: 12px; border-bottom: 1px solid #e8edf1; padding: 0 2px 10px; }
.coaching-timeline-heading strong { color: #344054; font-size: 12px; }
.coaching-timeline-heading span { color: var(--color-text-muted); font-size: 10px; font-weight: 700; }
.coaching-timeline input { min-height: 42px; background: #fbfcfd; }
.fixed-topic-cell { display: grid; gap: 10px; min-width: 280px; color: #172033; font-size: 12px; font-weight: 900; }
.fixed-topic-cell label { display: grid; gap: 6px; color: #475467; font-size: 11px; font-weight: 900; }
.fixed-topic-cell .table-textarea { min-width: 260px; min-height: 56px; }
.checkbox-choice-group { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; min-width: 150px; padding: 7px 2px; }
.checkbox-choice-group label { display: inline-flex; align-items: center; gap: 5px; min-width: 0; color: #172033; font-size: 11px; font-weight: 800; white-space: nowrap; }
.checkbox-choice-group input { width: 14px; min-width: 14px; height: 14px; margin: 0; padding: 0; accent-color: var(--color-primary); }
.multi-check-button { display: grid; place-items: center; width: 22px; height: 22px; margin: 0 auto; border: 1.5px solid #b8c7d9; border-radius: 5px; background: #fff; color: transparent; cursor: pointer; transition: background .15s ease, border-color .15s ease, box-shadow .15s ease; }
.multi-check-button span { color: inherit; font-size: 14px; font-weight: 900; line-height: 1; }
.multi-check-button.checked { border-color: var(--color-primary); background: var(--color-primary); color: #fff; box-shadow: 0 0 0 3px rgba(117, 41, 45, .14); }
.row-number { width: 42px; text-align: center !important; }
.row-control { width: 62px; text-align: center; }
.row-control button { border: 1px solid #efb8b8; border-radius: 5px; background: #fff; color: #b42318; padding: 7px 9px; font-size: 11px; font-weight: 900; }
.row-control button:disabled { opacity: .35; }
.approach-modal-backdrop { position: fixed; inset: 0; z-index: 110; display: grid; place-items: center; background: rgba(15, 23, 42, .62); padding: 6px; }
.approach-modal { display: grid; grid-template-rows: auto auto minmax(0, 1fr) auto; width: min(1360px, calc(100vw - 12px)); height: calc(100vh - 12px); min-height: 0; overflow: hidden; border: 1px solid #d7e0ea; border-radius: 12px; background: #fff; box-shadow: 0 26px 80px rgba(15, 23, 42, .34); }
.approach-modal-header { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 12px; border-bottom: 1px solid #d8e0e9; background: #fff; padding: 14px 18px; }
.approach-modal-header h3, .approach-modal-header p { margin: 0; }
.approach-modal-header h3 { color: #172033; font-size: 22px; font-weight: 900; }
.approach-modal-header p { margin-top: 4px; color: #667085; font-size: 14px; font-weight: 800; }
.approach-modal-badge { border: 1px solid var(--color-primary-border); border-radius: 999px; background: var(--color-primary-soft); color: var(--color-primary); padding: 8px 13px; font-size: 13px; font-weight: 900; white-space: nowrap; }
.approach-modal-header button { display: grid; place-items: center; width: 40px; height: 40px; border: 1px solid #efb8b8; border-radius: 7px; background: #fff; color: #b42318; font-size: 26px; font-weight: 600; line-height: 1; cursor: pointer; }
.approach-modal-guide { display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #d8e0e9; background: #f7fbfa; padding: 10px 18px; color: #46576b; font-size: 14px; font-weight: 800; }
.approach-modal-guide strong { color: var(--color-primary); }
.approach-modal-body { display: flex; flex-direction: column; gap: 12px; min-height: 0; overflow-x: hidden; overflow-y: scroll; overscroll-behavior: contain; background: #eef3f6; padding: 14px 18px 18px; scrollbar-gutter: stable; -webkit-overflow-scrolling: touch; }
.approach-help-card { display: block; flex: 0 0 auto; border: 1px solid #d8e0e9; border-radius: 10px; background: #fff; overflow: visible; }
.approach-help-card header { display: flex; align-items: flex-start; gap: 12px; border-bottom: 1px solid #dfe6ee; background: #f8fafc; padding: 14px 16px; }
.approach-help-card header b { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 8px; background: var(--color-primary); color: #fff; font-size: 15px; flex: 0 0 auto; box-shadow: 0 8px 18px rgba(117, 41, 45, .14); }
.approach-help-card header strong, .approach-help-card header span { display: block; }
.approach-help-card header strong { color: #172033; font-size: 17px; font-weight: 900; }
.approach-help-card header span { margin-top: 5px; color: #667085; font-size: 14px; font-weight: 800; line-height: 1.4; }
.approach-help-card ul { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 30px; margin: 0; padding: 18px 32px 22px 46px; color: #344054; font-size: 14px; line-height: 1.6; list-style: none; }
.approach-help-card li { position: relative; margin: 0; padding-left: 18px; }
.approach-help-card li::before { content: ''; position: absolute; top: .68em; left: 0; width: 7px; height: 7px; border-radius: 50%; background: var(--color-primary); transform: translateY(-50%); }
.approach-help-card.approach-b header b { background: #315f9f; box-shadow: 0 8px 18px rgba(49, 95, 159, .18); }
.approach-help-card.approach-c header b { background: #8a5b18; box-shadow: 0 8px 18px rgba(138, 91, 24, .18); }
.approach-help-card.approach-d header b { background: #8a3a4f; box-shadow: 0 8px 18px rgba(138, 58, 79, .18); }
.approach-modal-footer { display: flex; justify-content: flex-end; border-top: 1px solid #d8e0e9; background: #fff; padding: 9px 14px; }
.approach-modal-footer button { border: 1px solid var(--color-primary); border-radius: 7px; background: var(--color-primary); color: #fff; padding: 10px 20px; font-size: 14px; font-weight: 900; cursor: pointer; }
.form-modal-footer { display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #d8e0e9; background: #fff; padding: 13px 18px; }
.form-save-error { align-self: center; margin-right: auto; color: #b42318; font-size: 13px; font-weight: 800; }
.form-modal-footer button { border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; padding: 10px 14px; font-size: 12px; font-weight: 900; cursor: pointer; }
.form-modal-footer button.primary { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }
.form-modal-footer button:disabled { border-color: #cbd5e1; background: #aab5c2; color: #fff; cursor: not-allowed; }
.submit-bar { position: sticky; bottom: 0; z-index: 5; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 13px 16px; border: 1px solid #d7dfe8; border-radius: 8px; background: rgba(255,255,255,.96); box-shadow: 0 -8px 22px rgba(23,32,51,.08); }
.submit-bar strong, .submit-bar span { display: block; }
.submit-bar strong { font-size: 13px; }
.submit-bar span { margin-top: 3px; color: var(--color-text-muted); font-size: 11px; }
.submit-bar .submit-reason { color: #b42318; font-size: 12px; font-weight: 800; }
.submit-bar .submit-ready { color: var(--color-success); font-size: 12px; font-weight: 800; }
.submit-bar button { min-width: 180px; border: 0; border-radius: 6px; background: var(--color-primary); padding: 11px 16px; color: #fff; font-size: 12px; font-weight: 900; cursor: pointer; }
.submit-bar button:disabled { background: #aab5c2; cursor: not-allowed; }
@media (max-width: 900px) {
  .page-header, .competency-header, .submit-bar { align-items: stretch; flex-direction: column; }
  .idp-readiness-panel > header { grid-template-columns: auto minmax(0, 1fr); padding: 17px; }
  .idp-readiness-count { grid-column: 1 / -1; grid-template-columns: auto auto; align-items: baseline; justify-content: center; gap: 5px; }
  .idp-readiness-row { grid-template-columns: 28px minmax(0, 1fr); margin-right: 17px; margin-left: 17px; }
  .idp-readiness-owner { grid-column: 2; justify-self: start; }
  .plan-nav-meta { align-items: stretch; }
  .person { min-width: 0; }
  .goal-grid, .activity-form, .form-grid, .form-grid.three, .project-assignment-grid, .project-assignment-grid.training-form-grid, .coaching-timeline, .coaching-timeline.group-activity-timeline, .coaching-timeline.training-summary { grid-template-columns: 1fr; }
  .training-course-heading { display: grid; }
  .training-course-heading em { justify-self: start; }
  .training-catalog-summary dl { grid-template-columns: 1fr; }
  .training-form-grid > .training-meta, .training-form-grid > .training-wide { grid-column: 1; }
  .approach-modal-header { grid-template-columns: minmax(0, 1fr) auto; }
  .approach-modal-badge { display: none; }
  .approach-modal-guide { align-items: flex-start; flex-direction: column; }
  .coaching-timeline-heading { align-items: flex-start; flex-direction: column; gap: 3px; }
  .approach-help-card ul { grid-template-columns: 1fr; }
  .activity-form .wide { grid-column: span 1; }
  .activity-builder-tabs { grid-template-columns: 1fr; }
  .activity-choice { grid-template-columns: 38px minmax(0, 1fr) auto; }
  .activity-detail-action, .form-title-band { align-items: stretch; flex-direction: column; }
  .form-title-band label { width: 100%; }
  .score-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .indicator-row { grid-template-columns: 1fr; }
  .section-title { align-items: flex-start; }
  .submit-bar button { width: 100%; }
}
</style>
