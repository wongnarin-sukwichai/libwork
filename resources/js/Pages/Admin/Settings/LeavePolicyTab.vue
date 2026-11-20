<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';

// ประเภทลา + ตารางสิทธิ์ต่อปีตามประเภทบุคลากร
const props = defineProps({ leaveTypes: Array, employmentTypes: Array, policies: Array });

const quotaTypes = computed(() => props.leaveTypes.filter((t) => t.counts_quota && t.is_active));
const empTypes = computed(() => props.employmentTypes.filter((e) => e.has_leave_quota && e.is_active));
const empId = ref(empTypes.value[0]?.id ?? null);

// สร้างแถวครบทุกคู่ (ประเภทบุคลากร × ประเภทลา) ว่าง = ไม่มีสิทธิ์
const find = (e, l) => props.policies.find((p) => p.employment_type_id === e && p.leave_type_id === l);
const form = useForm({
    policies: props.employmentTypes.flatMap((e) => props.leaveTypes.map((l) => {
        const p = find(e.id, l.id);
        return { employment_type_id: e.id, leave_type_id: l.id, days_per_year: p?.days_per_year ?? '', min_service_months: p?.min_service_months ?? 0, max_total_days: p?.max_total_days ?? '', max_total_days_10y: p?.max_total_days_10y ?? '' };
    })),
});
const rows = computed(() => form.policies.filter((p) => p.employment_type_id === empId.value && quotaTypes.value.some((t) => t.id === p.leave_type_id)));
const typeName = (id) => props.leaveTypes.find((t) => t.id === id)?.name;
const isCarry = (id) => props.leaveTypes.find((t) => t.id === id)?.carry_over;
const save = () => form.transform((d) => ({
    policies: d.policies.map((p) => ({ ...p, days_per_year: p.days_per_year === '' ? null : p.days_per_year, max_total_days: p.max_total_days === '' ? null : p.max_total_days, max_total_days_10y: p.max_total_days_10y === '' ? null : p.max_total_days_10y })),
})).put('/admin/settings/leave-policies', { preserveScroll: true });

const typeForm = useForm({ id: null, name: '', category: 'leave', counts_quota: true, carry_over: false, allow_half_day: true, is_active: true });
const typeModal = ref(false);
const openType = (t = null) => {
    typeForm.clearErrors();
    Object.assign(typeForm, t ? { ...t } : { id: null, name: '', category: 'leave', counts_quota: true, carry_over: false, allow_half_day: true, is_active: true });
    typeModal.value = true;
};
const saveType = () => {
    const options = { preserveScroll: true, onSuccess: () => (typeModal.value = false) };
    if (typeForm.id) typeForm.put(`/admin/settings/leave-types/${typeForm.id}`, options);
    else typeForm.post('/admin/settings/leave-types', options);
};
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold">ประเภทการลา</h2>
                <button type="button" class="btn-primary" @click="openType()"><Icon name="plus" :size="18" /> เพิ่มประเภทลา</button>
            </div>
            <div class="card overflow-hidden">
                <table class="w-full border-collapse text-sm">
                    <thead><tr class="bg-navy-700 text-left text-white"><th class="px-3 py-3 font-semibold">ประเภท</th><th class="px-3 py-3 font-semibold">หน้าแรกแสดงเป็น</th><th class="px-3 py-3 font-semibold">หักโควต้า</th><th class="px-3 py-3 font-semibold">ยกยอดข้ามปี</th><th class="px-3 py-3 font-semibold">ครึ่งวัน</th><th class="px-3 py-3" /></tr></thead>
                    <tbody>
                        <tr v-for="t in leaveTypes" :key="t.id" class="border-b border-soft" :class="{ 'opacity-50': !t.is_active }">
                            <td class="px-3 py-2.5 font-semibold">{{ t.name }}</td>
                            <td class="px-3 py-2.5">{{ t.category === 'duty' ? 'ไปราชการ' : 'ลา' }}</td>
                            <td class="px-3 py-2.5">{{ t.counts_quota ? '✓' : '—' }}</td>
                            <td class="px-3 py-2.5">{{ t.carry_over ? '✓' : '—' }}</td>
                            <td class="px-3 py-2.5">{{ t.allow_half_day ? '✓' : '—' }}</td>
                            <td class="px-3 py-2.5 text-right"><button type="button" class="btn-outline h-9 px-3 text-sm" @click="openType(t)"><Icon name="pencil" :size="15" /> แก้ไข</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <form class="space-y-3" @submit.prevent="save">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">สิทธิ์ต่อปีตามประเภทบุคลากร</h2>
                    <p class="text-sm text-muted">ค่าตั้งต้นอิงระเบียบการลาของข้าราชการ (ตัวอย่าง) กรุณาตรวจให้ตรงกับข้อบังคับของหน่วยงาน · เว้นว่าง = ไม่มีสิทธิ์</p>
                </div>
                <button type="submit" class="btn-primary" :disabled="form.processing">บันทึกนโยบาย</button>
            </div>
            <div class="flex flex-wrap gap-1">
                <button v-for="e in empTypes" :key="e.id" type="button" class="rounded-[9px] px-3.5 py-1.5 text-sm" :class="e.id === empId ? 'bg-navy-700 font-bold text-white' : 'border border-line bg-white'" @click="empId = e.id">{{ e.name }}</button>
            </div>
            <div class="card overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead><tr class="bg-soft text-left"><th class="px-3 py-3 font-semibold">ประเภทลา</th><th class="px-3 py-3 font-semibold">วัน/ปี</th><th class="px-3 py-3 font-semibold">ต้องทำงานครบ (เดือน)</th><th class="px-3 py-3 font-semibold">ยกมา+ปีนี้ รวมไม่เกิน</th><th class="px-3 py-3 font-semibold">… เมื่ออายุงาน ≥ 10 ปี</th></tr></thead>
                    <tbody>
                        <tr v-for="p in rows" :key="p.leave_type_id" class="border-b border-soft">
                            <td class="px-3 py-2 font-semibold">{{ typeName(p.leave_type_id) }}</td>
                            <td class="px-3 py-2"><input v-model="p.days_per_year" type="number" step="0.5" min="0" class="input h-9 w-24" :aria-label="`วันต่อปี ${typeName(p.leave_type_id)}`"></td>
                            <td class="px-3 py-2"><input v-model="p.min_service_months" type="number" min="0" class="input h-9 w-24" :aria-label="`เดือนขั้นต่ำ ${typeName(p.leave_type_id)}`"></td>
                            <td class="px-3 py-2"><input v-if="isCarry(p.leave_type_id)" v-model="p.max_total_days" type="number" step="0.5" min="0" class="input h-9 w-24" :aria-label="`เพดานรวม ${typeName(p.leave_type_id)}`"><span v-else class="text-muted">ไม่ยกยอด</span></td>
                            <td class="px-3 py-2"><input v-if="isCarry(p.leave_type_id)" v-model="p.max_total_days_10y" type="number" step="0.5" min="0" class="input h-9 w-24" :aria-label="`เพดานรวม 10 ปี ${typeName(p.leave_type_id)}`"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <Modal v-if="typeModal" :title="typeForm.id ? `แก้ไข ${typeForm.name}` : 'เพิ่มประเภทลา'" width="max-w-lg" @close="typeModal = false">
        <form id="lt" class="space-y-4" @submit.prevent="saveType">
            <div><label for="lt-n" class="label">ชื่อ</label><input id="lt-n" v-model="typeForm.name" class="input" required><p v-if="typeForm.errors.name" class="field-error">{{ typeForm.errors.name }}</p></div>
            <div><label for="lt-c" class="label">หน้าแรกสาธารณะแสดงเป็น</label><select id="lt-c" v-model="typeForm.category" class="input"><option value="leave">ลา</option><option value="duty">ไปราชการ</option></select></div>
            <label class="flex items-center gap-2.5"><input v-model="typeForm.counts_quota" type="checkbox" class="h-4 w-4 accent-navy-700"> หักโควต้า</label>
            <label class="flex items-center gap-2.5"><input v-model="typeForm.carry_over" type="checkbox" class="h-4 w-4 accent-navy-700"> ยกยอดคงเหลือไปปีงบถัดไป</label>
            <label class="flex items-center gap-2.5"><input v-model="typeForm.allow_half_day" type="checkbox" class="h-4 w-4 accent-navy-700"> ลาครึ่งวันได้</label>
            <label v-if="typeForm.id" class="flex items-center gap-2.5"><input v-model="typeForm.is_active" type="checkbox" class="h-4 w-4 accent-navy-700"> ใช้งานอยู่</label>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="typeModal = false">ยกเลิก</button>
            <button type="submit" form="lt" class="btn-primary" :disabled="typeForm.processing">บันทึก</button>
        </template>
    </Modal>
</template>
