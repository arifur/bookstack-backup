<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('file_path');
            $table->string('sha_hash');
            $table->string('created_by');
            $table->text('downloaded_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->string('status')->default('pending');
            $table->nullableTimestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('backups');
    }
};
