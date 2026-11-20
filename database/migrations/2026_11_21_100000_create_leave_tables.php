<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category', 10)->default('leave')->comment('leave = ลา, duty = ไปราชการ (หน้าแรกสาธารณะแสดงเท่านี้)');
            $table->boolean('counts_quota')->default(true)->comment('หักจากโควต้า');
            $table->boolean('carry_over')->default(false)->comment('ยกยอดคงเหลือไปปีงบถัดไป (ลาพักผ่อน)');
            $table->boolean('allow_half_day')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // สิทธิ์ต่อปีตามประเภทบุคลากร
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('days_per_year', 5, 1)->default(0);
            $table->unsignedSmallInteger('min_service_months')->default(0)->comment('ต้องทำงานครบกี่เดือนก่อนมีสิทธิ์');
            $table->decimal('max_total_days', 5, 1)->nullable()->comment('สะสม+ปีนี้ รวมไม่เกิน (อายุงาน < 10 ปี)');
            $table->decimal('max_total_days_10y', 5, 1)->nullable()->comment('สะสม+ปีนี้ รวมไม่เกิน (อายุงาน ≥ 10 ปี)');
            $table->timestamps();
            $table->unique(['employment_type_id', 'leave_type_id']);
        });

        // HR บันทึกโดยตรง ไม่มีขั้นตอนอนุมัติ
        Schema::create('leave_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('half_day', 2)->nullable()->comment('am | pm (วันเดียวเท่านั้น)');
            $table->decimal('days', 5, 1);
            $table->unsignedSmallInteger('fiscal_year')->comment('ปีงบประมาณ พ.ศ.');
            $table->string('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'fiscal_year']);
            $table->index(['start_date', 'end_date']);
        });

        // ยอดตั้งต้นของปีงบ: สิทธิ์ + ยกมา · status draft = HR ยังตรวจไม่เสร็จ (member ยังไม่เห็นยอดใหม่)
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('entitled', 5, 1)->default(0);
            $table->decimal('carried_over', 5, 1)->default(0);
            $table->decimal('system_carried_over', 5, 1)->default(0)->comment('ค่าที่ระบบคำนวณ (เทียบกับที่ HR แก้)');
            $table->decimal('cap', 5, 1)->nullable();
            $table->boolean('reviewed')->default(false);
            $table->string('status', 10)->default('draft')->comment('draft | confirmed');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'leave_type_id', 'fiscal_year']);
        });

        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 5, 1);
            $table->string('field', 15)->default('carried_over');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->text('message');
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_inquiries');
        Schema::dropIfExists('leave_adjustments');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_records');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('holidays');
    }
};
