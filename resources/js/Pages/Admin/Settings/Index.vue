<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import HolidaysTab from './HolidaysTab.vue';
import KioskTab from './KioskTab.vue';
import LeavePolicyTab from './LeavePolicyTab.vue';
import SchedulesTab from './SchedulesTab.vue';
import FadeSwap from '@/Components/FadeSwap.vue';

const props = defineProps({
    tab: String,
    general: Object,
    departments: Array,
    employmentTypes: Array,
    schedules: Array,
    specialDays: Array,
    flex: Object,
    kiosk: Object,
    holidays: Array,
    leaveTypes: Array,
    policies: Array,
});

const tabs = [
    { key: 'general', label: 'ทั่วไป' },
    { key: 'departments', label: 'โครงสร้างหน่วยงาน' },
    { key: 'types', label: 'ประเภทบุคลากร' },
    { key: 'schedules', label: 'รูปแบบเวลา' },
    { key: 'kiosk', label: 'เครื่อง kiosk' },
    { key: 'holidays', label: 'วันหยุด' },
    { key: 'leave', label: 'นโยบายการลา' },
    { key: 'mobile', label: 'ลงเวลาผ่านมือถือ', soon: 'เฟส 2b' },
    { key: 'api', label: 'API token', soon: 'เฟส 5' },
];
const active = ref(props.tab);
const go = (key) => {
    active.value = key;
    window.history.replaceState(window.history.state, '', `?tab=${key}`);
};

// ---------- ทั่วไป ----------
const general = useForm({
    org_name: props.general.org_name,
    login_google: props.general.login_google,
    login_password: props.general.login_password,
    public_show_leave_type: props.general.public_show_leave_type,
    head_title: props.general.head_title ?? '',
    head_name: props.general.head_name ?? '',
});
const saveGeneral = () => general.put('/admin/settings/general', { preserveScroll: true });

// ---------- กลุ่มงาน ----------
const deptOrder = ref(props.departments.map((d) => d.id));
watch(() => props.departments, (list) => (deptOrder.value = list.map((d) => d.id)));
const deptById = (id) => props.departments.find((d) => d.id === id);
const moveDept = (index, delta) => {
    const list = [...deptOrder.value];
    const target = index + delta;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
    deptOrder.value = list;
    router.post('/admin/settings/departments/reorder', { ids: list }, { preserveScroll: true, preserveState: true });
};
const deptForm = useForm({ id: null, name: '', head_name: '', is_active: true });
const deptModal = ref(false);
const openDept = (d = null) => {
    deptForm.reset();
    deptForm.clearErrors();
    if (d) Object.assign(deptForm, { id: d.id, name: d.name, head_name: d.head_name ?? '', is_active: d.is_active });
    deptModal.value = true;
};
const saveDept = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (deptModal.value = false),
    };
    if (deptForm.id) deptForm.put(`/admin/settings/departments/${deptForm.id}`, options);
    else deptForm.post('/admin/settings/departments', options);
};

// ---------- ประเภทบุคลากร ----------
const typeForm = useForm({ id: null, name: '', has_leave_quota: true, is_active: true });
const typeModal = ref(false);
const openType = (t = null) => {
    typeForm.reset();
    typeForm.clearErrors();
    if (t) Object.assign(typeForm, { id: t.id, name: t.name, has_leave_quota: t.has_leave_quota, is_active: t.is_active });
    typeModal.value = true;
};
const saveType = () => {
    const options = { preserveScroll: true, onSuccess: () => (typeModal.value = false) };
    if (typeForm.id) typeForm.put(`/admin/settings/employment-types/${typeForm.id}`, options);
    else typeForm.post('/admin/settings/employment-types', options);
};
</script>

<template>
    <Head title="ตั้งค่า" />
    <div class="space-y-5">
        <div>
            <h1 class="text-[26px] font-bold">ตั้งค่า</h1>
            <p class="text-[15px] text-muted">เฉพาะผู้ดูแลระบบ</p>
        </div>

        <div class="flex flex-wrap gap-1 self-start rounded-xl border border-line bg-white p-1" role="tablist">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="active === t.key"
                class="rounded-[9px] px-4 py-2 text-sm"
                :class="active === t.key ? 'bg-navy-700 font-bold text-white' : t.soon ? 'text-navy-400' : 'text-ink hover:bg-soft'"
                @click="go(t.key)"
            >{{ t.label }}</button>
        </div>

        <FadeSwap>
        <!-- ทั่วไป -->
        <form v-if="active === 'general'" class="card max-w-2xl space-y-5 p-6" @submit.prevent="saveGeneral">
            <div>
                <label for="org" class="label">ชื่อหน่วยงาน</label>
                <input id="org" v-model="general.org_name" class="input" required>
                <p class="mt-1 text-xs text-muted">แสดงใต้ชื่อระบบ และหัวกระดาษรายงาน</p>
            </div>
            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="label">ช่องลงนาม "ผู้ตรวจ" ในรายงาน PDF</legend>
                <div>
                    <label for="ht" class="text-sm text-muted">ตำแหน่ง</label>
                    <input id="ht" v-model="general.head_title" class="input" placeholder="หัวหน้าสำนัก / ผู้อำนวยการ">
                </div>
                <div>
                    <label for="hn" class="text-sm text-muted">ชื่อ (เว้นว่างได้)</label>
                    <input id="hn" v-model="general.head_name" class="input">
                </div>
            </fieldset>
            <fieldset class="space-y-2.5">
                <legend class="label">วิธีเข้าสู่ระบบ</legend>
                <label class="flex items-start gap-2.5 text-[15px]">
                    <input v-model="general.login_google" type="checkbox" class="mt-1 h-4 w-4 accent-navy-700">
                    <span>Google <span v-if="!props.general.google_configured" class="text-sm text-red-700">(ยังไม่ได้ใส่ GOOGLE_CLIENT_ID ใน .env)</span></span>
                </label>
                <label class="flex items-start gap-2.5 text-[15px]">
                    <input v-model="general.login_password" type="checkbox" class="mt-1 h-4 w-4 accent-navy-700">
                    <span>อีเมลและรหัสผ่าน (สำรอง ใช้เมื่อ Google มีปัญหา)</span>
                </label>
                <p v-if="general.errors.login_google" class="field-error">{{ general.errors.login_google }}</p>
            </fieldset>
            <fieldset class="space-y-2.5">
                <legend class="label">ปฏิทินหน้าแรก (ไม่ต้อง login)</legend>
                <label class="flex items-start gap-2.5 text-[15px]">
                    <input v-model="general.public_show_leave_type" type="checkbox" class="mt-1 h-4 w-4 accent-navy-700">
                    <span>แสดงประเภทการลา (ลาป่วย ลากิจ ฯลฯ) <span class="block text-sm text-muted">ปิดไว้ = แสดงแค่ "ลา" และ "ไปราชการ" ตาม PDPA</span></span>
                </label>
            </fieldset>
            <div class="flex justify-end"><button type="submit" class="btn-primary" :disabled="general.processing">บันทึก</button></div>
        </form>

        <!-- โครงสร้างหน่วยงาน -->
        <section v-else-if="active === 'departments'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-2xl text-sm text-muted">เปลี่ยนชื่อได้ทันที (รายงานเก่าแสดงชื่อใหม่) · ลำดับนี้ใช้เรียงกลุ่มในรายงานประจำวัน · ย้ายบุคลากรทำที่หน้าสมาชิก</p>
                <button type="button" class="btn-primary" @click="openDept()"><Icon name="plus" :size="18" /> เพิ่มกลุ่มงาน</button>
            </div>
            <div class="card overflow-hidden">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-navy-700 text-left text-white">
                            <th class="w-24 px-3 py-3 font-semibold">ลำดับ</th>
                            <th class="px-3 py-3 font-semibold">กลุ่มงาน</th>
                            <th class="px-3 py-3 font-semibold">หัวหน้า (ช่องลงนาม)</th>
                            <th class="px-3 py-3 font-semibold">บุคลากร</th>
                            <th class="px-3 py-3 font-semibold">สถานะ</th>
                            <th class="px-3 py-3"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(id, i) in deptOrder" :key="id" class="border-b border-soft">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <span class="w-5 text-muted">{{ i + 1 }}</span>
                                    <button type="button" class="btn-ghost h-8 px-1.5" :disabled="i === 0" aria-label="เลื่อนขึ้น" @click="moveDept(i, -1)"><Icon name="arrow-up" :size="16" /></button>
                                    <button type="button" class="btn-ghost h-8 px-1.5" :disabled="i === deptOrder.length - 1" aria-label="เลื่อนลง" @click="moveDept(i, 1)"><Icon name="arrow-down" :size="16" /></button>
                                </div>
                            </td>
                            <td class="px-3 py-2 font-semibold">{{ deptById(id)?.name }}</td>
                            <td class="px-3 py-2 text-muted">{{ deptById(id)?.head_name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ deptById(id)?.users_count }} คน</td>
                            <td class="px-3 py-2">
                                <span class="pill" :class="deptById(id)?.is_active ? 'bg-green-50 text-green-800' : 'bg-soft text-muted'">{{ deptById(id)?.is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="btn-outline h-9 px-3 text-sm" @click="openDept(deptById(id))"><Icon name="pencil" :size="15" /> แก้ไข</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ประเภทบุคลากร -->
        <section v-else-if="active === 'types'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-2xl text-sm text-muted">ใช้ผูกนโยบายการลา (เฟส 3) · ประเภทที่ไม่มีโควต้าวันลา เช่น นิสิตฝึกงาน</p>
                <button type="button" class="btn-primary" @click="openType()"><Icon name="plus" :size="18" /> เพิ่มประเภท</button>
            </div>
            <div class="card overflow-hidden">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-navy-700 text-left text-white">
                            <th class="px-3 py-3 font-semibold">ประเภทบุคลากร</th>
                            <th class="px-3 py-3 font-semibold">โควต้าวันลา</th>
                            <th class="px-3 py-3 font-semibold">จำนวน</th>
                            <th class="px-3 py-3 font-semibold">สถานะ</th>
                            <th class="px-3 py-3"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in employmentTypes" :key="t.id" class="border-b border-soft">
                            <td class="px-3 py-2.5 font-semibold">{{ t.name }}</td>
                            <td class="px-3 py-2.5">{{ t.has_leave_quota ? 'มี' : 'ไม่มี' }}</td>
                            <td class="px-3 py-2.5">{{ t.users_count }} คน</td>
                            <td class="px-3 py-2.5"><span class="pill" :class="t.is_active ? 'bg-green-50 text-green-800' : 'bg-soft text-muted'">{{ t.is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</span></td>
                            <td class="px-3 py-2.5 text-right"><button type="button" class="btn-outline h-9 px-3 text-sm" @click="openType(t)"><Icon name="pencil" :size="15" /> แก้ไข</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <SchedulesTab v-else-if="active === 'schedules'" :schedules="schedules" :special-days="specialDays" :flex="flex" />
        <KioskTab v-else-if="active === 'kiosk'" :kiosk="kiosk" />
        <HolidaysTab v-else-if="active === 'holidays'" :holidays="holidays" />
        <LeavePolicyTab v-else-if="active === 'leave'" :leave-types="leaveTypes" :employment-types="employmentTypes" :policies="policies" />

        <div v-else class="card p-8 text-center">
            <p class="text-lg font-semibold">{{ tabs.find((t) => t.key === active)?.label }}</p>
            <p class="text-muted">จะเพิ่มใน{{ tabs.find((t) => t.key === active)?.soon }}</p>
        </div>
        </FadeSwap>
    </div>

    <Modal v-if="deptModal" :title="deptForm.id ? 'แก้ไขกลุ่มงาน' : 'เพิ่มกลุ่มงาน'" width="max-w-lg" @close="deptModal = false">
        <form id="dept-form" class="space-y-4" @submit.prevent="saveDept">
            <div>
                <label for="d-name" class="label">ชื่อกลุ่มงาน</label>
                <input id="d-name" v-model="deptForm.name" class="input" required>
                <p v-if="deptForm.errors.name" class="field-error">{{ deptForm.errors.name }}</p>
            </div>
            <div>
                <label for="d-head" class="label">ชื่อหัวหน้ากลุ่มงาน</label>
                <input id="d-head" v-model="deptForm.head_name" class="input" placeholder="ใช้ในช่องลงนามของรายงาน (ไม่บังคับ)">
            </div>
            <label v-if="deptForm.id" class="flex items-center gap-2.5 text-[15px]">
                <input v-model="deptForm.is_active" type="checkbox" class="h-4 w-4 accent-navy-700"> ใช้งานอยู่
            </label>
            <p v-if="deptForm.errors.is_active" class="field-error">{{ deptForm.errors.is_active }}</p>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="deptModal = false">ยกเลิก</button>
            <button type="submit" form="dept-form" class="btn-primary" :disabled="deptForm.processing">บันทึก</button>
        </template>
    </Modal>

    <Modal v-if="typeModal" :title="typeForm.id ? 'แก้ไขประเภทบุคลากร' : 'เพิ่มประเภทบุคลากร'" width="max-w-lg" @close="typeModal = false">
        <form id="type-form" class="space-y-4" @submit.prevent="saveType">
            <div>
                <label for="t-name" class="label">ชื่อประเภท</label>
                <input id="t-name" v-model="typeForm.name" class="input" required>
                <p v-if="typeForm.errors.name" class="field-error">{{ typeForm.errors.name }}</p>
            </div>
            <label class="flex items-center gap-2.5 text-[15px]"><input v-model="typeForm.has_leave_quota" type="checkbox" class="h-4 w-4 accent-navy-700"> มีโควต้าวันลา</label>
            <label v-if="typeForm.id" class="flex items-center gap-2.5 text-[15px]"><input v-model="typeForm.is_active" type="checkbox" class="h-4 w-4 accent-navy-700"> ใช้งานอยู่</label>
        </form>
        <template #footer>
            <button type="button" class="btn-outline" @click="typeModal = false">ยกเลิก</button>
            <button type="submit" form="type-form" class="btn-primary" :disabled="typeForm.processing">บันทึก</button>
        </template>
    </Modal>
</template>
