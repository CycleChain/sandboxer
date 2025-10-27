<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sandbox_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_token')->unique();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->timestamp('expires_at')->index();
            $table->json('initial_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sandbox_storage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sandbox_id')->index();
            $table->string('table_name')->index();
            $table->string('record_id');
            $table->enum('operation', ['INSERT', 'UPDATE', 'DELETE', 'SNAPSHOT', 'AUTH']);
            $table->json('data');
            $table->json('changed_fields')->nullable();
            $table->integer('sequence')->default(0);
            $table->timestamps();
            
            $table->index(['sandbox_id', 'table_name', 'record_id']);
            $table->index(['sandbox_id', 'created_at']);
            $table->index(['sandbox_id', 'operation']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sandbox_storage');
        Schema::dropIfExists('sandbox_sessions');
    }
};
