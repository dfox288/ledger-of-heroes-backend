<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * D&D-themed adjectives for generating public IDs
     */
    private const ADJECTIVES = [
        'ancient', 'arcane', 'bold', 'brave', 'crimson', 'dark', 'divine',
        'elder', 'eternal', 'fallen', 'fierce', 'frozen', 'golden', 'grim',
        'hidden', 'iron', 'lost', 'mighty', 'mystic', 'noble', 'primal',
        'radiant', 'rune', 'sacred', 'shadow', 'silent', 'silver', 'storm',
        'swift', 'thunder', 'twilight', 'valiant', 'wild', 'winter', 'wise',
    ];

    /**
     * D&D-themed nouns for generating public IDs
     */
    private const NOUNS = [
        'archer', 'bard', 'blade', 'cleric', 'dragon', 'druid', 'falcon',
        'flame', 'fury', 'guardian', 'hawk', 'herald', 'hunter', 'knight',
        'mage', 'oracle', 'paladin', 'phoenix', 'ranger', 'raven', 'rogue',
        'sage', 'seeker', 'sentinel', 'serpent', 'shade', 'shield', 'slayer',
        'sorcerer', 'sphinx', 'spirit', 'stalker', 'templar', 'titan', 'warden',
        'warrior', 'watcher', 'wolf', 'wraith', 'wyrm',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the column as nullable first
        Schema::table('characters', function (Blueprint $table) {
            $table->string('public_id', 30)->nullable()->unique()->after('id');
        });

        // Generate public_id for existing characters
        $characters = DB::table('characters')->whereNull('public_id')->get();

        foreach ($characters as $character) {
            $publicId = $this->generateUniquePublicId();
            DB::table('characters')
                ->where('id', $character->id)
                ->update(['public_id' => $publicId]);
        }

        // Make the column non-nullable after populating
        Schema::table('characters', function (Blueprint $table) {
            $table->string('public_id', 30)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }

    /**
     * Generate a unique public ID in format: adjective-noun-xxxx
     */
    private function generateUniquePublicId(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $adjective = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
            $noun = self::NOUNS[array_rand(self::NOUNS)];
            $suffix = $this->generateSuffix();

            $publicId = "{$adjective}-{$noun}-{$suffix}";
            $attempt++;

            $exists = DB::table('characters')->where('public_id', $publicId)->exists();
        } while ($exists && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            // Fallback: use a longer random string
            $publicId = "{$adjective}-{$noun}-".Str::random(8);
        }

        return $publicId;
    }

    /**
     * Generate a 4-character alphanumeric suffix (matching frontend nanoid format)
     */
    private function generateSuffix(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $suffix = '';
        for ($i = 0; $i < 4; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $suffix;
    }
};
