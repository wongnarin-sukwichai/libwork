<script setup>
import { computed, nextTick, ref, watch } from 'vue';

// ช่องค้นหาบุคลากรแบบ autocomplete: พิมพ์ชื่อ/นามสกุล/รหัส แล้วเลือกจากรายการที่ขึ้นมา
// ใช้คีย์บอร์ดได้ (↑ ↓ Enter Esc) · v-model = id ของคนที่เลือก · emit('select', person) เมื่อเลือก
const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    people: { type: Array, required: true }, // [{ id, name, code, department, active }]
    id: { type: String, default: 'person-picker' },
    placeholder: { type: String, default: 'พิมพ์ชื่อหรือรหัสเพื่อค้นหา' },
    limit: { type: Number, default: 8 },
});
const emit = defineEmits(['update:modelValue', 'select']);

const current = computed(() => props.people.find((p) => p.id === props.modelValue) ?? null);
const text = ref(current.value?.name ?? '');
const open = ref(false);
const active = ref(0);
const listId = `${props.id}-list`;

watch(current, (p) => (text.value = p?.name ?? ''));

const matches = computed(() => {
    const q = text.value.trim().toLowerCase();
    // ข้อความในช่องตรงกับคนที่เลือกอยู่ = แสดงทั้งหมด (เพิ่งคลิกเข้ามาเพื่อเปลี่ยนคน)
    const list = !q || q === current.value?.name.toLowerCase()
        ? props.people
        : props.people.filter((p) => p.name.toLowerCase().includes(q) || (p.code ?? '').toLowerCase().includes(q));
    return list.slice(0, props.limit);
});

const show = () => {
    open.value = true;
    active.value = 0;
};
const pick = (p) => {
    open.value = false;
    text.value = p.name;
    emit('update:modelValue', p.id);
    emit('select', p);
};
const onKey = (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!open.value) return show();
        const n = matches.value.length;
        active.value = (active.value + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
        nextTick(() => document.getElementById(`${listId}-${active.value}`)?.scrollIntoView({ block: 'nearest' }));
    } else if (e.key === 'Enter' && open.value && matches.value[active.value]) {
        e.preventDefault();
        pick(matches.value[active.value]);
    } else if (e.key === 'Escape') {
        open.value = false;
        text.value = current.value?.name ?? '';
    }
};
// ออกจากช่องโดยไม่ได้เลือก = คืนชื่อคนเดิม (หน่วงให้คลิกรายการทันก่อน)
const onBlur = () => setTimeout(() => {
    open.value = false;
    text.value = current.value?.name ?? '';
}, 150);
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="text"
            type="text"
            class="input"
            role="combobox"
            autocomplete="off"
            :placeholder="placeholder"
            :aria-expanded="open"
            :aria-controls="listId"
            :aria-activedescendant="open && matches.length ? `${listId}-${active}` : undefined"
            @focus="show(); $event.target.select()"
            @input="show"
            @keydown="onKey"
            @blur="onBlur"
        >
        <ul
            v-if="open"
            :id="listId"
            role="listbox"
            class="absolute inset-x-0 top-full z-30 mt-1 max-h-80 overflow-y-auto rounded-xl border border-line bg-white py-1 shadow-card"
        >
            <li v-if="!matches.length" class="px-3.5 py-2.5 text-sm text-muted">ไม่พบชื่อที่ค้นหา</li>
            <li
                v-for="(p, i) in matches"
                :id="`${listId}-${i}`"
                :key="p.id"
                role="option"
                :aria-selected="i === active"
                class="flex cursor-pointer items-center justify-between gap-3 px-3.5 py-2 text-[15px]"
                :class="i === active ? 'bg-soft-2' : ''"
                @mousedown.prevent="pick(p)"
                @mouseenter="active = i"
            >
                <span>
                    <span class="font-semibold">{{ p.name }}</span>
                    <span v-if="!p.active" class="ml-1.5 text-xs text-muted">· ระงับ</span>
                    <span v-if="p.department" class="block text-xs text-muted">{{ p.department }}</span>
                </span>
                <span v-if="p.code" class="shrink-0 text-xs text-muted">{{ p.code }}</span>
            </li>
        </ul>
    </div>
</template>
