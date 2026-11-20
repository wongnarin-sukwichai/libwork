<script setup>
import { computed } from 'vue';
import { THAI_DAYS_SHORT, isoDate } from '@/utils/thaiDate';

// ปฏิทินรายเดือน เริ่มวันอาทิตย์ · events: { 'YYYY-MM-DD': [{ label, tone }] }
// tone: leave | duty | holiday | otx | ot
const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true }, // 0-11
    events: { type: Object, default: () => ({}) },
});

const today = isoDate();

const cells = computed(() => {
    const first = new Date(props.year, props.month, 1);
    const start = new Date(first);
    start.setDate(1 - first.getDay());
    const weeks = Math.ceil((first.getDay() + new Date(props.year, props.month + 1, 0).getDate()) / 7);
    return Array.from({ length: weeks * 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const key = isoDate(d);
        return {
            key,
            day: d.getDate(),
            inMonth: d.getMonth() === props.month,
            weekend: d.getDay() === 0 || d.getDay() === 6,
            today: key === today,
            events: props.events[key] ?? [],
        };
    });
});

const toneClass = {
    leave: 'bg-soft-2 text-navy-700',
    duty: 'bg-sun-100 text-sun-800',
    holiday: 'bg-red-50 text-red-800 font-semibold',
    otx: 'bg-violet-700 text-white font-semibold',
    ot: 'bg-teal-50 text-teal-800',
};
</script>

<template>
    <div class="overflow-x-auto">
        <div class="card min-w-[720px] overflow-hidden">
            <div class="grid grid-cols-7 bg-soft">
                <div v-for="d in THAI_DAYS_SHORT" :key="d" class="p-2.5 text-center text-[13px] font-semibold">{{ d }}</div>
            </div>
            <div class="grid grid-cols-7">
                <div
                    v-for="c in cells"
                    :key="c.key"
                    class="min-h-[112px] border-b border-r border-soft p-2"
                    :class="[c.today ? 'bg-page shadow-[inset_0_0_0_2px_var(--color-navy-700)]' : c.inMonth ? (c.weekend ? 'bg-[#f8fafd]' : 'bg-white') : 'bg-[#fafbfd]']"
                >
                    <div
                        v-if="c.today"
                        class="flex h-[26px] w-[26px] items-center justify-center rounded-full bg-navy-700 text-[13px] font-bold text-white"
                    >{{ c.day }}</div>
                    <div v-else class="text-sm font-semibold" :class="!c.inMonth ? 'text-navy-400' : c.weekend ? 'text-red-700' : ''">{{ c.day }}</div>
                    <div
                        v-for="(e, i) in c.events"
                        :key="i"
                        class="mt-1 rounded-md px-1.5 py-0.5 text-xs leading-snug"
                        :class="toneClass[e.tone] ?? toneClass.leave"
                    >{{ e.label }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
