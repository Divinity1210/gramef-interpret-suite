<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            Schema::create('tenant_settings', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('brand_name');
                $table->string('brand_tagline')->nullable();
                $table->string('logo_text')->default('GRAMEF');
                $table->string('primary_color', 20)->default('#2563EB');
                $table->string('accent_color', 20)->default('#10B981');
                $table->string('currency_symbol', 8)->default('£');
                $table->decimal('default_vat_rate', 5, 2)->default(20.00);
                $table->string('chaser_mode', 20)->default('review'); // 'review' | 'auto'
                $table->unsignedTinyInteger('active_delivery_stage')->default(4); // 1..4
                $table->text('features_json')->nullable();
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('rate_cards')) {
            Schema::create('rate_cards', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('role_type', 20)->default('client'); // 'client' | 'interpreter'
                $table->string('service_type', 80)->default('Face to Face');
                $table->string('qualification_level', 80)->default('All Levels');
                $table->decimal('daytime_rate', 8, 2)->default(45.00);
                $table->decimal('ooh_rate', 8, 2)->default(60.00);
                $table->decimal('minimum_hours', 5, 2)->default(1.00);
                $table->decimal('travel_rate_hourly', 8, 2)->default(18.00);
                $table->decimal('mileage_rate', 6, 2)->default(0.45);
                $table->decimal('cancellation_min_hours', 5, 2)->default(2.00);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('timesheets')) {
            Schema::table('timesheets', function (Blueprint $table) {
                if (!Schema::hasColumn('timesheets', 'magic_sign_token')) {
                    $table->string('magic_sign_token', 80)->nullable()->index();
                }
                if (!Schema::hasColumn('timesheets', 'magic_sign_expires_at')) {
                    $table->timestamp('magic_sign_expires_at')->nullable();
                }
                if (!Schema::hasColumn('timesheets', 'signed_ip')) {
                    $table->string('signed_ip', 64)->nullable();
                }
                if (!Schema::hasColumn('timesheets', 'ocr_confidence')) {
                    $table->unsignedTinyInteger('ocr_confidence')->nullable();
                }
                if (!Schema::hasColumn('timesheets', 'ocr_extracted_json')) {
                    $table->text('ocr_extracted_json')->nullable();
                }
            });
        }

        if (Schema::hasTable('interpreter_jobs')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('interpreter_jobs', 'calculated_interpreter_pay')) {
                    $table->decimal('calculated_interpreter_pay', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('interpreter_jobs', 'calculated_client_charge')) {
                    $table->decimal('calculated_client_charge', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('interpreter_jobs', 'calculated_margin')) {
                    $table->decimal('calculated_margin', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('interpreter_jobs', 'traffic_light_status')) {
                    $table->string('traffic_light_status', 20)->nullable()->index();
                }
                if (!Schema::hasColumn('interpreter_jobs', 'traffic_light_warnings')) {
                    $table->text('traffic_light_warnings')->nullable();
                }
                if (!Schema::hasColumn('interpreter_jobs', 'client_confirmed_at')) {
                    $table->timestamp('client_confirmed_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('client_invoices')) {
            Schema::table('client_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('client_invoices', 'due_date')) {
                    $table->date('due_date')->nullable()->index();
                }
                if (!Schema::hasColumn('client_invoices', 'last_chased_at')) {
                    $table->timestamp('last_chased_at')->nullable();
                }
                if (!Schema::hasColumn('client_invoices', 'chase_count')) {
                    $table->unsignedInteger('chase_count')->default(0);
                }
                if (!Schema::hasColumn('client_invoices', 'chaser_paused')) {
                    $table->boolean('chaser_paused')->default(false);
                }
                if (!Schema::hasColumn('client_invoices', 'remittance_magic_token')) {
                    $table->string('remittance_magic_token', 80)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('agents')) {
            Schema::table('agents', function (Blueprint $table) {
                if (!Schema::hasColumn('agents', 'reliability_score')) {
                    $table->decimal('reliability_score', 5, 2)->default(98.50);
                }
                if (!Schema::hasColumn('agents', 'on_time_rate')) {
                    $table->decimal('on_time_rate', 5, 2)->default(99.00);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_cards');
        Schema::dropIfExists('tenant_settings');
    }
};
