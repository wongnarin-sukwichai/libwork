<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';

// แบ่งหน้าฝั่งเบราว์เซอร์ (ข้อมูลโหลดมาครบแล้ว) · v-model = เลขหน้า เริ่มที่ 1
const props = defineProps({
    modelValue: { type: Number, required: true },
    total: { type: Number, required: true },
    perPage: { type: Number, default: 25 },
});
const emit = defineEmits(['update:modelValue']);

const last = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
const from = computed(() => (props.total ? (props.modelValue - 1) * props.perPage + 1 : 0));
const to = computed(() => Math.min(props.total, props.modelValue * props.perPage));
// เลขหน้าที่แสดง: หน้าแรก หน้าสุดท้าย และรอบ ๆ หน้าปัจจุบัน (… แทนช่วงที่ข้าม)
const pages = computed(() => {
    const out = [];
    for (let p = 1; p <= last.value; p++) {
        if (p === 1 || p === last.value || Math.abs(p - props.modelValue) <= 1) out.push(p);
        else if (out[out.length - 1] !== '…') out.push('…');
    }
    return out;
});
const go = (p) => emit('update:modelValue', Math.min(last.value, Math.max(1, p)));
</script>

<template>
    <nav v-if="total > perPage" class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-sm" aria-label="แบ่งหน้า">
        <span class="text-muted">แสดง {{ from }}–{{ to }} จาก {{ total }} รายการ</span>
        <div class="flex items-center gap-1">
            <button type="button" class="btn-ghost h-9 w-9 px-0" :disabled="modelValue === 1" aria-label="หน้าก่อนหน้า" @click="go(modelValue - 1)"><Icon name="chevron-left" :size="16" /></button>
            <template v-for="(p, i) in pages" :key="i">
                <span v-if="p === '…'" class="px-1 text-muted">…</span>
                <button
                    v-else
                    type="button"
                    class="h-9 min-w-9 rounded-lg px-2"
                    :class="p === modelValue ? 'bg-navy-700 font-bold text-white' : 'hover:bg-soft'"
                    :aria-current="p === modelValue ? 'page' : undefined"
                    @click="go(p)"
                >{{ p }}</button>
            </template>
            <button type="button" class="btn-ghost h-9 w-9 px-0" :disabled="modelValue === last" aria-label="หน้าถัดไป" @click="go(modelValue + 1)"><Icon name="chevron-right" :size="16" /></button>
        </div>
    </nav>
</template>
