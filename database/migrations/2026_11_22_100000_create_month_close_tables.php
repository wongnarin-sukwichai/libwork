<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // งวดที่ปิดแล้ว: ข้อมูลเวลาและวันลาของเดือนนั้นถูกล็อก
        Schema::create('month_closures', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7)->unique()->comment('YYYY-MM');
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // HR รับทราบรายการผิดปกติพร้อมหมายเหตุ (กรณีไม่ต้องแก้ข้อมูล)
        Schema::create('anomaly_acks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 20);
            $table->string('note');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date', 'kind']);
        });

        Schema::create('month_closure_logs', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7);
            $table->string('action', 10)->comment('close | unlock');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('month_closure_logs');
        Schema::dropIfExists('anomaly_acks');
        Schema::dropIfExists('month_closures');
    }
};
