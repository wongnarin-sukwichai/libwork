<script setup>
// ไอคอนที่ใช้ในระบบ (lucide) ระบุด้วยชื่อ เช่น <Icon name="house" />
import {
    ArrowDown, ArrowUp, Building2, Calculator, CalendarCheck, CalendarDays, Check, ChevronLeft, ChevronRight,
    Clock, FilePen, History, House, ListChecks, LogIn, LogOut, Menu, Pencil, Plus, Search, Settings,
    ShieldCheck, Upload, UserCheck, UserMinus, UserSearch, Users, X, ArrowRightLeft, IdCard,
} from 'lucide-vue-next';

const icons = {
    'arrow-down': ArrowDown,
    'arrow-up': ArrowUp,
    'arrow-right-left': ArrowRightLeft,
    building: Building2,
    calculator: Calculator,
    'calendar-check': CalendarCheck,
    'calendar-days': CalendarDays,
    check: Check,
    'chevron-left': ChevronLeft,
    'chevron-right': ChevronRight,
    clock: Clock,
    'file-pen': FilePen,
    history: History,
    house: House,
    'id-card': IdCard,
    'list-checks': ListChecks,
    'log-in': LogIn,
    'log-out': LogOut,
    menu: Menu,
    pencil: Pencil,
    plus: Plus,
    search: Search,
    settings: Settings,
    'shield-check': ShieldCheck,
    upload: Upload,
    'user-check': UserCheck,
    'user-minus': UserMinus,
    'user-search': UserSearch,
    users: Users,
    x: X,
};

defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
    strokeWidth: { type: [Number, String], default: 2 },
});
</script>

<template>
    <component :is="icons[name]" :size="size" :stroke-width="strokeWidth" aria-hidden="true" />
</template>
