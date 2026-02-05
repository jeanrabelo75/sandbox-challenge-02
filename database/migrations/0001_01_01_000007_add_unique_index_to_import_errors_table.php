<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_errors', function (Blueprint $table) {
            $table->unique(['import_file_id', 'line_number', 'message'], 'import_errors_unique_line_message');
        });
    }

    public function down(): void
    {
        Schema::table('import_errors', function (Blueprint $table) {
            $table->dropUnique('import_errors_unique_line_message');
        });
    }
};
