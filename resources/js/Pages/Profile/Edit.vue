<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SidebarBrand from '../../Components/SidebarBrand.vue';
import PageTitleBlock from '../../Components/PageTitleBlock.vue';
import { NAV_CONFIG, ROLES_CONFIG } from '../../data';

const props = defineProps({
    profileUser: {
        type: Object,
        required: true,
    },
    pageTitle: {
        type: String,
        default: 'โปรไฟล์',
    },
});

const showSidebar = ref(true);
const normalizeRoleKey = (role) => role === 'manager_dept' ? 'dept_head' : role;

const roleKey = computed(() => normalizeRoleKey(props.profileUser?.r || 'employee'));
const roleData = computed(() => ROLES_CONFIG[roleKey.value] || ROLES_CONFIG.employee);
const navSections = computed(() => {
    if (['dept_head', 'division_head', 'academic_department_head'].includes(roleKey.value)) return NAV_CONFIG[roleKey.value] || NAV_CONFIG.dept_head || NAV_CONFIG.manager_dept || [];

    return NAV_CONFIG[roleKey.value] || NAV_CONFIG.employee || [];
});
const fullName = computed(() =>
    `${props.profileUser?.t || ''}${props.profileUser?.fn || ''} ${props.profileUser?.ln || ''}`.trim()
    || props.profileUser?.n
    || 'โปรไฟล์บุคลากร',
);
const roleLine = computed(() =>
    [props.profileUser?.p, props.profileUser?.l, props.profileUser?.d].filter(Boolean).join(' · ') || 'ยังไม่ได้ระบุข้อมูลตำแหน่ง',
);

const fieldGroups = computed(() => [
    { key: 'sso', label: 'ID', value: props.profileUser?.sso },
    { key: 'title', label: 'คำนำหน้า', value: props.profileUser?.t },
    { key: 'first_name_th', label: 'ชื่อ (ภาษาไทย)', value: props.profileUser?.fn },
    { key: 'last_name_th', label: 'นามสกุล (ภาษาไทย)', value: props.profileUser?.ln },
    { key: 'first_name_en', label: 'First Name', value: props.profileUser?.fe },
    { key: 'last_name_en', label: 'Last Name', value: props.profileUser?.le },
    { key: 'email', label: 'อีเมล', value: props.profileUser?.em },
    { key: 'phone', label: 'เบอร์โทรศัพท์', value: props.profileUser?.ph },
    { key: 'department', label: 'สังกัด/หน่วยงาน', value: props.profileUser?.d },
]);

const goDashboard = (pageId) => {
    router.visit(`${route('dashboard')}?page=${encodeURIComponent(pageId)}`);
};
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="props.pageTitle" />

    <div class="shell" :class="{ 'sidebar-hidden': !showSidebar }">
        <aside v-if="showSidebar" class="sidebar">
            <SidebarBrand />

            <button class="sb-user on" type="button">
                <div class="av" :style="{ background: roleData.col }">
                    {{ fullName[0] || roleData.av }}
                </div>
                <div style="overflow: hidden; min-width: 0">
                    <div class="u-name">{{ fullName }}</div>
                    <div class="u-role">{{ props.profileUser?.p || roleData.pos }}</div>
                </div>
            </button>

            <div class="sb-nav">
                <div v-for="(section, sectionIndex) in navSections" :key="sectionIndex">
                    <div class="nav-sec">{{ section.sec }}</div>
                    <button
                        v-for="item in section.items"
                        :key="item.id"
                        class="nav-item nav-button"
                        type="button"
                        @click="goDashboard(item.id)"
                    >
                        <span class="nav-ic">{{ item.ic }}</span>
                        {{ item.lb }}
                    </button>
                </div>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <button class="menu-btn" type="button" @click="showSidebar = !showSidebar">☰</button>
                <PageTitleBlock :page-title="props.pageTitle" />
                <button class="btn btn-s btn-sm" style="margin-left: 8px" type="button" @click="logout">
                    ออกจากระบบ
                </button>
            </div>

            <div class="content profile-page">
                <section class="profile-hero card">
                    <div class="profile-identity">
                        <div class="profile-photo-block">
                            <div class="profile-avatar">
                                <img
                                    v-if="props.profileUser?.photo"
                                    :src="props.profileUser.photo"
                                    :alt="fullName"
                                />
                                <span v-else>{{ fullName[0] || roleData.av }}</span>
                            </div>
                        </div>
                        <div class="profile-heading">
                            <div class="sec-t">โปรไฟล์บุคลากร</div>
                            <h1>{{ fullName }}</h1>
                            <p>{{ roleLine }}</p>
                            <div class="profile-tags">
                                <span>ID {{ props.profileUser?.sso || '—' }}</span>
                                <span>{{ props.profileUser?.w || 'ยังไม่ได้ระบุสายงาน' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-admin-notice" role="note">
                        <strong>ข้อมูลโปรไฟล์แก้ไขได้โดยผู้ดูแลระบบเท่านั้น</strong>
                        <span>หากข้อมูลไม่ถูกต้องหรือต้องการแก้ไข กรุณาติดต่อ Admin</span>
                    </div>
                </section>

                <section class="profile-section card">
                    <div class="fw8 fs14">ข้อมูลบุคลากร</div>
                    <div class="profile-grid">
                        <div v-for="field in fieldGroups" :key="field.key" class="profile-field">
                            <div class="lbl">{{ field.label }}</div>
                            <div class="profile-value">
                                {{ field.value || '—' }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.nav-button {
    width: 100%;
}

.menu-btn {
    padding: 8px;
    min-width: 40px;
    border: none;
    background: transparent;
    color: var(--text2);
    font-size: 22px;
    cursor: pointer;
}

.profile-page {
    display: grid;
    gap: 14px;
}

.profile-hero {
    min-height: 214px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
    padding: 34px 40px;
}

.profile-identity {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 30px;
}

.profile-avatar {
    width: 144px;
    height: 144px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 5px solid var(--bg);
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    font-size: 52px;
    font-weight: 900;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.profile-photo-block {
    display: grid;
    justify-items: center;
    gap: 12px;
}

.profile-heading {
    min-width: 0;
}

.profile-heading h1 {
    margin: 6px 0 4px;
    color: var(--navy);
    font-size: 26px;
    line-height: 1.18;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.profile-heading p {
    margin: 0;
    color: var(--text2);
    font-size: 13px;
    font-weight: 600;
}

.profile-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.profile-tags span {
    min-height: 26px;
    display: inline-flex;
    align-items: center;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--bg);
    color: var(--text2);
    font-size: 12px;
    font-weight: 800;
}

.profile-admin-notice {
    box-sizing: border-box;
    display: grid;
    gap: 5px;
    flex: 0 1 320px;
    max-width: 100%;
    padding: 14px 16px;
    border: 1px solid #cfe4dc;
    border-radius: var(--r);
    background: #f3fbf7;
    color: #245447;
    font-size: 13px;
    line-height: 1.5;
}

.profile-admin-notice strong {
    font-weight: 800;
}

.profile-section {
    padding: 20px 24px;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 14px;
    margin-top: 14px;
}

.profile-field {
    min-height: 72px;
    display: grid;
    align-content: center;
    gap: 6px;
    padding: 10px 14px;
    border-left: 3px solid var(--blue);
    border-radius: 0 var(--r) var(--r) 0;
    background: var(--bg);
}

.profile-value {
    color: var(--text);
    font-size: 14px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

@media (max-width: 900px) {
    .profile-hero,
    .profile-identity {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile-hero {
        padding: 24px;
    }

    .profile-avatar {
        width: 112px;
        height: 112px;
        font-size: 40px;
    }

    .profile-heading h1 {
        font-size: 21px;
    }

    .profile-admin-notice {
        width: 100%;
        flex-basis: auto;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }
}
</style>
