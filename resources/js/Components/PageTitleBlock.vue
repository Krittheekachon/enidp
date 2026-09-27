<script setup>
defineProps({
    pageTitle: {
        type: String,
        default: 'หน้าหลัก',
    },
});
</script>

<template>
    <div class="tb-title">{{ pageTitle }}</div>
</template>
