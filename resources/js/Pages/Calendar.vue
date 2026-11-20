<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import CalendarBrowser from '@/Components/CalendarBrowser.vue';
import Icon from '@/Components/Icon.vue';

// ปฏิทิน (หน้า 5): staff เห็นวันลาของทุกคน · member เห็นของตัวเอง วันหยุด และวันล่วงเวลาพิเศษ
defineProps({
    events: { type: Object, default: () => ({}) },
    canManageSpecial: Boolean,
});

const page = usePage();
const isStaff = computed(() => ['admin', 'staff'].includes(page.props.auth.user.role));
const legend = [
    { label: 'วันหยุด', cls: 'bg-red-200' },
    { label: 'ล่วงเวลาพิเศษ', cls: 'bg-violet-700' },
    { label: 'ลา', cls: 'bg-navy-300' },
    { label: 'ไปราชการ', cls: 'bg-sun-300' },
];
</script>

<template>
    <Head title="ปฏิทิน" />
    <CalendarBrowser :initial-events="events" :legend="legend">
        <template #actions>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-[13px] text-muted">{{ isStaff ? 'แสดงวันลาของทุกคน' : 'แสดงเฉพาะวันลาของฉัน วันหยุด และวันล่วงเวลาพิเศษ' }}</p>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="isStaff" href="/staff/leaves" class="btn-outline h-10 text-sm"><Icon name="file-pen" :size="16" /> บันทึกวันลา</Link>
                    <Link v-if="canManageSpecial" href="/admin/settings?tab=schedules" class="btn h-10 bg-violet-700 text-sm text-white hover:bg-violet-800"><Icon name="plus" :size="16" /> กำหนดวันล่วงเวลาพิเศษ</Link>
                </div>
            </div>
        </template>
    </CalendarBrowser>
</template>
