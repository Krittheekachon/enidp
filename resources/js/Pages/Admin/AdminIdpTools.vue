<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
  formDefinitions,
  hasGroupedRowFields,
  rowHeaderCells,
  rowSubHeaderFields,
} from '../../idpFormDefinitions';
import { coachingApproachDescriptions } from '../../coachingApproachDescriptions';

const props = defineProps({
  competencies: { type: Array, default: () => [] },
  idpLearningMethods: { type: Array, default: () => [] },
  learningCatalogs: { type: Array, default: () => [] },
  learningMethods: { type: Array, default: () => [] },
  deliveryTypeSettings: { type: Array, default: () => [] },
});

const focusTabs = [
  { key: 'experiential', label: 'Experiential Focus' },
  { key: 'social', label: 'Social Learning Focus' },
  { key: 'formal', label: 'Formal Learning Focus' },
];
const activityFormOptions = [
  { value: '', label: 'ไม่มีแบบฟอร์มรายละเอียด' },
  { value: 'form_3_project_assignment', label: 'Project Assignment' },
  { value: 'form_4_ojt', label: 'OJT' },
  { value: 'form_5_coaching', label: 'Coaching' },
  { value: 'form_6_mentoring', label: 'Mentoring' },
  { value: 'form_7_group_activity', label: 'Group Activity' },
  { value: 'form_8_feedback', label: 'แบบฟอร์มการเรียนรู้ผ่านการให้ข้อมูลป้อนกลับ (Feedback)' },
  { value: 'form_9_field_trip', label: 'Field Trip' },
];
const previewFormOptions = Object.entries(formDefinitions)
  .filter(([, form]) => Number(form.number) >= 3 && Number(form.number) <= 10)
  .map(([value, form]) => ({
    value,
    label: form.title,
  }));
const deliveryTypeBaseOptions = [
  { value: 'e_learning', label: 'การฝึกอบรมออนไลน์ (e-Learning)' },
  { value: 'in_class', label: 'การฝึกอบรมในห้องเรียน (In Class Training)' },
];
const formalTrainingFormCode = 'form_10_training';
const levelOptions = [
  { value: 1, label: 'Level 1' },
  { value: 2, label: 'Level 2' },
  { value: 3, label: 'Level 3' },
  { value: 4, label: 'Level 4' },
  { value: 5, label: 'Level 5' },
];
const deliverySortOrder = { in_class: 1, e_learning: 2 };
const codeCollator = new Intl.Collator(['th', 'en'], { numeric: true, sensitivity: 'base' });

const activeFocus = ref('experiential');
const initialDeliveryTypeCodes = () => {
  const codes = Object.fromEntries(deliveryTypeBaseOptions.map((item) => [item.value, '']));
  props.deliveryTypeSettings.forEach((setting) => {
    const value = setting.value || setting.delivery_type;
    if (value && codes[value] !== undefined) codes[value] = String(setting.code || '');
  });
  return codes;
};
const initialDeliveryTypeForms = () => {
  const forms = { e_learning: 'form_10_training', in_class: 'form_10_training' };
  props.deliveryTypeSettings.forEach((setting) => {
    const value = setting.value || setting.delivery_type;
    if (value && forms[value] !== undefined) forms[value] = String(setting.formCode || setting.form_code || forms[value]);
  });
  return forms;
};
const toolModalOpen = ref(false);
const toolMode = ref('create');
const toolForm = ref({ id: null, focusType: 'experiential', title: '', formCode: '' });
const previewFormCode = ref('');
const previewCoachType = ref('');
const previewCoachingHelpOpen = ref(false);
const previewPickerOpen = ref(false);
const deliveryTypeCodes = ref(initialDeliveryTypeCodes());
const deliveryTypeForms = ref(initialDeliveryTypeForms());
const deliveryCodeErrors = ref({});
const deliveryCodeSaving = ref(false);
const deliveryEditing = ref(false);
const catalogModalOpen = ref(false);
const catalogMode = ref('create');
const catalogView = ref('in_class');
const expandedCatalogId = ref(null);
const catalogSearch = ref('');
const catalogCompetencySearch = ref('');
const catalogMethodFilter = ref('all');
const catalogDeliveryFilter = ref('all');
const catalogStatusFilter = ref('all');
const catalogErrors = ref({});
const catalogForm = ref({
  id: null,
  code: '',
  name: '',
  methodKey: '',
  deliveryType: 'e_learning',
  sourceType: 'internal',
  cost: '',
  hours: '',
  competencyIds: [],
  expectedLevels: [],
  description: '',
  isActive: true,
});

const activeTab = computed(() => focusTabs.find((tab) => tab.key === activeFocus.value) || focusTabs[0]);
const simpleTools = computed(() => props.idpLearningMethods.filter((tool) => tool.focusType === activeFocus.value));
const deliveryTypeSettingsByValue = computed(() => Object.fromEntries(
  props.deliveryTypeSettings.map((setting) => [setting.value || setting.key || setting.delivery_type, setting]),
));
const deliveryTypeOptions = computed(() => deliveryTypeBaseOptions.map((item) => ({
  value: item.value,
  code: deliveryTypeCodes.value[item.value] || deliveryTypeSettingsByValue.value[item.value]?.code || item.defaultCode,
  formCode: deliveryTypeForms.value[item.value] || deliveryTypeSettingsByValue.value[item.value]?.formCode,
  label: deliveryTypeSettingsByValue.value[item.value]?.label || item.label,
})));
const isCatalogELearning = computed(() => catalogForm.value.deliveryType === 'e_learning');
const catalogCounts = computed(() => ({
  in_class: props.learningCatalogs.filter((item) => item.deliveryType === 'in_class').length,
  e_learning: props.learningCatalogs.filter((item) => item.deliveryType === 'e_learning').length,
}));
const catalogPrimaryError = computed(() => Object.values(catalogErrors.value)[0] || '');
const formalMethodKey = computed(() => {
  const formal = props.learningMethods.find((method) => {
    const key = String(method.key || '').toLowerCase();
    const label = String(method.label || '').toLowerCase();
    return key.includes('formal') || label.includes('formal');
  });
  return formal?.key || props.learningMethods[0]?.key || '';
});
const filteredCatalogs = computed(() => {
  const keyword = catalogSearch.value.trim().toLowerCase();
  return props.learningCatalogs.filter((item) => {
    const haystack = `${item.code || ''} ${item.name || ''} ${item.description || ''}`.toLowerCase();
    const matchesSearch = !keyword || haystack.includes(keyword);
    const matchesMethod = catalogMethodFilter.value === 'all' || item.methodKey === catalogMethodFilter.value;
    const matchesDelivery = item.deliveryType === catalogView.value;
    const matchesStatus = catalogStatusFilter.value === 'all'
      || (catalogStatusFilter.value === 'active' ? item.isActive : !item.isActive);

    return matchesSearch && matchesMethod && matchesDelivery && matchesStatus;
  }).sort((a, b) => {
    const deliveryA = deliverySortOrder[a.deliveryType] || 99;
    const deliveryB = deliverySortOrder[b.deliveryType] || 99;
    if (deliveryA !== deliveryB) return deliveryA - deliveryB;

    const codeA = String(a.code || '').trim();
    const codeB = String(b.code || '').trim();
    if (codeA && codeB) return codeCollator.compare(codeA, codeB);
    if (codeA) return -1;
    if (codeB) return 1;

    return codeCollator.compare(String(a.name || ''), String(b.name || ''));
  });
});
const filteredCatalogCompetencies = computed(() => {
  const keyword = catalogCompetencySearch.value.trim().toLowerCase();
  if (!keyword) return props.competencies;

  return props.competencies.filter((competency) => {
    const haystack = `${competency.cd || ''} ${competency.n || ''} ${competency.t || ''} ${competency.typeName || ''}`.toLowerCase();
    return haystack.includes(keyword);
  });
});
const resetCatalogFilters = () => {
  catalogSearch.value = '';
  catalogMethodFilter.value = 'all';
  catalogDeliveryFilter.value = 'all';
  catalogStatusFilter.value = 'all';
};

const toolPayload = () => ({
  focus_type: toolForm.value.focusType,
  title: toolForm.value.title,
  form_code: toolForm.value.formCode || null,
});
const activityFormLabel = (formCode) =>
  [...activityFormOptions, ...previewFormOptions].find((option) => option.value === (formCode || ''))?.label || 'ไม่มีแบบฟอร์มรายละเอียด';
const activePreviewOptions = computed(() => previewFormOptions);
const previewForm = computed(() => formDefinitions[previewFormCode.value] || null);
const previewRows = computed(() => {
  if (!previewForm.value) return [];
  return ['1'];
});
const openFormPreview = (formCode) => {
  const form = formDefinitions[formCode];
  if (!form || Number(form.number) < 3 || Number(form.number) > 10) return;
  previewPickerOpen.value = false;
  previewCoachType.value = '';
  previewFormCode.value = formCode;
};
const openPreviewPicker = () => {
  previewPickerOpen.value = true;
};
const openToolCreate = () => {
  toolMode.value = 'create';
  toolForm.value = { id: null, focusType: activeFocus.value, title: '', formCode: '' };
  toolModalOpen.value = true;
};
const openToolEdit = (tool) => {
  toolMode.value = 'edit';
  toolForm.value = { id: tool.id, focusType: tool.focusType, title: tool.title, formCode: tool.formCode || '' };
  toolModalOpen.value = true;
};
const submitTool = () => {
  if (toolMode.value === 'edit' && toolForm.value.id) {
    router.put(route('admin.idp-learning-methods.update', toolForm.value.id), toolPayload(), {
      preserveScroll: true,
      onSuccess: () => { toolModalOpen.value = false; },
    });
    return;
  }
  router.post(route('admin.idp-learning-methods.store'), toolPayload(), {
    preserveScroll: true,
    onSuccess: () => { toolModalOpen.value = false; },
  });
};
const deleteTool = (tool) => {
  if (!window.confirm(`ลบ "${tool.title}"?`)) return;
  router.delete(route('admin.idp-learning-methods.destroy', tool.id), { preserveScroll: true });
};

const catalogPayload = () => ({
  code: catalogForm.value.code || null,
  name: catalogForm.value.name,
  method_key: formalMethodKey.value || catalogForm.value.methodKey || null,
  delivery_type: catalogForm.value.deliveryType,
  source_type: isCatalogELearning.value ? catalogForm.value.sourceType : 'internal',
  cost: isCatalogELearning.value && catalogForm.value.cost !== '' ? catalogForm.value.cost : null,
  hours: catalogForm.value.hours === '' ? null : catalogForm.value.hours,
  competency_ids: catalogForm.value.competencyIds,
  expected_levels: isCatalogELearning.value ? catalogForm.value.expectedLevels : [],
  description: catalogForm.value.description || null,
  is_active: catalogForm.value.isActive,
});
const setCatalogDeliveryType = (value) => {
  catalogForm.value.deliveryType = value;

  if (value === 'in_class') {
    catalogForm.value.expectedLevels = [];
    catalogForm.value.cost = '';
    catalogForm.value.sourceType = 'internal';
  }
};
const clearCatalogErrors = () => {
  catalogErrors.value = {};
};
const clearCatalogError = (field) => {
  if (!catalogErrors.value[field]) return;
  const nextErrors = { ...catalogErrors.value };
  delete nextErrors[field];
  catalogErrors.value = nextErrors;
};
const openCatalogCreate = (deliveryType = catalogView.value) => {
  catalogMode.value = 'create';
  catalogCompetencySearch.value = '';
  clearCatalogErrors();
  catalogForm.value = {
    id: null,
    code: '',
    name: '',
    methodKey: formalMethodKey.value,
    deliveryType,
    sourceType: 'internal',
    cost: '',
    hours: '',
    competencyIds: [],
    expectedLevels: [],
    description: '',
    isActive: true,
  };
  catalogModalOpen.value = true;
};
const openCatalogEdit = (item) => {
  catalogMode.value = 'edit';
  catalogCompetencySearch.value = '';
  clearCatalogErrors();
  catalogForm.value = {
    id: item.id,
    code: item.code || '',
    name: item.name || '',
    methodKey: item.methodKey || formalMethodKey.value,
    deliveryType: item.deliveryType || 'e_learning',
    sourceType: item.sourceType || 'internal',
    cost: item.cost ?? '',
    hours: item.hours ?? '',
    competencyIds: [...(item.competencyIds || [])].slice(0, 1),
    expectedLevels: [...(item.expectedLevels || [])],
    description: item.description || '',
    isActive: Boolean(item.isActive),
  };
  catalogModalOpen.value = true;
};
const submitCatalog = () => {
  clearCatalogErrors();
  if (catalogMode.value === 'edit' && catalogForm.value.id) {
    router.put(route('admin.learning-catalogs.update', catalogForm.value.id), catalogPayload(), {
      preserveScroll: true,
      onError: (errors) => { catalogErrors.value = errors; },
      onSuccess: () => {
        clearCatalogErrors();
        catalogModalOpen.value = false;
      },
    });
    return;
  }
  router.post(route('admin.learning-catalogs.store'), catalogPayload(), {
    preserveScroll: true,
    onError: (errors) => { catalogErrors.value = errors; },
    onSuccess: () => {
      clearCatalogErrors();
      catalogModalOpen.value = false;
    },
  });
};
const deleteCatalog = (item) => {
  if (!window.confirm(`ลบ "${item.name}" ออกจาก Formal Learning?`)) return;
  router.delete(route('admin.learning-catalogs.destroy', item.id), { preserveScroll: true });
};
const toggleCatalogCompetency = (id) => {
  catalogForm.value.competencyIds = catalogForm.value.competencyIds.includes(id) ? [] : [id];
};
const toggleExpectedLevel = (level) => {
  const current = new Set(catalogForm.value.expectedLevels);
  current.has(level) ? current.delete(level) : current.add(level);
  catalogForm.value.expectedLevels = [...current].sort((a, b) => a - b);
  clearCatalogError('expected_levels');
};
const selectAllExpectedLevels = () => {
  catalogForm.value.expectedLevels = levelOptions.map((level) => level.value);
  clearCatalogError('expected_levels');
};
const competencyLabel = (item) => {
  const competencyId = item.competencyIds?.[0];
  const competency = props.competencies.find((entry) => entry.id === competencyId);

  if (!competency) return '-';
  return competency.cd || competency.n || '-';
};
const formatExpectedLevels = (levels = []) => {
  const normalized = [...new Set(levels.map(Number).filter((level) => level >= 1 && level <= 5))].sort((a, b) => a - b);
  if (!normalized.length) return '-';

  const ranges = [];
  let start = normalized[0];
  let end = normalized[0];

  normalized.slice(1).forEach((level) => {
    if (level === end + 1) {
      end = level;
      return;
    }

    ranges.push(start === end ? `${start}` : `${start}-${end}`);
    start = level;
    end = level;
  });
  ranges.push(start === end ? `${start}` : `${start}-${end}`);

  return ranges.join(', ');
};
const updateDeliveryTypeCode = (value, code) => {
  deliveryTypeCodes.value = {
    ...deliveryTypeCodes.value,
    [value]: String(code || '').replace(/\D/g, '').slice(0, 20),
  };
};
const updateDeliveryTypeForm = (value, formCode) => {
  deliveryTypeForms.value = {
    ...deliveryTypeForms.value,
    [value]: formCode,
  };
};
const deliveryCodeError = (value) => deliveryCodeErrors.value[`delivery_types.${value}`] || '';
const deliveryFormError = (value) => deliveryCodeErrors.value[`delivery_forms.${value}`] || '';
const resetDeliveryTypeSettings = () => {
  deliveryTypeCodes.value = initialDeliveryTypeCodes();
  deliveryTypeForms.value = initialDeliveryTypeForms();
  deliveryCodeErrors.value = {};
};
const cancelDeliveryTypeEdit = () => {
  resetDeliveryTypeSettings();
  deliveryEditing.value = false;
};
const saveDeliveryTypeCodes = () => {
  deliveryCodeErrors.value = {};
  router.put(route('admin.idp-delivery-type-settings.update'), {
    delivery_types: deliveryTypeCodes.value,
    delivery_forms: deliveryTypeForms.value,
  }, {
    preserveScroll: true,
    onStart: () => { deliveryCodeSaving.value = true; },
    onFinish: () => { deliveryCodeSaving.value = false; },
    onError: (errors) => { deliveryCodeErrors.value = errors; },
    onSuccess: () => { deliveryEditing.value = false; },
  });
};
const deliveryTypeOption = (value) => deliveryTypeOptions.value.find((item) => item.value === value);
const deliveryTypeCode = (value) => deliveryTypeOption(value)?.code || '-';
const deliveryTypeLabel = (value) => deliveryTypeOption(value)?.label || '-';
const deliveryTypeSelectLabel = (option) => option.label;
const deliveryTypeFormCode = (value) => deliveryTypeOption(value)?.formCode || 'form_10_training';
const deliveryTypeFormLabel = (value) => activityFormLabel(deliveryTypeFormCode(value));
const deliveryTypeDisplay = (value) => {
  const option = deliveryTypeOption(value);
  if (!option) return '-';
  return deliveryTypeSelectLabel(option);
};
</script>

<template>
  <div class="admin-idp-tools">
    <div class="page-head">
      <div>
        <div class="sec-t">เครื่องมือ / แนวทาง สำหรับทำแผน IDP</div>
        <div class="sec-s">จัดการหัวข้อที่บุคลากรเลือกใช้ตอนทำแผน IDP</div>
      </div>
    </div>

    <div class="focus-tabs">
      <button
        v-for="tab in focusTabs"
        :key="tab.key"
        class="focus-tab"
        :class="{ active: activeFocus === tab.key }"
        type="button"
        @click="activeFocus = tab.key"
      >
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <section v-if="activeFocus !== 'formal'" class="card focus-panel">
      <div class="panel-head">
        <div>
          <div class="ct">{{ activeTab.label }}</div>
          <div class="cs">เพิ่ม/ลดหัวข้อเครื่องมือที่ User จะเลือกตอนทำ IDP</div>
        </div>
        <div class="panel-actions">
          <button class="btn btn-s" type="button" @click="openPreviewPicker">ดูตัวอย่าง</button>
          <button class="btn btn-p" type="button" @click="openToolCreate">เพิ่มหัวข้อ</button>
        </div>
      </div>

      <div class="tool-list">
        <div v-if="!simpleTools.length" class="empty-box">ยังไม่มีหัวข้อในหมวดนี้</div>
        <div v-for="(tool, index) in simpleTools" v-else :key="tool.id" class="tool-row">
          <div class="tool-no">{{ index + 1 }}</div>
          <div class="tool-title">
            <strong>{{ tool.title }}</strong>
            <small>{{ activityFormLabel(tool.formCode) }}</small>
          </div>
          <div class="tool-actions">
            <button class="btn btn-s btn-sm" type="button" @click="openToolEdit(tool)">แก้ไข</button>
            <button class="btn btn-s btn-sm danger" type="button" @click="deleteTool(tool)">ลบ</button>
          </div>
        </div>
      </div>
    </section>

    <div v-else class="formal-stack">
      <section class="card focus-panel">
        <div class="panel-head">
          <div>
            <div class="ct">Learning Catalog</div>
            <div class="cs">จัดการหลักสูตรที่พนักงานเลือกใช้ในแผน IDP</div>
          </div>
          <div class="panel-actions">
            <span class="panel-count">{{ learningCatalogs.length }} หลักสูตร</span>
            <button class="btn btn-p" type="button" @click="openCatalogCreate(catalogView)">เพิ่มหลักสูตร</button>
          </div>
        </div>

        <div class="catalog-card">
          <div class="catalog-type-tabs">
            <button type="button" :class="{ active: catalogView === 'in_class' }" @click="catalogView = 'in_class'">
              <span><strong>Training Roadmap (In-class Training)</strong><small>ทะเบียนหลักสูตรฝึกอบรมในห้องเรียน</small></span>
              <em>{{ catalogCounts.in_class }}</em>
            </button>
            <button type="button" :class="{ active: catalogView === 'e_learning' }" @click="catalogView = 'e_learning'">
              <span><strong>e-Learning Catalog</strong><small>ทะเบียนหลักสูตรและบทเรียนออนไลน์</small></span>
              <em>{{ catalogCounts.e_learning }}</em>
            </button>
            <button class="catalog-form-preview" type="button" @click="openFormPreview(formalTrainingFormCode)">ดูแบบฟอร์มการฝึกอบรม</button>
          </div>
          <div class="catalog-filter-bar">
            <input
              v-model="catalogSearch"
              class="inp catalog-filter-search"
              placeholder="ค้นหารหัสหลักสูตร ชื่อหลักสูตร หรือคำอธิบาย"
            />
            <select v-model="catalogStatusFilter" class="sel catalog-filter-select compact">
              <option value="all">ทุกสถานะ</option>
              <option value="active">เปิดใช้</option>
              <option value="inactive">ปิด</option>
            </select>
            <button class="btn btn-s btn-sm" type="button" @click="resetCatalogFilters">ล้าง</button>
          </div>
          <div class="table-wrap">
            <table class="tbl catalog-table">
              <thead>
                <tr>
                  <th class="col-order">ลำดับ</th>
                  <th v-if="catalogView === 'in_class'">รหัสหลักสูตร</th>
                  <th>{{ catalogView === 'e_learning' ? 'ชื่อหลักสูตร/บทเรียน' : 'ชื่อหลักสูตร' }}</th>
                  <th>สมรรถนะที่เกี่ยวข้อง</th>
                  <th v-if="catalogView === 'e_learning'">ระดับคาดหวัง</th>
                  <th>{{ catalogView === 'e_learning' ? 'ชม.เรียนรู้' : 'จำนวนชั่วโมง' }}</th>
                  <th v-if="catalogView === 'e_learning'">แหล่งหลักสูตร</th>
                  <th>สถานะ</th>
                  <th></th>
                </tr>
              </thead>
              <tbody v-if="!catalogCounts[catalogView]">
                <tr>
                  <td :colspan="catalogView === 'e_learning' ? 8 : 7">
                    <div class="table-empty-cell">
                      <div>
                        <div class="fw8 fs14">ยังไม่มีหลักสูตรในหมวดนี้</div>
                        <p class="muted fs13 mb0">กด “เพิ่มหลักสูตร” เพื่อสร้างรายการแรก</p>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tbody v-else-if="!filteredCatalogs.length">
                <tr>
                  <td :colspan="catalogView === 'e_learning' ? 8 : 7">
                    <div class="table-empty-cell">
                      <div>
                        <div class="fw8 fs14">ไม่พบรายการตาม filter</div>
                        <p class="muted fs13 mb0">ลองเปลี่ยนคำค้นหา ประเภท รูปแบบ หรือสถานะอีกครั้ง</p>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr v-for="(item, index) in filteredCatalogs" :key="item.id">
                  <td class="fw8 muted">{{ index + 1 }}</td>
                  <td v-if="catalogView === 'in_class'" class="fw8">{{ item.code || '-' }}</td>
                  <td>
                    <button class="catalog-name-button" type="button" :aria-expanded="expandedCatalogId === item.id" @click="expandedCatalogId = expandedCatalogId === item.id ? null : item.id">
                      <span>{{ item.name }}</span><em>{{ expandedCatalogId === item.id ? 'ซ่อนรายละเอียด' : 'ดูรายละเอียด' }}</em>
                    </button>
                    <div v-if="catalogView === 'e_learning' && item.code" class="catalog-form-label">{{ item.code }}</div>
                    <div v-if="expandedCatalogId === item.id" class="catalog-description">
                      <span>คำอธิบายหลักสูตร</span>
                      <p>{{ item.description || 'ไม่มีคำอธิบายหลักสูตร' }}</p>
                    </div>
                  </td>
                  <td><span class="competency-badge">{{ competencyLabel(item) }}</span></td>
                  <td v-if="catalogView === 'e_learning'">
                    <span v-if="item.expectedLevels?.length" class="catalog-levels">ระดับ {{ formatExpectedLevels(item.expectedLevels) }}</span>
                    <span v-else class="muted">ไม่ระบุ</span>
                  </td>
                  <td>{{ item.hours ?? '-' }}</td>
                  <td v-if="catalogView === 'e_learning'">{{ item.sourceType === 'external' ? 'ภายนอก' : 'ภายใน' }}</td>
                  <td>
                    <span class="status-pill" :class="item.isActive ? 'active' : 'inactive'">
                      {{ item.isActive ? 'เปิดใช้' : 'ปิด' }}
                    </span>
                  </td>
                  <td>
                    <div class="row-actions">
                      <button class="btn btn-s btn-sm" type="button" @click="openCatalogEdit(item)">แก้ไข</button>
                      <button class="btn btn-s btn-sm danger" type="button" @click="deleteCatalog(item)">ลบ</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>

    <div v-if="toolModalOpen" class="modal" @click.self="toolModalOpen = false">
      <form class="modal-box compact" @submit.prevent="submitTool">
        <div class="modal-head">
          <div class="ct">{{ toolMode === 'edit' ? 'แก้ไขหัวข้อ' : 'เพิ่มหัวข้อ' }}</div>
          <button class="modal-close" type="button" @click="toolModalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <label class="lbl">หัวข้อ</label>
          <input v-model="toolForm.title" class="inp" required placeholder="เช่น การมอบหมายงานโครงการ / งานพิเศษ" />
          <label class="lbl">แบบฟอร์มที่ใช้</label>
          <div class="form-select-row">
            <select v-model="toolForm.formCode" class="sel">
              <option v-for="option in activityFormOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <button
              class="btn btn-s"
              type="button"
              :disabled="!toolForm.formCode"
              @click="openFormPreview(toolForm.formCode)"
            >ดูตัวอย่าง</button>
          </div>
          <div class="muted-note">ถ้าหัวข้อนี้ไม่มีแบบฟอร์มเฉพาะ ให้เลือก “ไม่มีแบบฟอร์มรายละเอียด” ปุ่มกรอกรายละเอียดจะถูกปิดในหน้า IDP</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-s" type="button" @click="toolModalOpen = false">ยกเลิก</button>
          <button class="btn btn-p" type="submit">บันทึก</button>
        </div>
      </form>
    </div>

    <div v-if="catalogModalOpen" class="modal" @click.self="catalogModalOpen = false">
      <form class="modal-box catalog-modal-box" @submit.prevent="submitCatalog">
        <div class="modal-head">
          <div>
            <div class="ct">{{ catalogMode === 'edit' ? 'แก้ไขหลักสูตร' : 'เพิ่มหลักสูตร' }}</div>
            <div class="cs">ข้อมูลนี้จะแสดงใน Learning Catalog สำหรับการทำแผน IDP</div>
          </div>
          <button class="modal-close" type="button" @click="catalogModalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <section class="form-section catalog-section">
            <div class="section-title">รูปแบบการอบรม</div>
            <div class="catalog-format-picker">
              <button type="button" :class="{ selected: catalogForm.deliveryType === 'in_class' }" @click="setCatalogDeliveryType('in_class')">
                <strong>Training Roadmap (In-class Training)</strong>
                <span>ทะเบียนหลักสูตรฝึกอบรมในห้องเรียน</span>
              </button>
              <button type="button" :class="{ selected: catalogForm.deliveryType === 'e_learning' }" @click="setCatalogDeliveryType('e_learning')">
                <strong>e-Learning Catalog</strong>
                <span>ทะเบียนหลักสูตรหรือบทเรียนออนไลน์</span>
              </button>
            </div>
          </section>

          <section class="form-section catalog-section">
            <div class="catalog-section-head">
              <div>
                <div class="section-title">สมรรถนะที่เกี่ยวข้อง</div>
                <div class="section-sub">เลือกสมรรถนะหลักที่หลักสูตรนี้ใช้พัฒนา 1 รายการ</div>
              </div>
              <span class="count-pill">{{ catalogForm.competencyIds.length ? 'เลือกแล้ว' : 'ยังไม่เลือก' }}</span>
            </div>
            <div v-if="catalogErrors.competency_ids" class="field-error catalog-field-error">{{ catalogErrors.competency_ids }}</div>
            <input v-model="catalogCompetencySearch" class="inp catalog-search-input" placeholder="ค้นหารหัสหรือชื่อสมรรถนะ เช่น CC, MC, FC" />
            <div class="competency-list compact-list">
              <button v-for="competency in filteredCatalogCompetencies" :key="competency.id" class="competency-option" :class="{ selected: catalogForm.competencyIds.includes(competency.id) }" type="button" @click="toggleCatalogCompetency(competency.id)">
                <span>{{ competency.cd }}</span><strong>{{ competency.n }}</strong><em>{{ competency.t || '-' }}</em>
              </button>
              <div v-if="!filteredCatalogCompetencies.length" class="empty-inline">ไม่พบสมรรถนะที่ค้นหา</div>
            </div>
          </section>

          <section class="form-section catalog-section">
            <div class="section-title">ข้อมูลหลักสูตร</div>
            <div v-if="catalogPrimaryError" class="form-alert">{{ catalogPrimaryError }}</div>
            <div class="form-grid catalog-detail-grid">
              <div class="fg">
                <label class="lbl">{{ isCatalogELearning ? 'รหัสหลักสูตร/บทเรียน' : 'รหัสหลักสูตร' }}</label>
                <input v-model="catalogForm.code" class="inp" :class="{ invalid: catalogErrors.code }" :required="!isCatalogELearning" :placeholder="isCatalogELearning ? 'เช่น OCSC014 , ศูนย์นวัตกรรมการเรียนการสอน' : 'เช่น TN001'" @input="clearCatalogError('code')" />
                <div v-if="catalogErrors.code" class="field-error">{{ catalogErrors.code }}</div>
              </div>
              <div class="fg">
                <label class="lbl">{{ isCatalogELearning ? 'ชื่อหลักสูตร/บทเรียน' : 'ชื่อหลักสูตร' }}</label>
                <input v-model="catalogForm.name" class="inp" :class="{ invalid: catalogErrors.name }" required placeholder="ชื่อหลักสูตร" @input="clearCatalogError('name')" />
                <div v-if="catalogErrors.name" class="field-error">{{ catalogErrors.name }}</div>
              </div>
              <div v-if="isCatalogELearning" class="fg">
                <label class="lbl">แหล่งหลักสูตร</label>
                <select v-model="catalogForm.sourceType" class="sel"><option value="internal">ภายในมหาวิทยาลัย</option><option value="external">ภายนอกมหาวิทยาลัย</option></select>
              </div>
              <div class="fg">
                <label class="lbl">{{ isCatalogELearning ? 'ชั่วโมงเรียนรู้' : 'จำนวนชั่วโมง' }}</label>
                <input v-model="catalogForm.hours" class="inp" min="0" step="0.5" type="number" placeholder="เช่น 6" />
              </div>
              <div v-if="isCatalogELearning" class="fg">
                <label class="lbl">ค่าใช้จ่าย (บาท)</label>
                <input v-model="catalogForm.cost" class="inp" min="0" step="0.01" type="number" placeholder="0" />
              </div>
              <div v-if="isCatalogELearning" class="fg span-2 catalog-level-field">
                <div class="catalog-level-head">
                  <div>
                    <label class="lbl">ระดับความคาดหวังที่เหมาะสม</label>
                  </div>
                  <button class="count-pill ghost" type="button" @click="selectAllExpectedLevels">{{ catalogForm.expectedLevels.length === levelOptions.length ? 'เลือกครบทุกระดับ' : 'เลือกทุกระดับ' }}</button>
                </div>
                <div class="level-grid compact-level-grid">
                  <button v-for="level in levelOptions" :key="level.value" class="level-option" :class="{ selected: catalogForm.expectedLevels.includes(level.value) }" type="button" @click="toggleExpectedLevel(level.value)"><span class="level-dot"></span><strong>{{ level.label }}</strong></button>
                </div>
                <div v-if="catalogErrors.expected_levels" class="field-error">{{ catalogErrors.expected_levels }}</div>
              </div>
              <div class="fg span-2">
                <label class="lbl">คำอธิบายหลักสูตร</label>
                <textarea v-model="catalogForm.description" class="ta" rows="4" placeholder="รายละเอียด เนื้อหา และวัตถุประสงค์ของการเรียนรู้"></textarea>
              </div>
            </div>
          </section>

          <label class="active-toggle catalog-status-toggle">
            <input v-model="catalogForm.isActive" type="checkbox" />
            <span>
              <strong>เปิดใช้งานหลักสูตรนี้</strong>
              <small>เมื่อเปิด ระบบจะนำไปแสดงเป็นหลักสูตรแนะนำให้บุคลากรที่มีคะแนนตกเกณฑ์</small>
            </span>
          </label>
        </div>
        <div class="modal-foot">
          <button class="btn btn-s" type="button" @click="catalogModalOpen = false">ยกเลิก</button>
          <button class="btn btn-p" type="submit">{{ catalogMode === 'edit' ? 'บันทึกการแก้ไข' : 'เพิ่มหลักสูตร' }}</button>
        </div>
      </form>
    </div>

    <div v-if="previewPickerOpen" class="modal preview-layer" @click.self="previewPickerOpen = false">
      <div class="modal-box compact">
        <div class="modal-head">
          <div>
            <div class="ct">เลือกตัวอย่างแบบฟอร์ม</div>
            <div class="cs">แบบฟอร์ม IDP ทั้งหมด {{ activePreviewOptions.length }} รายการ</div>
          </div>
          <button class="modal-close" type="button" @click="previewPickerOpen = false">×</button>
        </div>
        <div class="modal-body">
          <button
            v-for="(option, optionIndex) in activePreviewOptions"
            :key="option.value"
            class="preview-option"
            type="button"
            @click="openFormPreview(option.value)"
          >
            <span class="preview-option-number">{{ optionIndex + 1 }}</span>
            <strong>{{ option.label }}</strong>
          </button>
        </div>
      </div>
    </div>

    <div v-if="previewForm" class="modal preview-layer" @click.self="previewFormCode = ''">
      <div class="modal-box preview-modal">
        <div class="modal-head">
          <div>
            <div class="ct">ตัวอย่างแบบฟอร์ม</div>
            <div class="cs">{{ previewForm.title }}</div>
          </div>
          <button class="modal-close" type="button" @click="previewFormCode = ''">×</button>
        </div>
        <div class="modal-body">
          <section class="preview-paper">
            <div class="preview-title-band">
              <div>
                <strong>{{ previewForm.title }}</strong>
              </div>
            </div>

            <div class="preview-block readonly">
              <header>
                <h4>ข้อมูลผู้รับการพัฒนา</h4>
                <span>ดึงจากระบบ</span>
              </header>
              <div class="preview-meta-grid">
                <label><span>ชื่อผู้รับการพัฒนา</span><input disabled /></label>
                <label><span>รหัสพนักงาน</span><input disabled /></label>
                <label><span>ตำแหน่ง</span><input disabled /></label>
              </div>
            </div>

            <div class="preview-block readonly">
              <header>
                <h4>ข้อมูลประกอบการวางแผน</h4>
                <span>ดึงจาก competency gap</span>
              </header>
              <div class="preview-meta-grid three">
                <label><span>รหัส</span><input disabled /></label>
                <label><span>ชื่อสมรรถนะ</span><input disabled /></label>
                <label><span>Gap</span><input disabled /></label>
              </div>
            </div>

            <div v-if="previewForm.detailFields.length" class="preview-block">
              <header>
                <h4>{{ previewForm.detailTitle }}</h4>
                <span>พนักงานกรอกตอนทำแผน</span>
              </header>
              <div v-if="['4', '5', '6', '7', '8'].includes(previewForm.number)" class="preview-meta-grid">
                <label>
                  <span>{{ previewForm.number === '7' ? 'ผู้อำนวยการ/ผู้นำกิจกรรม' : previewForm.number === '8' ? 'ผู้ให้ข้อมูล' : 'ผู้สอนงาน' }}</span>
                  <select v-model="previewCoachType">
                    <option value="">เลือกจากรายการ</option>
                    <option value="ผู้บังคับบัญชา">ผู้บังคับบัญชา</option>
                    <option value="ผู้เชี่ยวชาญ">ผู้เชี่ยวชาญ</option>
                  </select>
                </label>
                <label v-if="previewCoachType === 'ผู้เชี่ยวชาญ'">
                  <span>ชื่อผู้เชี่ยวชาญ</span>
                  <input placeholder="กรอกชื่อผู้เชี่ยวชาญ" />
                </label>
              </div>
              <div v-else class="preview-meta-grid" :class="{ three: previewForm.detailFields.length === 3 }">
                <label
                  v-for="field in previewForm.detailFields"
                  :key="field.key"
                  :class="{ wide: field.type === 'area' }"
                >
                  <span>{{ field.label }}</span>
                  <textarea v-if="field.type === 'area'" disabled rows="3" />
                  <input v-else-if="field.type === 'date'" disabled type="date" />
                  <select v-else-if="['choice', 'supervisor-chain'].includes(field.type)" disabled>
                    <option>เลือกจากรายการ</option>
                  </select>
                  <input v-else disabled />
                </label>
              </div>
            </div>

            <div v-if="previewForm.rowFields.length" class="preview-block">
              <header>
                <h4>{{ previewForm.rowTitle }}</h4>
                <button type="button" disabled>
                  {{ previewForm.number === '3' ? '+ เพิ่มงานที่ได้รับมอบหมาย' : previewForm.number === '4' ? '+ เพิ่มหัวข้อฝึกปฏิบัติ' : previewForm.number === '5' ? '+ เพิ่มหัวข้อการสอนงาน' : previewForm.number === '6' ? '+ เพิ่มหัวข้อที่ต้องการพัฒนา' : previewForm.number === '7' ? '+ เพิ่มกิจกรรม' : previewForm.number === '8' ? '+ เพิ่มหัวข้อการพัฒนา' : previewForm.number === '9' ? '+ เพิ่มรายการศึกษาดูงาน' : previewForm.number === '10' ? '+ เพิ่มหลักสูตรอบรม' : '+ เพิ่มแถว' }}
                </button>
              </header>
              <div v-if="previewForm.number === '3'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>งานที่ได้รับมอบหมาย 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้องานโครงการ/งานพิเศษที่ได้รับมอบหมาย</span><input disabled /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><input disabled /></label>
                    <label class="wide"><span>รายละเอียด</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline group-activity-timeline wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>ระยะเวลาดำเนินการ</strong>
                        <span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '4'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>หัวข้อฝึกปฏิบัติ 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้อทักษะ/ประเด็นการฝึกปฏิบัติงาน</span><textarea disabled rows="3" /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="3" /></label>
                    <label class="wide"><span>วิธีการ</span><textarea disabled rows="2" /></label>
                    <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>ระยะเวลาการฝึกปฏิบัติงาน</strong>
                        <span>กำหนดช่วงเวลาและจำนวนชั่วโมงรวม</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                      <label><span>จำนวนชั่วโมง</span><input disabled type="number" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '5'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>หัวข้อการสอนงาน 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้อทักษะ/ประเด็นการสอนงาน</span><textarea disabled rows="3" /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="3" /></label>
                    <label class="wide"><span>วิธีการ</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-approaches wide">
                      <div class="preview-coaching-heading">
                        <div><span>แนวทางการสอนงาน</span><small>เลือกได้มากกว่า 1 แนวทาง</small></div>
                        <button type="button" @click="previewCoachingHelpOpen = true">อ่านเพิ่มเติม</button>
                      </div>
                      <div class="preview-coaching-options">
                        <button
                          v-for="approach in [{ code: 'A', title: 'ส่งเสริม' }, { code: 'B', title: 'สร้างสรรค์' }, { code: 'C', title: 'กระตุ้น' }, { code: 'D', title: 'แก้ไขปัญหา' }]"
                          :key="approach.code"
                          disabled
                          type="button"
                        ><strong>{{ approach.code }}</strong><span>{{ approach.title }}</span></button>
                      </div>
                    </div>
                    <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline coaching-timeline-four wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>ระยะเวลาการพัฒนา</strong>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                      <label><span>จำนวนครั้ง</span><input disabled type="number" /></label>
                      <label><span>ระยะเวลาต่อครั้ง</span><input disabled placeholder="เช่น ครั้งละ 1 ชั่วโมง" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '6'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>หัวข้อที่ต้องการพัฒนา 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea disabled rows="3" /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="3" /></label>
                    <label class="wide"><span>เทคนิค</span><textarea disabled rows="2" /></label>
                    <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline coaching-timeline-four wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>ระยะเวลาการพัฒนา</strong>
                        <span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                      <label><span>จำนวนครั้ง</span><input disabled type="number" /></label>
                      <label><span>ระยะเวลาต่อครั้ง</span><input disabled placeholder="เช่น ครั้งละ 1 ชั่วโมง" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '7'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>กิจกรรม 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea disabled rows="1" /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="1" /></label>
                    <label><span>เทคนิค</span><textarea disabled rows="2" /></label>
                    <label><span>เครื่องมือและเงื่อนไขการประเมิน</span><textarea disabled rows="2" /></label>
                    <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline group-activity-timeline wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>กำหนดการจัดกิจกรรม</strong>
                        <span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '8'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>หัวข้อการพัฒนา 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea disabled rows="1" /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="1" /></label>
                    <label class="wide"><span>แหล่งข้อมูลป้อนกลับ</span><textarea disabled rows="2" /></label>
                    <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline coaching-timeline-four wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>ระยะการพัฒนา</strong>
                        <span>กำหนดช่วงเวลา จำนวนครั้ง และระยะเวลาที่ใช้ต่อครั้ง</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                      <label><span>จำนวนครั้ง</span><input disabled type="number" /></label>
                      <label><span>ระยะเวลาต่อครั้ง</span><input disabled placeholder="เช่น ครั้งละ 1 ชั่วโมง" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '9'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>รายการศึกษาดูงาน 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid">
                    <label><span>หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา</span><textarea disabled rows="1" /></label>
                    <label><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="1" /></label>
                    <label><span>สถานที่/แหล่งศึกษาดูงาน</span><textarea disabled rows="2" /></label>
                    <label><span>เครื่องมือและเงื่อนไขการประเมิน</span><textarea disabled rows="2" /></label>
                    <label class="wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline group-activity-timeline wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>กำหนดการจัดกิจกรรม</strong>
                        <span>กำหนดวันที่เริ่มต้นและวันที่สิ้นสุด</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else-if="previewForm.number === '10'" class="preview-project-list">
                <article class="preview-project-item">
                  <div class="preview-project-head"><strong>หลักสูตรอบรม 1</strong><button disabled type="button">ลบ</button></div>
                  <div class="preview-project-grid training-form-grid">
                    <label class="training-meta"><span>รูปแบบการอบรม</span><select disabled><option>เลือกจากรายการ</option></select></label>
                    <label class="training-meta"><span>รหัสหลักสูตร</span><input disabled /></label>
                    <label class="training-meta"><span>ชื่อหลักสูตร</span><input disabled /></label>
                    <label class="training-wide"><span>เป้าหมายในการพัฒนา</span><textarea disabled rows="3" /></label>
                    <label class="training-wide"><span>รายละเอียดเพิ่มเติม (ถ้ามี)</span><textarea disabled rows="2" /></label>
                    <div class="preview-coaching-timeline training-summary training-wide">
                      <div class="preview-coaching-timeline-heading">
                        <strong>ระยะเวลาการอบรมและค่าใช้จ่าย</strong>
                        <span>กำหนดช่วงเวลา จำนวนชั่วโมง และค่าใช้จ่าย</span>
                      </div>
                      <label><span>วันที่เริ่มต้น</span><input disabled type="date" /></label>
                      <label><span>วันที่สิ้นสุด</span><input disabled type="date" /></label>
                      <label><span>จำนวนชั่วโมง</span><input disabled type="number" /></label>
                      <label><span>ค่าใช้จ่าย (บาท)</span><input disabled type="number" /></label>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else class="preview-table-wrap">
                <table class="preview-table">
                  <thead>
                    <tr>
                      <th
                        v-if="previewForm.number !== '9'"
                        class="row-number"
                        :rowspan="hasGroupedRowFields(previewForm.rowFields) ? 2 : 1"
                      >ที่</th>
                      <th
                        v-for="cell in rowHeaderCells(previewForm.rowFields)"
                        :key="cell.key"
                        :colspan="cell.colspan"
                        :rowspan="cell.rowspan"
                      >
                        {{ cell.label }}
                        <small v-if="cell.owner">{{ cell.owner }}</small>
                      </th>
                    </tr>
                    <tr v-if="hasGroupedRowFields(previewForm.rowFields)">
                      <th
                        v-for="(field, fieldIndex) in rowSubHeaderFields(previewForm.rowFields)"
                        :key="`preview-sub-${field.key}-${fieldIndex}`"
                      >
                        {{ field.label }}
                        <small v-if="field.owner">{{ field.owner }}</small>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, rowIndex) in previewRows" :key="row">
                      <td v-if="previewForm.number !== '9'" class="row-number">{{ rowIndex + 1 }}</td>
                      <td
                        v-for="(field, fieldIndex) in previewForm.rowFields"
                        :key="`${row}-${field.key}-${fieldIndex}`"
                      >
                        <div v-if="field.type === 'checkbox-choice'" class="preview-checkboxes">
                          <label v-for="choice in field.choices" :key="choice">
                            <input disabled type="checkbox" />
                            <span>{{ choice }}</span>
                          </label>
                        </div>
                        <button v-else-if="field.type === 'multi-checkbox'" class="preview-check" disabled type="button"></button>
                        <div v-else-if="previewForm.number === '9' && fieldIndex === 0" class="preview-fixed-topic">
                          <strong>{{ row }}</strong>
                          <label v-if="rowIndex === 0">
                            <span>ระบุ</span>
                            <textarea disabled rows="2" />
                          </label>
                        </div>
                        <textarea v-else-if="field.type === 'area'" disabled rows="3" />
                        <input v-else disabled :placeholder="field.placeholder || ''" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="!['3', '4', '5', '6', '7', '8', '9', '10'].includes(previewForm.number)" class="preview-block result">
              <header>
                <h4>ผลการดำเนินการ</h4>
                <span>ใช้หลังแผนอนุมัติแล้ว</span>
              </header>
              <div class="preview-meta-grid three">
                <label><span>เป้าหมายในการพัฒนา (Behavior Result)</span><textarea disabled rows="3" /></label>
                <label><span>ตัวชี้วัดผลสำเร็จของการพัฒนา</span><textarea disabled rows="3" /></label>
                <label><span>ผลลัพธ์จากการพัฒนา</span><textarea disabled rows="3" /></label>
              </div>
            </div>

            <div v-if="!['3', '4', '5', '6', '7', '8', '9', '10'].includes(previewForm.number)" class="preview-block assessment">
              <header>
                <h4>การติดตามประเมินผล / คำรับรอง</h4>
                <span>หัวหน้าใช้ตอนติดตามผล</span>
              </header>
              <div class="preview-meta-grid">
                <label><span>ผลดำเนินการ</span><select disabled><option>เลือกจากรายการ</option></select></label>
                <label><span>ผลการพัฒนา</span><select disabled><option>เลือกจากรายการ</option></select></label>
                <label class="wide"><span>เหตุผล / หมายเหตุ</span><textarea disabled rows="2" /></label>
              </div>
            </div>
          </section>
        </div>
        <div class="modal-foot">
          <button class="btn btn-p" type="button" @click="previewFormCode = ''">ปิด</button>
        </div>
      </div>
    </div>

    <div v-if="previewCoachingHelpOpen" class="modal preview-layer coaching-help-layer" @click.self="previewCoachingHelpOpen = false">
      <div class="modal-box coaching-help-modal">
        <div class="modal-head">
          <div>
            <div class="ct">คำอธิบายแนวทางการสอนงาน</div>
            <div class="cs">ใช้ประกอบการเลือกแนวทาง A–D ในแบบฟอร์มที่ 5</div>
          </div>
          <button class="modal-close" type="button" @click="previewCoachingHelpOpen = false">×</button>
        </div>
        <div class="modal-body coaching-help-body">
          <article v-for="approach in coachingApproachDescriptions" :key="approach.code" class="coaching-help-card">
            <header>
              <b>{{ approach.code }}</b>
              <div><strong>{{ approach.title }}</strong><span>{{ approach.summary }}</span></div>
            </header>
            <ul><li v-for="item in approach.items" :key="item">{{ item.replace(/^[-▪]\s*/, '') }}</li></ul>
          </article>
        </div>
        <div class="modal-foot"><button class="btn btn-p" type="button" @click="previewCoachingHelpOpen = false">ปิด</button></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-idp-tools { display: grid; gap: 16px; }
.page-head, .panel-head, .row-actions, .tool-actions, .modal-head, .modal-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.admin-idp-tools .btn.btn-p,
.modal .btn.btn-p {
  border: 1px solid #2563eb;
  background: #2563eb;
  color: #fff;
  box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18);
}
.admin-idp-tools .btn.btn-p:hover,
.modal .btn.btn-p:hover {
  border-color: #1d4ed8;
  background: #1d4ed8;
}
.formal-stack { display: grid; gap: 16px; }
.panel-actions { display: flex; align-items: center; gap: 10px; }
.panel-count { display: inline-flex; align-items: center; min-height: 34px; border: 1px solid #dbe7f7; border-radius: 999px; background: #f8fbff; color: var(--text3); font-size: 12px; font-weight: 900; padding: 4px 12px; white-space: nowrap; }
.focus-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.focus-tab { min-height: 74px; display: flex; align-items: center; gap: 12px; border: 1px solid var(--border); border-radius: 10px; background: #fff; color: var(--text2); cursor: pointer; padding: 14px 16px; text-align: left; box-shadow: var(--sh); }
.focus-tab.active { border-color: #9fc0fb; color: var(--navy); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08), var(--sh); }
.focus-tab span { font-size: 14px; font-weight: 900; }
.focus-panel { overflow: hidden; }
.panel-head { padding: 16px 18px; border-bottom: 1px solid var(--border); background: #fff; }
.tool-list { display: grid; }
.tool-row { display: grid; grid-template-columns: 58px minmax(0, 1fr) auto; align-items: center; gap: 14px; min-height: 64px; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.tool-row:last-child { border-bottom: 0; }
.tool-no { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: var(--bg); color: var(--text3); font-size: 12px; font-weight: 900; }
.tool-title { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 4px 10px; color: var(--text); font-size: 14px; font-weight: 800; line-height: 1.45; }
.tool-title strong { min-width: 0; }
.tool-title small { grid-column: 2; color: var(--text3); font-size: 11px; font-weight: 800; }
.formal-delivery-panel { overflow: hidden; background: #fff; }
.formal-delivery-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 18px; border-bottom: 1px solid var(--border); }
.formal-delivery-list { display: grid; }
.formal-delivery-item { display: grid; grid-template-columns: 44px minmax(220px, 0.75fr) minmax(260px, 1fr) auto; align-items: center; gap: 14px; min-height: 76px; padding: 14px 18px; border-bottom: 1px solid var(--border); background: #fff; }
.formal-delivery-item:last-child { border-bottom: 0; }
.formal-delivery-mark { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 10px; background: #eef4ff; color: #2563eb; font-size: 13px; font-weight: 900; }
.formal-delivery-copy, .formal-delivery-form { min-width: 0; display: grid; gap: 3px; }
.formal-delivery-copy strong, .formal-delivery-form strong { color: var(--text); font-size: 13px; font-weight: 900; line-height: 1.35; }
.formal-delivery-copy span, .formal-delivery-form span { color: var(--text3); font-size: 11px; font-weight: 800; }
.formal-delivery-form { min-height: 48px; align-content: center; border: 1px solid var(--border); border-radius: 8px; background: #f8fafc; padding: 8px 12px; }
.delivery-code-title { color: var(--text); font-size: 14px; font-weight: 900; }
.delivery-code-sub { margin-top: 3px; color: var(--text3); font-size: 12px; font-weight: 700; line-height: 1.45; }
.delivery-type-settings { display: grid; gap: 10px; }
.delivery-type-setting { display: grid; gap: 10px; border: 1px solid var(--border); border-radius: 10px; background: #fbfdff; padding: 12px; }
.delivery-type-setting.readonly { background: #fff; padding: 14px; }
.delivery-setting-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.delivery-setting-head strong,
.delivery-setting-head span { display: block; }
.delivery-setting-head strong { color: var(--text); font-size: 13px; font-weight: 900; line-height: 1.35; }
.delivery-setting-head span { margin-top: 2px; color: var(--text3); font-size: 11px; font-weight: 800; }
.delivery-setting-head em { min-width: 46px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #dbe7f7; border-radius: 8px; background: #fff; color: var(--text); font-size: 15px; font-style: normal; font-weight: 900; }
.delivery-setting-summary { display: grid; grid-template-columns: 94px minmax(260px, 1fr) auto; gap: 10px; align-items: stretch; }
.delivery-summary-code,
.delivery-summary-form { min-width: 0; display: grid; align-content: center; gap: 4px; min-height: 44px; border: 1px solid #dbe3ec; border-radius: 8px; background: #f8fafc; padding: 8px 12px; }
.delivery-summary-code { text-align: center; }
.delivery-summary-code span,
.delivery-summary-form span { color: var(--text3); font-size: 10px; font-weight: 900; line-height: 1; }
.delivery-summary-code strong,
.delivery-summary-form strong { overflow: hidden; color: var(--text); font-size: 13px; font-weight: 900; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.delivery-summary-code strong { font-size: 18px; }
.delivery-setting-controls { display: grid; grid-template-columns: 94px minmax(260px, 1fr) auto; gap: 10px; align-items: end; }
.delivery-code-field,
.delivery-form-field { min-width: 0; display: grid; gap: 6px; color: var(--text2); font-size: 11px; font-weight: 900; }
.delivery-code-input { width: 100%; min-height: 40px; text-align: center; font-size: 15px; font-weight: 900; letter-spacing: 0; }
.delivery-form-select { width: 100%; min-height: 40px; border-radius: 8px; font-size: 12px; }
.delivery-preview-btn { min-height: 40px; white-space: nowrap; }
.delivery-save-row { display: flex; justify-content: flex-end; gap: 10px; padding-top: 2px; }
.catalog-card { background: #fff; }
.catalog-type-tabs { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) auto; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--border); background: #fbfdff; }
.catalog-type-tabs > button { min-height: 62px; display: flex; align-items: center; justify-content: space-between; gap: 14px; border: 1px solid var(--border); border-radius: 9px; background: #fff; color: var(--text2); cursor: pointer; padding: 10px 14px; text-align: left; }
.catalog-type-tabs > button:hover { border-color: #b9c9df; background: #f8fbff; }
.catalog-type-tabs > button.active { border-color: #8eb4f7; background: #eff6ff; color: var(--blue); box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.06); }
.catalog-type-tabs button span { display: grid; gap: 2px; }
.catalog-type-tabs button strong { color: inherit; font-size: 13px; font-weight: 900; }
.catalog-type-tabs button small { color: var(--text3); font-size: 11px; font-weight: 700; }
.catalog-type-tabs button em { min-width: 30px; height: 30px; display: grid; place-items: center; border-radius: 999px; background: #f1f5f9; color: var(--text2); font-size: 12px; font-style: normal; font-weight: 900; }
.catalog-type-tabs button.active em { background: #dbeafe; color: #1d4ed8; }
.catalog-type-tabs > .catalog-form-preview { min-width: 190px; justify-content: center; color: var(--text2); font-weight: 900; }
.catalog-card-head { display: flex; align-items: center; gap: 12px; min-height: 56px; padding: 14px 18px; border-bottom: 1px solid var(--border); }
.catalog-filter-bar { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(130px, 0.3fr) auto; gap: 10px; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--border); background: #fff; }
.catalog-filter-search, .catalog-filter-select { min-height: 40px; border-radius: 8px; font-size: 13px; }
.catalog-filter-select.compact { min-width: 124px; }
.table-wrap { max-height: 560px; overflow: auto; }
.tbl { width: 100%; border-collapse: collapse; min-width: 760px; }
.catalog-table { min-width: 860px; }
.tbl th, .tbl td { padding: 13px 18px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
.tbl th { position: sticky; top: 0; z-index: 2; color: var(--text3); font-size: 12px; font-weight: 900; background: #fbfdff; }
.col-order { width: 72px; }
.competency-badge { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; border: 1px solid #dbe7f7; background: #f8fbff; color: var(--text2); font-size: 12px; font-weight: 900; padding: 4px 12px; white-space: nowrap; }
.catalog-format-cell { display: grid; gap: 5px; align-items: start; }
.catalog-code-badge { display: inline-flex; align-items: center; width: fit-content; min-height: 24px; border-radius: 8px; background: #f8fafc; color: var(--text); font-size: 12px; font-weight: 900; letter-spacing: 0; padding: 3px 9px; }
.catalog-delivery-label { color: var(--text2); font-size: 12px; font-weight: 800; line-height: 1.35; }
.catalog-form-label { margin-top: 4px; color: var(--text3); font-size: 11px; font-weight: 800; line-height: 1.4; }
.catalog-name-button { display: grid; gap: 2px; border: 0; background: transparent; color: var(--text); cursor: pointer; padding: 0; text-align: left; }
.catalog-name-button span { font-size: 13px; font-weight: 900; line-height: 1.4; }
.catalog-name-button em { color: var(--blue); font-size: 10px; font-style: normal; font-weight: 800; }
.catalog-description { max-width: 72ch; margin-top: 10px; border: 1px solid #dce5f0; border-radius: 8px; background: #f8fafc; padding: 12px 14px; }
.catalog-description span { display: block; margin-bottom: 5px; color: var(--text3); font-size: 10px; font-weight: 900; }
.catalog-description p { margin: 0; color: var(--text2); font-size: 12px; font-weight: 600; line-height: 1.7; white-space: pre-line; }
.catalog-levels { color: var(--text2); font-size: 12px; font-weight: 800; white-space: nowrap; }
.status-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.status-pill.active { border: 1px solid #bbf7d0; background: #dcfce7; color: #15803d; }
.status-pill.inactive { border: 1px solid #fecaca; background: #fee2e2; color: #b91c1c; }
.empty-box { min-height: 116px; display: grid; place-items: center; color: var(--text3); font-size: 13px; font-weight: 800; background: var(--bg); }
.table-empty-cell { min-height: 118px; display: flex; align-items: center; gap: 14px; background: var(--bg); padding: 20px; }
.tbl td:has(.table-empty-cell) { padding: 0; }
.ml-auto { margin-left: auto; }
.fs12 { font-size: 12px; }
.fs13 { font-size: 13px; }
.fs14 { font-size: 14px; }
.mb0 { margin-bottom: 0; }
.danger { color: #b42318 !important; }
.modal { position: fixed; inset: 0; z-index: 900; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 28px; background: rgba(15, 23, 42, 0.42); }
.modal.preview-layer { z-index: 950; }
.modal-box { width: min(1180px, 100%); border-radius: 12px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); overflow: hidden; }
.modal-box.compact { width: min(620px, 100%); }
.catalog-modal-box { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; max-height: calc(100vh - 56px); overflow: hidden; }
.catalog-modal-box > .modal-body { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
.preview-modal {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: min(1680px, calc(100vw - 24px));
  max-height: calc(100vh - 24px);
  overflow: hidden;
}
.preview-modal > .modal-body { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
.modal-head, .modal-foot { padding: 16px 20px; border-bottom: 1px solid var(--border); }
.modal-foot { border-top: 1px solid var(--border); border-bottom: 0; justify-content: flex-end; }
.modal-body { display: grid; gap: 14px; padding: 18px 20px; }
.modal-close { width: 40px; height: 40px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text3); font-size: 26px; cursor: pointer; }
.form-section { border: 1px solid var(--border); border-radius: 10px; background: #fff; padding: 14px; }
.form-select-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; }
.preview-paper { display: grid; gap: 14px; border: 1px solid var(--border); border-radius: 10px; background: #eef3f7; padding: 16px; }
.preview-title-band { display: flex; align-items: center; justify-content: space-between; gap: 14px; border: 1px solid #f2d3c4; border-radius: 8px; background: #fff1e8; padding: 14px 16px; }
.preview-title-band strong, .preview-title-band span { display: block; }
.preview-title-band strong { color: var(--text); font-size: 17px; font-weight: 900; }
.preview-title-band span { margin-top: 4px; color: var(--text3); font-size: 12px; font-weight: 900; }
.preview-title-band label { min-width: 260px; display: grid; gap: 6px; color: #9a4d00; font-size: 12px; font-weight: 900; }
.preview-block { overflow: hidden; border: 1px solid var(--border); border-radius: 10px; background: #fff; }
.preview-block header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border-bottom: 1px solid var(--border); background: #f8fafc; }
.preview-block h4 { margin: 0; color: var(--text); font-size: 13px; font-weight: 900; }
.preview-block header span { color: var(--text3); font-size: 11px; font-weight: 900; }
.preview-block header button { min-height: 34px; border: 1px solid var(--color-primary-border); border-radius: 7px; background: var(--color-primary-soft); color: var(--color-primary); font-size: 12px; font-weight: 900; padding: 6px 10px; }
.preview-approval-route { display: flex; align-items: center; gap: 10px; padding: 14px; }
.preview-approval-step { display: flex; align-items: center; gap: 10px; min-width: 260px; border: 1px solid #cfe3dc; border-radius: 8px; background: #f8fcfa; padding: 10px 12px; }
.preview-approval-step > span { display: grid; place-items: center; flex: 0 0 30px; width: 30px; height: 30px; border-radius: 50%; background: var(--color-primary); color: #fff; font-size: 12px; font-weight: 900; }
.preview-approval-step strong, .preview-approval-step small { display: block; }
.preview-approval-step strong { color: var(--text); font-size: 12px; }
.preview-approval-step small { margin-top: 3px; color: var(--text3); font-size: 10px; font-weight: 800; }
.preview-approval-arrow { color: var(--text3); font-size: 18px; font-weight: 900; }
.preview-approval-notice { margin: 0; border-top: 1px solid var(--border); background: #fffaf0; padding: 9px 14px; color: #9a4d00; font-size: 11px; font-weight: 900; }
.preview-meta-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.preview-meta-grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.preview-block > .preview-meta-grid { padding: 12px; }
.preview-meta-grid label { min-width: 0; display: grid; gap: 6px; color: var(--text2); font-size: 12px; font-weight: 900; }
.preview-meta-grid label.wide { grid-column: span 2; }
.preview-title-band input,
.preview-meta-grid input,
.preview-meta-grid select,
.preview-meta-grid textarea,
.preview-table input,
.preview-table select,
.preview-table textarea { width: 100%; min-height: 38px; border: 1px solid #cfd8e3; border-radius: 7px; background: #fff; color: var(--text); font: inherit; padding: 8px 10px; }
.preview-meta-grid textarea,
.preview-table textarea { resize: vertical; }
.preview-meta-grid input:disabled,
.preview-meta-grid select:disabled,
.preview-meta-grid textarea:disabled,
.preview-title-band input:disabled,
.preview-table input:disabled,
.preview-table textarea:disabled { opacity: 1; background: #fff; }
.preview-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
.preview-table { width: 100%; min-width: 1180px; border-collapse: collapse; }
.preview-table th, .preview-table td { border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); padding: 10px; text-align: left; vertical-align: top; }
.preview-table th:last-child, .preview-table td:last-child { border-right: 0; }
.preview-table tr:last-child td { border-bottom: 0; }
.preview-table th { background: #f8fafc; color: var(--text2); font-size: 11px; font-weight: 900; }
.preview-table th small { display: block; margin-top: 3px; color: #9a4d00; font-size: 10px; }
.preview-table td { height: 58px; color: var(--text); font-size: 12px; font-weight: 800; }
.preview-table .row-number { width: 44px; text-align: center; }
.preview-project-list { padding: 14px; }
.preview-project-item { overflow: hidden; border: 1px solid var(--border); border-radius: 9px; background: #fbfdff; }
.preview-project-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--border); background: #f8fafc; padding: 10px 12px; }
.preview-project-head strong { color: var(--text); font-size: 13px; }
.preview-project-head button { border: 1px solid #fecaca; border-radius: 6px; background: #fff; color: #b42318; padding: 6px 9px; font-size: 11px; font-weight: 900; }
.preview-project-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
.preview-project-grid.training-form-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); align-items: start; }
.training-form-grid > .training-meta { grid-column: span 2; }
.training-form-grid > .training-wide { grid-column: 1 / -1; }
.preview-coaching-timeline.training-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.preview-project-grid label { display: grid; gap: 6px; color: var(--text2); font-size: 12px; font-weight: 900; }
.preview-project-grid > .wide { grid-column: 1 / -1; }
.preview-project-grid input, .preview-project-grid textarea, .preview-project-grid select { width: 100%; min-height: 40px; border: 1px solid #cfd8e3; border-radius: 7px; background-color: #fff; padding: 9px 10px; font: inherit; color: var(--text); }
.preview-project-grid select { appearance: none; padding-right: 38px; background-image: linear-gradient(45deg, transparent 50%, #7a8798 50%), linear-gradient(135deg, #7a8798 50%, transparent 50%); background-position: calc(100% - 18px) 50%, calc(100% - 12px) 50%; background-repeat: no-repeat; background-size: 6px 6px, 6px 6px; }
.preview-coaching-approaches { display: grid; gap: 12px; border: 1px solid #dce9e5; border-radius: 9px; background: #f8fcfb; padding: 12px; color: var(--text2); font-size: 12px; font-weight: 900; }
.preview-coaching-approaches > div { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.preview-coaching-approaches > .preview-coaching-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.preview-coaching-heading > div { display: grid; gap: 2px; }
.preview-coaching-heading small { color: var(--text3); font-size: 10px; }
.preview-coaching-heading button { min-height: 28px; height: 28px; border: 1px solid var(--color-primary-border); border-radius: 999px; background: #fff; color: var(--color-primary); padding: 5px 11px; }
.preview-coaching-approaches .preview-coaching-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.preview-coaching-options button { display: flex; align-items: center; justify-content: flex-start; gap: 9px; min-height: 48px; border: 1px solid #cfd8e3; border-radius: 8px; background: #fff; color: var(--text2); padding: 9px 10px; font-weight: 900; opacity: 1; }
.preview-coaching-options strong { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 7px; background: #edf1f5; color: #344054; }
.preview-coaching-options span { font-size: 11px; }
.preview-coaching-timeline { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; border: 1px solid #d8e2e8; border-radius: 9px; background: #fff; padding: 12px; }
.preview-coaching-timeline.coaching-timeline-four { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.preview-coaching-timeline.group-activity-timeline { grid-template-columns: repeat(2, minmax(0, 1fr)); align-self: stretch; }
.preview-coaching-timeline-heading { grid-column: 1 / -1; display: flex; align-items: baseline; justify-content: space-between; gap: 12px; border-bottom: 1px solid #e8edf1; padding: 0 2px 10px; }
.preview-coaching-timeline-heading strong { color: var(--text); font-size: 12px; }
.preview-coaching-timeline-heading span { color: var(--text3); font-size: 10px; }
.preview-coaching-timeline input { min-height: 42px; background: #fbfcfd; }
.modal.coaching-help-layer { z-index: 1000; align-items: center; overflow: hidden; padding: 12px; }
.coaching-help-modal { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; width: min(1180px, calc(100vw - 24px)); height: calc(100dvh - 24px); max-height: calc(100dvh - 24px); min-height: 0; overflow: hidden; }
.coaching-help-body { display: flex; flex-direction: column; gap: 16px; min-height: 0; overflow-x: hidden; overflow-y: scroll; overscroll-behavior: contain; background: #f4f7f9; padding: 18px; scrollbar-gutter: stable; -webkit-overflow-scrolling: touch; }
.coaching-help-card { flex: 0 0 auto; overflow: hidden; border: 1px solid #d8e2e8; border-radius: 10px; background: #fff; }
.coaching-help-card header { display: flex; align-items: flex-start; gap: 12px; border-bottom: 1px solid #e3e9ee; background: #f8fcfb; padding: 16px 18px; }
.coaching-help-card header b { display: grid; place-items: center; flex: 0 0 40px; height: 40px; border-radius: 9px; background: var(--color-primary); color: #fff; font-size: 15px; }
.coaching-help-card header div { display: grid; gap: 3px; }
.coaching-help-card header strong { color: var(--text); font-size: 16px; }
.coaching-help-card header span { color: var(--text3); font-size: 12px; line-height: 1.45; }
.coaching-help-card ul { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 30px; margin: 0; padding: 18px 28px 22px; color: var(--text2); font-size: 13px; line-height: 1.6; list-style: none; }
.coaching-help-card li { position: relative; margin: 0; padding-left: 20px; }
.coaching-help-card li::before { content: ''; position: absolute; top: .72em; left: 0; width: 8px; height: 8px; border: 2px solid #65ad9b; border-radius: 50%; background: #e8f7f2; transform: translateY(-50%); }
.preview-table textarea { min-width: 220px; min-height: 68px; }
.preview-checkboxes { display: grid; gap: 8px; min-width: 130px; }
.preview-checkboxes label { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.preview-checkboxes input { width: 15px; height: 15px; min-height: auto; }
.preview-check { width: 18px; height: 18px; border: 1px solid #cfd8e3; border-radius: 5px; background: #fff; }
.preview-fixed-topic { display: grid; gap: 10px; min-width: 260px; }
.preview-fixed-topic strong { font-weight: 900; line-height: 1.45; }
.preview-fixed-topic label { display: grid; gap: 4px; }
.preview-option { display: grid; grid-template-columns: 34px minmax(0, 1fr); align-items: center; gap: 12px; width: 100%; border: 1px solid var(--border); border-radius: 9px; background: #fff; color: var(--text); padding: 11px 14px; text-align: left; cursor: pointer; }
.preview-option:hover { border-color: #9fc0fb; background: #eff6ff; }
.preview-option strong { font-size: 13px; font-weight: 900; }
.preview-option span { color: var(--text3); font-size: 12px; font-weight: 800; }
.preview-option .preview-option-number { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 8px; background: #fff1eb; color: #cf4429; font-size: 13px; font-weight: 950; }
.catalog-section { padding: 18px; }
.catalog-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
.catalog-section-head.compact { align-items: center; margin-bottom: 12px; }
.section-title { margin-bottom: 12px; color: var(--text); font-size: 15px; font-weight: 900; }
.catalog-section .section-title { margin-bottom: 0; }
.section-title.small { font-size: 13px; }
.section-sub { margin-top: 3px; color: var(--text3); font-size: 12px; font-weight: 700; line-height: 1.5; }
.count-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 34px; border: 1px solid #cfe0f5; border-radius: 999px; background: #eff6ff; color: #2563eb; font-size: 12px; font-weight: 900; padding: 4px 12px; white-space: nowrap; }
.count-pill.ghost { cursor: pointer; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 16px; }
.readonly-field { min-height: 42px; display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; background: #f8fafc; color: var(--text); font-size: 13px; font-weight: 800; padding: 8px 12px; }
.inp.invalid, .sel.invalid { border-color: #fca5a5; background: #fff7f7; }
.form-alert { margin-bottom: 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 13px; font-weight: 900; padding: 10px 12px; }
.field-error { margin-top: 6px; color: #b91c1c; font-size: 12px; font-weight: 800; }
.field-hint { margin-top: 6px; color: var(--text3); font-size: 12px; font-weight: 800; }
.span-2 { grid-column: span 2; }
.catalog-search-input { min-height: 44px; margin-bottom: 12px; }
.competency-list { max-height: 210px; overflow-y: auto; display: grid; gap: 8px; padding-right: 4px; }
.competency-option { display: grid; grid-template-columns: 22px 74px minmax(0, 1fr) auto; gap: 10px; align-items: center; min-height: 52px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text); text-align: left; padding: 10px 12px; cursor: pointer; }
.competency-option::before { content: ""; width: 16px; height: 16px; border: 1px solid #d8e4f3; border-radius: 4px; background: #fff; }
.competency-option.selected { border-color: #9fc0fb; background: #eff6ff; }
.competency-option.selected::before { border-color: #2563eb; background: #2563eb; box-shadow: inset 0 0 0 3px #2563eb; }
.competency-option span { color: var(--text3); font-weight: 900; }
.competency-option strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.competency-option em { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; min-height: 24px; border-radius: 999px; background: #f1f5f9; color: var(--text2); font-size: 11px; font-style: normal; font-weight: 900; padding: 2px 8px; }
.empty-inline { min-height: 48px; display: grid; place-items: center; color: var(--text3); font-size: 12px; font-weight: 800; }
.level-picker { margin-top: 12px; border: 1px solid var(--border); border-radius: 10px; background: #fbfdff; padding: 14px; }
.level-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.level-option { min-height: 76px; display: grid; grid-template-columns: 22px 1fr; grid-template-rows: auto auto; align-content: center; column-gap: 10px; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text2); font-weight: 900; cursor: pointer; padding: 12px; text-align: left; }
.level-option.selected { border-color: #9fc0fb; background: #eff6ff; color: var(--blue); }
.level-dot { grid-row: span 2; width: 18px; height: 18px; border: 1px solid #d8e4f3; border-radius: 50%; background: #fff; margin-top: 1px; }
.level-option.selected .level-dot { border-color: #2563eb; box-shadow: inset 0 0 0 5px #2563eb; }
.level-option strong { color: var(--text); font-size: 13px; }
.level-option.selected strong { color: var(--blue); }
.level-option small { color: var(--text3); font-size: 11px; font-weight: 800; }
.active-toggle { display: flex; align-items: center; gap: 10px; color: var(--text); font-weight: 900; }
.catalog-status-toggle { align-items: flex-start; border: 1px solid var(--border); border-radius: 10px; background: #fff; padding: 14px; }
.catalog-status-toggle input { width: 16px; height: 16px; margin-top: 2px; accent-color: #2563eb; }
.catalog-status-toggle span { display: grid; gap: 3px; }
.catalog-status-toggle small { color: var(--text3); font-size: 12px; font-weight: 700; }
.catalog-format-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.catalog-format-picker button { min-height: 68px; display: grid; gap: 3px; border: 1px solid var(--border); border-radius: 9px; background: #fff; color: var(--text2); cursor: pointer; padding: 12px 14px; text-align: left; }
.catalog-format-picker button:hover { border-color: #b9c9df; background: #f8fbff; }
.catalog-format-picker button.selected { border-color: #8eb4f7; background: #eff6ff; color: var(--blue); box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.06); }
.catalog-format-picker strong { font-size: 13px; font-weight: 900; }
.catalog-format-picker span { color: var(--text3); font-size: 11px; font-weight: 700; }
.catalog-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.catalog-field-error { margin: 8px 0; }
.compact-list { max-height: 220px; }
.compact-level-picker { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }
.catalog-level-field { margin-top: 2px; }
.catalog-level-head { display: flex; align-items: start; justify-content: space-between; gap: 14px; margin-bottom: 10px; }
.compact-level-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.compact-level-grid .level-option { min-height: 54px; grid-template-rows: auto; }
.muted-note { margin-top: 10px; color: var(--text3); font-size: 12px; }
.fw8 { font-weight: 800; }
@media (max-width: 900px) {
  .focus-tabs, .form-grid, .level-grid, .catalog-filter-bar { grid-template-columns: 1fr; }
  .catalog-type-tabs { grid-template-columns: 1fr; }
  .catalog-type-tabs > .catalog-form-preview { min-width: 0; }
  .catalog-format-picker, .catalog-detail-grid { grid-template-columns: 1fr; }
  .formal-delivery-head { align-items: flex-start; }
  .formal-delivery-item { grid-template-columns: 44px minmax(0, 1fr) auto; }
  .formal-delivery-form { grid-column: 2 / -1; }
  .delivery-setting-summary { grid-template-columns: 1fr; }
  .delivery-setting-controls { grid-template-columns: 1fr; }
  .delivery-save-row { justify-content: flex-start; }
  .span-2 { grid-column: auto; }
  .tool-row { grid-template-columns: 1fr; }
  .form-select-row, .preview-meta-grid, .preview-meta-grid.three, .preview-project-grid, .preview-project-grid.training-form-grid, .preview-coaching-timeline, .preview-coaching-timeline.group-activity-timeline, .preview-coaching-timeline.training-summary, .coaching-help-body { grid-template-columns: 1fr; }
  .training-form-grid > .training-meta, .training-form-grid > .training-wide { grid-column: 1; }
  .coaching-help-card ul { grid-template-columns: 1fr; }
  .preview-coaching-timeline-heading { align-items: flex-start; flex-direction: column; gap: 3px; }
  .tool-actions, .panel-actions { justify-content: flex-start; }
  .panel-head { align-items: flex-start; flex-direction: column; }
}
</style>
