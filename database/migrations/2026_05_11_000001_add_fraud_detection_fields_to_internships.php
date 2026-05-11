<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            if (! Schema::hasColumn('internships', 'certificate_hash')) {
                $table->string('certificate_hash', 64)->nullable()->after('certificate_path')->index();
            }

            if (! Schema::hasColumn('internships', 'certificate_text')) {
                $table->longText('certificate_text')->nullable()->after('certificate_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            if (Schema::hasColumn('internships', 'certificate_text')) {
                $table->dropColumn('certificate_text');
            }

            if (Schema::hasColumn('internships', 'certificate_hash')) {
                $table->dropIndex(['certificate_hash']);
                $table->dropColumn('certificate_hash');
            }
        });
    }
};
