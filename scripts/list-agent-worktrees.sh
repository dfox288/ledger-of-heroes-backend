#!/bin/bash
# =============================================================================
# List all agent worktree environments
# =============================================================================
#
# Usage:
#   ./scripts/list-agent-worktrees.sh
#
# Shows:
#   - Active worktrees with their branches
#   - Port assignments
#   - Docker container status
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PARENT_DIR="$(dirname "$PROJECT_ROOT")"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Backend Agent Worktrees${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check shared services status
echo -e "${CYAN}Shared Services (from main backend):${NC}"
cd "$PROJECT_ROOT"

check_service() {
    local service=$1
    if docker compose ps "$service" 2>/dev/null | grep -qE "(Up|running)"; then
        echo -e "  $service: ${GREEN}running${NC}"
    else
        echo -e "  $service: ${RED}stopped${NC}"
    fi
}

check_service "mysql"
check_service "meilisearch"
check_service "redis"
echo ""

# Main environment
echo -e "${CYAN}Main Environment:${NC}"
echo -e "  Directory: ${BLUE}$PROJECT_ROOT${NC}"
echo -e "  Port:      ${GREEN}http://localhost:8080${NC}"
echo -e "  Database:  dnd_compendium"

# Check if main nginx is running
if docker compose ps nginx 2>/dev/null | grep -qE "(Up|running)"; then
    echo -e "  Status:    ${GREEN}running${NC}"
else
    echo -e "  Status:    ${YELLOW}stopped${NC}"
fi
echo ""

# List worktrees
echo -e "${CYAN}Agent Worktrees:${NC}"
echo ""

FOUND_WORKTREES=0

for i in 1 2 3 4 5; do
    WORKTREE_DIR="$PARENT_DIR/backend-agent-$i"
    NGINX_PORT=$((8090 + i))

    if [ -d "$WORKTREE_DIR" ]; then
        FOUND_WORKTREES=1

        # Get branch name
        BRANCH=$(git worktree list | grep "$WORKTREE_DIR" | awk '{print $3}' | tr -d '[]')

        echo -e "  ${GREEN}Instance $i:${NC}"
        echo -e "    Directory: ${BLUE}$WORKTREE_DIR${NC}"
        echo -e "    Branch:    $BRANCH"
        echo -e "    Port:      ${GREEN}http://localhost:$NGINX_PORT${NC}"
        echo -e "    Database:  dnd_compendium_$i"
        echo -e "    Scout:     wt${i}_"

        # Check container status
        if [ -f "$WORKTREE_DIR/docker-compose.override.yml" ]; then
            cd "$WORKTREE_DIR"
            if docker compose -f docker-compose.yml -f docker-compose.override.yml ps php 2>/dev/null | grep -qE "(Up|running)"; then
                echo -e "    Status:    ${GREEN}running${NC}"
            else
                echo -e "    Status:    ${YELLOW}stopped${NC}"
            fi
            cd "$PROJECT_ROOT"
        fi
        echo ""
    fi
done

if [ $FOUND_WORKTREES -eq 0 ]; then
    echo -e "  ${YELLOW}No agent worktrees found.${NC}"
    echo ""
    echo "  Create one with:"
    echo "    ./scripts/create-agent-worktree.sh 1 feature/my-branch"
    echo ""
fi

# Show available slots
echo -e "${CYAN}Available Slots:${NC}"
for i in 1 2 3 4 5; do
    WORKTREE_DIR="$PARENT_DIR/backend-agent-$i"
    if [ ! -d "$WORKTREE_DIR" ]; then
        NGINX_PORT=$((8090 + i))
        echo -e "  Instance $i: ${GREEN}available${NC} (port $NGINX_PORT)"
    fi
done
echo ""
