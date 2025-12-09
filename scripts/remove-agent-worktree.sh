#!/bin/bash
# =============================================================================
# Remove an agent worktree environment
# =============================================================================
#
# Usage:
#   ./scripts/remove-agent-worktree.sh <instance-number>
#
# Examples:
#   ./scripts/remove-agent-worktree.sh 1
#   ./scripts/remove-agent-worktree.sh 2
#
# This will:
#   1. Stop and remove Docker containers for the worktree
#   2. Remove the git worktree
#   3. Optionally delete the branch
#
# NOTE: This does NOT delete the database or Meilisearch indices.
#       To clean those up, use:
#         docker compose exec mysql mysql -uroot -proot -e "DROP DATABASE dnd_compendium_N"
#         (Meilisearch indices will be overwritten on next import)
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
if [ $# -lt 1 ]; then
    echo -e "${RED}Error: Missing instance number${NC}"
    echo ""
    echo "Usage: $0 <instance-number>"
    echo ""
    echo "Example:"
    echo "  $0 1"
    echo ""
    echo "To see existing worktrees:"
    echo "  ./scripts/list-agent-worktrees.sh"
    exit 1
fi

INSTANCE_ID="$1"

# Validate instance number
if ! [[ "$INSTANCE_ID" =~ ^[1-5]$ ]]; then
    echo -e "${RED}Error: Instance number must be 1-5 (got: $INSTANCE_ID)${NC}"
    exit 1
fi

WORKTREE_DIR="$PARENT_DIR/backend-agent-$INSTANCE_ID"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Removing Agent Worktree Environment${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Instance:  ${YELLOW}$INSTANCE_ID${NC}"
echo -e "Directory: ${YELLOW}$WORKTREE_DIR${NC}"
echo ""

# Check if worktree exists
if [ ! -d "$WORKTREE_DIR" ]; then
    echo -e "${RED}Error: Worktree directory does not exist: $WORKTREE_DIR${NC}"
    echo ""
    echo "To see existing worktrees:"
    echo "  ./scripts/list-agent-worktrees.sh"
    exit 1
fi

# Get the branch name before removing
cd "$PROJECT_ROOT"
BRANCH_NAME=$(git worktree list | grep "$WORKTREE_DIR" | awk '{print $3}' | tr -d '[]')

# Stop Docker containers if running
echo -e "${BLUE}Stopping Docker containers...${NC}"
if [ -f "$WORKTREE_DIR/docker-compose.override.yml" ]; then
    cd "$WORKTREE_DIR"
    docker compose -f docker-compose.yml -f docker-compose.override.yml down 2>/dev/null || true
    echo -e "${GREEN}✓ Containers stopped${NC}"
else
    echo -e "${YELLOW}No docker-compose.override.yml found, skipping container cleanup${NC}"
fi

# Remove the worktree
echo ""
echo -e "${BLUE}Removing git worktree...${NC}"
cd "$PROJECT_ROOT"
git worktree remove "$WORKTREE_DIR" --force
echo -e "${GREEN}✓ Worktree removed${NC}"

# Ask about branch deletion
if [ -n "$BRANCH_NAME" ] && [ "$BRANCH_NAME" != "main" ] && [ "$BRANCH_NAME" != "master" ]; then
    echo ""
    echo -e "${YELLOW}Branch '$BRANCH_NAME' still exists.${NC}"
    read -p "Delete the branch? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git branch -D "$BRANCH_NAME" 2>/dev/null || echo -e "${YELLOW}Could not delete branch (may have unmerged changes)${NC}"
        echo -e "${GREEN}✓ Branch deleted${NC}"
    else
        echo -e "${BLUE}Branch kept. Delete manually with: git branch -d $BRANCH_NAME${NC}"
    fi
fi

# Summary
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Worktree Removed Successfully${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Note:${NC} Database and Meilisearch data were NOT deleted."
echo "To clean up data:"
echo "  # Delete database"
echo "  docker compose exec mysql mysql -uroot -proot -e 'DROP DATABASE dnd_compendium_$INSTANCE_ID'"
echo ""
echo "  # Meilisearch indices (prefixed with wt${INSTANCE_ID}_) will be"
echo "  # overwritten on next import to this instance."
