<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // รูปแบบเวลา: ใช้ทั้งเวลาปกติและ OT ตั้งค่าได้จากหลังบ้าน
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10)->comment('normal | ot');
            $table->string('applies_to', 10)->comment('weekday | holiday | special (กำหนดรายวันในปฏิทิน)');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(0)->comment('มาไม่เกินนี้ไม่นับสาย และนับเหมือนมาตรงเวลา');
            $table->time('count_from')->comment('เริ่มนับชั่วโมงจากเวลานี้ (มาก่อนไม่นับ)');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->time('special_from')->nullable()->comment('หลังเวลานี้นับเป็น OT ช่วงพิเศษ');
            $table->string('rounding', 15)->default('minute')->comment('minute | half_hour | hour (ปัดลง)');
            $table->unsignedSmallInteger('daily_cap_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // วันที่ใช้รูปแบบเวลาพิเศษแทนค่าปกติ เช่น ล่วงเวลาพิเศษถึง 22:00
        Schema::create('special_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->foreignId('schedule_id')->constrained();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('setup_code', 12)->nullable()->unique()->comment('รหัสใช้ครั้งเดียวตอนลงทะเบียนเครื่อง');
            $table->timestamp('setup_expires_at')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type', 10)->comment('normal | ot');
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete()->comment('กลุ่มงาน ณ วันนั้น');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('check_in_photo')->nullable();
            $table->string('check_out_photo')->nullable();
            $table->string('check_in_source', 10)->nullable()->comment('kiosk | mobile | manual');
            $table->string('check_out_source', 10)->nullable();
            $table->boolean('recorded_offline')->default(false);
            $table->unsignedSmallInteger('worked_minutes')->default(0)->comment('ชั่วโมงปกติ หรือ OT ช่วงปกติ');
            $table->unsignedSmallInteger('special_minutes')->default(0)->comment('OT ช่วงพิเศษ');
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'work_date', 'type']);
            $table->index(['work_date', 'type']);
        });

        // การกดลงเวลาทุกครั้ง (หลักฐานดิบ) · client_uuid กันข้อมูลซ้ำตอนส่งคิวออฟไลน์
        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 20)->nullable();
            $table->foreignId('kiosk_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 10)->comment('in | out | ot_in | ot_out');
            $table->dateTime('punched_at');
            $table->dateTime('received_at');
            $table->boolean('offline')->default(false);
            $table->string('photo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('result', 20)->comment('ok | rejected');
            $table->string('message')->nullable();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20)->comment('create | update | delete');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('punches');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('kiosk_devices');
        Schema::dropIfExists('special_days');
        Schema::dropIfExists('schedules');
    }
};
