<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Race;

/**
 * Picks random valid entities from the database for wizard flow testing.
 * Uses seeded randomization for reproducibility.
 */
class CharacterRandomizer
{
    private int $callCount = 0;

    public function __construct(
        private readonly int $seed
    ) {
        mt_srand($this->seed);
    }

    /**
     * Get a random base race (not a subrace).
     */
    public function randomRace(): Race
    {
        $races = Race::whereNull('parent_race_id')->get();

        return $races[$this->randomInt(0, $races->count() - 1)];
    }

    /**
     * Get a different race than the current one.
     */
    public function differentRace(Race $current): Race
    {
        $races = Race::whereNull('parent_race_id')
            ->where('id', '!=', $current->id)
            ->get();

        if ($races->isEmpty()) {
            throw new \RuntimeException('No alternative races available');
        }

        return $races[$this->randomInt(0, $races->count() - 1)];
    }

    /**
     * Get a random subrace for a given race.
     */
    public function randomSubrace(Race $parentRace): ?Race
    {
        $subraces = $parentRace->subraces;

        if ($subraces->isEmpty()) {
            return null;
        }

        return $subraces[$this->randomInt(0, $subraces->count() - 1)];
    }

    /**
     * Get a random background.
     */
    public function randomBackground(): Background
    {
        $backgrounds = Background::all();

        return $backgrounds[$this->randomInt(0, $backgrounds->count() - 1)];
    }

    /**
     * Get a different background than the current one.
     */
    public function differentBackground(Background $current): Background
    {
        $backgrounds = Background::where('id', '!=', $current->id)->get();

        if ($backgrounds->isEmpty()) {
            throw new \RuntimeException('No alternative backgrounds available');
        }

        return $backgrounds[$this->randomInt(0, $backgrounds->count() - 1)];
    }

    /**
     * Get a random class.
     *
     * @param  bool|null  $spellcaster  null = any, true = spellcaster only, false = non-spellcaster only
     */
    public function randomClass(?bool $spellcaster = null): CharacterClass
    {
        $query = CharacterClass::whereNull('parent_class_id');

        if ($spellcaster === true) {
            $query->whereNotNull('spellcasting_ability_id');
        } elseif ($spellcaster === false) {
            $query->whereNull('spellcasting_ability_id');
        }

        $classes = $query->get();

        if ($classes->isEmpty()) {
            throw new \RuntimeException('No classes available matching criteria');
        }

        return $classes[$this->randomInt(0, $classes->count() - 1)];
    }

    /**
     * Get a different class than the current one.
     */
    public function differentClass(CharacterClass $current, ?bool $spellcaster = null): CharacterClass
    {
        $query = CharacterClass::whereNull('parent_class_id')
            ->where('id', '!=', $current->id);

        if ($spellcaster === true) {
            $query->whereNotNull('spellcasting_ability_id');
        } elseif ($spellcaster === false) {
            $query->whereNull('spellcasting_ability_id');
        }

        $classes = $query->get();

        if ($classes->isEmpty()) {
            throw new \RuntimeException('No alternative classes available matching criteria');
        }

        return $classes[$this->randomInt(0, $classes->count() - 1)];
    }

    /**
     * Generate random ability scores using standard array.
     */
    public function randomAbilityScores(): array
    {
        $standardArray = [15, 14, 13, 12, 10, 8];
        $this->shuffle($standardArray);

        return [
            'strength' => $standardArray[0],
            'dexterity' => $standardArray[1],
            'constitution' => $standardArray[2],
            'intelligence' => $standardArray[3],
            'wisdom' => $standardArray[4],
            'charisma' => $standardArray[5],
            'ability_score_method' => 'standard_array',
        ];
    }

    /**
     * Generate a unique public_id in format: adjective-noun-4char
     *
     * Uses truly random (non-seeded) generation for uniqueness.
     * Max length: 30 chars (adjective 8 + noun 8 + suffix 4 + dashes 2 = 22)
     */
    public function generatePublicId(): string
    {
        $adjectives = [
            'brave', 'swift', 'bold', 'wise', 'dark', 'silver', 'golden', 'iron',
            'shadow', 'storm', 'frost', 'flame', 'ancient', 'mighty', 'silent',
            'noble', 'wild', 'fierce', 'cunning', 'valiant',
        ];

        $nouns = [
            'warrior', 'mage', 'hunter', 'rogue', 'knight', 'sage', 'wanderer',
            'guardian', 'seeker', 'warden', 'blade', 'arrow', 'shield', 'wolf',
            'dragon', 'hawk', 'raven', 'phoenix', 'titan', 'oracle',
        ];

        // Use random_int() (non-seeded) for guaranteed uniqueness
        $adjective = $adjectives[random_int(0, count($adjectives) - 1)];
        $noun = $nouns[random_int(0, count($nouns) - 1)];
        $suffix = \Illuminate\Support\Str::random(4);

        return "{$adjective}-{$noun}-{$suffix}";
    }

    /**
     * Generate a random character name.
     */
    public function randomName(): string
    {
        $prefixes = [
            'Ael', 'Bal', 'Cor', 'Dar', 'Eld', 'Fen', 'Gar', 'Hal', 'Ith', 'Jar',
            'Kel', 'Lor', 'Mal', 'Nor', 'Orn', 'Pel', 'Quar', 'Ren', 'Sar', 'Tor',
            'Und', 'Val', 'Wyr', 'Xan', 'Yel', 'Zar',
        ];

        $suffixes = [
            'an', 'en', 'in', 'on', 'ar', 'er', 'ir', 'or', 'ak', 'ek',
            'ik', 'ok', 'us', 'is', 'os', 'ath', 'eth', 'ith', 'oth', 'uth',
        ];

        $prefix = $prefixes[$this->randomInt(0, count($prefixes) - 1)];
        $suffix = $suffixes[$this->randomInt(0, count($suffixes) - 1)];

        return $prefix.$suffix;
    }

    /**
     * Pick a random equipment mode.
     */
    public function randomEquipmentMode(): string
    {
        $modes = ['gold', 'equipment'];

        return $modes[$this->randomInt(0, count($modes) - 1)];
    }

    /**
     * Pick random items from an array.
     */
    public function pickRandom(array $items, int $count = 1): array
    {
        if (count($items) <= $count) {
            return $items;
        }

        $shuffled = $items;
        $this->shuffle($shuffled);

        return array_slice($shuffled, 0, $count);
    }

    /**
     * Get a random integer in range (inclusive).
     */
    public function randomInt(int $min, int $max): int
    {
        $this->callCount++;

        return mt_rand($min, $max);
    }

    /**
     * Generate a random alphanumeric string.
     */
    private function randomString(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[$this->randomInt(0, strlen($chars) - 1)];
        }

        return $result;
    }

    /**
     * Shuffle array in place using seeded random.
     */
    private function shuffle(array &$array): void
    {
        $count = count($array);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = $this->randomInt(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
    }

    /**
     * Get the seed used for this randomizer.
     */
    public function getSeed(): int
    {
        return $this->seed;
    }

    /**
     * Get the number of random calls made (useful for debugging reproducibility).
     */
    public function getCallCount(): int
    {
        return $this->callCount;
    }
}
