<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Las contraseñas de los clientes ya no se guardan de forma recuperable: solo existe el hash en users. */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('account_password');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('account_password')->nullable()->after('phone');
        });
    }
};
