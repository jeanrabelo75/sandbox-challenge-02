<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('import_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_path')->unique();
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('file_mtime');
            $table->string('status')->index(); // pending|processing|processed|error
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('import_files');
    }
};
