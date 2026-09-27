<script setup>
import HeadDashboard from '../Head/Dashboard.vue';

const props = defineProps({
    roleKey: { type: String, default: 'supervisor' },
    idpReviewItems: { type: Array, default: () => [] },
});
</script>

<template>
    <HeadDashboard :role-key="props.roleKey" :idp-review-items="props.idpReviewItems"/>
</template>
