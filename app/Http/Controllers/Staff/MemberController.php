<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\User;
use App\Support\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * จัดการสมาชิก (โมดูล "ระบบ"): route เปิดเฉพาะ admin · เงื่อนไข staff ด้านล่างเก็บไว้กันพลาดถ้าเปิดให้ staff อีก
 */
class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only('q', 'role', 'department', 'status');

        $query = User::query()
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('employee_code', 'like', "%{$term}%"));
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'suspended', fn ($q) => $q->where('is_active', false));

        // จำนวนคนในแต่ละกลุ่มงานตามตัวกรอง (ทุกหน้า) ใช้แสดงที่หัวกลุ่ม · key 0 = ไม่มีกลุ่มงาน
        $groupTotals = (clone $query)->selectRaw('coalesce(department_id, 0) as dept, count(*) as total')
            ->groupBy('dept')->pluck('total', 'dept');

        $members = $query
            ->with(['department:id,name', 'employmentType:id,name'])
            // เรียงตามกลุ่มงานเหมือนหน้ารายวัน · ไม่มีกลุ่มงานอยู่ท้าย
            ->orderByRaw('users.department_id is null')
            ->orderBy(Department::select('sort_order')->whereColumn('departments.id', 'users.department_id'))
            ->orderBy('users.department_id')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'employee_code' => $u->employee_code,
                'prefix' => $u->prefix,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'full_name' => $u->full_name,
                'email' => $u->email,
                'avatar' => $u->avatar,
                'role' => $u->role,
                'department_id' => $u->department_id,
                'department' => $u->department?->name,
                'employment_type_id' => $u->employment_type_id,
                'employment_type' => $u->employmentType?->name,
                'position' => $u->position,
                'start_date' => $u->start_date?->toDateString(),
                'end_date' => $u->end_date?->toDateString(),
                'ot_eligible' => $u->ot_eligible,
                'mobile_checkin' => $u->mobile_checkin,
                'flex_time' => $u->flex_time,
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('Staff/Members/Index', [
            'members' => $members,
            'groupTotals' => $groupTotals,
            'filters' => $filters,
            'counts' => User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'total' => User::query()->count(),
            'departments' => Department::query()->ordered()->get(['id', 'name', 'is_active']),
            'employmentTypes' => EmploymentType::query()->ordered()->where('is_active', true)->get(['id', 'name']),
            'canAssignRoles' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::query()->create($data);

        if ($user->department_id) {
            $user->departmentAssignments()->create([
                'department_id' => $user->department_id,
                'effective_from' => $user->start_date?->toDateString() ?? now()->toDateString(),
                'created_by' => $request->user()->id,
                'note' => 'เพิ่มสมาชิก',
            ]);
        }

        return back()->with('success', "เพิ่ม {$user->full_name} แล้ว");
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $data = $this->validated($request, $member);
        // กลุ่มงานเปลี่ยนผ่าน "ย้ายกลุ่มงาน" เท่านั้น เพื่อเก็บวันที่มีผล
        unset($data['department_id']);
        $flexChanged = array_key_exists('flex_time', $data) && (bool) $data['flex_time'] !== $member->flex_time;
        $member->update($data);
        // เปลี่ยนเวลายืดหยุ่น: คำนวณชั่วโมงของเดือนนี้ใหม่ (งวดที่ปิดแล้วไม่แตะ)
        if ($flexChanged) {
            AttendanceService::recalculateRange($member->id, now()->startOfMonth()->toDateString(), now()->toDateString());
        }

        return back()->with('success', "บันทึกข้อมูล {$member->full_name} แล้ว");
    }

    public function toggle(Request $request, User $member): RedirectResponse
    {
        abort_if($member->is($request->user()), 422, 'ระงับบัญชีตัวเองไม่ได้');
        abort_if($member->isStaff() && ! $request->user()->isAdmin(), 403);

        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('success', ($member->is_active ? 'เปิดใช้งาน ' : 'ระงับ ').$member->full_name.' แล้ว');
    }

    /** ย้ายกลุ่มงานพร้อมวันที่มีผล (รองรับหลายคนพร้อมกัน) */
    public function move(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'effective_from' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $users = User::query()->whereIn('id', $data['user_ids'])->get();
        foreach ($users as $user) {
            $user->moveToDepartment($data['department_id'], $data['effective_from'], $request->user()->id, $data['note'] ?? null);
        }

        $dept = Department::query()->find($data['department_id'])->name;

        return back()->with('success', "ย้าย {$users->count()} คนไป {$dept} แล้ว");
    }

    public function history(User $member): \Illuminate\Http\JsonResponse
    {
        return response()->json($member->departmentAssignments()->with(['department:id,name', 'creator:id,first_name,last_name'])->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'department' => $a->department?->name,
                'effective_from' => $a->effective_from->toDateString(),
                'note' => $a->note,
                'by' => $a->creator?->full_name,
            ]));
    }

    private function validated(Request $request, ?User $member = null): array
    {
        $isAdmin = $request->user()->isAdmin();

        $data = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($member)],
            'prefix' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users')->ignore($member)],
            'role' => ['required', Rule::in(User::ROLES)],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'department_id' => [$member ? 'nullable' : 'required', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'ot_eligible' => ['boolean'],
            'mobile_checkin' => ['boolean'],
            'flex_time' => ['boolean'],
        ]);

        $data['email'] = isset($data['email']) ? strtolower($data['email']) : null;

        // staff กำหนดได้แค่ member และแก้ข้อมูล admin/staff คนอื่นไม่ได้
        if (! $isAdmin) {
            abort_if($member && $member->isStaff() && ! $member->is($request->user()), 403);
            $data['role'] = $member?->role ?? 'member';
        }

        return $data;
    }
}
