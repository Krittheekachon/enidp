<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  total?: number;
  assessed?: number;
  passed?: number;
  withGap?: number;
}>(), {
  total: 0,
  assessed: 0,
  passed: 0,
  withGap: 0,
});

const pending = computed(() => Math.max(Number(props.total) - Number(props.assessed), 0));
const completionRate = computed(() => Number(props.total)
  ? Math.round((Number(props.assessed) / Number(props.total)) * 100)
  : 0);
const donutStyle = computed(() => ({
  background: `conic-gradient(var(--chart-blue) ${completionRate.value}%, oklch(45% .045 253) 0)`,
}));
const formatNumber = (value: number) => Number(value || 0).toLocaleString('th-TH');
</script>

<template>
  <section class="assessment-visual" aria-label="ภาพรวมผลการประเมิน">
    <div class="assessment-metrics">
      <div>
        <span>บุคลากรที่ต้องประเมิน</span>
        <strong>{{ formatNumber(props.total) }}</strong>
        <small>คน</small>
      </div>
      <div>
        <span>ประเมินเสร็จแล้ว</span>
        <strong>{{ formatNumber(props.assessed) }}</strong>
        <small>เหลือ {{ formatNumber(pending) }} คน</small>
      </div>
      <div class="positive">
        <span>ผ่านเกณฑ์</span>
        <strong>{{ formatNumber(props.passed) }}</strong>
        <small>คน</small>
      </div>
      <div class="attention">
        <span>มี Competency Gap</span>
        <strong>{{ formatNumber(props.withGap) }}</strong>
        <small>คน</small>
      </div>
    </div>
    <div class="assessment-chart">
      <div
        class="donut"
        :style="donutStyle"
        role="img"
        :aria-label="`ประเมินเสร็จแล้ว ${completionRate}%`"
      >
        <div><strong>{{ completionRate }}%</strong><span>ประเมินแล้ว</span></div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.assessment-visual {
  --chart-blue: oklch(67% .15 222);
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(310px, .55fr);
  gap: 26px;
  border-radius: 14px;
  background: oklch(31% .075 253);
  padding: 26px 30px;
  color: oklch(96% .008 253);
  box-shadow: 0 10px 26px oklch(25% .04 253 / .12);
}

.assessment-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  align-items: center;
}

.assessment-metrics > div {
  min-width: 0;
  padding: 3px 22px;
}

.assessment-metrics > div:first-child {
  padding-left: 0;
}

.assessment-metrics > div + div {
  border-left: 1px solid oklch(79% .04 253 / .22);
}

.assessment-metrics span,
.assessment-metrics small {
  display: block;
  color: oklch(77% .03 253);
}

.assessment-metrics span {
  font-size: 12px;
  font-weight: 700;
}

.assessment-metrics strong {
  display: block;
  margin: 3px 0;
  color: oklch(98% .005 253);
  font-size: 34px;
  font-weight: 800;
  line-height: 1.1;
}

.assessment-metrics small {
  overflow: hidden;
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.assessment-metrics .positive strong {
  color: oklch(72% .17 151);
}

.assessment-metrics .attention strong {
  color: oklch(78% .13 28);
}

.assessment-chart {
  display: grid;
  place-items: center;
}

.donut {
  display: grid;
  width: 112px;
  height: 112px;
  place-items: center;
  border-radius: 50%;
}

.donut::before {
  grid-area: 1 / 1;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: oklch(31% .075 253);
  content: '';
}

.donut > div {
  z-index: 1;
  grid-area: 1 / 1;
  display: grid;
  text-align: center;
}

.donut strong {
  font-size: 24px;
  line-height: 1;
}

.donut span {
  margin-top: 3px;
  color: oklch(75% .03 253);
  font-size: 10px;
}

@media (max-width: 1180px) {
  .assessment-visual {
    grid-template-columns: 1fr;
  }

  .assessment-metrics > div:first-child {
    padding-left: 22px;
  }
}

@media (max-width: 1000px) {
  .assessment-metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    row-gap: 20px;
  }

  .assessment-metrics > div:nth-child(3) {
    border-left: 0;
  }
}

@media (max-width: 760px) {
  .assessment-visual {
    padding: 20px;
  }

  .assessment-metrics {
    grid-template-columns: 1fr 1fr;
  }

  .assessment-metrics > div,
  .assessment-metrics > div:first-child {
    padding: 0 12px;
  }

  .assessment-metrics strong {
    font-size: 28px;
  }
}
</style>
