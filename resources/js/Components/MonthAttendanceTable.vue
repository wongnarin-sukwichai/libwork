<script setup>
import StatusPill from '@/Components/StatusPill.vue';
import { thaiDate } from '@/utils/thaiDate';

// ตารางเวลาปฏิบัติงานรายวันของเดือน (เวลาปกติและ OT ในแถวเดียว)
defineProps({ rows: { type: Array, required: true } });
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="bg-soft text-left">
                    <th class="px-2 py-2.5 font-semibold">วันที่</th>
                    <th class="px-2 py-2.5 font-semibold">เข้า</th>
                    <th class="px-2 py-2.5 font-semibold">ออก</th>
                    <th class="px-2 py-2.5 font-semibold">ชม. ปกติ</th>
                    <th class="px-2 py-2.5 font-semibold">OT ปกติ</th>
                    <th class="px-2 py-2.5 font-semibold">OT พิเศษ</th>
                    <th class="px-2 py-2.5 font-semibold">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!rows.length"><td colspan="7" class="px-3 py-8 text-center text-muted">ยังไม่มีข้อมูลในเดือนนี้</td></tr>
                <tr v-for="r in rows" :key="r.date" class="border-b border-line">
                    <td class="whitespace-nowrap px-2 py-2.5">{{ thaiDate(r.date, { short: true, weekday: true }).replace(/ \d{4}$/, '') }}</td>
                    <td class="px-2 py-2.5">{{ r.check_in ?? '—' }}</td>
                    <td class="px-2 py-2.5">{{ r.check_out ?? '—' }}</td>
                    <td class="px-2 py-2.5">{{ r.hours ?? '—' }}</td>
                    <td class="px-2 py-2.5">{{ r.ot ?? '—' }}</td>
                    <td class="px-2 py-2.5 font-semibold text-violet-700">{{ r.ot_special ?? '' }}</td>
                    <td class="px-2 py-2.5"><StatusPill :tone="r.tone" :text="r.status" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
