<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { url } from '@/utils/url';

// เข้าสู่ระบบแบบ modal (เปิดจากปุ่มบนแถบหัว หรือเข้า /login ตรง ๆ)
// ข้อผิดพลาดจาก Google มาทาง flash → FlashToast แสดงให้ ส่วนรหัสผ่านแสดงใต้ช่องกรอก
const emit = defineEmits(['close']);

const page = usePage();
const options = computed(() => page.props.login ?? { google: false, password: false });
const form = useForm({ email: '', password: '' });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });

const emailInput = ref(null);
onMounted(() => nextTick(() => emailInput.value?.focus()));
</script>

<template>
    <Modal title="เข้าสู่ระบบ" width="max-w-md" @close="emit('close')">
        <p class="mb-5 text-sm text-muted">ใช้อีเมลที่ผู้ดูแลระบบลงทะเบียนไว้ให้</p>

        <a
            v-if="options.google"
            :href="url('/auth/google/redirect')"
            class="btn-outline h-12 w-full text-base"
        >
            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.5 12.3c0-.8-.1-1.5-.2-2.2H12v4.2h5.9a5 5 0 0 1-2.2 3.3v2.7h3.5c2.1-1.9 3.3-4.7 3.3-8z" /><path fill="#34A853" d="M12 23c3 0 5.5-1 7.3-2.7l-3.5-2.7c-1 .7-2.3 1.1-3.8 1.1-2.9 0-5.4-2-6.3-4.6H2.1v2.8A11 11 0 0 0 12 23z" /><path fill="#FBBC05" d="M5.7 14.1a6.6 6.6 0 0 1 0-4.2V7.1H2.1a11 11 0 0 0 0 9.8l3.6-2.8z" /><path fill="#EA4335" d="M12 5.4c1.6 0 3 .6 4.2 1.6l3.1-3.1A11 11 0 0 0 2.1 7.1l3.6 2.8C6.6 7.3 9.1 5.4 12 5.4z" /></svg>
            เข้าสู่ระบบด้วย Google
        </a>
        <p v-else class="rounded-xl bg-soft px-4 py-3 text-sm text-muted">ยังไม่ได้ตั้งค่าการเข้าสู่ระบบด้วย Google</p>

        <template v-if="options.password">
            <div class="my-5 flex items-center gap-3 text-xs text-muted"><span class="h-px flex-1 bg-line" />หรือใช้รหัสผ่านสำรอง<span class="h-px flex-1 bg-line" /></div>
            <form class="space-y-3.5" @submit.prevent="submit">
                <div>
                    <label for="login-email" class="label">อีเมล</label>
                    <input id="login-email" ref="emailInput" v-model="form.email" type="email" class="input" autocomplete="username" required>
                    <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label for="login-password" class="label">รหัสผ่าน</label>
                    <input id="login-password" v-model="form.password" type="password" class="input" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-navy h-12 w-full" :disabled="form.processing">เข้าสู่ระบบ</button>
            </form>
        </template>
    </Modal>
</template>
