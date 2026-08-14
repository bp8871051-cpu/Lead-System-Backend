<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Companies Table
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('industry')->nullable();
            $table->json('services')->nullable();
            $table->json('products')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->text('target_audience')->nullable();
            $table->json('target_industries')->nullable();
            $table->json('target_locations')->nullable();
            $table->text('usp')->nullable();
            $table->string('company_tone')->default('Professional');
            $table->text('email_signature')->nullable();
            $table->string('default_sender_name')->nullable();
            $table->string('default_sender_email')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        // 2. Add company_id & phone to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->onDelete('cascade');
            $table->string('role')->default('admin')->after('email');
            $table->string('phone')->nullable()->after('role');
        });

        // 3. Company Settings
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('apify_api_token')->nullable();
            $table->string('apify_actor_id')->default('compass/google-maps-extractor');
            $table->string('ai_provider')->default('openrouter');
            $table->string('ai_api_key')->nullable();
            $table->string('ai_model')->default('google/gemini-2.5-flash');
            $table->float('ai_temperature')->default(0.7);
            $table->string('brevo_api_key')->nullable();
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->default(587);
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->string('smtp_encryption')->default('tls');
            $table->string('smtp_from_email')->nullable();
            $table->string('smtp_from_name')->nullable();
            $table->timestamps();
        });

        // 4. Leads Table
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('business_name');
            $table->string('contact_name')->nullable();
            $table->string('category')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('secondary_email')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('secondary_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('website')->nullable()->index();
            $table->enum('website_status', ['has_website', 'no_website', 'invalid', 'unreachable', 'unknown'])->default('unknown');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('google_maps_url')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('google_rating', 3, 2)->nullable();
            $table->integer('review_count')->default(0);
            $table->enum('source', ['apify', 'google_maps', 'excel_import', 'manual'])->default('manual')->index();
            $table->string('source_id')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->enum('lead_status', [
                'new', 'contacted', 'email_generated', 'email_sent', 'replied',
                'interested', 'follow_up', 'converted', 'not_interested', 'closed'
            ])->default('new')->index();
            $table->enum('email_status', ['available', 'missing', 'invalid'])->default('missing')->index();
            $table->enum('phone_status', ['available', 'missing'])->default('missing');
            $table->enum('outreach_status', ['pending', 'queued', 'sent', 'failed'])->default('pending');
            $table->timestamps();
            $table->softDeletes()->index();
        });

        // 5. Lead Notes Table
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('note');
            $table->timestamps();
        });

        // 6. Tags Table
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('color')->default('#6366f1');
            $table->timestamps();
        });

        // 7. Scraping Jobs & Results
        Schema::create('scraping_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('job_number')->unique();
            $table->string('keyword');
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->integer('requested_count')->default(50);
            $table->decimal('rating_min', 3, 2)->nullable();
            $table->decimal('rating_max', 3, 2)->nullable();
            $table->string('website_filter')->default('all');
            $table->boolean('has_email_filter')->default(false);
            $table->boolean('has_phone_filter')->default(false);
            $table->enum('status', ['queued', 'running', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('apify_run_id')->nullable();
            $table->integer('leads_found')->default(0);
            $table->integer('leads_saved')->default(0);
            $table->integer('duplicates_found')->default(0);
            $table->integer('invalid_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scraping_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraping_job_id')->constrained('scraping_jobs')->onDelete('cascade');
            $table->json('raw_data');
            $table->timestamps();
        });

        // 8. Email Templates
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->string('service')->nullable();
            $table->string('tone')->default('Professional');
            $table->json('variables')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 9. Campaigns & Campaign Leads
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('service')->nullable();
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->onDelete('set null');
            $table->string('subject')->nullable();
            $table->integer('daily_sending_limit')->default(100);
            $table->enum('sending_provider', ['brevo', 'smtp'])->default('brevo');
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'running', 'paused', 'completed', 'failed'])->default('draft');
            $table->integer('total_leads')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->enum('status', ['pending', 'queued', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // 10. Generated Emails
        Schema::create('generated_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            $table->string('subject');
            $table->text('body');
            $table->string('tone')->default('Professional');
            $table->string('length')->default('Medium');
            $table->string('cta')->default('Book a Call');
            $table->string('service_offered')->nullable();
            $table->enum('status', ['draft', 'approved', 'sent'])->default('draft');
            $table->timestamps();
        });

        // 11. Email Logs
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            $table->string('subject');
            $table->string('provider')->default('brevo');
            $table->string('sender_email');
            $table->string('recipient_email');
            $table->enum('status', ['queued', 'sent', 'failed', 'bounced', 'replied'])->default('queued');
            $table->string('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // 12. Suppression List
        Schema::create('suppression_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('email')->index();
            $table->string('reason')->default('unsubscribe');
            $table->timestamps();
        });

        // 13. Import Logs
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('filename');
            $table->integer('total_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('duplicate_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->timestamps();
        });

        // 14. Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('description');
            $table->string('action_type');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('suppression_list');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('generated_emails');
        Schema::dropIfExists('campaign_leads');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('scraping_results');
        Schema::dropIfExists('scraping_jobs');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('company_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'role', 'phone']);
        });
        Schema::dropIfExists('companies');
    }
};
