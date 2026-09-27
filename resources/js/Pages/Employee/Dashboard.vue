<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
import {
    NAV_CONFIG,
    PAGE_TITLES,
    ROLES_CONFIG,
} from '../../data';
import EmployeeAssess from './EmployeeAssess.vue';
import EmployeeGap from './EmployeeGap.vue';
import EmployeeIDP from './EmployeeIDP.vue';
import EmployeeIDPDetail from './EmployeeIDPDetail.vue';
import EmployeeProgress from './EmployeeProgress.vue';
import FcTopicApproval from './FcTopicApproval.vue';
import HeadDashboard from '../Head/Dashboard.vue';

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'ประเมินตนเอง',
    },
});

const clone = (value) => JSON.parse(JSON.stringify(value));
const setRef = (target) => (next) => {
    target.value = typeof next === 'function' ? next(target.value) : next;
};
const requestedPage = typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('page')
    : null;
const implementedEmployeePages = new Set([
    'emp-assess',
    'emp-gap',
    'emp-idp',
    'emp-progress',
    'emp-idp-detail',
    'emp-fc-topic-approval',
    'emp-assessment-review',
    'emp-team-assessment',
    'emp-idp-review',
]);

const showSidebar = ref(true);
const activePage = ref(implementedEmployeePages.has(requestedPage) ? requestedPage : 'emp-assess');
const currentRole = ref('employee');
const page = usePage();
const users = ref(page.props.currentUser ? [clone(page.props.currentUser)] : []);

const defaultLearningMethods = [
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
        color: '#75292D',
    },
];

const currentRoleData = computed(() => ROLES_CONFIG[currentRole.value]);
const currentPageTitle = computed(() => ({
    'emp-fc-topic-approval': 'พิจารณาหัวข้อการประเมิน',
    'emp-assessment-review': 'อนุมัติการประเมิน',
    'emp-team-assessment': 'ผลการประเมินของทีม',
    'emp-idp-review': 'อนุมัติแผนและผล IDP',
}[activePage.value] || PAGE_TITLES[activePage.value] || props.pageTitle));
const serverCurrentUser = computed(() => page.props.currentUser || null);
const fcTopicApprovalModule = computed(() => page.props.fcTopicApprovalModule || { enabled: false, items: [] });
const assessmentApprovalModule = computed(() => page.props.assessmentApprovalModule || { enabled: false, items: [], pendingCount: 0 });
const idpReviewModule = computed(() => page.props.idpReviewModule || { enabled: false, assignmentCount: 0 });
const employeeNavSections = computed(() => {
    const sections = NAV_CONFIG.employee.map((section) => ({
        ...section,
        items: [...section.items],
    }));
    const assignedItems = [
        ...((fcTopicApprovalModule.value.enabled || assessmentApprovalModule.value.enabled)
            ? [{ id: 'emp-assessment-review', ic: '', lb: 'อนุมัติการประเมิน' }]
            : []),
        ...(assessmentApprovalModule.value.enabled
            ? [{ id: 'emp-team-assessment', ic: '', lb: 'ผลการประเมินของทีม' }]
            : []),
        ...(idpReviewModule.value.enabled
            ? [{ id: 'emp-idp-review', ic: '', lb: 'อนุมัติแผนและผล IDP' }]
            : []),
    ];

    if (assignedItems.length) {
        sections.push({ sec: 'งานที่ได้รับมอบหมาย', items: assignedItems });
    }

    return sections;
});
const currentProfileUser = computed(() =>
    serverCurrentUser.value
    || users.value.find((user) => user.r === 'employee')
    || users.value.find((user) => user.sso === '64020')
    || {
        n: page.props.auth?.user?.name || 'Employee User',
        t: '',
        sso: page.props.auth?.user?.id || 'current-user',
        p: '',
        l: '',
        w: '',
        r: 'employee',
        act: true,
    },
);
const assignedCompetencies = computed(() => page.props.currentUserCompetencies || []);
const fcTopicSelection = computed(() => page.props.currentUserFcTopicSelection || {});
const competencyGaps = computed(() => page.props.currentUserCompetencyGaps || []);
const learningMethods = computed(() => page.props.learningMethods?.length ? page.props.learningMethods : defaultLearningMethods);
const learningCatalogs = computed(() => page.props.hrCatalogItems || []);
const idpLearningMethods = computed(() => page.props.idpLearningMethods || []);
const currentUserIdp = computed(() => page.props.currentUserIdp || null);
const reviewerStepsForUser = (user) => {
    const steps = Array.isArray(user?.reviewerSteps) && user.reviewerSteps.length
        ? user.reviewerSteps
        : (Array.isArray(user?.supervisorChain) ? user.supervisorChain : []);

    return steps;
};
const selfAssessmentBlockReasons = computed(() => {
    const user = currentProfileUser.value || {};
    const rawStructureIssues = Array.isArray(user.structureIssues) ? user.structureIssues : [];
    const reasons = rawStructureIssues.filter((reason) => reason !== 'ยังไม่ได้กำหนดลำดับ IDP');
    const hasAssignedEvaluator = reviewerStepsForUser(user).length > 0;

    if (!hasAssignedEvaluator) {
        reasons.push('ยังไม่ได้กำหนดหัวหน้าอย่างน้อย 1 ลำดับ');
    }

    if (user.structureStatus === 'invalid' && rawStructureIssues.length === 0) {
        reasons.push('ข้อมูลโครงสร้างยังต้องตรวจสอบ');
    }

    return Array.from(new Set(reasons.filter(Boolean)));
});
const isSelfAssessmentBlocked = computed(() => selfAssessmentBlockReasons.value.length > 0);

const requestPageChange = (page) => {
    activePage.value = page;
};

const goProfile = () => router.visit(route('profile.edit'));
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="currentPageTitle" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <div v-if="showSidebar" class="sidebar">
            <SidebarBrand />

            <button class="sb-user on" type="button" @click="goProfile">
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
                <div v-for="(section, sectionIndex) in employeeNavSections" :key="sectionIndex">
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
                    :competencies="assignedCompetencies"
                    :fc-topic-selection="fcTopicSelection"
                    :blocked="isSelfAssessmentBlocked"
                    :block-reasons="selfAssessmentBlockReasons"
                />

                <EmployeeGap
                    v-else-if="activePage === 'emp-gap'"
                    :set-page="requestPageChange"
                    :competencies="assignedCompetencies"
                    :gaps="competencyGaps"
                    :eval-status="currentProfileUser?.evalStatus"
                    :user="currentProfileUser"
                />

                <EmployeeIDP
                    v-else-if="activePage === 'emp-idp'"
                    :learning-methods="learningMethods"
                    :idp-learning-methods="idpLearningMethods"
                    :learning-catalogs="learningCatalogs"
                    :gaps="competencyGaps"
                    :idp="currentUserIdp"
                    :user="currentProfileUser"
                />

                <EmployeeProgress
                    v-else-if="activePage === 'emp-progress'"
                    :activities="page.props.currentUserApprovedIdpActivities || []"
                />

                <EmployeeIDPDetail v-else-if="activePage === 'emp-idp-detail'" :activities="page.props.currentUserApprovedIdpActivities || []" />

                <FcTopicApproval
                    v-else-if="activePage === 'emp-fc-topic-approval' && fcTopicApprovalModule.enabled"
                    :module="fcTopicApprovalModule"
                />

                <HeadDashboard
                    v-else-if="activePage === 'emp-assessment-review' && (fcTopicApprovalModule.enabled || assessmentApprovalModule.enabled)"
                    embedded
                    embedded-page="dh-assess"
                    role-key="employee"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <HeadDashboard
                    v-else-if="activePage === 'emp-team-assessment' && assessmentApprovalModule.enabled"
                    embedded
                    embedded-page="sup-gap"
                    role-key="employee"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <HeadDashboard
                    v-else-if="activePage === 'emp-idp-review' && idpReviewModule.enabled"
                    embedded
                    embedded-page="dh-idp"
                    role-key="employee"
                    :idp-review-items="page.props.idpReviewItems || []"
                />

                <div v-else class="p-20 text-center text-text3">กำลังพัฒนา</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.nav-count { display: grid; place-items: center; min-width: 20px; height: 20px; margin-left: auto; border-radius: 10px; background: var(--color-primary-soft); color: var(--color-primary); padding: 0 6px; font-size: 10px; font-weight: 900; }
.nav-item.on .nav-count { background: rgba(255,255,255,.2); color: #fff; }
</style>
