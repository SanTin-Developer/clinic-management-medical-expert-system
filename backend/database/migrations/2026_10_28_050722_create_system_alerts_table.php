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
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_key')->unique();
            $table->string('category', 50);
            $table->string('severity', 20);
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('module', 50)->nullable();
            $table->string('url')->nullable();
            $table->json('data')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('read_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['category', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
