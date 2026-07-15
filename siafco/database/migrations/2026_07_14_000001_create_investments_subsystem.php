<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('ci')->unique();
            $table->string('ci_complement')->nullable();
            $table->string('issued_in')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::table('affiliates', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliates', 'person_id')) {
                $table->foreignId('person_id')->nullable()->after('id')->constrained('people')->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'person_id')) {
                $table->foreignId('person_id')->nullable()->after('id')->constrained('people')->nullOnDelete();
            }
        });

        DB::transaction(function () {
            DB::table('affiliates')->orderBy('id')->get()->each(function ($affiliate) {
                $person = DB::table('people')->where('ci', $affiliate->ci)->first();

                if (! $person) {
                    $personId = DB::table('people')->insertGetId([
                        'full_name' => $affiliate->full_name,
                        'ci' => $affiliate->ci,
                        'phone' => $affiliate->phone,
                        'email' => $affiliate->email,
                        'address' => $affiliate->address,
                        'birth_date' => $affiliate->birth_date,
                        'marital_status' => $affiliate->marital_status,
                        'photo' => $affiliate->photo_path,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $personId = $person->id;
                }

                DB::table('affiliates')->where('id', $affiliate->id)->update(['person_id' => $personId]);

                if ($affiliate->user_id) {
                    DB::table('users')->where('id', $affiliate->user_id)->update(['person_id' => $personId]);
                }
            });
        });

        Schema::create('investor_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('shares_quantity')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('investment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_legal_name')->nullable();
            $table->string('nit')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('receipt_logo')->nullable();
            $table->string('currency', 10)->default('BOB');
            $table->decimal('share_unit_price', 12, 2)->default(14000);
            $table->unsignedInteger('minimum_shares')->default(1);
            $table->unsignedInteger('maximum_shares')->default(10);
            $table->decimal('monthly_return_percentage', 5, 2)->default(5);
            $table->unsignedInteger('waiting_months')->default(4);
            $table->unsignedInteger('contract_years')->default(3);
            $table->unsignedInteger('reservation_days')->default(30);
            $table->boolean('maximum_shares_per_person')->default(true);
            $table->boolean('renewal_enabled')->default(true);
            $table->boolean('production_bonus_enabled')->default(true);
            $table->boolean('extra_amount_enabled')->default(true);
            $table->string('receipt_prefix')->default('REC-INV');
            $table->unsignedBigInteger('next_receipt_number')->default(1);
            $table->text('receipt_legal_text')->nullable();
            $table->unsignedInteger('alert_days_before_maturity')->default(15);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('investor_number')->unique();
            $table->foreignId('investor_type_id')->nullable()->constrained('investor_types')->nullOnDelete();
            $table->string('status')->default('prospect')->index();
            $table->date('start_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('person_id');
        });

        Schema::create('share_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->unsignedInteger('shares_quantity');
            $table->decimal('share_unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->date('reservation_date');
            $table->date('expiration_date');
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->string('closure_reason')->nullable();
            $table->string('support_document')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['expiration_date', 'status']);
        });

        Schema::create('investment_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('share_reservations')->nullOnDelete();
            $table->string('purchase_number')->unique();
            $table->date('purchase_date');
            $table->unsignedInteger('shares_quantity');
            $table->decimal('share_unit_price', 12, 2);
            $table->decimal('invested_capital', 12, 2);
            $table->decimal('return_percentage', 5, 2);
            $table->unsignedInteger('waiting_months');
            $table->unsignedInteger('contract_years');
            $table->date('maturity_date');
            $table->date('contract_end_date');
            $table->string('renewal_status')->default('pending_decision')->index();
            $table->string('status')->default('pending_approval')->index();
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('payment_receipt')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['maturity_date', 'status']);
            $table->index(['contract_end_date', 'renewal_status']);
        });

        Schema::create('investment_return_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_lot_id')->constrained('investment_lots')->cascadeOnDelete();
            $table->unsignedInteger('period_number');
            $table->unsignedInteger('period_year');
            $table->unsignedInteger('period_month');
            $table->date('due_date');
            $table->decimal('invested_capital_snapshot', 12, 2);
            $table->decimal('return_percentage_snapshot', 5, 2);
            $table->decimal('base_return_amount', 12, 2);
            $table->decimal('production_bonus_amount', 12, 2)->default(0);
            $table->string('extra_concept')->nullable();
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('deductions_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('upcoming')->index();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['investment_lot_id', 'period_year', 'period_month']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('investment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->foreignId('investment_lot_id')->constrained('investment_lots')->cascadeOnDelete();
            $table->foreignId('return_period_id')->unique()->constrained('investment_return_periods')->cascadeOnDelete();
            $table->date('issue_date');
            $table->string('company_name_snapshot');
            $table->string('company_nit_snapshot')->nullable();
            $table->string('company_address_snapshot')->nullable();
            $table->string('company_phone_snapshot')->nullable();
            $table->string('company_email_snapshot')->nullable();
            $table->string('logo_path_snapshot')->nullable();
            $table->string('investor_name_snapshot');
            $table->string('investor_ci_snapshot');
            $table->string('investor_number_snapshot');
            $table->string('purchase_number_snapshot');
            $table->unsignedInteger('shares_quantity_snapshot');
            $table->decimal('share_unit_price_snapshot', 12, 2);
            $table->decimal('invested_capital_snapshot', 12, 2);
            $table->decimal('return_percentage_snapshot', 5, 2);
            $table->decimal('base_return_amount', 12, 2);
            $table->decimal('production_bonus_amount', 12, 2);
            $table->string('extra_concept')->nullable();
            $table->decimal('extra_amount', 12, 2);
            $table->decimal('deductions_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_token')->unique();
            $table->string('status')->default('issued')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_receipts');
        Schema::dropIfExists('investment_return_periods');
        Schema::dropIfExists('investment_lots');
        Schema::dropIfExists('share_reservations');
        Schema::dropIfExists('investors');
        Schema::dropIfExists('investment_settings');
        Schema::dropIfExists('investor_types');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'person_id')) {
                $table->dropConstrainedForeignId('person_id');
            }
        });

        Schema::table('affiliates', function (Blueprint $table) {
            if (Schema::hasColumn('affiliates', 'person_id')) {
                $table->dropConstrainedForeignId('person_id');
            }
        });

        Schema::dropIfExists('people');
    }
};
