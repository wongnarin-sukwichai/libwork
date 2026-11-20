<script setup>
import { onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { thaiDate } from '@/utils/thaiDate';

// HR แก้ไข/เพิ่มเวลา: ต้องระบุเหตุผล ทุกครั้งเก็บประวัติก่อน-หลัง
const props = defineProps({
    row: { type: Object, required: true },
    date: { type: String, required: true },
    type: { type: String, required: true },
});
const emit = defineEmits(['close']);

const form = useForm({
    user_id: props.row.user_id,
    date: props.date,
    type: props.type,
    check_in: props.row.check_in ?? '',
    check_out: props.row.check_out ?? '',
    note: props.row.note ?? '',
    reason: '',
});

const logs = ref(null);
onMounted(async () => {
    const res = await fetch(`/staff/attendance/logs?user_id=${props.row.user_id}&date=${props.date}`, { headers: { Accept: 'application/json' } });
    logs.value = res.ok ? await res.json() : [];
});

const submit = () =>
    form.transform((d) => ({ ...d, check_in: d.check_in || null, check_out: d.check_out || null }))
        .post('/staff/attendance', { preserveScroll: true, onSuccess: () => emit('close') });
</script>

<template>
    <Modal :title="`${row.attendance_id ? 'แก้ไขเวลา' : 'เพิ่มเวลา'} · ${row.name}`" width="max-w-lg" @close="emit('close')">
        <p class="mb-4 text-sm text-muted">{{ thaiDate(date, { weekday: true }) }} · {{ type === 'ot' ? 'ล่วงเวลา (OT)' : 'เวลาปกติ' }}</p>
        <form id="att-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="a-in" class="label">เวลาเข้า</label>
                    <input id="a-in" v-model="form.check_in" type="time" class="input">
                </div>
                <div>
                    <label for="a-out" class="label">เวลาออก</label>
                    <input id="a-out" v-model="form.check_out" type="time" class="input">
                    <p v-if="form.errors.check_out" class="field-error">{{ form.errors.check_out }}</p>
                </div>
            </div>
            <div>
                <label for="a-note" class="label">หมายเหตุ (แสดงในรายงาน)</label>
                <input id="a-note" v-model="form.note" class="input" placeholder="เช่น รถเสีย, ไปประชุมนอกสถานที่">
            </div>
            <div>
                <label for="a-reason" class="label">เหตุผลการแก้ไข *</label>
                <input id="a-reason" v-model="form.reason" class="input" placeholder="เช่น ลืมลงเวลาออก ยืนยันโดยหัวหน้ากลุ่มงาน" required>
                <p v-if="form.errors.reason" class="field-error">{{ form.errors.reason }}</p>
            </div>
        </form>

        <div class="mt-5">
            <h3 class="mb-2 text-sm font-bold">ประวัติการแก้ไขวันนี้</h3>
            <p v-if="logs === null" class="text-sm text-muted">กำลังโหลด…</p>
            <p v-else-if="!logs.length" class="text-sm text-muted">ยังไม่เคยแก้ไข</p>
            <ol v-else class="space-y-1.5">
                <li v-for="l in logs" :key="l.id" class="rounded-xl bg-page px-3 py-2 text-sm">
                    <b>{{ l.at }}</b> · {{ l.by }}
                    <div class="text-muted">
                        {{ l.before ? `${l.before.check_in_at ?? '—'}–${l.before.check_out_at ?? '—'}` : 'ไม่มีข้อมูล' }} → {{ l.after?.check_in_at ?? '—' }}–{{ l.after?.check_out_at ?? '—' }} · {{ l.reason }}
                    </div>
                </li>
            </ol>
        </div>

        <template #footer>
            <button type="button" class="btn-outline" @click="emit('close')">ยกเลิก</button>
            <button type="submit" form="att-form" class="btn-primary" :disabled="form.processing">บันทึก</button>
        </template>
    </Modal>
</template>
