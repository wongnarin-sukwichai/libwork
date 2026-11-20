<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

// แสดงข้อความจาก session flash (success / error) มุมขวาล่าง แล้วหายเองใน 4 วินาที
const page = usePage();
const toast = ref(null);
let timer;

watch(
    () => page.props.flash,
    (flash) => {
        const message = flash?.success || flash?.error;
        if (!message) return;
        toast.value = { type: flash.success ? 'success' : 'error', message };
        clearTimeout(timer);
        timer = setTimeout(() => (toast.value = null), 4000);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Transition enter-from-class="translate-y-3 opacity-0" leave-to-class="translate-y-3 opacity-0" enter-active-class="transition" leave-active-class="transition">
        <div
            v-if="toast"
            role="status"
            class="fixed bottom-5 right-5 z-[60] flex max-w-sm items-center gap-3 rounded-2xl px-4 py-3 text-[15px] shadow-card"
            :class="toast.type === 'success' ? 'bg-navy-700 text-white' : 'bg-red-700 text-white'"
        >
            <Icon :name="toast.type === 'success' ? 'check' : 'x'" :size="18" />
            <span>{{ toast.message }}</span>
        </div>
    </Transition>
</template>
