<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_prospects', function (Blueprint $table) {
            $table->timestamp('last_interaction_at')->nullable()->index('inv_prospects_last_interaction_idx');
            $table->timestamp('next_follow_up_at')->nullable()->index('inv_prospects_next_follow_up_idx');
        });

        Schema::create('investment_prospect_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_prospect_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index('inv_prospect_history_to_status_idx');
            $table->foreignId('changed_by_user_id')->nullable();
            $table->timestamp('changed_at')->index('inv_prospect_history_changed_idx');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index('investment_prospect_id', 'inv_prospect_history_prospect_idx');
            $table->foreign('investment_prospect_id', 'fk_inv_prospect_history_prospect')->references('id')->on('investment_prospects')->cascadeOnDelete();
            $table->foreign('changed_by_user_id', 'fk_inv_prospect_history_user')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('investment_prospect_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_prospect_id');
            $table->foreignId('advisor_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('type', 30);
            $table->string('channel', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->index('inv_prospect_interactions_occurred_idx');
            $table->timestamp('next_follow_up_at')->nullable()->index('inv_prospect_interactions_follow_up_idx');
            $table->timestamps();
            $table->index('investment_prospect_id', 'inv_prospect_interactions_prospect_idx');
            $table->index('advisor_id', 'inv_prospect_interactions_advisor_idx');
            $table->foreign('investment_prospect_id', 'fk_inv_interaction_prospect')->references('id')->on('investment_prospects')->cascadeOnDelete();
            $table->foreign('advisor_id', 'fk_inv_interaction_advisor')->references('id')->on('investment_advisors')->nullOnDelete();
            $table->foreign('user_id', 'fk_inv_interaction_user')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('investment_prospect_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_prospect_id');
            $table->foreignId('from_advisor_id')->nullable();
            $table->foreignId('to_advisor_id');
            $table->foreignId('assigned_by_user_id')->nullable();
            $table->timestamp('assigned_at')->index('inv_prospect_assignments_assigned_idx');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index('investment_prospect_id', 'inv_prospect_assignments_prospect_idx');
            $table->foreign('investment_prospect_id', 'fk_inv_assignment_prospect')->references('id')->on('investment_prospects')->cascadeOnDelete();
            $table->foreign('from_advisor_id', 'fk_inv_assignment_from_advisor')->references('id')->on('investment_advisors')->nullOnDelete();
            $table->foreign('to_advisor_id', 'fk_inv_assignment_to_advisor')->references('id')->on('investment_advisors')->restrictOnDelete();
            $table->foreign('assigned_by_user_id', 'fk_inv_assignment_user')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_prospect_assignments');
        Schema::dropIfExists('investment_prospect_interactions');
        Schema::dropIfExists('investment_prospect_status_history');
        Schema::table('investment_prospects', function (Blueprint $table) {
            $table->dropIndex('inv_prospects_last_interaction_idx');
            $table->dropIndex('inv_prospects_next_follow_up_idx');
            $table->dropColumn(['last_interaction_at', 'next_follow_up_at']);
        });
    }
};
