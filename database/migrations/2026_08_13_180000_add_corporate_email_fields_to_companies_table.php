<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'primary_color')) {
                $table->string('primary_color')->default('#4F46E5')->after('logo');
            }
            if (!Schema::hasColumn('companies', 'alternate_phone')) {
                $table->string('alternate_phone')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('companies', 'gst_number')) {
                $table->string('gst_number')->nullable()->after('country');
            }
            if (!Schema::hasColumn('companies', 'cin_number')) {
                $table->string('cin_number')->nullable()->after('gst_number');
            }
            if (!Schema::hasColumn('companies', 'business_hours')) {
                $table->string('business_hours')->nullable()->after('cin_number');
            }
            if (!Schema::hasColumn('companies', 'privacy_policy_url')) {
                $table->string('privacy_policy_url')->nullable()->after('website');
            }
            if (!Schema::hasColumn('companies', 'terms_url')) {
                $table->string('terms_url')->nullable()->after('privacy_policy_url');
            }
            if (!Schema::hasColumn('companies', 'default_sender_designation')) {
                $table->string('default_sender_designation')->default('Business Development Specialist')->after('default_sender_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color',
                'alternate_phone',
                'gst_number',
                'cin_number',
                'business_hours',
                'privacy_policy_url',
                'terms_url',
                'default_sender_designation'
            ]);
        });
    }
};
