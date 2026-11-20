<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { isoDate } from '@/utils/thaiDate';

// ย้ายกลุ่มงานพร้อมวันที่มีผล: รายงานก่อนวันนี้ยังจัดคนไว้ในกลุ่มเดิม
const props = defineProps({
    members: { type: Array, required: true }, // [{ id, full_name, department }]
    departments: { type: Array, required: true },
});
const emit = defineEmits(['close']);

const form = useForm({
    user_ids: props.members.map((m) => m.id),
    department_id: '',
    effective_from: isoDate(),
    note: '',
});

const submit = () => form.post('/staff/members/move', { preserveScroll: true, onSuccess: () => emit('close') });
</script>

<template>
    <Modal :title="`ย้ายกลุ่มงาน (${members.length} คน)`" width="max-w-lg" @close="emit('close')">
        <form id="move-form" class="space-y-4" @submit.prevent="submit">
            <ul class="max-h-40 space-y-1 overflow-y-auto rounded-xl bg-page p-3 text-sm">
                <li v-for="m in members" :key="m.id"><b>{{ m.full_name }}</b> <span class="text-muted">· ปัจจุบัน {{ m.department ?? '—' }}</span></li>
            </ul>
            <div>
                <label for="mv-dept" class="label">ย้ายไปกลุ่มงาน</label>
                <select id="mv-dept" v-model="form.department_id" class="input" required>
                    <option value="" disabled>เลือกกลุ่มงาน</option>
                    <option v-for="d in departments.filter((d) => d.is_active)" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="form.errors.department_id" class="field-error">{{ form.errors.department_id }}</p>
            </div>
            <div>
                <label for="mv-date" class="label">มีผลตั้งแต่วันที่</label>
                <input id="mv-date" v-model="form.effective_from" type="date" class="input" required>
                <p class="mt-1 text-xs text-muted">รายงานก่อนวันนี้จะยังแสดงในกลุ่มงานเดิม · ใส่วันในอนาคตได้</p>
            </div>
            <div>
                <label for="mv-note" class="label">หมายเหตุ</label>
                <input id="mv-note" v-model="form.note" class="input" placeholder="เช่น ตามคำสั่งที่ .../2569">
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="emit('close')">ยกเลิก</button>
            <button type="submit" form="move-form" class="btn-primary" :disabled="form.processing">ย้ายกลุ่มงาน</button>
        </template>
    </Modal>
</template>
