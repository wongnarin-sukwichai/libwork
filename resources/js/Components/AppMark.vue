<script setup>
// สัญลักษณ์ระบบ: นาฬิกาในกรอบสี่เหลี่ยม (navy = ใน sidebar, sun = บนแถบหัวสีน้ำเงิน)
defineProps({
    tone: { type: String, default: 'navy' },
    size: { type: Number, default: 40 },
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center rounded-[11px]"
        :class="tone === 'sun' ? 'bg-sun-500' : 'bg-navy-700 shadow-nav'"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <svg :width="size / 2" :height="size / 2" viewBox="0 0 24 24" fill="none" :stroke="tone === 'sun' ? '#111' : '#fff'" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        </svg>
    </div>
</template>
