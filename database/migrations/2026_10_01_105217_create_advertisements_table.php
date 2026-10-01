<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The banner is stored in the row itself (bytea on PostgreSQL): production images are immutable
     * and keep no file volume, and the database backups and restore drill then cover it too.
     */
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title', 120);
            $table->string('link_url', 2048)->nullable();
            $table->string('placement', 16);
            $table->string('locale', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->binary('image_data');
            $table->string('image_mime_type', 32);
            $table->char('image_checksum', 64);
            $table->timestamps();

            $table->index(['placement', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
