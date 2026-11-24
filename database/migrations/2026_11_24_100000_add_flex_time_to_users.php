<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** เวลายืดหยุ่นรายคน (admin ติ๊กในหน้าสมาชิก) · กติกาอยู่ใน settings flex.* */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('flex_time')->default(false)->after('mobile_checkin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('flex_time');
        });
    }
};
