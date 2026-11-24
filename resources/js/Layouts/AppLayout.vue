<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppMark from '@/Components/AppMark.vue';
import FlashToast from '@/Components/FlashToast.vue';
import Icon from '@/Components/Icon.vue';
import { stripBase } from '@/utils/url';

// layout หลักหลัง login: sidebar สีขาว เมนูที่เลือกอยู่สีน้ำเงิน (ธีม A3)
const page = usePage();
const user = computed(() => page.props.auth.user);
const menu = computed(() => page.props.auth.menu);
const drawer = ref(false);

const currentPath = computed(() => stripBase(page.url.split('?')[0]));
const isActive = (href) => {
    if (href === '/staff' || href === '/me') return currentPath.value === href;
    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
};

watch(() => page.url, () => (drawer.value = false));

const initials = computed(() => (user.value?.first_name ?? '?').slice(0, 2));
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen lg:flex">
        <!-- แถบบนสำหรับจอเล็ก -->
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-line bg-white px-4 py-3 lg:hidden">
            <div class="flex items-center gap-3">
                <AppMark :size="36" />
                <div class="font-bold">ระบบลงเวลาปฏิบัติงาน</div>
            </div>
            <button type="button" class="btn-ghost h-11 px-3" aria-label="เปิดเมนู" @click="drawer = true">
                <Icon name="menu" />
            </button>
        </header>

        <div v-if="drawer" class="fixed inset-0 z-40 bg-navy-900/40 lg:hidden" @click="drawer = false" />

        <nav
            aria-label="เมนูหลัก"
            class="fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col gap-1 overflow-y-auto border-r border-line bg-white px-3.5 py-5 transition-transform lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0"
            :class="drawer ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="mb-3 flex items-center gap-2.5 border-b border-line px-2 pb-4">
                <AppMark />
                <div class="min-w-0">
                    <div class="text-base font-bold leading-tight">ระบบลงเวลาปฏิบัติงาน</div>
                    <div class="truncate text-[13px] text-muted">{{ page.props.app.org }}</div>
                </div>
            </div>

            <!-- โมดูล (หัวข้อ) และเมนูในโมดูล จาก config/menu.php -->
            <div v-for="(m, mi) in menu" :key="m.module ?? mi" class="flex flex-col gap-1" :class="{ 'mt-4': mi > 0 }">
                <div v-if="m.module" class="px-3 pb-1 text-xs font-semibold tracking-wide text-muted">{{ m.module }}</div>
                <Link
                    v-for="item in m.items"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-[10px] px-3 py-[11px] text-[15px] transition"
                    :class="isActive(item.href) ? 'bg-navy-700 font-bold text-white shadow-nav' : 'text-ink hover:bg-soft'"
                >
                    <Icon :name="item.icon" :size="19" />
                    {{ item.label }}
                </Link>
            </div>

            <div class="mt-auto pt-6">
                <div class="flex items-center gap-2.5 rounded-xl bg-page p-3">
                    <img v-if="user.avatar" :src="user.avatar" alt="" class="h-9 w-9 rounded-full object-cover" referrerpolicy="no-referrer">
                    <div v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-navy-700 text-sm font-bold text-white">{{ initials }}</div>
                    <div class="min-w-0 flex-1 text-[13px] leading-snug">
                        <div class="truncate font-semibold">{{ user.name }}</div>
                        <div class="text-muted">{{ user.role_label }}</div>
                    </div>
                    <button type="button" class="btn-ghost h-9 px-2" aria-label="ออกจากระบบ" title="ออกจากระบบ" @click="logout">
                        <Icon name="log-out" :size="18" />
                    </button>
                </div>
            </div>
        </nav>

        <main data-page-content class="min-w-0 flex-1 px-4 py-6 sm:px-7 lg:px-8">
            <slot />
        </main>

        <FlashToast />
    </div>
</template>
