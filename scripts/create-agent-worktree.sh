#!/bin/bash
# =============================================================================
# Create an isolated Git worktree for parallel Claude Code agent work
# =============================================================================
#
# Usage:
#   ./scripts/create-agent-worktree.sh <instance-number> <branch-name>
#
# Examples:
#   ./scripts/create-agent-worktree.sh 1 feature/issue-130-spell-filters
#   ./scripts/create-agent-worktree.sh 2 feature/issue-131-monster-api
#
# This creates:
#   ../backend-agent-<N>/  - Git worktree with isolated environment
#
# Port assignments (main uses 8080):
#   Instance 1: Nginx=8091
#   Instance 2: Nginx=8092
#   Instance N: Nginx=809N
#
# Each instance uses:
#   - Shared MySQL (different database: dnd_compendium_N)
#   - Shared Meilisearch (different prefix: wtN_)
#   - Shared Redis (different DB number: N)
#
# IMPORTANT: Shared services must be running from the main backend directory!
#   cd /path/to/backend && docker compose up -d mysql meilisearch redis
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PARENT_DIR="$(dirname "$PROJECT_ROOT")"

# Validate arguments
if [ $# -lt 2 ]; then
    echo -e "${RED}Error: Missing arguments${NC}"
    echo ""
    echo "Usage: $0 <instance-number> <branch-name>"
    echo ""
    echo "Examples:"
    echo "  $0 1 feature/issue-130-spell-filters"
    echo "  $0 2 feature/issue-131-monster-api"
    echo ""
    echo "Instance numbers and their ports:"
    echo "  1: Nginx=8091, DB=dnd_compendium_1, Scout=wt1_, Redis DB=1"
    echo "  2: Nginx=8092, DB=dnd_compendium_2, Scout=wt2_, Redis DB=2"
    echo "  3: Nginx=8093, DB=dnd_compendium_3, Scout=wt3_, Redis DB=3"
    echo "  4: Nginx=8094, DB=dnd_compendium_4, Scout=wt4_, Redis DB=4"
    echo "  5: Nginx=8095, DB=dnd_compendium_5, Scout=wt5_, Redis DB=5"
    exit 1
fi

INSTANCE_ID="$1"
BRANCH_NAME="$2"

# Validate instance number (1-5)
if ! [[ "$INSTANCE_ID" =~ ^[1-5]$ ]]; then
    echo -e "${RED}Error: Instance number must be 1-5 (got: $INSTANCE_ID)${NC}"
    echo "Only 5 worktree environments are supported (pre-created databases)."
    exit 1
fi

# Calculate ports
NGINX_PORT=$((8090 + INSTANCE_ID))

# Database and prefix settings
DB_DATABASE="dnd_compendium_${INSTANCE_ID}"
SCOUT_PREFIX="wt${INSTANCE_ID}_"
REDIS_DB="$INSTANCE_ID"
CACHE_PREFIX="dnd_wt${INSTANCE_ID}_"

WORKTREE_DIR="$PARENT_DIR/backend-agent-$INSTANCE_ID"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Creating Agent Worktree Environment${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Instance:    ${GREEN}$INSTANCE_ID${NC}"
echo -e "Branch:      ${GREEN}$BRANCH_NAME${NC}"
echo -e "Directory:   ${GREEN}$WORKTREE_DIR${NC}"
echo ""
echo -e "${YELLOW}Port assignments:${NC}"
echo -e "  Nginx:     ${GREEN}http://localhost:$NGINX_PORT${NC}"
echo ""
echo -e "${YELLOW}Service isolation:${NC}"
echo -e "  Database:  ${GREEN}$DB_DATABASE${NC}"
echo -e "  Scout:     ${GREEN}$SCOUT_PREFIX${NC}"
echo -e "  Redis DB:  ${GREEN}$REDIS_DB${NC}"
echo ""

# Check if worktree directory already exists
if [ -d "$WORKTREE_DIR" ]; then
    echo -e "${RED}Error: Directory already exists: $WORKTREE_DIR${NC}"
    echo ""
    echo "To remove an existing worktree, run:"
    echo "  ./scripts/remove-agent-worktree.sh $INSTANCE_ID"
    exit 1
fi

# Check if shared services are running
echo -e "${BLUE}Checking shared services...${NC}"
cd "$PROJECT_ROOT"

if ! docker compose ps mysql 2>/dev/null | grep -qE "(Up|running)"; then
    echo -e "${YELLOW}Warning: MySQL is not running in main backend.${NC}"
    echo -e "Start shared services first: ${GREEN}docker compose up -d mysql meilisearch redis${NC}"
    echo ""
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
else
    echo -e "${GREEN}✓ Shared services are running${NC}"
fi

# Check if branch exists, create if not
if git show-ref --verify --quiet "refs/heads/$BRANCH_NAME"; then
    echo -e "${GREEN}✓ Branch '$BRANCH_NAME' exists${NC}"
else
    echo -e "${YELLOW}Branch '$BRANCH_NAME' does not exist. Creating from current HEAD...${NC}"
    git branch "$BRANCH_NAME"
    echo -e "${GREEN}✓ Created branch '$BRANCH_NAME'${NC}"
fi

# Create worktree
echo ""
echo -e "${BLUE}Creating git worktree...${NC}"
git worktree add "$WORKTREE_DIR" "$BRANCH_NAME"
echo -e "${GREEN}✓ Worktree created${NC}"

# Generate docker-compose.override.yml from template
echo ""
echo -e "${BLUE}Generating docker-compose.override.yml...${NC}"
sed -e "s/{{INSTANCE_ID}}/$INSTANCE_ID/g" \
    -e "s/{{NGINX_PORT}}/$NGINX_PORT/g" \
    "$PROJECT_ROOT/docker-compose.override.template.yml" > "$WORKTREE_DIR/docker-compose.override.yml"
echo -e "${GREEN}✓ Override file created${NC}"

# Generate .env file with worktree-specific settings
echo ""
echo -e "${BLUE}Generating .env file...${NC}"
if [ -f "$PROJECT_ROOT/.env" ]; then
    # Copy base .env and override specific values
    cp "$PROJECT_ROOT/.env" "$WORKTREE_DIR/.env"

    # Update database name
    sed -i.bak "s/^DB_DATABASE=.*/DB_DATABASE=$DB_DATABASE/" "$WORKTREE_DIR/.env"

    # Update or add SCOUT_PREFIX
    if grep -q "^SCOUT_PREFIX=" "$WORKTREE_DIR/.env"; then
        sed -i.bak "s/^SCOUT_PREFIX=.*/SCOUT_PREFIX=$SCOUT_PREFIX/" "$WORKTREE_DIR/.env"
    else
        echo "SCOUT_PREFIX=$SCOUT_PREFIX" >> "$WORKTREE_DIR/.env"
    fi

    # Update or add REDIS_DB
    if grep -q "^REDIS_DB=" "$WORKTREE_DIR/.env"; then
        sed -i.bak "s/^REDIS_DB=.*/REDIS_DB=$REDIS_DB/" "$WORKTREE_DIR/.env"
    else
        echo "REDIS_DB=$REDIS_DB" >> "$WORKTREE_DIR/.env"
    fi

    # Update CACHE_PREFIX
    sed -i.bak "s/^CACHE_PREFIX=.*/CACHE_PREFIX=$CACHE_PREFIX/" "$WORKTREE_DIR/.env"

    # Clean up backup files
    rm -f "$WORKTREE_DIR/.env.bak"

    echo -e "${GREEN}✓ .env file created with worktree-specific settings${NC}"
else
    echo -e "${YELLOW}Warning: No .env file in main backend. Copy .env.example and configure manually.${NC}"
fi

# No convenience scripts - use docker commands directly:
#   docker exec loh_backend_php_N php artisan ...
#   docker exec loh_backend_php_N ./vendor/bin/pest ...
#   docker exec loh_backend_php_N ./vendor/bin/pint ...

# Summary
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Worktree Ready!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Directory: ${BLUE}$WORKTREE_DIR${NC}"
echo -e "Container: ${BLUE}loh_backend_php_$INSTANCE_ID${NC}"
echo ""
echo -e "${YELLOW}Quick Start:${NC}"
echo ""
echo "  cd $WORKTREE_DIR"
echo ""
echo "  # Start containers (requires main backend network running)"
echo "  docker compose -f docker-compose.yml -f docker-compose.override.yml up -d php nginx"
echo ""
echo "  # Install dependencies"
echo "  docker exec loh_backend_php_$INSTANCE_ID composer install"
echo ""
echo "  # Run migrations & import"
echo "  docker exec loh_backend_php_$INSTANCE_ID php artisan migrate"
echo "  docker exec loh_backend_php_$INSTANCE_ID php artisan import:all"
echo ""
echo "  # Run tests"
echo "  docker exec loh_backend_php_$INSTANCE_ID ./vendor/bin/pest"
echo ""
echo -e "${YELLOW}Access URLs:${NC}"
echo "  API:       http://localhost:$NGINX_PORT"
echo "  API Docs:  http://localhost:$NGINX_PORT/docs/api"
echo ""
echo -e "${YELLOW}Service Isolation:${NC}"
echo "  Database:      $DB_DATABASE"
echo "  Scout Prefix:  $SCOUT_PREFIX"
echo "  Redis DB:      $REDIS_DB"
echo ""
echo -e "${YELLOW}To remove this worktree later:${NC}"
echo "  ./scripts/remove-agent-worktree.sh $INSTANCE_ID"
