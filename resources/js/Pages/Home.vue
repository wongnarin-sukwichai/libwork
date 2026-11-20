<script setup>
import { Head } from '@inertiajs/vue3';
import CalendarBrowser from '@/Components/CalendarBrowser.vue';
import { thaiDate, isoDate } from '@/utils/thaiDate';

// หน้าแรกสาธารณะ (หน้า 0): ปฏิทินวันลาและวันหยุด · ประเภทการลาแสดงตามการตั้งค่า (ค่าเริ่มต้นแสดงแค่ "ลา"/"ไปราชการ")
defineProps({
    events: { type: Object, default: () => ({}) },
    absentToday: { type: Array, default: () => [] },
    upcomingHolidays: { type: Array, default: () => [] },
});

const legend = [
    { label: 'ลา', cls: 'bg-navy-300' },
    { label: 'ไปราชการ', cls: 'bg-sun-300' },
    { label: 'วันหยุด', cls: 'bg-red-200' },
];
</script>

<template>
    <Head title="ปฏิทินวันลา" />
    <div class="flex flex-wrap items-start gap-5 px-4 py-6 sm:px-8">
        <section class="min-w-0 flex-[999_1_640px]">
            <CalendarBrowser :initial-events="events" :legend="legend" />
        </section>

        <aside class="flex flex-[1_1_280px] flex-col gap-3.5">
            <section class="rounded-[18px] bg-navy-700 p-4 text-white shadow-card">
                <h2 class="text-[17px] font-bold">วันนี้ · {{ thaiDate(isoDate(), { short: true, weekday: true }) }}</h2>
                <p class="mb-3 text-[13px] text-navy-300">ไม่อยู่ปฏิบัติงาน {{ absentToday.length }} คน</p>
                <p v-if="!absentToday.length" class="rounded-xl bg-white/10 px-3 py-2.5 text-sm text-navy-200">ไม่มีรายการลาวันนี้</p>
                <div v-for="p in absentToday" :key="p.id" class="mb-2 flex items-center gap-2.5 rounded-xl bg-white/10 px-2.5 py-2">
                    <div class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-sun-500 text-[13px] font-bold text-black">{{ p.name.slice(0, 2) }}</div>
                    <div class="min-w-0 flex-1 text-sm leading-snug">
                        <div class="font-semibold">{{ p.name }}</div>
                        <div class="text-navy-200">{{ p.department ?? '' }}</div>
                    </div>
                    <span class="pill bg-white text-navy-700">{{ p.type }}</span>
                </div>
            </section>
            <section class="card p-4">
                <h2 class="mb-2.5 text-[17px] font-bold">วันหยุดที่จะถึง</h2>
                <p v-if="!upcomingHolidays.length" class="text-sm text-muted">ยังไม่ได้กำหนดวันหยุด</p>
                <div v-for="h in upcomingHolidays" :key="h.date" class="flex justify-between gap-3 py-1 text-sm">
                    <span>{{ h.name }}</span><span class="whitespace-nowrap text-muted">{{ thaiDate(h.date, { short: true, weekday: true }) }}</span>
                </div>
            </section>
        </aside>
    </div>
</template>
