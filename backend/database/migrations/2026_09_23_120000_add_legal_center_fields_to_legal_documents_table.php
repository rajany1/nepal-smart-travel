<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('type');
            $table->string('short_description', 500)->nullable()->after('title');
            $table->string('status', 20)->default('draft')->after('is_published');
            $table->date('effective_date')->nullable()->after('status');
            $table->unsignedBigInteger('created_by')->nullable()->after('last_edited_by');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->index('slug');
            $table->index('status');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Backfill status (pure derivation from is_published — no data modification).
        // MySQL fills a newly added column with its default for existing rows,
        // so re-derive from is_published unconditionally.
        DB::statement(
            'UPDATE legal_documents SET status = CASE WHEN is_published = 1 THEN "published" ELSE "draft" END'
        );

        // Backfill slug from type using the canonical public URL slugs.
        $slugByType = [
            'terms_conditions' => 'terms',
            'privacy_policy' => 'privacy',
            'community_guidelines' => 'community-guidelines',
            'coins_wallet_terms' => 'coins',
            'sos_emergency_policy' => 'sos',
            'ai_disclosure' => 'ai',
            'business_advertising_terms' => 'business-advertising',
        ];

        foreach ($slugByType as $type => $slug) {
            DB::table('legal_documents')
                ->where('type', $type)
                ->whereNull('slug')
                ->update(['slug' => $slug]);
        }

        // Fallback: any remaining rows derive slug from their type.
        DB::table('legal_documents')
            ->whereNull('slug')
            ->orderBy('id')
            ->get(['id', 'type'])
            ->each(function ($row) {
                DB::table('legal_documents')->where('id', $row->id)->update([
                    'slug' => str_replace('_', '-', strtolower($row->type)),
                ]);
            });

        // Ensure all 7 canonical document types exist (insert-if-missing only).
        $types = [
            ['slug' => 'terms_conditions', 'label' => 'Terms & Conditions', 'sort_order' => 0],
            ['slug' => 'privacy_policy', 'label' => 'Privacy Policy', 'sort_order' => 0],
            ['slug' => 'community-guidelines', 'label' => 'Community Guidelines & Report Policy', 'sort_order' => 30],
            ['slug' => 'coins', 'label' => 'Coins & Wallet Terms', 'sort_order' => 40],
            ['slug' => 'sos', 'label' => 'SOS & Emergency Policy', 'sort_order' => 50],
            ['slug' => 'ai', 'label' => 'AI Disclosure', 'sort_order' => 60],
            ['slug' => 'business-advertising', 'label' => 'Business & Advertising Terms', 'sort_order' => 70],
        ];

        foreach ($types as $type) {
            $exists = DB::table('legal_document_types')->where('slug', $type['slug'])->exists();
            if (! $exists) {
                DB::table('legal_document_types')->insert([
                    'slug' => $type['slug'],
                    'label' => $type['label'],
                    'is_active' => true,
                    'sort_order' => $type['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Ensure draft document rows exist for documents not yet present.
        // Content is a clearly marked admin placeholder — NO legal text is invented.
        $placeholder = '[LEGAL CONTENT TO BE ADDED BY ADMIN]';
        $documents = [
            [
                'type' => 'community-guidelines',
                'slug' => 'community-guidelines',
                'title' => 'Community Guidelines & Report Policy',
                'short_description' => 'Community standards, moderation, and how to report content.',
            ],
            [
                'type' => 'coins',
                'slug' => 'coins',
                'title' => 'Coins & Wallet Terms',
                'short_description' => 'How Oripori Coins, the wallet, rewards, and withdrawals work.',
            ],
            [
                'type' => 'sos',
                'slug' => 'sos',
                'title' => 'SOS & Emergency Policy',
                'short_description' => 'How SOS and emergency features are used and what to expect.',
            ],
            [
                'type' => 'ai',
                'slug' => 'ai',
                'title' => 'AI Disclosure',
                'short_description' => 'How AI features are disclosed and used in the app.',
            ],
            [
                'type' => 'business-advertising',
                'slug' => 'business-advertising',
                'title' => 'Business & Advertising Terms',
                'short_description' => 'Terms for advertisers and business partners.',
            ],
        ];

        foreach ($documents as $doc) {
            $exists = DB::table('legal_documents')
                ->where('slug', $doc['slug'])
                ->orWhere('type', $doc['type'])
                ->exists();

            if (! $exists) {
                DB::table('legal_documents')->insert([
                    'type' => $doc['type'],
                    'slug' => $doc['slug'],
                    'title' => $doc['title'],
                    'short_description' => $doc['short_description'],
                    'content' => $placeholder,
                    'version' => '1.0',
                    'is_published' => false,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['slug']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'slug',
                'short_description',
                'status',
                'effective_date',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
