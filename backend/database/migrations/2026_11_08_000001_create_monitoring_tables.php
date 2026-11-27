<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('viewer'));
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('url', 2048);
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });
        Schema::create('checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->boolean('available');
            $t->string('target_url', 2048);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedInteger('response_ms');
            $t->json('dns_addresses');
            $t->timestampTz('tls_expires_at')->nullable();
            $t->string('error', 64)->nullable();
            $t->timestampTz('checked_at');
            $t->index(['service_id', 'id']);
        });
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('open');
            $t->timestampTz('opened_at');
            $t->timestampTz('acknowledged_at')->nullable();
            $t->timestampTz('resolved_at')->nullable();
            $t->foreignId('acknowledged_by')->nullable()->constrained('users');
            $t->foreignId('resolved_by')->nullable()->constrained('users');
        });
        DB::statement('CREATE UNIQUE INDEX one_active_incident ON incidents(service_id) WHERE resolved_at IS NULL');
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('details');
            $t->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'incidents', 'checks', 'services'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
