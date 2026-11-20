<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import MonthAttendanceTable from '@/Components/MonthAttendanceTable.vue';
import { THAI_MONTHS } from '@/utils/thaiDate';

// เวลาปฏิบัติงานของฉัน รายเดือน
const props = defineProps({ month: Object });

const [y, m] = props.month.month.split('-').map(Number);
const title = `${THAI_MONTHS[m - 1]} ${y + 543}`;
const now = new Date();
const isCurrent = computed(() => y === now.getFullYear() && m === now.getMonth() + 1);
const go = (delta) => {
    const d = new Date(y, m - 1 + delta, 1);
    router.get('/me/attendance', { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true });
};
</script>

<template>
    <Head title="เวลาปฏิบัติงานของฉัน" />
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-[26px] font-bold">เวลาปฏิบัติงานของฉัน</h1>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-outline w-11 px-0" aria-label="เดือนก่อนหน้า" @click="go(-1)"><Icon name="chevron-left" :size="18" /></button>
                <span class="min-w-[160px] text-center text-lg font-bold">{{ title }}</span>
                <button type="button" class="btn-outline w-11 px-0" aria-label="เดือนถัดไป" :disabled="isCurrent" @click="go(1)"><Icon name="chevron-right" :size="18" /></button>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-[18px] bg-sun-500 px-4 py-3.5 text-black shadow-card"><div class="text-sm">มาปฏิบัติงาน</div><div class="text-2xl font-bold">{{ month.stats.present }} / {{ month.stats.workdays }} วัน</div></div>
            <div class="card px-4 py-3.5"><div class="text-sm text-muted">ชั่วโมงปกติ</div><div class="text-2xl font-bold">{{ month.stats.hours }}</div></div>
            <div class="card px-4 py-3.5"><div class="text-sm text-muted">ชั่วโมง OT</div><div class="text-2xl font-bold">{{ month.stats.ot }}</div></div>
            <div class="rounded-[18px] bg-navy-700 px-4 py-3.5 text-white shadow-card"><div class="text-sm">มาสาย</div><div class="text-2xl font-bold">{{ month.stats.late_count }} ครั้ง</div></div>
        </div>
        <section class="card p-5">
            <MonthAttendanceTable :rows="month.rows" />
        </section>
    </div>
</template>
