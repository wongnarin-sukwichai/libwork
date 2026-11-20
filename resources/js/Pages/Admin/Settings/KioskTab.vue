<script setup>
import { router, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { url } from '@/utils/url';

// เครื่องจอกลาง: เพิ่มเครื่อง → ได้รหัสลงทะเบียน → นำไปกรอกที่ /kiosk ของเครื่องนั้น
const props = defineProps({ kiosk: Object });

const settings = useForm({ kiosk_enabled: props.kiosk.enabled, auth_url: props.kiosk.auth_url ?? '' });
const saveSettings = () => settings.transform((d) => ({ ...d, auth_url: d.auth_url || null })).put('/admin/settings/kiosk', { preserveScroll: true });

const add = useForm({ name: '' });
const addDevice = () => add.post('/admin/settings/kiosk-devices', { preserveScroll: true, onSuccess: () => add.reset() });
const reissue = (d) => confirm(`ออกรหัสใหม่ให้ ${d.name}? เครื่องเดิมจะต้องลงทะเบียนใหม่`) && router.post(`/admin/settings/kiosk-devices/${d.id}/reissue`, {}, { preserveScroll: true });
const toggle = (d) => router.post(`/admin/settings/kiosk-devices/${d.id}/toggle`, {}, { preserveScroll: true });
const kioskUrl = `${window.location.origin}${url('/kiosk')}`;
</script>

<template>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="space-y-3">
            <div class="card overflow-hidden">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-navy-700 text-left text-white">
                            <th class="px-3 py-3 font-semibold">เครื่อง</th>
                            <th class="px-3 py-3 font-semibold">สถานะ</th>
                            <th class="px-3 py-3 font-semibold">ใช้งานล่าสุด</th>
                            <th class="px-3 py-3"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!kiosk.devices.length"><td colspan="4" class="px-4 py-6 text-center text-muted">ยังไม่มีเครื่อง</td></tr>
                        <tr v-for="d in kiosk.devices" :key="d.id" class="border-b border-soft align-top">
                            <td class="px-3 py-3 font-semibold">{{ d.name }}</td>
                            <td class="px-3 py-3">
                                <span v-if="!d.is_active" class="pill bg-soft text-muted">ยกเลิกแล้ว</span>
                                <span v-else-if="d.registered" class="pill bg-green-50 text-green-800">ลงทะเบียนแล้ว</span>
                                <div v-else-if="d.setup_code">
                                    <span class="pill bg-sun-100 text-sun-800">รอลงทะเบียน</span>
                                    <div class="mt-1.5 font-mono text-lg font-bold tracking-widest">{{ d.setup_code }}</div>
                                    <div class="text-xs text-muted">ใช้ได้ถึง {{ d.setup_expires_at }}</div>
                                </div>
                                <span v-else class="pill bg-red-50 text-red-800">รหัสหมดอายุ</span>
                            </td>
                            <td class="px-3 py-3 text-muted">{{ d.last_seen_at ?? '—' }}<div v-if="d.last_ip" class="text-xs">IP {{ d.last_ip }}</div></td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="btn-outline h-9 px-3 text-sm" @click="reissue(d)">ออกรหัสใหม่</button>
                                    <button type="button" class="btn-outline h-9 px-3 text-sm" @click="toggle(d)">{{ d.is_active ? 'ยกเลิก' : 'เปิดใช้' }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <form class="card flex flex-wrap items-end gap-3 p-4" @submit.prevent="addDevice">
                <div class="min-w-[220px] flex-1">
                    <label for="k-name" class="label">ชื่อเครื่องใหม่</label>
                    <input id="k-name" v-model="add.name" class="input" placeholder="เช่น จอกลาง ชั้น 1" required>
                </div>
                <button type="submit" class="btn-primary" :disabled="add.processing"><Icon name="plus" :size="18" /> เพิ่มเครื่อง</button>
            </form>
        </section>

        <aside class="space-y-4">
            <form class="card space-y-4 p-5" @submit.prevent="saveSettings">
                <h2 class="text-lg font-bold">การตั้งค่าจอกลาง</h2>
                <label class="flex items-center gap-2.5 text-[15px]"><input v-model="settings.kiosk_enabled" type="checkbox" class="h-4 w-4 accent-navy-700"> เปิดการลงเวลาที่จอกลาง</label>
                <div>
                    <label for="k-auth" class="label">ลิงก์หน้า authen อินเทอร์เน็ตมหาวิทยาลัย</label>
                    <input id="k-auth" v-model="settings.auth_url" type="url" class="input" placeholder="https://...">
                    <p class="mt-1 text-xs text-muted">ใช้กับปุ่มสีเหลืองเมื่อจอตรวจพบว่ายังไม่ได้ authen</p>
                    <p v-if="settings.errors.auth_url" class="field-error">{{ settings.errors.auth_url }}</p>
                </div>
                <div class="flex justify-end"><button type="submit" class="btn-navy" :disabled="settings.processing">บันทึก</button></div>
            </form>
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="text-lg font-bold">ติดตั้งเครื่อง</h2>
                <ol class="list-decimal space-y-1.5 pl-5 text-muted">
                    <li>เพิ่มเครื่องทางซ้าย จะได้รหัสลงทะเบียน (ใช้ได้ 24 ชม.)</li>
                    <li>ที่เครื่องจอกลาง เปิด <span class="break-all font-mono text-ink">{{ kioskUrl }}</span></li>
                    <li>กรอกรหัส แล้วอนุญาตให้ใช้กล้อง</li>
                    <li>ตั้ง Chrome ให้เปิดหน้านี้แบบเต็มจอเมื่อเปิดเครื่อง</li>
                </ol>
            </div>
        </aside>
    </div>
</template>
