#!/bin/bash

# verify-docs-structure.sh
# Verify docs/ directory structure is healthy

set -e

echo "🔍 Verifying docs/ structure..."
echo ""

ERRORS=0
WARNINGS=0

# Colors
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

# Check required files
echo "📄 Checking required files..."

if [ -f "docs/PROJECT-STATUS.md" ]; then
    echo -e "${GREEN}✓${NC} docs/PROJECT-STATUS.md exists"
else
    echo -e "${RED}✗${NC} docs/PROJECT-STATUS.md missing"
    ERRORS=$((ERRORS + 1))
fi

if [ -L "docs/LATEST-HANDOVER.md" ]; then
    TARGET=$(readlink docs/LATEST-HANDOVER.md)
    if [ -f "docs/$TARGET" ]; then
        echo -e "${GREEN}✓${NC} docs/LATEST-HANDOVER.md → $TARGET (valid)"
    else
        echo -e "${RED}✗${NC} docs/LATEST-HANDOVER.md → $TARGET (broken symlink)"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${YELLOW}⚠${NC} docs/LATEST-HANDOVER.md not a symlink"
    WARNINGS=$((WARNINGS + 1))
fi

if [ -f "docs/DND-FEATURES.md" ]; then
    echo -e "${GREEN}✓${NC} docs/DND-FEATURES.md exists"
else
    echo -e "${YELLOW}⚠${NC} docs/DND-FEATURES.md missing"
    WARNINGS=$((WARNINGS + 1))
fi

if [ -f "docs/README.md" ]; then
    echo -e "${GREEN}✓${NC} docs/README.md exists"
else
    echo -e "${YELLOW}⚠${NC} docs/README.md missing"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# Check for backup files in root
echo "🗑️  Checking for backup files in docs/ root..."
BACKUP_COUNT=$(find docs/ -maxdepth 1 -name "*.backup" -o -name "*.md.backup" 2>/dev/null | wc -l | tr -d ' ')

if [ "$BACKUP_COUNT" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No backup files in docs/ root"
else
    echo -e "${YELLOW}⚠${NC} Found $BACKUP_COUNT backup file(s) in docs/ root (should be archived)"
    find docs/ -maxdepth 1 \( -name "*.backup" -o -name "*.md.backup" \) -exec basename {} \;
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# Check for temp scripts in root
echo "🔧 Checking for temporary scripts..."
SCRIPT_COUNT=$(find docs/ -maxdepth 1 \( -name "*.py" -o -name "*.sql" -o -name "*.php" \) 2>/dev/null | wc -l | tr -d ' ')

if [ "$SCRIPT_COUNT" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No temporary scripts in docs/ root"
else
    echo -e "${YELLOW}⚠${NC} Found $SCRIPT_COUNT temporary script(s) in docs/ root"
    find docs/ -maxdepth 1 \( -name "*.py" -o -name "*.sql" -o -name "*.php" \) -exec basename {} \;
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# Check directory structure
echo "📁 Checking directory structure..."

for DIR in "plans" "handovers" "archive"; do
    if [ -d "docs/$DIR" ]; then
        COUNT=$(find "docs/$DIR" -name "*.md" 2>/dev/null | wc -l | tr -d ' ')
        echo -e "${GREEN}✓${NC} docs/$DIR/ exists ($COUNT files)"
    else
        echo -e "${YELLOW}⚠${NC} docs/$DIR/ missing (run /organize-docs to create)"
        WARNINGS=$((WARNINGS + 1))
    fi
done

echo ""

# Check for old handovers in root
echo "📋 Checking for old handovers in docs/ root..."
OLD_HANDOVERS=$(find docs/ -maxdepth 1 -name "SESSION-HANDOVER-*.md" -mtime +7 2>/dev/null | wc -l | tr -d ' ')

if [ "$OLD_HANDOVERS" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No old handovers in docs/ root"
else
    echo -e "${YELLOW}⚠${NC} Found $OLD_HANDOVERS handover(s) older than 7 days (should be archived)"
    find docs/ -maxdepth 1 -name "SESSION-HANDOVER-*.md" -mtime +7 -exec basename {} \;
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed!${NC}"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ $WARNINGS warning(s) found${NC}"
    echo ""
    echo "Run '/organize-docs' to clean up warnings"
    exit 0
else
    echo -e "${RED}✗ $ERRORS error(s), $WARNINGS warning(s) found${NC}"
    echo ""
    echo "Run '/organize-docs' to fix issues"
    exit 1
fi
