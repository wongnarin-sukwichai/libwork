<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // กลุ่มงาน: เปลี่ยนชื่อได้ (รายงานเก่าแสดงชื่อใหม่), ปิดใช้งานแทนการลบ
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0)->comment('ลำดับในรายงาน');
            $table->string('head_name')->nullable()->comment('ชื่อหัวหน้ากลุ่มงาน สำหรับช่องลงนาม');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->comment('deps.id จากระบบเดิม');
            $table->timestamps();
        });

        // ประเภทบุคลากร: ใช้ผูกนโยบายการลาในเฟสถัดไป
        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('has_leave_quota')->default(true)->comment('false = ไม่มีโควต้าวันลา เช่น นิสิตฝึกงาน');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 20)->nullable()->unique()->comment('รหัสลงเวลาที่จอ kiosk');
            $table->string('prefix', 30)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique()->comment('อีเมลที่ใช้ login ด้วย Google');
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable()->comment('ใช้เมื่อเปิด login ด้วยรหัสผ่านสำรอง');
            $table->string('role', 10)->default('member')->comment('admin | staff | member');
            $table->foreignId('employment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete()->comment('กลุ่มงานปัจจุบัน');
            $table->string('position')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->comment('วันสิ้นสุด เช่น นิสิตฝึกงาน');
            $table->boolean('ot_eligible')->default(false);
            $table->boolean('mobile_checkin')->default(false)->comment('อนุญาตลงเวลาผ่านมือถือ');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->comment('members.id จากระบบเดิม');
            $table->rememberToken();
            $table->timestamps();
        });

        // ประวัติการย้ายกลุ่มงาน: รายงานย้อนหลังจัดกลุ่มตามกลุ่มงาน ณ วันนั้น
        Schema::create('department_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained();
            $table->date('effective_from');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'effective_from']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('department_assignments');
        Schema::dropIfExists('users');
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('departments');
    }
};
