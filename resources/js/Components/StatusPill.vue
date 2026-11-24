<script setup>
import { STATUS_TONES } from '@/utils/statusTones';

// ป้ายสถานะการลงเวลา · tone: ok | late | bad | absent | leave | duty | ot | special | neutral
defineProps({
    tone: { type: String, default: 'neutral' },
    text: { type: String, required: true },
});
</script>

<template>
    <span class="pill" :class="STATUS_TONES[tone] ?? STATUS_TONES.neutral">{{ text }}</span>
</template>
