<script setup>
import { computed, ref } from 'vue';
import Icon from '@/Components/Icon.vue';
import MonthCalendar from '@/Components/MonthCalendar.vue';
import { THAI_MONTHS } from '@/utils/thaiDate';

// ปฏิทินพร้อมปุ่มเลื่อนเดือน โหลดเหตุการณ์ของเดือนอื่นจาก /calendar/events
const props = defineProps({
    initialEvents: { type: Object, default: () => ({}) },
    legend: { type: Array, default: () => [] }, // [{ label, cls }]
});

const now = new Date();
const year = ref(now.getFullYear());
const month = ref(now.getMonth());
const events = ref(props.initialEvents);
const loading = ref(false);
const title = computed(() => `${THAI_MONTHS[month.value]} ${year.value + 543}`);

async function load() {
    loading.value = true;
    try {
        const res = await fetch(`/calendar/events?year=${year.value}&month=${month.value + 1}`, { headers: { Accept: 'application/json' } });
        if (res.ok) events.value = await res.json();
    } finally {
        loading.value = false;
    }
}
const shift = (delta) => {
    const d = new Date(year.value, month.value + delta, 1);
    year.value = d.getFullYear();
    month.value = d.getMonth();
    load();
};
const goToday = () => {
    year.value = now.getFullYear();
    month.value = now.getMonth();
    load();
};
</script>

<template>
    <div class="space-y-3.5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <button type="button" class="btn-outline w-11 px-0" aria-label="เดือนก่อนหน้า" @click="shift(-1)"><Icon name="chevron-left" :size="18" /></button>
                <h1 class="min-w-[210px] text-center text-2xl font-bold" aria-live="polite">{{ title }}</h1>
                <button type="button" class="btn-outline w-11 px-0" aria-label="เดือนถัดไป" @click="shift(1)"><Icon name="chevron-right" :size="18" /></button>
                <button type="button" class="btn-primary h-11 px-4 text-sm" @click="goToday">วันนี้</button>
                <span v-if="loading" class="text-sm text-muted">กำลังโหลด…</span>
            </div>
            <div class="flex flex-wrap gap-x-3.5 gap-y-2 text-[13px]">
                <span v-for="l in legend" :key="l.label" class="flex items-center gap-1.5"><span class="h-3 w-3 rounded" :class="l.cls" />{{ l.label }}</span>
            </div>
        </div>
        <slot name="actions" />
        <MonthCalendar :year="year" :month="month" :events="events" />
    </div>
</template>
