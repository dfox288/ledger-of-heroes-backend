# D&D 5e XML Importer

Laravel-based command-line tool for importing D&D 5th Edition content from XML files into a relational database. Runs in a Dockerized environment with PHP-FPM, Nginx, and MySQL.

## Features

- Import spells, items, races, backgrounds, feats from XML
- Parse complex data: spell components, material costs, class associations
- Polymorphic relationships for traits, modifiers, proficiencies
- Comprehensive test coverage with PHPUnit
- Docker-based development environment (no local PHP/MySQL required)
- Support for Fight Club 5e XML format

## Prerequisites

- Docker Desktop or Docker Engine
- Docker Compose 2.x
- Git

## Installation

1. Clone the repository and navigate to the project directory:
```bash
cd /Users/dfox/Development/dnd/importer
```

2. Build and start Docker containers:
```bash
docker-compose build
docker-compose up -d
```

3. Install dependencies:
```bash
docker-compose exec php composer install
```

4. Set up environment:
```bash
cp .env.example .env
docker-compose exec php php artisan key:generate
```

5. Run migrations:
```bash
docker-compose exec php php artisan migrate
```

## Usage

### Import all content:
```bash
docker-compose exec php php artisan import:all import-files
```

### Import specific content type:
```bash
# Import spells
docker-compose exec php php artisan import:spells import-files/spells-phb.xml

# Import items
docker-compose exec php php artisan import:items import-files/items-base-phb.xml

# Import races
docker-compose exec php php artisan import:races import-files/races-phb.xml

# Import backgrounds
docker-compose exec php php artisan import:backgrounds import-files/backgrounds-phb.xml

# Import feats
docker-compose exec php php artisan import:feats import-files/feats-phb.xml
```

### Using the helper script:
```bash
./docker-exec.sh php artisan import:all import-files
./docker-exec.sh php artisan test
```

## Testing

Run all tests:
```bash
docker-compose exec php php artisan test
```

Run specific test suite:
```bash
docker-compose exec php php artisan test --filter=SpellImporter
docker-compose exec php php artisan test --filter=ItemParser
docker-compose exec php php artisan test --testsuite=Feature
docker-compose exec php php artisan test --testsuite=Unit
```

Run tests with coverage:
```bash
docker-compose exec php php artisan test --coverage
```

## Docker Services

- **PHP-FPM**: PHP 8.4 with required extensions (pdo_mysql, mbstring, etc.)
- **Nginx**: Web server (accessible at http://localhost:8080)
- **MySQL**: Database server (port 3306)
  - Database: `dnd_compendium`
  - User: `dnd_user`
  - Password: `dnd_password`

## Database Schema

The database follows a normalized relational design with:

### Core Content Tables
- `spells` - Spell data with parsed components
- `items` - Equipment and magic items
- `races` - Player character races
- `backgrounds` - Character backgrounds
- `feats` - Character feats

### Lookup Tables
- `spell_schools` - Schools of magic (Abjuration, Conjuration, etc.)
- `damage_types` - Damage types (fire, cold, slashing, etc.)
- `item_types` - Item categories (weapon, armor, gear, etc.)
- `item_rarities` - Item rarity levels (common, uncommon, etc.)
- `sizes` - Creature sizes (Tiny, Small, Medium, etc.)
- `source_books` - D&D sourcebooks (PHB, XGE, etc.)

### Relationship Tables
- `class_spell` - Spell-to-class associations
- `spell_effects` - Spell damage/healing effects

### Polymorphic Tables
- `traits` - Character traits (works with races, backgrounds, feats)
- `modifiers` - Ability score modifiers (works with races, feats, items)
- `proficiencies` - Skill/weapon/armor proficiencies (works with races, backgrounds)

See `docs/plans/2025-11-17-dnd-compendium-database-design.md` for detailed schema documentation.

## Available Artisan Commands

| Command | Description |
|---------|-------------|
| `import:all {directory}` | Import all XML files from directory |
| `import:spells {file}` | Import spells from XML file |
| `import:items {file}` | Import items from XML file |
| `import:races {file}` | Import races from XML file |
| `import:backgrounds {file}` | Import backgrounds from XML file |
| `import:feats {file}` | Import feats from XML file |

## Project Structure

```
app/
├── Console/Commands/      # Artisan import commands
├── Models/                # Eloquent models
└── Services/
    ├── Importers/         # Import logic for each content type
    └── Parsers/           # XML parsing for each content type

database/
├── factories/             # Model factories for testing
└── migrations/            # Database schema migrations

import-files/              # XML source files
├── spells-phb.xml
├── items-base-phb.xml
├── races-phb.xml
├── backgrounds-phb.xml
├── feats-phb.xml
└── class-druid-xge.xml

tests/
├── Feature/               # Feature/integration tests
│   ├── Commands/          # Command tests
│   └── Integration/       # Full import integration tests
└── Unit/                  # Unit tests
    ├── Migrations/        # Migration tests
    ├── Models/            # Model tests
    └── Services/          # Parser/importer tests
```

## Development

### Running Commands Inside Docker

All PHP and Artisan commands should be run inside the Docker container:

```bash
docker-compose exec php php artisan [command]
docker-compose exec php composer [command]
docker-compose exec php php [script]
```

### Accessing the Database

Connect to MySQL from host machine:
```bash
mysql -h 127.0.0.1 -P 3306 -u dnd_user -p
# Password: dnd_password
```

Connect to MySQL from inside container:
```bash
docker-compose exec mysql mysql -u dnd_user -p dnd_compendium
```

### Viewing Logs

```bash
# PHP-FPM logs
docker-compose logs -f php

# Nginx logs
docker-compose logs -f nginx

# MySQL logs
docker-compose logs -f mysql
```

## Stopping the Environment

Stop containers (preserves data):
```bash
docker-compose down
```

Stop containers and remove volumes (deletes database):
```bash
docker-compose down -v
```

Restart containers:
```bash
docker-compose restart
```

## Troubleshooting

### Containers won't start
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Database connection errors
- Ensure MySQL container is running: `docker-compose ps`
- Check .env file has correct database credentials
- Wait a few seconds for MySQL to fully start

### Permission errors
```bash
docker-compose exec php chown -R www-data:www-data storage bootstrap/cache
docker-compose exec php chmod -R 775 storage bootstrap/cache
```

## XML File Format

The importer expects XML files following the Fight Club 5e compendium format:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Fireball</name>
    <level>3</level>
    <school>EV</school>
    <time>1 action</time>
    <range>150 feet</range>
    <components>V, S, M (a tiny ball of bat guano and sulfur)</components>
    <duration>Instantaneous</duration>
    <classes>Sorcerer, Wizard</classes>
    <text>A bright streak flashes from your pointing finger...</text>
  </spell>
</compendium>
```

## Contributing

This is a personal project for importing D&D 5e content. Contributions are welcome!

1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Ensure all tests pass
5. Submit a pull request

## License

This project is open-source software licensed under the MIT license.

## Acknowledgments

- Built with Laravel 11.x
- XML format compatible with Fight Club 5e
- D&D 5e content is property of Wizards of the Coast
