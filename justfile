# =============================================================================
# Ledger of Heroes Backend - Justfile
# =============================================================================
# Run `just` or `just --list` to see all available commands
#
# Most commands run inside the Docker container. The project uses:
#   - php: PHP-FPM service (main application)
#   - nginx: Web server (port 8080)
#   - mysql: Database
#   - meilisearch: Search engine
#   - redis: Cache
# =============================================================================

# Default recipe shows help
default:
    @just --list

# Docker exec shorthand
dc := "docker compose exec php"

# =============================================================================
# DOCKER MANAGEMENT
# =============================================================================

# Start all services in detached mode
up:
    docker compose up -d

# Stop all services
down:
    docker compose down

# Restart all services
restart:
    docker compose restart

# View logs (optionally for specific service)
logs service="":
    docker compose logs -f {{ service }}

# Open shell in PHP container
shell:
    docker compose exec php bash

# Show container status
ps:
    docker compose ps

# Rebuild PHP container (after Dockerfile changes)
build:
    docker compose build php

# =============================================================================
# ARTISAN COMMANDS
# =============================================================================

# Run any artisan command
artisan *args:
    {{ dc }} php artisan {{ args }}

# Clear all caches (config, route, view, cache)
clear:
    {{ dc }} php artisan config:clear
    {{ dc }} php artisan route:clear
    {{ dc }} php artisan view:clear
    {{ dc }} php artisan cache:clear

# Optimize for production (cache config, routes, views)
optimize:
    {{ dc }} php artisan optimize

# Open tinker REPL
tinker:
    {{ dc }} php artisan tinker

# Run tinker with a PHP file
tinker-file file:
    docker compose exec -T php php artisan tinker < {{ file }}

# =============================================================================
# DATABASE
# =============================================================================

# Run pending migrations
migrate:
    {{ dc }} php artisan migrate

# Fresh migration (drop all tables and re-migrate)
migrate-fresh:
    {{ dc }} php artisan migrate:fresh

# Fresh migration with seed data
migrate-fresh-seed:
    {{ dc }} php artisan migrate:fresh --seed

# Rollback last migration batch
migrate-rollback:
    {{ dc }} php artisan migrate:rollback

# Show migration status
migrate-status:
    {{ dc }} php artisan migrate:status

# Seed the database
seed class="":
    {{ dc }} php artisan db:seed {{ if class != "" { "--class=" + class } else { "" } }}

# =============================================================================
# TESTING
# =============================================================================

# Run all tests (or specific suite/filter)
test *args:
    {{ dc }} ./vendor/bin/pest {{ args }}

# Run Unit-Pure suite (fastest, no DB)
test-pure *args:
    {{ dc }} ./vendor/bin/pest --testsuite=Unit-Pure {{ args }}

# Run Unit-DB suite (needs database)
test-unit *args:
    {{ dc }} ./vendor/bin/pest --testsuite=Unit-DB {{ args }}

# Run Feature-DB suite (API tests, no search)
test-feature *args:
    {{ dc }} ./vendor/bin/pest --testsuite=Feature-DB {{ args }}

# Run Feature-Search suite (requires Meilisearch fixture data)
test-search *args:
    {{ dc }} ./vendor/bin/pest --testsuite=Feature-Search {{ args }}

# Run Importers suite (XML import tests)
test-importers *args:
    {{ dc }} ./vendor/bin/pest --testsuite=Importers {{ args }}

# Run Health-Check suite (smoke tests)
test-health *args:
    {{ dc }} ./vendor/bin/pest --testsuite=Health-Check {{ args }}

# Run tests with coverage
test-coverage:
    {{ dc }} ./vendor/bin/pest --coverage

# Run tests with coverage enforcing minimum threshold
test-coverage-min min="80":
    {{ dc }} ./vendor/bin/pest --coverage --min={{ min }}

# Run specific test file
test-file file *args:
    {{ dc }} ./vendor/bin/pest {{ file }} {{ args }}

# =============================================================================
# CODE QUALITY
# =============================================================================

# Format code with Pint
pint *args:
    {{ dc }} ./vendor/bin/pint {{ args }}

# Check code style without fixing (dry run)
pint-check:
    {{ dc }} ./vendor/bin/pint --test

# Format specific path
pint-path path:
    {{ dc }} ./vendor/bin/pint {{ path }}

# =============================================================================
# COMPOSER
# =============================================================================

# Install dependencies
composer-install:
    {{ dc }} composer install

# Update dependencies
composer-update:
    {{ dc }} composer update

# Add a package
composer-require package:
    {{ dc }} composer require {{ package }}

# Add a dev package
composer-require-dev package:
    {{ dc }} composer require --dev {{ package }}

# Dump autoload
composer-dump:
    {{ dc }} composer dump-autoload

# =============================================================================
# IMPORT COMMANDS (XML Data Import)
# =============================================================================

# Import all data (fresh DB + seed + import all XML)
import-all:
    {{ dc }} php artisan import:all

# Import all with verbose output
import-all-verbose:
    {{ dc }} php artisan import:all -v

# Import all for test database (uses test_ prefix for Meilisearch)
import-test:
    docker compose exec -e SCOUT_PREFIX=test_ php php artisan import:all --env=testing

# Import sources only
import-sources:
    {{ dc }} php artisan import:sources

# Import spells
import-spells:
    {{ dc }} php artisan import:spells

# Import classes
import-classes:
    {{ dc }} php artisan import:classes

# Import races
import-races:
    {{ dc }} php artisan import:races

# Import backgrounds
import-backgrounds:
    {{ dc }} php artisan import:backgrounds

# Import feats
import-feats:
    {{ dc }} php artisan import:feats

# Import items
import-items:
    {{ dc }} php artisan import:items

# Import monsters
import-monsters:
    {{ dc }} php artisan import:monsters

# Import optional features
import-optional-features:
    {{ dc }} php artisan import:optional-features

# Link bonus cantrips (post-import processing)
import-link-cantrips:
    {{ dc }} php artisan import:link-bonus-cantrips

# =============================================================================
# SEARCH (Meilisearch)
# =============================================================================

# Re-index a model in Meilisearch
scout-import model:
    {{ dc }} php artisan scout:import "App\\Models\\{{ model }}"

# Flush a model from Meilisearch
scout-flush model:
    {{ dc }} php artisan scout:flush "App\\Models\\{{ model }}"

# =============================================================================
# CACHE WARMING
# =============================================================================

# Warm lookups cache
warm-lookups:
    {{ dc }} php artisan warm:lookups

# Warm entities cache
warm-entities:
    {{ dc }} php artisan warm:entities

# Warm all caches
warm-all: warm-lookups warm-entities

# =============================================================================
# QUEUE
# =============================================================================

# Start queue worker
queue-work:
    {{ dc }} php artisan queue:work

# Listen to queue (restarts on code changes)
queue-listen:
    {{ dc }} php artisan queue:listen

# Clear failed jobs
queue-clear:
    {{ dc }} php artisan queue:clear

# Retry failed jobs
queue-retry-all:
    {{ dc }} php artisan queue:retry all

# =============================================================================
# FIXTURES & TESTING COMMANDS
# =============================================================================

# Export fixture characters at milestone levels
fixtures-export *args:
    {{ dc }} php artisan fixtures:export-characters {{ args }}

# Import fixture characters for manual testing
fixtures-import:
    {{ dc }} php artisan fixtures:import-characters

# Extract fixture data from database
fixtures-extract:
    {{ dc }} php artisan fixtures:extract

# Test wizard flow with chaos testing
test-wizard *args:
    {{ dc }} php artisan test:wizard-flow {{ args }}

# Test level-up flow
test-levelup *args:
    {{ dc }} php artisan test:level-up-flow {{ args }}

# Test multiclass combinations
test-multiclass combinations:
    {{ dc }} php artisan test:multiclass-combinations --combinations="{{ combinations }}"

# Test all class combinations
test-all-classes *args:
    {{ dc }} php artisan test:all-class-combinations {{ args }}

# Test optional features
test-optional *args:
    {{ dc }} php artisan test:optional-features {{ args }}

# =============================================================================
# AUDIT COMMANDS
# =============================================================================

# Audit class/subclass matrix
audit-classes *args:
    {{ dc }} php artisan audit:class-subclass-matrix {{ args }}

# Audit optional feature counters
audit-optional *args:
    {{ dc }} php artisan audit:optional-feature-counters {{ args }}

# =============================================================================
# SCAFFOLDING
# =============================================================================

# Create a model (with migration, factory, seeder)
make-model name *args:
    {{ dc }} php artisan make:model {{ name }} {{ args }}

# Create a controller
make-controller name *args:
    {{ dc }} php artisan make:controller {{ name }} {{ args }}

# Create a migration
make-migration name *args:
    {{ dc }} php artisan make:migration {{ name }} {{ args }}

# Create a request
make-request name:
    {{ dc }} php artisan make:request {{ name }}

# Create a resource
make-resource name:
    {{ dc }} php artisan make:resource {{ name }}

# Create a factory
make-factory name *args:
    {{ dc }} php artisan make:factory {{ name }} {{ args }}

# Create a seeder
make-seeder name:
    {{ dc }} php artisan make:seeder {{ name }}

# Create a test
make-test name *args:
    {{ dc }} php artisan make:test {{ name }} {{ args }}

# Create a command
make-command name:
    {{ dc }} php artisan make:command {{ name }}

# =============================================================================
# DEVELOPMENT WORKFLOW
# =============================================================================

# Full setup: install deps, migrate, import data
setup: composer-install migrate import-all

# Quick dev reset: fresh migration + import
reset: migrate-fresh import-all

# Pre-commit check: format + test
check: pint test-pure test-unit test-feature

# Full validation: all test suites
validate: test-pure test-unit test-feature test-search

# =============================================================================
# WORKTREE MANAGEMENT (for parallel agent work)
# =============================================================================

# Create agent worktree
worktree-create instance branch:
    ./scripts/create-agent-worktree.sh {{ instance }} {{ branch }}

# Remove agent worktree
worktree-remove instance:
    ./scripts/remove-agent-worktree.sh {{ instance }}

# List agent worktrees
worktree-list:
    ./scripts/list-agent-worktrees.sh
