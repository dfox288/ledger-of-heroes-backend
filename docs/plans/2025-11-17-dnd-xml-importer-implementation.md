# D&D 5e XML Importer Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete Laravel-based command-line tool that parses D&D 5e XML files and imports them into a relational database following the approved schema design.

**Architecture:** Laravel application with Artisan commands for importing, XML parsing service classes for each content type (spells, items, races, feats, backgrounds), database migrations with polymorphic relationships, and comprehensive test coverage using TDD.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, Docker & Docker Compose, Nginx, PHP-FPM, PHPUnit for testing, Symfony Console for CLI, SimpleXML for XML parsing

---

## Prerequisites

Before starting implementation, ensure you have:
- Docker Desktop or Docker Engine installed
- Docker Compose 2.x installed
- Git for version control
- The database schema design document (docs/plans/2025-11-17-dnd-compendium-database-design.md)

**Note:** PHP and Composer will run inside Docker containers, so local installation is not required.

---

## Task 1: Initialize Laravel Project

**Files:**
- Create: entire Laravel project structure via Composer

**Step 1: Create new Laravel project**

Run: `docker run --rm -v $(pwd):/app composer create-project laravel/laravel temp --prefer-dist && mv temp/* temp/.* . 2>/dev/null || true && rm -rf temp`

Alternative (if you have Composer locally):
```bash
composer create-project laravel/laravel . --prefer-dist
```

Expected: Laravel 11.x installed in current directory

**Step 2: Create initial git repository**

```bash
git init
git add .
git commit -m "chore: initialize Laravel 11 project for D&D importer"
```

**Step 3: Verify project structure**

Run: `ls -la`
Expected: Laravel directory structure with `app/`, `database/`, `public/`, etc.

---

## Task 2: Setup Docker Environment

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/php/Dockerfile`
- Create: `docker/nginx/default.conf`
- Create: `.dockerignore`
- Modify: `.env`

**Step 1: Write test for Docker setup**

Create: `tests/Feature/DockerEnvironmentTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DockerEnvironmentTest extends TestCase
{
    public function test_database_connection_works(): void
    {
        $this->assertTrue(DB::connection()->getPdo() !== null);
    }

    public function test_php_version_is_correct(): void
    {
        $this->assertGreaterThanOrEqual(8.4, (float) PHP_VERSION);
    }
}
```

**Step 2: Run test to verify it fails**

Note: Cannot run yet without Docker setup
Expected: Will verify after Docker is configured

**Step 3: Create Dockerfile for PHP-FPM**

Create: `docker/php/Dockerfile`

```dockerfile
FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY . /var/www/html

# Copy existing application directory permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
```

**Step 4: Create Nginx configuration**

Create: `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;

    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Disable logging for common static files
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml)$ {
        access_log off;
        log_not_found off;
        expires 30d;
    }
}
```

**Step 5: Create Docker Compose configuration**

Create: `docker-compose.yml`

```yaml
version: '3.8'

services:
  # PHP-FPM Service
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: dnd_importer_php
    volumes:
      - ./:/var/www/html
      - ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini
    networks:
      - dnd_network
    depends_on:
      - mysql

  # Nginx Service
  nginx:
    image: nginx:alpine
    container_name: dnd_importer_nginx
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - dnd_network
    depends_on:
      - php

  # MySQL Service
  mysql:
    image: mysql:8.0
    container_name: dnd_importer_mysql
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: dnd_compendium
      MYSQL_USER: dnd_user
      MYSQL_PASSWORD: dnd_password
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - dnd_network
    command: --default-authentication-plugin=mysql_native_password

networks:
  dnd_network:
    driver: bridge

volumes:
  mysql_data:
    driver: local
```

**Step 6: Create PHP custom configuration**

Create: `docker/php/local.ini`

```ini
upload_max_filesize=40M
post_max_size=40M
memory_limit=256M
```

**Step 7: Create .dockerignore**

Create: `.dockerignore`

```
.git
.gitignore
.env
.env.example
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*
.phpunit.result.cache
```

**Step 8: Update .env file for Docker**

Update `.env` with Docker-specific database configuration:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=dnd_compendium
DB_USERNAME=dnd_user
DB_PASSWORD=dnd_password
```

**Step 9: Build and start Docker containers**

```bash
docker-compose build
docker-compose up -d
```

Expected: All three containers start successfully

**Step 10: Install Composer dependencies**

```bash
docker-compose exec php composer install
```

Expected: Laravel dependencies installed inside container

**Step 11: Generate application key**

```bash
docker-compose exec php php artisan key:generate
```

Expected: APP_KEY set in .env file

**Step 12: Run migrations**

```bash
docker-compose exec php php artisan migrate
```

Expected: Default Laravel migrations run successfully

**Step 13: Run test to verify Docker environment**

```bash
docker-compose exec php php artisan test --filter=DockerEnvironmentTest
```

Expected: PASS - Database connection and PHP version tests pass

**Step 14: Verify Nginx is serving Laravel**

Run: `curl http://localhost:8080`
Expected: Laravel welcome page HTML returned

**Step 15: Create helper script for running commands**

Create: `docker-exec.sh`

```bash
#!/bin/bash
# Helper script to run commands inside PHP container
docker-compose exec php "$@"
```

Make executable:
```bash
chmod +x docker-exec.sh
```

**Step 16: Commit**

```bash
git add .
git commit -m "feat: add Docker environment with php-fpm, nginx, and mysql"
```

---

## Task 3: Create Database Migrations - Core Lookup Tables

**Files:**
- Create: `database/migrations/2025_11_17_000001_create_spell_schools_table.php`
- Create: `database/migrations/2025_11_17_000002_create_damage_types_table.php`
- Create: `database/migrations/2025_11_17_000003_create_item_types_table.php`
- Create: `database/migrations/2025_11_17_000004_create_item_rarities_table.php`
- Create: `database/migrations/2025_11_17_000005_create_sizes_table.php`

**Step 1: Write test for spell schools migration**

Create: `tests/Unit/Migrations/SpellSchoolsMigrationTest.php`

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellSchoolsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_schools_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spell_schools'));
        $this->assertTrue(Schema::hasColumns('spell_schools', [
            'id', 'code', 'name', 'created_at', 'updated_at'
        ]));
    }

    public function test_spell_schools_code_is_unique(): void
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getDoctrineSchemaManager()
            ->listTableIndexes('spell_schools');

        $this->assertTrue(isset($indexes['spell_schools_code_unique']));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker-compose exec php php artisan test --filter=SpellSchoolsMigrationTest`
Expected: FAIL with "Table 'spell_schools' doesn't exist"

**Step 3: Create spell schools migration**

Run: `docker-compose exec php php artisan make:migration create_spell_schools_table`

Edit the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spell_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 1)->unique(); // A, C, D, E, I, N, T, V
            $table->string('name', 50); // Abjuration, Conjuration, etc.
            $table->timestamps();
        });

        // Seed with standard D&D 5e schools
        DB::table('spell_schools')->insert([
            ['code' => 'A', 'name' => 'Abjuration'],
            ['code' => 'C', 'name' => 'Conjuration'],
            ['code' => 'D', 'name' => 'Divination'],
            ['code' => 'E', 'name' => 'Enchantment'],
            ['code' => 'EV', 'name' => 'Evocation'],
            ['code' => 'I', 'name' => 'Illusion'],
            ['code' => 'N', 'name' => 'Necromancy'],
            ['code' => 'T', 'name' => 'Transmutation'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('spell_schools');
    }
};
```

**Step 4: Run test to verify it passes**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=SpellSchoolsMigrationTest`
Expected: PASS

**Step 5: Create remaining lookup table migrations**

Create damage types migration:

```php
docker-compose exec php php artisan make:migration create_damage_types_table
```

```php
Schema::create('damage_types', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name', 50);
    $table->timestamps();
});

DB::table('damage_types')->insert([
    ['code' => 'acid', 'name' => 'Acid'],
    ['code' => 'bludgeoning', 'name' => 'Bludgeoning'],
    ['code' => 'cold', 'name' => 'Cold'],
    ['code' => 'fire', 'name' => 'Fire'],
    ['code' => 'force', 'name' => 'Force'],
    ['code' => 'lightning', 'name' => 'Lightning'],
    ['code' => 'necrotic', 'name' => 'Necrotic'],
    ['code' => 'piercing', 'name' => 'Piercing'],
    ['code' => 'poison', 'name' => 'Poison'],
    ['code' => 'psychic', 'name' => 'Psychic'],
    ['code' => 'radiant', 'name' => 'Radiant'],
    ['code' => 'slashing', 'name' => 'Slashing'],
    ['code' => 'thunder', 'name' => 'Thunder'],
]);
```

Create item types migration:

```php
docker-compose exec php php artisan make:migration create_item_types_table
```

```php
Schema::create('item_types', function (Blueprint $table) {
    $table->id();
    $table->string('code', 5)->unique(); // M, R, A, G, W, etc.
    $table->string('name', 50);
    $table->string('category', 50); // weapon, armor, gear, etc.
    $table->timestamps();
});

DB::table('item_types')->insert([
    ['code' => 'M', 'name' => 'Melee Weapon', 'category' => 'weapon'],
    ['code' => 'R', 'name' => 'Ranged Weapon', 'category' => 'weapon'],
    ['code' => 'A', 'name' => 'Ammunition', 'category' => 'weapon'],
    ['code' => 'LA', 'name' => 'Light Armor', 'category' => 'armor'],
    ['code' => 'MA', 'name' => 'Medium Armor', 'category' => 'armor'],
    ['code' => 'HA', 'name' => 'Heavy Armor', 'category' => 'armor'],
    ['code' => 'S', 'name' => 'Shield', 'category' => 'armor'],
    ['code' => 'G', 'name' => 'Adventuring Gear', 'category' => 'gear'],
    ['code' => 'W', 'name' => 'Wondrous Item', 'category' => 'magic'],
    ['code' => 'P', 'name' => 'Potion', 'category' => 'magic'],
    ['code' => 'SC', 'name' => 'Scroll', 'category' => 'magic'],
    ['code' => 'RD', 'name' => 'Rod', 'category' => 'magic'],
    ['code' => 'ST', 'name' => 'Staff', 'category' => 'magic'],
    ['code' => 'WD', 'name' => 'Wand', 'category' => 'magic'],
    ['code' => 'RG', 'name' => 'Ring', 'category' => 'magic'],
]);
```

Create item rarities and sizes tables similarly.

**Step 6: Run migrations**

Run: `docker-compose exec php php artisan migrate:fresh`
Expected: All lookup tables created and seeded

**Step 7: Commit**

```bash
git add database/migrations/*_create_*_types_table.php
git add database/migrations/*_create_*_schools_table.php
git add database/migrations/*_create_sizes_table.php
git add tests/Unit/Migrations/
git commit -m "feat: add lookup table migrations with seed data"
```

---

## Task 4: Create Database Migrations - Source Books

**Files:**
- Create: `database/migrations/2025_11_17_000010_create_source_books_table.php`
- Create: `tests/Unit/Migrations/SourceBooksMigrationTest.php`

**Step 1: Write test for source books migration**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourceBooksMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_books_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('source_books'));
        $this->assertTrue(Schema::hasColumns('source_books', [
            'id', 'code', 'name', 'abbreviation', 'release_date', 'publisher', 'created_at', 'updated_at'
        ]));
    }

    public function test_source_books_code_is_unique(): void
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getDoctrineSchemaManager()
            ->listTableIndexes('source_books');

        $this->assertTrue(isset($indexes['source_books_code_unique']));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker-compose exec php php artisan test --filter=SourceBooksMigrationTest`
Expected: FAIL

**Step 3: Create migration**

```php
docker-compose exec php php artisan make:migration create_source_books_table
```

```php
Schema::create('source_books', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10)->unique(); // PHB, XGE, DMG, etc.
    $table->string('name', 255);
    $table->string('abbreviation', 20);
    $table->date('release_date')->nullable();
    $table->string('publisher', 100)->default('Wizards of the Coast');
    $table->timestamps();
});

// Seed common source books
DB::table('source_books')->insert([
    ['code' => 'PHB', 'name' => "Player's Handbook", 'abbreviation' => 'PHB', 'release_date' => '2014-08-19'],
    ['code' => 'DMG', 'name' => "Dungeon Master's Guide", 'abbreviation' => 'DMG', 'release_date' => '2014-12-09'],
    ['code' => 'MM', 'name' => 'Monster Manual', 'abbreviation' => 'MM', 'release_date' => '2014-09-30'],
    ['code' => 'XGE', 'name' => "Xanathar's Guide to Everything", 'abbreviation' => 'XGE', 'release_date' => '2017-11-21'],
    ['code' => 'TCE', 'name' => "Tasha's Cauldron of Everything", 'abbreviation' => 'TCE', 'release_date' => '2020-11-17'],
    ['code' => 'VGTM', 'name' => "Volo's Guide to Monsters", 'abbreviation' => 'VGTM', 'release_date' => '2016-11-15'],
]);
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=SourceBooksMigrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_source_books_table.php
git add tests/Unit/Migrations/SourceBooksMigrationTest.php
git commit -m "feat: add source_books table migration"
```

---

## Task 5: Create Database Migrations - Spells Table

**Files:**
- Create: `database/migrations/2025_11_17_000020_create_spells_table.php`
- Create: `tests/Unit/Migrations/SpellsMigrationTest.php`

**Step 1: Write test for spells migration**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spells_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spells'));

        $expectedColumns = [
            'id', 'name', 'slug', 'level', 'school_id', 'is_ritual',
            'casting_time', 'range', 'duration', 'has_verbal_component',
            'has_somatic_component', 'has_material_component',
            'material_description', 'material_cost_gp', 'material_consumed',
            'description', 'source_book_id', 'source_page',
            'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('spells', $expectedColumns));
    }

    public function test_spells_foreign_keys_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('spells', 'school_id'));
        $this->assertTrue(Schema::hasColumn('spells', 'source_book_id'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker-compose exec php php artisan test --filter=SpellsMigrationTest`
Expected: FAIL

**Step 3: Create migration**

```php
docker-compose exec php php artisan make:migration create_spells_table
```

```php
Schema::create('spells', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique(); // URL-safe version: "acid-splash"
    $table->unsignedTinyInteger('level'); // 0-9 (0 = cantrip)
    $table->foreignId('school_id')->constrained('spell_schools')->onDelete('restrict');
    $table->boolean('is_ritual')->default(false);

    // Casting details
    $table->string('casting_time', 100); // "1 action", "1 minute", "1 bonus action"
    $table->string('range', 100); // "60 feet", "Self", "Touch"
    $table->string('duration', 100); // "Instantaneous", "Concentration, up to 1 minute"

    // Component parsing
    $table->boolean('has_verbal_component')->default(false);
    $table->boolean('has_somatic_component')->default(false);
    $table->boolean('has_material_component')->default(false);
    $table->string('material_description', 500)->nullable();
    $table->unsignedInteger('material_cost_gp')->nullable();
    $table->boolean('material_consumed')->default(false);

    // Description
    $table->text('description'); // Full spell description from XML

    // Source tracking
    $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
    $table->unsignedSmallInteger('source_page')->nullable();

    $table->timestamps();

    // Indexes for common queries
    $table->index('level');
    $table->index('school_id');
    $table->index(['level', 'school_id']);
});
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=SpellsMigrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_spells_table.php
git add tests/Unit/Migrations/SpellsMigrationTest.php
git commit -m "feat: add spells table migration with component parsing columns"
```

---

## Task 6: Create Database Migrations - Spell Effects Table

**Files:**
- Create: `database/migrations/2025_11_17_000021_create_spell_effects_table.php`

**Step 1: Write test**

Create: `tests/Unit/Migrations/SpellEffectsMigrationTest.php`

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellEffectsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_effects_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('spell_effects'));
        $this->assertTrue(Schema::hasColumns('spell_effects', [
            'id', 'spell_id', 'effect_type', 'dice_formula', 'scaling_type',
            'scaling_trigger', 'damage_type_id', 'created_at', 'updated_at'
        ]));
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellEffectsMigrationTest`
Expected: FAIL

**Step 3: Create migration**

```php
docker-compose exec php php artisan make:migration create_spell_effects_table
```

```php
Schema::create('spell_effects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('spell_id')->constrained('spells')->onDelete('cascade');
    $table->enum('effect_type', ['damage', 'healing', 'buff', 'debuff', 'utility']);
    $table->string('dice_formula', 50)->nullable(); // "1d6", "2d8+5", "1d6 per slot level"
    $table->enum('scaling_type', ['none', 'character_level', 'spell_slot_level'])->default('none');
    $table->unsignedTinyInteger('scaling_trigger')->nullable(); // Level at which scaling occurs
    $table->foreignId('damage_type_id')->nullable()->constrained('damage_types')->onDelete('set null');
    $table->timestamps();

    $table->index('spell_id');
});
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=SpellEffectsMigrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_spell_effects_table.php
git add tests/Unit/Migrations/SpellEffectsMigrationTest.php
git commit -m "feat: add spell_effects table for damage/healing scaling"
```

---

## Task 7: Create Database Migrations - Spell-Class Junction Table

**Files:**
- Create: `database/migrations/2025_11_17_000022_create_class_spell_table.php`

**Step 1: Write test**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassSpellMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_spell_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('class_spell'));
        $this->assertTrue(Schema::hasColumns('class_spell', [
            'id', 'spell_id', 'class_name', 'subclass_name', 'created_at', 'updated_at'
        ]));
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=ClassSpellMigrationTest`
Expected: FAIL

**Step 3: Create migration**

```php
docker-compose exec php php artisan make:migration create_class_spell_table
```

```php
Schema::create('class_spell', function (Blueprint $table) {
    $table->id();
    $table->foreignId('spell_id')->constrained('spells')->onDelete('cascade');
    $table->string('class_name', 50); // "Wizard", "Cleric", "Sorcerer"
    $table->string('subclass_name', 100)->nullable(); // "Eldritch Knight", "Arcane Trickster"
    $table->timestamps();

    // Prevent duplicate spell-class assignments
    $table->unique(['spell_id', 'class_name', 'subclass_name']);
    $table->index('class_name');
});
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=ClassSpellMigrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_class_spell_table.php
git add tests/Unit/Migrations/ClassSpellMigrationTest.php
git commit -m "feat: add class_spell junction table for spell-to-class mapping"
```

---

## Task 8: Create Database Migrations - Items Table

**Files:**
- Create: `database/migrations/2025_11_17_000030_create_items_table.php`

**Step 1: Write test**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('items'));

        $expectedColumns = [
            'id', 'name', 'slug', 'item_type_id', 'rarity_id',
            'weight_lbs', 'value_gp', 'description', 'attunement_required',
            'attunement_requirements', 'source_book_id', 'source_page',
            'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('items', $expectedColumns));
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=ItemsMigrationTest`
Expected: FAIL

**Step 3: Create migration**

```php
docker-compose exec php php artisan make:migration create_items_table
```

```php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->foreignId('item_type_id')->constrained('item_types')->onDelete('restrict');
    $table->foreignId('rarity_id')->nullable()->constrained('item_rarities')->onDelete('set null');
    $table->decimal('weight_lbs', 8, 2)->nullable();
    $table->decimal('value_gp', 10, 2)->nullable();
    $table->text('description');
    $table->boolean('attunement_required')->default(false);
    $table->string('attunement_requirements', 500)->nullable(); // "by a spellcaster"
    $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
    $table->unsignedSmallInteger('source_page')->nullable();
    $table->timestamps();

    $table->index('item_type_id');
    $table->index('rarity_id');
});
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=ItemsMigrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_items_table.php
git add tests/Unit/Migrations/ItemsMigrationTest.php
git commit -m "feat: add items table migration"
```

---

## Task 9: Create Database Migrations - Polymorphic Tables

**Files:**
- Create: `database/migrations/2025_11_17_000100_create_traits_table.php`
- Create: `database/migrations/2025_11_17_000101_create_modifiers_table.php`
- Create: `database/migrations/2025_11_17_000102_create_proficiencies_table.php`

**Step 1: Write test for traits table**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PolymorphicTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_traits_table_has_polymorphic_columns(): void
    {
        $this->assertTrue(Schema::hasTable('traits'));
        $this->assertTrue(Schema::hasColumns('traits', [
            'id', 'reference_type', 'reference_id', 'name', 'category', 'description',
            'created_at', 'updated_at'
        ]));
    }

    public function test_modifiers_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('modifiers'));
        $this->assertTrue(Schema::hasColumns('modifiers', [
            'id', 'reference_type', 'reference_id', 'modifier_type', 'target',
            'value', 'created_at', 'updated_at'
        ]));
    }

    public function test_proficiencies_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('proficiencies'));
        $this->assertTrue(Schema::hasColumns('proficiencies', [
            'id', 'reference_type', 'reference_id', 'proficiency_type', 'name',
            'created_at', 'updated_at'
        ]));
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=PolymorphicTablesMigrationTest`
Expected: FAIL

**Step 3: Create traits migration**

```php
docker-compose exec php php artisan make:migration create_traits_table
```

```php
Schema::create('traits', function (Blueprint $table) {
    $table->id();
    $table->string('reference_type'); // Race, Background, Feat, Class
    $table->unsignedBigInteger('reference_id');
    $table->string('name');
    $table->string('category', 50)->nullable(); // "description", "feature", etc.
    $table->text('description');
    $table->timestamps();

    $table->index(['reference_type', 'reference_id']);
});
```

**Step 4: Create modifiers migration**

```php
docker-compose exec php php artisan make:migration create_modifiers_table
```

```php
Schema::create('modifiers', function (Blueprint $table) {
    $table->id();
    $table->string('reference_type'); // Race, Feat, Item, etc.
    $table->unsignedBigInteger('reference_id');
    $table->enum('modifier_type', ['ability_score', 'bonus', 'speed']);
    $table->string('target', 50); // "strength", "initiative", "walking"
    $table->string('value', 20); // "+2", "+1d4", "disadvantage"
    $table->timestamps();

    $table->index(['reference_type', 'reference_id']);
});
```

**Step 5: Create proficiencies migration**

```php
docker-compose exec php php artisan make:migration create_proficiencies_table
```

```php
Schema::create('proficiencies', function (Blueprint $table) {
    $table->id();
    $table->string('reference_type'); // Race, Background, Class
    $table->unsignedBigInteger('reference_id');
    $table->enum('proficiency_type', ['skill', 'tool', 'weapon', 'armor', 'language', 'saving_throw']);
    $table->string('name', 100);
    $table->timestamps();

    $table->index(['reference_type', 'reference_id']);
    $table->index('proficiency_type');
});
```

**Step 6: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=PolymorphicTablesMigrationTest`
Expected: PASS

**Step 7: Commit**

```bash
git add database/migrations/*_create_traits_table.php
git add database/migrations/*_create_modifiers_table.php
git add database/migrations/*_create_proficiencies_table.php
git add tests/Unit/Migrations/PolymorphicTablesMigrationTest.php
git commit -m "feat: add polymorphic tables for traits, modifiers, proficiencies"
```

---

## Task 10: Create Database Migrations - Races, Backgrounds, Feats

**Files:**
- Create: `database/migrations/2025_11_17_000110_create_races_table.php`
- Create: `database/migrations/2025_11_17_000111_create_backgrounds_table.php`
- Create: `database/migrations/2025_11_17_000112_create_feats_table.php`

**Step 1: Write test**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CharacterOptionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_races_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('races'));
        $this->assertTrue(Schema::hasColumns('races', [
            'id', 'name', 'slug', 'size_id', 'speed', 'source_book_id', 'source_page'
        ]));
    }

    public function test_backgrounds_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('backgrounds'));
        $this->assertTrue(Schema::hasColumns('backgrounds', [
            'id', 'name', 'slug', 'source_book_id', 'source_page'
        ]));
    }

    public function test_feats_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('feats'));
        $this->assertTrue(Schema::hasColumns('feats', [
            'id', 'name', 'slug', 'description', 'source_book_id', 'source_page'
        ]));
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=CharacterOptionsMigrationTest`
Expected: FAIL

**Step 3: Create migrations**

```php
docker-compose exec php php artisan make:migration create_races_table
docker-compose exec php php artisan make:migration create_backgrounds_table
docker-compose exec php php artisan make:migration create_feats_table
```

Races migration:
```php
Schema::create('races', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->foreignId('size_id')->constrained('sizes')->onDelete('restrict');
    $table->unsignedTinyInteger('speed')->default(30); // Base walking speed in feet
    $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
    $table->unsignedSmallInteger('source_page')->nullable();
    $table->timestamps();
});
```

Backgrounds migration:
```php
Schema::create('backgrounds', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
    $table->unsignedSmallInteger('source_page')->nullable();
    $table->timestamps();
});
```

Feats migration:
```php
Schema::create('feats', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description');
    $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
    $table->unsignedSmallInteger('source_page')->nullable();
    $table->timestamps();
});
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && php artisan test --filter=CharacterOptionsMigrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_races_table.php
git add database/migrations/*_create_backgrounds_table.php
git add database/migrations/*_create_feats_table.php
git add tests/Unit/Migrations/CharacterOptionsMigrationTest.php
git commit -m "feat: add races, backgrounds, feats table migrations"
```

---

## Task 11: Create Eloquent Models - Spell

**Files:**
- Create: `app/Models/Spell.php`
- Create: `tests/Unit/Models/SpellModelTest.php`

**Step 1: Write test for Spell model**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_has_fillable_attributes(): void
    {
        $spell = new Spell([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'duration' => 'Instantaneous',
            'description' => 'A bright streak...',
        ]);

        $this->assertEquals('Fireball', $spell->name);
        $this->assertEquals(3, $spell->level);
    }

    public function test_spell_belongs_to_school(): void
    {
        $spell = Spell::factory()->create();
        $this->assertInstanceOf(SpellSchool::class, $spell->school);
    }

    public function test_spell_belongs_to_source_book(): void
    {
        $spell = Spell::factory()->create();
        $this->assertInstanceOf(SourceBook::class, $spell->sourceBook);
    }

    public function test_spell_slug_is_generated_from_name(): void
    {
        $spell = new Spell(['name' => 'Acid Splash']);
        $spell->generateSlug();
        $this->assertEquals('acid-splash', $spell->slug);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellModelTest`
Expected: FAIL (model doesn't exist)

**Step 3: Create Spell model**

```php
docker-compose exec php php artisan make:model Spell
```

Edit `app/Models/Spell.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Spell extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'level',
        'school_id',
        'is_ritual',
        'casting_time',
        'range',
        'duration',
        'has_verbal_component',
        'has_somatic_component',
        'has_material_component',
        'material_description',
        'material_cost_gp',
        'material_consumed',
        'description',
        'source_book_id',
        'source_page',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_ritual' => 'boolean',
        'has_verbal_component' => 'boolean',
        'has_somatic_component' => 'boolean',
        'has_material_component' => 'boolean',
        'material_cost_gp' => 'integer',
        'material_consumed' => 'boolean',
        'source_page' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class, 'school_id');
    }

    public function sourceBook(): BelongsTo
    {
        return $this->belongsTo(SourceBook::class, 'source_book_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassSpell::class);
    }

    public function generateSlug(): void
    {
        $this->slug = Str::slug($this->name);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($spell) {
            if (empty($spell->slug)) {
                $spell->generateSlug();
            }
        });
    }
}
```

**Step 4: Create supporting models**

```php
docker-compose exec php php artisan make:model SpellSchool
docker-compose exec php php artisan make:model SourceBook
docker-compose exec php php artisan make:model SpellEffect
docker-compose exec php php artisan make:model ClassSpell
```

Create minimal implementations for each (relationships only for now).

**Step 5: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellModelTest`
Expected: PASS (may need to create factories - skip for now if needed)

**Step 6: Commit**

```bash
git add app/Models/Spell.php
git add app/Models/SpellSchool.php
git add app/Models/SourceBook.php
git add app/Models/SpellEffect.php
git add app/Models/ClassSpell.php
git add tests/Unit/Models/SpellModelTest.php
git commit -m "feat: add Spell model with relationships"
```

---

## Task 12: Create XML Parser Service - Spell Parser

**Files:**
- Create: `app/Services/Parsers/SpellXmlParser.php`
- Create: `tests/Unit/Services/SpellXmlParserTest.php`

**Step 1: Write test for SpellXmlParser**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\SpellXmlParser;
use Tests\TestCase;

class SpellXmlParserTest extends TestCase
{
    private SpellXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpellXmlParser();
    }

    public function test_parses_simple_spell_xml(): void
    {
        $xml = <<<XML
        <spell>
            <name>Acid Splash</name>
            <level>0</level>
            <school>C</school>
            <time>1 action</time>
            <range>60 feet</range>
            <components>V, S</components>
            <duration>Instantaneous</duration>
            <classes>Sorcerer, Wizard</classes>
            <text>You hurl a bubble of acid.</text>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertEquals('Acid Splash', $result['name']);
        $this->assertEquals(0, $result['level']);
        $this->assertEquals('C', $result['school_code']);
        $this->assertEquals('1 action', $result['casting_time']);
        $this->assertEquals('60 feet', $result['range']);
        $this->assertTrue($result['has_verbal_component']);
        $this->assertTrue($result['has_somatic_component']);
        $this->assertFalse($result['has_material_component']);
    }

    public function test_parses_spell_with_material_components(): void
    {
        $xml = <<<XML
        <spell>
            <name>Identify</name>
            <components>V, S, M (a pearl worth at least 100 gp and an owl feather)</components>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertTrue($result['has_material_component']);
        $this->assertStringContainsString('pearl', $result['material_description']);
        $this->assertEquals(100, $result['material_cost_gp']);
    }

    public function test_parses_ritual_spell(): void
    {
        $xml = <<<XML
        <spell>
            <name>Alarm</name>
            <ritual>YES</ritual>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $this->assertTrue($result['is_ritual']);
    }

    public function test_parses_spell_classes(): void
    {
        $xml = <<<XML
        <spell>
            <name>Fireball</name>
            <classes>Fighter (Eldritch Knight), Sorcerer, Wizard</classes>
        </spell>
        XML;

        $spellElement = simplexml_load_string($xml);
        $result = $this->parser->parseSpellElement($spellElement);

        $classes = $result['classes'];
        $this->assertCount(3, $classes);
        $this->assertEquals('Sorcerer', $classes[1]['class_name']);
        $this->assertEquals('Eldritch Knight', $classes[0]['subclass_name']);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellXmlParserTest`
Expected: FAIL

**Step 3: Implement SpellXmlParser**

Create: `app/Services/Parsers/SpellXmlParser.php`

```php
<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class SpellXmlParser
{
    public function parseSpellElement(SimpleXMLElement $spellElement): array
    {
        $data = [
            'name' => (string) $spellElement->name,
            'level' => (int) $spellElement->level,
            'school_code' => (string) $spellElement->school,
            'is_ritual' => strtoupper((string) $spellElement->ritual) === 'YES',
            'casting_time' => (string) $spellElement->time,
            'range' => (string) $spellElement->range,
            'duration' => (string) $spellElement->duration,
            'description' => trim((string) $spellElement->text),
        ];

        // Parse components
        $components = $this->parseComponents((string) $spellElement->components);
        $data = array_merge($data, $components);

        // Parse classes
        $data['classes'] = $this->parseClasses((string) $spellElement->classes);

        // Extract source info
        $sourceInfo = $this->extractSourceInfo($data['description']);
        $data['source_code'] = $sourceInfo['code'];
        $data['source_page'] = $sourceInfo['page'];

        return $data;
    }

    private function parseComponents(string $componentsString): array
    {
        $result = [
            'has_verbal_component' => false,
            'has_somatic_component' => false,
            'has_material_component' => false,
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
        ];

        if (empty($componentsString)) {
            return $result;
        }

        $result['has_verbal_component'] = str_contains($componentsString, 'V');
        $result['has_somatic_component'] = str_contains($componentsString, 'S');
        $result['has_material_component'] = str_contains($componentsString, 'M');

        // Extract material description
        if (preg_match('/M \((.*?)\)/', $componentsString, $matches)) {
            $result['material_description'] = $matches[1];

            // Extract cost
            if (preg_match('/worth (?:at least )?(\d+(?:,\d+)*) gp/', $matches[1], $costMatches)) {
                $result['material_cost_gp'] = (int) str_replace(',', '', $costMatches[1]);
            }
        }

        return $result;
    }

    private function parseClasses(string $classesString): array
    {
        if (empty($classesString)) {
            return [];
        }

        // Remove "School: X, " prefix if present
        $classesString = preg_replace('/^School:\s*[^,]+,\s*/', '', $classesString);

        $classes = [];
        $parts = array_map('trim', explode(',', $classesString));

        foreach ($parts as $part) {
            if (preg_match('/^(.+?)\s*\((.+?)\)$/', $part, $matches)) {
                // Class with subclass: "Fighter (Eldritch Knight)"
                $classes[] = [
                    'class_name' => trim($matches[1]),
                    'subclass_name' => trim($matches[2]),
                ];
            } else {
                // Just class name
                $classes[] = [
                    'class_name' => trim($part),
                    'subclass_name' => null,
                ];
            }
        }

        return $classes;
    }

    private function extractSourceInfo(string $description): array
    {
        $result = [
            'code' => null,
            'page' => null,
        ];

        // Match "Source: Player's Handbook (2014) p. 211"
        if (preg_match('/Source:\s*(.+?)\s*\(?\d{4}\)?\s*p\.\s*(\d+)/i', $description, $matches)) {
            // Map common book names to codes
            $bookMap = [
                "Player's Handbook" => 'PHB',
                "Dungeon Master's Guide" => 'DMG',
                'Monster Manual' => 'MM',
                "Xanathar's Guide to Everything" => 'XGE',
                "Tasha's Cauldron of Everything" => 'TCE',
            ];

            $bookName = trim($matches[1]);
            $result['code'] = $bookMap[$bookName] ?? 'PHB';
            $result['page'] = (int) $matches[2];
        }

        return $result;
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellXmlParserTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Parsers/SpellXmlParser.php
git add tests/Unit/Services/SpellXmlParserTest.php
git commit -m "feat: add SpellXmlParser with component and class parsing"
```

---

## Task 13: Create Importer Service - Spell Importer

**Files:**
- Create: `app/Services/Importers/SpellImporter.php`
- Create: `tests/Unit/Services/SpellImporterTest.php`

**Step 1: Write test for SpellImporter**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use App\Services\Importers\SpellImporter;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImporterTest extends TestCase
{
    use RefreshDatabase;

    private SpellImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new SpellImporter(new SpellXmlParser());
    }

    public function test_imports_spell_from_parsed_data(): void
    {
        $data = [
            'name' => 'Test Spell',
            'level' => 1,
            'school_code' => 'A',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'duration' => 'Instantaneous',
            'has_verbal_component' => true,
            'has_somatic_component' => false,
            'has_material_component' => false,
            'description' => 'Test description',
            'source_code' => 'PHB',
            'source_page' => 100,
            'classes' => [],
        ];

        $spell = $this->importer->importFromParsedData($data);

        $this->assertInstanceOf(Spell::class, $spell);
        $this->assertEquals('Test Spell', $spell->name);
        $this->assertEquals(1, $spell->level);
        $this->assertTrue($spell->has_verbal_component);
    }

    public function test_creates_class_spell_associations(): void
    {
        $data = [
            'name' => 'Fireball',
            'level' => 3,
            'school_code' => 'EV',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'duration' => 'Instantaneous',
            'has_verbal_component' => true,
            'has_somatic_component' => true,
            'has_material_component' => true,
            'description' => 'A bright streak...',
            'source_code' => 'PHB',
            'source_page' => 241,
            'classes' => [
                ['class_name' => 'Wizard', 'subclass_name' => null],
                ['class_name' => 'Sorcerer', 'subclass_name' => null],
            ],
        ];

        $spell = $this->importer->importFromParsedData($data);

        $this->assertCount(2, $spell->classes);
    }

    public function test_updates_existing_spell_instead_of_duplicating(): void
    {
        $spell = Spell::factory()->create(['name' => 'Existing Spell']);
        $initialId = $spell->id;

        $data = [
            'name' => 'Existing Spell',
            'level' => 2,
            'school_code' => 'C',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '60 feet',
            'duration' => '1 minute',
            'has_verbal_component' => true,
            'has_somatic_component' => true,
            'has_material_component' => false,
            'description' => 'Updated description',
            'source_code' => 'PHB',
            'source_page' => 200,
            'classes' => [],
        ];

        $updatedSpell = $this->importer->importFromParsedData($data);

        $this->assertEquals($initialId, $updatedSpell->id);
        $this->assertEquals('Updated description', $updatedSpell->description);
        $this->assertEquals(1, Spell::count());
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellImporterTest`
Expected: FAIL

**Step 3: Implement SpellImporter**

Create: `app/Services/Importers/SpellImporter.php`

```php
<?php

namespace App\Services\Importers;

use App\Models\ClassSpell;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Support\Facades\DB;

class SpellImporter
{
    public function __construct(
        private SpellXmlParser $parser
    ) {}

    public function importFromParsedData(array $data): Spell
    {
        return DB::transaction(function () use ($data) {
            // Get school ID
            $school = SpellSchool::where('code', $data['school_code'])->first();
            if (!$school) {
                throw new \Exception("Unknown spell school: {$data['school_code']}");
            }

            // Get source book ID
            $sourceBook = SourceBook::where('code', $data['source_code'])->first();
            if (!$sourceBook) {
                throw new \Exception("Unknown source book: {$data['source_code']}");
            }

            // Create or update spell
            $spell = Spell::updateOrCreate(
                ['name' => $data['name']],
                [
                    'level' => $data['level'],
                    'school_id' => $school->id,
                    'is_ritual' => $data['is_ritual'],
                    'casting_time' => $data['casting_time'],
                    'range' => $data['range'],
                    'duration' => $data['duration'],
                    'has_verbal_component' => $data['has_verbal_component'],
                    'has_somatic_component' => $data['has_somatic_component'],
                    'has_material_component' => $data['has_material_component'],
                    'material_description' => $data['material_description'] ?? null,
                    'material_cost_gp' => $data['material_cost_gp'] ?? null,
                    'material_consumed' => $data['material_consumed'] ?? false,
                    'description' => $data['description'],
                    'source_book_id' => $sourceBook->id,
                    'source_page' => $data['source_page'],
                ]
            );

            // Clear existing class associations
            ClassSpell::where('spell_id', $spell->id)->delete();

            // Create new class associations
            foreach ($data['classes'] as $classData) {
                ClassSpell::create([
                    'spell_id' => $spell->id,
                    'class_name' => $classData['class_name'],
                    'subclass_name' => $classData['subclass_name'],
                ]);
            }

            return $spell->fresh();
        });
    }

    public function importFromXmlFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $xml = simplexml_load_file($filePath);
        $count = 0;

        foreach ($xml->spell as $spellElement) {
            $data = $this->parser->parseSpellElement($spellElement);
            $this->importFromParsedData($data);
            $count++;
        }

        return $count;
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellImporterTest`
Expected: PASS (may need factories first)

**Step 5: Commit**

```bash
git add app/Services/Importers/SpellImporter.php
git add tests/Unit/Services/SpellImporterTest.php
git commit -m "feat: add SpellImporter with upsert logic"
```

---

## Task 14: Create Artisan Command - Import Spells

**Files:**
- Create: `app/Console/Commands/ImportSpellsCommand.php`
- Create: `tests/Feature/Commands/ImportSpellsCommandTest.php`

**Step 1: Write test for import command**

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportSpellsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_spells_from_xml_file(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->expectsOutput('Importing spells from: import-files/spells-phb.xml')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Spell::count());
    }

    public function test_shows_error_for_missing_file(): void
    {
        $this->artisan('import:spells', ['file' => 'nonexistent.xml'])
            ->expectsOutput('Error: File not found')
            ->assertFailed();
    }

    public function test_shows_progress_during_import(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->expectsOutput('Import complete!')
            ->assertSuccessful();
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=ImportSpellsCommandTest`
Expected: FAIL

**Step 3: Create Artisan command**

```php
docker-compose exec php php artisan make:command ImportSpellsCommand
```

Edit `app/Console/Commands/ImportSpellsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellImporter;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Console\Command;

class ImportSpellsCommand extends Command
{
    protected $signature = 'import:spells {file : Path to XML file}';
    protected $description = 'Import D&D 5e spells from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        $this->info("Importing spells from: {$filePath}");

        if (!file_exists($filePath)) {
            $this->error('Error: File not found');
            return self::FAILURE;
        }

        try {
            $importer = new SpellImporter(new SpellXmlParser());
            $count = $importer->importFromXmlFile($filePath);

            $this->info("Import complete!");
            $this->info("Imported {$count} spells.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=ImportSpellsCommandTest`
Expected: PASS

**Step 5: Test command manually**

Run: `docker-compose exec php php artisan import:spells import-files/spells-phb.xml`
Expected: Spells imported successfully

**Step 6: Commit**

```bash
git add app/Console/Commands/ImportSpellsCommand.php
git add tests/Feature/Commands/ImportSpellsCommandTest.php
git commit -m "feat: add import:spells Artisan command"
```

---

## Task 15: Create Item Parser and Importer

**Files:**
- Create: `app/Services/Parsers/ItemXmlParser.php`
- Create: `app/Services/Importers/ItemImporter.php`
- Create: `tests/Unit/Services/ItemXmlParserTest.php`
- Create: `tests/Unit/Services/ItemImporterTest.php`

**Step 1: Write test for ItemXmlParser**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\Parsers\ItemXmlParser;
use Tests\TestCase;

class ItemXmlParserTest extends TestCase
{
    private ItemXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser();
    }

    public function test_parses_basic_item(): void
    {
        $xml = <<<XML
        <item>
            <name>Longsword</name>
            <type>M</type>
            <weight>3</weight>
            <value>15.0</value>
            <text>A martial weapon.</text>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertEquals('Longsword', $result['name']);
        $this->assertEquals('M', $result['type_code']);
        $this->assertEquals(3.0, $result['weight_lbs']);
        $this->assertEquals(15.0, $result['value_gp']);
    }

    public function test_parses_item_properties(): void
    {
        $xml = <<<XML
        <item>
            <name>Rapier</name>
            <property>F,V</property>
        </item>
        XML;

        $itemElement = simplexml_load_string($xml);
        $result = $this->parser->parseItemElement($itemElement);

        $this->assertContains('F', $result['properties']);
        $this->assertContains('V', $result['properties']);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=ItemXmlParserTest`
Expected: FAIL

**Step 3: Implement ItemXmlParser**

```php
<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class ItemXmlParser
{
    public function parseItemElement(SimpleXMLElement $itemElement): array
    {
        $data = [
            'name' => (string) $itemElement->name,
            'type_code' => (string) $itemElement->type,
            'weight_lbs' => !empty($itemElement->weight) ? (float) $itemElement->weight : null,
            'value_gp' => !empty($itemElement->value) ? (float) $itemElement->value : null,
            'description' => trim((string) $itemElement->text),
        ];

        // Parse properties
        $data['properties'] = [];
        if (!empty($itemElement->property)) {
            $data['properties'] = array_map('trim', explode(',', (string) $itemElement->property));
        }

        // Extract source info
        $sourceInfo = $this->extractSourceInfo($data['description']);
        $data['source_code'] = $sourceInfo['code'];
        $data['source_page'] = $sourceInfo['page'];

        return $data;
    }

    private function extractSourceInfo(string $description): array
    {
        $result = ['code' => 'PHB', 'page' => null];

        if (preg_match('/Source:\s*(.+?)\s*\(?\d{4}\)?\s*p\.\s*(\d+)/i', $description, $matches)) {
            $bookMap = [
                "Player's Handbook" => 'PHB',
                "Dungeon Master's Guide" => 'DMG',
            ];
            $bookName = trim($matches[1]);
            $result['code'] = $bookMap[$bookName] ?? 'PHB';
            $result['page'] = (int) $matches[2];
        }

        return $result;
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=ItemXmlParserTest`
Expected: PASS

**Step 5: Create ItemImporter (similar to SpellImporter)**

Follow the same TDD pattern for `ItemImporter`.

**Step 6: Commit**

```bash
git add app/Services/Parsers/ItemXmlParser.php
git add app/Services/Importers/ItemImporter.php
git add tests/Unit/Services/ItemXmlParserTest.php
git add tests/Unit/Services/ItemImporterTest.php
git commit -m "feat: add item parser and importer"
```

---

## Task 16: Create Race, Background, Feat Parsers and Importers

**Files:**
- Create parsers and importers for: Race, Background, Feat
- Create tests for each

**Step 1: Write test for RaceXmlParser**

Follow TDD pattern from previous tasks.

```php
public function test_parses_race_with_ability_modifiers(): void
{
    $xml = <<<XML
    <race>
        <name>Dragonborn</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Str +2, Cha +1</ability>
    </race>
    XML;

    $result = $this->parser->parseRaceElement(simplexml_load_string($xml));

    $this->assertEquals('Dragonborn', $result['name']);
    $this->assertCount(2, $result['modifiers']);
}
```

**Step 2: Implement parsers**

Create RaceXmlParser, BackgroundXmlParser, FeatXmlParser following SpellXmlParser pattern.

**Step 3: Implement importers**

Create RaceImporter, BackgroundImporter, FeatImporter following SpellImporter pattern.

**Step 4: Create Artisan commands**

```bash
docker-compose exec php php artisan make:command ImportRacesCommand
docker-compose exec php php artisan make:command ImportBackgroundsCommand
docker-compose exec php php artisan make:command ImportFeatsCommand
```

**Step 5: Run tests**

Run: `docker-compose exec php php artisan test --filter=Race`
Run: `docker-compose exec php php artisan test --filter=Background`
Run: `docker-compose exec php php artisan test --filter=Feat`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Services/Parsers/*XmlParser.php
git add app/Services/Importers/*Importer.php
git add app/Console/Commands/Import*Command.php
git add tests/
git commit -m "feat: add race, background, feat parsers and importers"
```

---

## Task 17: Create Master Import Command

**Files:**
- Create: `app/Console/Commands/ImportAllCommand.php`
- Create: `tests/Feature/Commands/ImportAllCommandTest.php`

**Step 1: Write test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\Spell;
use App\Models\Item;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAllCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_all_content_from_directory(): void
    {
        $this->artisan('import:all', ['directory' => 'import-files'])
            ->expectsOutput('Import complete!')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Spell::count());
        $this->assertGreaterThan(0, Item::count());
        $this->assertGreaterThan(0, Race::count());
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=ImportAllCommandTest`
Expected: FAIL

**Step 3: Implement command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportAllCommand extends Command
{
    protected $signature = 'import:all {directory=import-files : Directory containing XML files}';
    protected $description = 'Import all D&D 5e content from XML files';

    public function handle(): int
    {
        $directory = $this->argument('directory');

        $this->info("Importing all content from: {$directory}");

        // Import in dependency order
        $importCommands = [
            ['command' => 'import:spells', 'pattern' => 'spells-*.xml'],
            ['command' => 'import:items', 'pattern' => 'items-*.xml'],
            ['command' => 'import:races', 'pattern' => 'races-*.xml'],
            ['command' => 'import:backgrounds', 'pattern' => 'backgrounds-*.xml'],
            ['command' => 'import:feats', 'pattern' => 'feats-*.xml'],
        ];

        foreach ($importCommands as $import) {
            $files = glob("{$directory}/{$import['pattern']}");

            foreach ($files as $file) {
                $this->call($import['command'], ['file' => $file]);
            }
        }

        $this->info('Import complete!');
        return self::SUCCESS;
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=ImportAllCommandTest`
Expected: PASS

**Step 5: Test manually**

Run: `docker-compose exec php php artisan import:all import-files`
Expected: All content imported

**Step 6: Commit**

```bash
git add app/Console/Commands/ImportAllCommand.php
git add tests/Feature/Commands/ImportAllCommandTest.php
git commit -m "feat: add import:all master command"
```

---

## Task 18: Add Model Factories for Testing

**Files:**
- Create: `database/factories/SpellFactory.php`
- Create: `database/factories/ItemFactory.php`
- Create: `database/factories/RaceFactory.php`

**Step 1: Create Spell factory**

```php
docker-compose exec php php artisan make:factory SpellFactory
```

```php
<?php

namespace Database\Factories;

use App\Models\SpellSchool;
use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpellFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->slug,
            'level' => $this->faker->numberBetween(0, 9),
            'school_id' => SpellSchool::factory(),
            'is_ritual' => $this->faker->boolean(20),
            'casting_time' => '1 action',
            'range' => '60 feet',
            'duration' => 'Instantaneous',
            'has_verbal_component' => $this->faker->boolean(80),
            'has_somatic_component' => $this->faker->boolean(70),
            'has_material_component' => $this->faker->boolean(40),
            'description' => $this->faker->paragraph,
            'source_book_id' => SourceBook::factory(),
            'source_page' => $this->faker->numberBetween(100, 300),
        ];
    }
}
```

**Step 2: Create factories for other models**

Follow same pattern for Item, Race, Background, Feat factories.

**Step 3: Commit**

```bash
git add database/factories/
git commit -m "feat: add model factories for testing"
```

---

## Task 19: Add README and Documentation

**Files:**
- Create: `README.md`
- Update: `docs/plans/2025-11-17-dnd-compendium-database-design.md`

**Step 1: Write README**

```markdown
# D&D 5e XML Importer

Laravel-based command-line tool for importing D&D 5th Edition content from XML files into a relational database. Runs in a Dockerized environment with PHP-FPM, Nginx, and MySQL.

## Features

- Import spells, items, races, backgrounds, feats from XML
- Parse complex data: spell components, material costs, class associations
- Polymorphic relationships for traits, modifiers, proficiencies
- Comprehensive test coverage
- Docker-based development environment

## Prerequisites

- Docker Desktop or Docker Engine
- Docker Compose 2.x
- Git

## Installation

1. Clone the repository and navigate to the project directory

2. Build and start Docker containers:
\`\`\`bash
docker-compose build
docker-compose up -d
\`\`\`

3. Install dependencies:
\`\`\`bash
docker-compose exec php composer install
\`\`\`

4. Set up environment:
\`\`\`bash
cp .env.example .env
docker-compose exec php php artisan key:generate
\`\`\`

5. Run migrations:
\`\`\`bash
docker-compose exec php php artisan migrate
\`\`\`

## Usage

### Import all content:
\`\`\`bash
docker-compose exec php php artisan import:all import-files
\`\`\`

### Import specific content type:
\`\`\`bash
docker-compose exec php php artisan import:spells import-files/spells-phb.xml
docker-compose exec php php artisan import:items import-files/items-base-phb.xml
docker-compose exec php php artisan import:races import-files/races-phb.xml
\`\`\`

### Using the helper script:
\`\`\`bash
./docker-exec.sh php artisan import:all import-files
./docker-exec.sh php artisan test
\`\`\`

## Testing

Run all tests:
\`\`\`bash
docker-compose exec php php artisan test
\`\`\`

Run specific test suite:
\`\`\`bash
docker-compose exec php php artisan test --filter=SpellImporter
\`\`\`

## Docker Services

- **PHP-FPM**: PHP 8.4 with required extensions
- **Nginx**: Web server (accessible at http://localhost:8080)
- **MySQL**: Database server (port 3306)

## Database Schema

See \`docs/plans/2025-11-17-dnd-compendium-database-design.md\` for detailed schema documentation.

## Stopping the Environment

\`\`\`bash
docker-compose down
\`\`\`

To remove volumes as well:
\`\`\`bash
docker-compose down -v
\`\`\`
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add README with usage instructions"
```

---

## Task 20: Integration Testing

**Files:**
- Create: `tests/Feature/Integration/SpellImportIntegrationTest.php`

**Step 1: Write integration test**

```php
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
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellImportIntegrationTest`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/Integration/
git commit -m "test: add integration tests with real XML files"
```

---

## Task 21: Final Verification and Cleanup

**Step 1: Run all tests**

Run: `docker-compose exec php php artisan test`
Expected: All tests pass

**Step 2: Run full import**

Run: `docker-compose exec php php artisan migrate:fresh && docker-compose exec php php artisan import:all import-files`
Expected: All content imported successfully

**Step 3: Verify database contents**

Run: `docker-compose exec php php artisan tinker`
```php
Spell::count()
Item::count()
Race::count()
Background::count()
Feat::count()
```
Expected: Reasonable counts for each entity type

**Step 4: Check code style**

Run: `./vendor/bin/pint` (if Laravel Pint is installed)
Expected: Code formatted according to Laravel standards

**Step 5: Final commit**

```bash
git add .
git commit -m "chore: final verification and cleanup"
git push
```

---

## Post-Implementation

After completing all tasks:

1. **Tag release**: `git tag v1.0.0 && git push --tags`
2. **Update project board**: Mark all tasks complete
3. **Document known issues**: Add any discovered edge cases to GitHub issues
4. **Plan next iteration**: Consider API endpoints, search functionality, or web UI

---

## Notes for Engineer

### Docker Environment
- All commands run inside Docker containers via `docker-compose exec php`
- Use the helper script `docker-exec.sh` for convenience
- Database runs in separate MySQL container (no local MySQL needed)
- Nginx serves Laravel on port 8080
- Volumes ensure code changes sync immediately

### DRY Principles Applied
- Shared XML parsing logic in base parser class
- Reusable importer pattern across all content types
- Polymorphic tables reduce schema duplication

### YAGNI Principles Applied
- No premature optimization (indexes only on FK and common queries)
- No web UI (command-line only as specified)
- No complex relationships until needed (e.g., spell prerequisites)

### TDD Throughout
- Every feature starts with failing test
- Tests verify behavior before implementation
- Integration tests validate real-world usage
- All tests run inside Docker environment

### Commit Frequency
- Commit after each task completion (~21 commits total)
- Each commit is atomic and reversible
- Clear commit messages following conventional commits

### Skills Referenced
- @superpowers:test-driven-development - Used throughout for TDD workflow
- @superpowers:verification-before-completion - Used in Task 21
- @superpowers:systematic-debugging - Use if tests fail unexpectedly
