<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AssessmentSummaryBand from '../../Components/AssessmentSummaryBand.vue';
import IdpActivityProgressReview from '../Head/IdpActivityProgressReview.vue';
import SupportDivisionDialog from './SupportDivisionDialog.vue';

type AttentionWorklineKey = 'all' | 'academic' | 'support' | 'other';

const props = defineProps<{
  analytics?: any;
  module?: 'assessment' | 'idp';
  title?: string;
  description?: string;
  reloadProp?: string;
  hideHeadingCopy?: boolean;
  hideWorklineBreakdown?: boolean;
}>();

const data = computed(() => props.analytics || {});
const activeModule = computed(() => props.module || 'assessment');
const isAssessmentModule = computed(() => activeModule.value === 'assessment');
const pageTitle = computed(() => props.title || (isAssessmentModule.value ? 'ภาพรวมการประเมิน' : 'การติดตาม IDP'));
const pageDescription = computed(() => props.description || (isAssessmentModule.value
  ? 'ผลการประเมินและ Competency Gap ของบุคลากรในรอบที่เลือก'
  : 'ติดตามการจัดทำแผน สถานะการพัฒนา และรายการที่ต้องเร่งดำเนินการ'));
const summary = computed(() => data.value.summary || {
  totalEmployees: 0,
  assessedEmployees: 0,
  employeesWithGap: 0,
  employeesWithoutGap: 0,
});
const worklines = computed(() => data.value.worklines || []);
const typeFilter = ref('all');
const search = ref('');
const selectedCell = ref<any | null>(null);
const selectedWorklineKey = ref<string | null>(null);
const selectedOrganizationKey = ref<string | null>(null);
const attentionFilter = ref<'all' | 'completed' | 'active' | 'waiting' | 'draft' | 'not_started'>('all');
const attentionWorklineFilter = ref<AttentionWorklineKey>('all');
const attentionSearch = ref('');
const worklineDialog = ref<HTMLElement | null>(null);
const selectedIdpPerson = ref<any | null>(null);
const selectedRoundId = ref<number | null>(data.value.round?.id || null);
const isChangingRound = ref(false);
const assessmentHeatmap = computed(() => data.value.assessmentHeatmap || data.value.heatmap || []);

const filteredHeatmap = computed(() => {
  const keyword = search.value.trim().toLocaleLowerCase('th');

  return assessmentHeatmap.value.filter((row: any) => {
    const matchesType = typeFilter.value === 'all' || row.type === typeFilter.value;
    const matchesSearch = !keyword || `${row.code} ${row.name}`.toLocaleLowerCase('th').includes(keyword);

    return matchesType && matchesSearch;
  });
});
const visibleHeatmap = computed(() => filteredHeatmap.value);
const hasAssessmentData = computed(() => Number(summary.value.assessedEmployees || 0) > 0);
const rounds = computed(() => data.value.rounds || []);
const selectedWorkline = computed(() => worklines.value.find((workline: any) => workline.key === selectedWorklineKey.value) || null);
const selectedWorklineOrganizations = computed(() => selectedWorkline.value?.organizations || []);
const supportOrganizationGroups = computed(() => selectedWorklineOrganizations.value
  .filter((organization: any) => organization.depth === 0)
  .map((division: any) => ({
    division,
    works: selectedWorklineOrganizations.value
      .filter((organization: any) => organization.depth === 1 && organization.path.startsWith(`${division.path} > `))
      .map((work: any) => ({
        work,
        units: selectedWorklineOrganizations.value.filter(
          (organization: any) => organization.depth === 2 && organization.path.startsWith(`${work.path} > `),
        ),
      })),
  })));
const academicOrganizationGroups = computed(() => selectedWorklineOrganizations.value
  .filter((organization: any) => organization.depth === 0)
  .map((department: any) => ({ division: department, works: [] })));
const organizationGroups = computed(() => selectedWorkline.value?.key === 'academic'
  ? academicOrganizationGroups.value
  : supportOrganizationGroups.value);
const selectedOrganizationGroup = computed(() => organizationGroups.value
  .find((group: any) => group.division.key === selectedOrganizationKey.value) || null);
const idpSummary = computed(() => data.value.idpSummary || {
  requiredEmployees: 0,
  startedEmployees: 0,
  notStartedEmployees: 0,
  totalItems: 0,
  startedItems: 0,
  states: {},
});
const hasCurrentModuleData = computed(() => isAssessmentModule.value
  ? hasAssessmentData.value
  : Number(idpSummary.value.requiredEmployees || 0) > 0);
const idpPeople = computed(() => {
  const people = new Map<number, any>();

  for (const row of data.value.heatmap || []) {
    for (const person of row.faculty?.people || []) {
      const current = people.get(Number(person.userId)) || {
        ...person,
        competencies: [],
        states: [],
        isOverdue: false,
      };
      current.competencies.push({
        code: row.code,
        name: row.name,
        expectedLevel: person.expectedLevel,
        actualLevel: person.actualLevel,
        gap: person.gap,
        idpStatus: person.idpStatus || 'not_started',
        isOverdue: Boolean(person.isOverdue),
      });
      current.states.push(person.idpStatus || 'not_started');
      current.isOverdue = current.isOverdue || Boolean(person.isOverdue);
      people.set(Number(person.userId), current);
    }
  }

  const attentionRank: Record<string, number> = {
    overdue: 0,
    not_started: 1,
    revision_required: 2,
    draft: 3,
    plan_review: 4,
    developing: 5,
    result_review: 6,
    failed: 7,
    passed: 8,
  };

  return Array.from(people.values()).map((person: any) => {
    let status = person.isOverdue ? 'overdue' : 'passed';
    if (!person.isOverdue) {
      status = person.states.every((state: string) => state === 'not_started')
        ? 'not_started'
        : person.states.slice().sort((left: string, right: string) => (attentionRank[left] ?? 99) - (attentionRank[right] ?? 99))[0];
    }

    return { ...person, status };
  }).sort((left: any, right: any) => (attentionRank[left.status] ?? 99) - (attentionRank[right.status] ?? 99)
    || String(left.name).localeCompare(String(right.name), 'th'));
});
const idpPersonWorklineKey = (person: any): 'academic' | 'support' | 'other' => {
  const value = String(person.workline || '');
  if (value.includes('วิชาการ')) return 'academic';
  if (value.includes('สนับสนุน')) return 'support';
  return 'other';
};
const idpAttentionWorklineFilters = computed<Array<{ key: AttentionWorklineKey; label: string; count: number }>>(() => {
  const filters: Array<{ key: AttentionWorklineKey; label: string; count: number }> = [
    { key: 'all', label: 'ทั้งหมด', count: idpPeople.value.length },
    { key: 'academic', label: 'สายวิชาการ', count: idpPeople.value.filter((person: any) => idpPersonWorklineKey(person) === 'academic').length },
    { key: 'support', label: 'สายสนับสนุน', count: idpPeople.value.filter((person: any) => idpPersonWorklineKey(person) === 'support').length },
  ];
  const otherCount = idpPeople.value.filter((person: any) => idpPersonWorklineKey(person) === 'other').length;
  if (otherCount) filters.push({ key: 'other', label: 'ไม่ระบุสายงาน', count: otherCount });
  return filters;
});
const worklineFilteredIdpPeople = computed(() => attentionWorklineFilter.value === 'all'
  ? idpPeople.value
  : idpPeople.value.filter((person: any) => idpPersonWorklineKey(person) === attentionWorklineFilter.value));
const selectedIdpDetailItems = computed(() => {
  if (!selectedIdpPerson.value) return [];
  return (data.value.idpDetails || []).filter(
    (item: any) => Number(item.userId) === Number(selectedIdpPerson.value.userId),
  );
});
const idpAttentionFilters = computed(() => [
  { key: 'all', label: 'ทั้งหมด', count: worklineFilteredIdpPeople.value.length },
  {
    key: 'completed',
    label: 'เสร็จสิ้น',
    count: worklineFilteredIdpPeople.value.filter((person: any) => person.status === 'passed').length,
  },
  {
    key: 'active',
    label: 'กำลังดำเนินการ',
    count: worklineFilteredIdpPeople.value.filter((person: any) => ['developing', 'overdue'].includes(person.status)).length,
  },
  {
    key: 'waiting',
    label: 'รออนุมัติ',
    count: worklineFilteredIdpPeople.value.filter((person: any) => ['plan_review', 'result_review'].includes(person.status)).length,
  },
  {
    key: 'draft',
    label: 'Draft / ต้องแก้ไข',
    count: worklineFilteredIdpPeople.value.filter((person: any) => ['draft', 'revision_required', 'failed'].includes(person.status)).length,
  },
  {
    key: 'not_started',
    label: 'ยังไม่มี IDP',
    count: worklineFilteredIdpPeople.value.filter((person: any) => person.status === 'not_started').length,
  },
]);
const filteredIdpAttentionPeople = computed(() => {
  let people = worklineFilteredIdpPeople.value;
  if (attentionFilter.value === 'completed') {
    people = people.filter((person: any) => person.status === 'passed');
  }
  if (attentionFilter.value === 'active') {
    people = people.filter((person: any) => ['developing', 'overdue'].includes(person.status));
  }
  if (attentionFilter.value === 'waiting') {
    people = people.filter((person: any) => ['plan_review', 'result_review'].includes(person.status));
  }
  if (attentionFilter.value === 'draft') {
    people = people.filter((person: any) => ['draft', 'revision_required', 'failed'].includes(person.status));
  }
  if (attentionFilter.value === 'not_started') {
    people = people.filter((person: any) => person.status === 'not_started');
  }

  const keyword = attentionSearch.value.trim().toLocaleLowerCase('th');
  if (!keyword) return people;

  return people.filter((person: any) => [
    person.name,
    person.position,
    person.organization,
    person.workline,
    ...(person.competencies || []).flatMap((competency: any) => [competency.code, competency.name]),
  ].filter(Boolean).join(' ').toLocaleLowerCase('th').includes(keyword));
});
const idpWorklines = computed(() => {
  const scopes = [...worklines.value];
  const hasOther = idpPeople.value.some((person: any) => {
    const value = String(person.workline || '');
    return !value.includes('วิชาการ') && !value.includes('สนับสนุน');
  });
  if (hasOther) scopes.push({ key: 'other', label: 'ยังไม่ระบุสายงาน' });

  return scopes.map((workline: any) => {
  const people = idpPeople.value.filter((person: any) => {
    return idpPersonWorklineKey(person) === workline.key;
  });
  const started = people.filter((person: any) => !person.states.every((state: string) => state === 'not_started')).length;
  const completed = people.filter((person: any) => person.status === 'passed').length;
  const active = people.filter((person: any) => ['developing', 'overdue'].includes(person.status)).length;
  const waiting = people.filter((person: any) => ['plan_review', 'result_review'].includes(person.status)).length;
  const draft = people.filter((person: any) => ['draft', 'revision_required', 'failed'].includes(person.status)).length;
  const notStarted = people.filter((person: any) => person.status === 'not_started').length;
  return {
    ...workline,
    requiredIdp: people.length,
    startedIdp: started,
    completedIdp: completed,
    activeIdp: active,
    waitingIdp: waiting,
    draftIdp: draft,
    notStartedIdp: notStarted,
  };
  });
});

const changeRound = () => {
  if (!selectedRoundId.value || Number(selectedRoundId.value) === Number(data.value.round?.id)) return;

  isChangingRound.value = true;
  router.get(route('dashboard'), {
    assessment_round_id: selectedRoundId.value,
  }, {
    only: [props.reloadProp || 'facultyAnalytics'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onError: () => {
      selectedRoundId.value = data.value.round?.id || null;
    },
    onFinish: () => {
      isChangingRound.value = false;
    },
  });
};

watch([typeFilter, search], () => {
  selectedCell.value = null;
  selectedWorklineKey.value = null;
});

watch(() => data.value.round?.id, (roundId) => {
  selectedRoundId.value = roundId || null;
  typeFilter.value = 'all';
  search.value = '';
  attentionSearch.value = '';
  attentionFilter.value = 'all';
  attentionWorklineFilter.value = 'all';
  selectedCell.value = null;
  selectedIdpPerson.value = null;
});

const scopes = [
  { key: 'faculty', label: 'ทั้งคณะ' },
  { key: 'academic', label: 'สายวิชาการ' },
  { key: 'support', label: 'สายสนับสนุน' },
];
const stateMeta: Record<string, { label: string; tone: string }> = {
  not_started: { label: 'ยังไม่เริ่ม', tone: 'muted' },
  draft: { label: 'ร่างแผน', tone: 'muted' },
  revision_required: { label: 'แก้ไขแผน', tone: 'returned' },
  plan_review: { label: 'รออนุมัติแผน', tone: 'waiting' },
  developing: { label: 'กำลังพัฒนา', tone: 'active' },
  result_review: { label: 'รอตรวจผล', tone: 'waiting' },
  passed: { label: 'ผ่าน', tone: 'success' },
  failed: { label: 'ไม่ผ่าน', tone: 'danger' },
};
const idpPersonStatusSummary = computed(() => idpPeople.value.reduce((summary: Record<string, number>, person: any) => {
  if (person.status === 'passed') summary.completed += 1;
  else if (['developing', 'overdue'].includes(person.status)) summary.active += 1;
  else if (['plan_review', 'result_review'].includes(person.status)) summary.waiting += 1;
  else if (person.status === 'not_started') summary.notStarted += 1;
  else summary.draft += 1;

  return summary;
}, {
  completed: 0,
  active: 0,
  waiting: 0,
  draft: 0,
  notStarted: 0,
}));
const idpPersonStatusSegments = computed(() => [
  { key: 'completed', label: 'เสร็จสิ้น', count: idpPersonStatusSummary.value.completed, color: 'var(--idp-complete)' },
  { key: 'active', label: 'กำลังดำเนินการ', count: idpPersonStatusSummary.value.active, color: 'var(--idp-active)' },
  { key: 'waiting', label: 'รออนุมัติ', count: idpPersonStatusSummary.value.waiting, color: 'var(--idp-waiting)' },
  { key: 'draft', label: 'Draft / ต้องแก้ไข', count: idpPersonStatusSummary.value.draft, color: 'var(--idp-draft)' },
  { key: 'not-started', label: 'ยังไม่มี IDP', count: idpPersonStatusSummary.value.notStarted, color: 'var(--idp-not-started)' },
]);
const idpDraftAndNotStarted = computed(() => idpPersonStatusSummary.value.draft + idpPersonStatusSummary.value.notStarted);

const formatNumber = (value: any) => Number(value || 0).toLocaleString('th-TH');
const worklineCompletionRate = (workline: any) => Number(workline.totalEmployees || 0)
  ? Math.round((Number(workline.assessedEmployees || 0) / Number(workline.totalEmployees)) * 100)
  : 0;
const worklinePassedEmployees = (workline: any) => Math.max(
  Number(workline.assessedEmployees || 0) - Number(workline.employeesWithGap || 0),
  0,
);
const worklinePendingEmployees = (workline: any) => Math.max(
  Number(workline.totalEmployees || 0) - Number(workline.assessedEmployees || 0),
  0,
);
const worklineOutcomeStyle = (workline: any) => {
  const total = Math.max(Number(workline.totalEmployees || 0), 1);
  const passedEnd = (worklinePassedEmployees(workline) / total) * 100;
  const failedEnd = passedEnd + ((Number(workline.employeesWithGap || 0) / total) * 100);

  return {
    background: `conic-gradient(oklch(62% .14 151) 0 ${passedEnd}%, oklch(68% .14 35) ${passedEnd}% ${failedEnd}%, oklch(88% .015 253) ${failedEnd}% 100%)`,
  };
};
const worklineCompetencyBarWidth = (workline: any, peopleCount: number) => {
  const maximum = Math.max(...(workline.topCompetencies || []).map((item: any) => Number(item.peopleCount || 0)), 1);
  return `${Math.max((Number(peopleCount || 0) / maximum) * 100, 4)}%`;
};
const openWorkline = (key: string) => {
  selectedOrganizationKey.value = null;
  selectedWorklineKey.value = key;
};
const closeWorkline = () => {
  selectedOrganizationKey.value = null;
  selectedWorklineKey.value = null;
};
const openOrganization = (key: string) => {
  selectedOrganizationKey.value = key;
};
const closeOrganization = () => {
  selectedOrganizationKey.value = null;
};
const handleWorklineDialogKeydown = (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return;
  if (selectedOrganizationKey.value) closeOrganization();
  else closeWorkline();
};

watch(selectedWorklineKey, async (key) => {
  if (typeof document === 'undefined') return;

  document.body.style.overflow = key ? 'hidden' : '';
  if (key) {
    window.addEventListener('keydown', handleWorklineDialogKeydown);
    await nextTick();
    worklineDialog.value?.focus();
  } else {
    window.removeEventListener('keydown', handleWorklineDialogKeydown);
  }
});

onBeforeUnmount(() => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = '';
  window.removeEventListener('keydown', handleWorklineDialogKeydown);
});
const heatTone = (cell: any) => {
  const peopleCount = Number(cell?.peopleCount || 0);
  if (!peopleCount) return 'none';
  if (peopleCount === 1) return 'low';
  if (peopleCount <= 3) return 'medium';
  return 'high';
};
const openCell = (row: any, scope: any) => {
  selectedCell.value = {
    competencyCode: row.code,
    competencyName: row.name,
    scopeKey: scope.key,
    scopeLabel: scope.label,
    ...row[scope.key],
  };
};
const personIdpLabel = (key: string) => stateMeta[key]?.label || 'ยังไม่มีข้อมูล';
const attentionMeta = (person: any) => {
  if (person.status === 'overdue') return { label: 'เกินกำหนด', tone: 'danger' };
  if (person.status === 'not_started') return { label: 'ยังไม่เริ่ม IDP', tone: 'muted' };
  return { label: personIdpLabel(person.status), tone: stateMeta[person.status]?.tone || 'muted' };
};
const openIdpPerson = (person: any) => {
  selectedIdpPerson.value = person;
};
const closeIdpPerson = () => {
  selectedIdpPerson.value = null;
};
const handleIdpPersonDialogKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') closeIdpPerson();
};

watch(selectedIdpPerson, (person) => {
  if (typeof document === 'undefined') return;

  document.body.style.overflow = person ? 'hidden' : '';
  if (person) {
    window.addEventListener('keydown', handleIdpPersonDialogKeydown);
  } else {
    window.removeEventListener('keydown', handleIdpPersonDialogKeydown);
  }
});

onBeforeUnmount(() => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = '';
  window.removeEventListener('keydown', handleIdpPersonDialogKeydown);
});
</script>

<template>
  <section class="faculty-overview">
    <header class="page-heading" :class="{ 'controls-only': props.hideHeadingCopy }">
      <div v-if="!props.hideHeadingCopy">
        <h1>{{ pageTitle }}</h1>
        <p>{{ pageDescription }}</p>
      </div>
      <label class="round-selector">
        <span>รอบการประเมิน</span>
        <select v-model.number="selectedRoundId" :disabled="isChangingRound || !rounds.length" @change="changeRound">
          <option v-if="!rounds.length" :value="null">ยังไม่มีรอบการประเมิน</option>
          <option v-for="round in rounds" :key="round.id" :value="round.id">
            {{ round.name }}{{ round.isActive ? ' · กำลังใช้งาน' : '' }}
          </option>
        </select>
        <small v-if="isChangingRound">กำลังโหลดข้อมูลรอบที่เลือก…</small>
      </label>
    </header>

    <AssessmentSummaryBand
      v-if="isAssessmentModule"
      :total="summary.totalEmployees"
      :assessed="summary.assessedEmployees"
      :passed="summary.employeesWithoutGap"
      :with-gap="summary.employeesWithGap"
    />

    <section v-if="!isAssessmentModule" class="idp-summary-dashboard" aria-label="ภาพรวมสถานะ IDP">
      <div class="idp-summary-hero">
        <div class="idp-coverage">
          <span>บุคลากรไม่ผ่านเกณฑ์ที่มี IDP แล้ว</span>
          <div>
            <strong>{{ formatNumber(idpSummary.startedEmployees) }}</strong>
            <b>/ {{ formatNumber(idpSummary.requiredEmployees) }} คน</b>
          </div>
          <small v-if="!idpSummary.requiredEmployees">รอผลประเมินที่อนุมัติเพื่อระบุบุคลากรที่ต้องทำ IDP</small>
          <small v-else-if="idpPersonStatusSummary.notStarted">ยังไม่ได้ทำ IDP อีก <b>{{ formatNumber(idpPersonStatusSummary.notStarted) }} คน</b></small>
          <small v-else>ผู้ที่มี Competency Gap เริ่มจัดทำ IDP ครบแล้ว</small>
        </div>

        <div class="idp-status-chart">
          <header>
            <span>สัดส่วนตามสถานะ</span>
            <small>{{ formatNumber(idpSummary.requiredEmployees) }} คน</small>
          </header>
          <div class="idp-track" role="img" :aria-label="`สถานะ IDP ของบุคลากร ${formatNumber(idpSummary.requiredEmployees)} คน`">
            <i
              v-for="segment in idpPersonStatusSegments"
              :key="segment.key"
              :style="{ background: segment.color, flexGrow: segment.count }"
              :title="`${segment.label} ${formatNumber(segment.count)} คน`"
            ></i>
          </div>
          <div class="idp-legend">
            <span v-for="segment in idpPersonStatusSegments" :key="segment.key">
              <i :style="{ background: segment.color }"></i>
              {{ segment.label }} <b>{{ formatNumber(segment.count) }} คน</b>
            </span>
          </div>
        </div>
      </div>

      <div class="idp-kpi-grid" aria-label="จำนวนบุคลากรแยกตามสถานะ IDP">
        <article class="completed">
          <span>เสร็จสิ้น</span>
          <strong>{{ formatNumber(idpPersonStatusSummary.completed) }}</strong>
          <small>คน</small>
        </article>
        <article class="active">
          <span>กำลังดำเนินการ</span>
          <strong>{{ formatNumber(idpPersonStatusSummary.active) }}</strong>
          <small>คน</small>
        </article>
        <article class="waiting">
          <span>รออนุมัติ</span>
          <strong>{{ formatNumber(idpPersonStatusSummary.waiting) }}</strong>
          <small>คน</small>
        </article>
        <article class="draft">
          <span>Draft / ยังไม่มี IDP</span>
          <strong>{{ formatNumber(idpDraftAndNotStarted) }}</strong>
          <small>คน (Draft {{ formatNumber(idpPersonStatusSummary.draft) }} · ยังไม่มี IDP {{ formatNumber(idpPersonStatusSummary.notStarted) }})</small>
        </article>
      </div>
    </section>

    <section v-if="!hasCurrentModuleData" class="no-analysis data-waiting">
      <div class="empty-icon" aria-hidden="true">…</div>
      <div>
        <h2>{{ isAssessmentModule ? 'กำลังรวบรวมผลประเมินของรอบนี้' : 'กำลังรอผลประเมินเพื่อเริ่มติดตาม IDP' }}</h2>
        <p>{{ isAssessmentModule ? 'โครงรายงานพร้อมแล้ว ตัวเลขและรายละเอียดด้านล่างจะอัปเดตเมื่อมีบุคลากรได้รับการอนุมัติผลครบทุกสมรรถนะ' : 'โครงติดตามพร้อมแล้ว บุคลากรและสถานะ IDP จะเพิ่มเข้ามาเมื่อผลประเมินได้รับอนุมัติและพบ Competency Gap' }}</p>
      </div>
    </section>

    <div v-if="isAssessmentModule" class="assessment-analysis">
      <section class="workline-surface">
        <header class="section-heading">
          <div>
            <h2>ผลการประเมินแยกตามสายงาน</h2>
            <p>กดดูรายละเอียดเพื่อเปรียบเทียบผลตามหน่วยงานและสมรรถนะที่ไม่ผ่านมากที่สุด</p>
          </div>
        </header>

        <div class="workline-cards" aria-label="ผลการประเมินแยกตามสายงาน">
          <article v-for="workline in worklines" :key="workline.key" :class="{ selected: selectedWorklineKey === workline.key }">
            <header>
              <div>
                <h3>{{ workline.label }}</h3>
                <span>บุคลากรที่ต้องประเมิน {{ formatNumber(workline.totalEmployees) }} คน</span>
              </div>
              <b>{{ formatNumber(workline.totalEmployees) }} คน</b>
            </header>

            <div class="completion-copy">
              <strong>ประเมินครบทุกสมรรถนะ {{ formatNumber(workline.assessedEmployees) }} จาก {{ formatNumber(workline.totalEmployees) }} คน</strong>
              <span>ยังประเมินไม่ครบ {{ formatNumber(Math.max(workline.totalEmployees - workline.assessedEmployees, 0)) }} คน</span>
            </div>
            <div class="workline-track" role="img" :aria-label="`${workline.label} ประเมินครบทุกสมรรถนะ ${formatNumber(workline.assessedEmployees)} จาก ${formatNumber(workline.totalEmployees)} คน`">
              <i :style="{ width: `${worklineCompletionRate(workline)}%` }"></i>
            </div>

            <dl>
              <div class="passed"><dt>ประเมินครบและไม่พบ Gap</dt><dd>{{ formatNumber(worklinePassedEmployees(workline)) }} <small>คน</small></dd></div>
              <div class="gap"><dt>ประเมินครบและพบ Gap</dt><dd>{{ formatNumber(workline.employeesWithGap) }} <small>คน</small></dd></div>
            </dl>

            <button type="button" aria-haspopup="dialog" @click="openWorkline(workline.key)">
              ดูรายละเอียด{{ workline.label }}
              <span aria-hidden="true">↗</span>
            </button>
          </article>
        </div>

        <Teleport to="body">
          <div v-if="selectedWorkline" class="workline-dialog-backdrop" @click.self="closeWorkline">
            <section
              ref="worklineDialog"
              class="workline-detail-panel"
              role="dialog"
              aria-modal="true"
              :aria-hidden="selectedOrganizationGroup ? 'true' : undefined"
              aria-labelledby="workline-dialog-title"
              tabindex="-1"
            >
              <header>
                <div>
                  <span>รายละเอียดสายงาน</span>
                  <h3 id="workline-dialog-title">{{ selectedWorkline.label }}</h3>
                </div>
                <button type="button" aria-label="ปิดหน้าต่างรายละเอียดสายงาน" @click="closeWorkline">×</button>
              </header>
              <div class="workline-dialog-content">
                <section class="workline-overall-dashboard" :aria-label="`ภาพรวมผลการประเมิน${selectedWorkline.label}`">
                <header class="workline-dashboard-heading">
                  <div>
                    <span>ภาพรวม</span>
                    <strong>ผลการประเมิน{{ selectedWorkline.label }}</strong>
                  </div>
                  <small>นับเฉพาะบุคลากรที่ต้องประเมินตนเอง</small>
                </header>

                <dl class="workline-dashboard-counts">
                  <div><dt>บุคลากรทั้งหมด</dt><dd>{{ formatNumber(selectedWorkline.totalEmployees) }} <small>คน</small></dd></div>
                  <div><dt>ประเมินครบทุกสมรรถนะ</dt><dd>{{ formatNumber(selectedWorkline.assessedEmployees) }} <small>คน</small></dd></div>
                  <div class="passed"><dt>ผ่านเกณฑ์</dt><dd>{{ formatNumber(worklinePassedEmployees(selectedWorkline)) }} <small>คน</small></dd></div>
                  <div class="failed"><dt>มี Competency Gap</dt><dd>{{ formatNumber(selectedWorkline.employeesWithGap) }} <small>คน</small></dd></div>
                </dl>

                <div class="workline-outcome-chart">
                  <div class="workline-panel-title">
                    <strong>ผลการประเมิน</strong>
                    <span>แยกตามสถานะของบุคลากร</span>
                  </div>
                  <div class="workline-chart-body">
                    <div class="workline-donut" :style="worklineOutcomeStyle(selectedWorkline)">
                      <div><strong>{{ formatNumber(selectedWorkline.totalEmployees) }}</strong><span>คนในสายงาน</span></div>
                    </div>
                    <div class="workline-chart-legend">
                      <span><i class="passed"></i><b>ผ่านเกณฑ์</b><strong>{{ formatNumber(worklinePassedEmployees(selectedWorkline)) }} คน</strong></span>
                      <span><i class="failed"></i><b>มี Competency Gap</b><strong>{{ formatNumber(selectedWorkline.employeesWithGap) }} คน</strong></span>
                      <span><i class="pending"></i><b>ยังประเมินไม่ครบ</b><strong>{{ formatNumber(worklinePendingEmployees(selectedWorkline)) }} คน</strong></span>
                    </div>
                  </div>
                </div>

                <div class="workline-ranking">
                  <header><strong>สมรรถนะที่ตกมากที่สุด</strong><span>รวมทั้งสายงาน สูงสุด 3 อันดับ</span></header>
                  <ol v-if="selectedWorkline.topCompetencies?.length">
                    <li v-for="(competency, index) in selectedWorkline.topCompetencies" :key="competency.competencyId">
                      <b>{{ index + 1 }}</b>
                      <div><span>{{ competency.name }}</span><i><em :style="{ width: worklineCompetencyBarWidth(selectedWorkline, competency.peopleCount) }"></em></i></div>
                      <strong>{{ formatNumber(competency.peopleCount) }} คน</strong>
                    </li>
                  </ol>
                  <div v-else class="workline-ranking-empty">ยังไม่มีผู้พบ Competency Gap ในสายงานนี้</div>
                </div>
                </section>

                <section class="workline-organizations-frame">
                  <div class="organization-summary-head">
                    <span>{{ selectedWorkline.key === 'academic' ? 'ภาควิชาในสายวิชาการ' : 'ฝ่ายในสายสนับสนุน' }}</span>
                    <small>{{ selectedWorkline.key === 'academic' ? 'เลือกภาควิชาเพื่อเปิด dashboard ผลการประเมิน' : 'เลือกฝ่ายเพื่อเปิด dashboard และดูงานกับหน่วยภายใน' }}</small>
                  </div>
                  <div v-if="organizationGroups.length" class="division-list">
                    <button
                      v-for="group in organizationGroups"
                      :key="group.division.key"
                      type="button"
                      class="division-overview-row"
                      aria-haspopup="dialog"
                      @click="openOrganization(group.division.key)"
                    >
                      <div class="division-name">
                        <small>{{ selectedWorkline.key === 'academic' ? 'ภาควิชา' : 'ฝ่าย' }}</small>
                        <strong>{{ group.division.label }}</strong>
                        <span>{{ selectedWorkline.key === 'academic' ? 'ผลการประเมินรายภาควิชา' : `${formatNumber(group.works.length)} งานภายในฝ่าย` }}</span>
                      </div>
                      <div class="division-counts">
                        <span class="passed"><b>{{ formatNumber(group.division.passedEmployees) }}</b> ผ่าน</span>
                        <span class="failed"><b>{{ formatNumber(group.division.failedEmployees) }}</b> ไม่ผ่าน</span>
                        <span v-if="group.division.pendingEmployees" class="pending"><b>{{ formatNumber(group.division.pendingEmployees) }}</b> รอผล</span>
                      </div>
                      <div class="division-result-track" role="img" :aria-label="`${group.division.label} ผ่าน ${formatNumber(group.division.passedEmployees)} คน ไม่ผ่าน ${formatNumber(group.division.failedEmployees)} คน รอผล ${formatNumber(group.division.pendingEmployees)} คน`">
                        <i class="passed" :style="{ flexGrow: group.division.passedEmployees }"></i>
                        <i class="failed" :style="{ flexGrow: group.division.failedEmployees }"></i>
                        <i class="pending" :style="{ flexGrow: group.division.pendingEmployees }"></i>
                      </div>
                      <div class="division-total"><b>{{ formatNumber(group.division.totalEmployees) }}</b><span>คนทั้งหมด</span></div>
                      <span class="division-open-label">ดู dashboard <b aria-hidden="true">›</b></span>
                    </button>
                  </div>
                  <div v-else class="workline-detail-empty">ยังไม่มีข้อมูลหน่วยงานในสายงานนี้</div>
                </section>
              </div>
            </section>

            <SupportDivisionDialog
              v-if="selectedOrganizationGroup"
              :group="selectedOrganizationGroup"
              :scope="selectedWorkline.key === 'academic' ? 'academic' : 'support'"
              @close="closeOrganization"
            />
          </div>
        </Teleport>
      </section>

      <section class="gap-surface">
        <header class="heatmap-heading">
          <div>
            <h2>ภาพรวม Competency Gap รายสมรรถนะ</h2>
            <p>ตัวเลขในแต่ละช่องคือจำนวนบุคลากรที่พบ Gap กดเพื่อดูรายชื่อ</p>
          </div>
          <div class="heatmap-tools">
            <div class="type-filter" aria-label="กรองประเภทสมรรถนะ">
              <button v-for="type in ['all', 'CC', 'MC', 'FC']" :key="type" type="button" :class="{ active: typeFilter === type }" @click="typeFilter = type">
                {{ type === 'all' ? 'ทั้งหมด' : type }}
              </button>
            </div>
            <input v-model="search" type="search" placeholder="ค้นหาสมรรถนะ" aria-label="ค้นหาสมรรถนะ">
          </div>
        </header>
        <div class="heat-legend" aria-label="คำอธิบายสี">
          <strong>เกณฑ์สีตามจำนวนคน</strong>
          <span><i class="none"></i>ไม่พบ Gap</span>
          <span><i class="low"></i>1 คน</span>
          <span><i class="medium"></i>2–3 คน</span>
          <span><i class="high"></i>4 คนขึ้นไป</span>
        </div>

        <div class="heatmap-wrap">
        <table class="heatmap-table">
          <thead>
            <tr>
              <th>สมรรถนะ</th>
              <th v-for="scope in scopes" :key="scope.key">{{ scope.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in visibleHeatmap" :key="row.competencyId">
              <th scope="row">
                <span>{{ row.code }} · {{ row.type }}</span>
                <strong>{{ row.name }}</strong>
              </th>
              <td v-for="scope in scopes" :key="scope.key">
                <button
                  type="button"
                  class="heat-cell"
                  :class="[heatTone(row[scope.key]), { selected: selectedCell?.competencyCode === row.code && selectedCell?.scopeKey === scope.key }]"
                  :disabled="!row[scope.key].peopleCount"
                  @click="openCell(row, scope)"
                >
                  <strong><b>{{ formatNumber(row[scope.key].peopleCount) }}</b> คน</strong>
                  <small>{{ row[scope.key].peopleCount ? 'ดูรายชื่อบุคลากร' : 'ไม่พบ Gap' }}</small>
                  <span v-if="row[scope.key].peopleCount" aria-hidden="true">›</span>
                </button>
              </td>
            </tr>
            <tr v-if="!visibleHeatmap.length" class="heatmap-empty-row">
              <td colspan="4">
                <strong>{{ hasAssessmentData ? 'ยังไม่พบ Competency Gap ในขอบเขตนี้' : 'รอผลประเมินที่ได้รับอนุมัติ' }}</strong>
                <span>{{ hasAssessmentData ? 'ลองเปลี่ยนประเภทสมรรถนะหรือคำค้นหา' : 'รายการสมรรถนะและจำนวนผู้พบ Gap จะเพิ่มเข้ามาอัตโนมัติ' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        </div>

        <section v-if="selectedCell" class="cell-detail">
        <header>
          <div>
            <span>{{ selectedCell.scopeLabel }}</span>
            <h3>{{ selectedCell.competencyCode }} · {{ selectedCell.competencyName }}</h3>
          </div>
          <button type="button" aria-label="ปิดรายละเอียด" @click="selectedCell = null">ปิด</button>
        </header>
        <div class="detail-facts">
          <span>พบ Competency Gap <b>{{ formatNumber(selectedCell.peopleCount) }}</b> คน</span>
          <span><b>{{ formatNumber(selectedCell.overdueCount) }}</b> IDP ล่าช้า</span>
        </div>
        <div class="people-list">
          <article v-for="person in selectedCell.people" :key="person.userId">
            <div>
              <strong>{{ person.name || 'ไม่ระบุชื่อ' }}</strong>
              <small>{{ person.position || person.organization || 'ไม่ระบุตำแหน่ง' }}</small>
            </div>
            <span>ระดับ {{ person.actualLevel ?? '-' }} / คาดหวัง {{ person.expectedLevel ?? '-' }}</span>
            <b>Gap {{ person.gap }}</b>
            <em :class="{ overdue: person.isOverdue }">{{ person.isOverdue ? 'IDP ล่าช้า' : personIdpLabel(person.idpStatus) }}</em>
          </article>
        </div>
        </section>
      </section>
    </div>

    <template v-if="!isAssessmentModule">
      <section class="idp-tracking-grid">
        <div v-if="!props.hideWorklineBreakdown" class="idp-worklines">
          <header class="tracking-panel-heading">
            <div>
              <span>แยกตามสายงาน</span>
              <h2>ความคืบหน้า IDP</h2>
              <p>เปรียบเทียบสถานะของสายวิชาการและสายสนับสนุน</p>
            </div>
            <strong>{{ formatNumber(idpSummary.startedEmployees) }} / {{ formatNumber(idpSummary.requiredEmployees) }} คนเริ่มแล้ว</strong>
          </header>
          <div class="workline-idp-list">
            <article v-for="workline in idpWorklines" :key="workline.key">
              <header>
                <div>
                  <strong>{{ workline.label }}</strong>
                  <span>ต้องทำ IDP {{ formatNumber(workline.requiredIdp) }} คน</span>
                </div>
                <b>{{ formatNumber(workline.requiredIdp) }} คน</b>
              </header>

              <div class="workline-idp-outcomes">
                <span class="completed"><i></i>เสร็จสิ้น <b>{{ formatNumber(workline.completedIdp) }}</b></span>
                <span class="active"><i></i>กำลังดำเนินการ <b>{{ formatNumber(workline.activeIdp) }}</b></span>
                <span class="waiting"><i></i>รออนุมัติ <b>{{ formatNumber(workline.waitingIdp) }}</b></span>
                <span class="draft"><i></i>Draft / ต้องแก้ไข <b>{{ formatNumber(workline.draftIdp) }}</b></span>
                <span class="not-started"><i></i>ยังไม่มี IDP <b>{{ formatNumber(workline.notStartedIdp) }}</b></span>
              </div>
              <div class="idp-progress" role="img" :aria-label="`${workline.label} เสร็จสิ้น ${formatNumber(workline.completedIdp)} คน กำลังดำเนินการ ${formatNumber(workline.activeIdp)} คน รออนุมัติ ${formatNumber(workline.waitingIdp)} คน Draft หรือรอแก้ไข ${formatNumber(workline.draftIdp)} คน ยังไม่มี IDP ${formatNumber(workline.notStartedIdp)} คน`">
                <i class="completed" :style="{ flexGrow: workline.completedIdp }"></i>
                <i class="active" :style="{ flexGrow: workline.activeIdp }"></i>
                <i class="waiting" :style="{ flexGrow: workline.waitingIdp }"></i>
                <i class="draft" :style="{ flexGrow: workline.draftIdp }"></i>
                <i class="not-started" :style="{ flexGrow: workline.notStartedIdp }"></i>
              </div>
            </article>
          </div>
        </div>

        <div class="idp-attention">
          <header class="tracking-panel-heading">
            <div>
              <span>ติดตามรายบุคคล</span>
              <h2>บุคลากรที่ทำ IDP</h2>
            </div>
            <strong>{{ formatNumber(idpPeople.length) }} คน</strong>
          </header>

          <div v-if="idpPeople.length" class="attention-toolbar">
            <label class="attention-search">
              <span>ค้นหาบุคลากรหรือสมรรถนะ</span>
              <input v-model="attentionSearch" type="search" placeholder="ชื่อ ตำแหน่ง หรือชื่อสมรรถนะ">
            </label>
            <label>
              <span>สายงาน</span>
              <select v-model="attentionWorklineFilter">
                <option v-for="filter in idpAttentionWorklineFilters" :key="filter.key" :value="filter.key">
                  {{ filter.label }} ({{ formatNumber(filter.count) }})
                </option>
              </select>
            </label>
            <label>
              <span>สถานะ IDP</span>
              <select v-model="attentionFilter">
                <option v-for="filter in idpAttentionFilters" :key="filter.key" :value="filter.key">
                  {{ filter.label }} ({{ formatNumber(filter.count) }})
                </option>
              </select>
            </label>
            <div class="attention-result-count">
              <span>ผลลัพธ์</span>
              <strong>{{ formatNumber(filteredIdpAttentionPeople.length) }} คน</strong>
            </div>
          </div>

          <div v-if="filteredIdpAttentionPeople.length" class="attention-results">
            <div class="attention-list-head" aria-hidden="true">
              <span>บุคลากร</span>
              <span>สมรรถนะที่ต้องพัฒนา</span>
              <span>สถานะ</span>
            </div>
            <div class="attention-list">
              <button
                v-for="person in filteredIdpAttentionPeople"
                :key="person.userId"
                type="button"
                class="attention-row"
                :aria-label="`ดูรายละเอียด IDP ของ ${person.name || 'บุคลากร'}`"
                @click="openIdpPerson(person)"
              >
                <div class="attention-person">
                  <span class="person-initial">{{ String(person.name || '?').trim().charAt(0) }}</span>
                  <div>
                    <strong>{{ person.name || 'ไม่ระบุชื่อ' }}</strong>
                    <small>{{ person.position || person.organization || 'ไม่ระบุหน่วยงาน' }}<template v-if="person.workline"> · {{ person.workline }}</template></small>
                  </div>
                </div>
                <div class="attention-competencies">
                  <span
                    v-for="competency in person.competencies.slice(0, 2)"
                    :key="competency.code"
                    :title="`${competency.code} · ${competency.name}`"
                  >{{ competency.name }}</span>
                  <b v-if="person.competencies.length > 2">+{{ formatNumber(person.competencies.length - 2) }}</b>
                  <small>{{ formatNumber(person.competencies.length) }} สมรรถนะ</small>
                </div>
                <span class="attention-status">
                  <em :class="attentionMeta(person).tone">{{ attentionMeta(person).label }}</em>
                  <b aria-hidden="true">›</b>
                </span>
              </button>
            </div>
          </div>
          <div v-else class="attention-empty">
            {{ idpPeople.length
              ? 'ไม่พบบุคลากรตามตัวกรองที่เลือก'
              : (hasAssessmentData ? 'ยังไม่มีบุคลากรที่ต้องทำ IDP' : 'รอผลประเมินที่อนุมัติเพื่อแสดงบุคลากรที่ต้องทำ IDP') }}
          </div>
        </div>
      </section>

    </template>

    <IdpActivityProgressReview
      v-if="selectedIdpPerson && selectedIdpDetailItems.length"
      :key="selectedIdpPerson.userId"
      :items="selectedIdpDetailItems"
      mode="tracking"
      dialog-only
      @close="closeIdpPerson"
    />
  </section>
</template>

<style scoped>
.faculty-overview {
  --ink: oklch(31% .025 162);
  --muted: oklch(55% .018 162);
  --line: oklch(88% .018 162);
  --surface: oklch(99% .006 162);
  --surface-soft: oklch(96.5% .012 162);
  --green: oklch(48% .105 162);
  --chart-blue: oklch(67% .15 222);
  --chart-green: oklch(72% .17 151);
  --chart-yellow: oklch(82% .15 85);
  --chart-orange: oklch(70% .18 55);
  --chart-red: oklch(66% .18 28);
  --chart-deep-red: oklch(54% .2 28);
  --idp-complete: oklch(62% .15 145);
  --idp-active: oklch(64% .115 194);
  --idp-waiting: oklch(80% .15 86);
  --idp-draft: oklch(72% .16 55);
  --idp-not-started: oklch(66% .18 28);
  display: grid;
  gap: 18px;
  color: var(--ink);
  font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
}
.page-heading, .section-heading, .heatmap-heading, .cell-detail > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
.page-heading.controls-only { justify-content: flex-end; }
.page-heading h1 { margin: 0; font-size: 23px; font-weight: 900; }
.page-heading p, .section-heading p, .heatmap-heading p { margin: 4px 0 0; color: var(--muted); font-size: 12px; }
.round-selector { display: grid; gap: 4px; min-width: min(330px, 100%); }.round-selector > span { color: var(--muted); font-size: 11px; font-weight: 700; }.round-selector select { width: 100%; min-height: 40px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); padding: 7px 34px 7px 11px; color: var(--ink); font: inherit; font-size: 13px; font-weight: 600; outline: none; }.round-selector select:focus-visible { border-color: var(--green); box-shadow: 0 0 0 3px oklch(82% .07 162 / .32); }.round-selector select:disabled { cursor: wait; opacity: .65; }.round-selector small { color: var(--muted); font-size: 10px; }
.idp-summary-dashboard { display: grid; gap: 14px; }
.idp-summary-hero { display: grid; grid-template-columns: minmax(280px, .48fr) minmax(0, 1.52fr); align-items: center; gap: 34px; overflow: hidden; border: 1px solid oklch(85% .035 185); border-radius: 14px; background: linear-gradient(115deg, var(--surface) 0%, oklch(97.5% .02 185) 100%); padding: 23px 28px; box-shadow: inset 0 4px 0 var(--idp-active), 0 8px 22px oklch(35% .025 185 / .07); }
.idp-coverage > span, .idp-status-chart header > span { color: oklch(58% .025 253); font-size: 13px; font-weight: 800; }
.idp-coverage > div { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; margin: 8px 0 5px; }
.idp-coverage strong { color: oklch(53% .13 185); font-size: 39px; font-weight: 900; line-height: 1; }
.idp-coverage b { color: oklch(59% .025 253); font-size: 16px; }
.idp-coverage small { color: var(--muted); font-size: 11px; }
.idp-coverage small b { color: var(--idp-not-started); font-size: inherit; }
.idp-status-chart { display: grid; gap: 10px; }
.idp-status-chart header { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
.idp-status-chart header small { color: var(--muted); font-size: 10px; font-weight: 700; }
.idp-track { display: flex; overflow: hidden; height: 18px; border-radius: 999px; background: oklch(91% .012 185); }
.idp-track i { flex-basis: 0; min-width: 0; transition: flex-grow 180ms cubic-bezier(.22,1,.36,1); }
.idp-track i:first-child { border-radius: 999px 0 0 999px; }
.idp-track i:last-child { border-radius: 0 999px 999px 0; }
.idp-legend { display: flex; flex-wrap: wrap; gap: 7px 14px; }
.idp-legend span { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 10px; white-space: nowrap; }
.idp-legend span > i { width: 9px; height: 9px; border-radius: 3px; }
.idp-legend span > b { color: var(--ink); font-size: inherit; }
.idp-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.idp-kpi-grid article { display: grid; align-content: center; min-height: 116px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); padding: 17px 20px; box-shadow: 0 4px 12px oklch(32% .02 162 / .06); }
.idp-kpi-grid span { color: oklch(59% .025 253); font-size: 12px; font-weight: 800; }
.idp-kpi-grid strong { margin-top: 5px; font-size: 30px; font-weight: 900; line-height: 1; }
.idp-kpi-grid small { margin-top: 6px; color: oklch(62% .025 253); font-size: 10px; }
.idp-kpi-grid .completed strong { color: var(--idp-complete); }
.idp-kpi-grid .active strong { color: var(--idp-active); }
.idp-kpi-grid .waiting strong { color: var(--idp-waiting); }
.idp-kpi-grid .draft strong { color: oklch(56% .2 28); }
.idp-tracking-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; }
.idp-worklines, .idp-attention { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); box-shadow: 0 4px 14px oklch(32% .02 162 / .045); }
.tracking-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid var(--line); background: oklch(97.5% .012 162); padding: 16px 19px; }
.tracking-panel-heading > div { display: grid; gap: 2px; }
.tracking-panel-heading span { color: var(--green); font-size: 11px; font-weight: 900; letter-spacing: .02em; }
.tracking-panel-heading h2 { margin: 0; font-size: 18px; font-weight: 900; }
.tracking-panel-heading p { margin: 2px 0 0; color: var(--muted); font-size: 12px; }
.tracking-panel-heading > strong { border-radius: 999px; background: oklch(91.5% .045 162); padding: 6px 10px; color: oklch(43% .11 162); font-size: 12px; font-weight: 900; white-space: nowrap; }
.workline-idp-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; background: oklch(96.5% .012 162); padding: 18px; }
.workline-idp-list article { display: grid; align-content: start; gap: 13px; min-width: 0; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); padding: 20px; }
.workline-idp-list article > header { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
.workline-idp-list article > header > div { display: grid; gap: 2px; }
.workline-idp-list article > header strong { font-size: 15px; }
.workline-idp-list article > header span { color: var(--muted); font-size: 12px; }
.workline-idp-list article > header > b { border-radius: 999px; background: oklch(89.5% .065 162); padding: 8px 13px; color: oklch(42% .105 162); font-size: 14px; font-weight: 900; line-height: 1; text-align: center; white-space: nowrap; }
.workline-idp-outcomes { display: flex; flex-wrap: wrap; gap: 7px 14px; }
.workline-idp-outcomes span { display: inline-flex; align-items: center; gap: 5px; color: var(--muted); font-size: 11px; }
.workline-idp-outcomes i { width: 8px; height: 8px; border-radius: 3px; }
.workline-idp-outcomes b { color: var(--ink); font-size: 12px; }
.workline-idp-outcomes .completed i, .idp-progress .completed { background: var(--idp-complete); }
.workline-idp-outcomes .active i, .idp-progress .active { background: var(--idp-active); }
.workline-idp-outcomes .waiting i, .idp-progress .waiting { background: var(--idp-waiting); }
.workline-idp-outcomes .draft i, .idp-progress .draft { background: var(--idp-draft); }
.workline-idp-outcomes .not-started i, .idp-progress .not-started { background: var(--idp-not-started); }
.idp-progress { display: flex; overflow: hidden; height: 9px; border-radius: 999px; background: oklch(91% .012 185); }
.idp-progress i { flex-basis: 0; min-width: 0; height: 100%; }
.idp-attention .tracking-panel-heading { padding: 14px 18px; }
.attention-toolbar { display: grid; grid-template-columns: minmax(260px, 1fr) 190px 210px auto; align-items: end; gap: 10px; border-bottom: 1px solid var(--line); background: oklch(97.5% .01 162); padding: 14px 18px; }
.attention-toolbar label { display: grid; gap: 5px; min-width: 0; }
.attention-toolbar label > span, .attention-result-count > span { color: var(--muted); font-size: 10px; font-weight: 800; }
.attention-toolbar input, .attention-toolbar select { width: 100%; min-height: 40px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); padding: 7px 10px; color: var(--ink); font: inherit; font-size: 11px; outline: none; }
.attention-toolbar input:focus, .attention-toolbar select:focus-visible { border-color: oklch(65% .09 162); box-shadow: 0 0 0 3px oklch(82% .07 162 / .28); }
.attention-result-count { display: grid; gap: 4px; min-width: 82px; justify-items: end; padding-bottom: 4px; }
.attention-result-count strong { color: var(--green); font-size: 15px; }
.attention-results { min-width: 0; }
.attention-list-head { display: grid; grid-template-columns: minmax(260px, .8fr) minmax(300px, 1.2fr) 130px; gap: 16px; border-bottom: 1px solid var(--line); background: oklch(96.5% .012 162); padding: 9px 18px; color: var(--muted); font-size: 10px; font-weight: 800; }
.attention-list { max-height: 460px; overflow-y: auto; scrollbar-gutter: stable; }
.attention-row { display: grid; grid-template-columns: minmax(260px, .8fr) minmax(300px, 1.2fr) 130px; align-items: center; gap: 16px; width: 100%; min-height: 72px; border: 0; background: transparent; padding: 11px 18px; color: inherit; font: inherit; text-align: left; cursor: pointer; transition: background-color 150ms ease-out; }
.attention-row + .attention-row { border-top: 1px solid var(--line); }
.attention-row:hover { background: oklch(97% .016 162); }
.attention-row:focus-visible { position: relative; z-index: 1; outline: 3px solid oklch(76% .09 162 / .5); outline-offset: -3px; }
.person-initial { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: oklch(91% .035 253); color: oklch(36% .09 253); font-size: 12px; font-weight: 900; }
.attention-person { display: grid; grid-template-columns: 36px minmax(0, 1fr); align-items: center; gap: 10px; min-width: 0; }
.attention-person > div { display: grid; gap: 2px; min-width: 0; }
.attention-person strong { overflow: hidden; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
.attention-person small { overflow: hidden; color: var(--muted); font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.attention-competencies { display: flex; align-items: center; gap: 5px; min-width: 0; }
.attention-competencies > span { overflow: hidden; max-width: 190px; border-radius: 999px; background: oklch(94.5% .018 162); padding: 5px 8px; color: oklch(43% .055 162); font-size: 9px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.attention-competencies > b { flex: none; border-radius: 999px; background: oklch(90% .035 162); padding: 5px 7px; color: var(--green); font-size: 9px; }
.attention-competencies > small { display: none; color: var(--muted); font-size: 10px; }
.attention-status { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.attention-status em { border-radius: 999px; background: oklch(93% .015 162); padding: 5px 8px; color: var(--muted); font-size: 11px; font-style: normal; font-weight: 800; white-space: nowrap; }
.attention-status em.active { background: oklch(92% .045 185); color: oklch(43% .11 185); }
.attention-status em.waiting { background: oklch(94% .06 87); color: oklch(47% .11 72); }
.attention-status em.returned, .attention-status em.danger { background: oklch(93% .055 35); color: oklch(48% .14 35); }
.attention-status > b { color: oklch(53% .07 162); font-size: 20px; font-weight: 500; line-height: 1; transition: transform 150ms ease-out; }
.attention-row:hover .attention-status > b { transform: translateX(3px); }
.attention-empty { display: grid; min-height: 210px; place-items: center; padding: 20px; color: var(--muted); font-size: 12px; text-align: center; }
.no-analysis { display: flex; align-items: center; gap: 14px; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); padding: 24px; }.no-analysis .empty-icon { display: grid; place-items: center; flex: none; width: 38px; height: 38px; border-radius: 10px; background: oklch(94% .035 75); color: oklch(52% .11 65); font-size: 18px; font-weight: 900; }.no-analysis h2 { margin: 0; font-size: 15px; }.no-analysis p { margin: 4px 0 0; color: var(--muted); font-size: 11px; }
.no-analysis.data-waiting { border-color: oklch(86% .035 162); background: oklch(98% .012 162); padding: 16px 20px; }
.no-analysis.data-waiting .empty-icon { background: oklch(92% .045 162); color: var(--green); }
.no-analysis.data-waiting h2 { color: var(--ink); }
.assessment-analysis { display: grid; gap: 18px; }.workline-surface, .gap-surface { overflow: hidden; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); }.workline-surface > .section-heading { padding: 18px 20px; }
.section-heading h2, .heatmap-heading h2 { margin: 0; font-size: 16px; font-weight: 900; }
.workline-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; border-top: 1px solid var(--line); padding: 16px 20px 20px; background: var(--surface-soft); }.workline-cards > article { display: grid; gap: 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); padding: 18px; transition: border-color 160ms ease-out, box-shadow 160ms ease-out; }.workline-cards > article.selected { border-color: oklch(61% .09 162); box-shadow: 0 0 0 3px oklch(83% .055 162 / .28); }.workline-cards > article > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }.workline-cards h3 { margin: 0; font-size: 16px; }.workline-cards header span { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; }.workline-cards header b { border-radius: 999px; background: oklch(92% .045 162); padding: 6px 10px; color: var(--green); font-size: 12px; white-space: nowrap; }.completion-copy { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }.completion-copy strong { font-size: 11px; }.completion-copy span { color: var(--muted); font-size: 9px; white-space: nowrap; }.workline-track { overflow: hidden; height: 8px; border-radius: 999px; background: oklch(92% .012 162); }.workline-track i { display: block; height: 100%; border-radius: inherit; background: var(--chart-blue); transition: width 180ms cubic-bezier(.22,1,.36,1); }.workline-cards dl { display: grid; grid-template-columns: repeat(2, 1fr); margin: 0; border: 1px solid var(--line); border-radius: 8px; background: var(--surface-soft); }.workline-cards dl > div { display: grid; align-content: space-between; gap: 8px; min-height: 76px; padding: 11px; }.workline-cards dl > div + div { border-left: 1px solid var(--line); }.workline-cards dt { color: var(--muted); font-size: 9px; line-height: 1.35; }.workline-cards dd { margin: 0; color: var(--ink); font-size: 21px; font-weight: 900; line-height: 1; }.workline-cards dd small { color: var(--muted); font-size: 9px; font-weight: 600; }.workline-cards .passed dd { color: oklch(52% .13 145); }.workline-cards .gap dd { color: oklch(55% .15 52); }.workline-cards > article > button { display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 40px; border: 1px solid oklch(82% .04 162); border-radius: 8px; background: oklch(97% .012 162); padding: 8px 11px; color: var(--green); font: inherit; font-size: 10px; font-weight: 900; cursor: pointer; }.workline-cards > article > button:hover { background: oklch(94% .025 162); }.workline-cards > article > button:focus-visible { outline: 3px solid oklch(79% .08 162 / .45); outline-offset: 2px; }
.workline-detail-panel { margin: 0 20px 20px; overflow: hidden; border: 1px solid oklch(80% .045 162); border-radius: 10px; background: var(--surface); }.workline-detail-panel > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--line); padding: 14px 16px; }.workline-detail-panel > header span { color: var(--green); font-size: 9px; font-weight: 900; }.workline-detail-panel h3 { margin: 2px 0 0; font-size: 15px; }.workline-detail-panel > header button { border: 1px solid var(--line); border-radius: 7px; background: var(--surface); padding: 6px 9px; color: var(--muted); font: inherit; font-size: 9px; font-weight: 800; cursor: pointer; }.gap-definition { display: flex; gap: 8px 14px; border-bottom: 1px solid var(--line); background: oklch(97% .018 85); padding: 10px 16px; font-size: 9px; line-height: 1.5; }.gap-definition strong { flex: none; color: oklch(48% .11 65); }.gap-definition span { color: var(--muted); }.workline-detail-grid { display: grid; grid-template-columns: 1fr 1fr; }.workline-detail-grid > div + div { border-left: 1px solid var(--line); }.workline-detail-grid h4 { margin: 0; border-bottom: 1px solid var(--line); padding: 12px 15px; font-size: 11px; }.workline-competency-list, .workline-person-list { max-height: 310px; overflow-y: auto; }.workline-competency-list article { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 12px; padding: 10px 15px; }.workline-competency-list article + article, .workline-person-list article + article { border-top: 1px solid var(--line); }.workline-competency-list article > span { color: var(--green); font-size: 8px; font-weight: 900; }.workline-competency-list article > strong { overflow: hidden; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }.workline-competency-list article > small { grid-row: 1 / span 2; grid-column: 2; align-self: center; color: var(--muted); font-size: 8px; }.workline-competency-list article > small span { color: oklch(49% .15 30); }.workline-person-list article { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; align-items: center; gap: 9px; padding: 9px 14px; }.workline-person-list article > span { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: oklch(32% .075 253); color: oklch(97% .005 253); font-size: 10px; font-weight: 900; }.workline-person-list article > div { display: grid; gap: 2px; min-width: 0; }.workline-person-list strong { overflow: hidden; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }.workline-person-list small { overflow: hidden; color: var(--muted); font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }.workline-person-list b { color: oklch(49% .14 35); font-size: 9px; }.workline-detail-empty { display: grid; min-height: 110px; place-items: center; color: var(--muted); font-size: 10px; }
.workline-dialog-backdrop {
  --ink: oklch(31% .025 162);
  --muted: oklch(55% .018 162);
  --line: oklch(88% .018 162);
  --surface: oklch(99% .006 162);
  --surface-soft: oklch(96.5% .012 162);
  --green: oklch(48% .105 162);
  position: fixed;
  z-index: 1000;
  inset: 0;
  display: grid;
  place-items: center;
  overflow-y: auto;
  background: oklch(18% .025 253 / .58);
  padding: 24px;
  color: var(--ink);
  font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif;
}
.workline-dialog-backdrop .workline-detail-panel { width: min(1180px, 100%); max-height: calc(100vh - 48px); margin: 0; overflow: auto; border-color: oklch(78% .025 162); border-radius: 12px; background: var(--surface-soft); box-shadow: 0 24px 64px oklch(18% .025 253 / .28); outline: none; }
.workline-dialog-backdrop .workline-detail-panel > header { position: sticky; z-index: 2; top: 0; background: var(--surface); padding: 16px 18px; }
.workline-dialog-backdrop .workline-detail-panel > header span { font-size: 10px; }
.workline-dialog-backdrop .workline-detail-panel h3 { font-size: 18px; }
.workline-dialog-backdrop .workline-detail-panel > header button { display: grid; width: 40px; height: 40px; place-items: center; padding: 0; border-radius: 9px; font-size: 22px; line-height: 1; }
.workline-dialog-backdrop .workline-detail-panel > header button:hover { background: var(--surface-soft); color: var(--ink); }
.workline-dialog-backdrop .workline-detail-panel > header button:focus-visible { outline: 3px solid oklch(79% .08 162 / .45); outline-offset: 2px; }
.workline-dialog-content { display: grid; gap: 16px; padding: 16px; }
.workline-overall-dashboard { display: grid; grid-template-columns: minmax(300px, .78fr) minmax(430px, 1.22fr); grid-template-areas: 'heading heading' 'counts counts' 'chart ranking'; gap: 0; overflow: hidden; border: 1px solid oklch(84% .035 162); border-radius: 11px; background: oklch(97.5% .012 162); color: var(--ink); box-shadow: 0 3px 10px oklch(30% .025 162 / .05); }
.workline-organizations-frame { overflow: hidden; border: 1px solid oklch(84% .035 162); border-radius: 11px; background: var(--surface); box-shadow: 0 3px 10px oklch(30% .025 162 / .05); }
.workline-organizations-frame .organization-summary-head { background: var(--surface); padding: 15px 18px; }
.workline-organizations-frame .division-list { background: oklch(97.5% .012 162); }
.workline-dashboard-heading { grid-area: heading; display: flex; align-items: center; justify-content: space-between; gap: 18px; border-bottom: 1px solid oklch(87% .025 162); background: oklch(95.5% .026 162); padding: 14px 20px; }
.workline-dashboard-heading > div { display: grid; gap: 2px; }
.workline-dashboard-heading span { color: var(--green); font-size: 10px; font-weight: 900; }
.workline-dashboard-heading strong { font-size: 16px; }
.workline-dashboard-heading small { color: var(--muted); font-size: 11px; }
.workline-outcome-chart { grid-area: chart; display: grid; align-content: start; gap: 18px; border-right: 1px solid oklch(87% .025 162); background: var(--surface); padding: 18px 22px 22px; }
.workline-panel-title { display: grid; gap: 2px; }
.workline-panel-title strong { font-size: 14px; }
.workline-panel-title span { color: var(--muted); font-size: 10px; }
.workline-chart-body { display: flex; align-items: center; gap: 24px; }
.workline-donut { display: grid; flex: none; width: 112px; height: 112px; place-items: center; border-radius: 50%; }
.workline-donut::before { grid-area: 1 / 1; width: 88px; height: 88px; border-radius: 50%; background: var(--surface); content: ''; }
.workline-donut > div { z-index: 1; grid-area: 1 / 1; display: grid; width: 80px; place-items: center; text-align: center; }
.workline-donut strong { color: var(--ink); font-size: 26px; line-height: .95; }
.workline-donut span { margin-top: 7px; color: var(--muted); font-size: 9px; line-height: 1.25; white-space: nowrap; }
.workline-chart-legend { display: grid; flex: 1; gap: 10px; }
.workline-chart-legend span { display: grid; grid-template-columns: 9px minmax(0, 1fr) auto; align-items: center; gap: 8px; color: var(--muted); font-size: 11px; }
.workline-chart-legend b { color: var(--ink); font-weight: 700; }
.workline-chart-legend strong { color: var(--ink); font-size: 12px; white-space: nowrap; }
.workline-chart-legend i { width: 9px; height: 9px; border-radius: 3px; }
.workline-chart-legend .passed { background: oklch(62% .14 151); }
.workline-chart-legend .failed { background: oklch(68% .14 35); }
.workline-chart-legend .pending { background: oklch(79% .02 253); }
.workline-dashboard-counts { grid-area: counts; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 0; border-bottom: 1px solid oklch(87% .025 162); background: var(--surface); padding: 0 20px; }
.workline-dashboard-counts > div { display: grid; align-content: center; gap: 5px; min-height: 92px; padding: 14px 20px; }
.workline-dashboard-counts > div + div { border-left: 1px solid oklch(89% .018 162); }
.workline-dashboard-counts dt { color: var(--muted); font-size: 11px; font-weight: 700; }
.workline-dashboard-counts dd { margin: 0; color: var(--ink); font-size: 30px; font-weight: 900; line-height: 1; }
.workline-dashboard-counts dd small { color: var(--muted); font-size: 11px; font-weight: 700; }
.workline-dashboard-counts .passed dd { color: oklch(50% .13 151); }
.workline-dashboard-counts .failed dd { color: oklch(56% .15 35); }
.workline-ranking { grid-area: ranking; background: oklch(98.5% .008 162); padding: 18px 22px 22px; }
.workline-ranking > header { display: flex; justify-content: space-between; gap: 12px; }
.workline-ranking > header strong { font-size: 14px; }
.workline-ranking > header span { color: var(--muted); font-size: 10px; }
.workline-ranking ol { display: grid; gap: 10px; margin: 16px 0 0; padding: 0; list-style: none; }
.workline-ranking li { display: grid; grid-template-columns: 24px minmax(0, 1fr) auto; align-items: center; gap: 8px; }
.workline-ranking li > b { display: grid; width: 24px; height: 24px; place-items: center; border-radius: 6px; background: oklch(92% .035 35); color: oklch(52% .14 35); font-size: 10px; }
.workline-ranking li > div { display: grid; gap: 4px; min-width: 0; }
.workline-ranking li span { overflow: hidden; color: var(--ink); font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.workline-ranking li i { display: block; overflow: hidden; height: 6px; border-radius: 999px; background: oklch(91% .012 253); }
.workline-ranking li em { display: block; height: 100%; border-radius: inherit; background: oklch(68% .14 35); }
.workline-ranking li > strong { color: oklch(52% .14 35); font-size: 12px; white-space: nowrap; }
.workline-ranking-empty { display: grid; min-height: 82px; place-items: center; color: var(--muted); font-size: 12px; }
.workline-dialog-backdrop .gap-definition { padding: 12px 18px; font-size: 10px; }
.workline-dialog-backdrop .workline-detail-grid h4 { padding: 14px 18px; font-size: 12px; }
.workline-dialog-backdrop .workline-detail-grid h4 > span { float: right; color: var(--muted); font-size: 10px; font-weight: 700; }
.workline-dialog-backdrop .workline-competency-list, .workline-dialog-backdrop .workline-person-list { max-height: min(430px, 52vh); }
.workline-dialog-backdrop .workline-competency-list article { padding: 12px 18px; }
.workline-dialog-backdrop .workline-person-list { max-height: 180px; }
.workline-dialog-backdrop .workline-person-list button { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; align-items: center; gap: 9px; width: 100%; min-height: 54px; border: 0; background: var(--surface); padding: 10px 18px; color: var(--ink); font: inherit; text-align: left; cursor: pointer; }
.workline-dialog-backdrop .workline-person-list button + button { border-top: 1px solid var(--line); }
.workline-dialog-backdrop .workline-person-list button:hover { background: var(--surface-soft); }
.workline-dialog-backdrop .workline-person-list button.selected { background: oklch(95% .028 162); }
.workline-dialog-backdrop .workline-person-list button:focus-visible { position: relative; z-index: 1; outline: 3px solid oklch(79% .08 162 / .45); outline-offset: -3px; }
.workline-dialog-backdrop .workline-person-list button > span { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 50%; background: oklch(32% .075 253); color: oklch(97% .005 253); font-size: 10px; font-weight: 900; }
.workline-dialog-backdrop .workline-person-list button > div { display: grid; gap: 2px; min-width: 0; }
.workline-dialog-backdrop .workline-person-list button strong, .workline-dialog-backdrop .workline-person-list button small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.workline-dialog-backdrop .workline-person-list button small { color: var(--muted); }
.workline-dialog-backdrop .workline-person-list button b { color: oklch(49% .14 35); font-size: 9px; white-space: nowrap; }
.workline-dialog-backdrop .workline-competency-list article > strong, .workline-dialog-backdrop .workline-person-list strong { font-size: 11px; }
.workline-dialog-backdrop .workline-competency-list article > span, .workline-dialog-backdrop .workline-competency-list article > small, .workline-dialog-backdrop .workline-person-list small, .workline-dialog-backdrop .workline-person-list b { font-size: 9px; }
.person-gap-detail { border-top: 1px solid var(--line); background: var(--surface-soft); }
.person-gap-detail > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 18px; }
.person-gap-detail > header div { display: grid; gap: 2px; }
.person-gap-detail > header small { color: var(--muted); font-size: 8px; }
.person-gap-detail > header strong { font-size: 11px; }
.person-gap-detail > header > span { border-radius: 999px; background: oklch(92% .055 45); padding: 4px 8px; color: oklch(49% .14 35); font-size: 8px; font-weight: 900; white-space: nowrap; }
.person-gap-columns, .person-gap-detail > article { display: grid; grid-template-columns: minmax(0, 1fr) 52px 52px 46px; align-items: center; gap: 8px; padding: 8px 18px; }
.person-gap-columns { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); font-size: 8px; font-weight: 800; }
.person-gap-columns span:not(:first-child), .person-gap-detail > article > span, .person-gap-detail > article > b { text-align: center; }
.person-gap-detail > article { background: var(--surface); }
.person-gap-detail > article + article { border-top: 1px solid var(--line); }
.person-gap-detail > article > div { display: grid; gap: 2px; min-width: 0; }
.person-gap-detail > article small { color: var(--green); font-size: 8px; font-weight: 900; }
.person-gap-detail > article strong { overflow: hidden; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.person-gap-detail > article > span { color: var(--muted); font-size: 10px; font-weight: 800; }
.person-gap-detail > article > b { color: oklch(49% .14 35); font-size: 10px; }
.person-gap-empty { display: grid; min-height: 120px; place-items: center; border-top: 1px solid var(--line); color: var(--muted); font-size: 9px; }
.organization-summary-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid var(--line); background: var(--surface-soft); padding: 11px 18px; }
.organization-summary-head span { color: var(--ink); font-size: 14px; font-weight: 900; }
.organization-summary-head small { color: var(--muted); font-size: 11px; }
.organization-summary-list { display: grid; gap: 10px; max-height: min(620px, 68vh); overflow-y: auto; background: var(--surface-soft); padding: 12px; }
.organization-summary-row { overflow: hidden; border: 1px solid var(--line); border-radius: 9px; background: var(--surface); }
.organization-summary-row.level-work { margin-left: 14px; }
.organization-summary-row.level-unit { margin-left: 28px; }
.organization-summary-toggle { display: grid; grid-template-columns: minmax(170px, .75fr) auto minmax(180px, 1.35fr) auto 16px; align-items: center; gap: 14px; width: 100%; min-height: 62px; border: 0; background: var(--surface); padding: 12px 15px; color: var(--ink); font: inherit; text-align: left; cursor: pointer; transition: background 160ms ease-out; }
.organization-summary-toggle:hover { background: oklch(97.5% .015 162); }
.organization-summary-toggle:focus-visible { position: relative; z-index: 1; outline: 3px solid oklch(77% .09 162 / .5); outline-offset: -3px; }
.organization-title { display: grid; gap: 2px; min-width: 0; border-left: 3px solid oklch(34% .09 253); padding-left: 10px; }
.level-work .organization-title { border-left-color: oklch(55% .11 222); }
.level-unit .organization-title { border-left-color: oklch(56% .08 185); }
.organization-title small { color: var(--muted); font-size: 10px; font-weight: 700; }
.organization-title h4 { overflow: hidden; margin: 0; color: var(--ink); font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }
.organization-result-counts { display: flex; align-items: center; gap: 10px; white-space: nowrap; }
.organization-result-counts strong { font-size: 13px; font-weight: 900; }
.organization-result-counts .passed { color: oklch(50% .15 145); }
.organization-result-counts .failed { color: oklch(53% .18 30); }
.organization-result-counts span { color: var(--muted); font-size: 10px; font-weight: 700; }
.organization-result-track { display: flex; overflow: hidden; height: 8px; margin: 0; border-radius: 999px; background: oklch(91% .012 162); }
.organization-result-track i { flex-basis: 0; min-width: 0; }
.organization-result-track .passed { background: oklch(62% .16 145); }
.organization-result-track .failed { background: oklch(69% .15 30); }
.organization-result-track .pending { background: oklch(88% .015 253); }
.organization-total { color: var(--muted); font-size: 13px; white-space: nowrap; }
.organization-chevron { color: var(--muted); font-size: 15px; line-height: 1; transition: transform 160ms ease-out; }
.organization-summary-toggle[aria-expanded="true"] .organization-chevron { transform: rotate(180deg); }
.organization-top-gaps { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 8px 12px; border-top: 1px solid oklch(89% .035 82); background: oklch(97% .035 82); padding: 10px 15px 10px 28px; }
.organization-top-gaps > strong { color: oklch(52% .13 65); font-size: 11px; white-space: nowrap; }
.organization-top-gaps ol { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; padding: 0; list-style: none; }
.organization-top-gaps li { display: flex; align-items: center; gap: 7px; max-width: 300px; border-radius: 999px; background: oklch(96% .035 25); padding: 5px 6px 5px 10px; color: oklch(49% .15 28); }
.organization-top-gaps li > span { overflow: hidden; font-size: 11px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.organization-top-gaps li em { flex: none; border-radius: 999px; background: oklch(65% .19 28); padding: 3px 8px; color: white; font-size: 10px; font-style: normal; font-weight: 900; white-space: nowrap; }
.organization-no-gap { color: var(--muted); font-size: 11px; }
.division-list { display: grid; gap: 10px; max-height: min(620px, 68vh); overflow-y: auto; background: var(--surface-soft); padding: 12px; }
.division-overview-row { display: grid; grid-template-columns: minmax(190px, .8fr) auto minmax(190px, 1.25fr) 70px 104px; align-items: center; gap: 15px; width: 100%; min-height: 76px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); padding: 12px 15px; color: var(--ink); font: inherit; text-align: left; cursor: pointer; transition: border-color 160ms cubic-bezier(.22, 1, .36, 1), background-color 160ms cubic-bezier(.22, 1, .36, 1), box-shadow 160ms cubic-bezier(.22, 1, .36, 1); }
.division-overview-row:hover { border-color: oklch(73% .065 162); background: oklch(97.5% .015 162); box-shadow: 0 4px 12px oklch(30% .025 162 / .08); }
.division-overview-row:focus-visible { outline: 3px solid oklch(77% .09 162 / .5); outline-offset: 2px; }
.division-name { display: grid; gap: 2px; min-width: 0; }
.division-name small { color: var(--green); font-size: 10px; font-weight: 900; }
.division-name strong { overflow: hidden; font-size: 16px; text-overflow: ellipsis; white-space: nowrap; }
.division-name span { color: var(--muted); font-size: 11px; }
.division-counts { display: flex; align-items: center; gap: 10px; white-space: nowrap; }
.division-counts span { color: var(--muted); font-size: 11px; font-weight: 800; }
.division-counts b { font-size: 14px; }
.division-counts .passed { color: oklch(48% .145 145); }
.division-counts .failed { color: oklch(52% .18 29); }
.division-result-track { display: flex; overflow: hidden; height: 8px; border-radius: 999px; background: oklch(89% .012 253); }
.division-result-track i { flex-basis: 0; min-width: 0; }
.division-result-track .passed { background: oklch(61% .155 145); }
.division-result-track .failed { background: oklch(69% .145 29); }
.division-result-track .pending { background: oklch(87% .015 253); }
.division-total { display: grid; justify-items: end; white-space: nowrap; }
.division-total b { font-size: 16px; line-height: 1; }
.division-total span { margin-top: 3px; color: var(--muted); font-size: 10px; }
.division-open-label { display: flex; align-items: center; justify-content: flex-end; gap: 5px; color: var(--green); font-size: 11px; font-weight: 900; white-space: nowrap; }
.division-open-label b { font-size: 18px; font-weight: 500; line-height: 1; }
.heatmap-heading { align-items: flex-end; padding: 20px 20px 14px; }
.heatmap-tools { display: flex; align-items: center; gap: 9px; }.type-filter { display: flex; gap: 3px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface-soft); padding: 3px; }.type-filter button { min-height: 34px; border: 0; border-radius: 7px; background: transparent; padding: 6px 11px; color: var(--muted); font: inherit; font-size: 11px; font-weight: 800; cursor: pointer; }.type-filter button:hover { color: var(--ink); }.type-filter button.active { background: var(--surface); color: var(--green); box-shadow: 0 1px 4px oklch(25% .02 162 / .13); }.type-filter button:focus-visible { outline: 3px solid oklch(79% .08 162 / .45); outline-offset: 1px; }.heatmap-tools input { width: 240px; min-height: 42px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface); padding: 8px 11px; color: var(--ink); font: inherit; font-size: 12px; outline: none; }.heatmap-tools input:focus { border-color: oklch(65% .09 162); box-shadow: 0 0 0 3px oklch(82% .07 162 / .32); }
.heat-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin: 0 20px 14px; border: 1px solid oklch(88% .022 162); border-radius: 8px; background: oklch(97.5% .012 162); padding: 9px 12px; color: var(--muted); font-size: 10px; }.heat-legend > strong { color: var(--ink); font-size: 10px; }.heat-legend span { display: flex; align-items: center; gap: 6px; }.heat-legend i { width: 10px; height: 10px; border-radius: 3px; }.heat-legend i.none { background: oklch(92% .008 162); }.heat-legend i.low { background: oklch(91% .055 92); }.heat-legend i.medium { background: oklch(84% .1 65); }.heat-legend i.high { background: oklch(75% .14 38); }.heatmap-wrap { max-height: min(520px, 62vh); margin: 0 20px 18px; overflow: auto; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); scrollbar-gutter: stable; }
.heatmap-table { width: 100%; min-width: 780px; border-collapse: collapse; table-layout: fixed; }.heatmap-table th, .heatmap-table td { border-bottom: 1px solid var(--line); padding: 8px; text-align: left; }.heatmap-table tr:last-child > * { border-bottom: 0; }.heatmap-table thead th { position: sticky; z-index: 2; top: 0; background: oklch(95.5% .018 162); box-shadow: 0 1px 0 var(--line); color: var(--muted); font-size: 11px; font-weight: 800; }.heatmap-table thead th:first-child { width: 41%; padding-left: 16px; }.heatmap-table tbody tr { transition: background-color 160ms cubic-bezier(.22, 1, .36, 1); }.heatmap-table tbody tr:hover { background: oklch(98% .008 162); }.heatmap-table tbody > tr > th { padding-left: 16px; }.heatmap-table tbody > tr > th span { display: block; color: var(--green); font-size: 11px; font-weight: 800; }.heatmap-table tbody > tr > th strong { display: block; margin-top: 3px; font-size: 13px; line-height: 1.45; }
.heatmap-table .heatmap-empty-row:hover { background: transparent; }
.heatmap-table .heatmap-empty-row td { height: 150px; background: oklch(99% .006 162); text-align: center; }
.heatmap-empty-row td strong, .heatmap-empty-row td span { display: block; }
.heatmap-empty-row td strong { color: var(--ink); font-size: 13px; }
.heatmap-empty-row td span { margin-top: 5px; color: var(--muted); font-size: 11px; }
.heat-cell { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 3px 8px; width: 100%; min-height: 58px; border: 1px solid transparent; border-radius: 8px; padding: 9px 12px; color: var(--ink); font: inherit; text-align: left; cursor: pointer; transition: border-color 160ms ease-out, box-shadow 160ms ease-out, background-color 160ms ease-out; }.heat-cell strong { font-size: 11px; font-weight: 700; }.heat-cell strong b { margin-right: 2px; font-size: 16px; }.heat-cell small { grid-column: 1; color: var(--muted); font-size: 9px; }.heat-cell > span { grid-column: 2; grid-row: 1 / span 2; align-self: center; font-size: 18px; line-height: 1; }.heat-cell.none { background: oklch(96% .006 162); color: var(--muted); cursor: default; }.heat-cell.low { background: oklch(95% .035 92); }.heat-cell.medium { background: oklch(92% .055 65); }.heat-cell.high { background: oklch(88% .075 38); }.heat-cell:not(:disabled):hover, .heat-cell.selected { border-color: oklch(53% .09 162); background: oklch(96% .025 162); box-shadow: 0 0 0 2px oklch(82% .07 162 / .3); }.heat-cell:focus-visible { outline: 3px solid oklch(79% .08 162 / .45); outline-offset: 2px; }.heat-cell:disabled { opacity: 1; }
.empty-state, .compact-empty { display: grid; place-items: center; color: var(--muted); text-align: center; }.empty-state { min-height: 150px; margin: 0 18px 16px; border: 1px dashed var(--line); border-radius: 9px; }.empty-state strong { font-size: 13px; }.empty-state span { margin-top: 3px; font-size: 10px; }
.cell-detail { margin: 0 18px 18px; border: 1px solid oklch(79% .055 162); border-radius: 9px; background: oklch(97.5% .018 162); }.cell-detail > header { align-items: center; border-bottom: 1px solid var(--line); padding: 12px 14px; }.cell-detail > header span { color: var(--green); font-size: 10px; font-weight: 900; }.cell-detail h3 { margin: 2px 0 0; font-size: 14px; }.cell-detail > header button { border: 1px solid var(--line); border-radius: 7px; background: var(--surface); padding: 5px 8px; color: var(--muted); font: inherit; font-size: 10px; cursor: pointer; }
.detail-facts { display: flex; flex-wrap: wrap; gap: 8px 18px; border-bottom: 1px solid var(--line); padding: 10px 14px; color: var(--muted); font-size: 10px; }.detail-facts b { color: var(--ink); font-size: 12px; }
.people-list { max-height: min(360px, 52vh); overflow-y: auto; scrollbar-gutter: stable; }.people-list article { display: grid; grid-template-columns: minmax(180px, 1fr) auto 70px 110px; align-items: center; gap: 12px; padding: 10px 14px; }.people-list article + article { border-top: 1px solid var(--line); }.people-list article > div { display: grid; gap: 2px; }.people-list article strong { font-size: 11px; }.people-list article small, .people-list article > span { color: var(--muted); font-size: 9px; }.people-list article > b { color: oklch(50% .15 35); font-size: 11px; }.people-list article em { border-radius: 999px; background: oklch(92% .025 162); padding: 4px 7px; color: var(--muted); font-size: 9px; font-style: normal; font-weight: 800; text-align: center; }.people-list article em.overdue { background: oklch(92% .06 35); color: oklch(47% .14 35); }
.compact-empty { min-height: 130px; border-top: 1px solid var(--line); font-size: 11px; }
@media (max-width: 1000px) { .idp-summary-hero, .idp-tracking-grid, .workline-detail-grid { grid-template-columns: 1fr; }.workline-detail-grid > div + div { border-top: 1px solid var(--line); border-left: 0; }.heatmap-heading { align-items: flex-start; flex-direction: column; }.heatmap-tools { width: 100%; }.heatmap-tools input { flex: 1; width: auto; }.attention-toolbar { grid-template-columns: minmax(240px, 1fr) 1fr 1fr; }.attention-result-count { grid-column: 1 / -1; justify-items: start; padding-bottom: 0; }.attention-list-head, .attention-row { grid-template-columns: minmax(220px, .9fr) minmax(220px, 1.1fr) 120px; } }
@media (max-width: 1000px) { .workline-overall-dashboard { grid-template-columns: 1fr; grid-template-areas: 'heading' 'counts' 'chart' 'ranking'; }.workline-outcome-chart { border-right: 0; }.workline-ranking { border-top: 1px solid oklch(87% .025 162); } }
@media (max-width: 900px) { .workline-idp-list { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .page-heading { flex-direction: column; }.round-selector { width: 100%; }.idp-summary-hero { gap: 22px; padding: 20px; }.idp-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.idp-kpi-grid article { min-height: 104px; padding: 15px; }.idp-kpi-grid .draft small { line-height: 1.45; }.workline-cards { grid-template-columns: 1fr; padding: 12px; }.workline-cards dl { grid-template-columns: 1fr; }.workline-cards dl > div { grid-template-columns: minmax(0, 1fr) auto; align-items: center; min-height: 0; }.workline-cards dl > div + div { border-top: 1px solid var(--line); border-left: 0; }.workline-cards dt { font-size: 10px; }.completion-copy { align-items: flex-start; flex-direction: column; gap: 3px; }.workline-dialog-backdrop { align-items: end; padding: 12px; }.workline-dialog-backdrop .workline-detail-panel { width: 100%; max-height: calc(100vh - 24px); margin: 0; }.gap-definition { flex-direction: column; }.heatmap-tools { align-items: stretch; flex-direction: column; }.heatmap-heading, .heat-legend { padding-right: 14px; padding-left: 14px; }.people-list article { grid-template-columns: minmax(150px, 1fr) auto; }.people-list article > span { display: none; }.heatmap-wrap, .cell-detail { margin-right: 10px; margin-left: 10px; } }
@media (max-width: 760px) {
  .tracking-panel-heading { align-items: flex-start; flex-direction: column; gap: 8px; }
  .attention-toolbar { grid-template-columns: 1fr; padding: 12px; }
  .attention-result-count { grid-column: auto; grid-template-columns: auto auto; align-items: center; justify-content: space-between; justify-items: stretch; }
  .workline-idp-list article { padding: 15px; }
  .attention-list-head { display: none; }
  .attention-row { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; padding: 12px; }
  .attention-person { grid-column: 1; }
  .attention-competencies { grid-column: 1 / -1; grid-row: 2; padding-left: 46px; }
  .attention-competencies > span, .attention-competencies > b { display: none; }
  .attention-competencies > small { display: block; }
  .attention-status { grid-column: 2; grid-row: 1; }
  .attention-status > b { display: none; }
  .workline-dashboard-heading { align-items: flex-start; flex-direction: column; gap: 4px; }
  .workline-dashboard-counts { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0; }
  .workline-dashboard-counts > div:nth-child(3) { border-top: 1px solid oklch(89% .018 162); border-left: 0; }
  .workline-dashboard-counts > div:nth-child(4) { border-top: 1px solid oklch(89% .018 162); }
  .workline-chart-body { justify-content: center; }
  .organization-summary-head { align-items: flex-start; flex-direction: column; gap: 3px; }
  .organization-summary-list { padding: 8px; }
  .organization-summary-row.level-work { margin-left: 8px; }
  .organization-summary-row.level-unit { margin-left: 16px; }
  .organization-summary-toggle { grid-template-columns: minmax(0, 1fr) auto 14px; gap: 8px 10px; padding: 11px 12px; }
  .organization-title { grid-column: 1; }
  .organization-total { grid-column: 2; grid-row: 1; }
  .organization-chevron { grid-column: 3; grid-row: 1; }
  .organization-result-counts { grid-column: 1 / -1; grid-row: 2; }
  .organization-result-track { grid-column: 1 / -1; grid-row: 3; }
  .organization-top-gaps { grid-template-columns: 1fr; padding: 10px 12px; }
  .organization-top-gaps ol { display: grid; }
  .organization-top-gaps li { max-width: none; }
  .division-list { padding: 8px; }
  .division-overview-row { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 10px; padding: 11px 12px; }
  .division-name { grid-column: 1; }
  .division-total { grid-column: 2; grid-row: 1; }
  .division-counts { grid-column: 1 / -1; grid-row: 2; }
  .division-result-track { grid-column: 1 / -1; grid-row: 3; }
  .division-open-label { grid-column: 1 / -1; grid-row: 4; justify-content: flex-start; }
}
</style>
