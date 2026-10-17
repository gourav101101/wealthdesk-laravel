<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client');
            $table->timestamp('password_changed_at')->nullable();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 6)->unique();
            $table->string('phone', 25);
            $table->string('pan', 10)->nullable();
            $table->string('risk');
            $table->bigInteger('investment')->default(0);
            $table->json('details');
            $table->boolean('archived')->default(false)->index();
            $table->boolean('sample')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('category');
            $table->string('section');
            $table->string('stock', 100);
            $table->timestamp('date')->index();
            $table->string('side');
            $table->unsignedInteger('quantity');
            $table->bigInteger('entry_price');
            $table->bigInteger('ltp');
            $table->bigInteger('exit_price')->nullable();
            $table->bigInteger('pnl');
            $table->string('state');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->timestamp('date')->index();
            $table->bigInteger('amount');
            $table->string('processed_by', 100);
            $table->string('reference', 100)->unique();
            $table->string('status');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('status')->default('Pending');
            $table->string('note', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
        Schema::create('workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('WealthDesk Capital');
            $table->string('payment_name')->nullable();
            $table->string('upi_id')->nullable();
            $table->text('payment_note')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('description', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'workspace_settings', 'documents', 'withdrawals', 'deposits', 'positions', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'password_changed_at']));
    }
};
