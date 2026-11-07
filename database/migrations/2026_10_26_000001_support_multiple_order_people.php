<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_people', function (Blueprint $table) {
            $table->index('order_id'); // Keep the existing FK indexed before removing its unique index.
            $table->dropUnique(['order_id']);
            $table->ulid('public_id')->nullable()->unique();
            $table->unsignedTinyInteger('evidence_version')->default(1);
            $table->unique(['order_id', 'id']);
        });
        Schema::create('order_person_services', function (Blueprint $table) {
            $table->foreignId('order_person_id')->constrained('order_people')->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->primary(['order_person_id', 'service_id']);
        });
        Schema::table('order_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_person_id')->nullable();
            // A document can never reference a person belonging to another order.
            $table->foreign(['order_id', 'order_person_id'])->references(['order_id', 'id'])->on('order_people')->restrictOnDelete();
        });
        DB::table('order_people')->orderBy('id')->chunkById(100, function ($people) {
            foreach ($people as $person) {
                DB::table('order_people')->where('id', $person->id)->update(['public_id' => (string) Str::ulid()]);
                $ids = DB::table('order_items')->join('services', 'services.id', '=', 'order_items.service_id')
                    ->where('order_id', $person->order_id)->whereIn('services.code', ['university-degree', 'exequatur', 'identity-verification'])->pluck('service_id');
                foreach ($ids as $id) {
                    DB::table('order_person_services')->insert(['order_person_id' => $person->id, 'service_id' => $id]);
                }
                DB::table('order_attachments')->where('order_id', $person->order_id)->where('purpose', '!=', 'supporting')->update(['order_person_id' => $person->id]);
            }
        });
        Schema::table('orders', fn (Blueprint $table) => $table->longText('details')->nullable()->change());
    }

    public function down(): void
    {
        // Refuse a rollback that would erase people or invalidate existing nullable details.
        if (DB::table('order_people')->select('order_id')->groupBy('order_id')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('orders')->whereNull('details')->exists()
            || DB::table('orders')->whereRaw('LENGTH(details) > 65535')->exists()) {
            throw new RuntimeException('No se puede revertir: existen solicitudes con varias personas, sin detalles o con texto cifrado que excede el esquema anterior.');
        }
        Schema::table('order_attachments', function (Blueprint $table) {
            $table->dropForeign(['order_id', 'order_person_id']);
            $table->dropColumn('order_person_id');
        });
        Schema::dropIfExists('order_person_services');
        Schema::table('order_people', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'id']);
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'evidence_version']);
            $table->unique('order_id');
            $table->dropIndex(['order_id']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->text('details')->nullable(false)->change());
    }
};
