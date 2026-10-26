<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 30)->default('other'); // lab, imaging, procedure, nursing, consultation, supply, room, other
            $table->string('unit', 30)->default('service');
            $table->decimal('default_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->string('price_source', 20)->default('catalog');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_services');
    }
};
