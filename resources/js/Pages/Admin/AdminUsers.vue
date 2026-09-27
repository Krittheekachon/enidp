<template>
  <div class="admin-users-head mb20">
    <div>
      <div class="sec-t">จัดการผู้ใช้งาน </div>
      <div class="sec-s">รายชื่อบุคลากรทั้งหมด , กำหนดบทบาทในระบบ และลำดับการประเมิน</div>
    </div>
    <div class="action-row">
      <!-- <button class="btn btn-s" type="button" @click="showImport = true"> Import Excel</button> -->
      <button class="btn btn-s reviewer-template-entry-btn" type="button" @click="openReviewerTemplateModal('assessment')">
        ลำดับการประเมิน
      </button>
      <button class="btn btn-s reviewer-template-entry-btn" type="button" @click="openReviewerTemplateModal('idp')">
        ลำดับ IDP
      </button>
      <button class="btn btn-p add-user-btn" type="button" @click="openModal('modal-user')">+ เพิ่มผู้ใช้</button>
    </div>
  </div>

  <ExcelImportModal
    v-if="showImport"
    title="นำเข้าข้อมูลผู้ใช้งาน (User Import)"
    template-name="User_Template.xlsx"
    @close="showImport = false"
  />

  <div class="user-dashboard-grid mb14">
    <div class="user-metric">
      <span>ผู้ใช้ทั้งหมด</span>
      <strong>{{ props.users.length }}</strong>
    </div>
    <div class="user-metric warning">
      <span>ต้องตรวจสอบ</span>
      <strong>{{ invalidStructureCount }}</strong>
    </div>
  </div>

  <div v-if="statusFeedback" class="status-feedback" :class="statusFeedback.kind" role="status" aria-live="polite">
    <span>{{ statusFeedback.message }}</span>
    <button type="button" class="status-feedback-close" aria-label="ปิดข้อความแจ้ง" @click="statusFeedback = null">×</button>
  </div>

  <div class="card mb14">
    <div class="ch filter-row">
      <input v-model="search" class="inp search-input" placeholder=" ค้นหาชื่อ / ID..." />

      <select v-model="worklineFilter" class="sel workline-select">
        <option>ทุกสายงาน</option>
        <option v-for="workline in worklineOptions" :key="workline" :value="workline">
          {{ workline }}
        </option>
      </select>

      <select v-if="!isSupportFilterMode" v-model="departmentFilter" class="sel department-select">
        <option>{{ allDepartmentsLabel }}</option>
        <option v-for="department in departmentOptions" :key="department" :value="department">
          {{ department }}
        </option>
      </select>

      <select v-else v-model="supportWorkFilter" class="sel department-select">
        <option>{{ allSupportWorksLabel }}</option>
        <option v-for="work in supportWorkOptions" :key="work" :value="work">
          {{ work }}
        </option>
      </select>

      <select v-if="showSupportUnitFilter" v-model="supportUnitFilter" class="sel unit-select">
        <option>{{ allSupportUnitsLabel }}</option>
        <option v-for="unit in supportUnitOptions" :key="unit" :value="unit">
          {{ unit }}
        </option>
      </select>

      <select v-model="positionFilter" class="sel position-select-filter">
        <option>ทุกตำแหน่ง</option>
        <option v-for="position in positionOptions" :key="position" :value="position">
          {{ position }}
        </option>
      </select>

      <select v-model="roleFilter" class="sel role-select">
        <option>ทุกบทบาท (Role)</option>
        <option v-for="role in roleOptions" :key="role" :value="role">
          {{ role }}
        </option>
      </select>

      <select v-model="statusFilter" class="sel status-select">
        <option>ทุกสถานะ</option>
        <option>ปกติ / ใช้งาน</option>
        <option>ระงับการใช้งาน</option>
      </select>
    </div>

    <div class="table-scroll">
      <table class="tbl">
        <colgroup>
          <col class="col-id" />
          <col class="col-name" />
          <col class="col-workline" />
          <col class="col-dept" />
          <col class="col-position" />
          <col class="col-level" />
          <col class="col-role" />
          <col class="col-status" />
          <col class="col-actions" />
        </colgroup>
        <thead>
          <tr>
            <th>ID</th>
            <th>ชื่อ-นามสกุล</th>
            <th>สายงาน</th>
            <th>หน่วยงาน/ภาควิชา</th>
            <th>ตำแหน่ง</th>
            <th>ระดับตำแหน่ง</th>
            <th>บทบาทในระบบ</th>
            <th class="center-cell">สถานะ</th>
            <th class="right-cell"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(user, index) in filteredUsers" :key="user.sso || index">
            <td class="id-cell">{{ user.sso || '—' }}</td>
            <td>
              <div class="flex ic g8">
                <div class="av user-avatar">
                  <img v-if="user.photo" class="avatar-photo" :src="user.photo" :alt="user.n" />
                  <span v-else>{{ avatarInitial(user) }}</span>
                </div>
                <div class="flex col">
                  <span class="fw6 fs13 user-name-line">
                    {{ user.t }}{{ user.n }}
                    <span
                      v-if="hasInvalidStructure(user)"
                      class="structure-warning-badge"
                      :title="structureIssueText(user)"
                    >
                      ! ต้องตรวจสอบ
                    </span>
                  </span>
                </div>
              </div>
            </td>
            <td>
              <span class="b workline-badge" :class="user.w === 'สายวิชาการ' ? 'bb' : 'bg'">
                {{ user.w || '—' }}
              </span>
            </td>
            <td>
              <div
                class="fs12 fw6 text-gray-700 whitespace-nowrap overflow-hidden truncate dept-cell"
                :title="organizationUnit(user)"
              >
                {{ organizationUnit(user) || '—' }}
              </div>
            </td>
            <td class="position-cell">
              <div class="whitespace-nowrap overflow-hidden truncate full-width" :title="user.p || ''">
                {{ user.p || '—' }}
              </div>
            </td>
            <td class="level-cell">{{ getDisplayLevel(user) || '—' }}</td>
            <td class="role-cell">
              <span class="b" :class="roleBadge(user.r).className" :style="roleBadge(user.r).style">
                {{ roleBadge(user.r).label }}
              </span>
            </td>
            <td class="center-cell">
              <span class="b" :class="isActive(user) ? 'bg' : 'br'">
                {{ isActive(user) ? 'ใช้งานอยู่' : 'ระงับแล้ว' }}
              </span>
            </td>
            <td class="right-cell">
              <div class="row-actions">
                <button class="btn btn-s btn-xs" type="button" @click="openModal('modal-user', user)">
                  แก้ไข
                </button>
                <button
                  class="btn btn-xs status-btn"
                  type="button"
                  :class="isActive(user) ? 'btn-r suspend' : 'btn-g activate'"
                  :disabled="updatingStatusId !== null || isStatusActionDisabled(user)"
                  :title="statusActionTitle(user)"
                  @click.stop="toggleStatus(user)"
                >
                  {{ updatingStatusId === user.db_id ? 'กำลังบันทึก...' : (isActive(user) ? 'ระงับ' : 'เปิดใช้งาน') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredUsers.length === 0" class="empty-result">
        ไม่พบข้อมูลที่คุณค้นหา 
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ExcelImportModal } from '../../Components/SharedUI.vue';

type User = {
  db_id?: number | null;
  sso?: string;
  t?: string;
  n: string;
  photo?: string;
  w?: string;
  d?: string;
  p?: string;
  l?: string;
  r?: string;
  sup?: string;
  evaluator2?: string;
  act?: boolean;
  structureStatus?: string;
  structureIssues?: string[];
};

type RoleBadge = {
  label: string;
  className: string;
  style?: Record<string, string>;
};

const props = defineProps<{
  openModal: (type: string, data?: unknown) => void;
  openReviewerTemplateModal: (chainType?: string) => void;
  updateUserStatus: (user: User, callbacks?: {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
  }) => void;
  users: User[];
  setUsers: (next: User[] | ((users: User[]) => User[])) => void;
  academicDepts: string[];
  supportDepts: string[];
  adminDepts: string[];
  worklines: string[];
}>();

const showImport = ref(false);
const search = ref('');
const allWorklinesLabel = 'ทุกสายงาน';
const allDepartmentsLabel = 'ทุกหน่วยงาน/ภาควิชา';
const allSupportWorksLabel = 'ทุกงาน';
const allSupportUnitsLabel = 'ทุกหน่วย';
const allPositionsLabel = 'ทุกตำแหน่ง';
const allRolesLabel = 'ทุกบทบาท (Role)';
const allStatusesLabel = 'ทุกสถานะ';
const activeStatusLabel = 'ปกติ / ใช้งาน';
const supportWorklineNames = ['สายสนับสนุน', 'สายงานสนับสนุน'];
const worklineFilter = ref(allWorklinesLabel);
const departmentFilter = ref(allDepartmentsLabel);
const supportWorkFilter = ref(allSupportWorksLabel);
const supportUnitFilter = ref(allSupportUnitsLabel);
const positionFilter = ref(allPositionsLabel);
const roleFilter = ref(allRolesLabel);
const statusFilter = ref(allStatusesLabel);
const updatingStatusId = ref<number | null>(null);
const statusFeedback = ref<{ kind: 'success' | 'suspended' | 'error'; message: string } | null>(null);
const page = usePage();
const currentUserId = computed(() => Number(page.props.auth?.user?.id || 0));
const roleOptions = [
  'บุคลากร',
  'หัวหน้าหน่วย',
  'หัวหน้างาน',
  'หัวหน้าฝ่าย',
  'หัวหน้าภาควิชา',
  'ผู้บริหารคณะ',
  'งานทรัพยากรบุคคล',
  'ผู้ดูแลระบบ',
];

const getDisplayLevel = (user: User) => (user.w === 'สายงานบริหาร' ? user.p : user.l);
const isSupportUser = (user: User) => supportWorklineNames.includes(user.w || '');
const supportPath = (user: User) => {
  const [department = '', work = '', ...unitParts] = (user.d || '').split(' > ').map((part) => part.trim()).filter(Boolean);

  return {
    department,
    work,
    unit: unitParts.join(' > '),
  };
};
const organizationUnit = (user: User) => {
  const parts = (user.d || '').split(' > ').map((part) => part.trim()).filter(Boolean);
  if (!parts.length) return '';

  return isSupportUser(user)
    ? parts[parts.length - 1]
    : parts[0];
};
const avatarInitial = (user: User) => user.n?.[0] || '?';
const openModal = (type: string, data?: unknown) => props.openModal(type, data);
const openReviewerTemplateModal = (chainType?: string) => props.openReviewerTemplateModal(chainType);
const worklineOptions = computed(() => props.worklines || []);
const isSupportFilterMode = computed(() => supportWorklineNames.includes(worklineFilter.value));
const showSupportUnitFilter = computed(() =>
  isSupportFilterMode.value && supportWorkFilter.value !== allSupportWorksLabel,
);
const isActive = (user: User) => user.act !== false;
const isAdminRole = (user: User) => user.r === 'admin';
const isCurrentUser = (user: User) => Boolean(user.db_id && Number(user.db_id) === currentUserId.value);
const isStatusActionDisabled = (user: User) => isActive(user) && (isCurrentUser(user) || isAdminRole(user));
const statusActionTitle = (user: User) => {
  if (isCurrentUser(user) && isActive(user)) {
    return 'ไม่สามารถระงับบัญชีที่กำลังใช้งานอยู่ได้';
  }
  if (isAdminRole(user) && isActive(user)) {
    return 'ไม่สามารถระงับบัญชีผู้ดูแลระบบได้';
  }

  return isActive(user) ? 'ระงับบัญชีผู้ใช้นี้' : 'เปิดใช้งานบัญชีผู้ใช้นี้';
};
const hasInvalidStructure = (user: User) => user.structureStatus === 'invalid';
const structureIssueText = (user: User) => (user.structureIssues || []).join('\n') || 'ข้อมูลโครงสร้างไม่ตรงกับ master data ปัจจุบัน';
const invalidStructureCount = computed(() => props.users.filter(hasInvalidStructure).length);
const departmentOptions = computed(() => {
  const departments = props.users
    .filter((user) => worklineFilter.value === allWorklinesLabel || user.w === worklineFilter.value)
    .map(organizationUnit)
    .filter(Boolean);

  return Array.from(new Set(departments)).sort((a, b) => a.localeCompare(b, 'th'));
});
const supportWorkOptions = computed(() => {
  const works = props.users
    .filter(isSupportUser)
    .map((user) => supportPath(user).work)
    .filter(Boolean);

  return Array.from(new Set(works)).sort((a, b) => a.localeCompare(b, 'th'));
});
const supportUnitOptions = computed(() => {
  if (supportWorkFilter.value === allSupportWorksLabel) return [];

  const units = props.users
    .filter(isSupportUser)
    .filter((user) => supportPath(user).work === supportWorkFilter.value)
    .map((user) => supportPath(user).unit)
    .filter(Boolean);

  return Array.from(new Set(units)).sort((a, b) => a.localeCompare(b, 'th'));
});
const matchesOrganizationFilter = (user: User) => {
  if (isSupportFilterMode.value) {
    if (!isSupportUser(user)) return false;

    const path = supportPath(user);
    const matchesWork = supportWorkFilter.value === allSupportWorksLabel || path.work === supportWorkFilter.value;
    const matchesUnit = supportUnitFilter.value === allSupportUnitsLabel || path.unit === supportUnitFilter.value;

    return matchesWork && matchesUnit;
  }

  return departmentFilter.value === allDepartmentsLabel || organizationUnit(user) === departmentFilter.value;
};
const positionOptions = computed(() => {
  const positions = props.users
    .filter((user) => worklineFilter.value === allWorklinesLabel || user.w === worklineFilter.value)
    .filter(matchesOrganizationFilter)
    .map((user) => user.p || '')
    .filter(Boolean);

  return Array.from(new Set(positions)).sort((a, b) => a.localeCompare(b, 'th'));
});

watch(worklineFilter, () => {
  departmentFilter.value = allDepartmentsLabel;
  supportWorkFilter.value = allSupportWorksLabel;
  supportUnitFilter.value = allSupportUnitsLabel;
  positionFilter.value = allPositionsLabel;
});

watch(departmentFilter, () => {
  positionFilter.value = allPositionsLabel;
});

watch(supportWorkFilter, () => {
  supportUnitFilter.value = allSupportUnitsLabel;
  positionFilter.value = allPositionsLabel;
});

watch(supportUnitFilter, () => {
  positionFilter.value = allPositionsLabel;
});

watch(departmentOptions, (options) => {
  if (departmentFilter.value !== allDepartmentsLabel && !options.includes(departmentFilter.value)) {
    departmentFilter.value = allDepartmentsLabel;
  }
});

watch(supportWorkOptions, (options) => {
  if (supportWorkFilter.value !== allSupportWorksLabel && !options.includes(supportWorkFilter.value)) {
    supportWorkFilter.value = allSupportWorksLabel;
  }
});

watch(supportUnitOptions, (options) => {
  if (supportUnitFilter.value !== allSupportUnitsLabel && !options.includes(supportUnitFilter.value)) {
    supportUnitFilter.value = allSupportUnitsLabel;
  }
});

watch(positionOptions, (options) => {
  if (positionFilter.value !== allPositionsLabel && !options.includes(positionFilter.value)) {
    positionFilter.value = allPositionsLabel;
  }
});

const roleBadge = (role?: string): RoleBadge => {
  switch (role) {
    case 'admin':
      return { label: 'ผู้ดูแลระบบ', className: 'bp' };
    case 'hr':
      return { label: 'งานทรัพยากรบุคคล', className: 'bb' };
    case 'manager':
    case 'dean':
      return {
        label: 'ผู้บริหารคณะ',
        className: 'bg',
        style: { background: '#e0f2fe', color: '#0369a1' },
      };
    case 'supervisor':
      return {
        label: 'หัวหน้าหน่วย',
        className: 'bg',
        style: { background: '#fff7ed', color: '#c2410c' },
      };
    case 'dept_head':
    case 'manager_dept':
      return { label: 'หัวหน้างาน', className: 'bg', style: { background: '#f0f9ff', color: '#0284c7' } };
    case 'division_head':
      return { label: 'หัวหน้าฝ่าย', className: 'bg', style: { background: '#fef3c7', color: '#b45309' } };
    case 'academic_department_head':
      return { label: 'หัวหน้าภาควิชา', className: 'bg', style: { background: '#ecfeff', color: '#0e7490' } };
    default:
      return { label: 'บุคลากร', className: 'bgr' };
  }
};

const roleName = (role?: string) => roleBadge(role).label;

const filteredUsers = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return props.users.filter((user) => {
    const name = user.n || '';
    const id = user.sso || '';
    const matchesSearch = !keyword
      || name.toLowerCase().includes(keyword)
      || id.toLowerCase().includes(keyword);
    const matchesWorkline = worklineFilter.value === allWorklinesLabel || user.w === worklineFilter.value;
    const matchesDepartment = matchesOrganizationFilter(user);
    const matchesPosition = positionFilter.value === allPositionsLabel || user.p === positionFilter.value;
    const matchesRole = roleFilter.value === allRolesLabel || roleName(user.r) === roleFilter.value;
    const matchesStatus = statusFilter.value === allStatusesLabel
      || (statusFilter.value === activeStatusLabel ? isActive(user) : !isActive(user));

    return matchesSearch && matchesWorkline && matchesDepartment && matchesPosition && matchesRole && matchesStatus;
  }).sort((a, b) => String(b.sso || '').localeCompare(String(a.sso || ''), 'th', {
    numeric: true,
    sensitivity: 'base',
  }));
});

const toggleStatus = (user: User) => {
  if (!user.db_id) {
    statusFeedback.value = { kind: 'error', message: 'ไม่พบรหัสฐานข้อมูลของผู้ใช้นี้ กรุณารีเฟรชหน้าแล้วลองใหม่' };
    return;
  }

  if (isStatusActionDisabled(user)) {
    statusFeedback.value = {
      kind: 'error',
      message: isAdminRole(user)
        ? 'ไม่สามารถระงับบัญชีผู้ดูแลระบบได้'
        : 'ไม่สามารถระงับบัญชีที่กำลังใช้งานอยู่ได้',
    };
    return;
  }

  const nextActive = !isActive(user);
  updatingStatusId.value = user.db_id;
  statusFeedback.value = null;

  props.updateUserStatus(user, {
    onSuccess: () => {
      statusFeedback.value = {
        kind: nextActive ? 'success' : 'suspended',
        message: `${nextActive ? 'เปิดใช้งาน' : 'ระงับ'}บัญชี ${user.n} เรียบร้อยแล้ว`,
      };
    },
    onError: (errors) => {
      statusFeedback.value = {
        kind: 'error',
        message: errors.act || Object.values(errors)[0] || 'ไม่สามารถบันทึกสถานะผู้ใช้ได้ กรุณาลองใหม่อีกครั้ง',
      };
    },
    onFinish: () => { updatingStatusId.value = null; },
  });
};

</script>

<style scoped>
.status-feedback {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 1100;
  max-width: min(420px, calc(100vw - 32px));
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 11px 14px;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  background: #f0fdf4;
  color: #166534;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
}

.status-feedback.suspended,
.status-feedback.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.status-feedback-close {
  margin: -4px -5px -4px auto;
  padding: 2px 6px;
  border: 0;
  border-radius: 4px;
  background: transparent;
  color: inherit;
  font-size: 18px;
  cursor: pointer;
}

.admin-users-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.action-row {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
  margin-left: auto;
}

.add-user-btn {
  border-color: #2563eb;
  background: #2563eb;
  color: #fff;
  min-width: 116px;
  justify-content: center;
  opacity: 1;
}

.add-user-btn:hover {
  border-color: #1d4ed8;
  background: #1d4ed8;
  color: #fff;
}

.reviewer-template-entry-btn {
  min-width: 136px;
  justify-content: center;
  border-color: #cbd5e1;
  background: #fff;
  color: #334155;
}

.user-dashboard-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.user-metric {
  min-height: 92px;
  padding: 16px;
  border: 1px solid #dbe5f1;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.user-metric span {
  display: block;
  color: var(--text3);
  font-size: 12px;
  font-weight: 800;
}

.user-metric strong {
  display: block;
  margin-top: 8px;
  color: var(--navy);
  font-size: 30px;
  font-weight: 950;
  line-height: 1;
}

.user-metric.warning strong {
  color: #c2410c;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
}

.search-input {
  width: 100%;
  max-width: none;
  min-width: 210px;
}

.workline-select {
  width: 100%;
}

.department-select {
  width: 100%;
}

.unit-select {
  width: 100%;
}

.position-select-filter {
  width: 100%;
}

.role-select {
  width: 100%;
}

.status-select {
  width: 100%;
}

.table-scroll {
  overflow-x: auto;
}

.tbl {
  table-layout: fixed;
  min-width: 1220px;
}

.tbl th,
.tbl td {
  vertical-align: middle;
}

.tbl th {
  white-space: nowrap;
}

.tbl td {
  padding-top: 14px;
  padding-bottom: 14px;
}

.col-id {
  width: 7%;
}

.col-name {
  width: 18%;
}

.col-workline {
  width: 9%;
}

.col-dept {
  width: 16%;
}

.col-position {
  width: 14%;
}

.col-level {
  width: 9%;
}

.col-role {
  width: 10%;
}

.col-status {
  width: 7%;
}

.col-actions {
  width: 10%;
}

.id-cell {
  font-family: monospace;
  font-size: 11px;
  color: var(--text3);
  white-space: nowrap;
}

.user-avatar {
  width: 32px;
  height: 32px;
  font-size: 12px;
  background: var(--navy);
}

.user-name-line {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  min-width: 0;
}

.structure-warning-badge {
  display: inline-flex;
  align-items: center;
  min-height: 22px;
  padding: 3px 7px;
  border: 1px solid #fed7aa;
  border-radius: 999px;
  background: #fff7ed;
  color: #c2410c;
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
  white-space: nowrap;
}

.workline-badge {
  font-size: 11px;
}

.dept-cell {
  max-width: 100%;
}

.position-cell {
  color: var(--text);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.35;
}

.level-cell {
  max-width: 100%;
  color: var(--text3);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.35;
  word-break: keep-all;
  overflow-wrap: anywhere;
}

.role-cell {
  white-space: nowrap;
}

.center-cell {
  text-align: center;
}

.right-cell {
  text-align: right;
}

.row-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 5px;
  white-space: nowrap;
}

.full-width {
  width: 100%;
}

.status-btn.suspend {
  background: #fee2e2;
  color: #b91c1c;
}

.status-btn.activate {
  background: #dcfce7;
  color: #15803d;
}

.status-btn:disabled {
  cursor: not-allowed;
  border-color: #e5e7eb;
  background: #f3f6fb;
  color: var(--color-text-muted);
  opacity: 1;
}

.delete-btn {
  border-color: #fca5a5;
  background: #fff;
  color: #dc2626;
}

.delete-btn:hover {
  border-color: #ef4444;
  background: #fef2f2;
  color: #b91c1c;
}

.empty-result {
  padding: 40px;
  text-align: center;
  color: var(--text3);
}

@media (max-width: 980px) {
  .user-dashboard-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .user-dashboard-grid {
    grid-template-columns: 1fr;
  }

  .filter-row {
    grid-template-columns: 1fr;
  }

  .search-input,
  .workline-select,
  .department-select,
  .position-select-filter,
  .role-select,
  .status-select {
    width: 100%;
    max-width: none;
  }
}
</style>
