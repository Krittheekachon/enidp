<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps<{
  group: {
    division: any;
    works: Array<{ work: any; units: any[] }>;
  };
  scope?: 'academic' | 'support';
}>();

const emit = defineEmits<{
  close: [];
}>();

const dialog = ref<HTMLElement | null>(null);
const division = computed(() => props.group.division);
const isAcademic = computed(() => props.scope === 'academic');
const formatNumber = (value: any) => Number(value || 0).toLocaleString('th-TH');
const outcomeChartStyle = computed(() => {
  const total = Math.max(Number(division.value.totalEmployees || 0), 1);
  const passedEnd = (Number(division.value.passedEmployees || 0) / total) * 100;
  const failedEnd = passedEnd + ((Number(division.value.failedEmployees || 0) / total) * 100);

  return {
    background: `conic-gradient(oklch(61% .155 145) 0 ${passedEnd}%, oklch(69% .145 29) ${passedEnd}% ${failedEnd}%, oklch(87% .015 253) ${failedEnd}% 100%)`,
  };
});
const competencyBarWidth = (peopleCount: number) => {
  const maximum = Math.max(...division.value.topCompetencies.map((item: any) => Number(item.peopleCount || 0)), 1);
  return `${Math.max((Number(peopleCount || 0) / maximum) * 100, 4)}%`;
};

onMounted(async () => {
  await nextTick();
  dialog.value?.focus();
});
</script>

<template>
  <div class="division-dialog-backdrop" @click.self="emit('close')">
    <section ref="dialog" class="division-dialog" role="dialog" aria-modal="true" aria-labelledby="division-dialog-title" tabindex="-1">
      <header class="dialog-header">
        <div>
          <span>{{ isAcademic ? 'รายละเอียดภาควิชา' : 'รายละเอียดฝ่าย' }}</span>
          <h3 id="division-dialog-title">{{ division.label }}</h3>
          <small>{{ isAcademic ? 'ภาพรวมผลการประเมินรายภาควิชา' : `${formatNumber(group.works.length)} งานภายในฝ่าย` }}</small>
        </div>
        <button type="button" :aria-label="`ปิดหน้าต่างรายละเอียด${isAcademic ? 'ภาควิชา' : 'ฝ่าย'}`" @click="emit('close')">×</button>
      </header>

      <section class="division-dashboard" :aria-label="`ภาพรวมผลการประเมินของ${isAcademic ? 'ภาควิชา' : 'ฝ่าย'}`">
        <header class="dashboard-heading">
          <div>
            <span>ภาพรวม</span>
            <strong>ผลการประเมิน{{ isAcademic ? 'ภาควิชา' : 'ฝ่าย' }}</strong>
          </div>
          <small>นับเฉพาะบุคลากรที่ต้องประเมินตนเอง</small>
        </header>

        <div class="outcome-chart">
          <div class="outcome-title">
            <strong>ผลการประเมิน</strong>
            <span>แยกตามสถานะของบุคลากร</span>
          </div>
          <div class="outcome-body">
            <div class="donut" :style="outcomeChartStyle">
              <div><strong>{{ formatNumber(division.totalEmployees) }}</strong><span>คนทั้งหมด</span></div>
            </div>
            <div class="chart-legend">
              <span><i class="passed"></i><b>ผ่านเกณฑ์</b><strong>{{ formatNumber(division.passedEmployees) }} คน</strong></span>
              <span><i class="failed"></i><b>มี Competency Gap</b><strong>{{ formatNumber(division.failedEmployees) }} คน</strong></span>
              <span><i class="pending"></i><b>ยังประเมินไม่ครบ</b><strong>{{ formatNumber(division.pendingEmployees) }} คน</strong></span>
            </div>
          </div>
        </div>

        <dl class="dashboard-counts">
          <div>
            <dt>ประเมินครบ</dt>
            <dd>{{ formatNumber(division.assessedEmployees) }} <small>คน</small></dd>
          </div>
          <div class="passed">
            <dt>ผ่านเกณฑ์</dt>
            <dd>{{ formatNumber(division.passedEmployees) }} <small>คน</small></dd>
          </div>
          <div class="failed">
            <dt>มี Competency Gap</dt>
            <dd>{{ formatNumber(division.failedEmployees) }} <small>คน</small></dd>
          </div>
          <div>
            <dt>ยังประเมินไม่ครบ</dt>
            <dd>{{ formatNumber(division.pendingEmployees) }} <small>คน</small></dd>
          </div>
        </dl>

        <div class="competency-ranking">
          <header>
            <strong>สมรรถนะที่ตกมากที่สุด</strong>
            <span>สูงสุด 3 อันดับ</span>
          </header>
          <ol v-if="division.topCompetencies.length">
            <li v-for="(competency, index) in division.topCompetencies" :key="competency.competencyId">
              <b>{{ index + 1 }}</b>
              <div>
                <span>{{ competency.name }}</span>
                <i><em :style="{ width: competencyBarWidth(competency.peopleCount) }"></em></i>
              </div>
              <strong>{{ formatNumber(competency.peopleCount) }} คน</strong>
            </li>
          </ol>
          <div v-else class="ranking-empty">ไม่มีผู้พบ Competency Gap ใน{{ isAcademic ? 'ภาควิชานี้' : 'ฝ่ายนี้' }}</div>
        </div>
      </section>

      <section v-if="!isAcademic" class="organization-structure">
        <header>
          <div><h4>งานและหน่วยภายในฝ่าย</h4><p>กดแต่ละแถวเพื่อดูสมรรถนะที่ตกมากที่สุด</p></div>
          <span>{{ formatNumber(group.works.length) }} งาน</span>
        </header>

        <div v-if="group.works.length" class="work-list">
          <section v-for="branch in group.works" :key="branch.work.key" class="work-group">
            <details class="result-row work-row">
              <summary>
                <div class="row-name"><small>งาน</small><strong>{{ branch.work.label }}</strong></div>
                <div class="row-counts"><span class="passed">{{ formatNumber(branch.work.passedEmployees) }} ผ่าน</span><span class="failed">{{ formatNumber(branch.work.failedEmployees) }} ไม่ผ่าน</span><span v-if="branch.work.pendingEmployees">{{ formatNumber(branch.work.pendingEmployees) }} รอผล</span></div>
                <div class="row-track"><i class="passed" :style="{ flexGrow: branch.work.passedEmployees }"></i><i class="failed" :style="{ flexGrow: branch.work.failedEmployees }"></i><i class="pending" :style="{ flexGrow: branch.work.pendingEmployees }"></i></div>
                <b>{{ formatNumber(branch.work.totalEmployees) }} คน</b><span class="chevron">⌄</span>
              </summary>
              <div class="row-gaps">
                <strong>สมรรถนะที่ตก:</strong>
                <span v-for="competency in branch.work.topCompetencies" :key="competency.competencyId">{{ competency.name }} <b>{{ formatNumber(competency.peopleCount) }} คน</b></span>
                <em v-if="!branch.work.topCompetencies.length">ไม่มีสมรรถนะที่ตก</em>
              </div>
            </details>

            <div v-if="branch.units.length" class="unit-list">
              <details v-for="unit in branch.units" :key="unit.key" class="result-row unit-row">
                <summary>
                  <div class="row-name"><small>หน่วย</small><strong>{{ unit.label }}</strong></div>
                  <div class="row-counts"><span class="passed">{{ formatNumber(unit.passedEmployees) }} ผ่าน</span><span class="failed">{{ formatNumber(unit.failedEmployees) }} ไม่ผ่าน</span><span v-if="unit.pendingEmployees">{{ formatNumber(unit.pendingEmployees) }} รอผล</span></div>
                  <div class="row-track"><i class="passed" :style="{ flexGrow: unit.passedEmployees }"></i><i class="failed" :style="{ flexGrow: unit.failedEmployees }"></i><i class="pending" :style="{ flexGrow: unit.pendingEmployees }"></i></div>
                  <b>{{ formatNumber(unit.totalEmployees) }} คน</b><span class="chevron">⌄</span>
                </summary>
                <div class="row-gaps">
                  <strong>สมรรถนะที่ตก:</strong>
                  <span v-for="competency in unit.topCompetencies" :key="competency.competencyId">{{ competency.name }} <b>{{ formatNumber(competency.peopleCount) }} คน</b></span>
                  <em v-if="!unit.topCompetencies.length">ไม่มีสมรรถนะที่ตก</em>
                </div>
              </details>
            </div>
          </section>
        </div>
        <div v-else class="structure-empty">ยังไม่มีข้อมูลงานและหน่วยภายในฝ่ายนี้</div>
      </section>
    </section>
  </div>
</template>

<style scoped>
.division-dialog-backdrop { position: fixed; z-index: 90; inset: 0; display: grid; place-items: center; overflow-y: auto; background: oklch(18% .025 253 / .7); padding: 28px; color: oklch(31% .025 162); font-family: 'Sarabun', 'Noto Sans Thai', system-ui, sans-serif; }
.division-dialog { width: min(1120px, 100%); max-height: calc(100vh - 56px); overflow-y: auto; border: 1px solid oklch(78% .025 162); border-radius: 13px; background: oklch(96.5% .012 162); box-shadow: 0 28px 72px oklch(18% .025 253 / .34); outline: none; }
.dialog-header { position: sticky; z-index: 3; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid oklch(88% .018 162); background: oklch(99% .006 162); padding: 15px 18px; }
.dialog-header > div { display: grid; gap: 2px; }
.dialog-header span { color: oklch(48% .105 162); font-size: 12px; font-weight: 900; }
.dialog-header h3 { margin: 0; font-size: 22px; }
.dialog-header small { color: oklch(55% .018 162); font-size: 12px; }
.dialog-header button { display: grid; width: 40px; height: 40px; place-items: center; border: 1px solid oklch(86% .02 162); border-radius: 9px; background: oklch(98% .006 162); color: oklch(51% .02 162); font: inherit; font-size: 22px; line-height: 1; cursor: pointer; }
.dialog-header button:hover { background: oklch(95.5% .012 162); color: oklch(31% .025 162); }
.dialog-header button:focus-visible, summary:focus-visible { outline: 3px solid oklch(74% .09 162 / .48); outline-offset: 2px; }

.division-dashboard { display: grid; grid-template-columns: minmax(300px, .78fr) minmax(430px, 1.22fr); grid-template-areas: 'heading heading' 'counts counts' 'chart ranking'; overflow: hidden; margin: 16px; border: 1px solid oklch(84% .035 162); border-radius: 11px; background: oklch(99% .006 162); color: oklch(31% .025 162); box-shadow: 0 3px 10px oklch(30% .025 162 / .05); }
.dashboard-heading { grid-area: heading; display: flex; align-items: center; justify-content: space-between; gap: 18px; border-bottom: 1px solid oklch(87% .025 162); background: oklch(95.5% .026 162); padding: 14px 20px; }
.dashboard-heading > div { display: grid; gap: 2px; }
.dashboard-heading span { color: oklch(48% .105 162); font-size: 11px; font-weight: 900; }
.dashboard-heading strong { font-size: 16px; }
.dashboard-heading small { color: oklch(55% .018 162); font-size: 11px; }
.outcome-chart { grid-area: chart; display: grid; align-content: start; gap: 18px; border-right: 1px solid oklch(87% .025 162); padding: 18px 22px 22px; }
.outcome-title { display: grid; gap: 2px; }
.outcome-title strong { font-size: 14px; }
.outcome-title span { color: oklch(55% .018 162); font-size: 10px; }
.outcome-body { display: flex; align-items: center; gap: 24px; }
.donut { display: grid; flex: none; width: 112px; height: 112px; place-items: center; border-radius: 50%; }
.donut::before { grid-area: 1 / 1; width: 88px; height: 88px; border-radius: 50%; background: oklch(99% .006 162); content: ''; }
.donut > div { z-index: 1; grid-area: 1 / 1; display: grid; width: 80px; place-items: center; text-align: center; }
.donut strong { color: oklch(31% .025 162); font-size: 26px; line-height: .95; }
.donut span { margin-top: 7px; color: oklch(55% .018 162); font-size: 9px; line-height: 1.25; white-space: nowrap; }
.chart-legend { display: grid; flex: 1; gap: 10px; }
.chart-legend span { display: grid; grid-template-columns: 9px minmax(0, 1fr) auto; align-items: center; gap: 8px; color: oklch(55% .018 162); font-size: 11px; }
.chart-legend b { color: oklch(31% .025 162); font-weight: 700; }
.chart-legend strong { color: oklch(31% .025 162); font-size: 12px; white-space: nowrap; }
.chart-legend i { width: 9px; height: 9px; border-radius: 3px; }
.chart-legend .passed { background: oklch(62% .14 151); }
.chart-legend .failed { background: oklch(68% .14 35); }
.chart-legend .pending { background: oklch(79% .02 253); }
.dashboard-counts { grid-area: counts; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 0; border-bottom: 1px solid oklch(87% .025 162); padding: 0 20px; }
.dashboard-counts > div { display: grid; align-content: center; gap: 5px; min-height: 92px; padding: 14px 20px; }
.dashboard-counts > div + div { border-left: 1px solid oklch(89% .018 162); }
.dashboard-counts dt { color: oklch(55% .018 162); font-size: 11px; font-weight: 700; }
.dashboard-counts dd { margin: 0; color: oklch(31% .025 162); font-size: 30px; font-weight: 900; line-height: 1; }
.dashboard-counts dd small { color: oklch(55% .018 162); font-size: 11px; font-weight: 700; }
.dashboard-counts .passed dd { color: oklch(50% .13 151); }
.dashboard-counts .failed dd { color: oklch(56% .15 35); }
.competency-ranking { grid-area: ranking; background: oklch(98.5% .008 162); padding: 18px 22px 22px; }
.competency-ranking > header { display: flex; justify-content: space-between; gap: 12px; }
.competency-ranking > header strong { font-size: 14px; }
.competency-ranking > header span { color: oklch(55% .018 162); font-size: 10px; }
.competency-ranking ol { display: grid; gap: 10px; margin: 16px 0 0; padding: 0; list-style: none; }
.competency-ranking li { display: grid; grid-template-columns: 20px minmax(0, 1fr) auto; align-items: center; gap: 8px; }
.competency-ranking li > b { display: grid; width: 24px; height: 24px; place-items: center; border-radius: 6px; background: oklch(92% .035 35); color: oklch(52% .14 35); font-size: 10px; }
.competency-ranking li > div { display: grid; gap: 4px; min-width: 0; }
.competency-ranking li span { overflow: hidden; color: oklch(31% .025 162); font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.competency-ranking li i { display: block; overflow: hidden; height: 6px; border-radius: 999px; background: oklch(91% .012 253); }
.competency-ranking li em { display: block; height: 100%; border-radius: inherit; background: oklch(68% .14 35); }
.competency-ranking li > strong { color: oklch(52% .14 35); font-size: 12px; white-space: nowrap; }
.ranking-empty { display: grid; min-height: 82px; place-items: center; color: oklch(55% .018 162); font-size: 12px; }

.organization-structure { overflow: hidden; margin: 0 16px 16px; border: 1px solid oklch(84% .035 162); border-radius: 11px; background: oklch(99% .006 162); box-shadow: 0 3px 10px oklch(30% .025 162 / .05); }
.organization-structure > header { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid oklch(87% .025 162); padding: 16px 18px 12px; }
.organization-structure h4 { margin: 0; font-size: 17px; }
.organization-structure p { margin: 3px 0 0; color: oklch(55% .018 162); font-size: 12px; }
.organization-structure > header > span { border-radius: 999px; background: oklch(92% .03 162); padding: 5px 10px; color: oklch(48% .105 162); font-size: 11px; font-weight: 900; }
.work-list { display: grid; gap: 11px; padding: 0 18px 18px; }
.work-group { overflow: hidden; border: 1px solid oklch(86% .018 162); border-radius: 9px; background: oklch(99% .006 162); }
.result-row + .unit-list { border-top: 1px solid oklch(88% .018 162); }
.result-row { background: oklch(99% .006 162); }
.result-row summary { display: grid; grid-template-columns: minmax(180px, .8fr) auto minmax(160px, 1.2fr) auto 16px; align-items: center; gap: 13px; min-height: 56px; padding: 9px 13px; cursor: pointer; list-style: none; }
.result-row summary::-webkit-details-marker { display: none; }
.result-row summary:hover { background: oklch(96.5% .012 162); }
.result-row[open] > summary .chevron { transform: rotate(180deg); }
.row-name { display: grid; gap: 1px; min-width: 0; }
.row-name small { color: oklch(51% .07 162); font-size: 10px; font-weight: 800; }
.row-name strong { overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.row-counts { display: flex; gap: 11px; color: oklch(55% .018 162); font-size: 11px; font-weight: 800; white-space: nowrap; }
.row-counts .passed { color: oklch(48% .145 145); }
.row-counts .failed { color: oklch(52% .18 29); }
.row-track { display: flex; overflow: hidden; height: 7px; border-radius: 999px; background: oklch(89% .012 253); }
.row-track i { flex-basis: 0; min-width: 0; }
.row-track .passed { background: oklch(61% .155 145); }
.row-track .failed { background: oklch(69% .145 29); }
.row-track .pending { background: oklch(87% .015 253); }
.result-row summary > b { color: oklch(47% .02 162); font-size: 12px; white-space: nowrap; }
.chevron { color: oklch(55% .02 162); font-size: 14px; transition: transform 160ms cubic-bezier(.22, 1, .36, 1); }
.row-gaps { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; border-top: 1px solid oklch(89% .035 82); background: oklch(97% .035 82); padding: 8px 13px; }
.row-gaps > strong { color: oklch(50% .12 62); font-size: 11px; }
.row-gaps > span { border-radius: 999px; background: oklch(95.5% .04 27); padding: 5px 8px 5px 10px; color: oklch(48% .15 28); font-size: 11px; font-weight: 800; }
.row-gaps > span b { margin-left: 4px; }
.row-gaps > em { color: oklch(55% .018 162); font-size: 11px; font-style: normal; }
.unit-list { display: grid; gap: 7px; background: oklch(97% .008 215); padding: 8px 9px 9px 28px; }
.unit-row { border: 1px solid oklch(88% .018 215); border-radius: 7px; background: oklch(99% .006 215); }
.unit-row .row-name small { color: oklch(51% .07 215); }
.structure-empty { display: grid; min-height: 130px; place-items: center; color: oklch(55% .018 162); font-size: 12px; }

@media (max-width: 900px) {
  .division-dashboard { grid-template-columns: 1fr; grid-template-areas: 'heading' 'counts' 'chart' 'ranking'; }
  .outcome-chart { border-right: 0; }
  .competency-ranking { border-top: 1px solid oklch(87% .025 162); }
}

@media (max-width: 700px) {
  .division-dialog-backdrop { align-items: end; padding: 10px; }
  .division-dialog { max-height: calc(100vh - 20px); }
  .division-dashboard { margin: 10px; }
  .dashboard-heading { align-items: flex-start; flex-direction: column; gap: 4px; }
  .dashboard-counts { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0; }
  .dashboard-counts > div:nth-child(3) { border-top: 1px solid oklch(89% .018 162); border-left: 0; }
  .dashboard-counts > div:nth-child(4) { border-top: 1px solid oklch(89% .018 162); }
  .outcome-body { justify-content: center; }
  .organization-structure { margin: 0 10px 10px; }
  .result-row summary { grid-template-columns: minmax(0, 1fr) auto 14px; gap: 7px 9px; }
  .row-counts { grid-column: 1 / -1; grid-row: 2; }
  .row-track { grid-column: 1 / -1; grid-row: 3; }
  .result-row summary > b { grid-column: 2; grid-row: 1; }
  .result-row summary > .chevron { grid-column: 3; grid-row: 1; }
  .unit-list { padding-left: 12px; }
}
</style>
