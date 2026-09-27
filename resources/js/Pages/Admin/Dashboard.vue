<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
import {
    NAV_CONFIG,
    PAGE_TITLES,
    ROLES_CONFIG,
} from '../../data';
import AdminDict from './AdminDict.vue';
import AdminIdpTools from './AdminIdpTools.vue';
import AdminOrgStructure from './AdminOrgStructure.vue';
import AdminUsers from './AdminUsers.vue';
import EmployeeAssess from '../Employee/EmployeeAssess.vue';
import EmployeeGap from '../Employee/EmployeeGap.vue';
import EmployeeIDP from '../Employee/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Employee/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Employee/EmployeeProgress.vue';
import HeadDashboard from '../Head/Dashboard.vue';

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'จัดการผู้ใช้งาน',
    },
});

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const supportOrgFromGroups = (groups = {}) => Object.fromEntries(
    Object.entries(groups || {}).map(([dept, works]) => [
        dept,
        (Array.isArray(works) ? works : []).map((work) => ({ work, units: [] })),
    ]),
);
const adminPageStorageKey = 'admin-active-page';
const requestedPage = ref(typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null);
const savedAdminPage = typeof window !== 'undefined'
    ? window.sessionStorage.getItem(adminPageStorageKey)
        || window.sessionStorage.getItem('cidp.admin.activePage')
    : null;

const rememberedAdminState = useRemember({
    showSidebar: true,
    activePage: requestedPage.value || savedAdminPage || 'admin-users',
}, 'AdminDashboard');
const showSidebar = computed({
    get: () => rememberedAdminState.value.showSidebar !== false,
    set: (value) => {
        rememberedAdminState.value.showSidebar = value;
    },
});
const activePage = computed({
    get: () => rememberedAdminState.value.activePage,
    set: (value) => {
        rememberedAdminState.value.activePage = value;
    },
});
const currentRole = ref('admin');
const page = usePage();
const competencies = ref(clone(page.props.competencies || []));
const users = ref(clone(page.props.users || []));
const activeModal = ref(null);
const editingUserKey = ref(null);
const organizationDirty = ref(false);
const isSavingUser = ref(false);
const isChangingPassword = ref(false);
const showNewPassword = ref(false);
const showPasswordConfirmation = ref(false);
const issuedCredentials = ref(null);
const supervisorSearch = ref('');
const evaluator2Search = ref('');
const showReviewerModal = ref(false);
const activeReviewerTemplateModal = ref('');
const showAssessmentTemplateCreate = ref(false);
const activeAssessmentTemplateId = ref(null);
const editingAssessmentTemplateId = ref(null);
const assessmentTemplateMemberPick = ref('');
const assessmentTemplateError = ref('');
const isSavingAssessmentTemplate = ref(false);
const reviewerSearchTerms = ref({});
const activeReviewerDropdown = ref(null);
const assessmentTemplateForm = ref({
    name: '',
    description: '',
    reviewer_ids: [],
    assignment_user_ids: [],
    reviewer_pick: '',
    user_pick: '',
});
const userForm = ref({
    db_id: null,
    sso: '',
    t: '',
    n: '',
    fn: '',
    ln: '',
    fe: '',
    le: '',
    em: '',
    username: '',
    password: '',
    password_confirmation: '',
    ph: '',
    w: '',
    d: '',
    dept: '',
    job: '',
    unit: '',
    p: '',
    l: '',
    r: 'employee',
    reviewer_template_id: '',
    idp_reviewer_template_id: '',
    reviewer_ids: [],
    idp_reviewer_ids: [],
    act: true,
    structureStatus: 'ok',
    structureIssues: [],
});

const worklines = ref(clone(page.props.worklines || []));
const jobFamiliesByWorkline = ref(clone(page.props.jobFamiliesByWorkline || {}));
const academicPositions = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายวิชาการ'] || {})));
const adminDepts = ref(clone(Object.keys(jobFamiliesByWorkline.value['สายงานบริหาร'] || {})));
const competencyTypes = ref(clone(page.props.competencyTypes || []));
const reviewerChainTemplates = computed(() => page.props.reviewerChainTemplates || []);
const assessmentReviewerTemplates = computed(() =>
    reviewerChainTemplates.value.filter((template) => (template.chainType || 'assessment') === 'assessment'),
);
const idpReviewerTemplates = computed(() =>
    reviewerChainTemplates.value.filter((template) => template.chainType === 'idp'),
);
const activeReviewerTemplateType = computed(() =>
    activeReviewerTemplateModal.value === 'idp' ? 'idp' : 'assessment',
);
const activeReviewerTemplateList = computed(() =>
    activeReviewerTemplateType.value === 'idp'
        ? idpReviewerTemplates.value
        : assessmentReviewerTemplates.value,
);
const selectedAssessmentTemplate = computed(() =>
    activeReviewerTemplateList.value.find((template) => Number(template.id) === selectedEvaluatorId(activeAssessmentTemplateId.value))
    || null,
);
const editingAssessmentTemplate = computed(() =>
    activeReviewerTemplateList.value.find((template) => Number(template.id) === selectedEvaluatorId(editingAssessmentTemplateId.value))
    || null,
);
const isEditingAssessmentTemplate = computed(() => Boolean(editingAssessmentTemplate.value));
const isEditingSelectedAssessmentTemplate = computed(() =>
    Boolean(selectedAssessmentTemplate.value)
    && Number(selectedAssessmentTemplate.value.id) === selectedEvaluatorId(editingAssessmentTemplateId.value),
);
const activeReviewerTemplateTitle = computed(() =>
    activeReviewerTemplateType.value === 'idp'
        ? 'ลำดับการทำ IDP'
        : 'ลำดับในการประเมิน',
);
const activeReviewerTemplateSubtitle = computed(() =>
    activeReviewerTemplateType.value === 'idp'
        ? 'กำหนดลำดับสำหรับตอนส่งแผน IDP'
        : 'กำหนดลำดับสำหรับ workflow การประเมินสมรรถนะ',
);
const supportPositionGroups = ref(clone(jobFamiliesByWorkline.value['สายสนับสนุน'] || page.props.supportPositionGroups || {}));
const supportOrg = ref(clone(page.props.supportOrg || supportOrgFromGroups(supportPositionGroups.value)));
const supportPositions = ref([]);
const adminPositions = ref(clone(page.props.adminJobFamilies || []));
const levelsByWorkline = ref(clone(page.props.levelsByWorkline || {}));
const levelExpectationsByWorkline = ref(clone(page.props.levelExpectationsByWorkline || {}));
const academicRanks = ref(clone(levelsByWorkline.value['สายวิชาการ'] || []));
const supportRanks = ref(clone(levelsByWorkline.value['สายสนับสนุน'] || []));
const learningMethods = ref(clone(page.props.learningMethods || []));
const hrCatalogItems = computed(() => page.props.hrCatalogItems || []);
const currentUserIdp = computed(() => page.props.currentUserIdp || null);
const idpLearningMethods = computed(() => page.props.idpLearningMethods || []);
const idpDeliveryTypeSettings = computed(() => page.props.idpDeliveryTypeSettings || []);
const roleLabelsByKey = {
    admin: 'ผู้ดูแลระบบ',
    supervisor: 'หัวหน้าหน่วย',
    dept_head: 'หัวหน้างาน',
    division_head: 'หัวหน้าฝ่าย',
    academic_department_head: 'หัวหน้าภาควิชา',
    employee: 'บุคลากร',
    hr: 'งานทรัพยากรบุคคล',
    dean: 'ผู้บริหารคณะ',
};
const roleOptions = computed(() => (page.props.roles || [
    { id: 0, key: 'admin', label: 'ผู้ดูแลระบบ' },
    { id: 1, key: 'supervisor', label: 'หัวหน้าหน่วย' },
    { id: 2, key: 'dept_head', label: 'หัวหน้างาน' },
    { id: 6, key: 'division_head', label: 'หัวหน้าฝ่าย' },
    { id: 7, key: 'academic_department_head', label: 'หัวหน้าภาควิชา' },
    { id: 3, key: 'employee', label: 'บุคลากร' },
    { id: 4, key: 'hr', label: 'งานทรัพยากรบุคคล' },
    { id: 5, key: 'dean', label: 'ผู้บริหารคณะ' },
]).map((role) => ({
    ...role,
    key: normalizeUserRoleKey(role.key),
    label: roleLabelsByKey[normalizeUserRoleKey(role.key)] || role.label,
})));

const supportDeptsList = computed(() => Object.keys(supportOrg.value));
const legacyDeptOption = computed(() => {
    const department = userForm.value.dept;

    return department && !supportDeptsList.value.includes(department) ? department : '';
});
const supportJobFamilies = computed(() => Object.keys(supportPositionGroups.value));
const normalizeWorklineName = (name = '') => name.replace(/^สายงาน\s*/, '').replace(/^สาย\s*/, '').trim();
const normalizeOptionName = (name = '') => String(name || '').trim();
const optionIncludes = (options, value) => {
    const normalizedValue = normalizeOptionName(value);

    return Boolean(normalizedValue)
        && options.some((option) => normalizeOptionName(option) === normalizedValue);
};
const selectedWorklineKind = computed(() => normalizeWorklineName(userForm.value.w));
const selectedWorklineGroups = computed(() => jobFamiliesByWorkline.value[userForm.value.w] || {});
const levelOptionsFromDatabase = computed(() => {
    return levelsByWorkline.value[userForm.value.w] || [];
});
const isAcademicWorkline = computed(() => selectedWorklineKind.value === 'วิชาการ');
const isSupportWorkline = computed(() => selectedWorklineKind.value === 'สนับสนุน');
const isAdminWorkline = computed(() => selectedWorklineKind.value === 'บริหาร');
const supportWorksForDepartment = (departmentName = '') => {
    const selectedDepartment = normalizeOptionName(departmentName);
    const matchedDepartment = Object.keys(supportOrg.value || {}).find((department) =>
        normalizeOptionName(department) === selectedDepartment,
    );

    return matchedDepartment ? supportOrg.value[matchedDepartment] || [] : [];
};
const supportWorkForDepartment = (departmentName = '', workName = '') => {
    const selectedWork = normalizeOptionName(workName);

    return supportWorksForDepartment(departmentName).find((item) =>
        normalizeOptionName(item.work) === selectedWork,
    );
};
const supportUnitNamesForWork = (departmentName = '', workName = '') =>
    (supportWorkForDepartment(departmentName, workName)?.units || [])
        .map((unit) => typeof unit === 'string' ? unit : unit.name)
        .map(normalizeOptionName)
        .filter(Boolean);
const selectedDeptWorks = computed(() => supportWorksForDepartment(userForm.value.dept));
const incompleteLegacySupportPath = computed(() => {
    if (!userForm.value.db_id || !isSupportWorkline.value || organizationDirty.value) return '';

    const path = userForm.value.d;
    return path && path.split(' > ').filter(Boolean).length < 3 ? path : '';
});
const jobOptions = computed(() => {
    if (!userForm.value.w) return [];
    if (isSupportWorkline.value) {
        return Array.from(new Set(
            selectedDeptWorks.value
                .map((item) => normalizeOptionName(item.work))
                .filter(Boolean),
        ));
    }

    return Object.keys(selectedWorklineGroups.value);
});
const legacyJobOption = computed(() => {
    const job = userForm.value.job;

    return job && !optionIncludes(jobOptions.value, job) ? job : '';
});
const selectedSupportWork = computed(() =>
    supportWorkForDepartment(userForm.value.dept, userForm.value.job),
);
const unitOptions = computed(() => {
    if (isSupportWorkline.value) return (selectedSupportWork.value?.units || []).map((unit) => typeof unit === 'string' ? unit : unit.name);

    return [];
});
const canPickPosition = computed(() => Boolean(userForm.value.w && normalizeOptionName(userForm.value.job)));
const legacyUnitOption = computed(() => {
    const unit = userForm.value.unit;

    return unit && !unitOptions.value.includes(unit) ? unit : '';
});
const positionOptions = computed(() => {
    if (!canPickPosition.value) return [];

    if (isSupportWorkline.value) {
        const globalPositions = Object.values(selectedWorklineGroups.value).flat().filter(Boolean);
        const workPositions = (selectedSupportWork.value?.units || [])
            .flatMap((unit) => typeof unit === 'object' ? unit.positions || [] : [])
            .filter(Boolean);

        return Array.from(new Set(
            [...globalPositions, ...workPositions],
        )).sort((a, b) => a.localeCompare(b, 'th'));
    }

    const selectedJob = normalizeOptionName(userForm.value.job);
    const matchedJob = Object.keys(selectedWorklineGroups.value).find((job) =>
        normalizeOptionName(job) === selectedJob,
    );

    return matchedJob ? selectedWorklineGroups.value[matchedJob] || [] : [];
});
const positionEmptyLabel = computed(() => {
    if (!userForm.value.w) return 'เลือกสายงานก่อนเพื่อดูตำแหน่ง';
    if (!userForm.value.job) return `เลือก${isSupportWorkline.value ? 'งาน' : 'ภาควิชา'}ก่อนเพื่อดูตำแหน่ง`;

    return `ยังไม่มีตำแหน่งใน${isSupportWorkline.value ? 'งาน' : 'ภาควิชา'}นี้`;
});
const positionEmptyHelp = computed(() => {
    if (!userForm.value.w) return 'เลือกสายงานเพื่อดูตำแหน่งของสายงานนั้น';
    if (!userForm.value.job) return `เลือก${isSupportWorkline.value ? 'งาน' : 'ภาควิชา'}เพื่อเปิดรายการตำแหน่ง`;

    return `กรุณาให้ Admin เพิ่มตำแหน่งใน${isSupportWorkline.value ? 'งาน' : 'ภาควิชา'}นี้ก่อนกำหนดผู้ใช้`;
});
const legacyPositionOption = computed(() => {
    const position = userForm.value.p;

    return position && !optionIncludes(positionOptions.value, position) ? position : '';
});
const levelOptions = computed(() => {
    if (!userForm.value.w) return [];

    return levelOptionsFromDatabase.value;
});
const legacyLevelOption = computed(() => {
    const level = userForm.value.l;

    return level && !optionIncludes(levelOptions.value, level) ? level : '';
});
const currentPageTitle = computed(() => PAGE_TITLES[activePage.value] || props.pageTitle);
const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const fcTopicApprovalModule = computed(() => page.props.fcTopicApprovalModule || { enabled: false, items: [] });
const assessmentApprovalModule = computed(() => page.props.assessmentApprovalModule || { enabled: false, items: [] });
const idpReviewModule = computed(() => page.props.idpReviewModule || { enabled: false, assignmentCount: 0 });
const visibleAdminPageIds = new Set([
    'admin-users',
    'admin-org-structure',
    'admin-dict',
    'admin-idp-tools',
    'admin-fc-topic-review',
    'admin-assessment-review',
    'admin-team-assessment',
    'admin-idp-review',
]);
const currentNavConfig = computed(() => {
    const sections = NAV_CONFIG[currentRole.value] || [];

    if (currentRole.value !== 'admin') return sections;

    const visibleSections = sections
        .map((section) => ({
            ...section,
            items: (section.items || []).filter((item) => visibleAdminPageIds.has(item.id)),
        }))
        .filter((section) => section.items.length > 0);
    const assignedItems = [
        ...((fcTopicApprovalModule.value.enabled || assessmentApprovalModule.value.enabled) ? [{ id: 'admin-assessment-review', ic: '', lb: 'อนุมัติการประเมิน' }] : []),
        ...(assessmentApprovalModule.value.enabled ? [{ id: 'admin-team-assessment', ic: '', lb: 'ผลการประเมินของทีม' }] : []),
        ...(idpReviewModule.value.enabled ? [{ id: 'admin-idp-review', ic: '', lb: 'อนุมัติแผนและผล IDP' }] : []),
    ];

    return assignedItems.length
        ? [...visibleSections, { sec: 'งานที่ได้รับมอบหมาย', items: assignedItems }]
        : visibleSections;
});
const implementedAdminPages = new Set([
    'emp-assess',
    'emp-gap', 
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
    'admin-users',
    'admin-org-structure',
    'admin-dict',
    'admin-idp-tools',
    'admin-fc-topic-review',
    'admin-assessment-review',
    'admin-idp-review',
]);

watchEffect(() => {
    if (Array.isArray(page.props.users)) {
        users.value = clone(page.props.users);
    }
});

watchEffect(() => {
    if (Array.isArray(page.props.worklines)) {
        worklines.value = clone(page.props.worklines);
    }

    if (page.props.jobFamiliesByWorkline && typeof page.props.jobFamiliesByWorkline === 'object') {
        jobFamiliesByWorkline.value = clone(page.props.jobFamiliesByWorkline);
    }

    if (Array.isArray(page.props.academicJobFamilies)) {
        academicPositions.value = clone(page.props.academicJobFamilies);
    }

    if (Array.isArray(page.props.adminJobFamilies)) {
        adminDepts.value = clone(page.props.adminJobFamilies);
        adminPositions.value = clone(page.props.adminJobFamilies);
    }

    if (page.props.supportPositionGroups && typeof page.props.supportPositionGroups === 'object') {
        supportPositionGroups.value = clone(page.props.supportPositionGroups);
    }

    if (page.props.supportOrg && typeof page.props.supportOrg === 'object') {
        supportOrg.value = clone(page.props.supportOrg);
    } else {
        supportOrg.value = supportOrgFromGroups(supportPositionGroups.value);
    }

    if (page.props.levelsByWorkline && typeof page.props.levelsByWorkline === 'object') {
        levelsByWorkline.value = clone(page.props.levelsByWorkline);
    }

    if (page.props.levelExpectationsByWorkline && typeof page.props.levelExpectationsByWorkline === 'object') {
        levelExpectationsByWorkline.value = clone(page.props.levelExpectationsByWorkline);
    }

});

watchEffect(() => {
    if (requestedPage.value && implementedAdminPages.has(requestedPage.value) && visibleAdminPageIds.has(requestedPage.value)) {
        activePage.value = requestedPage.value;
        requestedPage.value = null;
    }

    if (!implementedAdminPages.has(activePage.value) || !visibleAdminPageIds.has(activePage.value)) {
        activePage.value = 'admin-users';
    }

    if (typeof rememberedAdminState.value.showSidebar !== 'boolean') {
        rememberedAdminState.value.showSidebar = true;
    }

    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(adminPageStorageKey, activePage.value);
    }
});
const currentProfileUser = computed(() =>
    page.props.currentUser
    || users.value.find((user) => user.r === currentRole.value)
    || users.value[0]
    || {
        n: page.props.auth?.user?.name || currentRoleData.value.name,
        t: '',
        sso: page.props.auth?.user?.id || 'current-user',
        p: currentRoleData.value.pos,
        r: currentRole.value,
        act: true,
    },
);
const normalizeUserRoleKey = (role = '') => ({
    manager_dept: 'dept_head',
    manager: 'dean',
}[role] || role);
const roleLabel = (role = '') => {
    const normalizedRole = normalizeUserRoleKey(role);
    return roleOptions.value.find((option) => option.key === normalizedRole)?.label
        || normalizedRole
        || 'ไม่ระบุบทบาท';
};
const primaryJobFamily = (department = '') => department.split(' > ')[0]?.trim() || 'ไม่มีกลุ่มงาน';
const personOption = (user) => ({
    key: user.db_id || user.sso || `${user.t || ''}${user.n}`,
    value: user.db_id,
    label: `${user.t || ''}${user.n} · ${primaryJobFamily(user.d)} · ${roleLabel(user.r)}`,
});

const evaluatorOptions = computed(() =>
    users.value
        .filter((user) => user.db_id)
        .map((user) => ({
            key: user.db_id,
            value: Number(user.db_id),
            name: user.n,
            displayName: `${user.t || ''}${user.n}`,
            p: user.p || '',
            w: user.w || '',
            d: user.d || '',
            r: normalizeUserRoleKey(user.r || ''),
            label: `${user.t || ''}${user.n}${user.p ? ` · ${user.p}` : ''}`,
            searchText: [
                user.db_id,
                user.sso,
                user.t,
                user.n,
                user.p,
                user.w,
                user.d,
                user.r,
            ].filter(Boolean).join(' ').toLowerCase(),
        })),
);
const selectedEvaluatorId = (value) => {
    const id = Number(value);

    return Number.isFinite(id) && id > 0 ? id : '';
};
const selectedReviewerIds = computed(() =>
    (userForm.value.reviewer_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedIdpReviewerIds = computed(() =>
    (userForm.value.idp_reviewer_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const effectiveIdpReviewerIds = computed(() =>
    selectedIdpReviewerIds.value.length
        ? selectedIdpReviewerIds.value
        : (selectedEvaluatorId(userForm.value.idp_reviewer_template_id) ? [] : selectedReviewerIds.value),
);
const selectedTemplateReviewerIds = computed(() =>
    (assessmentTemplateForm.value.reviewer_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedTemplateUserIds = computed(() =>
    (assessmentTemplateForm.value.assignment_user_ids || [])
        .map((id) => selectedEvaluatorId(id))
        .filter(Boolean),
);
const selectedReviewerTemplate = computed(() =>
    assessmentReviewerTemplates.value.find((template) => Number(template.id) === selectedEvaluatorId(userForm.value.reviewer_template_id))
    || null,
);
const selectedIdpReviewerTemplate = computed(() =>
    idpReviewerTemplates.value.find((template) => Number(template.id) === selectedEvaluatorId(userForm.value.idp_reviewer_template_id))
    || null,
);
const jobFamilyFromDepartment = (department = '') => department.split(' > ')[0]?.trim() || '';
const templateAssignmentLabel = (assignment) => {
    const scopeLabels = {
        default: 'ค่าเริ่มต้น',
        workline: 'สายงาน',
        job_family: 'กลุ่มงาน',
        position: 'ตำแหน่ง',
        user: 'รายคน',
    };

    return assignment.scopeType === 'default'
        ? scopeLabels.default
        : `${scopeLabels[assignment.scopeType] || assignment.scopeType}: ${assignment.scopeValue || assignment.userId || '-'}`;
};
const templateStepLabel = (step) => {
    if (step.reviewerId) {
        return evaluatorFromId(step.reviewerId)?.label || `ผู้ใช้ #${step.reviewerId}`;
    }

    return step.label || 'ผู้ประเมิน';
};
const templateChainSummary = (template) => {
    const steps = template.steps || [];
    if (!steps.length) return 'ยังไม่ได้กำหนดผู้ประเมิน';

    return steps.map(templateStepLabel).join(' -> ');
};
const templateAssignmentSummary = (template) => {
    const assignments = (template.assignments || [])
        .filter((assignment) => assignment.scopeType === 'user' && assignment.userId);
    if (!assignments.length) return 'ยังไม่ได้ผูกผู้ใช้';

    return assignments.map((assignment) => {
        return evaluatorFromId(assignment.userId)?.label || `ผู้ใช้ #${assignment.userId}`;
    }).join(' · ');
};
const templateAssignedUserIds = (template) =>
    (template?.assignments || [])
        .filter((assignment) => assignment.scopeType === 'user' && assignment.userId)
        .map((assignment) => Number(assignment.userId))
        .filter(Boolean);
const templateAssignedUsers = (template) => {
    const ids = new Set(templateAssignedUserIds(template));

    return users.value
        .filter((user) => ids.has(Number(user.db_id)))
        .map((user) => ({
            id: Number(user.db_id),
            name: `${user.t || ''}${user.n}`,
            position: user.p || 'ไม่ระบุตำแหน่ง',
            department: primaryJobFamily(user.d || ''),
        }));
};
const selectedAssessmentTemplateUserOptions = computed(() => {
    const template = selectedAssessmentTemplate.value;
    if (!template) return [];

    const blocked = new Set([
        ...templateAssignedUserIds(template),
        ...(template.steps || [])
            .map((step) => selectedEvaluatorId(step.reviewerId))
            .filter(Boolean),
    ]);

    return evaluatorOptions.value.filter((person) => !blocked.has(person.value));
});
const reviewerChainTypeLabel = (chainType = 'assessment') => ({
    assessment: 'ลำดับในการประเมิน',
    idp: 'ลำดับการทำ IDP',
}[chainType] || chainType);
const reviewerTemplateDescription = computed(() => {
    if (!selectedReviewerTemplate.value) return 'ยังไม่ได้เลือก template';

    const stepText = (selectedReviewerTemplate.value.steps || [])
        .map((step) => `${step.step}. ${templateStepLabel(step)}`)
        .join(' -> ');

    return stepText || 'ยังไม่ได้กำหนดผู้ประเมิน';
});
const reviewerSummary = computed(() => {
    if (!selectedReviewerIds.value.length) return 'ยังไม่ได้กำหนดลำดับการประเมิน';

    return selectedReviewerIds.value
        .map((id, index) => {
            const person = evaluatorFromId(id);
            return `${index + 1}. ${person?.displayName || person?.label || id}`;
        })
        .join(' · ');
});
const idpReviewerSummary = computed(() => {
    if (!selectedEvaluatorId(userForm.value.idp_reviewer_template_id) && !selectedIdpReviewerIds.value.length && selectedReviewerIds.value.length) {
        return selectedReviewerIds.value
            .map((id, index) => {
                const person = evaluatorFromId(id);
                return `${index + 1}. ${person?.displayName || person?.label || id}`;
            })
            .join(' -> ') + ' (fallback)';
    }
    if (!selectedIdpReviewerIds.value.length) return 'ยังไม่ได้กำหนดลำดับการทำ IDP';

    return selectedIdpReviewerIds.value
        .map((id, index) => {
            const person = evaluatorFromId(id);
            return `${index + 1}. ${person?.displayName || person?.label || id}`;
        })
        .join(' · ');
});
const userWorkflowIssues = computed(() => {
    if (normalizeUserRoleKey(userForm.value.r) === 'admin') return [];

    const issues = [];
    if (!selectedReviewerIds.value.length) {
        issues.push('ยังไม่ได้กำหนดลำดับการประเมิน');
    }
    if (!effectiveIdpReviewerIds.value.length) {
        issues.push('ยังไม่ได้กำหนดลำดับ IDP');
    }

    return issues;
});
const templatePersonLabel = (id) => evaluatorFromId(id)?.label || id;
const templateReviewerOptions = computed(() => {
    const blocked = new Set([
        ...selectedTemplateReviewerIds.value,
        ...selectedTemplateUserIds.value,
    ]);

    return evaluatorOptions.value.filter((person) => !blocked.has(person.value));
});
const templateAssignmentUserOptions = computed(() => {
    const blocked = new Set([
        ...selectedTemplateUserIds.value,
        ...selectedTemplateReviewerIds.value,
    ]);

    return evaluatorOptions.value.filter((person) => !blocked.has(person.value));
});
const addTemplateReviewer = () => {
    const reviewerId = selectedEvaluatorId(assessmentTemplateForm.value.reviewer_pick);
    if (
        !reviewerId
        || selectedTemplateReviewerIds.value.includes(reviewerId)
        || selectedTemplateUserIds.value.includes(reviewerId)
    ) return;

    assessmentTemplateError.value = '';
    assessmentTemplateForm.value.reviewer_ids = [...(assessmentTemplateForm.value.reviewer_ids || []), reviewerId];
    assessmentTemplateForm.value.reviewer_pick = '';
};
const removeTemplateReviewer = (index) => {
    assessmentTemplateForm.value.reviewer_ids = (assessmentTemplateForm.value.reviewer_ids || []).filter((_, itemIndex) => itemIndex !== index);
};
const addTemplateAssignmentUser = () => {
    const userId = selectedEvaluatorId(assessmentTemplateForm.value.user_pick);
    if (
        !userId
        || selectedTemplateUserIds.value.includes(userId)
        || selectedTemplateReviewerIds.value.includes(userId)
    ) return;

    assessmentTemplateError.value = '';
    assessmentTemplateForm.value.assignment_user_ids = [...selectedTemplateUserIds.value, userId];
    assessmentTemplateForm.value.user_pick = '';
};
const removeTemplateAssignmentUser = (index) => {
    assessmentTemplateForm.value.assignment_user_ids = selectedTemplateUserIds.value.filter((_, itemIndex) => itemIndex !== index);
};
const userForTemplateEditContext = (template) => {
    const assignedIds = new Set(templateAssignedUserIds(template));
    const templateColumn = (template?.chainType || 'assessment') === 'idp'
        ? 'idp_reviewer_template_id'
        : 'reviewer_template_id';

    return users.value.find((user) =>
        Number(user[templateColumn] || 0) === Number(template?.id || 0)
        || assignedIds.has(Number(user.db_id || 0))
    ) || null;
};
const reviewerIdsFromAssignedTemplateUser = (template) => {
    const user = userForTemplateEditContext(template);
    if (!user) return [];

    const steps = (template?.chainType || 'assessment') === 'idp'
        ? (user.idpReviewerSteps || [])
        : (user.reviewerSteps || user.supervisorChain || []);

    return steps
        .map((step) => selectedEvaluatorId(step.id || step.reviewerId || step.reviewer_id))
        .filter(Boolean);
};
const reviewerIdsFromTemplateSteps = (template) => {
    const ids = [];
    const contextUser = userForTemplateEditContext(template);

    (template.steps || []).forEach((step) => {
        const reviewerId = resolveReviewerForTemplateStep(step, ids, contextUser);

        if (reviewerId && !ids.includes(reviewerId)) {
            ids.push(reviewerId);
        }
    });

    return ids;
};
const editableReviewerIdsForTemplate = (template) => {
    const assignedUserReviewerIds = reviewerIdsFromAssignedTemplateUser(template);
    if (assignedUserReviewerIds.length) return assignedUserReviewerIds;

    return reviewerIdsFromTemplateSteps(template);
};
const startCreateAssessmentTemplate = () => {
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
    showAssessmentTemplateCreate.value = true;
};
const startEditAssessmentTemplate = (template) => {
    if (!template?.id) return;

    const editableReviewerIds = editableReviewerIdsForTemplate(template);
    editingAssessmentTemplateId.value = template.id;
    assessmentTemplateForm.value = {
        name: template.name || '',
        description: template.description || '',
        reviewer_ids: editableReviewerIds.length
            ? editableReviewerIds
            : (template.steps || []).map((step) => selectedEvaluatorId(step.reviewerId) || ''),
        assignment_user_ids: [],
        reviewer_pick: '',
        user_pick: '',
    };
    showAssessmentTemplateCreate.value = false;
};
const openAssessmentTemplateDetail = (template) => {
    resetAssessmentTemplateForm();
    activeAssessmentTemplateId.value = template.id;
    assessmentTemplateMemberPick.value = '';
};
const closeAssessmentTemplateDetail = () => {
    activeAssessmentTemplateId.value = null;
    assessmentTemplateMemberPick.value = '';
};
const cancelAssessmentTemplateEdit = () => {
    resetAssessmentTemplateForm();
};
const returnToAssessmentTemplateList = () => {
    showAssessmentTemplateCreate.value = false;
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
};
const resetAssessmentTemplateForm = () => {
    assessmentTemplateError.value = '';
    isSavingAssessmentTemplate.value = false;
    editingAssessmentTemplateId.value = null;
    assessmentTemplateForm.value = {
        name: '',
        description: '',
        reviewer_ids: [],
        assignment_user_ids: [],
        reviewer_pick: '',
        user_pick: '',
    };
};
const addAssessmentTemplateMember = () => {
    const template = selectedAssessmentTemplate.value;
    const userId = selectedEvaluatorId(assessmentTemplateMemberPick.value);
    if (!template || !userId) return;

    router.post(route('admin.reviewer-chain-templates.users.store', template.id), {
        user_ids: [userId],
    }, {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
            assessmentTemplateMemberPick.value = '';
        },
    });
};
const removeAssessmentTemplateMember = (member) => {
    const template = selectedAssessmentTemplate.value;
    if (!template || !member?.id) return;

    if (!confirm(`ลบ ${member.name} ออกจากลำดับการประเมินนี้?`)) return;

    router.delete(route('admin.reviewer-chain-templates.users.destroy', [template.id, member.id]), {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
        },
    });
};
const deleteAssessmentTemplate = (template) => {
    if (!template?.id) return;

    if (!confirm(`ลบลำดับการประเมิน "${template.name}"? ผู้ใช้ที่ผูกกับลำดับนี้จะถูกถอดออกด้วย`)) return;

    router.delete(route('admin.reviewer-chain-templates.destroy', template.id), {
        preserveScroll: true,
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
            if (selectedAssessmentTemplate.value?.id === template.id) {
                closeAssessmentTemplateDetail();
            }
        },
    });
};
const saveAssessmentTemplate = () => {
    const form = assessmentTemplateForm.value;
    assessmentTemplateError.value = '';

    if (!form.name.trim()) {
        assessmentTemplateError.value = `กรุณากรอกชื่อ${activeReviewerTemplateTitle.value}`;
        return;
    }

    if (!selectedTemplateReviewerIds.value.length) {
        assessmentTemplateError.value = 'กรุณาเพิ่มผู้ประเมินอย่างน้อย 1 คน';
        return;
    }

    if (selectedTemplateReviewerIds.value.some((id) => selectedTemplateUserIds.value.includes(id))) {
        assessmentTemplateError.value = 'ผู้ใช้ที่ถูกประเมินต้องไม่อยู่ในลำดับผู้ประเมินของตัวเอง';
        return;
    }

    const payload = {
        name: form.name.trim(),
        description: form.description.trim(),
        reviewer_ids: selectedTemplateReviewerIds.value,
    };
    const endpoint = isEditingAssessmentTemplate.value
        ? route('admin.reviewer-chain-templates.update', editingAssessmentTemplateId.value)
        : route('admin.reviewer-chain-templates.store');

    if (!isEditingAssessmentTemplate.value) {
        payload.chain_type = activeReviewerTemplateType.value;
        payload.assignment_user_ids = selectedTemplateUserIds.value;
    }

    const submitOptions = {
        preserveScroll: true,
        onStart: () => {
            isSavingAssessmentTemplate.value = true;
        },
        onError: (errors) => {
            assessmentTemplateError.value = Object.values(errors || {})[0]
                || `ไม่สามารถบันทึก${activeReviewerTemplateTitle.value}ได้`;
        },
        onFinish: () => {
            isSavingAssessmentTemplate.value = false;
        },
        onSuccess: (responsePage) => {
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
            }
            resetAssessmentTemplateForm();
            showAssessmentTemplateCreate.value = false;
        },
    };

    if (isEditingAssessmentTemplate.value) {
        router.patch(endpoint, payload, submitOptions);
    } else {
        router.post(endpoint, payload, submitOptions);
    }
};
const templateCandidateQuery = (roleKey, blockedIds = [], contextUser = null) => {
    const blocked = new Set(blockedIds.map((id) => Number(id)));
    const currentUserId = Number(contextUser?.db_id || userForm.value.db_id || 0);

    return evaluatorOptions.value
        .filter((person) => Number(person.value) !== currentUserId)
        .filter((person) => !blocked.has(Number(person.value)))
        .filter((person) => person.r === normalizeUserRoleKey(roleKey))
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'th'));
};
const resolveReviewerForTemplateStep = (step, blockedIds = [], contextUser = null) => {
    if (step.resolverType === 'fixed_user') {
        return selectedEvaluatorId(step.reviewerId);
    }

    const contextDepartment = contextUser?.d || userForm.value.d;
    const contextWorkline = contextUser?.w || userForm.value.w;
    const userJobFamily = jobFamilyFromDepartment(contextDepartment);
    const sameDepartment = templateCandidateQuery(step.roleKey, blockedIds, contextUser)
        .find((person) => userJobFamily && jobFamilyFromDepartment(person.d) === userJobFamily);

    if (step.resolverType === 'role_same_department' && sameDepartment) {
        return selectedEvaluatorId(sameDepartment.value);
    }

    const sameWorkline = templateCandidateQuery(step.roleKey, blockedIds, contextUser)
        .find((person) => contextWorkline && person.w === contextWorkline);

    if (['role_same_department', 'role_same_workline'].includes(step.resolverType) && sameWorkline) {
        return selectedEvaluatorId(sameWorkline.value);
    }

    return selectedEvaluatorId(templateCandidateQuery(step.roleKey, blockedIds, contextUser)[0]?.value);
};
const applyReviewerTemplate = () => {
    const template = selectedReviewerTemplate.value;

    if (!template) return;

    syncOrgPath();
    const ids = [];
    (template.steps || []).forEach((step) => {
        const reviewerId = resolveReviewerForTemplateStep(step, ids);

        if (reviewerId && !ids.includes(reviewerId)) {
            ids.push(reviewerId);
        }
    });

    userForm.value.reviewer_ids = ids.length ? ids : [''];
    clearReviewerSearch();
};
const applyIdpReviewerTemplate = () => {
    const template = selectedIdpReviewerTemplate.value;

    if (!template) return;

    syncOrgPath();
    const ids = [];
    (template.steps || []).forEach((step) => {
        const reviewerId = resolveReviewerForTemplateStep(step, ids);

        if (reviewerId && !ids.includes(reviewerId)) {
            ids.push(reviewerId);
        }
    });

    userForm.value.idp_reviewer_ids = ids;
};
const selectReviewerTemplate = (templateId) => {
    userForm.value.reviewer_template_id = selectedEvaluatorId(templateId);

    if (userForm.value.reviewer_template_id) {
        applyReviewerTemplate();
    }
};
const selectIdpReviewerTemplate = (templateId) => {
    userForm.value.idp_reviewer_template_id = selectedEvaluatorId(templateId);

    if (userForm.value.idp_reviewer_template_id) {
        applyIdpReviewerTemplate();
        return;
    }

    userForm.value.idp_reviewer_ids = [];
};
const reviewerChoicesForStep = (stepIndex) => {
    const selectedInOtherSteps = new Set(
        (userForm.value.reviewer_ids || [])
            .map((id, index) => index === stepIndex ? '' : selectedEvaluatorId(id))
            .filter(Boolean),
    );

    return evaluatorOptions.value.filter((person) =>
        person.value !== Number(userForm.value.db_id || 0)
        && !selectedInOtherSteps.has(person.value),
    );
};
const normalizeReviewerList = () => {
    userForm.value.reviewer_ids = selectedReviewerIds.value;
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const addReviewerStep = () => {
    userForm.value.reviewer_ids = [...(userForm.value.reviewer_ids || []), ''];
};
const removeReviewerStep = (index) => {
    userForm.value.reviewer_ids = (userForm.value.reviewer_ids || []).filter((_, itemIndex) => itemIndex !== index);
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const updateReviewerStep = (index, value) => {
    const next = [...(userForm.value.reviewer_ids || [])];
    next[index] = selectedEvaluatorId(value);
    userForm.value.reviewer_ids = next;
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const reviewerInputValue = (index, reviewerId) => {
    if (Object.prototype.hasOwnProperty.call(reviewerSearchTerms.value, index)) {
        return reviewerSearchTerms.value[index];
    }

    return evaluatorFromId(reviewerId)?.label || '';
};
const updateReviewerSearch = (index, value) => {
    activeReviewerDropdown.value = index;
    reviewerSearchTerms.value = {
        ...reviewerSearchTerms.value,
        [index]: value,
    };

    const exactMatch = reviewerChoicesForStep(index).find((person) =>
        person.label === value
        || person.displayName === value
        || person.name === value,
    );

    if (exactMatch) {
        updateReviewerStep(index, exactMatch.value);
    }
};
const filteredReviewerChoicesForStep = (index) => {
    const term = String(reviewerSearchTerms.value[index] || '').trim().toLowerCase();
    const choices = reviewerChoicesForStep(index);

    if (!term) return choices;

    return choices.filter((person) => person.searchText.includes(term));
};
const openReviewerDropdown = (index, reviewerId) => {
    activeReviewerDropdown.value = index;
    reviewerSearchTerms.value = {
        ...reviewerSearchTerms.value,
        [index]: Object.prototype.hasOwnProperty.call(reviewerSearchTerms.value, index)
            ? reviewerSearchTerms.value[index]
            : reviewerInputValue(index, reviewerId),
    };
};
const isReviewerDropdownOpen = (index) => activeReviewerDropdown.value === index;
const chooseReviewerStep = (index, value) => {
    updateReviewerStep(index, value);
};
const clearReviewerSearch = () => {
    reviewerSearchTerms.value = {};
    activeReviewerDropdown.value = null;
};
const closeReviewerDropdown = () => {
    activeReviewerDropdown.value = null;
};
const openReviewerModal = () => {
    showReviewerModal.value = true;
    if (!selectedReviewerIds.value.length) {
        userForm.value.reviewer_ids = [''];
    }
};
const closeReviewerModal = () => {
    normalizeReviewerList();
    showReviewerModal.value = false;
};
const openReviewerTemplateModal = (chainType = 'assessment') => {
    activeReviewerTemplateModal.value = chainType === 'idp' ? 'idp' : 'assessment';
    showAssessmentTemplateCreate.value = false;
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
};
const closeReviewerTemplateModal = () => {
    activeReviewerTemplateModal.value = '';
    showAssessmentTemplateCreate.value = false;
    closeAssessmentTemplateDetail();
    resetAssessmentTemplateForm();
};
const supervisorIdFromUser = (user, idKey, nameKey) => {
    const explicitId = selectedEvaluatorId(user?.[idKey]);
    if (explicitId) return explicitId;

    const storedName = (user?.[nameKey] || '').trim();
    if (!storedName) return '';

    return evaluatorOptions.value.find((person) =>
        person.name === storedName
        || person.label === storedName
        || person.label.startsWith(storedName),
    )?.value || '';
};
const evaluatorFromId = (id) =>
    evaluatorOptions.value.find((person) => person.value === selectedEvaluatorId(id)) || null;
const reviewerOption = (user) =>
    evaluatorOptions.value.find((person) => person.value === Number(user.db_id))
    || {
        key: user.db_id,
        value: Number(user.db_id),
        name: user.n,
        displayName: `${user.t || ''}${user.n}`,
        label: `${user.t || ''}${user.n}${user.p ? ` · ${user.p}` : ''}`,
    };
const filteredEvaluatorOptions = (query, selectedValue, blockedValue) => {
    const needle = query.trim().toLowerCase();
    const selectedId = selectedEvaluatorId(selectedValue);
    const blockedId = selectedEvaluatorId(blockedValue);

    return evaluatorOptions.value.filter((person) =>
        (person.value === selectedId || person.value !== blockedId)
        && (
            person.value === selectedId
            || !needle
            || person.searchText.includes(needle)
            || person.label.toLowerCase().includes(needle)
        ),
    );
};
const deptHeadOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'dept_head')
        .map(reviewerOption),
);

const supervisorOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'supervisor')
        .map(reviewerOption),
);

const deanOptions = computed(() =>
    users.value
        .filter((user) => user.sso !== editingUserKey.value)
        .filter((user) => normalizeUserRoleKey(user.r) === 'dean')
        .map(reviewerOption),
);

const canPickEvaluator1 = computed(() => !['admin', 'supervisor', 'dept_head', 'division_head', 'academic_department_head', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const canPickEvaluator2 = computed(() => !['admin', 'dept_head', 'division_head', 'academic_department_head', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const canPickEvaluator3 = computed(() => !['admin', 'dean'].includes(normalizeUserRoleKey(userForm.value.r)));
const isDeanRole = computed(() => normalizeUserRoleKey(userForm.value.r) === 'dean');
const requiresOrganizationStructure = computed(() => !isDeanRole.value);
const isDeptHeadRole = computed(() => normalizeUserRoleKey(userForm.value.r) === 'dept_head');
const isDivisionHeadRole = computed(() => normalizeUserRoleKey(userForm.value.r) === 'division_head');
const requiresSupportWork = computed(() => !(isSupportWorkline.value && isDivisionHeadRole.value));
const requiresSupportUnit = computed(() => !(isSupportWorkline.value && (isDivisionHeadRole.value || isDeptHeadRole.value)));
const supportPathCount = computed(() =>
    [userForm.value.dept, userForm.value.job, userForm.value.unit].filter(Boolean).length,
);
const allowsMissingPositionFields = computed(() => {
    if (isDeanRole.value) return true;
    if (!isSupportWorkline.value) return false;
    if (isDivisionHeadRole.value) return supportPathCount.value >= 1 && supportPathCount.value < 3;
    if (isDeptHeadRole.value) return supportPathCount.value === 2;

    return false;
});
const requiresPositionFields = computed(() =>
    requiresOrganizationStructure.value && Boolean(userForm.value.w) && !allowsMissingPositionFields.value,
);
const showsPositionSection = computed(() =>
    requiresOrganizationStructure.value && Boolean(userForm.value.w),
);

const requestPageChange = (page) => {
    activePage.value = page;
};

const parseOrgPath = (path = '') => {
    const parts = path.split(' > ').map((part) => part.trim()).filter(Boolean);

    if (parts.length >= 3) return { dept: parts[0], job: parts[1], unit: parts.slice(2).join(' > ') };
    if (parts.length === 2) return { dept: parts[0], job: parts[1], unit: '' };
    if (parts.length === 1) return { dept: parts[0], job: '', unit: '' };

    return { dept: '', job: '', unit: '' };
};

const syncOrgPath = () => {
    const form = userForm.value;

    if (isSupportWorkline.value) {
        form.d = [form.dept, form.job, form.unit].filter(Boolean).join(' > ');
    } else if (parseOrgPath(form.d).dept !== form.job) {
        form.d = form.job;
    }
};

const findUserName = (predicate) => {
    const found = users.value.find(predicate);
    return found ? `${found.t || ''}${found.n}` : '';
};

const syncOrgSupervisors = () => {
    const form = userForm.value;
    form.reviewer_template_id = '';
    form.idp_reviewer_template_id = '';
    form.reviewer_ids = [];
    form.idp_reviewer_ids = [];
};

const resetOrgSelection = () => {
    organizationDirty.value = true;
    userForm.value.dept = '';
    userForm.value.job = '';
    userForm.value.unit = '';
    userForm.value.d = '';
    userForm.value.p = '';
    userForm.value.l = '';
    userForm.value.reviewer_template_id = '';
    userForm.value.idp_reviewer_template_id = '';
    userForm.value.reviewer_ids = [];
    userForm.value.idp_reviewer_ids = [];
};

const handleWorklineChange = () => {
    resetOrgSelection();
};

const handleDeptChange = (event = null) => {
    if (event?.target) {
        userForm.value.dept = event.target.value;
    }
    organizationDirty.value = true;
    userForm.value.job = '';
    userForm.value.unit = '';
    userForm.value.p = '';
    syncOrgPath();
};

const handleJobChange = (event = null) => {
    if (event?.target) {
        userForm.value.job = event.target.value;
    }
    organizationDirty.value = true;
    userForm.value.unit = '';
    userForm.value.p = '';
    if (isDeanRole.value) {
        userForm.value.p = userForm.value.job;
    }
    syncOrgPath();
};

const handleUnitChange = () => {
    organizationDirty.value = true;
    syncOrgPath();
};

const handlePositionChange = () => {
    organizationDirty.value = true;
    userForm.value.l = '';
    const directLevels = levelsByWorkline.value[userForm.value.w] || [];
    if (!directLevels.length && userForm.value.p) {
        userForm.value.l = userForm.value.p;
    }
};

const handleRoleChange = () => {
    if (isDeanRole.value && userForm.value.job) {
        organizationDirty.value = organizationDirty.value || userForm.value.p !== userForm.value.job;
        userForm.value.p = userForm.value.job;
    }

    if (isDeanRole.value) {
        resetOrgSelection();
    }

    normalizeReviewerList();
};

const normalizeAcademicTitle = (title) => ({
    'ผศ.': 'ผศ.ดร.',
    'รศ.': 'รศ.ดร.',
    'ศ.': 'ศ.ดร.',
}[title] || title || '');

const resetUserForm = (data = null) => {
    const org = parseOrgPath(data?.d || '');
    const supportWorkline = normalizeWorklineName(data?.w || '') === 'สนับสนุน';
    const [firstName = '', ...lastNameParts] = (data?.n || '').split(' ');
    const initialRole = normalizeUserRoleKey(data?.r || 'employee');
    const initialWorkline = data?.w || (initialRole === 'dean' ? '' : worklines.value[0] || '');
    const initialIsSupportWorkline = normalizeWorklineName(initialWorkline) === 'สนับสนุน';
    const rawSupportDept = initialIsSupportWorkline ? org.dept : '';
    const initialSupportDeptIsValid = !rawSupportDept
        || optionIncludes(supportDeptsList.value, rawSupportDept);
    const initialSupportDept = initialSupportDeptIsValid ? rawSupportDept : '';
    const initialSupportJob = initialIsSupportWorkline && initialSupportDeptIsValid ? org.job : '';
    const initialSupportJobIsValid = !initialSupportJob
        || Boolean(supportWorkForDepartment(initialSupportDept, initialSupportJob));
    const initialSupportUnit = initialSupportJobIsValid ? org.unit : '';
    const initialSupportUnitIsValid = !initialSupportUnit
        || supportUnitNamesForWork(initialSupportDept, initialSupportJob).includes(normalizeOptionName(initialSupportUnit));
    const initialDepartmentPath = initialIsSupportWorkline
        ? [
            initialSupportDept,
            initialSupportJobIsValid ? initialSupportJob : '',
            initialSupportJobIsValid && initialSupportUnitIsValid ? initialSupportUnit : '',
        ].filter(Boolean).join(' > ')
        : data?.d || '';

    editingUserKey.value = data?.sso || null;
    organizationDirty.value = false;
    isChangingPassword.value = !data?.db_id;
    showNewPassword.value = false;
    showPasswordConfirmation.value = false;
    supervisorSearch.value = '';
    evaluator2Search.value = '';
    userForm.value = {
        db_id: data?.db_id || null,
        sso: data?.sso || '',
        t: normalizeAcademicTitle(data?.t),
        n: data?.n || '',
        fn: data?.fn || firstName,
        ln: data?.ln || lastNameParts.join(' '),
        fe: data?.fe || '',
        le: data?.le || '',
        em: data?.em || '',
        username: data?.username || '',
        password: '',
        password_confirmation: '',
        ph: data?.ph || '',
        w: initialWorkline,
        d: initialDepartmentPath,
        dept: initialSupportDept,
        job: initialIsSupportWorkline ? (initialSupportJobIsValid ? initialSupportJob : '') : org.job || org.dept,
        unit: initialIsSupportWorkline && initialSupportJobIsValid && initialSupportUnitIsValid ? initialSupportUnit : '',
        p: initialIsSupportWorkline && !initialSupportJobIsValid ? '' : data?.p || '',
        l: initialIsSupportWorkline && !initialSupportJobIsValid ? '' : data?.l || '',
        r: initialRole,
        reviewer_template_id: data?.reviewer_template_id || '',
        idp_reviewer_template_id: data?.idp_reviewer_template_id || '',
        reviewer_ids: (data?.reviewerSteps || [])
            .map((step) => selectedEvaluatorId(step?.id))
            .filter(Boolean),
        idp_reviewer_ids: (data?.idpReviewerSteps || [])
            .map((step) => selectedEvaluatorId(step?.id))
            .filter(Boolean),
        act: data?.act !== false,
        structureStatus: data?.structureStatus || 'ok',
        structureIssues: Array.isArray(data?.structureIssues) ? data.structureIssues : [],
    };
    normalizeReviewerList();
};

const openModal = (type, data = null) => {
    if (type !== 'modal-user') return;

    resetUserForm(data);
    activeModal.value = 'modal-user';
};

const closeModal = () => {
    activeModal.value = null;
    editingUserKey.value = null;
    userForm.value.password = '';
    userForm.value.password_confirmation = '';
    isChangingPassword.value = false;
    showNewPassword.value = false;
    showPasswordConfirmation.value = false;
};

const cancelPasswordChange = () => {
    userForm.value.password = '';
    userForm.value.password_confirmation = '';
    isChangingPassword.value = false;
    showNewPassword.value = false;
    showPasswordConfirmation.value = false;
};

const closeIssuedCredentials = () => {
    issuedCredentials.value = null;
};

const updateUserStatus = (targetUser, callbacks = {}) => {
    if (!targetUser?.db_id) {
        alert('ไม่พบรหัสฐานข้อมูลของผู้ใช้นี้ กรุณารีเฟรชหน้าแล้วลองใหม่');
        return;
    }

    const nextActive = targetUser.act === false;
    if (!nextActive && normalizeUserRoleKey(targetUser.r || '') === 'admin') {
        alert('ไม่สามารถระงับบัญชีผู้ดูแลระบบได้');
        return;
    }
    if (!nextActive && Number(targetUser.db_id) === Number(page.props.auth?.user?.id || 0)) {
        alert('ไม่สามารถระงับบัญชีที่กำลังใช้งานอยู่ได้');
        return;
    }

    const previousUsers = clone(users.value);
    const userId = Number(targetUser.db_id);
    activePage.value = 'admin-users';
    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(adminPageStorageKey, 'admin-users');
        window.sessionStorage.setItem('cidp.admin.activePage', 'admin-users');
    }

    users.value = users.value.map((user) =>
        Number(user.db_id) === userId ? { ...user, act: nextActive } : user,
    );

    router.visit(`/admin/users/${userId}/status`, {
        method: 'patch',
        data: { act: nextActive },
        preserveScroll: true,
        preserveState: false,
        onSuccess: (responsePage) => {
            activePage.value = 'admin-users';
            if (Array.isArray(responsePage.props.users)) {
                users.value = clone(responsePage.props.users);
                callbacks.onSuccess?.();
                return;
            }

            router.reload({
                only: ['users'],
                preserveScroll: true,
                onSuccess: (reloadPage) => {
                    users.value = clone(reloadPage.props.users || users.value);
                    callbacks.onSuccess?.();
                },
            });
        },
        onError: (errors) => {
            users.value = previousUsers;
            if (callbacks.onError) {
                callbacks.onError(errors);
                return;
            }

            alert(String(Object.values(errors)[0] || 'ไม่สามารถบันทึกสถานะผู้ใช้ลงฐานข้อมูลได้'));
        },
        onFinish: () => callbacks.onFinish?.(),
    });
};

const saveUser = () => {
    if (isSavingUser.value) return;

    activePage.value = 'admin-users';
    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(adminPageStorageKey, 'admin-users');
    }
    const form = userForm.value;
    const preserveExistingStructure = Boolean(form.db_id && !organizationDirty.value);
    if (!preserveExistingStructure) {
        syncOrgPath();
    }
    if (!preserveExistingStructure && isDeanRole.value && form.job) {
        form.p = form.job;
    }
    const thaiName = [form.fn.trim(), form.ln.trim()].filter(Boolean).join(' ');

    if (!form.sso.trim() || !thaiName) {
        alert('กรุณากรอก ID และชื่อผู้ใช้');
        return;
    }

    if (!form.db_id && (!form.username.trim() || !form.password)) {
        alert('กรุณากำหนด Username และ Password สำหรับเข้าสู่ระบบ');
        return;
    }

    if (form.db_id && isChangingPassword.value && !form.password) {
        alert('กรุณากรอกรหัสผ่านใหม่ หรือยกเลิกการแก้ไขรหัสผ่าน');
        return;
    }

    if (form.password && form.password !== form.password_confirmation) {
        alert('Password และการยืนยัน Password ไม่ตรงกัน');
        return;
    }

    const invalidSupportJob = isSupportWorkline.value
        && Boolean(form.job)
        && !optionIncludes(jobOptions.value, form.job);
    const invalidSupportUnit = isSupportWorkline.value
        && Boolean(form.unit)
        && !optionIncludes(unitOptions.value, form.unit);
    const invalidSupportDept = isSupportWorkline.value
        && Boolean(form.dept)
        && !optionIncludes(supportDeptsList.value, form.dept);
    if (!preserveExistingStructure && (invalidSupportDept || invalidSupportJob || invalidSupportUnit)) {
        alert(invalidSupportDept
            ? 'ฝ่ายนี้ไม่มีในโครงสร้างปัจจุบัน กรุณาเลือกฝ่ายใหม่ก่อนบันทึก'
            : invalidSupportJob
            ? 'งานนี้ไม่มีในโครงสร้างปัจจุบัน กรุณาเลือกงานใหม่ก่อนบันทึก'
            : 'หน่วยนี้ไม่มีในโครงสร้างปัจจุบัน กรุณาเลือกหน่วยใหม่ก่อนบันทึก');
        return;
    }

    const missingOrganization = requiresOrganizationStructure.value && (
        !form.w
        || (!isSupportWorkline.value && !form.job)
        || (isSupportWorkline.value && (
            !form.dept
            || (requiresSupportWork.value && !form.job)
            || (requiresSupportUnit.value && !form.unit)
        ))
    );
    const selectedOptionalPosition = !requiresPositionFields.value && canPickPosition.value && Boolean(form.p);
    const missingPosition = requiresPositionFields.value && (!form.l || (canPickPosition.value && !form.p));
    const incompleteOptionalPosition = selectedOptionalPosition && !form.l;
    if (!preserveExistingStructure && requiresOrganizationStructure.value && (missingOrganization || missingPosition)) {
        alert(isSupportWorkline.value
            ? 'กรุณาเลือกสายงาน ฝ่าย งาน หน่วย ตำแหน่ง และระดับตำแหน่งให้ครบถ้วน'
            : 'กรุณาเลือกสายงาน ภาควิชา ตำแหน่ง และระดับตำแหน่งให้ครบถ้วน');
        return;
    }

    if (!preserveExistingStructure && incompleteOptionalPosition) {
        alert('กรุณาเลือกระดับตำแหน่งก่อนบันทึกตำแหน่งนี้');
        return;
    }

    if (!preserveExistingStructure && (requiresPositionFields.value || selectedOptionalPosition) && canPickPosition.value && !optionIncludes(positionOptions.value, form.p)) {
        alert(isSupportWorkline.value
            ? 'กรุณาให้ Admin เพิ่มตำแหน่งในงานนี้ก่อนบันทึกผู้ใช้'
            : 'กรุณาให้ Admin เพิ่มตำแหน่งสำหรับภาควิชานี้ก่อนบันทึกผู้ใช้');
        return;
    }

    if (!preserveExistingStructure && (requiresPositionFields.value || form.l) && !optionIncludes(levelOptions.value, form.l)) {
        alert('กรุณาให้ Admin เพิ่มระดับตำแหน่งในสายงานนี้ก่อนบันทึกผู้ใช้');
        return;
    }

    const duplicate = users.value.some((user) => user.sso === form.sso && user.sso !== editingUserKey.value);
    if (duplicate) {
        alert(`ID ${form.sso} มีอยู่ในระบบแล้ว`);
        return;
    }

    const nextUser = {
        ...form,
        preserve_existing_structure: preserveExistingStructure,
        db_id: form.db_id,
        sso: form.sso.trim(),
        n: thaiName,
        fn: form.fn.trim(),
        ln: form.ln.trim(),
        fe: form.fe.trim(),
        le: form.le.trim(),
        em: form.em.trim(),
        username: form.username.trim().toLowerCase(),
        password: form.password,
        password_confirmation: form.password_confirmation,
        ph: form.ph.trim(),
        t: form.t.trim(),
        w: form.w.trim(),
        d: form.d.trim(),
        dept: form.dept.trim(),
        job: form.job.trim(),
        unit: form.unit.trim(),
        p: (isDeanRole.value || !canPickPosition.value || (!requiresPositionFields.value && !form.p) ? '' : form.p).trim(),
        l: (requiresPositionFields.value || form.p || form.l ? form.l : '').trim(),
        reviewer_template_id: selectedEvaluatorId(form.reviewer_template_id) || null,
        idp_reviewer_template_id: selectedEvaluatorId(form.idp_reviewer_template_id) || null,
        reviewer_ids: selectedReviewerIds.value,
        idp_reviewer_ids: effectiveIdpReviewerIds.value,
        act: Boolean(form.act),
    };
    const credentialsToShow = nextUser.password
        ? { username: nextUser.username || nextUser.em, password: nextUser.password }
        : null;

    const finishUserSave = () => {
        closeModal();
        issuedCredentials.value = credentialsToShow;
    };

    const onSuccess = (responsePage) => {
        activePage.value = 'admin-users';
        if (typeof window !== 'undefined') {
            window.sessionStorage.setItem(adminPageStorageKey, 'admin-users');
        }

        if (Array.isArray(responsePage.props.users)) {
            users.value = clone(responsePage.props.users);
            finishUserSave();
            return;
        }

        router.reload({
            only: ['users'],
            preserveScroll: true,
            onSuccess: (page) => {
                users.value = clone(page.props.users || []);
                finishUserSave();
            },
        });
    };

    const onError = (errors) => {
        const firstError = Object.values(errors)[0];
        alert(firstError || 'ไม่สามารถบันทึกข้อมูลผู้ใช้ได้');
    };

    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            isSavingUser.value = true;
        },
        onFinish: () => {
            isSavingUser.value = false;
        },
        onSuccess,
        onError,
    };

    if (nextUser.db_id) {
        router.put(`/admin/users/${nextUser.db_id}`, nextUser, options);
        return;
    }

    router.post('/admin/users', nextUser, options);
};

const goProfile = () => router.visit(route('profile.edit'));
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="currentPageTitle" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <div v-if="showSidebar" class="sidebar">
            <SidebarBrand />

            <button class="sb-user" type="button" @click="goProfile">
                <div class="av" :style="{ background: currentRoleData.col }">
                    {{ currentProfileUser?.n?.[0] || currentRoleData.av }}
                </div>
                <div style="overflow: hidden; min-width: 0">
                    <div class="u-name">
                        {{ currentProfileUser ? `${currentProfileUser.t}${currentProfileUser.n}` : currentRoleData.name }}
                    </div>
                    <div class="u-role">{{ currentProfileUser?.p || currentRoleData.pos }}</div>
                </div>
            </button>

            <div class="sb-nav">
                <div v-for="(section, sectionIndex) in currentNavConfig" :key="sectionIndex">
                    <div class="nav-sec">{{ section.sec }}</div>
                    <div
                        v-for="item in section.items"
                        :key="item.id"
                        class="nav-item"
                        :class="{ on: activePage === item.id }"
                        @click="requestPageChange(item.id)"
                    >
                        <span class="nav-ic">{{ item.ic }}</span>
                        {{ item.lb }}
                    </div>
                </div>
            </div>
        </div>

        <div class="main">
            <div class="topbar">
                <button
                    class="btn btn-s btn-sm"
                    style="padding: 8px; min-width: 40px; justify-content: center; border: none; background: transparent"
                    type="button"
                    @click="showSidebar = !showSidebar"
                >
                    ☰
                </button>
                <PageTitleBlock :page-title="currentPageTitle" />
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <div class="content">
                <EmployeeAssess
                    v-if="activePage === 'emp-assess'"
                    :user="currentProfileUser"
                    :set-users="setRef(users)"
                />

                <EmployeeGap
                    v-else-if="activePage === 'emp-gap'"
                    :set-page="requestPageChange"
                    :competencies="page.props.currentUserCompetencies || []"
                    :gaps="page.props.currentUserCompetencyGaps || []"
                    :user="currentProfileUser"
                />

                <EmployeeIDP
                    v-else-if="activePage === 'emp-idp'"
                    :learning-methods="learningMethods"
                    :idp-learning-methods="idpLearningMethods"
                    :learning-catalogs="hrCatalogItems"
                    :gaps="page.props.currentUserCompetencyGaps || []"
                    :idp="currentUserIdp"
                    :user="currentProfileUser"
                />

                <EmployeeProgress v-else-if="activePage === 'emp-progress'" />

                <EmployeeIDPDetail v-else-if="activePage === 'emp-idp-detail'" :activities="page.props.currentUserApprovedIdpActivities || []" />

                <HeadDashboard
                    v-else-if="activePage === 'admin-fc-topic-review' && fcTopicApprovalModule.enabled"
                    embedded
                    embedded-page="dh-fc-topic-approval"
                    role-key="admin"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <HeadDashboard
                    v-else-if="activePage === 'admin-assessment-review' && (fcTopicApprovalModule.enabled || assessmentApprovalModule.enabled)"
                    embedded
                    embedded-page="dh-assess"
                    role-key="admin"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <HeadDashboard
                    v-else-if="activePage === 'admin-team-assessment' && assessmentApprovalModule.enabled"
                    embedded
                    embedded-page="sup-gap"
                    role-key="admin"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <HeadDashboard
                    v-else-if="activePage === 'admin-idp-review' && idpReviewModule.enabled"
                    embedded
                    embedded-page="dh-idp"
                    role-key="admin"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <AdminUsers
                    v-else-if="activePage === 'admin-users'"
                    :open-modal="openModal"
                    :open-reviewer-template-modal="openReviewerTemplateModal"
                    :update-user-status="updateUserStatus"
                    :users="users"
                    :set-users="setRef(users)"
                    :academic-depts="academicPositions"
                    :support-depts="supportDeptsList"
                    :admin-depts="adminDepts"
                    :worklines="worklines"
                />

                <AdminOrgStructure
                    v-else-if="activePage === 'admin-org-structure'"
                    :academic-depts="academicPositions"
                    :set-academic-depts="setRef(academicPositions)"
                    :support-depts="supportJobFamilies"
                    :support-position-groups="supportPositionGroups"
                    :set-support-position-groups="setRef(supportPositionGroups)"
                    :admin-depts="adminDepts"
                    :set-admin-depts="setRef(adminDepts)"
                    :support-org="supportOrg"
                    :set-support-org="setRef(supportOrg)"
                    :users="users"
                    :academic-pos="academicPositions"
                    :set-academic-pos="setRef(academicPositions)"
                    :support-pos="supportPositions"
                    :set-support-pos="setRef(supportPositions)"
                    :admin-pos="adminPositions"
                    :set-admin-pos="setRef(adminPositions)"
                    :job-families-by-workline="jobFamiliesByWorkline"
                    :set-job-families-by-workline="setRef(jobFamiliesByWorkline)"
                    :levels-by-workline="levelsByWorkline"
                    :set-levels-by-workline="setRef(levelsByWorkline)"
                    :level-expectations-by-workline="levelExpectationsByWorkline"
                    :set-level-expectations-by-workline="setRef(levelExpectationsByWorkline)"
                    :academic-rank="academicRanks"
                    :set-academic-rank="setRef(academicRanks)"
                    :support-rank="supportRanks"
                    :set-support-rank="setRef(supportRanks)"
                    :worklines="worklines"
                    :set-worklines="setRef(worklines)"
                    :competency-types="competencyTypes"
                    :set-competency-types="setRef(competencyTypes)"
                />

                <AdminDict
                    v-else-if="activePage === 'admin-dict'"
                    :competencies="competencies"
                    :set-competencies="setRef(competencies)"
                    :competency-types="competencyTypes"
                    :on-dirty-change="() => {}"
                />

                <AdminIdpTools
                    v-else-if="activePage === 'admin-idp-tools'"
                    :competencies="competencies"
                    :idp-learning-methods="idpLearningMethods"
                    :learning-catalogs="hrCatalogItems"
                    :learning-methods="learningMethods"
                    :delivery-type-settings="idpDeliveryTypeSettings"
                />

                <div v-else class="p-20 text-center text-text3">กำลังพัฒนา</div>
            </div>
        </div>
    </div>

    <div v-if="activeModal === 'modal-user'" class="mo admin-user-modal">
        <div class="mo-box admin-user-modal-box">
            <div class="mo-h admin-user-modal-head">
                <div>
                    <div class="fw8 fs18">
                        จัดการผู้ใช้งาน
                    </div>
                    <div class="muted fs12">
                        กรอกข้อมูลให้ครบตามตาราง users ในฐานข้อมูล
                    </div>
                </div>
                <button class="btn btn-s btn-sm" type="button" @click="closeModal">× ปิด</button>
            </div>

            <div class="mo-b admin-user-modal-body">
                <div class="admin-user-note">
                     ID ใช้เชื่อมโยงข้อมูลบุคลากรเดิม ส่วน Username และ Password ใช้สำหรับเข้าสู่ระบบ
                </div>

                <div v-if="userWorkflowIssues.length" class="admin-user-warning">
                    <div class="admin-user-warning-title">ต้องตรวจสอบข้อมูลผู้ใช้นี้</div>
                    <ul>
                        <li v-for="issue in userWorkflowIssues" :key="issue">{{ issue }}</li>
                    </ul>
                </div>

                <section class="workflow-role-panel user-role-top-panel">
                    <div class="fg evaluator-role-field">
                        <label class="lbl req">บทบาทในระบบ</label>
                        <select v-model="userForm.r" class="sel modal-input" @change="handleRoleChange">
                            <option
                                v-for="role in roleOptions"
                                :key="`role-${role.key}`"
                                :value="role.key"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                    </div>
                </section>

                <div class="fg">
                    <label class="lbl req">ID</label>
                    <input v-model="userForm.sso" class="inp modal-input" placeholder="เช่น 64XXXX หรือ stu_XXXXXXX" />
                </div>

                <div class="modal-section-label">ข้อมูลเข้าสู่ระบบ</div>
                <div class="admin-user-note login-account-note">
                    Username ใช้เข้าสู่ระบบแทนอีเมล{{ userForm.db_id ? ' · รหัสเดิมเปิดดูย้อนหลังไม่ได้ หากผู้ใช้ลืม ให้กำหนดรหัสใหม่' : '' }}
                </div>
                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl" :class="{ req: !userForm.db_id }">Username</label>
                        <input
                            v-model="userForm.username"
                            autocomplete="off"
                            class="inp modal-input"
                            placeholder="เช่น somchai.k"
                        />
                    </div>
                    <div class="fg">
                        <label class="lbl" :class="{ req: isChangingPassword }">{{ userForm.db_id && isChangingPassword ? 'Password ใหม่' : 'Password' }}</label>
                        <div class="admin-password-input">
                            <input
                                v-if="!isChangingPassword"
                                class="inp modal-input"
                                value="ตั้งรหัสผ่านแล้ว"
                                aria-label="สถานะรหัสผ่าน: ตั้งรหัสผ่านแล้ว"
                                readonly
                            />
                            <div v-else class="admin-password-field">
                                <input
                                    v-model="userForm.password"
                                    autocomplete="new-password"
                                    class="inp modal-input"
                                    placeholder="อย่างน้อย 8 ตัวอักษร"
                                    :type="showNewPassword ? 'text' : 'password'"
                                />
                                <button class="admin-password-toggle" type="button" :aria-label="showNewPassword ? 'ซ่อนรหัสผ่านใหม่' : 'แสดงรหัสผ่านใหม่'" :aria-pressed="showNewPassword" @click="showNewPassword = !showNewPassword">
                                    {{ showNewPassword ? 'ซ่อน' : 'แสดง' }}
                                </button>
                            </div>
                            <button v-if="userForm.db_id" class="btn btn-s btn-sm" type="button" @click="isChangingPassword ? cancelPasswordChange() : isChangingPassword = true">
                                {{ isChangingPassword ? 'ยกเลิก' : 'แก้ไขรหัสผ่าน' }}
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="isChangingPassword" class="modal-grid single-col">
                    <div class="fg">
                        <label class="lbl req">ยืนยัน Password</label>
                        <div class="admin-password-field">
                            <input
                                v-model="userForm.password_confirmation"
                                autocomplete="new-password"
                                class="inp modal-input"
                                placeholder="กรอก Password อีกครั้ง"
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                            />
                            <button class="admin-password-toggle" type="button" :aria-label="showPasswordConfirmation ? 'ซ่อนรหัสผ่านที่ยืนยัน' : 'แสดงรหัสผ่านที่ยืนยัน'" :aria-pressed="showPasswordConfirmation" @click="showPasswordConfirmation = !showPasswordConfirmation">
                                {{ showPasswordConfirmation ? 'ซ่อน' : 'แสดง' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">คำนำหน้า</label>
                        <select v-model="userForm.t" class="sel modal-input">
                            <option value="">— เลือกคำนำหน้า —</option>
                            <option value="นาย">นาย</option>
                            <option value="นาง">นาง</option>
                            <option value="นางสาว">นางสาว</option>
                            <option value="ดร.">ดร.</option>
                            <option value="ผศ.ดร.">ผศ.ดร.</option>
                            <option value="รศ.ดร.">รศ.ดร.</option>
                            <option value="ศ.ดร.">ศ.ดร.</option>
                        </select>
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">ชื่อ (ภาษาไทย)</label>
                        <input v-model="userForm.fn" class="inp modal-input" placeholder="ชื่อจริง" />
                    </div>
                    <div class="fg">
                        <label class="lbl req">นามสกุล (ภาษาไทย)</label>
                        <input v-model="userForm.ln" class="inp modal-input" placeholder="นามสกุล" />
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">First Name (English)</label>
                        <input v-model="userForm.fe" class="inp modal-input" placeholder="First name in English" />
                    </div>
                    <div class="fg">
                        <label class="lbl req">Last Name (English)</label>
                        <input v-model="userForm.le" class="inp modal-input" placeholder="Last name in English" />
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="fg">
                        <label class="lbl req">Email</label>
                        <input v-model="userForm.em" class="inp modal-input" placeholder="name@example.com" type="email" />
                    </div>
                </div>

                <div v-if="requiresOrganizationStructure" class="modal-section-label">โครงสร้างสังกัด</div>
                <div v-if="requiresOrganizationStructure && incompleteLegacySupportPath" class="modal-help warning">
                    สังกัดเดิม: {{ incompleteLegacySupportPath }} (ข้อมูลเดิมไม่ครบลำดับฝ่าย งาน และหน่วย หากไม่แก้สังกัด ระบบจะคงค่าเดิมไว้)
                </div>
                <div v-if="requiresOrganizationStructure" class="modal-grid" :class="{ 'single-col': !userForm.w }">
                    <div class="fg">
                        <label class="lbl req">สายงาน</label>
                        <select v-model="userForm.w" class="sel modal-input" @change="handleWorklineChange">
                            <option value="">— เลือกสายงาน —</option>
                            <option v-for="workline in worklines" :key="workline" :value="workline">
                                {{ workline }}
                            </option>
                        </select>
                    </div>

                    <div v-if="isSupportWorkline" class="fg">
                        <label class="lbl req">ฝ่าย</label>
                        <select v-model="userForm.dept" class="sel modal-input" @change="handleDeptChange">
                            <option value="">— เลือกฝ่าย —</option>
                            <option v-if="legacyDeptOption" :value="legacyDeptOption">
                                {{ legacyDeptOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="department in supportDeptsList" :key="department" :value="department">{{ department }}</option>
                        </select>
                    </div>

                    <div v-if="userForm.w && (!isSupportWorkline || userForm.dept)" class="fg">
                        <label class="lbl" :class="{ req: !isSupportWorkline || requiresSupportWork }">{{ isSupportWorkline ? 'งาน' : 'ภาควิชา' }}</label>
                        <select v-model="userForm.job" class="sel modal-input" @change="handleJobChange">
                            <option value="">— เลือก{{ isSupportWorkline ? 'งาน' : 'ภาควิชา' }} —</option>
                            <option v-if="legacyJobOption" :value="legacyJobOption">
                                {{ legacyJobOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="job in jobOptions" :key="job" :value="job">
                                {{ job }}
                            </option>
                        </select>
                        <div v-if="legacyJobOption" class="modal-help warning">
                            {{ isSupportWorkline ? 'งาน' : 'ภาควิชา' }}นี้ไม่มีในโครงสร้างปัจจุบัน หากเปลี่ยนสังกัดกรุณาเลือกใหม่
                        </div>
                    </div>

                    <div v-if="isSupportWorkline && userForm.job" class="fg">
                        <label class="lbl" :class="{ req: requiresSupportUnit }">หน่วย</label>
                        <select v-model="userForm.unit" class="sel modal-input" @change="handleUnitChange">
                            <option value="">— เลือกหน่วย —</option>
                            <option v-if="legacyUnitOption" :value="legacyUnitOption">
                                {{ legacyUnitOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="unit in unitOptions" :key="unit" :value="unit">{{ unit }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="showsPositionSection" class="modal-section-label">ข้อมูลตำแหน่ง</div>
                <div v-if="showsPositionSection" class="modal-grid">
                    <div v-if="!isDeanRole" class="fg">
                        <label class="lbl" :class="{ req: requiresPositionFields && canPickPosition }">ตำแหน่ง</label>
                        <select
                            v-model="userForm.p"
                            class="sel modal-input"
                            :disabled="!canPickPosition || (!positionOptions.length && !legacyPositionOption)"
                            @change="handlePositionChange"
                        >
                            <option v-if="positionOptions.length" value="">— เลือกตำแหน่ง —</option>
                            <option v-else value="">{{ positionEmptyLabel }}</option>
                            <option v-if="legacyPositionOption" :value="legacyPositionOption">
                                {{ legacyPositionOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="position in positionOptions" :key="position" :value="position">
                                {{ position }}
                            </option>
                        </select>
                        <div v-if="legacyPositionOption" class="modal-help warning">
                            ตำแหน่งนี้ไม่มีในสายงานปัจจุบัน กรุณาเลือกตำแหน่งใหม่ก่อนบันทึก
                        </div>
                        <div v-if="!positionOptions.length" class="modal-help">
                            {{ positionEmptyHelp }}
                        </div>
                    </div>
                    <div v-else class="fg">
                        <label class="lbl">ตำแหน่ง</label>
                        <input :value="userForm.job" class="inp modal-input" disabled />
                        <div class="modal-help">
                            บทบาทคณบดีใช้ภาควิชาเป็นตำแหน่งโดยอัตโนมัติ
                        </div>
                    </div>
                    <div class="fg">
                        <label class="lbl" :class="{ req: requiresPositionFields }">ระดับตำแหน่ง</label>
                        <select v-model="userForm.l" class="sel modal-input" :disabled="!levelOptions.length && !legacyLevelOption" @change="organizationDirty = true">
                            <option v-if="levelOptions.length" value="">— เลือกระดับตำแหน่ง —</option>
                            <option v-else value="">ยังไม่มีระดับตำแหน่งในสายงานหรือกลุ่มงาน</option>
                            <option v-if="legacyLevelOption" :value="legacyLevelOption">
                                {{ legacyLevelOption }} (ข้อมูลเดิม)
                            </option>
                            <option v-for="level in levelOptions" :key="level" :value="level">
                                {{ level }}
                            </option>
                        </select>
                        <div v-if="legacyLevelOption" class="modal-help warning">
                            ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน หากเปลี่ยนสังกัดกรุณาเลือกใหม่
                        </div>
                        <div v-if="!levelOptions.length" class="modal-help">
                            กรุณาให้ Admin เพิ่มระดับตำแหน่งก่อนกำหนดผู้ใช้
                        </div>
                    </div>
                </div>

                <div class="modal-divider"></div>

                <div class="evaluator-section">
                    <section class="workflow-group">
                        <div class="workflow-group-head">
                            <div class="workflow-title-row">
                                <span class="workflow-number">1</span>
                                <div class="workflow-copy">
                                    <h4>ลำดับในการประเมิน</h4>
                                    <p>{{ reviewerSummary }}</p>
                                </div>
                            </div>
                            <button class="btn btn-s reviewer-config-btn" type="button" @click="openReviewerModal">
                                จัดการลำดับ
                            </button>
                        </div>

                        <div v-if="assessmentReviewerTemplates.length" class="workflow-template-row">
                            <div class="fg">
                                <label class="lbl">Template ลำดับในการประเมิน</label>
                                <select
                                    :value="userForm.reviewer_template_id"
                                    class="sel modal-input"
                                    @change="selectReviewerTemplate($event.target.value)"
                                >
                                    <option value="">— ไม่ใช้ template —</option>
                                    <option
                                        v-for="template in assessmentReviewerTemplates"
                                        :key="`reviewer-template-${template.id}`"
                                        :value="template.id"
                                    >
                                        {{ template.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="modal-help workflow-template-help">
                                {{ reviewerTemplateDescription }}
                            </div>
                        </div>
                    </section>

                    <section class="workflow-group">
                        <div class="workflow-group-head">
                            <div class="workflow-title-row">
                                <span class="workflow-number">2</span>
                                <div class="workflow-copy">
                                    <h4>ลำดับการทำ IDP</h4>
                                    <p>{{ idpReviewerSummary }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="idpReviewerTemplates.length" class="workflow-template-row">
                            <div class="fg">
                                <label class="lbl">Template ลำดับการทำ IDP</label>
                                <select
                                    :value="userForm.idp_reviewer_template_id"
                                    class="sel modal-input"
                                    @change="selectIdpReviewerTemplate($event.target.value)"
                                >
                                    <option value="">— ใช้ลำดับประเมินเป็น fallback —</option>
                                    <option
                                        v-for="template in idpReviewerTemplates"
                                        :key="`idp-reviewer-template-${template.id}`"
                                        :value="template.id"
                                    >
                                        {{ template.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="modal-help workflow-template-help">
                                {{ selectedIdpReviewerTemplate ? templateChainSummary(selectedIdpReviewerTemplate) : 'ระบบจะใช้ลำดับในการประเมินเป็นค่า fallback' }}
                            </div>
                        </div>
                    </section>
                </div>

                <div v-if="showReviewerModal" class="reviewer-modal-backdrop" @click.self="closeReviewerModal">
                    <div class="reviewer-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-modal-title" @click="closeReviewerDropdown">
                        <div class="reviewer-modal-head">
                            <div>
                                <h3 id="reviewer-modal-title">จัดการลำดับการประเมิน</h3>
                                <p>เพิ่มหรือลดผู้ประเมินได้อิสระ ระบบจะส่งงานตามลำดับที่กำหนด</p>
                            </div>
                            <button class="modal-close-btn" type="button" aria-label="ปิดหน้าต่าง" @click="closeReviewerModal">
                                ×
                            </button>
                        </div>

                        <div class="reviewer-step-list">
                            <div
                                v-for="(reviewerId, index) in userForm.reviewer_ids"
                                :key="`reviewer-step-${index}`"
                                class="reviewer-step-row"
                            >
                                <div class="reviewer-step-badge">{{ index + 1 }}</div>
                                <div class="reviewer-step-main">
                                    <label class="lbl">ลำดับที่ {{ index + 1 }}</label>
                                    <input
                                        class="inp modal-input reviewer-search-input"
                                        type="text"
                                        placeholder="พิมพ์ชื่อ / ตำแหน่ง / หน่วยงาน"
                                        :value="reviewerInputValue(index, reviewerId)"
                                        @click.stop="openReviewerDropdown(index, reviewerId)"
                                        @focus="openReviewerDropdown(index, reviewerId)"
                                        @input="updateReviewerSearch(index, $event.target.value)"
                                        @keydown.escape="clearReviewerSearch"
                                    />
                                    <div v-if="isReviewerDropdownOpen(index)" class="reviewer-choice-list" @click.stop>
                                        <button
                                            v-for="person in filteredReviewerChoicesForStep(index)"
                                            :key="`reviewer-choice-${index}-${person.key}`"
                                            class="reviewer-choice-item"
                                            type="button"
                                            @mousedown.prevent="chooseReviewerStep(index, person.value)"
                                        >
                                            <span>{{ person.displayName }}</span>
                                            <small>{{ person.p || '-' }}</small>
                                        </button>
                                        <div v-if="filteredReviewerChoicesForStep(index).length === 0" class="reviewer-choice-empty">
                                            ไม่พบรายชื่อที่ค้นหา
                                        </div>
                                    </div>
                                </div>
                                <button
                                    class="btn btn-s reviewer-remove-btn"
                                    type="button"
                                    :disabled="(userForm.reviewer_ids || []).length <= 1"
                                    @click="removeReviewerStep(index)"
                                >
                                    ลบ
                                </button>
                            </div>
                        </div>

                        <div class="reviewer-modal-actions">
                            <button class="btn btn-s" type="button" @click="addReviewerStep">
                                + เพิ่มผู้ประเมิน
                            </button>
                            <button class="btn btn-p reviewer-done-btn" type="button" @click="closeReviewerModal">
                                เสร็จสิ้น
                            </button>
                        </div>
                    </div>
                </div>

                <label class="modal-checkbox">
                    <span>สถานะบัญชี</span>
                    <input v-model="userForm.act" type="checkbox" />
                    <span>ใช้งานได้</span>
                </label>

                <div class="modal-actions">
                    <button class="btn btn-s modal-action-btn" type="button" @click="closeModal">ยกเลิก</button>
                    <button class="btn btn-p modal-action-btn modal-save-btn" type="button" @click="saveUser">
                         บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="issuedCredentials" class="mo admin-user-modal">
        <div class="mo-box issued-credentials-box" role="dialog" aria-modal="true" aria-labelledby="issued-credentials-title">
            <div class="mo-h admin-user-modal-head">
                <div class="fw8 fs18" id="issued-credentials-title">ข้อมูลเข้าสู่ระบบที่เพิ่งกำหนด</div>
            </div>
            <div class="mo-b admin-user-modal-body">
                <p class="issued-credentials-note">แจ้งข้อมูลนี้ให้ผู้ใช้ก่อนปิดหน้าต่าง รหัสผ่านเดิมจะเปิดดูย้อนหลังไม่ได้ หากลืมรหัสให้ Admin กำหนดใหม่</p>
                <div class="fg">
                    <label class="lbl">Username</label>
                    <input class="inp modal-input" :value="issuedCredentials.username" readonly />
                </div>
                <div class="fg">
                    <label class="lbl">Password</label>
                    <input class="inp modal-input" :value="issuedCredentials.password" readonly />
                </div>
                <div class="modal-actions">
                    <button class="btn btn-p modal-action-btn" type="button" @click="closeIssuedCredentials">รับทราบและปิด</button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="activeReviewerTemplateModal" class="reviewer-modal-backdrop" @click.self="closeReviewerTemplateModal">
        <div class="reviewer-template-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-template-modal-title">
            <div class="reviewer-modal-head">
                <div>
                    <h3 id="reviewer-template-modal-title">{{ activeReviewerTemplateTitle }}</h3>
                    <p>{{ activeReviewerTemplateSubtitle }}</p>
                </div>
                <button class="modal-close-btn" type="button" aria-label="ปิดหน้าต่าง" @click="closeReviewerTemplateModal">
                    ×
                </button>
            </div>

            <div class="reviewer-template-modal-body">
                <div v-if="!showAssessmentTemplateCreate && !selectedAssessmentTemplate" class="assessment-template-list-view">
                    <div class="assessment-template-list-head">
                        <div>
                            <h4>{{ activeReviewerTemplateTitle }} ทั้งหมด</h4>
                            <p>รายการ workflow ที่บันทึกไว้ในระบบ</p>
                        </div>
                        <button class="btn btn-p" type="button" @click="startCreateAssessmentTemplate">
                            + เพิ่ม{{ activeReviewerTemplateTitle }}
                        </button>
                    </div>

                    <div v-if="!activeReviewerTemplateList.length" class="reviewer-template-empty">
                        ยังไม่มี{{ activeReviewerTemplateTitle }}
                    </div>

                    <div v-else class="assessment-template-list">
                        <div
                            v-for="template in activeReviewerTemplateList"
                            :key="`${activeReviewerTemplateType}-template-${template.id}`"
                            class="assessment-template-row"
                            role="button"
                            tabindex="0"
                            @click="openAssessmentTemplateDetail(template)"
                            @keydown.enter.prevent="openAssessmentTemplateDetail(template)"
                        >
                            <div class="assessment-template-row-main">
                                <div class="assessment-template-title-line">
                                    <div>
                                        <div class="reviewer-template-name">{{ template.name }}</div>
                                        <div class="reviewer-template-desc">{{ template.description || 'ไม่มีคำอธิบาย' }}</div>
                                    </div>
                                </div>

                                <div v-if="(template.steps || []).length" class="assessment-step-track">
                                    <template
                                        v-for="(step, stepIndex) in template.steps"
                                        :key="`${activeReviewerTemplateType}-template-${template.id}-step-${step.step}`"
                                    >
                                        <div class="assessment-step-pill">
                                            <span>{{ stepIndex + 1 }}</span>
                                            <strong>{{ templateStepLabel(step) }}</strong>
                                        </div>
                                        <div
                                            v-if="stepIndex < (template.steps || []).length - 1"
                                            class="assessment-step-arrow"
                                        >
                                            ->
                                        </div>
                                    </template>
                                </div>
                                <div v-else class="assessment-template-chain-text">
                                    ยังไม่ได้กำหนดผู้ประเมิน
                                </div>
                            </div>
                            <div class="assessment-template-row-meta">
                                <div class="assessment-template-stat wide">
                                    <b>{{ templateAssignedUserIds(template).length }}</b>
                                    <span>ผู้ใช้</span>
                                </div>
                                <button class="assessment-template-detail-btn" type="button" @click.stop="openAssessmentTemplateDetail(template)">
                                    ดู
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="!showAssessmentTemplateCreate && selectedAssessmentTemplate" class="assessment-template-detail-view">
                    <div class="assessment-template-detail-head">
                        <div>
                            <template v-if="isEditingSelectedAssessmentTemplate">
                                <input
                                    v-model="assessmentTemplateForm.name"
                                    class="assessment-template-inline-name"
                                    :placeholder="`ชื่อ${activeReviewerTemplateTitle}`"
                                />
                                <textarea
                                    v-model="assessmentTemplateForm.description"
                                    class="assessment-template-inline-desc"
                                    placeholder="คำอธิบาย"
                                    rows="1"
                                ></textarea>
                            </template>
                            <template v-else>
                                <h4>{{ selectedAssessmentTemplate.name }}</h4>
                                <p>{{ selectedAssessmentTemplate.description || 'ไม่มีคำอธิบาย' }}</p>
                            </template>
                        </div>
                        <div class="assessment-template-detail-actions">
                            <button
                                v-if="!isEditingSelectedAssessmentTemplate"
                                class="assessment-template-detail-btn"
                                type="button"
                                @click="startEditAssessmentTemplate(selectedAssessmentTemplate)"
                            >
                                แก้ไข
                            </button>
                            <button
                                v-if="isEditingSelectedAssessmentTemplate"
                                class="assessment-template-detail-btn"
                                type="button"
                                @click="cancelAssessmentTemplateEdit"
                            >
                                ยกเลิก
                            </button>
                            <button
                                v-if="isEditingSelectedAssessmentTemplate"
                                class="btn btn-p"
                                type="button"
                                @click="saveAssessmentTemplate"
                            >
                                ยืนยัน
                            </button>
                            <button
                                v-if="!isEditingSelectedAssessmentTemplate"
                                class="assessment-template-delete-btn"
                                type="button"
                                @click="deleteAssessmentTemplate(selectedAssessmentTemplate)"
                            >
                                ลบลำดับ
                            </button>
                        </div>
                        <div v-if="isEditingSelectedAssessmentTemplate" class="assessment-step-track detail editable">
                            <template
                                v-for="(reviewerId, index) in assessmentTemplateForm.reviewer_ids"
                                :key="`detail-template-selected-reviewer-${reviewerId}-${index}`"
                            >
                                <div class="assessment-step-pill editable">
                                    <span>{{ index + 1 }}</span>
                                    <select
                                        :value="reviewerId"
                                        class="assessment-step-inline-select"
                                        @change="assessmentTemplateForm.reviewer_ids = assessmentTemplateForm.reviewer_ids.map((id, itemIndex) => itemIndex === index ? selectedEvaluatorId($event.target.value) : id)"
                                    >
                                        <option value="">เลือกผู้ประเมิน</option>
                                        <option
                                            v-for="person in evaluatorOptions"
                                            :key="`detail-template-reviewer-option-${index}-${person.value}`"
                                            :value="person.value"
                                            :disabled="selectedTemplateReviewerIds.includes(selectedEvaluatorId(person.value)) && selectedEvaluatorId(person.value) !== reviewerId"
                                        >
                                            {{ person.label }}
                                        </option>
                                    </select>
                                    <button class="assessment-step-inline-remove" type="button" @click="removeTemplateReviewer(index)">×</button>
                                </div>
                                <div
                                    v-if="index < assessmentTemplateForm.reviewer_ids.length - 1"
                                    class="assessment-step-arrow"
                                >
                                    ->
                                </div>
                            </template>
                            <div class="assessment-step-pill add">
                                <span>+</span>
                                <select v-model="assessmentTemplateForm.reviewer_pick" class="assessment-step-inline-select" @change="addTemplateReviewer">
                                    <option value="">เพิ่มผู้ประเมิน</option>
                                    <option
                                        v-for="person in templateReviewerOptions"
                                        :key="`detail-template-reviewer-add-${person.value}`"
                                        :value="person.value"
                                    >
                                        {{ person.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div v-else-if="(selectedAssessmentTemplate.steps || []).length" class="assessment-step-track detail">
                            <template
                                v-for="(step, stepIndex) in selectedAssessmentTemplate.steps"
                                :key="`selected-assessment-template-step-${step.step}`"
                            >
                                <div class="assessment-step-pill">
                                    <span>{{ stepIndex + 1 }}</span>
                                    <strong>{{ templateStepLabel(step) }}</strong>
                                </div>
                                <div
                                    v-if="stepIndex < (selectedAssessmentTemplate.steps || []).length - 1"
                                    class="assessment-step-arrow"
                                >
                                    ->
                                </div>
                            </template>
                        </div>
                        <div v-else class="assessment-template-chain-text">
                            ยังไม่ได้กำหนดผู้ประเมิน
                        </div>
                    </div>

                    <div class="assessment-template-member-panel">
                        <div class="template-builder-head">
                            <div>
                                <h4>ผู้ใช้ที่ใช้ลำดับนี้ {{ templateAssignedUsers(selectedAssessmentTemplate).length }} คน</h4>
                            </div>
                        </div>

                        <div class="template-picker-row">
                            <select v-model="assessmentTemplateMemberPick" class="sel modal-input">
                                <option value="">เพิ่มผู้ใช้{{ activeReviewerTemplateTitle }}</option>
                                <option
                                    v-for="person in selectedAssessmentTemplateUserOptions"
                                    :key="`assessment-template-member-${person.value}`"
                                    :value="person.value"
                                >
                                    {{ person.label }}
                                </option>
                            </select>
                            <button class="btn btn-p" type="button" @click="addAssessmentTemplateMember">
                                + เพิ่มสมาชิก
                            </button>
                        </div>

                        <div v-if="templateAssignedUsers(selectedAssessmentTemplate).length" class="assessment-template-member-list">
                            <div
                                v-for="member in templateAssignedUsers(selectedAssessmentTemplate)"
                                :key="`assessment-template-member-row-${member.id}`"
                                class="assessment-template-member-row"
                            >
                                <div class="assessment-template-member-avatar">
                                    {{ member.name.slice(0, 1) }}
                                </div>
                                <div>
                                    <strong>{{ member.name }}</strong>
                                    <span>{{ member.position }} · {{ member.department }}</span>
                                </div>
                                <button class="assessment-template-member-remove" type="button" @click="removeAssessmentTemplateMember(member)">
                                    ลบ
                                </button>
                            </div>
                        </div>
                        <div v-else class="reviewer-template-empty compact">
                            ยังไม่มีผู้ใช้ในลำดับนี้
                        </div>
                    </div>
                </div>

                <div v-else class="assessment-template-builder">
                    <div class="template-builder-grid">
                        <div class="fg">
                            <label class="lbl req">ชื่อ{{ activeReviewerTemplateTitle }}</label>
                            <input
                                v-model="assessmentTemplateForm.name"
                                class="inp modal-input"
                                :placeholder="activeReviewerTemplateType === 'idp' ? 'เช่น ลำดับ IDP สายสนับสนุน' : 'เช่น ลำดับประเมินสายสนับสนุน'"
                            />
                        </div>
                        <div class="fg">
                            <label class="lbl">คำอธิบาย</label>
                            <input
                                v-model="assessmentTemplateForm.description"
                                class="inp modal-input"
                                placeholder="อธิบายว่าใช้กับกลุ่มไหน"
                            />
                        </div>
                    </div>

                    <div class="template-builder-section">
                        <div class="template-builder-head">
                            <div>
                                <h4>ลำดับผู้ประเมิน</h4>
                                <p>เลือกเป็นชื่อคนจริงตามลำดับ เช่น นาย A -> นาย B -> นาย C</p>
                            </div>
                        </div>

                        <div class="template-picker-row">
                            <select v-model="assessmentTemplateForm.reviewer_pick" class="sel modal-input">
                                <option value="">เลือกผู้ประเมิน</option>
                                <option
                                    v-for="person in templateReviewerOptions"
                                    :key="`template-reviewer-${person.value}`"
                                    :value="person.value"
                                >
                                    {{ person.label }}
                                </option>
                            </select>
                            <button class="btn btn-s" type="button" @click="addTemplateReviewer">+ เพิ่มลำดับ</button>
                        </div>

                        <div v-if="selectedTemplateReviewerIds.length" class="template-chain-list">
                            <div
                                v-for="(reviewerId, index) in selectedTemplateReviewerIds"
                                :key="`template-selected-reviewer-${reviewerId}`"
                                class="template-chain-item"
                            >
                                <span class="template-chain-no">{{ index + 1 }}</span>
                                <span>{{ templatePersonLabel(reviewerId) }}</span>
                                <button class="btn btn-s" type="button" @click="removeTemplateReviewer(index)">ลบ</button>
                            </div>
                        </div>
                        <div v-else class="reviewer-template-empty compact">
                            ยังไม่ได้เพิ่มผู้ประเมิน
                        </div>
                    </div>

                    <div v-if="!isEditingAssessmentTemplate" class="template-builder-section">
                        <div class="template-builder-head">
                            <div>
                                <h4>ผู้ใช้ที่จะใช้ลำดับนี้</h4>
                                <p>เลือกได้ภายหลัง หากต้องการสร้างลำดับเก็บไว้ก่อน</p>
                            </div>
                        </div>

                        <div class="template-picker-row">
                            <select v-model="assessmentTemplateForm.user_pick" class="sel modal-input">
                                <option value="">เลือกผู้ใช้</option>
                                <option
                                    v-for="person in templateAssignmentUserOptions"
                                    :key="`template-user-${person.value}`"
                                    :value="person.value"
                                >
                                    {{ person.label }}
                                </option>
                            </select>
                            <button class="btn btn-s" type="button" @click="addTemplateAssignmentUser">+ เพิ่มผู้ใช้</button>
                        </div>

                        <div v-if="selectedTemplateUserIds.length" class="template-user-list">
                            <div
                                v-for="(userId, index) in selectedTemplateUserIds"
                                :key="`template-selected-user-${userId}`"
                                class="template-user-chip"
                            >
                                <span>{{ templatePersonLabel(userId) }}</span>
                                <button type="button" @click="removeTemplateAssignmentUser(index)">×</button>
                            </div>
                        </div>
                        <div v-else class="reviewer-template-empty compact">
                            ยังไม่ได้เพิ่มผู้ใช้
                        </div>
                    </div>

                    <p v-if="assessmentTemplateError" class="reviewer-template-form-error" role="alert">
                        {{ assessmentTemplateError }}
                    </p>
                </div>

            </div>

            <div class="reviewer-template-modal-actions">
                <button class="btn btn-s" type="button" @click="closeReviewerTemplateModal">ปิด</button>
                <button
                    v-if="showAssessmentTemplateCreate || selectedAssessmentTemplate"
                    class="btn btn-s"
                    type="button"
                    @click="returnToAssessmentTemplateList"
                >
                    กลับไปหน้ารายการ
                </button>
                <button
                    v-if="showAssessmentTemplateCreate"
                    class="btn btn-p"
                    type="button"
                    :disabled="isSavingAssessmentTemplate"
                    @click="saveAssessmentTemplate"
                >
                    {{ isSavingAssessmentTemplate ? 'กำลังบันทึก...' : (isEditingAssessmentTemplate ? 'ยืนยัน' : `บันทึก${activeReviewerTemplateTitle}`) }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.reviewer-template-form-error {
    margin: 14px 0 0;
    color: #b42318;
    font-size: 14px;
    font-weight: 600;
}

.admin-user-modal {
    align-items: center;
    overflow-y: auto;
}

.admin-user-modal-box {
    width: min(800px, calc(100vw - 32px));
    max-height: min(88vh, 760px);
    margin: 14px 0;
    border-radius: 14px;
}

.admin-user-modal-head {
    padding: 16px 18px;
}

.admin-user-modal-body {
    padding: 18px;
}

.admin-user-note {
    margin-bottom: 16px;
    padding: 10px 12px;
    border-radius: 6px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 13px;
}

.admin-password-input {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-password-input .inp {
    min-width: 0;
    flex: 1;
}

.admin-password-field {
    position: relative;
    min-width: 0;
    flex: 1;
}

.admin-password-field .inp {
    width: 100%;
    padding-right: 72px;
}

.admin-password-toggle {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    min-height: 32px;
    padding: 0 8px;
    border: 0;
    border-radius: 5px;
    background: transparent;
    color: var(--navy);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.admin-password-toggle:hover,
.admin-password-toggle:focus-visible {
    background: #eef2f7;
}

.issued-credentials-box {
    max-width: 480px;
}

.issued-credentials-box .fg {
    margin-bottom: 14px;
}

.issued-credentials-note {
    margin: 0 0 18px;
    color: var(--text2);
    font-size: 13px;
    line-height: 1.55;
}

.admin-user-warning {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
}

.admin-user-warning-title {
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 900;
}

.admin-user-warning ul {
    margin: 0;
    padding-left: 18px;
}

.admin-user-warning li {
    margin: 3px 0;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.45;
}

.org-edit-summary {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #dbeafe;
    border-radius: 6px;
    background: #eff6ff;
    color: var(--navy);
}

.modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 20px;
    row-gap: 20px;
}

.modal-section-label {
    margin: 4px 0 10px;
    color: var(--navy);
    font-size: 13px;
    font-weight: 800;
}

.modal-grid + .modal-section-label {
    margin-top: 18px;
}

.modal-grid.single-col {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.modal-input {
    min-height: 44px;
    border-radius: 8px;
    border-color: var(--border);
    font-size: 14px;
    padding-top: 8px;
    padding-bottom: 8px;
}

.modal-input:disabled {
    background: var(--color-disabled-bg);
    color: var(--color-disabled-text);
}

.modal-help {
    margin-top: 7px;
    color: var(--color-text-muted);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
}

.modal-help.warning {
    color: #c2410c;
}

.evaluator-section {
    margin: 4px 0 16px;
    display: grid;
    gap: 12px;
}

.workflow-role-panel,
.workflow-group {
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.workflow-role-panel {
    padding: 16px;
}

.user-role-top-panel {
    margin-bottom: 14px;
}

.workflow-group {
    display: grid;
    gap: 14px;
    padding: 16px;
}

.evaluator-role-field {
    max-width: 420px;
}

.evaluator-role-field .lbl {
    margin-bottom: 7px;
    color: var(--text3);
    font-size: 14px;
    font-weight: 800;
}

.evaluator-role-field .modal-input {
    min-height: 47px;
    padding-left: 14px;
    padding-right: 14px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 500;
}

.workflow-group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.workflow-title-row {
    min-width: 0;
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
}

.workflow-number {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: #fff2ea;
    color: var(--accent);
    font-size: 18px;
    font-weight: 900;
}

.workflow-copy {
    min-width: 0;
}

.workflow-copy h4 {
    margin: 0;
    color: var(--text);
    font-size: 17px;
    font-weight: 900;
}

.workflow-copy p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
}

.workflow-template-row {
    display: grid;
    grid-template-columns: minmax(260px, 1fr);
    gap: 12px;
    align-items: end;
    padding: 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
}

.workflow-template-help {
    grid-column: 1 / -1;
    margin-top: 0;
}

.reviewer-config-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
}

.reviewer-template-card {
    display: grid;
    gap: 8px;
    margin-top: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #f8fafc;
}

.reviewer-template-grid {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.reviewer-template-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.reviewer-config-copy {
    min-width: 0;
    display: grid;
    gap: 5px;
}

.reviewer-config-title {
    color: var(--text);
    font-size: 15px;
    font-weight: 800;
}

.reviewer-config-summary {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.45;
}

.reviewer-config-btn {
    flex: 0 0 auto;
}

.reviewer-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.42);
}

.reviewer-modal {
    width: min(720px, 100%);
    max-height: min(82vh, 720px);
    overflow: visible;
    border: 1px solid #dbe3ef;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
}

.reviewer-template-modal {
    width: min(900px, 100%);
    max-height: min(84vh, 760px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #dbe3ef;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
}

.reviewer-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.reviewer-modal-head h3 {
    margin: 0 0 4px;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
}

.reviewer-modal-head p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
}

.reviewer-template-modal-body {
    display: grid;
    gap: 14px;
    overflow-y: auto;
    padding: 18px 20px;
    background: #f8fafc;
}

.reviewer-template-empty {
    padding: 28px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #64748b;
    text-align: center;
    font-weight: 700;
}

.reviewer-template-overview-card {
    display: grid;
    gap: 14px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.reviewer-template-overview-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.reviewer-template-name {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    color: var(--text);
    font-size: 17px;
    font-weight: 900;
}

.reviewer-template-chain-badge,
.reviewer-template-default-badge,
.reviewer-template-scope-pill {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 4px 9px;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 12px;
    font-weight: 800;
}

.reviewer-template-chain-badge {
    background: #e0f2fe;
    color: #0369a1;
}

.reviewer-template-desc {
    margin-top: 4px;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.reviewer-template-step-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.reviewer-template-step-card {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fbfdff;
}

.reviewer-template-step-no {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-weight: 900;
}

.reviewer-template-step-title {
    color: var(--text);
    font-size: 13px;
    font-weight: 900;
    line-height: 1.35;
}

.reviewer-template-step-meta {
    margin-top: 3px;
    color: var(--color-text-muted);
    font-size: 11px;
    font-weight: 700;
}

.reviewer-template-scope-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.reviewer-template-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
}

.assessment-template-builder {
    display: grid;
    gap: 14px;
}

.assessment-template-list-view {
    display: grid;
    gap: 18px;
}

.assessment-template-list-head,
.assessment-template-builder-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.assessment-template-list-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
}

.assessment-template-list-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.assessment-template-list {
    display: grid;
    gap: 12px;
}

.assessment-template-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 190px;
    gap: 18px;
    padding: 18px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.assessment-template-row:hover {
    border-color: #f2b7a3;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

.assessment-template-row-main {
    min-width: 0;
}

.assessment-template-title-line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.assessment-template-chain-text {
    margin-top: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.5;
}

.assessment-step-track {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
    padding: 12px;
    border-radius: 8px;
    background: #f8fafc;
}

.assessment-step-track.detail {
    margin-top: 12px;
}

.assessment-step-pill {
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    max-width: 100%;
    gap: 8px;
    padding: 7px 10px 7px 7px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 900;
}

.assessment-step-pill span {
    width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-size: 12px;
}

.assessment-step-pill strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-step-arrow {
    color: #cbd5e1;
    font-weight: 900;
}

.assessment-template-row-meta {
    display: grid;
    grid-template-columns: 1fr;
    align-content: stretch;
    gap: 10px;
    padding: 12px;
    border-radius: 8px;
    background: #fff7ed;
}

.assessment-template-stat {
    display: grid;
    gap: 2px;
    padding: 10px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #fed7aa;
}

.assessment-template-stat.wide {
    min-height: 86px;
}

.assessment-template-stat b {
    color: #cf3c23;
    font-size: 24px;
    line-height: 1;
}

.assessment-template-stat span {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
}

.assessment-template-users {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.45;
}

.assessment-template-detail-btn {
    min-height: 36px;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fff;
    color: #cf3c23;
    font-weight: 900;
    cursor: pointer;
}

.assessment-template-delete-btn {
    min-height: 36px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff;
    color: #dc2626;
    font-weight: 900;
    cursor: pointer;
}

.assessment-template-delete-btn:hover {
    background: #fef2f2;
    border-color: #fca5a5;
}

.assessment-template-detail-view {
    display: grid;
    gap: 14px;
}

.assessment-template-detail-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.assessment-template-detail-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
}

.assessment-template-detail-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}

.assessment-template-inline-name {
    display: block;
    width: min(100%, 560px);
    max-width: 560px;
    padding: 0 2px 4px;
    border: 0;
    border-bottom: 2px solid transparent;
    outline: 0;
    background: transparent;
    color: var(--text);
    font-size: 20px;
    font-weight: 900;
    line-height: 1.25;
}

.assessment-template-inline-desc {
    display: block;
    width: min(100%, 760px);
    max-width: 760px;
    min-height: 30px;
    margin-top: 4px;
    padding: 0 2px 4px;
    resize: none;
    overflow: hidden;
    border: 0;
    border-bottom: 2px solid transparent;
    outline: 0;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.5;
    font-family: inherit;
}

.assessment-template-inline-name:hover,
.assessment-template-inline-desc:hover {
    border-bottom-color: #e2e8f0;
}

.assessment-template-inline-name:focus,
.assessment-template-inline-desc:focus {
    border-bottom-color: #cf3c23;
}

.assessment-template-detail-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.assessment-template-detail-head .assessment-step-track,
.assessment-template-detail-head .assessment-template-chain-text {
    grid-column: 1 / -1;
}

.assessment-step-track.editable {
    align-items: center;
    border: 1px dashed #dbe4ef;
}

.assessment-step-pill.editable,
.assessment-step-pill.add {
    padding-right: 8px;
}

.assessment-step-pill.add {
    border-style: dashed;
    color: #64748b;
}

.assessment-step-inline-select {
    min-width: 160px;
    max-width: 320px;
    border: 0;
    outline: 0;
    background: transparent;
    color: #334155;
    font: inherit;
    font-weight: 900;
    cursor: pointer;
}

.assessment-step-inline-remove {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border: 0;
    border-radius: 999px;
    background: #fff1f2;
    color: #dc2626;
    font-size: 16px;
    font-weight: 900;
    line-height: 1;
    cursor: pointer;
}

.assessment-template-member-panel {
    display: grid;
    gap: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.assessment-template-member-list {
    display: grid;
    gap: 10px;
}

.assessment-template-member-row {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fbfdff;
}

.assessment-template-member-avatar {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-weight: 900;
}

.assessment-template-member-row strong,
.assessment-template-member-row span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-template-member-row strong {
    color: var(--text);
    font-size: 14px;
    font-weight: 900;
}

.assessment-template-member-row span {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.assessment-template-member-remove {
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff;
    color: #dc2626;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
}

.assessment-template-member-remove:hover {
    background: #fef2f2;
    border-color: #fca5a5;
}

.template-builder-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
    gap: 12px;
}

.template-builder-section {
    display: grid;
    gap: 12px;
    padding: 16px;
    border: 1px solid #dbe4ef;
    border-radius: 8px;
    background: #fff;
}

.template-builder-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.template-builder-head h4 {
    margin: 0;
    color: var(--text);
    font-size: 16px;
    font-weight: 900;
}

.template-builder-head p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.template-picker-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
}

.template-chain-list {
    display: grid;
    gap: 8px;
}

.template-chain-item {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fbfdff;
}

.template-chain-no {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #fff3ec;
    color: #cf3c23;
    font-weight: 900;
}

.template-user-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.template-user-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    padding: 8px 10px;
    border: 1px solid #dbe4ef;
    border-radius: 999px;
    background: #f8fafc;
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
}

.template-user-chip button {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border: 0;
    border-radius: 999px;
    background: #e2e8f0;
    color: #64748b;
    cursor: pointer;
}

.reviewer-template-empty.compact {
    padding: 16px;
    text-align: left;
}

.modal-close-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

.reviewer-step-list {
    display: grid;
    gap: 10px;
    padding: 18px 20px;
    overflow: visible;
}

.reviewer-step-row {
    position: relative;
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto;
    align-items: end;
    gap: 12px;
    padding: 14px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #f8fafc;
    overflow: visible;
}

.reviewer-step-row:has(.reviewer-choice-list) {
    z-index: 10;
}

.reviewer-step-badge {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: #fff1e8;
    color: #c93616;
    font-size: 15px;
    font-weight: 900;
}

.reviewer-step-main {
    position: relative;
    min-width: 0;
    overflow: visible;
}

.reviewer-step-main .lbl {
    margin-bottom: 7px;
    color: #64748b;
    font-size: 13px;
    font-weight: 800;
}

.reviewer-step-main .modal-input {
    width: 100%;
    min-height: 44px;
    border-radius: 8px;
}

.reviewer-search-input {
    padding: 0 13px;
    background: #fff;
}

.reviewer-choice-list {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 120;
    display: grid;
    gap: 6px;
    box-sizing: border-box;
    width: auto;
    max-height: 220px;
    padding: 8px;
    overflow-y: auto;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.16);
}

.reviewer-choice-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 9px 11px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: var(--text);
    text-align: left;
    cursor: pointer;
}

.reviewer-choice-item:hover {
    border-color: #c93616;
    background: #fff7ed;
}

.reviewer-choice-item span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
    font-weight: 800;
}

.reviewer-choice-item small {
    flex: 0 1 auto;
    min-width: 120px;
    overflow: hidden;
    color: #64748b;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 700;
}

.reviewer-choice-empty {
    padding: 10px 12px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: var(--color-text-muted);
    font-size: 13px;
    font-weight: 700;
}

.reviewer-remove-btn {
    min-height: 38px;
}

.reviewer-remove-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.reviewer-modal-actions {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 16px 20px 20px;
    border-top: 1px solid #e2e8f0;
}

.reviewer-done-btn {
    min-width: 96px;
    justify-content: center;
}

.req::after {
    content: ' *';
    color: #ef4444;
}

.modal-divider {
    height: 1px;
    margin: 12px 0;
    background: #dbe3ef;
}

.modal-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 20px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text2);
}

.modal-checkbox input {
    width: 18px;
    height: 18px;
    accent-color: #1d70d6;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-action-btn {
    min-width: 82px;
    min-height: 38px;
    justify-content: center;
    font-size: 14px;
}

.modal-save-btn {
    background: #2563eb;
    color: #fff;
}

.modal-save-btn:hover {
    background: #1d4ed8;
}

@media (max-width: 720px) {
    .modal-grid,
    .modal-grid.single-col {
        grid-template-columns: 1fr;
    }

    .evaluator-role-field {
        max-width: none;
    }

    .workflow-group-head,
    .reviewer-config-card,
    .reviewer-modal-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .workflow-template-row,
    .reviewer-template-grid {
        grid-template-columns: 1fr;
    }

    .workflow-title-row {
        grid-template-columns: 34px minmax(0, 1fr);
    }

    .workflow-number {
        width: 34px;
        height: 34px;
        font-size: 16px;
    }

    .reviewer-template-actions {
        justify-content: flex-start;
    }

    .reviewer-template-step-grid {
        grid-template-columns: 1fr;
    }

    .reviewer-template-modal-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .assessment-template-list-head,
    .assessment-template-builder-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .assessment-template-row,
    .template-builder-grid {
        grid-template-columns: 1fr;
    }

    .assessment-template-row-meta {
        grid-template-columns: 1fr;
    }

    .assessment-template-detail-head,
    .template-picker-row {
        grid-template-columns: 1fr;
    }

    .reviewer-step-row {
        grid-template-columns: 34px minmax(0, 1fr);
    }

    .reviewer-remove-btn {
        grid-column: 2;
        justify-self: start;
    }

    .reviewer-choice-item {
        align-items: flex-start;
        flex-direction: column;
    }

    .reviewer-choice-item small {
        min-width: 0;
        text-align: left;
    }
}
</style>
