<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_file_id')->constrained('import_files')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('external_id')->nullable();
            $table->text('message');
            $table->text('raw_line')->nullable();
            $table->timestamps();
            $table->unique(['import_file_id', 'line_number', 'message']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('import_errors');
    }
};
