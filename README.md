# D&D 5e Compendium API

Laravel-based REST API for D&D 5th Edition content with XML import capability. Full-featured API with search, filtering, and comprehensive test coverage.

## 🎯 Project Status

**Current Version:** 1.0.0 (Production Ready)
- ✅ **7 Entity APIs Complete:** Spells, Items, Classes, Feats, Backgrounds, Races, Monsters (full REST APIs)
- ✅ **1,489 Tests Passing** (7,704 assertions) - 99.7% pass rate
- ✅ **124 Filter Operator Tests** (2,462 assertions) - 100% coverage across all entities
- ✅ **Performance Optimized:** Redis caching (93.7% improvement, 16.6x faster, <0.2ms response time)
- ✅ **3,600+ Documents Indexed** in Meilisearch for fast, typo-tolerant search
- ✅ **598 Monsters Imported** with type-specific parsing strategies
- ✅ **OpenAPI Documentation** auto-generated via Scramble
- ✅ **Docker-based** development environment (no local PHP/MySQL required)

## ✨ Features

### Entity Management
- **7 Main Entities:** Spells (477), Classes (131), Monsters (598), Items (516), Feats, Backgrounds, Races
- **Dual ID/Slug Routing:** SEO-friendly URLs (`/api/v1/monsters/ancient-red-dragon`)
- **Polymorphic Relationships:** Traits, modifiers, proficiencies, sources, prerequisites
- **Universal Tag System:** Spatie Tags on all entities for categorization

### Search & Filtering
- **Global Search:** Multi-entity search across all 7 entity types
- **Meilisearch Integration:** Typo-tolerant search (<50ms response time)
- **Advanced Filtering:** Meilisearch filter expressions (level, CR, rarity, etc.)
- **Scout Fallback:** Graceful degradation to database when search unavailable

### API Features
- **RESTful Design:** Consistent patterns across all endpoints
- **Pagination:** Configurable page size (max 100 per page)
- **Sorting:** Multiple sort fields with asc/desc
- **Form Request Validation:** Type-safe requests with auto-generated OpenAPI docs
- **Resource Serialization:** Consistent JSON structure via Laravel Resources

### Import System
- **One-Command Import:** `import:all` handles 60+ XML files in correct order
- **9 Importers:** Spells, Classes, Races, Items, Backgrounds, Feats, Monsters, Spell Class Mappings, Master
- **Strategy Pattern:** Type-specific parsing for Items (5 strategies) and Monsters (5 strategies)
- **21 Reusable Traits:** DRY code for common import operations
- **Import Logging:** Detailed strategy statistics and warnings

### Performance & Code Quality
- **Redis Caching:** Lookup + entity endpoints (93.7% improvement, <0.2ms response time)
- **Test-Driven Development:** 1,273 tests with 99.8% pass rate
- **Type Safety:** PHP 8.4 strict types, Form Requests, DTOs
- **Custom Exceptions:** Domain-specific exceptions with proper HTTP status codes
- **Code Formatting:** Laravel Pint for consistent style
- **OpenAPI Docs:** Auto-generated via Scramble at `/docs/api`

## 🚀 Quick Start

### Prerequisites
- Docker Desktop or Docker Engine
- Docker Compose 2.x
- Git

### Installation

1. **Clone and navigate:**
```bash
git clone <repository-url>
cd importer
```

2. **Build and start containers:**
```bash
docker compose up -d
```

3. **Install dependencies:**
```bash
docker compose exec php composer install
```

4. **Setup environment:**
```bash
cp .env.example .env
docker compose exec php php artisan key:generate
```

5. **Import all data (recommended):**
```bash
docker compose exec php php artisan import:all
```

This single command will:
- Run `migrate:fresh --seed` (fresh database with lookup data)
- Import all 60+ XML files in correct order
- Configure Meilisearch search indexes
- Display import statistics

**Duration:** ~2-5 minutes

## 📚 API Endpoints

**Base URL:** `http://localhost:8080/api/v1`

### Main Entities

#### Spells
```bash
GET /api/v1/spells                    # List with pagination
GET /api/v1/spells/{id|slug}          # Show by ID or slug
GET /api/v1/spells?level=3            # Filter by level
GET /api/v1/spells?school=EVO         # Filter by school code
GET /api/v1/spells?concentration=1    # Filter by concentration
GET /api/v1/spells?q=fire             # Search by name/description
GET /api/v1/spells?filter=level >= 3 AND school_code = EV  # Meilisearch filters
```

#### Monsters
```bash
GET /api/v1/monsters                      # List with pagination
GET /api/v1/monsters/{id|slug}            # Show by ID or slug
GET /api/v1/monsters?challenge_rating=5   # Filter by exact CR
GET /api/v1/monsters?min_cr=5&max_cr=10   # Filter by CR range
GET /api/v1/monsters?type=dragon          # Filter by creature type
GET /api/v1/monsters?size=L               # Filter by size
GET /api/v1/monsters?alignment=evil       # Filter by alignment
GET /api/v1/monsters?q=dragon             # Search by name
```

#### Items
```bash
GET /api/v1/items                     # List with pagination
GET /api/v1/items/{id|slug}           # Show by ID or slug
GET /api/v1/items?rarity=legendary    # Filter by rarity
GET /api/v1/items?type=weapon         # Filter by item type
GET /api/v1/items?q=sword             # Search by name/description
GET /api/v1/items/{id}/spells         # Get spells from charged items
```

#### Classes
```bash
GET /api/v1/classes                   # List with pagination
GET /api/v1/classes/{id|slug}         # Show by ID or slug
GET /api/v1/classes/{id}/spells       # Class spell list
GET /api/v1/classes?hit_die=d10       # Filter by hit die
```

#### Feats
```bash
GET /api/v1/feats                     # List with pagination
GET /api/v1/feats/{id|slug}           # Show by ID or slug
GET /api/v1/feats?q=mobile            # Search by name/description
```

#### Backgrounds
```bash
GET /api/v1/backgrounds               # List with pagination
GET /api/v1/backgrounds/{id|slug}     # Show by ID or slug
GET /api/v1/backgrounds?q=acolyte     # Search by name
```

#### Races
```bash
GET /api/v1/races                     # List with pagination
GET /api/v1/races/{id|slug}           # Show by ID or slug
GET /api/v1/races?size=M              # Filter by size
```

### Lookup Tables

```bash
GET /api/v1/sources              # D&D sourcebooks
GET /api/v1/spell-schools        # 8 schools of magic
GET /api/v1/damage-types         # 13 damage types
GET /api/v1/conditions           # 15 D&D conditions
GET /api/v1/languages            # 30 languages
GET /api/v1/proficiency-types    # 82 weapon/armor/tool types
GET /api/v1/sizes                # Creature sizes
GET /api/v1/ability-scores       # STR, DEX, CON, INT, WIS, CHA
GET /api/v1/skills               # 18 D&D skills
GET /api/v1/item-types           # Item categories
GET /api/v1/item-properties      # Weapon properties
```

### Global Search

```bash
GET /api/v1/search?q=fire&types[]=spell&types[]=item
```

**Supported Types:** `spell`, `class`, `monster`, `item`, `feat`, `background`, `race`

**Features:**
- Cross-entity search
- Typo-tolerance
- Relevance ranking
- <50ms response time

## 🔍 Advanced Filtering

### Meilisearch Filter Syntax

All entity endpoints support `filter` parameter with Meilisearch expressions:

**Comparison Operators:**
```bash
?filter=level >= 3
?filter=challenge_rating > 10
?filter=armor_class <= 15
```

**Logical Operators:**
```bash
?filter=level >= 3 AND level <= 5
?filter=school_code = EV OR school_code = C
?filter=(type = dragon OR type = undead) AND challenge_rating >= 10
```

**String Matching:**
```bash
?filter=alignment = "lawful good"
?filter=type = dragon
```

See `docs/MEILISEARCH-FILTERS.md` for complete syntax documentation.

## 🧪 Testing

### Run all tests:
```bash
docker compose exec php php artisan test
```

### Run specific suites:
```bash
docker compose exec php php artisan test --testsuite=Feature
docker compose exec php php artisan test --testsuite=Unit
docker compose exec php php artisan test --filter=MonsterApi
```

### With coverage:
```bash
docker compose exec php php artisan test --coverage-text
```

**Current Status:** 1,489 tests passing (7,704 assertions) in ~240s (includes Meilisearch indexing)

## 📥 Import System

### One-Command Import (Recommended)

```bash
# Import EVERYTHING (fresh DB + all entities)
docker compose exec php php artisan import:all

# Options
docker compose exec php php artisan import:all --skip-migrate    # Keep existing DB
docker compose exec php php artisan import:all --only=monsters   # Import only monsters
docker compose exec php php artisan import:all --skip-search     # Skip search config
```

### Individual Importers

```bash
docker compose exec php php artisan import:classes <file>
docker compose exec php php artisan import:spells <file>
docker compose exec php php artisan import:spell-class-mappings <file>
docker compose exec php php artisan import:races <file>
docker compose exec php php artisan import:items <file>
docker compose exec php php artisan import:backgrounds <file>
docker compose exec php php artisan import:feats <file>
docker compose exec php php artisan import:monsters <file>
```

**Import Order Matters:**
1. Classes first (required by spells for pivot table)
2. Main spell files
3. Additive spell files (class mappings)
4. Other entities

## 🐳 Docker Services

- **php** - PHP 8.4-FPM (Laravel application)
- **nginx** - Nginx 1.25 (web server on port 8080)
- **mysql** - MySQL 8.0 (database)
- **meilisearch** - Meilisearch 1.6 (search engine on port 7700)

### Useful Commands

```bash
# View logs
docker compose logs -f php
docker compose logs -f nginx

# Run artisan commands
docker compose exec php php artisan tinker
docker compose exec php php artisan migrate:fresh --seed

# Run composer
docker compose exec php composer install
docker compose exec php composer update

# Code formatting
docker compose exec php ./vendor/bin/pint

# Database
docker compose exec mysql mysql -u root -p
```

## 📖 Documentation

### Project Documentation
- `CLAUDE.md` - Development guide for Claude Code
- `CHANGELOG.md` - Version history
- `docs/SEARCH.md` - Search system architecture
- `docs/MEILISEARCH-FILTERS.md` - Advanced filter syntax
- `docs/recommendations/` - Design decisions and strategies

### Session Handovers
- `docs/SESSION-HANDOVER-2025-11-25-FILTER-OPERATOR-PHASE-2-COMPLETE.md` - Filter operator testing complete (LATEST)
- `docs/LATEST-HANDOVER.md` - Symlink to latest handover
- Archived handovers available in `docs/archive/handovers-2025-11/`

### Performance Documentation
- `docs/PERFORMANCE-BENCHMARKS.md` - Phase 2 + 3 caching results

### OpenAPI Documentation
Auto-generated API documentation: `http://localhost:8080/docs/api`

## 🏗️ Architecture

### Tech Stack
- **Backend:** Laravel 12.x, PHP 8.4
- **Database:** MySQL 8.0
- **Caching:** Redis 7 (93.7% performance improvement)
- **Search:** Meilisearch 1.6 + Laravel Scout
- **Testing:** PHPUnit 11+
- **Containerization:** Docker + Docker Compose
- **API Docs:** Scramble (OpenAPI 3)
- **Code Quality:** Laravel Pint (PSR-12)

### Design Patterns
- **Repository Pattern:** Services layer for business logic
- **Strategy Pattern:** Type-specific parsing (Items, Monsters)
- **Resource Pattern:** Consistent API serialization
- **DTO Pattern:** Type-safe request data transfer
- **Form Request Pattern:** Validation + OpenAPI auto-generation

### Database Structure
- **32 Models:** Entities + polymorphic relationships
- **64 Migrations:** Complete schema with indexes
- **12 Seeders:** Lookup tables (sources, schools, languages, etc.)
- **Performance:** 17 database indexes + Redis caching (94% query reduction)

## 📊 Data Overview

### Imported Data
- **Spells:** 477 (from 9 XML files)
- **Classes:** 131 (35 XML files)
- **Monsters:** 598 (9 bestiary files)
- **Items:** 516 (25 XML files)
- **Feats:** ~100 (4 XML files)
- **Backgrounds:** ~40 (4 XML files)
- **Races:** ~30 (5 XML files)

### Search Index
- **Total Documents:** 3,600+
- **Index Size:** ~3MB
- **Search Latency:** <50ms p95

## 🔜 Roadmap

### Core Features (Complete) ✅
- ✅ All 7 entity REST APIs
- ✅ Performance optimization (Redis caching, 93.7% faster)
- ✅ Database indexing
- ✅ Meilisearch integration with filter-only queries
- ✅ Comprehensive filter operator testing (124 tests, 100% coverage)

### Optional Enhancements
1. **API Documentation Enhancements** (2-3 hours) - Standardize controller PHPDoc, add filter examples
2. **Character Builder API** (8-12 hours) - Character creation, leveling, spell selection
3. **Performance Optimizations** (2-4 hours) - Additional indexing, query optimization
4. **Advanced Filter Testing** (4-6 hours) - Compound filters, performance benchmarks
5. **Frontend Application** (20-40 hours) - Inertia.js + Vue or Next.js + React
6. **Rate Limiting** - Per-IP throttling middleware

## 🤝 Contributing

1. Follow TDD approach (write tests first)
2. Use PHP 8.4 attributes for tests (`#[Test]`)
3. Format code with Pint before committing
4. Update Form Requests when adding filters
5. Maintain OpenAPI documentation

## 📝 License

This project is for educational purposes. D&D 5e content is property of Wizards of the Coast.

## 🙏 Acknowledgments

- Laravel Framework
- Meilisearch for fast search
- Fight Club 5e for XML format
- Scramble for OpenAPI generation
- Spatie Laravel-Tags package
