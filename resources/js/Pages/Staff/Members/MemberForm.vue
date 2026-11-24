<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

// ฟอร์มเพิ่ม/แก้ไขสมาชิก · ตอนแก้ไข กลุ่มงานเปลี่ยนผ่าน "ย้ายกลุ่มงาน" เท่านั้น (เพื่อเก็บวันที่มีผล)
const props = defineProps({
    member: { type: Object, default: null },
    departments: { type: Array, required: true },
    employmentTypes: { type: Array, required: true },
    canAssignRoles: Boolean,
});
const emit = defineEmits(['close']);

const m = props.member;
const form = useForm({
    employee_code: m?.employee_code ?? '',
    prefix: m?.prefix ?? '',
    first_name: m?.first_name ?? '',
    last_name: m?.last_name ?? '',
    email: m?.email ?? '',
    role: m?.role ?? 'member',
    employment_type_id: m?.employment_type_id ?? '',
    department_id: m?.department_id ?? '',
    position: m?.position ?? '',
    start_date: m?.start_date ?? '',
    end_date: m?.end_date ?? '',
    ot_eligible: m?.ot_eligible ?? false,
    mobile_checkin: m?.mobile_checkin ?? false,
    flex_time: m?.flex_time ?? false,
});

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    const data = form.transform((d) => ({ ...d, employment_type_id: d.employment_type_id || null, department_id: d.department_id || null }));
    if (m) data.put(`/staff/members/${m.id}`, options);
    else data.post('/staff/members', options);
};

const roles = [
    { value: 'member', label: 'บุคลากร (member)' },
    { value: 'staff', label: 'เจ้าหน้าที่ HR (staff)' },
    { value: 'admin', label: 'ผู้ดูแลระบบ (admin)' },
];
</script>

<template>
    <Modal :title="m ? `แก้ไขข้อมูล ${m.full_name}` : 'เพิ่มสมาชิก'" @close="emit('close')">
        <form id="member-form" class="grid gap-4 sm:grid-cols-6" @submit.prevent="submit">
            <div class="sm:col-span-2">
                <label for="f-prefix" class="label">คำนำหน้า</label>
                <input id="f-prefix" v-model="form.prefix" class="input" placeholder="นาย / นาง / นางสาว">
            </div>
            <div class="sm:col-span-2">
                <label for="f-first" class="label">ชื่อ *</label>
                <input id="f-first" v-model="form.first_name" class="input" required>
                <p v-if="form.errors.first_name" class="field-error">{{ form.errors.first_name }}</p>
            </div>
            <div class="sm:col-span-2">
                <label for="f-last" class="label">นามสกุล *</label>
                <input id="f-last" v-model="form.last_name" class="input" required>
                <p v-if="form.errors.last_name" class="field-error">{{ form.errors.last_name }}</p>
            </div>

            <div class="sm:col-span-4">
                <label for="f-email" class="label">อีเมล Google</label>
                <input id="f-email" v-model="form.email" type="email" class="input" placeholder="ชื่อ@msu.ac.th">
                <p class="mt-1 text-xs text-muted">ใช้ login ด้วย Google · เว้นว่างได้ถ้าลงเวลาที่จอ kiosk อย่างเดียว</p>
                <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
            </div>
            <div class="sm:col-span-2">
                <label for="f-code" class="label">รหัสลงเวลา (kiosk)</label>
                <input id="f-code" v-model="form.employee_code" class="input" inputmode="numeric">
                <p v-if="form.errors.employee_code" class="field-error">{{ form.errors.employee_code }}</p>
            </div>

            <div v-if="!m" class="sm:col-span-3">
                <label for="f-dept" class="label">กลุ่มงาน *</label>
                <select id="f-dept" v-model="form.department_id" class="input" required>
                    <option value="" disabled>เลือกกลุ่มงาน</option>
                    <option v-for="d in departments.filter((d) => d.is_active)" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="form.errors.department_id" class="field-error">{{ form.errors.department_id }}</p>
            </div>
            <div class="sm:col-span-3">
                <label for="f-type" class="label">ประเภทบุคลากร</label>
                <select id="f-type" v-model="form.employment_type_id" class="input">
                    <option value="">— ไม่ระบุ —</option>
                    <option v-for="t in employmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <div :class="m ? 'sm:col-span-3' : 'sm:col-span-6'">
                <label for="f-pos" class="label">ตำแหน่ง</label>
                <input id="f-pos" v-model="form.position" class="input">
            </div>

            <div class="sm:col-span-3">
                <label for="f-start" class="label">วันเริ่มงาน</label>
                <input id="f-start" v-model="form.start_date" type="date" class="input">
            </div>
            <div class="sm:col-span-3">
                <label for="f-end" class="label">วันสิ้นสุด (เช่น นิสิตฝึกงาน)</label>
                <input id="f-end" v-model="form.end_date" type="date" class="input">
                <p class="mt-1 text-xs text-muted">พ้นวันนี้แล้วบัญชีจะใช้งานไม่ได้อัตโนมัติ</p>
                <p v-if="form.errors.end_date" class="field-error">{{ form.errors.end_date }}</p>
            </div>

            <div class="sm:col-span-6">
                <label for="f-role" class="label">สิทธิ์ในระบบ</label>
                <select id="f-role" v-model="form.role" class="input" :disabled="!canAssignRoles">
                    <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
                <p v-if="!canAssignRoles" class="mt-1 text-xs text-muted">กำหนดสิทธิ์ได้เฉพาะผู้ดูแลระบบ</p>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2 sm:col-span-6">
                <label class="flex items-center gap-2 text-[15px]"><input v-model="form.ot_eligible" type="checkbox" class="h-4 w-4 accent-navy-700"> มีสิทธิ์ลง OT</label>
                <label class="flex items-center gap-2 text-[15px]"><input v-model="form.mobile_checkin" type="checkbox" class="h-4 w-4 accent-navy-700"> อนุญาตลงเวลาผ่านมือถือ</label>
                <label class="flex items-start gap-2 text-[15px]"><input v-model="form.flex_time" type="checkbox" class="mt-1 h-4 w-4 accent-navy-700"> <span>เวลายืดหยุ่น <span class="block text-xs text-muted">นับชั่วโมงจริงตั้งแต่ 08:30 · กติกาตั้งที่ ตั้งค่า › รูปแบบเวลา</span></span></label>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="emit('close')">ยกเลิก</button>
            <button type="submit" form="member-form" class="btn-primary" :disabled="form.processing">บันทึก</button>
        </template>
    </Modal>
</template>
