<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('cat_sin_iva', 8, 4)->nullable()->after('cat_amount')->comment('CAT sin IVA como fracción; cat_amount es el CAT con IVA');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('cat_sin_iva');
        });
    }
};
