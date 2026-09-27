<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage, useRemember } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import AssessmentSummaryBand from '../../Components/AssessmentSummaryBand.vue';
import FacultyOverview from '../Analytics/FacultyOverview.vue';
import { NAV_CONFIG, PAGE_TITLES, ROLES_CONFIG } from '../../data';
import EmployeeAssess from '../Employee/EmployeeAssess.vue';
import EmployeeGap from '../Employee/EmployeeGap.vue';
import EmployeeIDP from '../Employee/EmployeeIDP.vue';
import EmployeeIDPDetail from '../Employee/EmployeeIDPDetail.vue';
import EmployeeProgress from '../Employee/EmployeeProgress.vue';
import IdpItemApproval from './IdpItemApproval.vue';
import IdpActivityProgressReview from './IdpActivityProgressReview.vue';
import FcTopicApproval from '../Employee/FcTopicApproval.vue';
import { assessmentReviewRoster, isInProgressAssessmentReview, isPendingAssessmentReview } from '../../utils/assessmentReviewRoster';
import { availableAssessmentHubTab, initialAssessmentHubTab } from '../../utils/assessmentHubTab';
const selectedEmployee = ref(null);
const props = defineProps({
    roleKey: { type: String, default: null },
    idpReviewItems: { type: Array, default: () => [] },
    embedded: { type: Boolean, default: false },
    embeddedPage: { type: String, default: '' },
});

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const normalizeRoleKey = (role) => ({
    manager_dept: 'dept_head',
    manager: 'dean',
}[role] || role || 'dept_head');
const defaultPageForRole = () => 'emp-assess';

const page = usePage();
const activeCycleName = computed(() => page.props.activeCycleName || 'รอบประเมินปัจจุบัน');
const idpTeamTab = ref('approval');
const uniqueIdpPersonCount = (items) => new Set(items.map((item) => String(item.userId || item.userSso || item.userName))).size;
const idpApprovalCount = computed(() => uniqueIdpPersonCount(props.idpReviewItems.filter((item) => item.canReview)));
const idpProgressItems = computed(() => page.props.idpProgressReviewItems || []);
const teamIdpAnalytics = computed(() => page.props.teamIdpAnalytics || null);
const idpTrackingCount = computed(() => Number(
    teamIdpAnalytics.value?.idpSummary?.requiredEmployees
    ?? uniqueIdpPersonCount(idpProgressItems.value),
));
const idpOverdueCount = computed(() => uniqueIdpPersonCount(
    (teamIdpAnalytics.value?.idpDetails || idpProgressItems.value).filter((item) => item.isOverdue),
));
const idpPendingProgressReviewCount = computed(() => uniqueIdpPersonCount(
    idpProgressItems.value.filter((item) => item.canReview),
));
const users = ref(clone(page.props.reviewerTeamUsers ?? page.props.users ?? []));
const requestedPage = ref(typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null);
const initialRoleKey = normalizeRoleKey(props.roleKey || page.props.roleKey || page.props.currentUser?.r || page.props.auth?.user?.role_key || 'dept_head');
const rememberedHeadState = useRemember({
    showSidebar: true,
}, 'UnifiedReviewerDashboardV2');

const showSidebar = computed({
    get: () => rememberedHeadState.value.showSidebar !== false,
    set: (value) => {
        rememberedHeadState.value.showSidebar = value;
    },
});
const activePage = ref(props.embeddedPage || requestedPage.value || defaultPageForRole(initialRoleKey));

const learningMethods = ref([
    {
        key: 'experiential',
        label: 'Experiential Learning',
        desc: 'การเรียนรู้ผ่านประสบการณ์จากการทำงานจริง',
        color: '#ea580c',
    },
    {
        key: 'social',
        label: 'Social Learning',
        desc: 'การเรียนรู้ผ่านบุคคลอื่นและการแลกเปลี่ยนประสบการณ์',
        color: '#16a34a',
    },
    {
        key: 'formal',
        label: 'Formal Learning',
        desc: 'การเรียนรู้อย่างเป็นทางการผ่านหลักสูตรหรือการอบรม',
        color: '#2563eb',
    },
]);

const authRoleKey = computed(() => normalizeRoleKey(props.roleKey || page.props.roleKey || page.props.currentUser?.r || page.props.auth?.user?.role_key || initialRoleKey));
const fcTopicApprovalModule = computed(() => page.props.fcTopicApprovalModule || { enabled: false, items: [] });
const assessmentApprovalModule = computed(() => page.props.assessmentApprovalModule || { enabled: false, items: [], pendingCount: 0 });
const idpReviewModule = computed(() => page.props.idpReviewModule || { enabled: false, assignmentCount: 0 });
const assessmentHubTab = ref(initialAssessmentHubTab(fcTopicApprovalModule.value.enabled));
const currentRoleData = computed(() => ROLES_CONFIG[authRoleKey.value] || ROLES_CONFIG.dept_head || ROLES_CONFIG.supervisor);
const navSections = computed(() => {
    const sections = [{
        sec: 'ของฉัน (บุคลากร)',
        items: [
            { id: 'emp-assess', ic: '', lb: 'ประเมินตนเอง' },
            { id: 'emp-gap', ic: '', lb: 'ผลการประเมิน' },
            { id: 'emp-idp', ic: '', lb: 'IDP ของฉัน' },
            { id: 'emp-progress', ic: '', lb: 'อัปเดตความก้าวหน้า' },
            { id: 'emp-idp-detail', ic: '', lb: 'รายละเอียด IDP' },
        ],
    }];
    const isHeadRole = ['dept_head', 'division_head', 'academic_department_head', 'supervisor'].includes(authRoleKey.value);

    const assignedItems = [
        ...((fcTopicApprovalModule.value.enabled || assessmentApprovalModule.value.enabled)
            ? [{ id: 'dh-assess', ic: '', lb: 'อนุมัติการประเมิน' }]
            : []),
        ...(isHeadRole || assessmentApprovalModule.value.enabled ? [{ id: 'sup-gap', ic: '', lb: 'ผลการประเมินของทีม' }] : []),
        ...(idpReviewModule.value.enabled ? [{ id: 'dh-idp', ic: '', lb: 'อนุมัติแผนและผล IDP' }] : []),
    ];

    if (assignedItems.length) {
        sections.push({ sec: 'งานที่ได้รับมอบหมาย', items: assignedItems });
    }

    return sections;
});
const pageTitle = computed(() => ({
    'dh-fc-topic-approval': 'พิจารณาหัวข้อการประเมิน',
    'dh-assess': 'อนุมัติการประเมิน',
    'dh-idp': 'อนุมัติแผนและผล IDP',
}[activePage.value] || PAGE_TITLES[activePage.value] || activePage.value));
const authUserId = computed(() => page.props.auth?.user?.id ? String(page.props.auth.user.id) : '');
const authUserName = computed(() => page.props.auth?.user?.name || '');
const implementedPages = new Set([
    'emp-assess',
    'emp-gap',
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
    'sup-gap',
    'dh-idp',
    'dh-assess',
    'dh-fc-topic-approval',
]);

const currentUser = computed(() =>
    page.props.currentUser
    || users.value.find((user) => String(user.db_id || '') === authUserId.value)
    || users.value.find((user) => user.sso === authUserId.value)
    || users.value.find((user) => user.n === authUserName.value)
    || users.value.find((user) => normalizeRoleKey(user.r) === authRoleKey.value)
    || {
        sso: authUserId.value || 'current-head',
        t: '',
        n: authUserName.value || currentRoleData.value.name,
        p: currentRoleData.value.pos,
        d: '',
        r: authRoleKey.value,
        act: true,
    },
);
const selfAssessmentBlockReasons = computed(() => {
    const user = currentUser.value || {};
    const rawStructureIssues = Array.isArray(user.structureIssues) ? user.structureIssues : [];
    const reasons = rawStructureIssues.filter((reason) => reason !== 'ยังไม่ได้กำหนดลำดับ IDP');
    const hasAssignedHeadOrSupervisor = reviewerStepsForUser(user).length > 0;

    if (!hasAssignedHeadOrSupervisor) {
        reasons.push('ยังไม่ได้กำหนดหัวหน้าหน่วยหรือหัวหน้างาน');
    }

    if (user.structureStatus === 'invalid' && rawStructureIssues.length === 0) {
        reasons.push('ข้อมูลโครงสร้างยังต้องตรวจสอบ');
    }

    return Array.from(new Set(reasons.filter(Boolean)));
});
const isSelfAssessmentBlocked = computed(() => selfAssessmentBlockReasons.value.length > 0);

const personNames = (user) => [
    user?.n,
    `${user?.t || ''}${user?.n || ''}`,
].map((name) => String(name || '').trim()).filter(Boolean);
const isSamePersonName = (storedName, user) => personNames(user).includes(String(storedName || '').trim());
const reviewerStepsForUser = (user) => {
    const dynamicSteps = Array.isArray(user?.reviewerSteps) && user.reviewerSteps.length
        ? user.reviewerSteps
        : (Array.isArray(user?.supervisorChain) ? user.supervisorChain : []);

    return dynamicSteps
        .map((step, index) => ({
            step: Number(step.step || index + 1),
            reviewer_id: Number(step.id || step.reviewer_id || step.value || 0),
            name: step.name || step.label || '',
            position: step.position || '',
            role: step.role || '',
        }))
        .filter((step) => step.step > 0 && step.reviewer_id > 0)
        .sort((a, b) => a.step - b.step);
};
const reviewerStepForUser = (user, reviewer = currentUser.value) => {
    const reviewerId = Number(reviewer?.db_id);

    if (!reviewerId) return null;

    return reviewerStepsForUser(user)
        .find((step) => step.reviewer_id === reviewerId)
        ?.step || null;
};
const pendingStatusForReviewerStep = (step) => ({
    1: 'self_submitted',
    2: 'unit_evaluated',
    3: 'dept_evaluated',
}[step] || (step ? `review_step_${step}` : null));
const nextStatusAfterReviewerStep = (user, step) => {
    const nextStep = reviewerStepsForUser(user).find((item) => item.step > step);

    if (nextStep) return pendingStatusForReviewerStep(nextStep.step);

    return 'approved';
};
const isAssignedReviewer = (user, reviewer) => {
    return (
        Boolean(reviewerStepForUser(user, reviewer))
        || isSamePersonName(user.sup, reviewer)
        || isSamePersonName(user.evaluator2, reviewer)
        || isSamePersonName(user.evaluator3, reviewer)
    );
};
const teamMembers = computed(() => {
    return users.value.filter((user) =>
        user.sso !== currentUser.value.sso
        && isAssignedReviewer(user, currentUser.value)
        && user.act !== false,
    );
});
const approvalOrganizationFor = (person) => {
    if (person?.displayOrganization) return person.displayOrganization;
    if (person?.approvalOrg) return person.approvalOrg;

    const department = String(person?.d || '');
    const isSupport = ['สายสนับสนุน', 'สายงานสนับสนุน'].includes(person?.w);
    if (!isSupport) return department || '-';

    const parts = department.split(' > ').map((part) => part.trim()).filter(Boolean);
    return parts.at(-1) || department || '-';
};
// const teamMembers = computed(() => {
//     return users.value.filter((user) =>
//         user.sso !== currentUser.value.sso
//         && (user.sup === currentUser.value.n || user.evaluator2 === currentUser.value.n)
//         && !['manager_dept', 'dept_head', 'manager'].includes(user.r)
//         && user.act !== false,
//     );
// });

const normalizeIdpPhase = (user) => user.idpPhase || user.idp_status || user.idpStatus || 'notsent';
const normalizeGaps = (user) => {
    const gaps = user.gaps || user.failedCompetencies || user.competencyGaps || [];
    if (!Array.isArray(gaps)) return [];

    return gaps
        .map((gap) => typeof gap === 'string' ? gap : (gap.n || gap.name || gap.title || gap.competency_name || ''))
        .filter(Boolean);
};

const idpRows = computed(() => teamMembers.value.map((user) => ({
    ...user,
    phase: idpPhaseOverrides.value[user.sso] || normalizeIdpPhase(user),
})));

const assessCounts = computed(() => ({
    total: teamMembers.value.length,
    notSent: teamMembers.value.filter((user) => user.evalStatus === 'draft' || !user.evalStatus).length,
    pending: teamMembers.value.filter((user) => user.evalStatus === pendingStatusForReviewerStep(reviewerStepForUser(user))).length,
    forwarded: teamMembers.value.filter((user) => ['unit_evaluated', 'dept_evaluated'].includes(user.evalStatus)).length,
    done: teamMembers.value.filter((user) => user.evalStatus === 'approved').length,
}));
const normalizeAssessmentStatus = (status) => status === 'dean_approved' ? 'approved' : (status || 'draft');
const reviewerStepCountForUser = (user) => reviewerStepsForUser(user).length;
const reviewedStatusesForStep = (step) => {
    const statuses = ['revision_required', 'approved'];

    if (step <= 1) statuses.push('unit_evaluated', 'dept_evaluated');
    if (step === 2) statuses.push('dept_evaluated');

    for (let nextStep = Math.max(4, step + 1); nextStep <= 12; nextStep += 1) {
        statuses.push(`review_step_${nextStep}`);
    }

    return statuses;
};
const isReviewedByStep = (status, step) => reviewedStatusesForStep(step).includes(normalizeAssessmentStatus(status));
const supervisorApprovalStatus = (person, results = []) => {
    const rows = Array.isArray(results) ? results : [];
    const workflows = rows.map((row) => row.workflowStatus || competencyWorkflowStatus(person, row));

    if (workflows.length === 0 || workflows.every((workflow) => workflow.key === 'self_pending')) {
        return { label: 'ยังไม่ประเมินตนเอง', cls: 'bgr' };
    }
    if (workflows.every((workflow) => workflow.key === 'approved')) {
        return { label: 'เสร็จสิ้น', cls: 'bg' };
    }
    if (workflows.some((workflow) => workflow.isCurrentReviewer)) {
        return { label: 'รอคุณประเมิน', cls: 'by' };
    }
    if (workflows.some((workflow) => workflow.key === 'revision_required')) {
        return { label: 'ถูกส่งกลับแก้ไข', cls: 'br' };
    }

    const hasNotSelfAssessed = workflows.some((workflow) => workflow.key === 'self_pending');
    const hasStarted = workflows.some((workflow) => workflow.key !== 'self_pending');
    if (hasNotSelfAssessed && hasStarted) {
        return { key: 'in_progress', label: 'อยู่ระหว่างดำเนินการ', cls: 'bb' };
    }

    return { label: 'รอท่านอื่นประเมิน', cls: 'bb' };
};
const workflowStatusClass = (key) => ({
    self_pending: 'bgr',
    pending_review: 'by',
    revision_required: 'br',
    approved: 'bg',
}[key] || 'bgr');
const fallbackWorkflowFor = (person, row) => {
    const status = normalizeAssessmentStatus(row?.status);
    const steps = reviewerStepsForUser(person);
    const current = steps.find((step) => pendingStatusForReviewerStep(step.step) === status);
    const rejectStep = steps.find((step) => step.reviewer_id === Number(row?.rejectReviewerId));
    let key = 'pending_review';
    let label = 'อยู่ระหว่างการประเมิน';

    if (!row?.hasAssessment || status === 'draft') {
        key = 'self_pending';
        label = 'ยังไม่ประเมินตนเอง';
    } else if (status === 'revision_required') {
        key = 'revision_required';
        label = row?.rejectReviewerName ? `ถูกส่งกลับโดย ${row.rejectReviewerName}` : 'ถูกส่งกลับแก้ไข';
    } else if (status === 'approved') {
        key = 'approved';
        label = 'ผ่านครบทุกลำดับ';
    } else if (current) {
        label = `รอการประเมินลำดับที่ ${current.step}${current.name ? ` · ${current.name}` : ''}`;
    }

    return {
        key,
        label,
        currentStep: current?.step || null,
        currentReviewerId: current?.reviewer_id || null,
        currentReviewerName: current?.name || null,
        totalSteps: steps.length,
        timeline: [
            {
                kind: 'self',
                step: 0,
                label: 'ประเมินตนเอง',
                name: `${person?.t || ''}${person?.n || ''}`,
                position: person?.p || '',
                state: ['draft', 'revision_required'].includes(status) ? 'active' : 'complete',
            },
            ...steps.map((step) => ({
                kind: 'reviewer',
                step: step.step,
                label: `ผู้ประเมินลำดับที่ ${step.step}`,
                name: step.name,
                position: step.position,
                reviewerId: step.reviewer_id,
                state: status === 'approved'
                    ? 'complete'
                    : (current
                        ? (step.step < current.step ? 'complete' : (step.step === current.step ? 'active' : 'waiting'))
                        : (status === 'revision_required' && rejectStep
                            ? (step.step < rejectStep.step ? 'complete' : (step.step === rejectStep.step ? 'returned' : 'waiting'))
                            : 'waiting')),
            })),
        ],
    };
};
const competencyWorkflowStatus = (person, row) => {
    const workflow = row?.workflow?.key ? row.workflow : fallbackWorkflowFor(person, row);
    const isCurrentReviewer = workflow.key === 'pending_review'
        && Number(workflow.currentReviewerId) === Number(currentUser.value?.db_id);

    return {
        ...workflow,
        label: isCurrentReviewer ? 'รอคุณประเมิน' : workflow.label,
        cls: workflowStatusClass(workflow.key),
        isCurrentReviewer,
    };
};
const competencyApprovalStatus = (row) => {
    if (row?.workflowStatus) return row.workflowStatus;
    if (!row?.hasAssessment || row.status === 'draft') return { label: 'ยังไม่ประเมินตนเอง', cls: 'bgr' };
    if (row.status === 'revision_required') {
        return { label: 'ส่งกลับแก้ไข', cls: 'br' };
    }
    if (row.status === pendingStatusForReviewerStep(row.reviewStep)) {
        return { label: 'รอคุณประเมิน', cls: 'by' };
    }
    if (isReviewedByStep(row.status, row.reviewStep)) {
        return { label: 'ผ่าน', cls: 'bg' };
    }

    return { label: 'ยังไม่ประเมินตนเอง', cls: 'bgr' };
};
const workflowNodeStateLabel = (state) => ({
    complete: 'ผ่านแล้ว',
    active: 'กำลังดำเนินการ',
    returned: 'ส่งกลับแก้ไข',
    waiting: 'รอ',
}[state] || 'รอ');
const supervisorApprovalRows = computed(() => assessmentReviewRoster(assessmentApprovalModule.value.items || [], users.value).map((person) => {
    const reviewStep = Number(person.assessmentAssignment.reviewStep) || reviewerStepForUser(person);
    const approvalTotalSteps = Number(person.assessmentAssignment.totalSteps) || reviewerStepCountForUser(person);
    const results = gapResultRows(person).map((row) => {
        const workflowStatus = competencyWorkflowStatus(person, row);

        return {
            ...row,
            reviewStep,
            approvalTotalSteps,
            workflowStatus,
        };
    });
    const hasSubmittedAssessment = results.some((row) => row.hasAssessment) || (person.evalStatus && person.evalStatus !== 'draft');
    const hasReviewableCompetencies = results.length > 0;
    const approvalExpectedStatus = pendingStatusForReviewerStep(reviewStep);
    const approvalNextStatus = person.assessmentAssignment.nextStatus || (reviewStep ? nextStatusAfterReviewerStep(person, reviewStep) : null);

    return {
        ...person,
        results,
        reviewStep,
        approvalExpectedStatus,
        approvalNextStatus,
        approvalTotalSteps,
        hasSubmittedAssessment,
        hasReviewableCompetencies,
        statusMeta: supervisorApprovalStatus(person, results),
        submittedAt: results.find((row) => row.hasAssessment)?.updatedAt || person.updatedAt || '-',
    };
}));
const approvalRoleLabel = computed(() => ({
    supervisor: 'หัวหน้าหน่วย',
    dept_head: 'หัวหน้างาน',
    division_head: 'หัวหน้าฝ่าย',
    academic_department_head: 'หัวหน้าภาควิชา',
}[authRoleKey.value] || 'ผู้ประเมิน'));
const supervisorPendingRows = computed(() => supervisorApprovalRows.value.filter((person) =>
    isPendingAssessmentReview(person),
));
const supervisorTrackingRows = computed(() => supervisorApprovalRows.value.filter((person) =>
    !isPendingAssessmentReview(person),
));
const supervisorInProgressRows = computed(() => supervisorApprovalRows.value.filter(isInProgressAssessmentReview));
const supervisorApprovedRows = computed(() => supervisorApprovalRows.value.filter((person) =>
    person.assessmentAssignment.isApproved
    || (person.results.length > 0 && person.results.every((row) => row.workflowStatus.key === 'approved')),
));
const fcTopicApprovalRows = computed(() => teamMembers.value
    .map((person) => ({
        ...person,
        fcTopicSelection: person.fcTopicSelection || {},
    }))
    .filter((person) =>
        Number(person.fcTopicSelection?.submittedTo) === Number(page.props.auth?.user?.id)
        && person.fcTopicSelection?.status === 'submitted',
    ));
const selectedFcTopicApproval = ref(null);
const fcTopicDecision = ref(null);
const fcTopicComment = ref('');
const isSubmittingFcTopicDecision = ref(false);
const fcTopicNameList = (selection) => {
    const selectedIds = new Set((selection?.selectedCompetencyIds || []).map((id) => Number(id)));

    return (selection?.availableCompetencies || [])
        .filter((item) => selectedIds.has(Number(item.id)))
        .map((item) => `${item.cd} · ${item.n}`);
};
const openFcTopicDecision = (person, decision) => {
    selectedFcTopicApproval.value = person;
    fcTopicDecision.value = decision;
    fcTopicComment.value = '';
};
const closeFcTopicDecision = () => {
    if (isSubmittingFcTopicDecision.value) return;
    selectedFcTopicApproval.value = null;
    fcTopicDecision.value = null;
    fcTopicComment.value = '';
};
const submitFcTopicDecision = () => {
    const selectionId = selectedFcTopicApproval.value?.fcTopicSelection?.id;
    if (!selectionId || !fcTopicDecision.value) return;
    if (fcTopicDecision.value === 'reject' && !fcTopicComment.value.trim()) return;

    isSubmittingFcTopicDecision.value = true;
    router.post(route(fcTopicDecision.value === 'approve' ? 'fc-topic-selections.approve' : 'fc-topic-selections.reject'), {
        selection_id: selectionId,
        comment: fcTopicComment.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            users.value = users.value.map((user) => {
                if (user.sso !== selectedFcTopicApproval.value?.sso) return user;

                return {
                    ...user,
                    fcTopicSelection: {
                        ...(user.fcTopicSelection || {}),
                        status: fcTopicDecision.value === 'approve' ? 'approved' : 'revision_required',
                        reviewComment: fcTopicComment.value.trim(),
                    },
                };
            });
            closeFcTopicDecision();
        },
        onFinish: () => {
            isSubmittingFcTopicDecision.value = false;
        },
    });
};

const gapRows = computed(() => teamMembers.value.map((user) => ({
    ...user,
    pending: user.evalStatus === 'draft' || !user.evalStatus,
    gaps: normalizeGaps(user),
})));
const assessedGapRows = computed(() => gapRows.value.filter((user) => !user.pending));
const foundGapRows = computed(() => assessedGapRows.value.filter((user) => user.gaps.length));
const gapCompetencies = computed(() => Object.values(
    foundGapRows.value
        .flatMap((user) => user.gaps)
        .reduce((items, name) => {
            const comp = assessmentComps.find((item) => item.title === name);
            items[name] = items[name] || { type: comp?.group || '-', name, count: 0 };
            items[name].count += 1;
            return items;
        }, {}),
));

const activeIdpTab = ref('notsent');
const idpStatusMeta = {
    notsent: { label: 'ยังไม่ส่งแผน', cls: 'bgr', title: 'ยังไม่ส่งแผน IDP' },
    pending: { label: 'รอการตรวจ/ยืนยัน', cls: 'by', title: 'แผน IDP รอการตรวจ/ยืนยัน' },
    rejected: { label: 'แผนไม่ผ่าน', cls: 'br', title: 'แผน IDP ไม่ผ่าน' },
    inprogress: { label: 'ระหว่างดำเนินการ', cls: 'bb', title: 'แผน IDP ระหว่างดำเนินการ' },
    done: { label: 'เสร็จสิ้น', cls: 'bg', title: 'แผน IDP เสร็จสิ้น' },
};
const idpTabs = computed(() => [
    { id: 'notsent', label: idpStatusMeta.notsent.label, count: idpRows.value.filter((row) => row.phase === 'notsent').length, cls: '' },
    { id: 'pending', label: idpStatusMeta.pending.label, count: idpRows.value.filter((row) => row.phase === 'pending').length, cls: 'yc' },
    { id: 'rejected', label: idpStatusMeta.rejected.label, count: idpRows.value.filter((row) => row.phase === 'rejected').length, cls: 'rc' },
    { id: 'inprogress', label: idpStatusMeta.inprogress.label, count: idpRows.value.filter((row) => row.phase === 'inprogress').length, cls: 'bc' },
    { id: 'done', label: idpStatusMeta.done.label, count: idpRows.value.filter((row) => row.phase === 'done').length, cls: 'gcc' },
]);
const visibleIdpRows = computed(() => idpRows.value.filter((row) => row.phase === activeIdpTab.value));

const scoreLabels = ['ต่ำมาก', 'ต่ำ', 'พอใช้', 'ดี', 'ดีมาก'];
const selectedGroup = ref('all');

const compGroups = computed(() => {
    const groups = [...new Set(assessmentComps.map((c) => c.group))];
    return [{ value: 'all', label: 'ทุกประเภท' }, ...groups.map((g) => ({ value: g, label: g }))];
});

const filteredComps = computed(() =>
    selectedGroup.value === 'all'
        ? assessmentComps
        : assessmentComps.filter((c) => c.group === selectedGroup.value),
);
const assessmentDrafts = ref({});
const assessmentSavedAt = ref('');
const draftTimer = ref(null);
const selectedGapEmployee = ref(null);
const selectedIdpEmployee = ref(null);
const selectedSupervisorApprovalSso = ref(null);
const openedSupervisorCompetencyId = ref(null);
const approvalDecision = ref(null);
const approvalDecisionCompetency = ref(null);
const approvalComment = ref('');
const reviewerComments = ref({});
const isSubmittingApprovalDecision = ref(false);
const allowIdpReview = false;
const idpPhaseOverrides = ref({});
const idpReviewDecisions = ref({});
const idpReviewFeedbacks = ref({});

const assessmentComps = [
    {
        id: 'service',
        group: 'CC',
        title: 'การบริการที่ดี',
        selfScore: 2,
        evidenceUrl: 'https://kku.ac.th/service-mind-2568',
        evidenceDesc: 'อบรมหลักสูตรการบริการเชิงรุกและแนบสรุปการนำไปใช้ในงานประจำ',
        behaviors: {
            1: ['รับเรื่องจากผู้รับบริการได้เมื่อมีคำแนะนำ', 'ตอบคำถามพื้นฐานตามข้อมูลที่มี', 'รักษามารยาทในการให้บริการ'],
            2: ['ตอบสนองความต้องการผู้รับบริการได้ทันท่วงที', 'ให้ข้อมูลที่ถูกต้องและครบถ้วนแก่ผู้รับบริการ', 'แสดงความเป็นมิตร ยิ้มแย้ม ให้บริการด้วยใจ'],
            3: ['ติดตามเรื่องจนผู้รับบริการได้รับคำตอบ', 'ปรับวิธีสื่อสารให้เหมาะกับผู้รับบริการ', 'ประสานงานข้ามหน่วยเพื่อแก้ปัญหาได้'],
            4: ['คาดการณ์ปัญหาการบริการล่วงหน้า', 'ออกแบบวิธีบริการที่ลดความผิดพลาดซ้ำ', 'เป็นที่ปรึกษาให้ทีมในกรณีบริการซับซ้อน'],
            5: ['ยกระดับมาตรฐานการบริการของหน่วยงาน', 'ใช้เสียงสะท้อนผู้รับบริการพัฒนาระบบงาน', 'สร้างวัฒนธรรมบริการที่ดีให้ทีมอย่างต่อเนื่อง'],
        },
    },
    {
        id: 'data',
        group: 'FC',
        title: 'การวิเคราะห์ข้อมูล',
        selfScore: 3,
        evidenceUrl: 'https://kku.ac.th/data-dashboard-2568',
        evidenceDesc: 'ตัวอย่าง dashboard สรุปข้อมูลการให้บริการรายเดือน',
        behaviors: {
            1: ['รวบรวมข้อมูลจากแหล่งที่กำหนดได้', 'ตรวจสอบข้อมูลเบื้องต้นตามแบบฟอร์ม', 'สรุปข้อเท็จจริงง่าย ๆ จากข้อมูลที่ได้รับ'],
            2: ['จัดหมวดหมู่ข้อมูลและตรวจความครบถ้วนได้', 'เปรียบเทียบข้อมูลพื้นฐานเพื่อหาความต่าง', 'จัดทำตารางหรือกราฟพื้นฐานประกอบรายงาน'],
            3: ['เลือกวิธีวิเคราะห์ให้เหมาะกับโจทย์งาน', 'อธิบายแนวโน้มและประเด็นสำคัญจากข้อมูล', 'เชื่อมผลวิเคราะห์กับข้อเสนอแนะในการทำงาน'],
            4: ['วิเคราะห์ข้อมูลหลายมิติและระบุปัจจัยที่เกี่ยวข้อง', 'ตรวจสอบความน่าเชื่อถือของผลวิเคราะห์', 'นำเสนอ insight ให้ผู้เกี่ยวข้องตัดสินใจได้'],
            5: ['ออกแบบกรอบวิเคราะห์ข้อมูลให้หน่วยงานใช้ร่วมกัน', 'คาดการณ์ผลกระทบจากข้อมูลเชิงลึก', 'พัฒนาระบบติดตามข้อมูลเพื่อยกระดับการตัดสินใจ'],
        },
    },
];

const selectedAssessment = computed(() => selectedEmployee.value || teamMembers.value.find((user) => user.evalStatus === 'self_submitted') || teamMembers.value[0] || null);
const assessmentName = computed(() => selectedAssessment.value ? `${selectedAssessment.value.t || ''}${selectedAssessment.value.n}` : '');
const selectedSupervisorApproval = computed(() =>
    supervisorApprovalRows.value.find((person) => person.sso === selectedSupervisorApprovalSso.value) || null,
);
const supervisorApprovalName = computed(() => selectedSupervisorApproval.value ? `${selectedSupervisorApproval.value.t || ''}${selectedSupervisorApproval.value.n}` : '');
const supervisorApprovalExpectedLevel = computed(() => {
    const levels = [...new Set((selectedSupervisorApproval.value?.results || [])
        .map((row) => row.expected)
        .filter((level) => level !== null && level !== undefined && level !== ''))];

    return levels.length > 0 ? levels.join(', ') : '-';
});
const supervisorApprovalOrganization = computed(() => {
    const person = selectedSupervisorApproval.value;
    const organization = approvalOrganizationFor(person);
    const isSupport = ['สายสนับสนุน', 'สายงานสนับสนุน'].includes(person?.w);
    const isAcademic = ['สายวิชาการ', 'สายงานวิชาการ'].includes(person?.w);
    const label = isSupport ? 'หน่วย' : (isAcademic ? 'ภาควิชา' : 'หน่วยงาน');

    if (!organization || organization === '-') return `${label} -`;
    return organization.startsWith(label) ? organization : `${label} ${organization}`;
});
const selectedSupervisorCompetency = computed(() => selectedSupervisorApproval.value?.results
    ?.find((row) => supervisorCompetencyKey(row) === openedSupervisorCompetencyId.value) || null);
const selectedSupervisorLevels = computed(() => {
    const row = selectedSupervisorCompetency.value;
    if (!row) return [];

    return (row.levels || []).map((level, levelIndex) => {
        const indicators = Array.isArray(level.indicators) ? level.indicators : [];
        const levelNumber = Number(level.lvl ?? level.level ?? levelIndex + 1);
        const rows = indicators.map((indicator, indicatorIndex) => ({
            text: typeof indicator === 'string' ? indicator : (indicator?.description || indicator?.text || ''),
            checked: isIndicatorChecked(row, { ...level, id: level.id, lvl: levelNumber }, indicatorIndex),
            index: indicatorIndex,
        }));

        return {
            id: level.id || levelNumber,
            number: levelNumber,
            rows,
            checkedCount: rows.filter((indicator) => indicator.checked).length,
        };
    });
});
const canDecideSupervisorCompetency = (row) =>
    Boolean(row?.hasAssessment)
    && row?.status === selectedSupervisorApproval.value?.approvalExpectedStatus;
const aggregateAssessmentStatus = (rows, fallback = 'draft') => {
    const statuses = (rows || []).map((row) => row.status).filter(Boolean);

    for (const status of ['revision_required', 'self_submitted', 'unit_evaluated', 'dept_evaluated', 'approved']) {
        if (statuses.includes(status)) return status;
    }

    return fallback || 'draft';
};
const canDecideSupervisorApproval = computed(() =>
    Boolean(approvalDecisionCompetency.value)
    && canDecideSupervisorCompetency(approvalDecisionCompetency.value),
);
const approvalCommentKey = (row) => `${selectedSupervisorApproval.value?.db_id || 'none'}:${competencyIdentity(row) || 'none'}`;
const approvalDecisionTitle = computed(() => approvalDecision.value === 'approve'
    ? `อนุมัติ ${approvalDecisionCompetency.value?.code || ''} ของ ${supervisorApprovalName.value}`
    : `ส่งกลับ ${approvalDecisionCompetency.value?.code || ''} ให้ประเมินใหม่`);
const approvalDecisionMessage = computed(() => approvalDecision.value === 'approve'
    ? 'สมรรถนะนี้จะถูกส่งต่อไปยังผู้ประเมินลำดับถัดไป หากไม่มีลำดับถัดไปจะปิดเป็นอนุมัติแล้ว'
    : `สมรรถนะนี้จะถูกส่งกลับให้ ${supervisorApprovalName.value} ประเมินใหม่ โดยไม่กระทบสมรรถนะข้ออื่น`);
const assessmentDraftKey = computed(() => selectedAssessment.value ? `${currentUser.value.sso || currentUser.value.n}:${selectedAssessment.value.sso || selectedAssessment.value.n}` : '');
const activeDraft = computed(() => assessmentDrafts.value[assessmentDraftKey.value] || { scores: {}, feedback: {}, submitted: false });

const getHeadScore = (comp) => activeDraft.value.scores?.[comp.id] || comp.selfScore;
const getHeadFeedback = (comp) => activeDraft.value.feedback?.[comp.id] || '';

const gapScoreFor = (person, comp, key, fallback) => {
    const values = person?.competencyScores || person?.scores || person?.assessmentScores || {};
    return Number(values?.[comp.id]?.[key] ?? values?.[`${comp.id}_${key}`] ?? comp[key] ?? fallback);
};
const competencyIdentity = (row) => String(row?.competencyId || row?.competency_id || row?.id || row?.code || row?.title || '');

const gapResultRows = (person) => {
    if (!person) return [];
    const assessmentStatusFromAssigned = (row) => normalizeAssessmentStatus(row.assessmentStatus || row.status);
    const assignedRows = Array.isArray(person.assignedCompetencies) ? person.assignedCompetencies.map((row) => ({
        id: row.id || row.competency_id || row.name || row.title,
        competencyId: row.competencyId || row.competency_id || row.id || null,
        title: row.title || row.name || row.n || row.competency_name || '-',
        group: row.group || row.type || row.t || '-',
        code: row.code || row.cd || '',
        detail: row.detail || row.det || '',
        expected: Number(row.expected ?? row.expectedScore ?? row.expectedLevel ?? row.target ?? 3),
        selfScore: null,
        headScore: null,
        gap: null,
        feedback: '',
        note: '',
        reviewerComment: row.reviewerComment || row.rejectComment || '',
        rejectReviewerId: row.rejectReviewerId || null,
        rejectReviewerName: row.rejectReviewerName || '',
        workflow: row.workflow || null,
        levels: row.levels || [],
        checkedIndicatorKeys: [],
        checkedIndicatorCount: 0,
        lastDraftSavedAt: row.lastDraftSavedAt || null,
        updatedAt: '',
        failed: false,
        status: assessmentStatusFromAssigned(row),
        hasAssessment: assessmentStatusFromAssigned(row) !== 'draft',
    })) : [];
    const rawRows = person.competencyGaps || person.competencyResults || person.assessmentResults || person.evaluationResults;
    if (Array.isArray(rawRows) && rawRows.length) {
        const assessedRows = rawRows.map((row) => {
            const expected = Number(row.expected ?? row.expectedScore ?? row.target ?? 3);
            const selfScore = Number(row.selfScore ?? row.self ?? row.actual ?? row.actualLevel ?? 0);
            const headScore = Number(row.headScore ?? row.supervisorScore ?? row.evaluatorScore ?? row.score ?? row.actual ?? row.actualLevel ?? selfScore);
            const gap = Number(row.gap ?? headScore - expected);
            return {
                id: row.id || row.competency_id || row.name || row.title,
                competencyId: row.competencyId || row.competency_id || null,
                title: row.title || row.name || row.n || row.competency_name || '-',
                group: row.group || row.type || row.t || '-',
                code: row.code || row.cd || '',
                detail: row.detail || row.det || '',
                expected,
                selfScore,
                headScore,
                gap,
                feedback: row.feedback || '',
                note: row.note || '',
                reviewerComment: row.reviewerComment || row.evaluatorComment || row.rejectComment || '',
                rejectReviewerId: row.rejectReviewerId || null,
                rejectReviewerName: row.rejectReviewerName || '',
                workflow: row.workflow || null,
                levels: row.levels || [],
                checkedIndicatorKeys: row.checkedIndicatorKeys || [],
                checkedIndicatorCount: Number(row.checkedIndicatorCount || 0),
                lastDraftSavedAt: row.lastDraftSavedAt || row.last_draft_saved_at || null,
                updatedAt: row.updatedAt || row.updated_at || '',
                failed: row.failed ?? row.requiresIdp ?? gap < 0,
                status: normalizeAssessmentStatus(row.status),
                hasAssessment: true,
            };
        });

        const assessedKeys = new Set(assessedRows.map((row) => competencyIdentity(row)).filter(Boolean));
        const missingAssignedRows = assignedRows.filter((row) => !assessedKeys.has(competencyIdentity(row)));

        return [...assessedRows, ...missingAssignedRows];
    }

    if (assignedRows.length) return assignedRows;

    const explicitGaps = normalizeGaps(person);
    return assessmentComps.map((comp, index) => {
        const expected = gapScoreFor(person, comp, 'expected', 3);
        const selfScore = gapScoreFor(person, comp, 'selfScore', index === 1 ? 1 : comp.selfScore);
        const headScore = gapScoreFor(person, comp, 'headScore', index === 1 ? 2 : comp.selfScore);
        const isExplicitGap = explicitGaps.includes(comp.title);
        const failed = isExplicitGap || headScore < expected;
        return {
            id: comp.id,
            title: comp.title,
            group: comp.group,
            code: comp.code || '',
            expected,
            selfScore,
            headScore,
            gap: headScore - expected,
            failed,
            status: normalizeAssessmentStatus(person.evalStatus),
        };
    }).filter((row) => row.failed);
};

const selectedGapPerson = computed(() => selectedGapEmployee.value);
const selectedGapRows = computed(() => gapResultRows(selectedGapPerson.value));
const isCompetencyApproved = (row) => row?.hasAssessment !== false
    && normalizeAssessmentStatus(row.status) === 'approved';
const selectedGapApprovedRows = computed(() => selectedGapRows.value.filter(isCompetencyApproved));
const selectedGapFailedCount = computed(() => selectedGapApprovedRows.value.filter((row) => Number(row.gap) < 0).length);
const selectedGapPassedCount = computed(() => selectedGapApprovedRows.value.filter((row) => Number(row.gap) >= 0).length);
const selectedGapPendingCount = computed(() => selectedGapRows.value.length - selectedGapApprovedRows.value.length);
const teamGapSearch = ref('');
const teamGapFilter = ref('all');
const teamGapPage = ref(1);
const teamGapPageSize = 10;
const selectedGapSearch = ref('');
const selectedGapCompetencyFilter = ref('all');
const selectedGapPage = ref(1);
const selectedGapPageSize = 10;
const formatTeamGap = (value) => {
    if (value === null || value === undefined || value === '') return '-';
    const numberValue = Number(value);
    if (!Number.isFinite(numberValue)) return '-';
    if (numberValue === 0) return '0';

    const formatted = Math.abs(numberValue)
        .toFixed(2)
        .replace(/\.00$/, '')
        .replace(/0$/, '')
        .replace(/\.$/, '');

    return `${numberValue > 0 ? '+' : '-'}${formatted}`;
};
const teamHeatmapRows = computed(() => teamMembers.value.map((person) => {
    const resultRows = gapResultRows(person);
    const completed = resultRows.length > 0 && resultRows.every(isCompetencyApproved);
    const started = resultRows.some((row) => row.hasAssessment || row.lastDraftSavedAt)
        || normalizeAssessmentStatus(person.evalStatus) !== 'draft';
    const missingCount = completed ? resultRows.filter((row) => Number(row.gap) < 0).length : 0;
    const failedCompetencies = completed
        ? resultRows
            .filter((row) => Number(row.gap) < 0)
            .sort((left, right) => Number(left.gap) - Number(right.gap))
        : [];

    return {
        ...person,
        completed,
        started,
        assessed: completed,
        assessmentProgress: completed
            ? (missingCount
                ? { key: 'needs-development', label: `ต้องพัฒนา ${missingCount}` }
                : { key: 'passed-all', label: 'ผ่านทั้งหมด' })
            : (started
                ? { key: 'in-progress', label: 'กำลังดำเนินการ' }
                : { key: 'not-started', label: 'ยังไม่เริ่ม' }),
        missingCount,
        competencyCount: resultRows.length,
        failedCompetencies,
        summary: completed
            ? (missingCount ? `ไม่ผ่าน ${missingCount} สมรรถนะ` : 'ผ่านทุกสมรรถนะ')
            : (started ? 'กำลังดำเนินการ' : 'ยังไม่เริ่มประเมิน'),
    };
}));
const teamAssessedRows = computed(() => teamHeatmapRows.value.filter((row) => row.completed));
const teamFailedRows = computed(() => teamAssessedRows.value.filter((row) => row.missingCount > 0));
const teamTalentRows = computed(() => teamAssessedRows.value.filter((row) => row.missingCount === 0));
const teamPendingRows = computed(() => teamHeatmapRows.value.filter((row) => !row.completed));
const teamGapFilterOptions = computed(() => [
    { key: 'all', label: 'ทั้งหมด', count: teamHeatmapRows.value.length },
    { key: 'needs-development', label: 'ต้องพัฒนา', count: teamFailedRows.value.length },
    { key: 'passed', label: 'ผ่านครบ', count: teamTalentRows.value.length },
    { key: 'pending', label: 'ยังไม่เสร็จ', count: teamPendingRows.value.length },
]);
const filteredTeamGapRows = computed(() => {
    const keyword = teamGapSearch.value.trim().toLocaleLowerCase('th');

    return teamHeatmapRows.value.filter((row) => {
        const matchesKeyword = !keyword || [row.t, row.n, row.p, row.d]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase('th')
            .includes(keyword);
        const matchesStatus = teamGapFilter.value === 'all'
            || (teamGapFilter.value === 'needs-development' && row.completed && row.missingCount > 0)
            || (teamGapFilter.value === 'passed' && row.completed && row.missingCount === 0)
            || (teamGapFilter.value === 'pending' && !row.completed);

        return matchesKeyword && matchesStatus;
    }).sort((left, right) => {
        const priority = (row) => {
            if (row.completed && row.missingCount > 0) return 0;
            if (!row.completed && row.started) return 1;
            if (!row.started) return 2;
            return 3;
        };

        return priority(left) - priority(right)
            || String(left.n || '').localeCompare(String(right.n || ''), 'th');
    });
});
const teamGapPageCount = computed(() => Math.max(1, Math.ceil(filteredTeamGapRows.value.length / teamGapPageSize)));
const paginatedTeamGapRows = computed(() => {
    const safePage = Math.min(teamGapPage.value, teamGapPageCount.value);
    const start = (safePage - 1) * teamGapPageSize;
    return filteredTeamGapRows.value.slice(start, start + teamGapPageSize);
});
const updateTeamGapSearch = (value) => {
    teamGapSearch.value = value;
    teamGapPage.value = 1;
};
const updateTeamGapFilter = (value) => {
    teamGapFilter.value = value;
    teamGapPage.value = 1;
};
const competencyResultMeta = (row) => {
    const status = normalizeAssessmentStatus(row?.status);
    if (isCompetencyApproved(row)) {
        return Number(row.gap) < 0
            ? { key: 'failed', label: 'ต้องพัฒนา' }
            : { key: 'passed', label: 'ผ่าน' };
    }
    if (status === 'revision_required') return { key: 'revision', label: 'ส่งกลับแก้ไข' };
    if (status === 'draft') return { key: 'not-started', label: row?.lastDraftSavedAt ? 'บันทึกร่างแล้ว' : 'ยังไม่เริ่ม' };
    return { key: 'pending', label: 'อยู่ระหว่างตรวจ' };
};
const filteredSelectedGapRows = computed(() => {
    const keyword = selectedGapSearch.value.trim().toLocaleLowerCase('th');
    return selectedGapRows.value.filter((row) => {
        const matchesKeyword = !keyword || [row.code, row.title, row.group]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase('th')
            .includes(keyword);
        const approved = isCompetencyApproved(row);
        const matchesFilter = selectedGapCompetencyFilter.value === 'all'
            || (selectedGapCompetencyFilter.value === 'gap' && approved && Number(row.gap) < 0)
            || (selectedGapCompetencyFilter.value === 'passed' && approved && Number(row.gap) >= 0)
            || (selectedGapCompetencyFilter.value === 'pending' && !approved);

        return matchesKeyword && matchesFilter;
    });
});
const selectedGapPageCount = computed(() => Math.max(1, Math.ceil(filteredSelectedGapRows.value.length / selectedGapPageSize)));
const paginatedSelectedGapRows = computed(() => {
    const safePage = Math.min(selectedGapPage.value, selectedGapPageCount.value);
    const start = (safePage - 1) * selectedGapPageSize;
    return filteredSelectedGapRows.value.slice(start, start + selectedGapPageSize);
});
const selectedGapSummary = computed(() => teamHeatmapRows.value.find((row) => row.sso === selectedGapPerson.value?.sso) || null);
watchEffect(() => {
    if (activePage.value !== 'sup-gap') return;

    const selectionIsVisible = filteredTeamGapRows.value.some((row) => row.sso === selectedGapEmployee.value?.sso);
    if (selectionIsVisible) return;

    selectedGapEmployee.value = filteredTeamGapRows.value.find((row) => row.completed) || filteredTeamGapRows.value[0] || null;
    selectedGapSearch.value = '';
    selectedGapCompetencyFilter.value = 'all';
    selectedGapPage.value = 1;
});
const idpOverviewRows = computed(() => idpRows.value.map((person, index) => {
    const gapCount = gapResultRows(person).filter((row) => row.failed).length || normalizeGaps(person).length;
    const phaseMeta = idpStatusFor(person.phase);
    return {
        ...person,
        missingCount: gapCount,
        statusLabel: person.phase === 'notsent' ? 'ร่าง' : phaseMeta.label,
        statusClass: person.phase === 'done' ? 'bg' : person.phase === 'rejected' ? 'br' : person.phase === 'inprogress' ? 'bb' : 'bgr',
        updatedAt: person.updatedAt || person.sentDate || `2026-06-${String(10 + index).padStart(2, '0')}`,
    };
}));
const openGapDetail = (person) => {
    selectedGapSearch.value = '';
    selectedGapCompetencyFilter.value = 'all';
    selectedGapPage.value = 1;
    selectedGapEmployee.value = person;
};

const selectedIdpPerson = computed(() =>
    idpRows.value.find((person) => person.sso === selectedIdpEmployee.value?.sso) || selectedIdpEmployee.value,
);
const selectedIdpGapRows = computed(() => gapResultRows(selectedIdpPerson.value));
const selectedIdpName = computed(() => selectedIdpPerson.value ? `${selectedIdpPerson.value.t || ''}${selectedIdpPerson.value.n}` : '');
const idpStatusFor = (phase) => idpStatusMeta[phase] || idpStatusMeta.notsent;
const selectedIdpStatus = computed(() => idpStatusFor(selectedIdpPerson.value?.phase));
const idpPlanFor = (row) => selectedIdpPerson.value?.idpPlans?.find((plan) => plan.id === row.id) || {};
const idpReviewKey = (row) => `${selectedIdpPerson.value?.sso || ''}:${row.id}`;
const idpDecisionFor = (row) => idpReviewDecisions.value[idpReviewKey(row)] || '';
const idpFeedbackFor = (row) => idpReviewFeedbacks.value[idpReviewKey(row)] || row.feedback || '';
const setIdpFeedback = (row, value) => {
    idpReviewFeedbacks.value = {
        ...idpReviewFeedbacks.value,
        [idpReviewKey(row)]: value,
    };
};
const setIdpDecision = (row, decision) => {
    if (decision === 'rejected' && !idpFeedbackFor(row).trim()) {
        alert('กรุณาบันทึกข้อเสนอแนะ IDP ก่อนระบุว่าแผนไม่ผ่าน');
        return;
    }
    idpReviewDecisions.value = {
        ...idpReviewDecisions.value,
        [idpReviewKey(row)]: decision,
    };
};
const completeIdpReview = () => {
    if (!selectedIdpPerson.value) return;
    const decisions = selectedIdpGapRows.value.map((row) => idpDecisionFor(row));
    if (decisions.length === 0 || decisions.some((decision) => !decision)) {
        alert('กรุณาตรวจทุกแผนก่อนดำเนินการต่อ');
        return;
    }
    const nextPhase = decisions.includes('rejected') ? 'rejected' : 'inprogress';
    idpPhaseOverrides.value = {
        ...idpPhaseOverrides.value,
        [selectedIdpPerson.value.sso]: nextPhase,
    };
    activeIdpTab.value = nextPhase;
};

const openIdpDetail = (person) => {
    selectedIdpEmployee.value = person;
};

const closeIdpDetail = () => {
    selectedIdpEmployee.value = null;
};

const persistAssessmentDraft = (nextDraft, label = 'บันทึกร่างอัตโนมัติแล้ว') => {
    if (!assessmentDraftKey.value) return;
    assessmentDrafts.value = {
        ...assessmentDrafts.value,
        [assessmentDraftKey.value]: nextDraft,
    };
    assessmentSavedAt.value = `${label} ${new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })}`;
};

const scheduleAssessmentAutosave = (nextDraft) => {
    assessmentSavedAt.value = 'กำลังบันทึกร่าง...';
    if (assessmentDraftKey.value) {
        assessmentDrafts.value = {
            ...assessmentDrafts.value,
            [assessmentDraftKey.value]: nextDraft,
        };
    }
    if (draftTimer.value) clearTimeout(draftTimer.value);
    draftTimer.value = setTimeout(() => persistAssessmentDraft(nextDraft), 500);
};

const updateAssessmentScore = (comp, score) => {
    scheduleAssessmentAutosave({
        ...activeDraft.value,
        scores: { ...(activeDraft.value.scores || {}), [comp.id]: score },
        feedback: { ...(activeDraft.value.feedback || {}) },
    });
};

const updateAssessmentFeedback = (comp, value) => {
    scheduleAssessmentAutosave({
        ...activeDraft.value,
        scores: { ...(activeDraft.value.scores || {}) },
        feedback: { ...(activeDraft.value.feedback || {}), [comp.id]: value },
    });
};

const selectAssessmentEmployee = (person) => {
    selectedEmployee.value = person;
    assessmentSavedAt.value = '';
};

const openSupervisorApprovalModal = (person) => {
    selectedSupervisorApprovalSso.value = person.sso;
    openedSupervisorCompetencyId.value = null;
};

const closeSupervisorApprovalModal = () => {
    selectedSupervisorApprovalSso.value = null;
    openedSupervisorCompetencyId.value = null;
    approvalDecision.value = null;
    approvalDecisionCompetency.value = null;
};

const supervisorCompetencyKey = (row) => `${competencyIdentity(row)}`;
const isSupervisorCompetencyOpen = (row) => openedSupervisorCompetencyId.value === supervisorCompetencyKey(row);
const toggleSupervisorCompetency = (row) => {
    openedSupervisorCompetencyId.value = supervisorCompetencyKey(row);
};
const closeSupervisorCompetencyModal = () => {
    if (isSubmittingApprovalDecision.value) return;
    openedSupervisorCompetencyId.value = null;
    approvalDecision.value = null;
    approvalDecisionCompetency.value = null;
};
const checkedIndicatorKey = (row, level, index) => `${competencyIdentity(row)}:${level.id || level.lvl}:${index}`;
const checkedIndicatorSet = (row) => new Set(row.checkedIndicatorKeys || []);
const isIndicatorChecked = (row, level, index) => checkedIndicatorSet(row).has(checkedIndicatorKey(row, level, index));
const totalIndicatorCount = (row) => (row.levels || [])
    .reduce((total, level) => total + (level.indicators?.length || 0), 0);
const checkedIndicatorsForLevel = (row, level) => (level.indicators || [])
    .map((indicator, index) => ({ indicator, index }))
    .filter(({ index }) => isIndicatorChecked(row, level, index));
const indicatorsForLevel = (row, level) => (level.indicators || [])
    .map((indicator, index) => ({
        indicator,
        index,
        checked: isIndicatorChecked(row, level, index),
    }));
const checkedLevelsForRow = (row) => (row.levels || [])
    .map((level) => ({
        ...level,
        checkedIndicators: checkedIndicatorsForLevel(row, level),
    }))
    .filter((level) => level.checkedIndicators.length > 0);
const approvalLevelsForRow = (row) => (row.levels || [])
    .map((level) => ({
        ...level,
        indicatorsWithStatus: indicatorsForLevel(row, level),
        checkedIndicators: checkedIndicatorsForLevel(row, level),
    }));
const levelSelectionState = (level) => {
    const total = level.indicators?.length || 0;
    const checked = level.checkedIndicators?.length || 0;
    if (!total || checked === 0) return 'empty';
    if (checked === total) return 'complete';
    return 'partial';
};
const levelSelectionLabel = (level) => ({
    complete: 'เลือกครบ',
    partial: 'เลือกบางส่วน',
    empty: 'ยังไม่เลือก',
}[levelSelectionState(level)] || 'ยังไม่เลือก');

const submitAssessmentToManager = () => {
    if (!selectedAssessment.value) return;
    persistAssessmentDraft({ ...activeDraft.value, submitted: true }, 'ส่งต่อหัวหน้างานแล้ว');
    users.value = users.value.map((user) => user.sso === selectedAssessment.value.sso ? { ...user, evalStatus: 'unit_evaluated' } : user);
};
const requestApprovalDecision = (decision, row) => {
    if (!selectedSupervisorApproval.value || !canDecideSupervisorCompetency(row)) return;
    approvalDecisionCompetency.value = row;
    approvalDecision.value = decision;
    approvalComment.value = reviewerComments.value[approvalCommentKey(row)] ?? '';
};
const closeApprovalDecision = () => {
    if (isSubmittingApprovalDecision.value) return;
    approvalDecision.value = null;
    approvalDecisionCompetency.value = null;
    approvalComment.value = '';
};
const submitApprovalDecision = () => {
    const competency = approvalDecisionCompetency.value;
    if (!selectedSupervisorApproval.value || !approvalDecision.value || !competency) return;

    const decision = approvalDecision.value;
    const comment = (reviewerComments.value[approvalCommentKey(competency)] || approvalComment.value || '').trim();
    if (decision === 'reject' && !comment) {
        return;
    }

    const nextStatus = decision === 'approve' ? selectedSupervisorApproval.value.approvalNextStatus : 'revision_required';
    isSubmittingApprovalDecision.value = true;
    router.post(route(decision === 'approve' ? 'assessments.approve' : 'assessments.reject'), {
        user_id: selectedSupervisorApproval.value.db_id,
        competency_id: competency.competencyId || competency.competency_id || competency.id,
        comment,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            users.value = users.value.map((user) =>
                user.sso === selectedSupervisorApproval.value.sso
                    ? (() => {
                        const competencyGaps = (user.competencyGaps || []).map((gap) =>
                            Number(competencyIdentity(gap)) === Number(competencyIdentity(competency))
                                ? { ...gap, status: nextStatus, reviewerComment: comment }
                                : gap,
                        );
                        reviewerComments.value[approvalCommentKey(competency)] = comment;

                        return {
                            ...user,
                            evalStatus: aggregateAssessmentStatus(competencyGaps, nextStatus),
                            competencyGaps,
                        };
                    })()
                    : user,
            );
            approvalDecision.value = null;
            approvalDecisionCompetency.value = null;
            approvalComment.value = '';
        },
        onFinish: () => {
            isSubmittingApprovalDecision.value = false;
        },
    });
};

watchEffect(() => {
    if (props.embeddedPage && implementedPages.has(props.embeddedPage)) {
        activePage.value = props.embeddedPage;
    }

    if (requestedPage.value && implementedPages.has(requestedPage.value)) {
        activePage.value = requestedPage.value;
        requestedPage.value = null;
    }

    if (!implementedPages.has(activePage.value)) {
        activePage.value = defaultPageForRole(authRoleKey.value);
    }

    if (activePage.value === 'dh-fc-topic-approval' && !fcTopicApprovalModule.value.enabled) {
        activePage.value = defaultPageForRole(authRoleKey.value);
    }
    if (activePage.value === 'dh-assess' && !fcTopicApprovalModule.value.enabled && !assessmentApprovalModule.value.enabled) {
        activePage.value = defaultPageForRole(authRoleKey.value);
    }
    if (activePage.value === 'dh-assess') {
        assessmentHubTab.value = availableAssessmentHubTab(assessmentHubTab.value, assessmentApprovalModule.value.enabled);
    }
    if (activePage.value === 'dh-idp' && !idpReviewModule.value.enabled) {
        activePage.value = defaultPageForRole(authRoleKey.value);
    }

    if (Array.isArray(page.props.reviewerTeamUsers)) {
        users.value = clone(page.props.reviewerTeamUsers);
    } else if (Array.isArray(page.props.users)) {
        users.value = clone(page.props.users);
    }
});

const requestPageChange = (nextPage) => {
    activePage.value = nextPage;
};

const goProfile = () => router.visit(route('profile.edit'));
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head v-if="!props.embedded" title="Head - EN-IDP" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar && !props.embedded, 'embedded-reviewer-shell': props.embedded }">
        <div v-if="showSidebar && !props.embedded" class="sidebar">
            <SidebarBrand />

            <button class="sb-user on" type="button" @click="goProfile">
                <div class="av" :style="{ background: currentRoleData.col }">
                    {{ currentUser.n?.[0] || currentRoleData.av }}
                </div>
                <div style="overflow: hidden; min-width: 0">
                    <div class="u-name">{{ `${currentUser.t || ''}${currentUser.n}` }}</div>
                    <div class="u-role">{{ currentUser.p || currentRoleData.pos }}</div>
                </div>
            </button>

            <div class="sb-nav">
                <div v-for="(section, sectionIndex) in navSections" :key="sectionIndex">
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
            <div v-if="!props.embedded" class="topbar">
                <button class="btn btn-s btn-sm menu-btn" type="button" @click="showSidebar = !showSidebar">☰</button>
                <div class="tb-title">{{ pageTitle }}</div>
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <div class="content" :class="{ 'embedded-reviewer-content': props.embedded }">
                <EmployeeAssess
                    v-if="activePage === 'emp-assess'"
                    :user="currentUser"
                    :set-users="setRef(users)"
                    :competencies="page.props.currentUserCompetencies || []"
                    :fc-topic-selection="page.props.currentUserFcTopicSelection || {}"
                    :blocked="isSelfAssessmentBlocked"
                    :block-reasons="selfAssessmentBlockReasons"
                />

                <EmployeeGap
                    v-else-if="activePage === 'emp-gap'"
                    :set-page="requestPageChange"
                    :competencies="page.props.currentUserCompetencies || []"
                    :gaps="page.props.currentUserCompetencyGaps || []"
                    :user="currentUser"
                />

                <EmployeeIDP
                    v-else-if="activePage === 'emp-idp'"
                    :learning-methods="page.props.learningMethods || learningMethods"
                    :idp-learning-methods="page.props.idpLearningMethods || []"
                    :learning-catalogs="page.props.hrCatalogItems || []"
                    :gaps="page.props.currentUserCompetencyGaps || []"
                    :idp="page.props.currentUserIdp || null"
                    :user="currentUser"
                />

                <EmployeeProgress
                    v-else-if="activePage === 'emp-progress'"
                    :activities="page.props.currentUserApprovedIdpActivities || []"
                />

                <EmployeeIDPDetail v-else-if="activePage === 'emp-idp-detail'" :activities="page.props.currentUserApprovedIdpActivities || []" />

                <template v-else-if="activePage === 'dh-idp' && idpReviewModule.enabled">
                    <div class="team-page-head idp-team-head">
                        <div>
                            <div class="sec-t">IDP ของทีม</div>
                            <div class="sec-s">ตรวจแผนและติดตามการพัฒนาของบุคลากรในสาย IDP</div>
                        </div>
                    </div>

                    <div class="idp-workspace">
                        <nav class="idp-mode-tabs" role="tablist" aria-label="งาน IDP ของทีม">
                            <button
                                id="idp-approval-tab"
                                type="button"
                                role="tab"
                                :aria-selected="idpTeamTab === 'approval'"
                                aria-controls="idp-approval-panel"
                                :class="{ active: idpTeamTab === 'approval' }"
                                @click="idpTeamTab = 'approval'"
                            >
                                <span class="idp-tab-copy">
                                    <strong>อนุมัติแผน</strong>
                                    <small>ตรวจแผนที่มาถึงลำดับของคุณ</small>
                                </span>
                                <span class="idp-tab-count">{{ idpApprovalCount }} คน</span>
                            </button>
                            <button
                                id="idp-completion-tab"
                                type="button"
                                role="tab"
                                :aria-selected="idpTeamTab === 'completion'"
                                aria-controls="idp-completion-panel"
                                :class="{ active: idpTeamTab === 'completion' }"
                                @click="idpTeamTab = 'completion'"
                            >
                                <span class="idp-tab-copy">
                                    <strong>ตรวจผลการพัฒนา</strong>
                                    <small>ตรวจผลและหลักฐานที่มาถึงลำดับของคุณ</small>
                                </span>
                                <span class="idp-tab-count" :class="{ pending: idpPendingProgressReviewCount > 0 }">{{ idpPendingProgressReviewCount }} คน</span>
                            </button>
                            <button
                                id="idp-tracking-tab"
                                type="button"
                                role="tab"
                                :aria-selected="idpTeamTab === 'tracking'"
                                aria-controls="idp-tracking-panel"
                                :class="{ active: idpTeamTab === 'tracking' }"
                                @click="idpTeamTab = 'tracking'"
                            >
                                <span class="idp-tab-copy">
                                    <strong>ติดตามสถานะ</strong>
                                    <small>ดูความก้าวหน้าและสถานะ IDP ของทีม</small>
                                </span>
                                <span class="idp-tab-meta">
                                    <span v-if="idpOverdueCount" class="idp-tab-overdue">{{ idpOverdueCount }} ล่าช้า</span>
                                    <span class="idp-tab-count">{{ idpTrackingCount }} คน</span>
                                </span>
                            </button>
                        </nav>

                        <div
                            v-if="idpTeamTab === 'approval'"
                            id="idp-approval-panel"
                            class="idp-tab-panel"
                            role="tabpanel"
                            aria-labelledby="idp-approval-tab"
                        >
                            <IdpItemApproval :items="props.idpReviewItems" />
                        </div>
                        <div
                            v-else-if="idpTeamTab === 'completion'"
                            id="idp-completion-panel"
                            class="idp-tab-panel"
                            role="tabpanel"
                            aria-labelledby="idp-completion-tab"
                        >
                            <IdpActivityProgressReview mode="review" :items="idpProgressItems" />
                        </div>
                        <div
                            v-else
                            id="idp-tracking-panel"
                            class="idp-tab-panel idp-tracking-dashboard-panel"
                            role="tabpanel"
                            aria-labelledby="idp-tracking-tab"
                        >
                            <FacultyOverview
                                :analytics="teamIdpAnalytics"
                                module="idp"
                                reload-prop="teamIdpAnalytics"
                                title="การติดตาม IDP ของทีม"
                                description="ติดตามการจัดทำแผน ความก้าวหน้า และบุคลากรในสาย IDP ของคุณ"
                                hide-heading-copy
                                hide-workline-breakdown
                            />
                        </div>
                    </div>
                </template>

                <template v-else-if="activePage === 'sup-gap'">
                    <header class="team-dashboard-head">
                        <div class="team-dashboard-copy">
                            <span>TEAM ASSESSMENT</span>
                            <h1>ผลการประเมินของทีม</h1>
                            <p>ติดตามความคืบหน้าและค้นหาบุคลากรที่ต้องพัฒนาในมุมมองเดียว</p>
                        </div>
                        <div class="team-round-context" aria-label="รอบประเมินที่กำลังแสดง">
                            <span aria-hidden="true"></span>
                            <div>
                                <small>รอบประเมิน</small>
                                <strong>{{ activeCycleName }}</strong>
                            </div>
                        </div>
                    </header>

                    <AssessmentSummaryBand
                        class="team-summary-band"
                        :total="teamHeatmapRows.length"
                        :assessed="teamAssessedRows.length"
                        :passed="teamTalentRows.length"
                        :with-gap="teamFailedRows.length"
                    />

                    <div v-if="teamHeatmapRows.length === 0" class="card team-dashboard-empty">
                        <span aria-hidden="true">—</span>
                        <strong>ยังไม่มีบุคลากรในสายประเมิน</strong>
                        <p>เมื่อมีการกำหนดสายประเมิน รายชื่อและสถานะของทีมจะแสดงในส่วนนี้</p>
                    </div>

                    <div v-else class="card team-gap-workspace">
                            <aside class="team-member-panel">
                                <div class="team-member-head">
                                    <div>
                                        <div class="ct">รายชื่อทีม</div>
                                    </div>
                                </div>
                                <div class="team-member-tools">
                                    <label class="team-gap-search">
                                        <span aria-hidden="true">⌕</span>
                                        <input
                                            :value="teamGapSearch"
                                            type="search"
                                            placeholder="ค้นหาชื่อหรือตำแหน่ง"
                                            aria-label="ค้นหาบุคลากร"
                                            @input="updateTeamGapSearch($event.target.value)"
                                        >
                                    </label>
                                    <div class="team-member-filter" aria-label="กรองสถานะบุคลากร">
                                        <button
                                            v-for="option in teamGapFilterOptions"
                                            :key="option.key"
                                            type="button"
                                            :class="{ active: teamGapFilter === option.key }"
                                            :aria-pressed="teamGapFilter === option.key"
                                            @click="updateTeamGapFilter(option.key)"
                                        >
                                            {{ option.label }} <span>{{ option.count }}</span>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="paginatedTeamGapRows.length" class="team-member-list">
                                    <button
                                        v-for="row in paginatedTeamGapRows"
                                        :key="row.sso"
                                        type="button"
                                        class="team-member-row"
                                        :class="{ selected: selectedGapPerson?.sso === row.sso }"
                                        @click="openGapDetail(row)"
                                    >
                                        <span class="team-member-avatar">{{ row.n?.[0] || '?' }}</span>
                                        <span class="team-member-copy">
                                            <strong>{{ `${row.t || ''}${row.n}` }}</strong>
                                            <small>{{ row.p || '-' }}<template v-if="approvalOrganizationFor(row) !== '-'"> · {{ approvalOrganizationFor(row) }}</template></small>
                                        </span>
                                        <span class="team-member-status" :class="row.assessmentProgress.key">
                                            {{ row.assessmentProgress.label }}
                                        </span>
                                    </button>
                                </div>
                                <div v-else class="team-gap-empty">ไม่พบบุคลากรตามตัวกรอง</div>
                                <div v-if="filteredTeamGapRows.length > teamGapPageSize" class="team-pagination compact-pagination">
                                    <span>{{ Math.min(teamGapPage, teamGapPageCount) }}/{{ teamGapPageCount }}</span>
                                    <div>
                                        <button type="button" aria-label="หน้าก่อนหน้า" :disabled="teamGapPage <= 1" @click="teamGapPage -= 1">←</button>
                                        <button type="button" aria-label="หน้าถัดไป" :disabled="teamGapPage >= teamGapPageCount" @click="teamGapPage += 1">→</button>
                                    </div>
                                </div>
                            </aside>

                            <section v-if="selectedGapPerson" class="team-competency-panel">
                                <div class="team-competency-head">
                                    <div>
                                        <div class="team-selected-person">
                                            <span>{{ `${selectedGapPerson.t || ''}${selectedGapPerson.n}` }}</span>
                                            <em :class="selectedGapSummary?.completed ? (selectedGapFailedCount ? 'failed' : 'passed') : 'pending'">
                                                {{ selectedGapSummary?.summary }}
                                            </em>
                                        </div>
                                        <div class="cs">{{ selectedGapPerson.p }}<template v-if="approvalOrganizationFor(selectedGapPerson) !== '-'"> · {{ approvalOrganizationFor(selectedGapPerson) }}</template></div>
                                    </div>
                                    <div class="team-selected-metrics" aria-label="สรุปสมรรถนะของบุคลากร">
                                        <span><small>ทั้งหมด</small><strong>{{ selectedGapRows.length }}</strong></span>
                                        <span class="passed"><small>ผ่าน</small><strong>{{ selectedGapPassedCount }}</strong></span>
                                        <span class="failed"><small>ต้องพัฒนา</small><strong>{{ selectedGapFailedCount }}</strong></span>
                                        <span v-if="selectedGapPendingCount" class="pending"><small>รอดำเนินการ</small><strong>{{ selectedGapPendingCount }}</strong></span>
                                    </div>
                                </div>

                                <template v-if="selectedGapRows.length">
                                    <div class="team-competency-tools">
                                        <div class="team-gap-filters" aria-label="กรองผลสมรรถนะ">
                                            <button type="button" :class="{ active: selectedGapCompetencyFilter === 'all' }" @click="selectedGapCompetencyFilter = 'all'; selectedGapPage = 1">ทั้งหมด {{ selectedGapRows.length }}</button>
                                            <button type="button" :class="{ active: selectedGapCompetencyFilter === 'gap' }" @click="selectedGapCompetencyFilter = 'gap'; selectedGapPage = 1">ต้องพัฒนา {{ selectedGapFailedCount }}</button>
                                            <button type="button" :class="{ active: selectedGapCompetencyFilter === 'passed' }" @click="selectedGapCompetencyFilter = 'passed'; selectedGapPage = 1">ผ่าน {{ selectedGapPassedCount }}</button>
                                            <button type="button" :class="{ active: selectedGapCompetencyFilter === 'pending' }" @click="selectedGapCompetencyFilter = 'pending'; selectedGapPage = 1">รอดำเนินการ {{ selectedGapPendingCount }}</button>
                                        </div>
                                        <label class="team-gap-search compact">
                                            <span aria-hidden="true">⌕</span>
                                            <input v-model="selectedGapSearch" type="search" placeholder="ค้นหาสมรรถนะ" aria-label="ค้นหาสมรรถนะ" @input="selectedGapPage = 1">
                                        </label>
                                    </div>

                                    <div class="team-competency-list">
                                        <div class="team-competency-list-head">
                                            <span>สมรรถนะ</span><span>คาดหวัง</span><span>ผลจริง</span><span>Gap</span><span>ผล</span>
                                        </div>
                                        <div
                                            v-for="row in paginatedSelectedGapRows"
                                            :key="row.id"
                                            class="team-competency-row"
                                            :class="{ pending: !isCompetencyApproved(row) }"
                                        >
                                            <div class="team-competency-name">
                                                <span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span>
                                                <div><strong>{{ row.code || '-' }}</strong><small>{{ row.title }}</small></div>
                                            </div>
                                            <span>{{ row.expected }}</span>
                                            <span>{{ isCompetencyApproved(row) ? row.headScore : '-' }}</span>
                                            <strong :class="isCompetencyApproved(row) ? (Number(row.gap) < 0 ? 'rc' : 'gcc') : ''">{{ isCompetencyApproved(row) ? formatTeamGap(row.gap) : '-' }}</strong>
                                            <span class="team-result-status" :class="competencyResultMeta(row).key">{{ competencyResultMeta(row).label }}</span>
                                        </div>
                                        <div v-if="filteredSelectedGapRows.length === 0" class="team-gap-empty">ไม่พบสมรรถนะตามตัวกรอง</div>
                                    </div>
                                    <div v-if="filteredSelectedGapRows.length > selectedGapPageSize" class="team-pagination">
                                        <span>หน้า {{ Math.min(selectedGapPage, selectedGapPageCount) }} จาก {{ selectedGapPageCount }}</span>
                                        <div>
                                            <button type="button" :disabled="selectedGapPage <= 1" @click="selectedGapPage -= 1">ก่อนหน้า</button>
                                            <button type="button" :disabled="selectedGapPage >= selectedGapPageCount" @click="selectedGapPage += 1">ถัดไป</button>
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="team-assessment-pending">
                                    <span class="team-pending-mark">…</span>
                                    <strong>ยังไม่มีสมรรถนะในรอบนี้</strong>
                                    <p>รายการสมรรถนะจะปรากฏเมื่อ HR กำหนดสมรรถนะให้ตำแหน่งของบุคลากร</p>
                                </div>
                            </section>
                            <section v-else class="team-competency-panel team-no-selection">
                                <strong>เลือกบุคลากรเพื่อดูรายละเอียด</strong>
                            </section>
                    </div>
                </template>

                <section
                    v-else-if="(activePage === 'dh-fc-topic-approval' && fcTopicApprovalModule.enabled) || (activePage === 'dh-assess' && assessmentHubTab === 'topics' && (fcTopicApprovalModule.enabled || assessmentApprovalModule.enabled))"
                    class="assessment-approval-hub"
                >
                    <template v-if="activePage === 'dh-assess'">
                        <header class="assessment-hub-header">
                            <div class="assessment-hub-copy">
                                <span>งานที่ได้รับมอบหมาย</span>
                                <h1>อนุมัติการประเมิน</h1>
                                <p>พิจารณาหัวข้อก่อนเริ่มประเมิน และตรวจผลตามลำดับผู้บังคับบัญชา</p>
                            </div>
                            <div class="assessment-queue-status" :class="{ clear: (fcTopicApprovalModule.items?.length || 0) + supervisorPendingRows.length === 0 }">
                                <span aria-hidden="true"></span>
                                <strong>{{ (fcTopicApprovalModule.items?.length || 0) + supervisorPendingRows.length === 0 ? 'ไม่มีงานรอดำเนินการ' : `มี ${(fcTopicApprovalModule.items?.length || 0) + supervisorPendingRows.length} รายการรอดำเนินการ` }}</strong>
                            </div>
                        </header>
                        <nav v-if="assessmentApprovalModule.enabled" class="assessment-hub-tabs" aria-label="ประเภทงานอนุมัติ">
                            <button type="button" class="active" aria-current="page"><i aria-hidden="true">1</i><span><strong>หัวข้อการประเมิน</strong><small>ตรวจก่อนเปิดแบบประเมิน</small></span><b>{{ fcTopicApprovalModule.items?.length || 0 }}</b></button>
                            <button type="button" @click="assessmentHubTab = 'results'"><i aria-hidden="true">2</i><span><strong>ผลการประเมิน</strong><small>ตรวจผลและส่งต่อ</small></span><b>{{ supervisorPendingRows.length }}</b></button>
                        </nav>
                    </template>
                    <FcTopicApproval :module="fcTopicApprovalModule" :compact="activePage === 'dh-assess'" />
                </section>

                <template v-else-if="activePage === 'dh-assess' && assessmentHubTab === 'results' && assessmentApprovalModule.enabled">
                    <header class="assessment-hub-header">
                        <div class="assessment-hub-copy">
                            <span>งานที่ได้รับมอบหมาย</span>
                            <h1>อนุมัติการประเมิน</h1>
                            <p>พิจารณาหัวข้อก่อนเริ่มประเมิน และตรวจผลตามลำดับผู้บังคับบัญชา</p>
                        </div>
                        <div class="assessment-queue-status" :class="{ clear: (fcTopicApprovalModule.items?.length || 0) + supervisorPendingRows.length === 0 }">
                            <span aria-hidden="true"></span>
                            <strong>{{ (fcTopicApprovalModule.items?.length || 0) + supervisorPendingRows.length === 0 ? 'ไม่มีงานรอดำเนินการ' : `มี ${(fcTopicApprovalModule.items?.length || 0) + supervisorPendingRows.length} รายการรอดำเนินการ` }}</strong>
                        </div>
                    </header>
                    <nav class="assessment-hub-tabs" aria-label="ประเภทงานอนุมัติ">
                        <button type="button" @click="assessmentHubTab = 'topics'"><i aria-hidden="true">1</i><span><strong>หัวข้อการประเมิน</strong><small>ตรวจก่อนเปิดแบบประเมิน</small></span><b>{{ fcTopicApprovalModule.items?.length || 0 }}</b></button>
                        <button type="button" class="active" aria-current="page"><i aria-hidden="true">2</i><span><strong>ผลการประเมิน</strong><small>ตรวจผลและส่งต่อ</small></span><b>{{ supervisorPendingRows.length }}</b></button>
                    </nav>
                    <template v-if="assessmentApprovalModule.enabled">
                        <section class="assessment-results-overview">
                            <div>
                                <h2>ผลการประเมิน</h2>
                                <p>{{ approvalRoleLabel }}ตรวจผลก่อนอนุมัติและส่งต่อไปยังขั้นตอนถัดไป</p>
                            </div>
                            <dl class="assessment-status-summary">
                                <div class="pending">
                                    <dt>รอตรวจ</dt>
                                    <dd>{{ supervisorPendingRows.length }}</dd>
                                    <small>คน</small>
                                </div>
                                <div>
                                    <dt>อยู่ระหว่างดำเนินการ</dt>
                                    <dd>{{ supervisorInProgressRows.length }}</dd>
                                    <small>คน</small>
                                </div>
                                <div class="complete">
                                    <dt>อนุมัติครบแล้ว</dt>
                                    <dd>{{ supervisorApprovedRows.length }}</dd>
                                    <small>คน</small>
                                </div>
                            </dl>
                        </section>

                        <div v-if="activePage === 'dh-fc-topic-approval'" class="card supervisor-approval-card mb16">
                            <div class="team-card-head">
                                <div>
                                    <div class="ct">อนุมัติหัวข้อ FC</div>
                                    <div class="cs">รายการที่ผู้ประเมินเลือก FC และส่งให้หัวหน้า 1 อนุมัติก่อนเปิดแบบประเมิน</div>
                                </div>
                                <span class="b by">{{ fcTopicApprovalRows.length }} รายการ</span>
                            </div>
                            <div class="team-table-wrap approval-table-wrap">
                                <table class="team-table approval-table">
                                    <thead>
                                        <tr>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>ตำแหน่ง</th>
                                            <th>หัวข้อ FC ที่เลือก</th>
                                            <th>ดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="person in fcTopicApprovalRows" :key="`fc-${person.sso}`">
                                            <td>
                                                <div class="person-cell">
                                                    <button class="review-person-link" type="button" @click.stop="openSupervisorApprovalModal(person)">{{ `${person.t || ''}${person.n}` }}</button>
                                                    <small>{{ approvalOrganizationFor(person) }}</small>
                                                </div>
                                            </td>
                                            <td>{{ person.p || '-' }}</td>
                                            <td>
                                                <div class="fc-topic-list">
                                                    <span
                                                        v-for="topic in fcTopicNameList(person.fcTopicSelection)"
                                                        :key="topic"
                                                        class="fc-topic-chip"
                                                    >
                                                        {{ topic }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex g8">
                                                    <button class="btn btn-t btn-sm" type="button" @click="openFcTopicDecision(person, 'approve')">อนุมัติ</button>
                                                    <button class="btn btn-s btn-sm danger-text" type="button" @click="openFcTopicDecision(person, 'reject')">ส่งกลับ</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="fcTopicApprovalRows.length === 0">
                                            <td colspan="4" class="muted ac py20">ไม่มีรายการรออนุมัติหัวข้อ FC</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-if="activePage === 'dh-fc-topic-approval' && selectedFcTopicApproval" class="approval-modal-backdrop" @click.self="closeFcTopicDecision">
                            <div class="fc-topic-decision-modal">
                                <div class="approval-modal-head">
                                    <div>
                                        <div class="sec-t">
                                            {{ fcTopicDecision === 'approve' ? 'อนุมัติหัวข้อ FC' : 'ส่งหัวข้อ FC กลับให้เลือกใหม่' }}
                                        </div>
                                        <div class="sec-s">{{ selectedFcTopicApproval.p || '-' }} · {{ `${selectedFcTopicApproval.t || ''}${selectedFcTopicApproval.n}` }}</div>
                                    </div>
                                    <button class="btn btn-s btn-sm" type="button" @click="closeFcTopicDecision">ปิด</button>
                                </div>
                                <div class="fc-topic-decision-body">
                                    <div class="fc-topic-review-list">
                                        <span
                                            v-for="topic in fcTopicNameList(selectedFcTopicApproval.fcTopicSelection)"
                                            :key="topic"
                                            class="fc-topic-chip"
                                        >
                                            {{ topic }}
                                        </span>
                                    </div>
                                    <label class="lbl" for="fc-topic-comment">
                                        {{ fcTopicDecision === 'reject' ? 'เหตุผลที่ส่งกลับ' : 'หมายเหตุ' }}
                                    </label>
                                    <textarea
                                        id="fc-topic-comment"
                                        v-model="fcTopicComment"
                                        class="fc-topic-comment"
                                        :placeholder="fcTopicDecision === 'reject' ? 'กรอกเหตุผลให้ผู้ประเมินเลือกใหม่' : 'หมายเหตุเพิ่มเติม (ไม่บังคับ)'"
                                    />
                                    <div class="flex end g8">
                                        <button class="btn btn-s" type="button" @click="closeFcTopicDecision">ยกเลิก</button>
                                        <button
                                            class="btn btn-t"
                                            type="button"
                                            :disabled="isSubmittingFcTopicDecision || (fcTopicDecision === 'reject' && !fcTopicComment.trim())"
                                            @click="submitFcTopicDecision"
                                        >
                                            {{ isSubmittingFcTopicDecision ? 'กำลังบันทึก...' : (fcTopicDecision === 'approve' ? 'ยืนยันอนุมัติ' : 'ยืนยันส่งกลับ') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <section class="assessment-list-section">
                            <header class="assessment-list-head">
                                <div>
                                    <h3>รอคุณประเมิน</h3>
                                    <p>รายการที่มาถึงลำดับของคุณและดำเนินการได้ทันที</p>
                                </div>
                                <span>{{ supervisorPendingRows.length }} รายการ</span>
                            </header>
                            <div v-if="supervisorPendingRows.length === 0" class="assessment-empty-state">
                                <span aria-hidden="true">✓</span>
                                <div><strong>ไม่มีผลการประเมินรอตรวจ</strong><p>เมื่อมีรายการมาถึงลำดับของคุณ ระบบจะแสดงที่นี่</p></div>
                            </div>
                            <div v-else class="team-table-wrap approval-table-wrap">
                                <table class="team-table approval-table">
                                    <thead>
                                        <tr>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>ตำแหน่ง</th>
                                            <th>วันที่ส่งประเมิน</th>
                                            <th>สถานะ</th>
                                            <th><span class="sr-only">ดำเนินการ</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="person in supervisorPendingRows"
                                            :key="person.sso"
                                            :class="{ selected: selectedSupervisorApproval?.sso === person.sso, disabled: !person.hasReviewableCompetencies }"
                                            @click="openSupervisorApprovalModal(person)"
                                        >
                                            <td>
                                                <div class="person-cell">
                                                    <button class="review-person-link" type="button" @click.stop="openSupervisorApprovalModal(person)">{{ `${person.t || ''}${person.n}` }}</button>
                                                    <small>{{ approvalOrganizationFor(person) }}</small>
                                                </div>
                                            </td>
                                            <td>{{ person.p || '-' }}</td>
                                            <td>{{ person.hasSubmittedAssessment ? person.submittedAt : '-' }}</td>
                                            <td><span class="b" :class="person.statusMeta.cls">{{ person.statusMeta.label }}</span></td>
                                            <td><button class="assessment-row-action" type="button" @click.stop="openSupervisorApprovalModal(person)">ตรวจผล</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="assessment-list-section secondary">
                            <header class="assessment-list-head">
                                <div>
                                    <h3>ติดตามสถานะ</h3>
                                    <p>สมาชิกในสายของคุณที่ยังไม่ถึงคิวตรวจ กำลังแก้ไข หรือดำเนินการแล้ว</p>
                                </div>
                                <span>{{ supervisorTrackingRows.length }} รายการ</span>
                            </header>
                            <div v-if="supervisorTrackingRows.length === 0" class="assessment-empty-state muted-state">
                                <span aria-hidden="true">–</span>
                                <div><strong>ยังไม่มีรายการติดตาม</strong><p>รายการที่ผ่านการดำเนินการจะแสดงพร้อมสถานะล่าสุดที่นี่</p></div>
                            </div>
                            <div v-else class="team-table-wrap approval-table-wrap">
                                <table class="team-table approval-table">
                                    <thead>
                                        <tr>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>ตำแหน่ง</th>
                                            <th>วันที่อัปเดต</th>
                                            <th>สถานะปัจจุบัน</th>
                                            <th><span class="sr-only">ดำเนินการ</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="person in supervisorTrackingRows"
                                            :key="`tracking-${person.sso}`"
                                            :class="{ selected: selectedSupervisorApproval?.sso === person.sso }"
                                            @click="openSupervisorApprovalModal(person)"
                                        >
                                            <td>
                                                <div class="person-cell">
                                                    <strong>{{ `${person.t || ''}${person.n}` }}</strong>
                                                    <small>{{ approvalOrganizationFor(person) }}</small>
                                                </div>
                                            </td>
                                            <td>{{ person.p || '-' }}</td>
                                            <td>{{ person.hasSubmittedAssessment ? person.submittedAt : '-' }}</td>
                                            <td><span class="b" :class="person.statusMeta.cls">{{ person.statusMeta.label }}</span></td>
                                            <td><button class="assessment-row-action secondary" type="button" @click.stop="openSupervisorApprovalModal(person)">ดูรายละเอียด</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <div v-if="selectedSupervisorApproval" class="approval-modal-backdrop" @click.self="closeSupervisorApprovalModal">
                            <div class="approval-modal">
                                <div class="approval-modal-head">
                                    <div>
                                        <div class="sec-t">ตรวจสอบผลประเมิน · {{ supervisorApprovalName }}</div>
                                        <div class="sec-s">ตำแหน่ง {{ selectedSupervisorApproval.p || '-' }} · {{ supervisorApprovalOrganization }} · ระดับความคาดหวัง {{ supervisorApprovalExpectedLevel }}</div>
                                    </div>
                                    <button class="btn btn-s btn-sm" type="button" @click="closeSupervisorApprovalModal">ปิด</button>
                                </div>

                                <div class="approval-modal-body">
                                    <div v-for="row in selectedSupervisorApproval.results" :key="`detail-${supervisorCompetencyKey(row)}`" class="approval-competency-card" :class="{ disabled: !row.hasAssessment }">
                                        <button
                                            class="approval-competency-head"
                                            :class="{ open: isSupervisorCompetencyOpen(row) }"
                                            type="button"
                                            :aria-expanded="isSupervisorCompetencyOpen(row)"
                                            @click="toggleSupervisorCompetency(row)"
                                        >
                                            <div class="flex ic g10">
                                                <span class="tag-cc" :class="{ 'tag-fc': row.group === 'FC' }">{{ row.group }}</span>
                                                <span>{{ row.code }} · {{ row.title }}</span>
                                                <span class="approval-accordion-icon">{{ isSupervisorCompetencyOpen(row) ? 'ซ่อน' : 'ดูรายละเอียด' }}</span>
                                            </div>
                                            <div class="flex ic g8">
                                                <span class="b" :class="competencyApprovalStatus(row).cls">{{ competencyApprovalStatus(row).label }}</span>
                                                <span class="b bgr">Expected Level {{ row.expected ?? '-' }}</span>
                                                <span class="b by">{{ row.checkedIndicatorCount || 0 }}/{{ totalIndicatorCount(row) }}</span>
                                            </div>
                                        </button>

                                        <div v-if="false" class="approval-accordion-body">
                                            <div v-for="level in approvalLevelsForRow(row)" :key="level.id || level.lvl" class="approval-level-card">
                                                <div class="approval-level-head">
                                                    <div>
                                                        <div class="fw8">ระดับที่ {{ level.lvl }}</div>
                                                        <div class="muted fs12">เลือกแล้ว {{ level.checkedIndicators.length }}/{{ level.indicators?.length || 0 }} พฤติกรรม</div>
                                                    </div>
                                                    <span
                                                        class="approval-level-state"
                                                        :class="levelSelectionState(level)"
                                                    >
                                                        {{ levelSelectionLabel(level) }}
                                                    </span>
                                                </div>

                                                <div class="approval-checklist">
                                                    <label
                                                        v-for="{ indicator, index, checked } in level.indicatorsWithStatus"
                                                        :key="`${supervisorCompetencyKey(row)}-${level.id || level.lvl}-${index}`"
                                                        class="approval-check-row"
                                                        :class="{ selected: checked, muted: !checked }"
                                                    >
                                                        <input :checked="checked" disabled type="checkbox" />
                                                        <span class="approval-check-copy">
                                                            <strong>ข้อ {{ level.lvl }}.{{ index + 1 }}</strong>
                                                            <span>{{ indicator }}</span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="approval-comment-box">
                                                <div class="fs12 fw8">ความคิดเห็นของผู้ประเมิน</div>
                                                <div>{{ row.note || 'ไม่มีความคิดเห็น' }}</div>
                                            </div>
                                            <label class="approval-reviewer-comment">
                                                <span>ความคิดเห็นของคุณ</span>
                                                <textarea
                                                    :value="reviewerComments[approvalCommentKey(row)] ?? ''"
                                                    rows="3"
                                                    :disabled="!canDecideSupervisorCompetency(row)"
                                                    @input="reviewerComments[approvalCommentKey(row)] = $event.target.value"
                                                    placeholder="พิมพ์ข้อเสนอแนะสำหรับสมรรถนะนี้ หากไม่อนุมัติต้องกรอกเหตุผลก่อน"
                                                ></textarea>
                                            </label>
                                            <div class="approval-row-actions">
                                                <button class="btn btn-r" type="button" :disabled="!canDecideSupervisorCompetency(row)" @click.stop="requestApprovalDecision('reject', row)">
                                                    ไม่อนุมัติ
                                                </button>
                                                <button class="btn btn-t" type="button" :disabled="!canDecideSupervisorCompetency(row)" @click.stop="requestApprovalDecision('approve', row)">
                                                    อนุมัติ
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="selectedSupervisorApproval.results.length === 0" class="empty-card compact">
                                        ยังไม่มีผลการประเมินให้ดู
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div v-if="selectedSupervisorCompetency" class="competency-detail-backdrop" @click.self="closeSupervisorCompetencyModal">
                            <section class="competency-detail-modal" role="dialog" aria-modal="true">
                                <header class="competency-detail-head">
                                    <div>
                                        <div class="flex ic g10">
                                            <span class="tag-cc" :class="{ 'tag-fc': selectedSupervisorCompetency.group === 'FC' }">{{ selectedSupervisorCompetency.group }}</span>
                                            <strong>{{ selectedSupervisorCompetency.code }}</strong>
                                        </div>
                                        <h2>{{ selectedSupervisorCompetency.title }}</h2>
                                        <p>{{ supervisorApprovalName }} · Expected Level {{ selectedSupervisorCompetency.expected ?? '-' }}</p>
                                        <span class="b competency-current-status" :class="competencyApprovalStatus(selectedSupervisorCompetency).cls">
                                            {{ competencyApprovalStatus(selectedSupervisorCompetency).label }}
                                        </span>
                                    </div>
                                    <button class="btn btn-s btn-sm" type="button" @click="closeSupervisorCompetencyModal">ปิด</button>
                                </header>

                                <div class="competency-detail-body">
                                    <section v-if="selectedSupervisorCompetency.detail" class="approval-comment-box">
                                        <div class="fs12 fw8">คำอธิบายสมรรถนะ</div>
                                        <div>{{ selectedSupervisorCompetency.detail }}</div>
                                    </section>

                                    <section class="assessment-workflow-panel">
                                        <div class="assessment-workflow-title">
                                            <strong>ลำดับการประเมิน</strong>
                                            <span>{{ selectedSupervisorCompetency.workflowStatus?.totalSteps || selectedSupervisorApproval.approvalTotalSteps }} ลำดับ</span>
                                        </div>
                                        <ol class="assessment-workflow-list">
                                            <li
                                                v-for="node in selectedSupervisorCompetency.workflowStatus?.timeline || []"
                                                :key="`${node.kind}-${node.step}`"
                                                :class="`is-${node.state}`"
                                            >
                                                <span class="assessment-workflow-marker">{{ node.state === 'complete' ? '✓' : (node.step || 'ตนเอง') }}</span>
                                                <div>
                                                    <strong>{{ node.name }}{{ node.position ? ` · ${node.position}` : '' }}</strong>
                                                    <small>{{ node.label }}</small>
                                                </div>
                                                <b>{{ workflowNodeStateLabel(node.state) }}</b>
                                            </li>
                                        </ol>
                                    </section>

                                    <details
                                        v-if="selectedSupervisorCompetency.reviewHistory?.length"
                                        class="assessment-review-history"
                                        open
                                    >
                                        <summary>
                                            ประวัติการพิจารณา {{ selectedSupervisorCompetency.reviewHistory.length }} รายการ
                                        </summary>
                                        <div
                                            v-for="review in selectedSupervisorCompetency.reviewHistory"
                                            :key="`${review.reviewerId}-${review.reviewStep}-${review.submittedAt}`"
                                            class="assessment-history-row"
                                        >
                                            <strong>
                                                {{ review.reviewerName || 'ไม่พบชื่อผู้พิจารณา' }}
                                                <span
                                                    class="assessment-review-decision"
                                                    :class="review.decision === 'approved' ? 'is-approved' : 'is-returned'"
                                                >
                                                    {{ review.decision === 'approved' ? 'อนุมัติ' : 'ส่งกลับ' }}
                                                </span>
                                            </strong>
                                            <span>
                                                ผู้พิจารณาลำดับที่ {{ review.reviewStep || '-' }} · {{ review.submittedAt || '-' }}
                                            </span>
                                            <p v-if="review.comment">{{ review.comment }}</p>
                                        </div>
                                    </details>

                                    <article v-for="level in selectedSupervisorLevels" :key="level.id" class="detail-level-card">
                                        <header>
                                            <div><strong>ระดับที่ {{ level.number }}</strong><span>เลือกแล้ว {{ level.checkedCount }}/{{ level.rows.length }} พฤติกรรม</span></div>
                                            <b :class="{ complete: level.rows.length > 0 && level.checkedCount === level.rows.length, partial: level.checkedCount > 0 && level.checkedCount < level.rows.length }">
                                                {{ level.checkedCount === 0 ? 'ยังไม่เลือก' : (level.checkedCount === level.rows.length ? 'เลือกครบ' : 'เลือกบางส่วน') }}
                                            </b>
                                        </header>
                                        <div class="detail-indicator-list">
                                            <div v-for="indicator in level.rows" :key="indicator.index" :class="{ selected: indicator.checked }">
                                                <span>{{ indicator.checked ? '✓' : '' }}</span>
                                                <p><strong>ข้อ {{ level.number }}.{{ indicator.index + 1 }}</strong>{{ indicator.text }}</p>
                                            </div>
                                            <p v-if="level.rows.length === 0" class="muted">ยังไม่มีพฤติกรรมบ่งชี้ในระดับนี้</p>
                                        </div>
                                    </article>

                                    <div class="approval-comment-box">
                                        <div class="fs12 fw8">ความคิดเห็นของผู้ประเมิน</div>
                                        <div>{{ selectedSupervisorCompetency.note || 'ไม่มีความคิดเห็น' }}</div>
                                    </div>
                                    <label v-if="canDecideSupervisorCompetency(selectedSupervisorCompetency)" class="approval-reviewer-comment">
                                        <span>ความคิดเห็นของคุณ</span>
                                        <textarea
                                            :value="reviewerComments[approvalCommentKey(selectedSupervisorCompetency)] ?? ''"
                                            rows="3"
                                            :disabled="!canDecideSupervisorCompetency(selectedSupervisorCompetency)"
                                            @input="reviewerComments[approvalCommentKey(selectedSupervisorCompetency)] = $event.target.value"
                                            placeholder="พิมพ์ข้อเสนอแนะ หากส่งกลับแก้ไขต้องกรอกเหตุผล"
                                        ></textarea>
                                    </label>
                                </div>

                                <footer class="competency-detail-actions">
                                    <span>
                                        {{ canDecideSupervisorCompetency(selectedSupervisorCompetency)
                                            ? 'ตรวจสอบพฤติกรรมบ่งชี้ก่อนดำเนินการ'
                                            : `ดูได้อย่างเดียว · ${competencyApprovalStatus(selectedSupervisorCompetency).label}` }}
                                    </span>
                                    <div v-if="canDecideSupervisorCompetency(selectedSupervisorCompetency)" class="flex g8">
                                        <button class="btn btn-r" type="button" @click="requestApprovalDecision('reject', selectedSupervisorCompetency)">ส่งกลับแก้ไข</button>
                                        <button class="btn btn-t" type="button" @click="requestApprovalDecision('approve', selectedSupervisorCompetency)">ผ่านและส่งต่อ</button>
                                    </div>
                                </footer>
                            </section>
                        </div>

                        <div v-if="approvalDecision" class="approval-decision-backdrop" @click.self="closeApprovalDecision">
                            <div class="approval-decision-modal">
                                <div class="approval-decision-title">{{ approvalDecisionTitle }}</div>
                                <div class="approval-decision-message">{{ approvalDecisionMessage }}</div>
                                <div
                                    v-if="approvalDecision === 'reject' && !approvalComment.trim()"
                                    class="approval-decision-warning"
                                >
                                    ยังส่งกลับไม่ได้ กรุณากรอกเหตุผลในช่อง “ความคิดเห็นของคุณ” ก่อน
                                </div>
                                <div class="approval-decision-actions">
                                    <button class="btn btn-s" type="button" :disabled="isSubmittingApprovalDecision" @click="closeApprovalDecision">
                                        ยกเลิก
                                    </button>
                                    <button
                                        class="btn"
                                        :class="approvalDecision === 'approve' ? 'approval-confirm-button' : 'approval-reject-button'"
                                        type="button"
                                        :disabled="isSubmittingApprovalDecision || (approvalDecision === 'reject' && !approvalComment.trim())"
                                        @click="submitApprovalDecision"
                                    >
                                        {{ isSubmittingApprovalDecision ? 'กำลังดำเนินการ...' : 'ยืนยัน' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                    <div class="g5 assess-summary mb20">
                        <div class="sc navy-top">
                            <div class="sl">ทั้งหมด</div>
                            <div class="sv">{{ assessCounts.total }}</div>
                            <div class="ss muted">คนในความดูแล</div>
                        </div>
                        <div class="sc red-top">
                            <div class="sl">ยังไม่ส่ง</div>
                            <div class="sv rc">{{ assessCounts.notSent }}</div>
                            <div class="ss muted">รอลูกน้องประเมินตนเอง</div>
                        </div>
                        <div class="sc yellow-top">
                            <div class="sl">รอคุณประเมิน</div>
                            <div class="sv yc">{{ assessCounts.pending }}</div>
                            <div class="ss muted">คน</div>
                        </div>
                        <div class="sc green-top">
                            <div class="sl">หัวหน้าฝ่าย/คณบดี</div>
                            <div class="sv gcc">{{ assessCounts.forwarded }}</div>
                            <div class="ss muted">อยู่ระหว่างพิจารณา</div>
                        </div>
                        <div class="sc green-top">
                            <div class="sl">เสร็จสมบูรณ์</div>
                            <div class="sv gcc">{{ assessCounts.done }}</div>
                            <div class="ss muted">คน</div>
                        </div>
                    </div>
                    <div class="flex ic jb mb20">
                        <div>
                            <div class="sec-t">ประเมินลูกน้อง</div>
                            <div class="sec-s">เลือกบุคลากรเพื่อดูผลประเมินตนเอง หลักฐาน และให้คะแนนพร้อม feedback</div>
                        </div>
                        <span class="b by">รอคุณประเมิน {{ assessCounts.pending }} คน</span>
                    </div>

                    <div class="assessment-workspace">
                        <div class="card assess-list-card">
                            <div class="ch"><div class="ct">ผู้ส่งแบบประเมินแล้ว</div></div>
                            <button
                                v-for="person in teamMembers"
                                :key="person.sso"
                                class="assess-person"
                                :class="{ selected: selectedAssessment?.sso === person.sso }"
                                type="button"
                                @click="selectAssessmentEmployee(person)"
                            >
                                <div class="av row-avatar">{{ person.n[0] }}</div>
                                <div class="row-main">
                                    <div class="fw8 fs13">{{ `${person.t || ''}${person.n}` }}</div>
                                    <div class="muted fs11">{{ person.p }} · {{ approvalOrganizationFor(person) }}</div>
                                </div>
                                <span class="b" :class="person.evalStatus === 'unit_evaluated' ? 'bt' : person.evalStatus === 'self_submitted' ? 'by' : 'br'">
                                    {{ person.evalStatus === 'unit_evaluated' ? 'ส่งต่อแล้ว' : person.evalStatus === 'self_submitted' ? 'รอประเมิน' : 'ยังไม่ส่ง' }}
                                </span>
                            </button>
                        </div>

                        <div v-if="selectedAssessment" class="assessment-detail">
                            <div class="flex ic jb mb12">
                                <div>
                                    <div class="fw8 fs16">การประเมิน: {{ assessmentName }}</div>
                                    <div class="muted fs12">{{ selectedAssessment.p }} · {{ approvalOrganizationFor(selectedAssessment) }}</div>
                                </div>
                                <span class="b" :class="activeDraft.submitted ? 'bt' : 'bgr'">
                                    {{ activeDraft.submitted ? 'ส่งต่อหัวหน้างานแล้ว' : (assessmentSavedAt || 'ยังไม่มีการแก้ไขผลการประเมิน') }}
                                </span>
                            </div>

                            <div class="flex ic jb mb14">
                                <div class="assessment-group-title" style="margin: 0">
                                    {{ selectedGroup === 'all' ? 'ทุกประเภทสมรรถนะ' : `ประเภท ${selectedGroup}` }}
                                    — แสดง {{ filteredComps.length }} รายการ
                                </div>
                                <select class="filter-select" v-model="selectedGroup">
                                    <option v-for="g in compGroups" :key="g.value" :value="g.value">
                                        {{ g.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-for="(comp, index) in filteredComps" :key="comp.id" class="card assessment-card">
                                <div class="assessment-card-head">
                                    <div class="flex ic g10">
                                        <span class="tag-cc">{{ comp.group }}</span>
                                        <span class="fw8 fs14">{{ comp.title }}</span>
                                    </div>
                                    <div class="tc fw8 fs12">{{ getHeadScore(comp) }}/5 ✓</div>
                                </div>

                                <div class="assessment-columns">
                                    <div class="assessment-side self">
                                        <div class="muted fw7 fs11 mb10">พฤติกรรมบ่งชี้ (ใช้ประกอบการตัดสิน)</div>
                                        <ul class="behavior-list">
                                            <li v-for="behavior in comp.behaviors[comp.selfScore]" :key="behavior">{{ behavior }}</li>
                                        </ul>
                                        <div class="hint-box">แสดงพฤติกรรมบ่งชี้ตามคะแนนประเมินตนเองระดับ {{ comp.selfScore }}: {{ scoreLabels[comp.selfScore - 1] }}</div>

                                        <div class="muted fw7 fs11 mb8">คะแนนความสามารถของบุคลากร (Self-Score)</div>
                                        <div class="score-grid">
                                            <div v-for="score in [1, 2, 3, 4, 5]" :key="score" class="score-tile" :class="{ selected: comp.selfScore === score }">
                                                <span>{{ score }}</span>
                                                <small>{{ scoreLabels[score - 1] }}</small>
                                            </div>
                                        </div>

                                        <div class="muted fw7 fs11 mb8">หลักฐานประกอบ (Evidence)</div>
                                        <div class="evidence-grid">
                                            <div class="evidence-file">
                                                <div class="file-icon">▤</div>
                                                <div class="bc fw7 fs12">ยังไม่แนบไฟล์</div>
                                            </div>
                                            <div class="fs11">
                                                <div class="mb8"><span class="fw8">URL:</span> <span class="bc">{{ comp.evidenceUrl }}</span></div>
                                                <div><span class="fw8">คำอธิบาย:</span> <span class="muted">{{ comp.evidenceDesc }}</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="assessment-side reviewer">
                                        <div class="bc fw8 fs12 mb10">1. หัวหน้าหน่วย (คุณ) *</div>
                                        <div class="muted fw7 fs11 mb6">พฤติกรรมบ่งชี้ (ใช้ประกอบการตัดสิน)</div>
                                        <ul class="behavior-list">
                                            <li v-for="behavior in comp.behaviors[getHeadScore(comp)]" :key="behavior">{{ behavior }}</li>
                                        </ul>
                                        <div class="hint-box">แสดงพฤติกรรมบ่งชี้ตามคะแนนที่หัวหน้าหน่วยเลือก ระดับ {{ getHeadScore(comp) }}: {{ scoreLabels[getHeadScore(comp) - 1] }}</div>

                                        <div class="muted fw7 fs11 mb8">คะแนนความสามารถของบุคลากรโดยหัวหน้าหน่วย</div>
                                        <div class="score-grid">
                                            <button
                                                v-for="score in [1, 2, 3, 4, 5]"
                                                :key="score"
                                                class="score-tile score-button"
                                                :class="{ selected: getHeadScore(comp) === score }"
                                                type="button"
                                                @click="updateAssessmentScore(comp, score)"
                                            >
                                                <span>{{ score }}</span>
                                                <small>{{ scoreLabels[score - 1] }}</small>
                                            </button>
                                        </div>

                                        <textarea
                                            class="ta feedback-box"
                                            :value="getHeadFeedback(comp)"
                                            placeholder="ใส่คำเสนอแนะ (ถ้ามี)..."
                                            @input="updateAssessmentFeedback(comp, $event.target.value)"
                                        />

                                        <div v-if="index === filteredComps.length - 1" class="approval-flow">
                                            <div class="flow-step"><div class="av s24 flow-dot">2</div><div><div class="fw7 fs12">หัวหน้างาน</div><div class="muted fs11">รอดำเนินการ...</div></div></div>
                                            <div class="flow-step"><div class="av s24 flow-dot">3</div><div><div class="fw7 fs12">คณบดี</div><div class="muted fs11">รอยืนยัน</div></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex g8 mt16">
                                <button class="btn btn-s" type="button" style="flex: 1" disabled>
                                    {{ assessmentSavedAt || 'ระบบจะบันทึกร่างอัตโนมัติ' }}
                                </button>
                                <button class="btn btn-t" type="button" style="flex: 2" :disabled="activeDraft.submitted" @click="submitAssessmentToManager">
                                    {{ activeDraft.submitted ? 'ส่งผลการประเมินแล้ว' : 'ส่งผลการประเมินให้หัวหน้างาน' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    </template>
                </template>

                <div v-else class="card empty-card">
                    หน้านี้กำลังเชื่อมต่อข้อมูลสำหรับหัวหน้าหน่วย
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.embedded-reviewer-shell {
    display: block;
    width: 100%;
    height: auto;
    min-height: 0;
    overflow: visible;
    background: transparent;
}

.embedded-reviewer-shell > .main {
    width: 100%;
    min-height: 0;
    overflow: visible;
}

.embedded-reviewer-content {
    overflow: visible;
    padding: 0;
}

.menu-btn {
    padding: 8px;
    min-width: 40px;
    justify-content: center;
    border: none;
    background: transparent;
}

.stat-button {
    text-align: left;
    cursor: pointer;
    font-family: inherit;
}

.stat-button.selected {
    border-color: var(--blue);
}

.idp-summary {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.team-page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.team-dashboard-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
}

.team-dashboard-copy > span {
    display: block;
    margin-bottom: 6px;
    color: oklch(48% 0.1 165);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.14em;
}

.team-dashboard-copy h1 {
    margin: 0;
    color: oklch(31% 0.025 165);
    font-size: 24px;
    line-height: 1.25;
}

.team-dashboard-copy p {
    margin: 6px 0 0;
    color: oklch(58% 0.02 165);
    font-size: 12px;
}

.team-round-context {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 220px;
    padding: 11px 14px;
    border: 1px solid oklch(86% 0.02 165);
    border-radius: 10px;
    background: oklch(98.5% 0.006 165);
}

.team-round-context > span {
    width: 9px;
    height: 9px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: oklch(55% 0.13 165);
    box-shadow: 0 0 0 4px oklch(92% 0.04 165);
}

.team-round-context small,
.team-round-context strong {
    display: block;
}

.team-round-context small {
    color: oklch(59% 0.018 165);
    font-size: 10px;
}

.team-round-context strong {
    margin-top: 2px;
    color: oklch(34% 0.035 165);
    font-size: 12px;
}

.team-summary-band {
    margin-bottom: 16px;
}

.team-dashboard-empty {
    display: grid;
    min-height: 360px;
    place-items: center;
    align-content: center;
    color: oklch(57% 0.02 165);
    text-align: center;
}

.team-dashboard-empty > span {
    display: grid;
    width: 46px;
    height: 46px;
    margin-bottom: 14px;
    place-items: center;
    border-radius: 50%;
    background: oklch(94% 0.02 165);
    color: oklch(48% 0.08 165);
    font-weight: 900;
}

.team-dashboard-empty strong {
    color: oklch(32% 0.025 165);
    font-size: 15px;
}

.team-dashboard-empty p {
    max-width: 52ch;
    margin: 7px 0 0;
    font-size: 11px;
}

.team-metrics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.team-table-card {
    overflow: hidden;
}

.team-overview-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    overflow: hidden;
    border: 1px solid #dce3e8;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 3px 10px rgba(33, 50, 57, 0.05);
}

.team-overview-item {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 3px 18px;
    padding: 17px 20px 16px 38px;
}

.team-overview-item + .team-overview-item {
    border-left: 1px solid #e3e8eb;
}

.team-overview-item::before {
    position: absolute;
    top: 23px;
    left: 20px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4f7d70;
    content: '';
}

.team-overview-item.failed::before { background: #c84a38; }
.team-overview-item.passed::before { background: #229664; }

.team-overview-item > span {
    align-self: end;
    color: #53616d;
    font-size: 12px;
    font-weight: 800;
}

.team-overview-item strong {
    grid-row: span 2;
    align-self: center;
    color: #263b35;
    font-size: 29px;
    line-height: 1;
}

.team-overview-item.failed strong { color: #b93b2e; }
.team-overview-item.passed strong { color: #21875e; }

.team-overview-item strong small {
    margin-left: 5px;
    color: #677582;
    font-size: 12px;
}

.team-overview-item p {
    margin: 0;
    color: #89949e;
    font-size: 11px;
}

.team-gap-workspace {
    display: grid;
    grid-template-columns: minmax(320px, 0.76fr) minmax(0, 1.55fr);
    min-height: 560px;
    overflow: hidden;
}

.team-member-panel {
    min-width: 0;
    border-right: 1px solid #dde4e8;
    background: #fbfcfc;
}

.team-member-head,
.team-competency-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 76px;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid #dde4e8;
    background: #fff;
}

.team-member-tools {
    display: grid;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid #e3e8eb;
}

.team-member-filter {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
}

.team-member-filter button {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 34px;
    padding: 6px 9px;
    border: 1px solid oklch(89% 0.012 165);
    border-radius: 7px;
    background: oklch(98.5% 0.005 165);
    color: oklch(50% 0.025 165);
    font: inherit;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
}

.team-member-filter button:hover,
.team-member-filter button.active {
    border-color: oklch(75% 0.07 165);
    background: oklch(94% 0.035 165);
    color: oklch(42% 0.1 165);
}

.team-member-filter button:focus-visible {
    outline: 2px solid oklch(55% 0.12 165);
    outline-offset: 1px;
}

.team-member-filter button span {
    display: grid;
    min-width: 22px;
    height: 22px;
    place-items: center;
    border-radius: 999px;
    background: oklch(91% 0.015 165);
    color: oklch(48% 0.03 165);
    font-size: 10px;
}

.team-member-filter button.active span {
    background: oklch(55% 0.12 165);
    color: oklch(98% 0.01 165);
}

.team-member-tools .team-gap-search {
    width: 100%;
}

.member-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(max-content, 1fr));
    overflow-x: auto;
}

.team-member-list {
    padding: 8px;
}

.team-member-row {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 66px;
    padding: 9px 10px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    color: var(--text);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 160ms ease-out, border-color 160ms ease-out;
}

.team-member-row + .team-member-row {
    margin-top: 3px;
}

.team-member-row:hover {
    background: #f0f6f4;
}

.team-member-row.selected {
    border-color: #acd0c5;
    background: #eaf5f1;
}

.team-member-row:focus-visible {
    outline: 2px solid #39725d;
    outline-offset: 1px;
}

.team-member-avatar {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 50%;
    background: #dcebe6;
    color: #245f50;
    font-size: 13px;
    font-weight: 900;
}

.team-member-copy {
    min-width: 0;
}

.team-member-copy strong,
.team-member-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.team-member-copy strong {
    color: #263b35;
    font-size: 13px;
}

.team-member-copy small {
    margin-top: 3px;
    color: #84909b;
    font-size: 11px;
}

.team-member-status {
    min-width: 86px;
    padding: 5px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-style: normal;
    font-weight: 900;
    text-align: center;
    white-space: nowrap;
}

.team-member-status.failed,
.team-selected-person em.failed {
    background: #fff0ee;
    color: #b63b2f;
}

.team-member-status.passed,
.team-selected-person em.passed {
    background: #eaf7f1;
    color: #207957;
}

.team-member-status.pending,
.team-selected-person em.pending {
    background: #f1f3f5;
    color: #6e7b86;
}

.team-member-status.completed {
    background: #eaf7f1;
    color: #207957;
}

.team-member-status.in-progress {
    background: #fff6e6;
    color: #b56a13;
}

.team-member-status.not-started {
    background: #f1f3f5;
    color: #6e7b86;
}

.team-member-status.needs-development {
    background: oklch(95% 0.035 30);
    color: oklch(50% 0.17 30);
}

.team-member-status.passed-all {
    background: oklch(95% 0.035 155);
    color: oklch(45% 0.13 155);
}

.compact-pagination {
    padding: 10px 14px;
}

.compact-pagination button {
    min-width: 34px;
}

.team-competency-panel {
    min-width: 0;
    background: #fff;
}

.team-no-selection {
    display: grid;
    place-items: center;
    color: oklch(58% 0.02 165);
    font-size: 13px;
}

.team-selected-person {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    color: #253a34;
    font-size: 16px;
    font-weight: 900;
}

.team-selected-person em {
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-style: normal;
}

.team-selected-metrics {
    display: flex;
    align-items: stretch;
    flex: 0 0 auto;
    overflow: hidden;
    border: 1px solid oklch(89% 0.012 165);
    border-radius: 9px;
    background: oklch(98.5% 0.004 165);
}

.team-selected-metrics > span {
    display: grid;
    grid-template-columns: auto auto;
    align-items: baseline;
    gap: 7px;
    padding: 9px 11px;
}

.team-selected-metrics > span + span {
    border-left: 1px solid oklch(89% 0.012 165);
}

.team-selected-metrics small {
    color: oklch(59% 0.018 165);
    font-size: 9px;
    font-weight: 700;
}

.team-selected-metrics strong {
    color: oklch(32% 0.025 165);
    font-size: 14px;
}

.team-selected-metrics .passed strong { color: oklch(47% 0.13 155); }
.team-selected-metrics .failed strong { color: oklch(52% 0.17 30); }
.team-selected-metrics .pending strong { color: oklch(50% 0.13 75); }

.team-competency-tools {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 18px;
    border-bottom: 1px solid #e3e8eb;
    background: #fbfcfc;
}

.team-competency-list {
    overflow-x: auto;
    padding: 0 18px;
}

.team-competency-list-head,
.team-competency-row {
    display: grid;
    grid-template-columns: minmax(230px, 1fr) 72px 72px 76px 82px;
    align-items: center;
    gap: 10px;
    min-width: 600px;
}

.team-competency-list-head {
    min-height: 44px;
    color: #7b8894;
    font-size: 11px;
    font-weight: 900;
}

.team-competency-row {
    min-height: 64px;
    border-top: 1px solid #e6eaed;
    color: #33453f;
    font-size: 12px;
}

.team-competency-row:hover {
    background: #fbfdfc;
}

.team-competency-row.pending {
    background: oklch(98% 0.005 165);
    color: oklch(57% 0.018 165);
}

.team-competency-row > .b {
    justify-self: start;
    width: auto;
    min-width: 0;
    padding-inline: 10px;
}

.team-competency-name {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.team-competency-name > div {
    min-width: 0;
}

.team-competency-name strong,
.team-competency-name small {
    display: block;
}

.team-competency-name strong {
    color: #273b35;
    font-size: 12px;
}

.team-competency-name small {
    margin-top: 2px;
    overflow: hidden;
    color: #687681;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.team-result-status {
    justify-self: start;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 900;
    white-space: nowrap;
}

.team-result-status.failed,
.team-result-status.revision {
    background: oklch(95% 0.035 30);
    color: oklch(50% 0.17 30);
}

.team-result-status.passed {
    background: oklch(95% 0.035 155);
    color: oklch(45% 0.13 155);
}

.team-result-status.pending {
    background: oklch(95% 0.035 75);
    color: oklch(48% 0.12 75);
}

.team-result-status.not-started {
    background: oklch(94% 0.008 165);
    color: oklch(51% 0.02 165);
}

.team-assessment-pending {
    display: grid;
    justify-items: center;
    max-width: 480px;
    margin: 90px auto;
    padding: 24px;
    color: #596873;
    text-align: center;
}

.team-pending-mark {
    display: grid;
    width: 52px;
    height: 52px;
    margin-bottom: 16px;
    place-items: center;
    border-radius: 50%;
    background: #eef2f2;
    color: #71807c;
    font-size: 24px;
    font-weight: 900;
}

.team-assessment-pending strong {
    color: #263b35;
    font-size: 16px;
}

.team-assessment-pending p {
    margin: 8px 0 0;
    color: #83909a;
    font-size: 12px;
    line-height: 1.7;
}

.team-gap-search {
    display: flex;
    align-items: center;
    gap: 9px;
    width: min(390px, 100%);
    min-height: 42px;
    padding: 0 13px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    color: var(--text3);
}

.team-gap-search:focus-within {
    border-color: #388a76;
    box-shadow: 0 0 0 3px rgba(56, 138, 118, 0.12);
}

.team-gap-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text);
    font: inherit;
    font-size: 13px;
}

.team-gap-search.compact {
    width: min(320px, 100%);
}

.team-gap-filters {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: #fff;
}

.team-gap-filters button,
.team-pagination button {
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    color: var(--text2);
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.team-gap-filters button {
    padding: 7px 11px;
}

.team-gap-filters button:hover,
.team-gap-filters button.active {
    background: #edf7f3;
    color: #216d5a;
}

.team-gap-empty {
    padding: 50px 22px;
    color: var(--text3);
    font-size: 13px;
    text-align: center;
}

.team-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 22px;
    border-top: 1px solid var(--border);
    color: var(--text3);
    font-size: 12px;
}

.team-pagination > div {
    display: flex;
    gap: 6px;
}

.team-pagination button {
    padding: 7px 11px;
    border-color: var(--border);
    background: #fff;
}

.team-pagination button:hover:not(:disabled) {
    border-color: #9bc8bc;
    color: #216d5a;
}

.team-pagination button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.team-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
}

.team-table-wrap {
    overflow-x: auto;
    padding: 20px 22px;
}

.team-table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
}

.team-table th,
.team-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    font-size: 13px;
    text-align: left;
    vertical-align: middle;
}

.team-table th {
    background: #f8fafc;
    color: var(--text3);
    font-size: 12px;
    font-weight: 900;
}

.team-table tbody tr:last-child td {
    border-bottom: 0;
}

.heatmap-table tbody tr {
    cursor: pointer;
}

.heatmap-table tbody tr:hover {
    background: var(--blue-lt);
}

.heatmap-table tbody tr.disabled {
    cursor: default;
    opacity: 0.56;
}

.approval-table tbody tr {
    cursor: pointer;
}

.approval-table tbody tr:hover {
    background: var(--blue-lt);
}

.approval-table tbody tr.selected {
    background: #eff6ff;
}

.approval-table tbody tr.disabled {
    cursor: default;
    opacity: 0.62;
}

.approval-result-table {
    padding: 18px 22px;
}

.approval-result-row {
    display: grid;
    grid-template-columns: minmax(220px, 1.4fr) 90px 90px 90px 90px minmax(220px, 1.2fr);
    gap: 12px;
    align-items: center;
    min-height: 58px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.approval-result-head {
    min-height: 44px;
    background: #f8fafc;
    color: var(--text3);
    font-size: 12px;
    font-weight: 900;
}

.approval-note {
    color: var(--text2);
    line-height: 1.45;
    white-space: pre-wrap;
}

.approval-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 22px 20px;
    border-top: 1px solid var(--border);
}

.approval-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.34);
    overflow-y: auto;
}

.approval-modal {
    width: min(1080px, 100%);
    max-height: calc(100vh - 36px);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-direction: column;
}

.fc-topic-decision-modal {
    width: min(560px, 100%);
    max-height: calc(100vh - 36px);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-direction: column;
}

.approval-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.approval-modal-body {
    padding: 20px;
    overflow-y: auto;
}

.competency-detail-backdrop {
    position: fixed;
    z-index: 1250;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgba(15, 20, 25, 0.7);
}

.competency-detail-modal {
    display: flex;
    flex-direction: column;
    width: min(920px, 100%);
    max-height: calc(100vh - 48px);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 28px 80px rgba(15, 23, 42, 0.34);
}

.competency-detail-head {
    display: flex;
    flex: 0 0 auto;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 1px solid var(--border);
    padding: 20px 24px;
}

.competency-detail-head h2 {
    margin: 8px 0 2px;
    font-size: 20px;
}

.competency-detail-head p {
    margin: 0;
    color: var(--text3);
    font-size: 12px;
}

.competency-current-status {
    display: inline-flex;
    margin-top: 10px;
    max-width: 100%;
    white-space: normal;
}

.competency-detail-body {
    display: block;
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    background: #f8fafc;
    padding: 20px 24px;
}

.competency-detail-body > * + * {
    margin-top: 14px;
}

.competency-detail-body > .approval-comment-box {
    margin-right: 0;
    margin-bottom: 0;
    margin-left: 0;
}

.competency-detail-body > .detail-level-card + .approval-comment-box {
    margin-top: 24px;
}

.assessment-workflow-panel {
    border: 1px solid #dbe3ea;
    border-radius: 10px;
    background: #fff;
    padding: 16px;
}

.assessment-review-history {
    border: 1px solid #d9e3ec;
    border-radius: 8px;
    background: #f8fafc;
    padding: 12px 16px;
}

.assessment-review-history summary {
    color: #344054;
    font-size: 15px;
    font-weight: 900;
    cursor: pointer;
}

.assessment-history-row {
    display: grid;
    gap: 5px;
    padding: 14px 0;
    border-top: 1px solid #e2e7ec;
}

.assessment-history-row:first-of-type {
    margin-top: 12px;
}

.assessment-history-row strong {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    color: var(--text);
    font-size: 15px;
}

.assessment-history-row > span,
.assessment-history-row p {
    margin: 0;
    color: var(--text3);
    font-size: 14px;
}

.assessment-review-decision {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 2px 10px;
    border: 1px solid;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
    line-height: 1;
}

.assessment-review-decision.is-approved {
    border-color: #abefc6;
    background: #ecfdf3;
    color: #067647;
}

.assessment-review-decision.is-returned {
    border-color: #fecdca;
    background: #fef3f2;
    color: #b42318;
}

.assessment-workflow-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.assessment-workflow-title strong {
    font-size: 14px;
}

.assessment-workflow-title span {
    color: var(--text3);
    font-size: 11px;
}

.assessment-workflow-list {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.assessment-workflow-list li {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    min-width: 0;
    border: 1px solid #e5eaf0;
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px 12px;
}

.assessment-workflow-marker {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    font-size: 10px;
    font-weight: 900;
}

.assessment-workflow-list li > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.assessment-workflow-list li strong {
    font-size: 12px;
}

.assessment-workflow-list li small {
    overflow: hidden;
    color: var(--text3);
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-workflow-list li > b {
    color: #64748b;
    font-size: 11px;
    white-space: nowrap;
}

.assessment-workflow-list li.is-complete {
    border-color: #bbf7d0;
    background: #f0fdf4;
}

.assessment-workflow-list li.is-complete .assessment-workflow-marker {
    background: #16a34a;
    color: #fff;
}

.assessment-workflow-list li.is-complete > b { color: #15803d; }

.assessment-workflow-list li.is-active {
    border-color: #fed7aa;
    background: #fff7ed;
}

.assessment-workflow-list li.is-active .assessment-workflow-marker {
    background: #ea580c;
    color: #fff;
}

.assessment-workflow-list li.is-active > b { color: #c2410c; }

.assessment-workflow-list li.is-returned {
    border-color: #fecaca;
    background: #fff7f7;
}

.assessment-workflow-list li.is-returned .assessment-workflow-marker {
    background: #dc2626;
    color: #fff;
}

.assessment-workflow-list li.is-returned > b { color: #b91c1c; }

.detail-level-card {
    min-height: max-content;
    flex-shrink: 0;
    overflow: hidden;
    border: 1px solid #e1d8d2;
    border-radius: 10px;
    background: #fff;
}

.detail-level-card > header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-height: 58px;
    border-bottom: 1px solid #eadfd9;
    background: #fff3ed;
    padding: 12px 16px;
}

.detail-level-card > header > div {
    display: grid;
    gap: 3px;
}

.detail-level-card > header strong {
    color: #b93d28;
    font-size: 14px;
}

.detail-level-card > header span {
    color: #8d817a;
    font-size: 11px;
}

.detail-level-card > header b {
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    padding: 5px 10px;
    font-size: 11px;
}

.detail-level-card > header b.complete { border-color: #bbf7d0; background: #ecfdf5; color: #047857; }
.detail-level-card > header b.partial { border-color: #fed7aa; background: #fff7ed; color: #b45309; }
.detail-indicator-list { display: grid; padding: 10px 12px; }
.detail-indicator-list > div { display: grid; grid-template-columns: 20px minmax(0, 1fr); gap: 10px; border: 1px solid #edf0f2; border-radius: 8px; background: #fff; padding: 10px 12px; color: #8c949d; }
.detail-indicator-list > div + div { margin-top: 7px; }
.detail-indicator-list > div.selected { border-color: #f2b7a9; background: #fff8f5; color: #302b28; }
.detail-indicator-list > div > span { display: grid; place-items: center; width: 18px; height: 18px; border: 1px solid #d6dbe0; border-radius: 5px; color: #fff; font-size: 11px; font-weight: 900; }
.detail-indicator-list > div.selected > span { border-color: var(--color-primary); background: var(--color-primary); }
.detail-indicator-list p { margin: 0; font-size: 12px; line-height: 1.6; }
.detail-indicator-list p strong { display: inline-block; margin-right: 8px; color: inherit; }

.competency-detail-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-top: 1px solid var(--border);
    padding: 14px 20px;
}

.competency-detail-actions > span {
    color: var(--text3);
    font-size: 11px;
}

.fc-topic-list,
.fc-topic-review-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.fc-topic-chip {
    display: inline-flex;
    max-width: 100%;
    border: 1px solid #fed7aa;
    border-radius: 999px;
    background: #fff7ed;
    color: #9a3412;
    padding: 5px 9px;
    font-size: 12px;
    font-weight: 800;
}

.fc-topic-decision-body {
    display: grid;
    gap: 14px;
    padding: 18px 20px 20px;
}

.fc-topic-comment {
    min-height: 110px;
    resize: vertical;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 12px;
    font: inherit;
}

.approval-score-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}

.approval-score-card {
    min-height: 100px;
    padding: 16px 18px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #fff;
}

.approval-score-card.disabled {
    background: #f8fafc;
    color: var(--text3);
    opacity: 0.72;
}

.approval-score-gap {
    margin: 4px 0;
    color: var(--green);
    font-size: 25px;
    font-weight: 900;
    line-height: 1.15;
}

.approval-score-pending {
    margin: 8px 0 6px;
    color: var(--text3);
    font-size: 13px;
    font-weight: 800;
}

.approval-score-gap.bad {
    color: var(--red);
}

.approval-score-gap.ok {
    color: var(--green);
}

.approval-competency-card {
    overflow: hidden;
    margin-top: 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #fff;
}

.approval-competency-card.disabled {
    border-color: #e2e8f0;
    background: #f8fafc;
}

.approval-competency-head {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: #f8fafc;
    color: var(--text);
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    flex-wrap: wrap;
}

.approval-competency-head:hover,
.approval-competency-head.open {
    background: #eff6ff;
}

.approval-competency-head.disabled,
.approval-competency-head:disabled,
.approval-competency-head.disabled:hover {
    background: #f8fafc;
    color: var(--text3);
    cursor: not-allowed;
}

.approval-competency-head.disabled .approval-accordion-icon,
.approval-competency-head:disabled .approval-accordion-icon {
    background: #e2e8f0;
    color: var(--text3);
}

.approval-accordion-icon {
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: var(--blue);
    font-size: 11px;
    font-weight: 900;
}

.approval-accordion-body {
    display: grid;
    gap: 12px;
    padding: 16px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.approval-level-card {
    overflow: hidden;
    border: 1px solid #dbe5f0;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
}

.approval-level-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #dbe5f0;
    background: linear-gradient(90deg, #f8fafc 0%, #ecfeff 100%);
}

.approval-level-state {
    flex: 0 0 auto;
    padding: 5px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
}

.approval-level-state.complete {
    border-color: #bbf7d0;
    background: #ecfdf5;
    color: #047857;
}

.approval-level-state.partial {
    border-color: #fed7aa;
    background: #fff7ed;
    color: #b45309;
}

.approval-checklist {
    display: grid;
    gap: 8px;
    padding: 12px;
    background: #fff;
}

.approval-check-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #e5edf6;
    border-radius: 8px;
    background: #f8fafc;
    color: var(--text);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
}

.approval-check-row.selected {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #172033;
}

.approval-check-row input {
    width: 14px;
    height: 14px;
    margin-top: 3px;
    flex: 0 0 auto;
    accent-color: #2563eb;
}

.approval-check-row.unchecked,
.approval-check-row.muted {
    border-color: #edf2f7;
    background: #fff;
    color: var(--text3);
    font-weight: 500;
}

.approval-comment-box {
    margin: 0 16px 16px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: #f8fafc;
}

.approval-accordion-body .approval-comment-box {
    margin: 0;
}

.approval-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 2px;
}

.approval-check-row strong {
    color: #172033;
    font-weight: 900;
}

.approval-check-row.muted strong {
    color: #64748b;
}

.approval-check-copy {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.approval-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px 18px;
    border-top: 1px solid var(--border);
    background: #fff;
    flex-shrink: 0;
}

.approval-decision-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1300;
    display: grid;
    place-items: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.46);
}

.approval-decision-modal {
    width: min(460px, 100%);
    padding: 22px 24px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.approval-decision-title {
    color: var(--text);
    font-size: 17px;
    font-weight: 900;
}

.approval-decision-message {
    margin-top: 10px;
    color: var(--text2);
    font-size: 13px;
    line-height: 1.65;
}

.approval-decision-warning {
    margin-top: 12px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff7f7;
    color: #b42318;
    padding: 9px 11px;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.5;
}

.approval-reviewer-comment {
    display: grid;
    gap: 8px;
    margin-top: 14px;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    background: #f8fbff;
    padding: 12px;
}

.approval-reviewer-comment span {
    color: var(--text);
    font-size: 12px;
    font-weight: 900;
}

.approval-reviewer-comment textarea {
    width: 100%;
    min-height: 86px;
    resize: vertical;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--text);
    padding: 11px 12px;
    font-size: 13px;
    line-height: 1.6;
    outline: none;
}

.approval-reviewer-comment textarea:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

.approval-reviewer-comment textarea:disabled {
    background: #f1f5f9;
    color: var(--text3);
}

.approval-decision-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.approval-decision-actions .btn {
    min-width: 96px;
    opacity: 1;
    visibility: visible;
}

.approval-confirm-button {
    border: 1px solid var(--color-primary);
    background: var(--color-primary);
    color: #fff;
}

.approval-confirm-button:hover:not(:disabled) {
    border-color: var(--color-primary-hover);
    background: var(--color-primary-hover);
    color: #fff;
}

.approval-reject-button {
    border: 1px solid #dc2626;
    background: #dc2626;
    color: #fff;
}

.approval-reject-button:hover:not(:disabled) {
    border-color: #991b1b;
    background: #991b1b;
    color: #fff;
}

.approval-decision-actions .btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.person-cell {
    display: grid;
    gap: 4px;
}

.person-cell strong {
    color: var(--text);
    font-size: 13px;
    font-weight: 900;
}

.person-cell small {
    color: var(--text3);
    font-size: 12px;
}

.gap-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 22px;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 0 7px;
    font-size: 11px;
    font-weight: 900;
}

.gap-chip::before {
    margin-right: 3px;
    font-size: 9px;
    line-height: 1;
}

.gap-chip.bad {
    border-color: #b45309;
    background: #fff7ed;
    color: #9a3412;
}

/* .gap-chip.bad::before {
    content: '!';
} */

.gap-chip.ok {
    border-color: var(--blue);
    background: var(--blue-lt);
    color: var(--blue);
}

/* .gap-chip.ok::before {
    content: '=';
} */

.idp-row-action {
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--navy);
    font-weight: 800;
}

.idp-row-action:hover {
    border-color: var(--navy);
    background: var(--navy);
    color: #fff;
}

.idp-row-action.primary {
    border-color: var(--blue);
    background: #fff;
    color: var(--blue);
}

.idp-row-action.primary:hover {
    background: var(--blue);
    color: #fff;
}

.assess-summary {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.team-row,
.gap-person {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    margin-bottom: 10px;
}

.team-row.compact {
    margin: 0;
    border-bottom: 1px solid var(--border);
    border-radius: 0;
    box-shadow: none;
}

.row-avatar {
    width: 44px;
    height: 44px;
    background: var(--navy);
    font-size: 16px;
    flex-shrink: 0;
}

.mutedAvatar {
    background: var(--text3);
}

.row-main {
    flex: 1;
    min-width: 0;
}

.team-grid,
.assess-grid {
    grid-template-columns: 1fr 1fr;
    align-items: stretch;
}

.assessment-workspace {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.idp-detail-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.idp-profile-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 20px 26px;
    border-left: 5px solid var(--red);
    flex-wrap: wrap;
}

.idp-detail-avatar {
    width: 56px;
    height: 56px;
    background: var(--navy);
    font-size: 16px;
}

.idp-gap-detail-card {
    overflow: hidden;
}

.idp-gap-detail-head {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.idp-gap-list {
    padding: 18px 22px;
}

.idp-gap-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px dashed var(--border);
}

.idp-gap-row:last-child {
    border-bottom: 0;
}

.idp-review-space {
    display: grid;
    gap: 14px;
}

.idp-review-card {
    overflow: hidden;
}

.idp-review-head,
.idp-progress-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.idp-review-body {
    padding: 20px 22px;
}

.idp-goal-box {
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg);
    color: var(--text3);
    font-size: 13px;
}

.idp-feedback-input {
    min-height: 74px;
}

.idp-review-actions {
    display: flex;
    justify-content: flex-end;
    padding: 4px 0 0;
}

.idp-review-body .btn.selected {
    box-shadow: 0 0 0 2px var(--blue-md);
}

.idp-progress-section {
    border-bottom: 1px solid var(--border);
}

.idp-progress-section:last-child {
    border-bottom: 0;
}

.idp-activity-card {
    margin: 14px 16px;
    padding: 16px 18px;
    border-left: 4px solid var(--green);
    background: #fbfdff;
}

.idp-activity-card + .idp-activity-card {
    margin-top: 0;
}

.idp-activity-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

.idp-event-row {
    display: grid;
    grid-template-columns: 70px minmax(0, 1fr) 190px;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.idp-event-row:last-child {
    border-bottom: 0;
}

.assess-list-card {
    position: sticky;
    top: 10px;
    overflow: hidden;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
}

.assess-person {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: #fff;
    text-align: left;
    font-family: inherit;
    cursor: pointer;
}

.assess-person:hover,
.assess-person.selected {
    background: var(--blue-lt);
}

.assess-person.selected {
    box-shadow: inset 4px 0 0 var(--blue);
}

.assessment-detail {
    min-width: 0;
}

.assessment-group-title {
    color: var(--blue);
    font-size: 13px;
    font-weight: 800;
    margin: 10px 0 14px;
}

.assessment-card {
    overflow: hidden;
    margin-bottom: 18px;
}

.assessment-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    background: #fff;
}

.assessment-columns {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
}

.assessment-side {
    padding: 22px 20px;
}

.assessment-side.self {
    border-right: 1px solid var(--border);
}

.behavior-list {
    margin: 0 0 18px 18px;
    padding: 0;
    font-size: 12px;
    line-height: 1.7;
}

.hint-box {
    padding: 10px 12px;
    margin-bottom: 18px;
    background: var(--blue-lt);
    border-radius: 8px;
    color: var(--blue);
    font-size: 11px;
}

.score-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 7px;
    margin-bottom: 18px;
}

.score-tile {
    min-height: 54px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: #fff;
    color: var(--text3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: inherit;
}

.score-tile.selected {
    border-color: var(--teal);
    background: var(--teal-lt);
    color: var(--teal);
}

.score-tile span {
    font-weight: 800;
    font-size: 15px;
}

.score-tile small {
    font-size: 10px;
}

.score-button {
    cursor: pointer;
}

.evidence-grid {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) 1fr;
    gap: 14px;
    align-items: center;
}

.evidence-file {
    height: 80px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.file-icon {
    font-size: 18px;
    opacity: 0.55;
}

.feedback-box {
    min-height: 80px;
    font-size: 13px;
    border-radius: 10px;
}

.approval-flow {
    border-top: 1px solid var(--border);
    margin-top: 24px;
    padding-top: 16px;
}

.flow-step {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.flow-dot {
    background: var(--blue-lt);
    color: var(--blue);
    font-size: 10px;
}

.gap-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px;
    border-bottom: 1px solid var(--border);
}

.gap-main {
    flex: 1;
}

.gap-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.red-bar {
    width: 50%;
    background: var(--red);
}

.gap-person-button {
    width: 100%;
    border: 0;
    background: #fff;
    text-align: left;
    font-family: inherit;
    cursor: pointer;
}

.gap-person-button:hover:not(:disabled) {
    background: var(--blue-lt);
}

.gap-person-button:disabled {
    cursor: not-allowed;
}

.gap-detail-profile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 34px 32px;
    min-height: 146px;
    flex-wrap: wrap;
}

.gap-detail-avatar {
    width: 74px;
    height: 74px;
    background: var(--navy);
    font-size: 18px;
    flex-shrink: 0;
}

.gap-result-card {
    overflow: hidden;
}

.gap-result-table {
    width: 100%;
    overflow-x: auto;
}

.gap-result-row {
    display: grid;
    grid-template-columns: minmax(220px, 1.6fr) minmax(90px, 0.7fr) repeat(5, minmax(120px, 1fr));
    align-items: center;
    min-width: 980px;
    border-top: 1px solid var(--border);
}

.gap-result-row > div {
    padding: 18px 16px;
}

.gap-result-head {
    background: #f8fafc;
    color: var(--text3);
    font-size: 12px;
    font-weight: 900;
}

.gap-result-body:hover {
    background: var(--blue-lt);
}

.score-pill {
    width: 52px;
    height: 52px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 900;
}

.score-pill.navy {
    background: var(--navy);
    color: #fff;
}

.score-pill.blue {
    background: var(--blue-lt);
    color: var(--blue);
}

.score-pill.evaluator {
    border: 1px solid #fed7aa;
    background: #fff7ed;
    color: #c2410c;
}

.tag-fc {
    background: var(--green-bg);
    color: var(--green);
}

.suggestion-block {
    padding: 22px;
    border-top: 1px solid var(--border);
}

.suggestion-note {
    margin-top: 8px;
    padding: 14px 16px;
    border-radius: 8px;
    background: var(--blue-lt);
    font-size: 13px;
}

.suggestion-block.dept .suggestion-note {
    background: var(--teal-lt);
}

.empty-card,
.empty-panel {
    text-align: center;
    padding: 42px 24px;
}

.empty-panel {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.target-icon {
    font-size: 46px;
    margin-bottom: 14px;
}

.g5 {
    display: grid;
    gap: 14px;
}

.filter-select {
    padding: 7px 12px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    background: #fff;
    color: var(--text1);
    cursor: pointer;
    min-width: 140px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--blue);
}

.mb14 {
    margin-bottom: 14px;
}

.btn-t {
    background: var(--teal, #0d9488) !important;
    color: #fff !important;
    border: none;
}

.btn-t:hover:not(:disabled) {
    background: var(--navy, #1e3a5f) !important;
    color: #fff !important;
}

.btn-t:disabled {
    background: var(--border, #e2e8f0) !important;
    color: var(--text3, #94a3b8) !important;
    cursor: not-allowed;
    opacity: 1 !important;
}
.supervisor-approval-card .approval-table-wrap { padding: 12px 16px 16px; }
.supervisor-approval-card .approval-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; border: 1px solid #dce3ea; border-radius: 8px; overflow: hidden; }
.supervisor-approval-card .approval-table th { padding: 11px 16px; background: #f1f4f7; color: #657287; font-size: 12px; }
.supervisor-approval-card .approval-table th:first-child { width: 32%; }
.supervisor-approval-card .approval-table td { padding: 16px; background: #fff; vertical-align: middle; border-top: 1px solid #e4e9ee; }
.supervisor-approval-card .approval-table tbody tr:nth-child(even) td { background: #fafbfc; }
.supervisor-approval-card .approval-table tbody tr:hover td { background: #f0f7f4; }
.supervisor-approval-card .person-cell small { margin-top: 5px; color: var(--color-text-muted); font-size: 12px; }
.review-person-link { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; border: 0; padding: 0; background: transparent; color: #263d35; text-align: left; font: inherit; font-weight: 800; cursor: pointer; }
.review-person-link:focus-visible { outline: 2px solid #39725d; outline-offset: 4px; }
@media (max-width: 900px) { .supervisor-approval-card .approval-table { min-width: 700px; } }
.idp-team-head { margin-bottom: 14px; }
.idp-workspace { overflow: hidden; border: 1px solid #d5dfdb; border-radius: 12px; background: #fff; box-shadow: 0 5px 20px rgba(35, 67, 57, .05); }
.idp-mode-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0; border-bottom: 1px solid #dce5e1; background: #f6f8f7; }
.idp-mode-tabs button { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 20px; min-height: 76px; border: 0; background: transparent; padding: 14px 22px; color: #65736d; font: inherit; text-align: left; cursor: pointer; transition: background-color .18s ease, color .18s ease; }
.idp-mode-tabs button + button { border-left: 1px solid #dce5e1; }
.idp-mode-tabs button::after { position: absolute; right: 0; bottom: -1px; left: 0; height: 3px; background: transparent; content: ''; }
.idp-mode-tabs button:hover { background: #eef5f2; color: #2d5145; }
.idp-mode-tabs button.active { background: #fff; color: #1f6956; }
.idp-mode-tabs button.active::after { background: var(--color-primary); }
.idp-mode-tabs button:focus-visible { z-index: 1; outline: 3px solid var(--color-focus); outline-offset: -3px; }
.idp-tab-copy { display: grid; gap: 3px; }
.idp-tab-copy strong { font-size: 16px; line-height: 1.35; }
.idp-tab-copy small { color: #7a8782; font-size: 12px; font-weight: 600; line-height: 1.45; }
.idp-tab-meta { display: flex; align-items: center; gap: 8px; }
.idp-tab-pending { border-radius: 16px; background: #fff0cf; padding: 6px 9px; color: #925b08; font-size: 11px; font-weight: 900; white-space: nowrap; }
.idp-tab-overdue { border-radius: 16px; background: #fff0ed; padding: 6px 9px; color: #ad3929; font-size: 11px; font-weight: 900; white-space: nowrap; }
.idp-tab-count { display: grid; flex: 0 0 auto; place-items: center; min-width: 32px; height: 32px; border-radius: 16px; background: #e5ebe8; padding: 0 9px; color: #5f6d67; font-size: 13px; font-weight: 900; }
.idp-tab-count.pending { background: #fff0cf; color: #925b08; }
.idp-mode-tabs button.active .idp-tab-count { background: #dff1ea; color: #176d57; }
.idp-mode-tabs button.active .idp-tab-count.pending { background: #ffedc2; color: #8b5405; }
.idp-tab-panel { padding: 16px; background: #f8faf9; }
.idp-tab-panel > :first-child { margin-top: 0; }
.idp-tab-panel > :last-child { margin-bottom: 0; }
.assessment-approval-hub {
    display: block;
}
.assessment-hub-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 8px 2px 22px;
    border-bottom: 1px solid oklch(88% .012 165);
}
.assessment-hub-copy > span {
    display: block;
    margin-bottom: 4px;
    color: oklch(49% .09 165);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .04em;
}
.assessment-hub-copy h1 {
    margin: 0;
    color: oklch(29% .018 165);
    font-size: 26px;
    line-height: 1.3;
}
.assessment-hub-copy p {
    max-width: 68ch;
    margin: 5px 0 0;
    color: oklch(57% .018 165);
    font-size: 14px;
}
.assessment-queue-status {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 9px;
    min-height: 38px;
    border: 1px solid oklch(86% .04 75);
    border-radius: 999px;
    background: oklch(97% .025 80);
    padding: 8px 13px;
    color: oklch(46% .09 67);
    font-size: 12px;
}
.assessment-queue-status > span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: oklch(68% .15 67);
    box-shadow: 0 0 0 4px oklch(92% .06 75);
}
.assessment-queue-status.clear {
    border-color: oklch(86% .035 155);
    background: oklch(97% .025 155);
    color: oklch(44% .075 155);
}
.assessment-queue-status.clear > span {
    background: oklch(62% .13 155);
    box-shadow: 0 0 0 4px oklch(91% .05 155);
}
.assessment-hub-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    margin: 0 0 22px;
    border: 1px solid oklch(87% .014 165);
    border-radius: 12px;
    background: oklch(95.5% .012 165);
    padding: 6px;
}
.assessment-hub-tabs button {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    align-items: center;
    gap: 11px;
    min-height: 66px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    padding: 10px 12px;
    color: oklch(52% .015 165);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: border-color .18s cubic-bezier(.25, 1, .5, 1), background-color .18s cubic-bezier(.25, 1, .5, 1), box-shadow .18s cubic-bezier(.25, 1, .5, 1), color .18s cubic-bezier(.25, 1, .5, 1);
}
.assessment-hub-tabs button:hover {
    background: oklch(97.5% .01 165);
    color: oklch(38% .055 165);
}
.assessment-hub-tabs button.active {
    border-color: oklch(82% .045 165);
    background: oklch(99% .004 165);
    box-shadow: 0 3px 10px oklch(32% .025 165 / .08);
    color: oklch(42% .095 165);
}
.assessment-hub-tabs button:focus-visible {
    outline: 3px solid oklch(76% .08 165 / .38);
    outline-offset: 2px;
}
.assessment-hub-tabs button > i {
    display: grid;
    width: 32px;
    height: 32px;
    place-items: center;
    border-radius: 8px;
    background: oklch(90.5% .012 165);
    color: oklch(53% .02 165);
    font-size: 12px;
    font-style: normal;
    font-weight: 900;
}
.assessment-hub-tabs button.active > i {
    background: oklch(50% .105 165);
    color: oklch(98% .004 165);
}
.assessment-hub-tabs button > span {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.assessment-hub-tabs button strong {
    overflow: hidden;
    font-size: 14px;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.assessment-hub-tabs button b {
    display: grid;
    min-width: 32px;
    height: 32px;
    place-items: center;
    padding: 0 8px;
    border-radius: 999px;
    background: oklch(90.5% .012 165);
    color: oklch(52% .018 165);
    font-size: 12px;
}
.assessment-hub-tabs button.active b {
    background: oklch(91.5% .05 165);
    color: oklch(42% .095 165);
}
.assessment-hub-tabs button small {
    color: oklch(62% .014 165);
    font-size: 11px;
    font-weight: 600;
}
.assessment-results-overview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    margin-bottom: 18px;
    border: 1px solid oklch(88% .012 165);
    border-radius: 12px;
    background: oklch(98% .008 165);
    padding: 16px 20px;
}
.assessment-results-overview h2 {
    margin: 0;
    color: oklch(31% .018 165);
    font-size: 17px;
}
.assessment-results-overview p {
    margin: 4px 0 0;
    color: oklch(59% .014 165);
    font-size: 12px;
}
.assessment-status-summary {
    display: flex;
    flex: 0 0 auto;
    margin: 0;
}
.assessment-status-summary > div {
    display: grid;
    grid-template-columns: auto auto;
    align-items: baseline;
    min-width: 125px;
    padding: 2px 18px;
    border-left: 1px solid oklch(88% .012 165);
}
.assessment-status-summary dt {
    grid-column: 1 / -1;
    color: oklch(58% .016 165);
    font-size: 11px;
    font-weight: 800;
}
.assessment-status-summary dd {
    margin: 2px 5px 0 0;
    color: oklch(42% .04 165);
    font-size: 21px;
    font-weight: 900;
    line-height: 1;
}
.assessment-status-summary small {
    color: oklch(62% .014 165);
    font-size: 10px;
}
.assessment-status-summary .pending dd { color: oklch(55% .13 67); }
.assessment-status-summary .complete dd { color: oklch(49% .11 150); }
.assessment-list-section {
    overflow: hidden;
    margin-bottom: 16px;
    border: 1px solid oklch(87% .012 165);
    border-radius: 12px;
    background: oklch(99% .004 165);
}
.assessment-list-section.secondary { background: oklch(98% .006 165); }
.assessment-list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 15px 18px;
    border-bottom: 1px solid oklch(88% .012 165);
}
.assessment-list-head h3 { margin: 0; color: oklch(31% .018 165); font-size: 15px; }
.assessment-list-head p { margin: 3px 0 0; color: oklch(61% .014 165); font-size: 11px; }
.assessment-list-head > span {
    flex: 0 0 auto;
    border-radius: 999px;
    background: oklch(94% .012 165);
    padding: 5px 9px;
    color: oklch(49% .025 165);
    font-size: 10px;
    font-weight: 900;
}
.assessment-list-section .approval-table-wrap { padding: 0; }
.assessment-list-section .approval-table { min-width: 760px; table-layout: fixed; border-collapse: collapse; }
.assessment-list-section .approval-table th { background: oklch(96.5% .008 165); color: oklch(55% .015 165); font-size: 11px; }
.assessment-list-section .approval-table th:first-child { width: 30%; }
.assessment-list-section .approval-table th:last-child { width: 118px; }
.assessment-list-section .approval-table td { background: transparent; padding-block: 15px; }
.assessment-list-section .approval-table tbody tr:hover td { background: oklch(97% .02 165); }
.assessment-list-section .person-cell small { margin-top: 4px; color: oklch(61% .014 165); font-size: 11px; }
.assessment-row-action {
    min-height: 36px;
    border: 1px solid oklch(55% .09 165);
    border-radius: 7px;
    background: oklch(50% .105 165);
    padding: 7px 12px;
    color: oklch(98% .004 165);
    font: inherit;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color .18s cubic-bezier(.25, 1, .5, 1), border-color .18s cubic-bezier(.25, 1, .5, 1);
}
.assessment-row-action:hover { border-color: oklch(43% .1 165); background: oklch(43% .1 165); }
.assessment-row-action.secondary { border-color: oklch(82% .02 165); background: oklch(98% .004 165); color: oklch(43% .065 165); }
.assessment-row-action.secondary:hover { border-color: oklch(62% .055 165); background: oklch(96% .02 165); }
.assessment-row-action:focus-visible { outline: 3px solid oklch(76% .08 165 / .38); outline-offset: 2px; }
.assessment-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 13px;
    min-height: 110px;
    padding: 22px;
    text-align: left;
}
.assessment-empty-state > span {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 50%;
    background: oklch(92% .045 155);
    color: oklch(47% .1 155);
    font-weight: 900;
}
.assessment-empty-state strong { color: oklch(36% .018 165); font-size: 13px; }
.assessment-empty-state p { margin: 3px 0 0; color: oklch(62% .014 165); font-size: 11px; }
.assessment-empty-state.muted-state > span { background: oklch(93% .01 165); color: oklch(58% .015 165); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
@media (max-width: 900px) {
    .assessment-hub-header {
        align-items: flex-start;
        padding-top: 2px;
    }
    .assessment-results-overview {
        align-items: stretch;
        flex-direction: column;
        gap: 14px;
    }
    .assessment-status-summary {
        border-top: 1px solid oklch(88% .012 165);
        padding-top: 14px;
    }
    .assessment-status-summary > div:first-child { border-left: 0; padding-left: 0; }
    .assessment-list-section .approval-table { min-width: 720px; }
    .assessment-hub-tabs {
        gap: 5px;
    }
    .idp-mode-tabs button { min-height: 68px; padding: 12px 14px; }
    .idp-tab-copy strong { font-size: 15px; }
    .idp-tab-copy small { display: none; }
    .idp-tab-pending { padding: 5px 7px; font-size: 10px; }
    .idp-tab-panel { padding: 10px; }
}
@media (max-width: 620px) {
    .assessment-hub-header {
        align-items: stretch;
        flex-direction: column;
        gap: 16px;
    }
    .assessment-queue-status { align-self: flex-start; }
    .assessment-hub-tabs { grid-template-columns: 1fr; }
    .assessment-status-summary { display: grid; grid-template-columns: repeat(3, 1fr); }
    .assessment-status-summary > div { min-width: 0; padding-inline: 10px; }
    .assessment-list-head { align-items: flex-start; }
    .idp-mode-tabs { grid-template-columns: none; grid-auto-columns: minmax(185px, 72vw); grid-auto-flow: column; overflow-x: auto; }
    .idp-mode-tabs button + button { border-left: 1px solid #dce5e1; }
}
.navy-top { border-top: 3px solid var(--navy); }
.blue-top { border-top: 3px solid var(--blue); }
.red-top { border-top: 3px solid var(--red); }
.yellow-top { border-top: 3px solid var(--yellow); }
.green-top { border-top: 3px solid var(--green); }
@media (max-width: 1200px) {
    .team-competency-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .team-selected-metrics {
        max-width: 100%;
    }
}
@media (max-width: 900px) {
    .team-dashboard-head {
        align-items: stretch;
        flex-direction: column;
        gap: 14px;
    }

    .team-round-context {
        width: 100%;
        min-width: 0;
    }

    .team-overview-strip {
        grid-template-columns: 1fr;
    }

    .team-overview-item + .team-overview-item {
        border-left: 0;
        border-top: 1px solid #e3e8eb;
    }

    .team-gap-workspace {
        grid-template-columns: 1fr;
    }

    .team-member-panel {
        border-right: 0;
        border-bottom: 1px solid #dde4e8;
    }

    .team-member-list {
        max-height: 340px;
        overflow-y: auto;
    }

    .team-competency-tools {
        align-items: stretch;
        flex-direction: column;
    }

    .team-gap-search,
    .team-gap-search.compact {
        width: 100%;
    }

    .team-gap-filters {
        overflow-x: auto;
    }

    .team-gap-filters button {
        flex: 0 0 auto;
    }

    .team-pagination {
        padding-inline: 14px;
    }

    .team-competency-list {
        overflow-x: auto;
        padding-inline: 14px;
    }

    .team-competency-list-head,
    .team-competency-row {
        min-width: 650px;
    }
}
@media (max-width: 620px) {
    .team-dashboard-copy h1 {
        font-size: 21px;
    }

    .team-selected-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }

    .team-selected-metrics > span:nth-child(odd) {
        border-left: 0;
    }

    .team-selected-metrics > span:nth-child(n + 3) {
        border-top: 1px solid oklch(89% 0.012 165);
    }

    .team-member-filter {
        grid-template-columns: repeat(2, minmax(120px, 1fr));
        overflow-x: auto;
    }
}
</style>
