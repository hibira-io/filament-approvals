<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per paused agent turn.
        Schema::create('hibira_approval_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invocation_id')->unique();
            $table->string('agent');
            $table->string('conversation_id')->nullable()->index();
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
        });

        // One row per tool call in that turn that waits on a decision.
        Schema::create('hibira_approval_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('request_id')->constrained('hibira_approval_requests')->cascadeOnDelete();
            $table->string('tool_call_id');
            $table->string('tool');
            $table->json('arguments');
            $table->text('reason')->nullable();
            $table->string('decision', 8)->nullable();
            $table->json('edited_arguments')->nullable();
            $table->text('rejection')->nullable();
            $table->string('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'tool_call_id']);
            $table->index('tool_call_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hibira_approval_tool_calls');
        Schema::dropIfExists('hibira_approval_requests');
    }
};
