<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
import EmployeeAssess from '../Employee/EmployeeAssess.vue';
import EmployeeGap from '../Employee/EmployeeGap.vue';
import EmployeeIDP from '../Employee/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Employee/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Employee/EmployeeProgress.vue';
import FacultyOverview from '../Analytics/FacultyOverview.vue';
import HrAssessmentRounds from './HrAssessmentRounds.vue';
import HeadDashboard from '../Head/Dashboard.vue';

const props = defineProps({
    currentUser: {
        type: Object,
        default: () => ({}),
    },
    currentUserCompetencies: {
        type: Array,
        default: () => [],
    },
    currentUserCompetencyGaps: {
        type: Array,
        default: () => [],
    },
    currentUserFcTopicSelection: {
        type: Object,
        default: () => ({}),
    },
    hrSummary: {
        type: Object,
        required: true,
    },
    worklines: {
        type: Array,
        default: () => [],
    },
    jobFamiliesByWorkline: {
        type: Object,
        default: () => ({}),
    },
    supportOrg: {
        type: Object,
        default: () => ({}),
    },
    supportPositionGroups: {
        type: Object,
        default: () => ({}),
    },
    positionLookup: {
        type: Array,
        default: () => [],
    },
    positionCompetencies: {
        type: Object,
        default: () => ({}),
    },
    positionFcSelectionRules: {
        type: Object,
        default: () => ({}),
    },
    levelsByWorkline: {
        type: Object,
        default: () => ({}),
    },
    competencies: {
        type: Array,
        default: () => [],
    },
    learningMethods: {
        type: Array,
        default: () => [],
    },
    hrCatalogItems: {
        type: Array,
        default: () => [],
    },
    activeCycleName: {
        type: String,
        default: '',
    },
    facultyAnalytics: {
        type: Object,
        default: () => ({}),
    },
    assessmentRounds: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'กำหนดสมรรถนะ',
    },
});

const page = usePage();
const logout = () => router.post(route('logout'));

const isSidebarOpen = ref(true);
const activePage = ref('emp-assess');
const activeModal = ref('');
const selectedWorkline = ref('');
const selectedOrgScope = ref('');
const selectedPosition = ref('');
const selectedConfigRoundId = ref(null);
const dictionarySearch = ref('');
const dictionaryType = ref('all');
const selectedDetailCompetency = ref(null);
const expandedDetailLevels = ref({});
const isImportingRound = ref(false);
const catalogCompetencySearch = ref('');
const catalogSearch = ref('');
const catalogMethodFilter = ref('all');
const catalogDeliveryFilter = ref('all');
const catalogStatusFilter = ref('all');
const catalogMode = ref('create');
const catalogForm = ref({
    id: null,
    code: '',
    name: '',
    methodKey: '',
    deliveryType: 'e_learning',
    sourceType: 'internal',
    provider: '',
    cost: '',
    hours: '',
    competencyIds: [],
    expectedLevels: [],
    description: '',
    isActive: true,
});
const levelOptions = [
    { value: 1, label: 'Level 1', hint: 'พื้นฐาน / เริ่มต้น' },
    { value: 2, label: 'Level 2', hint: 'ลงมือปฏิบัติ' },
    { value: 3, label: 'Level 3', hint: 'พัฒนา / ทำได้ดี' },
    { value: 4, label: 'Level 4', hint: 'ขั้นสูง / ชำนาญ' },
    { value: 5, label: 'Level 5', hint: 'เชี่ยวชาญ' },
];
const deliveryTypeOptions = [
    { value: 'e_learning', label: 'การฝึกอบรมออนไลน์ (e-Learning)' },
    { value: 'in_class', label: 'การฝึกอบรมในห้องเรียน (In Class Training)' },
];

const baseSections = [
    {
        title: 'ของฉัน (บุคลากร)',
        items: [
            { id: 'emp-assess', label: 'ประเมินตนเอง' },
            { id: 'emp-gap', label: 'ผล Competency Gap' },
            { id: 'emp-idp', label: 'IDP ของฉัน' },
            { id: 'emp-progress', label: 'อัปเดตความก้าวหน้า' },
            { id: 'emp-idp-detail', label: 'รายละเอียด IDP' },
        ],
    },
    {
        title: 'HR',
        items: [
            { id: 'hr-cycle', label: 'รอบการประเมิน' },
            { id: 'hr-position-competencies', label: 'กำหนดสมรรถนะประจำตำแหน่ง' },
        ],
    },
    {
        title: 'ภาพรวมคณะ',
        items: [
            { id: 'faculty-assessment-overview', label: 'ภาพรวมการประเมิน' },
            { id: 'faculty-idp-tracking', label: 'การติดตาม IDP' },
        ],
    },
];
const fcTopicApprovalModule = computed(() => page.props.fcTopicApprovalModule || { enabled: false, items: [] });
const assessmentApprovalModule = computed(() => page.props.assessmentApprovalModule || { enabled: false, items: [] });
const idpReviewModule = computed(() => page.props.idpReviewModule || { enabled: false, assignmentCount: 0 });
const sections = computed(() => {
    const assignedItems = [
        ...((fcTopicApprovalModule.value.enabled || assessmentApprovalModule.value.enabled) ? [{ id: 'hr-assessment-review', label: 'อนุมัติการประเมิน' }] : []),
        ...(assessmentApprovalModule.value.enabled ? [{ id: 'hr-team-assessment', label: 'ผลการประเมินของทีม' }] : []),
        ...(idpReviewModule.value.enabled ? [{ id: 'hr-idp-review', label: 'อนุมัติแผนและผล IDP' }] : []),
    ];

    return assignedItems.length
        ? [...baseSections, { title: 'งานที่ได้รับมอบหมาย', items: assignedItems }]
        : baseSections;
});

const pageTitles = {
    'emp-assess': 'ประเมินตนเอง',
    'emp-gap': 'สรุปผลสมรรถนะ',
    'emp-idp': 'แผนพัฒนารายบุคคล',
    'emp-progress': 'อัปเดตความก้าวหน้า',
    'emp-idp-detail': 'รายละเอียด IDP',
    'hr-position-competencies': 'กำหนดสมรรถนะ',
    'hr-cycle': 'รอบการประเมิน',
    'faculty-assessment-overview': 'ภาพรวมการประเมิน',
    'faculty-idp-tracking': 'การติดตาม IDP',
    'hr-fc-topic-review': 'พิจารณาหัวข้อการประเมิน',
    'hr-assessment-review': 'อนุมัติการประเมิน',
    'hr-team-assessment': 'ผลการประเมินของทีม',
    'hr-idp-review': 'อนุมัติแผนและผล IDP',
};

const userInitial = computed(() => page.props.auth.user.name?.[0] || 'H');
const currentPageTitle = computed(() => pageTitles[activePage.value] || props.pageTitle);
const currentProfileUser = computed(() => props.currentUser || {});
const reviewerStepsForUser = (user) => {
    const steps = Array.isArray(user?.reviewerSteps) && user.reviewerSteps.length
        ? user.reviewerSteps
        : (Array.isArray(user?.supervisorChain) ? user.supervisorChain : []);

    return steps;
};
const selfAssessmentBlockReasons = computed(() => {
    const user = currentProfileUser.value;
    const rawStructureIssues = Array.isArray(user?.structureIssues) ? user.structureIssues : [];
    const reasons = rawStructureIssues.filter((reason) => reason !== 'ยังไม่ได้กำหนดลำดับ IDP');
    const hasAssignedEvaluator = reviewerStepsForUser(user).length > 0;

    if (!hasAssignedEvaluator) {
        reasons.push('ยังไม่ได้กำหนดหัวหน้าอย่างน้อย 1 ลำดับ');
    }

    if (user?.structureStatus === 'invalid' && rawStructureIssues.length === 0) {
        reasons.push('ข้อมูลโครงสร้างยังต้องตรวจสอบ');
    }

    return Array.from(new Set(reasons.filter(Boolean)));
});
const isSelfAssessmentBlocked = computed(() => selfAssessmentBlockReasons.value.length > 0);
const updateUsers = () => {};
const worklineOptions = computed(() => props.worklines || []);

const isSupportWorkline = computed(() => selectedWorkline.value === 'สายสนับสนุน');
const uniqueSortedPositions = (positions) => Array.from(new Set((positions || []).filter(Boolean)))
    .sort((left, right) => left.localeCompare(right, 'th'));
const supportGlobalPositions = computed(() => uniqueSortedPositions(
    Object.values(props.supportPositionGroups || {}).flat(),
));
const supportUnitScopes = computed(() => Object.entries(props.supportOrg || {}).flatMap(([division, works]) =>
    (works || []).flatMap((work) => (work.units || []).map((unit) => ({
        key: unit.key || [division, work.work, unit.name].join('|||'),
        label: unit.name,
        jobFamilyName: work.work,
        positions: uniqueSortedPositions([
            ...supportGlobalPositions.value,
            ...(unit.positions || []),
        ]),
    }))),
));
const organizationScopes = computed(() => {
    if (isSupportWorkline.value) return supportUnitScopes.value;

    const departments = props.jobFamiliesByWorkline?.[selectedWorkline.value] || {};
    return Object.keys(departments).map((name) => ({ key: name, label: name, positions: departments[name] || [] }));
});
const selectedScope = computed(() => organizationScopes.value.find((scope) => scope.key === selectedOrgScope.value) || null);

const levelsForSelectedWorkline = computed(() => {
    return props.levelsByWorkline?.[selectedWorkline.value] || [];
});

const positionOptions = computed(() => {
    return selectedScope.value?.positions || [];
});
const needsPositionBeforeMapping = computed(() => Boolean(selectedOrgScope.value && !positionOptions.value.length));
const selectedSupportGlobalPositions = computed(() => (
    isSupportWorkline.value
        ? supportGlobalPositions.value
        : []
));

const visiblePositionIds = computed(() => new Set(
    (props.positionLookup || [])
        .filter((position) => {
            if (!worklineOptions.value.includes(position.worklineName)) return false;

            if (position.worklineName === 'สายสนับสนุน') {
                const matchingScopes = position.supportUnitKey
                    ? supportUnitScopes.value.filter((scope) => scope.key === position.supportUnitKey)
                    : supportUnitScopes.value;

                return matchingScopes.some((scope) => scope.positions.includes(position.name));
            }

            const positions = props.jobFamiliesByWorkline?.[position.worklineName]?.[position.jobFamilyName] || [];
            return positions.includes(position.name);
        })
        .map((position) => Number(position.id))
        .filter((id) => id > 0),
));
const selectedConfigRound = computed(() => (props.assessmentRounds || [])
    .find((round) => Number(round.id) === Number(selectedConfigRoundId.value)) || null);
const importSourceRounds = computed(() => (props.assessmentRounds || [])
    .filter((round) => Number(round.id) !== Number(selectedConfigRoundId.value))
    .sort((left, right) => Number(right.year) - Number(left.year) || Number(right.id) - Number(left.id)));
const positionCompetenciesForRound = computed(() => props.positionCompetencies?.[selectedConfigRoundId.value] || {});
const positionFcRulesForRound = computed(() => props.positionFcSelectionRules?.[selectedConfigRoundId.value] || {});
const allPositionCount = computed(() => visiblePositionIds.value.size);
const configuredPositionCount = computed(() => Object.entries(positionCompetenciesForRound.value)
    .filter(([positionId, items]) => visiblePositionIds.value.has(Number(positionId)) && items.length)
    .length);
const unconfiguredPositionCount = computed(() => Math.max(allPositionCount.value - configuredPositionCount.value, 0));
const positionLabel = computed(() => selectedPosition.value || 'ยังไม่มีข้อมูลตำแหน่ง/ระดับตำแหน่ง');
const organizationScopeLabel = computed(() => selectedScope.value?.label || (isSupportWorkline.value ? 'ยังไม่มีข้อมูลหน่วย' : 'ยังไม่มีข้อมูลภาควิชา'));
const currentPosition = computed(() => {
    const candidates = (props.positionLookup || []).filter((position) => (
        position.worklineName === selectedWorkline.value && position.name === selectedPosition.value
    ));

    if (!isSupportWorkline.value) {
        return candidates.find((position) => position.jobFamilyName === selectedOrgScope.value) || null;
    }

    const globalPosition = candidates.find((position) => (
        !position.supportUnitKey && selectedSupportGlobalPositions.value.includes(position.name)
    ));
    const unitPosition = candidates.find((position) => position.supportUnitKey === selectedOrgScope.value);

    return selectedSupportGlobalPositions.value.includes(selectedPosition.value)
        ? (globalPosition || unitPosition || null)
        : (unitPosition || globalPosition || null);
});
const currentPositionId = computed(() => currentPosition.value?.id || null);

const competencyItems = computed(() => props.competencies || []);
const competencyTypes = computed(() => {
    return [...new Set(competencyItems.value.map((item) => item.t).filter(Boolean))];
});
const coreCompetencyCount = computed(() => competencyItems.value.filter((item) => item.t === 'CC').length);
const assignedCompetencyIds = computed(() => new Set(positionCompetenciesForRound.value?.[currentPositionId.value] || []));
const assignedCompetencies = computed(() => {
    return competencyItems.value.filter((item) => assignedCompetencyIds.value.has(item.id));
});
const assignedCoreCompetencyCount = computed(() => assignedCompetencies.value.filter((item) => item.t === 'CC').length);
const assignedFcCompetencyCount = computed(() => assignedCompetencies.value.filter((item) => String(item.t || '').startsWith('FC')).length);
const assignedManagerialCompetencyCount = computed(() => assignedCompetencies.value.filter((item) => item.t === 'MC').length);
const requiredFcCount = ref(0);
const savedRequiredFcCount = computed(() => Number(positionFcRulesForRound.value?.[currentPositionId.value] || 0));
const filteredCompetencies = computed(() => {
    const keyword = dictionarySearch.value.trim().toLowerCase();

    return competencyItems.value.filter((item) => {
        const matchesType = dictionaryType.value === 'all' || item.t === dictionaryType.value;
        const haystack = `${item.cd || ''} ${item.n || ''} ${item.det || ''}`.toLowerCase();
        return matchesType && (!keyword || haystack.includes(keyword));
    });
});
const availableCoreCompetencies = computed(() => {
    return competencyItems.value.filter((item) => item.t === 'CC' && !assignedCompetencyIds.value.has(item.id));
});

const catalogItems = computed(() => props.hrCatalogItems || []);
const filteredCatalogItems = computed(() => {
    const keyword = catalogSearch.value.trim().toLowerCase();

    return catalogItems.value.filter((item) => {
        const haystack = `${item.code || ''} ${item.name || ''} ${item.description || ''}`.toLowerCase();
        const matchesSearch = !keyword || haystack.includes(keyword);
        const matchesMethod = catalogMethodFilter.value === 'all' || item.methodKey === catalogMethodFilter.value;
        const matchesDelivery = catalogDeliveryFilter.value === 'all' || item.deliveryType === catalogDeliveryFilter.value;
        const matchesStatus = catalogStatusFilter.value === 'all'
            || (catalogStatusFilter.value === 'active' ? item.isActive : !item.isActive);

        return matchesSearch && matchesMethod && matchesDelivery && matchesStatus;
    });
});
const resetCatalogFilters = () => {
    catalogSearch.value = '';
    catalogMethodFilter.value = 'all';
    catalogDeliveryFilter.value = 'all';
    catalogStatusFilter.value = 'all';
};
const defaultCatalogMethodKey = () => {
    const methods = props.learningMethods || [];
    const formal = methods.find((method) => {
        const key = String(method.key || '').toLowerCase();
        const label = String(method.label || '').toLowerCase();
        return key.includes('formal') || label.includes('formal');
    });

    return formal?.key || methods[0]?.key || '';
};
const filteredCatalogCompetencies = computed(() => {
    const keyword = catalogCompetencySearch.value.trim().toLowerCase();
    if (!keyword) return competencyItems.value;

    return competencyItems.value.filter((item) => {
        const haystack = `${item.cd || ''} ${item.n || ''} ${item.t || ''}`.toLowerCase();
        return haystack.includes(keyword);
    });
});

watch(worklineOptions, (next) => {
    if (!selectedWorkline.value && next.length) selectedWorkline.value = next[0];
}, { immediate: true });

watch(() => props.assessmentRounds, (rounds) => {
    if ((rounds || []).some((round) => Number(round.id) === Number(selectedConfigRoundId.value))) return;
    selectedConfigRoundId.value = (rounds || []).find((round) => round.isActive)?.id || rounds?.[0]?.id || null;
}, { immediate: true });

watch(organizationScopes, (next) => {
    if (!next.some((scope) => scope.key === selectedOrgScope.value)) selectedOrgScope.value = next[0]?.key || '';
}, { immediate: true });

watch(positionOptions, (next) => {
    if (!next.includes(selectedPosition.value)) selectedPosition.value = next[0] || '';
}, { immediate: true });

watch([currentPositionId, savedRequiredFcCount], () => {
    requiredFcCount.value = savedRequiredFcCount.value;
}, { immediate: true });

const openModal = (modal) => {
    activeModal.value = modal;
};

const closeModal = () => {
    activeModal.value = '';
    selectedDetailCompetency.value = null;
    expandedDetailLevels.value = {};
};

const resetCatalogForm = () => {
    catalogMode.value = 'create';
    catalogCompetencySearch.value = '';
    catalogForm.value = {
        id: null,
        code: '',
        name: '',
        methodKey: defaultCatalogMethodKey(),
        deliveryType: 'e_learning',
        sourceType: 'internal',
        provider: '',
        cost: '',
        hours: '',
        competencyIds: [],
        expectedLevels: [],
        description: '',
        isActive: true,
    };
};

const openCatalogCreate = () => {
    resetCatalogForm();
    openModal('catalog');
};

const openCatalogEdit = (item) => {
    catalogMode.value = 'edit';
    catalogCompetencySearch.value = '';
    catalogForm.value = {
        id: item.id,
        code: item.code || '',
        name: item.name || '',
        methodKey: item.methodKey || '',
        deliveryType: item.deliveryType || 'e_learning',
        sourceType: item.sourceType || 'internal',
        provider: item.provider || '',
        cost: item.cost ?? '',
        hours: item.hours ?? '',
        competencyIds: [...(item.competencyIds || [])].slice(0, 1),
        expectedLevels: [...(item.expectedLevels || [])],
        description: item.description || '',
        isActive: Boolean(item.isActive),
    };

};

const catalogPayload = () => ({
    code: catalogForm.value.code || null,
    name: catalogForm.value.name,
    method_key: catalogForm.value.methodKey || null,
    delivery_type: catalogForm.value.deliveryType,
    source_type: catalogForm.value.sourceType,
    provider: catalogForm.value.provider || null,
    cost: catalogForm.value.cost === '' ? null : catalogForm.value.cost,
    hours: catalogForm.value.hours === '' ? null : catalogForm.value.hours,
    competency_ids: catalogForm.value.competencyIds,
    expected_levels: catalogForm.value.expectedLevels,
    description: catalogForm.value.description || null,
    is_active: catalogForm.value.isActive,
});

const toggleCatalogCompetency = (id) => {
    catalogForm.value.competencyIds = catalogForm.value.competencyIds.includes(id) ? [] : [id];
};

const toggleCatalogExpectedLevel = (level) => {
    const current = new Set(catalogForm.value.expectedLevels);
    current.has(level) ? current.delete(level) : current.add(level);
    catalogForm.value.expectedLevels = [...current].sort((a, b) => a - b);
};

const submitCatalog = () => {
    if (catalogMode.value === 'edit' && catalogForm.value.id) {
        router.put(route('hr.learning-catalogs.update', catalogForm.value.id), catalogPayload(), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    router.post(route('hr.learning-catalogs.store'), catalogPayload(), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const deleteCatalog = (item) => {
    if (!window.confirm(`ลบ "${item.name}" ออกจาก Learning Catalog?`)) return;

    router.delete(route('hr.learning-catalogs.destroy', item.id), {
        preserveScroll: true,
    });
};

const addCompetency = (item) => {
    if (!selectedConfigRoundId.value || !currentPositionId.value || assignedCompetencyIds.value.has(item.id)) return;

    router.post(route('hr.position-competencies.store'), {
        position_id: currentPositionId.value,
        assessment_round_id: selectedConfigRoundId.value,
        competency_id: item.id,
    }, {
        preserveScroll: true,
    });
};

const addAllCoreCompetencies = () => {
    if (!selectedConfigRoundId.value || !currentPositionId.value || !availableCoreCompetencies.value.length) return;

    router.post(route('hr.position-competencies.store'), {
        position_id: currentPositionId.value,
        assessment_round_id: selectedConfigRoundId.value,
        competency_ids: availableCoreCompetencies.value.map((item) => item.id),
    }, {
        preserveScroll: true,
    });
};

const removeCompetency = (itemId) => {
    if (!selectedConfigRoundId.value || !currentPositionId.value) return;

    router.delete(route('hr.position-competencies.destroy'), {
        data: {
            position_id: currentPositionId.value,
            assessment_round_id: selectedConfigRoundId.value,
            competency_id: itemId,
        },
        preserveScroll: true,
    });
};

const saveFcSelectionRule = () => {
    if (!selectedConfigRoundId.value || !currentPositionId.value) return;

    router.put(route('hr.position-fc-selection-rules.update'), {
        position_id: currentPositionId.value,
        assessment_round_id: selectedConfigRoundId.value,
        required_fc_count: Number(requiredFcCount.value || 0),
    }, {
        preserveScroll: true,
    });
};

const importRoundConfiguration = (source) => {
    const target = selectedConfigRound.value;
    if (!source || !target || isImportingRound.value) return;

    isImportingRound.value = true;

    router.post(route('hr.position-competencies.copy-round'), {
        source_round_id: source.id,
        target_round_id: target.id,
    }, {
        preserveScroll: true,
        onSuccess: closeModal,
        onFinish: () => {
            isImportingRound.value = false;
        },
    });
};

const openCompetencyDetail = (item) => {
    selectedDetailCompetency.value = item;
    expandedDetailLevels.value = {};
    openModal('competency-detail');
};

const toggleDetailLevel = (level) => {
    expandedDetailLevels.value = {
        ...expandedDetailLevels.value,
        [level.lvl]: !expandedDetailLevels.value[level.lvl],
    };
};

const formatCost = (cost) => {
    if (cost === null || cost === undefined || cost === '') return 'ฟรี';
    const number = Number(cost);
    if (Number.isNaN(number) || number === 0) return 'ฟรี';
    return `${number.toLocaleString('th-TH')} บาท`;
};

const deliveryTypeLabel = (value) => {
    return deliveryTypeOptions.find((option) => option.value === value)?.label || '-';
};

const formatWeight = (weight) => {
    if (weight === null || weight === undefined || weight === '') return '-';
    const number = Number(weight);
    if (Number.isNaN(number)) return '-';
    return number.toLocaleString('th-TH', { maximumFractionDigits: 2 });
};
</script>

<template>
    <Head :title="currentPageTitle" />

    <div class="shell">
        <aside v-if="isSidebarOpen" class="sidebar">
            <SidebarBrand />

            <button class="sb-user" type="button">
                <div class="av" style="background: var(--blue)">{{ userInitial }}</div>
                <div style="min-width: 0">
                    <div class="u-name">{{ $page.props.auth.user.name }}</div>
                    <div class="u-role">งานทรัพยากรบุคคล</div>
                </div>
            </button>

            <nav class="sb-nav">
                <div v-for="section in sections" :key="section.title">
                    <div class="nav-sec">{{ section.title }}</div>
                    <button
                        v-for="item in section.items"
                        :key="item.id"
                        class="nav-item"
                        :class="{ on: activePage === item.id }"
                        type="button"
                        @click="activePage = item.id"
                    >
                        <span>{{ item.label }}</span>
                    </button>
                </div>
            </nav>
        </aside>

        <section class="main">
            <header class="topbar">
                <button
                    class="btn btn-s btn-sm hr-menu-button"
                    :title="isSidebarOpen ? 'ซ่อนเมนู' : 'แสดงเมนู'"
                    type="button"
                    @click="isSidebarOpen = !isSidebarOpen"
                >
                    ☰
                </button>
                <PageTitleBlock :page-title="currentPageTitle" />
                <button class="btn btn-s btn-sm" type="button" @click="logout">ออกจากระบบ</button>
            </header>

            <main class="content">
                <EmployeeAssess
                    v-if="activePage === 'emp-assess'"
                    :user="currentProfileUser"
                    :set-users="updateUsers"
                    :competencies="props.currentUserCompetencies"
                    :fc-topic-selection="props.currentUserFcTopicSelection"
                    :blocked="isSelfAssessmentBlocked"
                    :block-reasons="selfAssessmentBlockReasons"
                />
                <EmployeeGap
                    v-else-if="activePage === 'emp-gap'"
                    :set-page="(pageId) => (activePage = pageId)"
                    :competencies="props.currentUserCompetencies"
                    :gaps="props.currentUserCompetencyGaps"
                    :eval-status="currentProfileUser.evalStatus"
                />
                <EmployeeIDP
                    v-else-if="activePage === 'emp-idp'"
                    :learning-methods="props.learningMethods"
                    :idp-learning-methods="page.props.idpLearningMethods || []"
                    :learning-catalogs="page.props.hrCatalogItems || []"
                    :gaps="props.currentUserCompetencyGaps"
                    :idp="page.props.currentUserIdp || null"
                    :user="currentProfileUser"
                />
                <EmployeeProgress
                    v-else-if="activePage === 'emp-progress'"
                    :activities="page.props.currentUserApprovedIdpActivities || []"
                />
                <EmployeeIDPDetail v-else-if="activePage === 'emp-idp-detail'" :activities="page.props.currentUserApprovedIdpActivities || []" />
                <HeadDashboard
                    v-else-if="activePage === 'hr-fc-topic-review' && fcTopicApprovalModule.enabled"
                    embedded
                    embedded-page="dh-fc-topic-approval"
                    role-key="hr"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <HeadDashboard
                    v-else-if="activePage === 'hr-assessment-review' && (fcTopicApprovalModule.enabled || assessmentApprovalModule.enabled)"
                    embedded
                    embedded-page="dh-assess"
                    role-key="hr"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <HeadDashboard
                    v-else-if="activePage === 'hr-team-assessment' && assessmentApprovalModule.enabled"
                    embedded
                    embedded-page="sup-gap"
                    role-key="hr"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <HeadDashboard
                    v-else-if="activePage === 'hr-idp-review' && idpReviewModule.enabled"
                    embedded
                    embedded-page="dh-idp"
                    role-key="hr"
                    :idp-review-items="page.props.idpReviewItems || []"
                />
                <template v-else-if="activePage === 'hr-position-competencies'">
                    <div class="position-hero mb14">
                        <div>
                            <div class="position-kicker">COMPETENCY SETUP</div>
                            <div class="sec-t position-title">กำหนดสมรรถนะประจำตำแหน่ง</div>
                            <div class="sec-s position-sub">
                                เลือกตำแหน่ง แล้วกำหนดชุดสมรรถนะที่ต้องใช้ประเมิน ก่อนนำไปตั้งระดับความคาดหวังในรอบประเมิน
                            </div>
                        </div>
                        <div class="position-hero-metrics">
                            <div>
                                <span>{{ configuredPositionCount }}</span>
                                <small>ตำแหน่งที่กำหนดแล้ว</small>
                            </div>
                            <div>
                                <span>{{ unconfiguredPositionCount }}</span>
                                <small>ยังไม่กำหนด</small>
                            </div>
                        </div>
                    </div>
                    <div class="round-config-bar mb14">
                        <div>
                            <label class="lbl">รอบการประเมินที่กำลังกำหนด</label>
                            <select v-model.number="selectedConfigRoundId" class="sel">
                                <option v-if="!assessmentRounds.length" :value="null">ยังไม่มีรอบการประเมิน</option>
                                <option v-for="round in assessmentRounds" :key="round.id" :value="round.id">
                                    {{ round.name }}{{ round.isActive ? ' · กำลังใช้งาน' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="round-config-copy">
                            <span v-if="importSourceRounds.length">นำเข้าชุดสมรรถนะจากรอบอื่นมาใช้กับรอบนี้</span>
                            <span v-else>ยังไม่มีรอบการประเมินอื่นสำหรับนำเข้า</span>
                            <button
                                class="btn btn-p btn-sm"
                                type="button"
                                :disabled="!selectedConfigRoundId || !importSourceRounds.length"
                                @click="openModal('round-import')"
                            >
                                นำเข้าสมรรถนะประจำตำแหน่ง
                            </button>
                        </div>
                    </div>
                    <div class="position-scope mb14">
                        <div class="position-workline">
                            <div class="position-scope-label">สายงาน</div>
                            <div v-if="worklineOptions.length" class="position-segments">
                                <button
                                    v-for="workline in worklineOptions"
                                    :key="workline"
                                    :class="{ active: selectedWorkline === workline }"
                                    type="button"
                                    @click="selectedWorkline = workline"
                                >
                                    {{ workline }}
                                </button>
                            </div>
                            <div v-else class="position-empty-inline">ยังไม่มีสายงาน</div>
                        </div>
                        <div class="position-picker">
                            <div class="fg mb0">
                                <label class="lbl">{{ isSupportWorkline ? 'หน่วย' : 'ภาควิชา' }}</label>
                                <select v-model="selectedOrgScope" class="sel">
                                    <option value="">{{ isSupportWorkline ? 'ยังไม่มีข้อมูลหน่วย' : 'ยังไม่มีข้อมูลภาควิชา' }}</option>
                                    <option v-for="scope in organizationScopes" :key="scope.key" :value="scope.key">
                                        {{ scope.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="fg mb0">
                                <label class="lbl">ตำแหน่ง</label>
                                <select v-model="selectedPosition" class="sel" :disabled="needsPositionBeforeMapping">
                                    <option v-if="!positionOptions.length" value="">ไม่มีตำแหน่งใน{{ isSupportWorkline ? 'หน่วย' : 'ภาควิชา' }}</option>
                                    <option v-for="position in positionOptions" :key="position" :value="position">
                                        {{ position }}
                                    </option>
                                </select>
                                <div v-if="needsPositionBeforeMapping" class="position-warning">
                                    กรุณาให้ Admin เพิ่มตำแหน่งงานก่อนกำหนดสมรรถนะ
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="position-board mb14">
                        <div class="position-card selected">
                            <div class="position-card-label">ตำแหน่งที่กำลังกำหนด</div>
                            <div class="position-card-title">{{ positionLabel }}</div>
                            <div class="position-card-sub">{{ selectedWorkline || 'ยังไม่มีสายงาน' }} · {{ organizationScopeLabel }}</div>
                        </div>
                        <div class="position-card">
                            <div class="position-card-label">สมรรถนะของตำแหน่งนี้</div>
                            <div class="position-card-title">{{ assignedCompetencies.length }}</div>
                            <div class="position-card-sub">{{ assignedCompetencies.length ? 'เลือกไว้ในหน้านี้' : 'ยังไม่มีรายการ' }}</div>
                        </div>
                        <div class="position-card type-breakdown-card">
                            <div class="position-card-label">แยกตามประเภทที่กำหนด</div>
                            <div class="type-breakdown">
                                <div>
                                    <span>CC</span>
                                    <strong>{{ assignedCoreCompetencyCount }}</strong>
                                </div>
                                <div>
                                    <span>FC</span>
                                    <strong>{{ assignedFcCompetencyCount }}</strong>
                                </div>
                                <div>
                                    <span>MC</span>
                                    <strong>{{ assignedManagerialCompetencyCount }}</strong>
                                </div>
                            </div>
                            <div class="position-card-sub">นับจากรายการที่ผูกกับตำแหน่งนี้</div>
                        </div>
                        <div class="position-card">
                            <div class="position-card-label">CC พื้นฐาน</div>
                            <div class="position-card-title warn">{{ assignedCoreCompetencyCount }}/{{ coreCompetencyCount }}</div>
                            <div class="position-card-sub">{{ coreCompetencyCount ? 'รอเลือกจากพจนานุกรม' : 'ยังไม่มีข้อมูล CC พื้นฐาน' }}</div>
                        </div>
                        <div class="position-card fc-rule-card">
                            <div class="position-card-label">FC ที่ต้องให้ผู้ประเมินเลือก</div>
                            <div class="fc-rule-control">
                                <input
                                    v-model.number="requiredFcCount"
                                    class="inp fc-rule-input"
                                    type="number"
                                    min="0"
                                    :max="assignedFcCompetencyCount"
                                    :disabled="!selectedConfigRoundId || !currentPositionId"
                                />
                                <button
                                    class="btn btn-p btn-sm"
                                    type="button"
                                    :disabled="!selectedConfigRoundId || !currentPositionId || requiredFcCount > assignedFcCompetencyCount"
                                    @click="saveFcSelectionRule"
                                >
                                    บันทึก
                                </button>
                            </div>
                            <div class="position-card-sub">
                                มี FC ในตำแหน่งนี้ {{ assignedFcCompetencyCount }} ข้อ · 0 คือไม่ใช้ flow เลือกหัวข้อ
                            </div>
                        </div>
                    </div>

                    <div class="position-layout">
                        <section class="position-panel assigned">
                            <div class="position-panel-head">
                                <div>
                                    <div class="ct">ชุดสมรรถนะประจำตำแหน่ง</div>
                                    <div class="cs">รายการนี้จะถูกใช้เป็นฐานสำหรับกำหนดระดับความคาดหวัง</div>
                                </div>
                                <button
                                    class="btn btn-t btn-sm"
                                    :disabled="!selectedConfigRoundId || !currentPositionId || !availableCoreCompetencies.length"
                                    type="button"
                                    @click="addAllCoreCompetencies"
                                >
                                    เพิ่ม CC ทั้งหมด
                                </button>
                            </div>
                            <div class="assigned-list">
                                <div v-if="!assignedCompetencies.length" class="assigned-empty">
                                    <div class="empty-symbol">ไม่มีข้อมูล</div>
                                    <div class="fw8">{{ needsPositionBeforeMapping ? 'ต้องเพิ่มตำแหน่งก่อน' : 'ยังไม่ได้กำหนดสมรรถนะให้ตำแหน่งนี้' }}</div>
                                    <div class="muted fs12">
                                        {{ needsPositionBeforeMapping ? 'ระบบต้องมีตำแหน่งจริงในฐานข้อมูลก่อน จึงจะบันทึกการผูกสมรรถนะได้' : 'เลือกจากพจนานุกรมด้านขวาเพื่อเพิ่มเข้าชุดนี้' }}
                                    </div>
                                </div>
                                <div v-for="item in assignedCompetencies" v-else :key="item.id" class="assigned-row">
                                    <div>
                                        <div class="dictionary-code">{{ item.cd }} <span class="b bgr">{{ item.t }}</span></div>
                                        <div class="dictionary-name">{{ item.n }}</div>
                                    </div>
                                    <div class="assigned-actions">
                                        <button class="btn btn-s btn-sm" type="button" @click="openCompetencyDetail(item)">รายละเอียด</button>
                                        <button class="btn btn-s btn-sm danger-text" type="button" @click="removeCompetency(item.id)">ลบ</button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <aside class="position-panel dictionary">
                            <div class="position-panel-head">
                                <div>
                                    <div class="ct">พจนานุกรมสมรรถนะ</div>
                                    <div class="cs">เลือกจากรายการที่ Admin กำหนดไว้</div>
                                </div>
                            </div>
                            <div class="dictionary-tools">
                                <input v-model="dictionarySearch" class="inp" placeholder="ค้นหารหัส / ชื่อ / คำอธิบาย" />
                                <select v-model="dictionaryType" class="sel">
                                    <option value="all">ทั้งหมด</option>
                                    <option v-for="type in competencyTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                            </div>
                            <div class="dictionary-list">
                                <div v-if="!filteredCompetencies.length" class="assigned-empty dictionary-empty">
                                    <div class="empty-symbol">ไม่มีข้อมูล</div>
                                    <div class="fw8">ยังไม่มีข้อมูลสมรรถนะ</div>
                                    <div class="muted fs12">เมื่อ Admin เพิ่มพจนานุกรมแล้ว รายการจะแสดงที่นี่</div>
                                </div>
                                <div v-for="item in filteredCompetencies" v-else :key="item.id" class="dictionary-row">
                                    <div class="dictionary-main">
                                        <div class="dictionary-code">
                                            <span>{{ item.cd }}</span>
                                            <span class="b bgr">{{ item.t }}</span>
                                            <button class="dictionary-detail-button" type="button" @click="openCompetencyDetail(item)">รายละเอียด</button>
                                        </div>
                                        <div class="dictionary-name">{{ item.n }}</div>
                                    </div>
                                    <button
                                        class="btn btn-p btn-sm"
                                        :disabled="!selectedConfigRoundId || !selectedPosition || assignedCompetencyIds.has(item.id)"
                                        type="button"
                                        @click="addCompetency(item)"
                                    >
                                        {{ assignedCompetencyIds.has(item.id) ? 'เพิ่มแล้ว' : 'เพิ่ม' }}
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </div>
                </template>

                <FacultyOverview
                    v-else-if="activePage === 'faculty-assessment-overview'"
                    :analytics="props.facultyAnalytics"
                    module="assessment"
                />

                <FacultyOverview
                    v-else-if="activePage === 'faculty-idp-tracking'"
                    :analytics="props.facultyAnalytics"
                    module="idp"
                />

                <HrAssessmentRounds
                    v-else-if="activePage === 'hr-cycle'"
                    :rounds="props.assessmentRounds"
                />

                <template v-else-if="activePage === 'hr-catalog'">
                    <div class="hr-page-head mb20">
                        <div>
                            <div class="sec-t">Learning Catalog</div>
                            <div class="sec-s">ทะเบียนกิจกรรมพัฒนา · บุคลากรเลือกกิจกรรมจาก Catalog นี้เมื่อทำ IDP</div>
                        </div>
                        <span class="muted fs12">อ่านอย่างเดียว · ผู้ดูแลระบบเป็นผู้กำหนด</span>
                    </div>

                    <div class="catalog-summary-card single mb14">
                        <div class="catalog-summary-total">
                            <div class="catalog-summary-number">{{ catalogItems.length }}</div>
                            <div>
                                <div class="catalog-summary-title">กิจกรรมทั้งหมด</div>
                                <div class="catalog-summary-sub">รายการใน Learning Catalog</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="ch">
                            <div class="ct">Learning Catalog</div>
                            <span class="muted fs12 ml-auto">{{ filteredCatalogItems.length }}/{{ catalogItems.length }} รายการ</span>
                        </div>
                        <div class="catalog-filter-bar">
                            <input
                                v-model="catalogSearch"
                                class="inp catalog-filter-search"
                                placeholder="ค้นหารหัส / ชื่อกิจกรรม / คำอธิบาย"
                            />
                            <select v-model="catalogMethodFilter" class="sel catalog-filter-select">
                                <option value="all">ทุกประเภท</option>
                                <option v-for="method in learningMethods" :key="method.key" :value="method.key">{{ method.label }}</option>
                            </select>
                            <select v-model="catalogDeliveryFilter" class="sel catalog-filter-select">
                                <option value="all">ทุกรูปแบบ</option>
                                <option v-for="option in deliveryTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <select v-model="catalogStatusFilter" class="sel catalog-filter-select compact">
                                <option value="all">ทุกสถานะ</option>
                                <option value="active">เปิดใช้</option>
                                <option value="inactive">ปิด</option>
                            </select>
                            <button class="btn btn-s btn-sm" type="button" @click="resetCatalogFilters">ล้าง</button>
                        </div>
                        <div class="hr-table-wrap">
                            <table class="tbl catalog-table">
                                <thead>
                                    <tr>
                                        <th>รหัส</th>
                                        <th>ชื่อกิจกรรม</th>
                                        <th>ประเภท</th>
                                        <th>รูปแบบ</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody v-if="!catalogItems.length">
                                    <tr>
                                        <td colspan="5">
                                            <div class="table-empty-cell">
                                                <div>
                                                    <div class="fw8 fs14">ยังไม่มี Learning Catalog</div>
                                                    <p class="muted fs13 mb0">เมื่อมีตารางกิจกรรมพัฒนาแล้ว รายการหลักสูตรและกิจกรรมจะถูกดึงจากฐานข้อมูลมาแสดง</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else-if="!filteredCatalogItems.length">
                                    <tr>
                                        <td colspan="5">
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
                                    <tr v-for="item in filteredCatalogItems" :key="item.id">
                                        <td class="fw8">{{ item.code || '-' }}</td>
                                        <td>
                                            <div class="fw8">{{ item.name }}</div>
                                        </td>
                                        <td><span class="b bgr">{{ item.methodLabel || '-' }}</span></td>
                                        <td>{{ deliveryTypeLabel(item.deliveryType) }}</td>
                                        <td>
                                            <span class="catalog-status-badge" :class="item.isActive ? 'active' : 'inactive'">
                                                {{ item.isActive ? 'เปิดใช้' : 'ปิด' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </main>
        </section>

        <div v-if="activeModal" class="mo hr-modal" @click.self="closeModal">
            <div class="mo-box hr-modal-box" :class="{ 'catalog-modal-box': activeModal === 'catalog' }">
                <div class="mo-h">
                    <div>
                        <template v-if="activeModal === 'round-import'">
                            <div class="ct">นำเข้าสมรรถนะประจำตำแหน่ง</div>
                            <div class="cs">เลือกชุดข้อมูลที่จะนำมาใช้กับ {{ selectedConfigRound?.name }}</div>
                        </template>
                        <template v-else-if="activeModal === 'competency-detail' && selectedDetailCompetency">
                            <div class="ct">{{ selectedDetailCompetency.cd }} · {{ selectedDetailCompetency.n }}</div>
                            <div class="cs">รายละเอียดระดับและพฤติกรรมบ่งชี้ของสมรรถนะ</div>
                        </template>
                        <template v-else>
                            <div class="ct">{{ catalogMode === 'edit' ? 'แก้ไขกิจกรรม Learning Catalog' : 'เพิ่มกิจกรรม Learning Catalog' }}</div>
                            <div class="cs">ข้อมูลนี้จะถูกบันทึกลง Learning Catalog เพื่อใช้ในแผนพัฒนา IDP</div>
                        </template>
                    </div>
                    <button class="modal-close" type="button" @click="closeModal">×</button>
                </div>

                <div class="mo-b">
                    <template v-if="activeModal === 'round-import'">
                        <div class="round-import-note">
                            เมื่อเลือกรอบ ระบบจะแทนที่สมรรถนะประจำตำแหน่งและจำนวน FC ของรอบนี้ทันที
                        </div>
                        <div class="round-import-list" aria-label="เลือกรอบการประเมินต้นทาง">
                            <button
                                v-for="round in importSourceRounds"
                                :key="round.id"
                                class="round-import-option"
                                type="button"
                                :disabled="isImportingRound"
                                @click="importRoundConfiguration(round)"
                            >
                                <span>
                                    <strong>{{ round.name }}</strong>
                                </span>
                                <span v-if="isImportingRound" class="round-import-action">กำลังนำเข้า…</span>
                                <span v-else class="round-import-action">เลือกใช้</span>
                            </button>
                        </div>
                    </template>
                    <template v-else-if="activeModal === 'competency-detail' && selectedDetailCompetency">
                        <div class="competency-detail">
                            <section class="detail-overview">
                                <div class="detail-title">{{ selectedDetailCompetency.n }}</div>
                                <div class="detail-meta-row">
                                    <span>{{ selectedDetailCompetency.t }}<template v-if="selectedDetailCompetency.typeName"> - {{ selectedDetailCompetency.typeName }}</template></span>
                                    <span>{{ selectedDetailCompetency.cd }}</span>
                                </div>
                                <div class="detail-description">{{ selectedDetailCompetency.det || 'ไม่มีคำอธิบายสมรรถนะ' }}</div>
                            </section>

                            <section class="detail-behavior-panel">
                                <div class="detail-panel-head">
                                    <div>
                                        <div class="detail-section-label">รายละเอียดพฤติกรรมบ่งชี้</div>
                                        <div class="detail-panel-sub">เลือกเปิดแต่ละระดับเพื่อดูพฤติกรรมและน้ำหนัก</div>
                                    </div>
                                </div>
                                <div class="detail-levels">
                                    <div v-if="!(selectedDetailCompetency.levels || []).length" class="assigned-empty detail-empty">
                                        <div class="empty-symbol">ไม่มีข้อมูล</div>
                                        <div class="fw8">ยังไม่มีข้อมูลระดับสมรรถนะ</div>
                                    </div>
                                    <article
                                        v-for="level in selectedDetailCompetency.levels"
                                        v-else
                                        :key="level.id || level.lvl"
                                        class="detail-level"
                                        :class="{ open: expandedDetailLevels[level.lvl] }"
                                    >
                                        <button class="detail-level-head" type="button" @click="toggleDetailLevel(level)">
                                            <span class="detail-level-number">Level {{ level.lvl }}</span>
                                            <span class="detail-toggle" :class="{ open: expandedDetailLevels[level.lvl] }" aria-hidden="true"></span>
                                        </button>
                                        <div v-if="expandedDetailLevels[level.lvl] && level.indicators?.length" class="detail-indicators">
                                            <div class="detail-indicator-head">
                                                <div>ข้อ</div>
                                                <div>พฤติกรรมบ่งชี้</div>
                                                <div>น้ำหนัก</div>
                                            </div>
                                            <div
                                                v-for="(indicator, index) in level.indicators"
                                                :key="`${level.lvl}-${index}`"
                                                class="detail-indicator"
                                            >
                                                <div class="detail-index">{{ index + 1 }}</div>
                                                <div class="detail-copy">{{ indicator || 'ยังไม่มีรายละเอียดพฤติกรรม' }}</div>
                                                <div class="detail-weight">{{ formatWeight(level.weights?.[index]) }}</div>
                                            </div>
                                        </div>
                                        <div
                                            v-else-if="expandedDetailLevels[level.lvl]"
                                            class="detail-no-indicators"
                                        >ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</div>
                                    </article>
                                </div>
                            </section>
                        </div>
                    </template>

                    <form v-else class="catalog-form" @submit.prevent="submitCatalog">
                        <section class="catalog-form-section">
                            <div class="catalog-section-head">
                                <div>
                                    <div class="catalog-section-title">1. เลือกสมรรถนะที่เกี่ยวข้อง</div>
                                    <div class="catalog-section-sub">เลือกสมรรถนะหลักที่หลักสูตรนี้ช่วยพัฒนาหรือปิด Gap ได้ 1 รายการ</div>
                                </div>
                                <span class="catalog-count-pill">{{ catalogForm.competencyIds.length ? 'เลือกแล้ว' : 'ยังไม่เลือก' }}</span>
                            </div>
                            <input
                                v-model="catalogCompetencySearch"
                                class="inp catalog-input catalog-search"
                                placeholder="ค้นหารหัสหรือชื่อสมรรถนะ เช่น CC, MC, FC"
                            />
                            <div class="catalog-competency-list">
                                <button
                                    v-for="competency in filteredCatalogCompetencies"
                                    :key="competency.id"
                                    class="catalog-check-row"
                                    :class="{ selected: catalogForm.competencyIds.includes(competency.id) }"
                                    type="button"
                                    @click="toggleCatalogCompetency(competency.id)"
                                >
                                    <span class="catalog-checkbox" aria-hidden="true"></span>
                                    <span class="catalog-competency-copy">
                                        <span>{{ competency.cd }}</span>
                                        <strong>{{ competency.n }}</strong>
                                    </span>
                                    <span class="b bgr">{{ competency.t }}</span>
                                </button>
                                <div v-if="!filteredCatalogCompetencies.length" class="catalog-empty-line">ไม่พบสมรรถนะที่ค้นหา</div>
                            </div>

                            <div class="catalog-level-picker">
                                <div class="catalog-level-head">
                                    <div>
                                        <div class="lbl">เหมาะสำหรับผู้ที่มีระดับความคาดหวัง</div>
                                        <div class="catalog-level-help">เลือกได้มากกว่า 1 ระดับ หรือไม่ระบุก็ได้หากคู่มือไม่ได้กำหนดไว้</div>
                                    </div>
                                    <span class="catalog-count-pill">{{ catalogForm.expectedLevels.length ? `${catalogForm.expectedLevels.length} ระดับ` : 'ไม่ระบุ' }}</span>
                                </div>
                                <div class="catalog-level-options">
                                    <button
                                        v-for="level in levelOptions"
                                        :key="level.value"
                                        class="catalog-level-option"
                                        :class="{ selected: catalogForm.expectedLevels.includes(level.value) }"
                                        type="button"
                                        @click="toggleCatalogExpectedLevel(level.value)"
                                    >
                                        <span class="catalog-level-check" aria-hidden="true"></span>
                                        <span>
                                            <strong>{{ level.label }}</strong>
                                            <small>{{ level.hint }}</small>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section class="catalog-form-section">
                            <div class="catalog-section-head">
                                <div class="catalog-section-title">2. กรอกรายละเอียดหลักสูตร/กิจกรรม</div>
                            </div>
                            <div class="catalog-form-grid">
                                <div class="fg">
                                    <label class="lbl">รหัสหลักสูตร/บทเรียน</label>
                                    <input
                                        v-model="catalogForm.code"
                                        autocomplete="off"
                                        class="inp catalog-input"
                                        placeholder="เช่น TN001"
                                    />
                                </div>
                                <div class="fg">
                                    <label class="lbl">ประเภทกิจกรรม</label>
                                    <select v-model="catalogForm.methodKey" class="sel catalog-input">
                                        <option value="">ไม่ระบุประเภท</option>
                                        <option v-for="method in learningMethods" :key="method.key" :value="method.key">{{ method.label }}</option>
                                    </select>
                                </div>
                                <div class="fg">
                                    <label class="lbl">รูปแบบการฝึกอบรม</label>
                                    <select v-model="catalogForm.deliveryType" class="sel catalog-input">
                                        <option v-for="option in deliveryTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="catalog-title-field">
                                <label class="lbl">ชื่อกิจกรรม หรือหลักสูตร</label>
                                <input
                                    v-model="catalogForm.name"
                                    autocomplete="off"
                                    class="inp catalog-input"
                                    required
                                    placeholder="ชื่อกิจกรรมพัฒนา"
                                />
                            </div>
                            <div class="fg mb0 catalog-description-field">
                                <label class="lbl">คำอธิบายหลักสูตร</label>
                                <textarea
                                    v-model="catalogForm.description"
                                    class="ta catalog-textarea"
                                    placeholder="รายละเอียด เนื้อหา และวัตถุประสงค์ของการเรียนรู้"
                                ></textarea>
                            </div>
                        </section>

                        <section class="catalog-form-section">
                            <div class="catalog-section-head">
                                <div class="catalog-section-title">3. ข้อมูลด้านการบริหารจัดการ</div>
                            </div>
                            <div class="catalog-form-grid">
                                <div class="fg">
                                    <label class="lbl">แหล่งหลักสูตร</label>
                                    <select v-model="catalogForm.sourceType" class="sel catalog-input">
                                        <option value="internal">ภายในมหาวิทยาลัย</option>
                                        <option value="external">ภายนอกมหาวิทยาลัย</option>
                                    </select>
                                </div>
                                <div class="fg">
                                    <label class="lbl">ผู้จัดให้บริการ</label>
                                    <input v-model="catalogForm.provider" autocomplete="off" class="inp catalog-input" placeholder="หน่วยงาน / บริษัทผู้จัด" />
                                </div>
                                <div class="fg">
                                    <label class="lbl">ค่าใช้จ่าย (บาท)</label>
                                    <input v-model="catalogForm.cost" class="inp catalog-input" min="0" step="0.01" type="number" placeholder="0" />
                                </div>
                                <div class="fg">
                                    <label class="lbl">จำนวนชั่วโมงการเรียนรู้</label>
                                    <input v-model="catalogForm.hours" class="inp catalog-input" min="0" step="0.5" type="number" placeholder="เช่น 3" />
                                </div>
                            </div>
                        </section>

                        <section class="catalog-form-section compact">
                            <label class="catalog-active-toggle">
                                <input v-model="catalogForm.isActive" type="checkbox" />
                                <span>
                                    <strong>เปิดใช้งานหลักสูตรนี้</strong>
                                    <small>เมื่อเปิด ระบบจะนำไปแสดงเป็นหลักสูตรแนะนำให้บุคลากรที่มีคะแนนตกเกณฑ์</small>
                                </span>
                            </label>
                        </section>

                        <div class="modal-actions catalog-modal-actions">
                            <button class="btn btn-s" type="button" @click="closeModal">ยกเลิก</button>
                            <button class="btn btn-p" type="submit">{{ catalogMode === 'edit' ? 'บันทึกการแก้ไข' : 'เพิ่มกิจกรรม' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.shell {
    font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
    font-synthesis-weight: none;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    letter-spacing: 0;
}

.nav-item {
    width: calc(100% - 20px);
    max-width: calc(100% - 20px);
    box-sizing: border-box;
    border-top: 0;
    border-right: 0;
    border-bottom: 0;
    background: transparent;
    font-family: inherit;
    text-align: left;
}

.hr-menu-button {
    min-width: 40px;
    justify-content: center;
    border: 0;
    background: transparent;
    padding: 8px;
}

.content .btn.btn-p {
    background: var(--blue);
    border: 1px solid var(--blue);
    color: #fff;
    opacity: 1;
    visibility: visible;
}

.content .btn.btn-p:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: #fff;
}

.content .btn.btn-t {
    background: var(--teal);
    border: none;
    color: #fff;
}

.content .btn.btn-s {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text2);
}

.content .btn.btn-s:hover {
    background: var(--border);
}

.content .btn.btn-p {
    background: var(--blue);
    border: 1px solid var(--blue);
    color: #fff;
    opacity: 1;
    visibility: visible;
}

.content .btn.btn-p:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: #fff;
}

.content .sec-t {
    font-weight: 800;
}

.content .sec-s,
.content .muted,
.content .cs,
.content .ss {
    font-weight: 400;
}

.content .ct,
.content .sl,
.content .lbl,
.content .tbl th,
.content .fw8 {
    font-weight: 700;
}

.content .tbl td {
    font-weight: 400;
}

.hr-empty {
    display: flex;
    gap: 14px;
    align-items: center;
    min-height: 150px;
    border: 1px dashed var(--border);
    border-radius: var(--r-lg);
    background: var(--bg);
    padding: 22px;
}

.hr-empty.compact {
    min-height: 96px;
}

.catalog-summary-card {
    display: block;
}

.catalog-summary-total {
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    background: #fff;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 18px 22px;
}

.catalog-summary-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 18px;
    background: #eff6ff;
    color: var(--blue);
    font-size: 38px;
    font-weight: 900;
    line-height: 1;
}

.catalog-summary-title {
    color: var(--text);
    font-size: 18px;
    font-weight: 800;
}

.catalog-summary-sub {
    color: var(--muted);
    font-size: 13px;
    margin-top: 2px;
}

.hr-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.hr-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.hr-table-wrap {
    overflow-x: auto;
}

.catalog-filter-bar {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(160px, 0.45fr) minmax(220px, 0.55fr) minmax(130px, 0.32fr) auto;
    gap: 10px;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    background: #fbfdff;
}

.catalog-filter-search,
.catalog-filter-select {
    min-height: 40px;
    border-radius: 8px;
    font-size: 13px;
}

.catalog-filter-select.compact {
    min-width: 124px;
}

.catalog-table {
    min-width: 720px;
}

.catalog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.catalog-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    border-radius: 999px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
}

.catalog-status-badge.active {
    border: 1px solid #bbf7d0;
    background: #dcfce7;
    color: #15803d;
}

.catalog-status-badge.inactive {
    border: 1px solid #fecaca;
    background: #fee2e2;
    color: #b91c1c;
}

.content .btn.btn-s.danger {
    color: #b42318;
}

.table-empty-cell {
    min-height: 118px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--bg);
    padding: 20px;
}

.tbl td:has(.table-empty-cell) {
    padding: 0;
}

.ml-auto {
    margin-left: auto;
}

.btn:disabled,
.sel:disabled,
.inp:disabled {
    cursor: not-allowed;
    opacity: 1;
}

.btn-p:disabled {
    border: 1px solid var(--blue-md);
    background: var(--blue-lt);
    color: var(--blue);
}

.btn-t:disabled {
    border: 1px solid var(--teal-md);
    background: var(--teal-lt);
    color: var(--teal);
}

.position-hero {
    min-height: 138px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px;
    align-items: end;
    padding: 24px 26px;
    border-radius: 14px;
    border: 1px solid rgba(199, 67, 43, 0.34);
    background: linear-gradient(135deg, #171b1f 0%, #25282c 68%, #30211e 100%);
    color: #f8fbff;
    box-shadow: 0 16px 34px rgba(21, 25, 29, 0.16);
}

.position-kicker {
    color: #e68a76;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.position-title {
    color: #f8fbff;
    font-size: 26px;
}

.position-sub {
    color: rgba(248, 251, 255, 0.68);
    max-width: 760px;
    line-height: 1.65;
}

.position-hero-metrics {
    display: grid;
    grid-template-columns: repeat(2, 120px);
    gap: 10px;
}

.position-hero-metrics div {
    padding: 14px;
    border: 1px solid rgba(230, 138, 118, 0.3);
    border-radius: 10px;
    background: rgba(199, 67, 43, 0.1);
}

.position-hero-metrics span {
    display: block;
    font-size: 30px;
    font-weight: 800;
    line-height: 1;
}

.position-hero-metrics small {
    display: block;
    color: rgba(248, 251, 255, 0.64);
    font-size: 11px;
    font-weight: 700;
    margin-top: 6px;
}

.position-scope {
    display: grid;
    grid-template-columns: minmax(320px, 1fr) minmax(360px, 1fr);
    gap: 14px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
}

.round-config-bar {
    display: grid;
    grid-template-columns: minmax(260px, .7fr) minmax(360px, 1.3fr);
    align-items: end;
    gap: 18px;
    border: 1px solid #cfe0da;
    border-radius: 12px;
    background: #f5faf8;
    padding: 15px 18px;
}

.round-config-copy {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 14px;
}

.round-config-copy span {
    color: #71817b;
    font-size: 11px;
    text-align: right;
}

.round-import-note {
    margin-bottom: 12px;
    border: 1px solid #f2d2c9;
    border-radius: 8px;
    background: #fff8f5;
    padding: 11px 13px;
    color: #8b3a2b;
    font-size: 12px;
    line-height: 1.55;
}

.round-import-list {
    display: grid;
    gap: 8px;
}

.round-import-option {
    width: 100%;
    min-height: 58px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: #fff;
    padding: 10px 13px;
    color: var(--text);
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.round-import-option:hover:not(:disabled),
.round-import-option:focus-visible {
    border-color: var(--blue);
    background: #f7faff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
}

.round-import-option:disabled {
    cursor: wait;
    opacity: 0.62;
}

.round-import-option > span:first-child {
    display: grid;
    gap: 2px;
}

.round-import-option strong {
    font-size: 13px;
}

.round-import-option small {
    color: var(--text3);
    font-size: 11px;
}

.round-import-action {
    color: var(--blue);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.position-scope-label {
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
}

.position-segments {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.position-segments button {
    min-height: 36px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    color: var(--text2);
    padding: 0 14px;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.position-segments button.active {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
}

.position-empty-inline {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    border: 1px dashed var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text3);
    padding: 0 14px;
    font-size: 12px;
    font-weight: 700;
}

.position-picker {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
}

.position-warning {
    margin-top: 6px;
    color: var(--text3);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
}

.position-board {
    display: grid;
    grid-template-columns: 1.25fr repeat(4, minmax(160px, 0.65fr));
    gap: 14px;
}

.position-card {
    min-height: 104px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
}

.position-card.selected {
    border-color: var(--border);
    background: #fff;
}

.position-card-label {
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
}

.position-card-title {
    color: var(--text);
    font-size: 24px;
    font-weight: 800;
    line-height: 1.2;
}

.position-card-title.warn {
    color: var(--yellow);
}

.position-card-sub {
    color: var(--text3);
    font-size: 12px;
    font-weight: 600;
    margin-top: 7px;
}

.type-breakdown {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.type-breakdown div {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #f8fafc;
    padding: 8px;
}

.type-breakdown span {
    display: block;
    color: var(--text3);
    font-size: 10px;
    font-weight: 800;
}

.type-breakdown strong {
    display: block;
    margin-top: 2px;
    color: var(--text);
    font-size: 22px;
    font-weight: 900;
    line-height: 1;
}

.fc-rule-control {
    display: grid;
    grid-template-columns: minmax(80px, 1fr) auto;
    gap: 8px;
    align-items: center;
}

.fc-rule-input {
    min-height: 38px;
    font-size: 18px;
    font-weight: 800;
}

.position-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(360px, 0.75fr);
    gap: 14px;
    align-items: start;
}

.position-panel {
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--sh);
    overflow: hidden;
}

.position-panel-head {
    min-height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    background: #fff;
}

.assigned-list {
    display: grid;
    gap: 10px;
    padding: 14px;
}

.assigned-empty {
    min-height: 220px;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 8px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    background: var(--bg);
    text-align: center;
}

.assigned-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: #fff;
}

.assigned-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.danger-text {
    color: var(--red);
}

.empty-symbol {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 76px;
    min-height: 32px;
    border-radius: 999px;
    background: #fff;
    color: var(--blue);
    box-shadow: var(--sh);
    font-size: 12px;
    font-weight: 800;
}

.dictionary {
    position: sticky;
    top: 14px;
}

.dictionary-tools {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 132px;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
}

.dictionary-list {
    display: grid;
    gap: 10px;
    max-height: 560px;
    overflow-y: auto;
    padding: 14px;
}

.dictionary-empty {
    min-height: 340px;
}

.dictionary-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    min-height: 96px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    transition: background .16s ease, border-color .16s ease, box-shadow .16s ease;
}

.dictionary-row:hover {
    background: #f8fbff;
    border-color: #bcd0ef;
    box-shadow: 0 8px 18px rgba(15, 42, 83, 0.06);
}

.dictionary-main {
    min-width: 0;
}

.dictionary-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.dictionary-detail-button {
    border: 1px solid #cfe0f7;
    border-radius: 999px;
    background: #f8fbff;
    color: var(--blue);
    cursor: pointer;
    font-size: 12px;
    font-weight: 800;
    margin-left: 4px;
    padding: 4px 10px;
    white-space: nowrap;
    transition: background .16s ease, border-color .16s ease, color .16s ease;
}

.dictionary-detail-button:hover {
    background: #eef5ff;
    border-color: #a9c7f2;
    color: var(--blue2);
}

.dictionary-add-button {
    min-width: 74px;
    justify-content: center;
}

.dictionary-code {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    color: var(--text3);
    font-size: 12px;
    font-weight: 800;
}

.dictionary-name {
    color: var(--text);
    font-size: 14px;
    font-weight: 800;
    line-height: 1.4;
    margin-top: 7px;
    overflow-wrap: anywhere;
}

.dictionary-detail {
    color: var(--text3);
    font-size: 12px;
    line-height: 1.55;
    margin-top: 4px;
}

.truncate-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.hr-modal {
    font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
}

.hr-modal .btn.btn-p {
    background: var(--blue);
    border: 1px solid var(--blue);
    color: #fff;
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18);
}

.hr-modal .btn.btn-p:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}

.hr-modal .btn.btn-s {
    background: #fff;
    border: 1px solid var(--border);
    color: var(--text2);
}

.hr-modal-box {
    width: min(920px, calc(100vw - 36px));
    max-height: calc(100vh - 40px);
    overflow: auto;
}

.catalog-modal-box {
    width: min(1180px, calc(100vw - 36px));
}

.catalog-form {
    display: grid;
    gap: 16px;
}

.catalog-form-section {
    display: grid;
    gap: 14px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
}

.catalog-form-section.compact {
    padding: 14px 16px;
}

.catalog-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.catalog-section-title {
    color: var(--text);
    font-size: 15px;
    font-weight: 900;
}

.catalog-section-sub {
    color: var(--text3);
    font-size: 12px;
    font-weight: 700;
    margin-top: 2px;
}

.catalog-count-pill {
    flex: 0 0 auto;
    border: 1px solid #cfe0f7;
    border-radius: 999px;
    background: var(--blue-lt);
    color: var(--blue);
    font-size: 12px;
    font-weight: 900;
    padding: 6px 10px;
}

.catalog-title-field {
    display: grid;
    gap: 8px;
}

.catalog-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
}

.catalog-input {
    min-height: 48px;
    border-radius: 8px;
    font-size: 15px;
}

.catalog-textarea {
    min-height: 104px;
    border-radius: 8px;
    font-size: 15px;
    line-height: 1.6;
}

.catalog-search {
    width: 100%;
}

.catalog-competency-list {
    max-height: 230px;
    display: grid;
    gap: 8px;
    overflow: auto;
    padding-right: 4px;
}

.catalog-check-row {
    width: 100%;
    display: grid;
    grid-template-columns: 22px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    min-height: 54px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--text);
    padding: 10px 12px;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.catalog-check-row.selected {
    border-color: var(--blue-md);
    background: #f7fbff;
}

.catalog-checkbox {
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 5px;
    background: #fff;
}

.catalog-check-row.selected .catalog-checkbox {
    border-color: var(--blue);
    background: var(--blue);
}

.catalog-check-row.selected .catalog-checkbox::before {
    content: "";
    width: 8px;
    height: 5px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: translateY(-1px) rotate(-45deg);
}

.catalog-competency-copy {
    min-width: 0;
    display: grid;
    gap: 2px;
}

.catalog-competency-copy span {
    color: var(--text3);
    font-size: 11px;
    font-weight: 900;
}

.catalog-competency-copy strong {
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
    line-height: 1.35;
}

.catalog-empty-line {
    min-height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed var(--border);
    border-radius: 10px;
    color: var(--text3);
    font-size: 13px;
    font-weight: 700;
}

.catalog-level-picker {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #f8fbff;
}

.catalog-level-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.catalog-level-help {
    color: var(--text3);
    font-size: 12px;
    font-weight: 700;
    margin-top: 3px;
}

.catalog-level-options {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
}

.catalog-level-option {
    min-height: 76px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--text2);
    padding: 12px;
    font: inherit;
    text-align: left;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(15, 45, 91, 0.04);
}

.catalog-level-option.selected {
    border-color: var(--blue);
    background: #eef6ff;
    color: var(--blue);
}

.catalog-level-check {
    width: 18px;
    height: 18px;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    margin-top: 2px;
}

.catalog-level-option.selected .catalog-level-check {
    border-color: var(--blue);
    background: var(--blue);
}

.catalog-level-option.selected .catalog-level-check::before {
    content: "";
    width: 7px;
    height: 4px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: translateY(-1px) rotate(-45deg);
}

.catalog-level-option strong {
    display: block;
    color: var(--text);
    font-size: 13px;
    font-weight: 900;
    line-height: 1.25;
}

.catalog-level-option.selected strong {
    color: var(--blue);
}

.catalog-level-option small {
    display: block;
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
    line-height: 1.35;
    margin-top: 4px;
}

.catalog-active-toggle {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    cursor: pointer;
}

.catalog-active-toggle input {
    width: 18px;
    height: 18px;
    margin-top: 3px;
    accent-color: var(--blue);
}

.catalog-active-toggle span {
    display: grid;
    gap: 2px;
}

.catalog-active-toggle strong {
    color: var(--text);
    font-size: 14px;
    font-weight: 900;
}

.catalog-active-toggle small {
    color: var(--text3);
    font-size: 12px;
    font-weight: 700;
}

.competency-detail {
    display: grid;
    gap: 16px;
}

.detail-overview,
.detail-behavior-panel {
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
}

.detail-overview {
    padding: 18px;
}

.detail-section-label {
    color: var(--text);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .02em;
}

.detail-title {
    color: var(--text);
    font-size: 21px;
    font-weight: 900;
    line-height: 1.35;
}

.detail-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.detail-meta-row span {
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text2);
    font-size: 12px;
    font-weight: 800;
    padding: 6px 10px;
}

.detail-description {
    margin-top: 14px;
    color: var(--text3);
    font-size: 14px;
    font-weight: 600;
    line-height: 1.75;
}

.detail-copy {
    color: var(--text2);
    font-size: 13px;
    font-weight: 650;
    line-height: 1.65;
}

.detail-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.detail-summary-card {
    min-width: 0;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: #fff;
    grid-column: 1 / -1;
}

.detail-summary-label {
    color: var(--text3);
    font-size: 12px;
    font-weight: 800;
}

.detail-summary-value {
    margin-top: 6px;
    color: var(--text);
    font-size: 16px;
    font-weight: 900;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.detail-panel-head {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
}

.detail-panel-sub {
    color: var(--text3);
    font-size: 12px;
    font-weight: 700;
    margin-top: 3px;
}

.detail-levels {
    display: grid;
    gap: 10px;
    padding: 12px;
    background: #f8fafc;
}

.detail-level {
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.detail-level:hover {
    border-color: #cfe0f7;
}

.detail-level-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
}

.level-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex: 0 0 auto;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
    font-size: 15px;
    font-weight: 900;
}

.level-head-content {
    flex: 1 1 auto;
    min-width: 0;
}

.level-indicator-count {
    flex: 0 0 auto;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
}

.detail-level:last-child {
    border-bottom: 1px solid var(--border);
}

.detail-level.open {
    border-color: #bfd7ff;
    background: #fff;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.08);
}

.detail-level-head {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 32px;
    gap: 10px;
    align-items: center;
    padding: 12px 16px;
    border: 0;
    background: #fff;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
}

.detail-level-number {
    color: var(--text);
    font-size: 14px;
    font-weight: 900;
}

.detail-indicator {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-top: 1px solid #f1f5f9;
}

.detail-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    justify-self: end;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--text3);
    transition: transform .16s ease, color .16s ease, border-color .16s ease;
}

.detail-toggle::before {
    content: "";
    width: 8px;
    height: 8px;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: translateY(-2px) rotate(45deg);
}

.detail-toggle.open {
    color: var(--blue);
    border-color: #cfe0f7;
    transform: rotate(180deg);
}

.detail-indicators {
    display: grid;
    border-top: 1px solid var(--border);
    background: #fbfdff;
}

.detail-indicator-head,
.detail-indicator {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) 70px;
    gap: 10px;
    align-items: start;
}

.detail-indicator-head {
    padding: 9px 16px;
    background: #f1f5f9;
    border-bottom: 1px solid #eef3f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
}

.indicator-number {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 900;
}

.indicator-text {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 13px;
    line-height: 1.65;
    color: var(--text);
}

.detail-weight-pill {
    flex: 0 0 auto;
    padding: 4px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 11px;
    font-weight: 800;
}

.no-indicators {
    padding: 16px;
    color: var(--color-text-muted);
    font-size: 13px;
    font-style: italic;
}

.detail-indicator {
    padding: 12px 16px;
    border-bottom: 1px solid #eef3f9;
}

.detail-indicator:last-child {
    border-bottom: 0;
}

.detail-index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: #64748b;
    font-size: 12px;
    font-weight: 900;
}

.detail-weight {
    color: var(--blue);
    font-size: 13px;
    font-weight: 900;
    text-align: right;
}

.detail-no-indicators {
    color: var(--text3);
    font-size: 13px;
    font-weight: 700;
    padding: 12px 16px;
    border-top: 1px solid var(--border);
}

.detail-empty {
    min-height: 160px;
}

.modal-close {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border);
    border-radius: var(--r);
    background: #fff;
    color: var(--text3);
    font: inherit;
    font-size: 22px;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
}

.modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.modal-span {
    grid-column: 1 / -1;
}

.upload-box {
    display: grid;
    gap: 12px;
    align-items: center;
    padding: 16px;
    border: 1px dashed var(--border);
    border-radius: var(--r-lg);
    background: var(--bg);
}

.upload-box .inp {
    background: #fff;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
}

@media (max-width: 1100px) {
    .position-layout,
    .position-scope,
    .position-board,
    .position-hero,
    .round-config-bar {
        grid-template-columns: 1fr;
    }

    .round-config-copy {
        justify-content: space-between;
    }

    .round-config-copy span {
        text-align: left;
    }

    .dictionary {
        position: static;
    }

    .position-hero-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .catalog-filter-bar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .catalog-level-options {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .shell {
        height: auto;
        min-height: 100vh;
        flex-direction: column;
        overflow: visible;
    }

    .sidebar {
        position: static;
        width: 100%;
    }

    .main {
        min-height: 0;
    }

    .content {
        overflow: visible;
    }

    .g3 {
        grid-template-columns: 1fr;
    }

    .hr-page-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .hr-actions {
        justify-content: flex-start;
    }

    .round-config-copy {
        align-items: stretch;
        flex-direction: column;
    }

    .position-picker,
    .dictionary-tools,
    .modal-grid,
    .detail-summary-grid {
        grid-template-columns: 1fr;
    }

    .catalog-form-grid,
    .catalog-level-options,
    .catalog-filter-bar {
        grid-template-columns: 1fr;
    }

    .catalog-summary-total {
        align-items: flex-start;
        flex-direction: column;
    }

    .dictionary-row {
        grid-template-columns: 1fr;
        min-height: 0;
        padding: 14px;
    }

    .dictionary-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .dictionary-detail-button {
        text-align: left;
    }

    .dictionary-add-button {
        width: 100%;
    }

    .detail-level-head,
    .detail-indicator-head,
    .detail-indicator {
        grid-template-columns: 1fr;
    }

    .detail-indicator-head {
        display: none;
    }

    .detail-weight {
        text-align: left;
    }

    .modal-actions {
        justify-content: stretch;
    }

    .modal-actions .btn {
        justify-content: center;
        flex: 1;
    }

    .position-hero {
        padding: 20px;
    }

    .position-title {
        font-size: 22px;
    }
}
</style>
