<script setup>
import { ref } from 'vue';

const props = defineProps({
    initialEnabled: {
        type: Boolean,
        default: true,
    },
    endpoint: {
        type: String,
        default: '/mock-sso/notification-toggle',
    },
});

const enabled = ref(props.initialEnabled);
const loading = ref(false);

const toggle = async () => {
    loading.value = true;

    try {
        const response = await fetch(props.endpoint, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        enabled.value = Boolean(data.enabled);
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <button
        class="mock-sso-notification-toggle"
        :class="enabled ? 'on' : 'off'"
        type="button"
        :disabled="loading"
        @click="toggle"
    >
        Notifications: {{ enabled ? 'ON' : 'OFF' }}
    </button>
</template>

<style scoped>
.mock-sso-notification-toggle {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 20;
    border: 0;
    border-radius: 999px;
    padding: 10px 14px;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
}

.mock-sso-notification-toggle.on {
    background: #16a34a;
}

.mock-sso-notification-toggle.off {
    background: #dc2626;
}
</style>
