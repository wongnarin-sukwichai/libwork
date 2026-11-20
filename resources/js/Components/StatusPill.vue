<script setup>
// ป้ายสถานะการลงเวลา · tone: ok | late | bad | leave | ot | neutral
defineProps({
    tone: { type: String, default: 'neutral' },
    text: { type: String, required: true },
});

const tones = {
    ok: 'bg-green-50 text-green-800',
    late: 'bg-orange-50 text-orange-800',
    bad: 'bg-red-50 text-red-800',
    leave: 'bg-soft-2 text-navy-700',
    duty: 'bg-sun-100 text-sun-800',
    ot: 'bg-violet-50 text-violet-800',
    neutral: 'bg-[#F2F4F7] text-[#52606D]',
};
</script>

<template>
    <span class="pill" :class="tones[tone] ?? tones.neutral">{{ text }}</span>
</template>
