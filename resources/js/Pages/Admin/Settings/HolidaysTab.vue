<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { thaiDate } from '@/utils/thaiDate';

// วันหยุด: ใช้ตัดวันลา ตัดวันทำการ และเลือก OT วันหยุดอัตโนมัติ
const props = defineProps({ holidays: Array });

const years = computed(() => [...new Set(props.holidays.map((h) => Number(h.date.slice(0, 4))))].sort());
const year = ref(new Date().getFullYear());
const list = computed(() => props.holidays.filter((h) => Number(h.date.slice(0, 4)) === year.value));

const form = useForm({ date: '', name: '' });
const add = () => form.post('/admin/settings/holidays', { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (h) => confirm(`ลบวันหยุด ${h.name}?`) && router.delete(`/admin/settings/holidays/${h.id}`, { preserveScroll: true });
</script>

<template>
    <div class="max-w-3xl space-y-4">
        <p class="text-sm text-muted">ระบบใส่วันหยุดราชการที่วันที่ตายตัวให้แล้ว · <b>วันหยุดตามจันทรคติ</b> (มาฆบูชา วิสาขบูชา อาสาฬหบูชา เข้าพรรษา) และ <b>วันหยุดชดเชย</b> กรุณาเพิ่มตามประกาศของแต่ละปี</p>
        <form class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="add">
            <div>
                <label for="h-date" class="label">วันที่</label>
                <input id="h-date" v-model="form.date" type="date" class="input" required>
            </div>
            <div class="min-w-[240px] flex-1">
                <label for="h-name" class="label">ชื่อวันหยุด</label>
                <input id="h-name" v-model="form.name" class="input" placeholder="เช่น วันหยุดชดเชยวันพ่อแห่งชาติ" required>
            </div>
            <button type="submit" class="btn-primary" :disabled="form.processing"><Icon name="plus" :size="18" /> เพิ่ม</button>
            <p v-if="form.errors.date" class="field-error w-full">{{ form.errors.date }}</p>
        </form>
        <div class="flex flex-wrap gap-1">
            <button v-for="y in years" :key="y" type="button" class="rounded-[9px] px-3.5 py-1.5 text-sm" :class="y === year ? 'bg-navy-700 font-bold text-white' : 'border border-line bg-white'" @click="year = y">{{ y + 543 }}</button>
        </div>
        <div class="card divide-y divide-soft">
            <p v-if="!list.length" class="px-4 py-4 text-sm text-muted">ไม่มีวันหยุดในปีนี้</p>
            <div v-for="h in list" :key="h.id" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                <div><b class="inline-block min-w-[190px]">{{ thaiDate(h.date, { weekday: true }) }}</b> {{ h.name }}</div>
                <button type="button" class="btn-ghost h-9 px-2" :aria-label="`ลบ ${h.name}`" @click="remove(h)"><Icon name="x" :size="17" /></button>
            </div>
        </div>
    </div>
</template>
