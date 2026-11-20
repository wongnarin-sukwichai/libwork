<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import MemberForm from './MemberForm.vue';
import MoveDialog from './MoveDialog.vue';
import { thaiDate } from '@/utils/thaiDate';

const props = defineProps({
    members: Object,
    filters: Object,
    counts: Object,
    total: Number,
    departments: Array,
    employmentTypes: Array,
    canAssignRoles: Boolean,
});

// ---------- ตัวกรอง ----------
const q = ref(props.filters.q ?? '');
const apply = (patch) => {
    router.get('/staff/members', { ...props.filters, q: q.value || undefined, page: undefined, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
};
let t;
watch(q, () => {
    clearTimeout(t);
    t = setTimeout(() => apply({}), 350);
});
const roleFilters = computed(() => [
    { value: undefined, label: 'ทั้งหมด', count: props.total },
    { value: 'admin', label: 'admin', count: props.counts.admin ?? 0 },
    { value: 'staff', label: 'staff', count: props.counts.staff ?? 0 },
    { value: 'member', label: 'member', count: props.counts.member ?? 0 },
]);

// ---------- เลือกหลายคนเพื่อย้ายกลุ่มงาน ----------
const selected = ref([]);
const allOnPage = computed(() => props.members.data.length > 0 && props.members.data.every((m) => selected.value.includes(m.id)));
const toggleAll = () => {
    const ids = props.members.data.map((m) => m.id);
    selected.value = allOnPage.value ? selected.value.filter((id) => !ids.includes(id)) : [...new Set([...selected.value, ...ids])];
};
const selectedMembers = computed(() => props.members.data.filter((m) => selected.value.includes(m.id)));

// ---------- dialog ----------
const editing = ref(null); // null = ปิด, {} = เพิ่มใหม่, member = แก้ไข
const moving = ref(null);
const historyOf = ref(null);
const history = ref([]);

const openHistory = async (m) => {
    historyOf.value = m;
    history.value = [];
    const res = await fetch(`/staff/members/${m.id}/history`, { headers: { Accept: 'application/json' } });
    history.value = await res.json();
};
const toggle = (m) => {
    const action = m.is_active ? 'ระงับการใช้งาน' : 'เปิดใช้งาน';
    if (confirm(`${action} ${m.full_name}?`)) router.post(`/staff/members/${m.id}/toggle`, {}, { preserveScroll: true });
};

const roleClass = { admin: 'bg-navy-700 text-white', staff: 'bg-sun-500 text-black', member: 'bg-soft text-ink' };
const status = (m) => (!m.is_active ? ['ระงับ', 'bg-soft text-muted'] : !m.last_login_at && m.email ? ['รอ login ครั้งแรก', 'bg-sun-100 text-sun-800'] : ['ใช้งาน', 'bg-green-50 text-green-800']);
</script>

<template>
    <Head title="สมาชิก" />
    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[26px] font-bold">สมาชิก</h1>
                <p class="text-[15px] text-muted">{{ total }} คน · เข้าระบบด้วย Google ได้เฉพาะอีเมลในรายการนี้</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-if="selected.length" type="button" class="btn-navy" @click="moving = selectedMembers">
                    <Icon name="arrow-right-left" :size="18" /> ย้ายกลุ่มงาน ({{ selected.length }})
                </button>
                <button type="button" class="btn-primary" @click="editing = {}"><Icon name="plus" :size="18" /> เพิ่มสมาชิก</button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[240px] flex-1">
                <Icon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-navy-400" />
                <label for="q" class="sr-only">ค้นหา</label>
                <input id="q" v-model="q" class="input pl-10" placeholder="ค้นหาชื่อ อีเมล หรือรหัสลงเวลา">
            </div>
            <button
                v-for="r in roleFilters"
                :key="r.label"
                type="button"
                class="btn h-11 px-3.5 text-sm"
                :class="(filters.role ?? undefined) === r.value ? 'bg-navy-700 text-white' : 'border border-line-strong bg-white'"
                @click="apply({ role: r.value })"
            >{{ r.label }} <span class="opacity-70">{{ r.count }}</span></button>
            <label for="dept" class="sr-only">กลุ่มงาน</label>
            <select id="dept" class="input w-auto" :value="filters.department ?? ''" @change="apply({ department: $event.target.value || undefined })">
                <option value="">ทุกกลุ่มงาน</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}{{ d.is_active ? '' : ' (ปิดใช้งาน)' }}</option>
            </select>
            <label for="status" class="sr-only">สถานะ</label>
            <select id="status" class="input w-auto" :value="filters.status ?? ''" @change="apply({ status: $event.target.value || undefined })">
                <option value="">ทุกสถานะ</option>
                <option value="active">ใช้งาน</option>
                <option value="suspended">ระงับ</option>
            </select>
        </div>

        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-navy-700 text-left text-white">
                            <th class="w-10 px-3 py-3"><input type="checkbox" class="h-4 w-4 accent-sun-500" :checked="allOnPage" aria-label="เลือกทั้งหมดในหน้านี้" @change="toggleAll"></th>
                            <th class="px-2 py-3 font-semibold">ชื่อ / อีเมล Google</th>
                            <th class="px-2 py-3 font-semibold">รหัส</th>
                            <th class="px-2 py-3 font-semibold">กลุ่มงาน</th>
                            <th class="px-2 py-3 font-semibold">ประเภท</th>
                            <th class="px-2 py-3 font-semibold">สิทธิ์</th>
                            <th class="px-2 py-3 text-center font-semibold">OT</th>
                            <th class="px-2 py-3 text-center font-semibold">มือถือ</th>
                            <th class="px-2 py-3 font-semibold">สถานะ</th>
                            <th class="px-2 py-3"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!members.data.length"><td colspan="10" class="px-4 py-10 text-center text-muted">ไม่พบสมาชิก</td></tr>
                        <tr v-for="m in members.data" :key="m.id" class="border-b border-soft" :class="{ 'bg-page': selected.includes(m.id) }">
                            <td class="px-3 py-2.5"><input v-model="selected" type="checkbox" :value="m.id" class="h-4 w-4 accent-navy-700" :aria-label="`เลือก ${m.full_name}`"></td>
                            <td class="px-2 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <img v-if="m.avatar" :src="m.avatar" alt="" class="h-[34px] w-[34px] rounded-full object-cover" referrerpolicy="no-referrer">
                                    <div v-else class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-soft-2 text-[13px] font-bold text-navy-700">{{ m.first_name.slice(0, 2) }}</div>
                                    <div>
                                        <div class="font-semibold">{{ m.full_name }}</div>
                                        <div class="text-xs text-muted">{{ m.email ?? 'ไม่มีอีเมล (ลงเวลาที่ kiosk อย่างเดียว)' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-2.5">{{ m.employee_code ?? '—' }}</td>
                            <td class="px-2 py-2.5">{{ m.department ?? '—' }}</td>
                            <td class="px-2 py-2.5 text-muted">
                                {{ m.employment_type ?? '—' }}
                                <div v-if="m.end_date" class="text-xs">ถึง {{ thaiDate(m.end_date, { short: true }) }}</div>
                            </td>
                            <td class="px-2 py-2.5"><span class="pill" :class="roleClass[m.role]">{{ m.role }}</span></td>
                            <td class="px-2 py-2.5 text-center font-bold" :class="m.ot_eligible ? 'text-green-800' : 'text-navy-300'">{{ m.ot_eligible ? '✓' : '—' }}</td>
                            <td class="px-2 py-2.5 text-center font-bold" :class="m.mobile_checkin ? 'text-green-800' : 'text-navy-300'">{{ m.mobile_checkin ? '✓' : '—' }}</td>
                            <td class="px-2 py-2.5"><span class="pill" :class="status(m)[1]">{{ status(m)[0] }}</span></td>
                            <td class="px-2 py-2.5">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="btn-ghost h-9 px-2" title="แก้ไข" :aria-label="`แก้ไข ${m.full_name}`" @click="editing = m"><Icon name="pencil" :size="17" /></button>
                                    <button type="button" class="btn-ghost h-9 px-2" title="ย้ายกลุ่มงาน" :aria-label="`ย้ายกลุ่มงาน ${m.full_name}`" @click="moving = [m]"><Icon name="arrow-right-left" :size="17" /></button>
                                    <button type="button" class="btn-ghost h-9 px-2" title="ประวัติกลุ่มงาน" :aria-label="`ประวัติกลุ่มงาน ${m.full_name}`" @click="openHistory(m)"><Icon name="history" :size="17" /></button>
                                    <button type="button" class="btn-ghost h-9 px-2" :title="m.is_active ? 'ระงับ' : 'เปิดใช้งาน'" :aria-label="`${m.is_active ? 'ระงับ' : 'เปิดใช้งาน'} ${m.full_name}`" @click="toggle(m)">
                                        <Icon :name="m.is_active ? 'user-minus' : 'user-check'" :size="17" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="members.last_page > 1" class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-sm">
                <span class="text-muted">แสดง {{ members.from }}–{{ members.to }} จาก {{ members.total }}</span>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in members.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="min-w-9 rounded-lg px-2.5 py-1.5 text-center"
                            :class="link.active ? 'bg-navy-700 font-bold text-white' : 'hover:bg-soft'"
                        ><span v-html="link.label.replace('pagination.previous', '‹').replace('pagination.next', '›')" /></Link>
                    </template>
                </div>
            </div>
        </section>
        <p class="text-[13px] text-muted">"รอ login ครั้งแรก" = เพิ่มอีเมลแล้วแต่ยังไม่เคยเข้าระบบ · ระงับแล้วข้อมูลเดิมยังอยู่ครบ · กลุ่มงานเปลี่ยนผ่านปุ่ม "ย้ายกลุ่มงาน" เพื่อเก็บวันที่มีผล</p>
    </div>

    <MemberForm
        v-if="editing"
        :member="editing.id ? editing : null"
        :departments="departments"
        :employment-types="employmentTypes"
        :can-assign-roles="canAssignRoles"
        @close="editing = null"
    />
    <MoveDialog v-if="moving" :members="moving" :departments="departments" @close="moving = null; selected = []" />
    <Modal v-if="historyOf" :title="`ประวัติกลุ่มงาน · ${historyOf.full_name}`" width="max-w-lg" @close="historyOf = null">
        <p v-if="!history.length" class="text-muted">กำลังโหลด…</p>
        <ol class="space-y-2">
            <li v-for="h in history" :key="h.id" class="rounded-xl bg-page px-3.5 py-2.5 text-sm">
                <div class="font-semibold">{{ h.department }}</div>
                <div class="text-muted">มีผล {{ thaiDate(h.effective_from) }}<span v-if="h.by"> · โดย {{ h.by }}</span><span v-if="h.note"> · {{ h.note }}</span></div>
            </li>
        </ol>
    </Modal>
</template>
