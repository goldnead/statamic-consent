<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();

            // The random id the browser carries. It is what links a record to a
            // visitor's cookie, and it is the only identifier here — no IP, no
            // user agent. Indexed because looking a person up by it is the one
            // question this table exists to answer.
            $table->string('consent_id', 64)->index();

            // What was decided, and what it was decided about. The version is
            // the load-bearing column: it proves *which* set of services the
            // visitor was shown, which is the part a later dispute turns on.
            $table->unsignedInteger('version');
            $table->json('granted');
            $table->string('how', 32);
            $table->string('site', 64)->nullable();

            // The browser's own timestamp, kept as it was recorded, next to the
            // server's. They can differ by a lot on a device with a wrong clock,
            // and pretending otherwise would be inventing precision.
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();

            // One row per decision. A visitor who reloads a page does not create
            // a second record of the same decision.
            $table->unique(['consent_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
