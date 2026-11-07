<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The two columns of a host's user table that the default subscription preferences read. Test
 * only: it runs after the package's migrations (hence the late date), once, with the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('thread_subscription_email_frequency')->nullable();
            $table->boolean('auto_subscribe_on_post')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['thread_subscription_email_frequency', 'auto_subscribe_on_post']));
    }
};
