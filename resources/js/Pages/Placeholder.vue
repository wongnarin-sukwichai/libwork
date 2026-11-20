<script setup>
import { Head } from '@inertiajs/vue3';

// หน้าชั่วคราวของเมนูที่ยังไม่ได้สร้าง
defineProps({
    title: { type: String, required: true },
    phase: { type: String, default: '' },
});
</script>

<template>
    <Head :title="title" />
    <h1 class="mb-4 text-2xl font-bold">{{ title }}</h1>
    <div class="card p-8 text-center">
        <p class="text-lg font-semibold">หน้านี้กำลังพัฒนา</p>
        <p v-if="phase" class="mt-1 text-muted">อยู่ในแผน {{ phase }}</p>
    </div>
</template>
