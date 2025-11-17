<?php

namespace Tests\Feature\Integration;

use App\Models\Spell;
use App\Models\ClassSpell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_real_spell_file(): void
    {
        $initialCount = Spell::count();

        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->assertSuccessful();

        $this->assertGreaterThan($initialCount, Spell::count());
    }

    public function test_imported_spell_has_correct_data(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->assertSuccessful();

        $spell = Spell::where('name', 'Fireball')->first();

        $this->assertNotNull($spell);
        $this->assertEquals(3, $spell->level);
        $this->assertTrue($spell->has_verbal_component);
        $this->assertTrue($spell->has_somatic_component);
        $this->assertTrue($spell->has_material_component);
    }

    public function test_imported_spell_has_class_associations(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->assertSuccessful();

        $spell = Spell::where('name', 'Fireball')->first();
        $classes = ClassSpell::where('spell_id', $spell->id)->get();

        $this->assertGreaterThan(0, $classes->count());
    }
}
