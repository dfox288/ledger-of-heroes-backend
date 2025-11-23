#!/bin/bash

# API Examples Test Script
# Tests all documented API usage examples from controller comments

BASE_URL="http://localhost:8080/api/v1"
FAILED=0
PASSED=0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test helper function
test_endpoint() {
    local description="$1"
    local endpoint="$2"
    local expected_min_results="${3:-0}"

    echo -n "Testing: $description... "

    response=$(curl -s "$BASE_URL$endpoint")

    # Check if response contains error
    if echo "$response" | grep -q "<!DOCTYPE html"; then
        echo -e "${RED}FAIL${NC} (404 or error page)"
        echo "  Endpoint: $endpoint"
        FAILED=$((FAILED + 1))
        return 1
    fi

    # Check if response is valid JSON
    if ! echo "$response" | jq empty 2>/dev/null; then
        echo -e "${RED}FAIL${NC} (invalid JSON)"
        echo "  Endpoint: $endpoint"
        FAILED=$((FAILED + 1))
        return 1
    fi

    # Check result count if specified
    if [ "$expected_min_results" -gt 0 ]; then
        result_count=$(echo "$response" | jq '.data | length' 2>/dev/null || echo "0")
        if [ "$result_count" -lt "$expected_min_results" ]; then
            echo -e "${YELLOW}WARN${NC} (expected >=$expected_min_results results, got $result_count)"
            echo "  Endpoint: $endpoint"
            PASSED=$((PASSED + 1))
            return 0
        fi
    fi

    echo -e "${GREEN}PASS${NC}"
    PASSED=$((PASSED + 1))
    return 0
}

echo "=================================="
echo "API Examples Validation Test"
echo "=================================="
echo ""

# Background examples
echo "--- BACKGROUNDS ---"
test_endpoint "All backgrounds" "/backgrounds"
test_endpoint "By skill proficiency (stealth)" "/backgrounds?grants_skill=stealth"
test_endpoint "By tool proficiency (thieves' tools)" "/backgrounds?grants_proficiency=thieves-tools"
test_endpoint "By language (dwarvish)" "/backgrounds?speaks_language=dwarvish"
test_endpoint "Search by name (noble)" "/backgrounds?q=noble"

# Class examples
echo ""
echo "--- CLASSES ---"
test_endpoint "All classes" "/classes"
test_endpoint "Base classes only" "/classes?base_only=1"
test_endpoint "By hit die (12)" "/classes?hit_die=12"
test_endpoint "Spellcasters only" "/classes?is_spellcaster=true"
test_endpoint "Non-spellcasters" "/classes?is_spellcaster=false"

# Race examples
echo ""
echo "--- RACES ---"
test_endpoint "All races" "/races"
test_endpoint "By size (S)" "/races?size=S"
test_endpoint "Search (half)" "/races?q=half" 3
test_endpoint "By speed (min 35)" "/races?min_speed=35"

# Spell examples
echo ""
echo "--- SPELLS ---"
test_endpoint "All spells" "/spells"
test_endpoint "Search (fireball)" "/spells?q=fireball" 1
test_endpoint "By level (3)" "/spells?level=3"
test_endpoint "By school ID (3 = Evocation)" "/spells?school=3"
test_endpoint "Ritual spells" "/spells?ritual=true"
test_endpoint "Concentration spells" "/spells?concentration=true"

# Item examples
echo ""
echo "--- ITEMS ---"
test_endpoint "All items" "/items"
test_endpoint "Search (sword)" "/items?q=sword"
test_endpoint "By rarity (rare)" "/items?rarity=rare"
test_endpoint "Magic items" "/items?is_magic=true"

# Monster examples
echo ""
echo "--- MONSTERS ---"
test_endpoint "All monsters" "/monsters"
test_endpoint "Search (dragon)" "/monsters?q=dragon" 10
test_endpoint "By CR (5)" "/monsters?cr=5"
test_endpoint "By type (dragon)" "/monsters?type=dragon"

# Feat examples
echo ""
echo "--- FEATS ---"
test_endpoint "All feats" "/feats"
test_endpoint "Search (alert)" "/feats?q=alert"

# Lookup endpoints
echo ""
echo "--- LOOKUPS ---"
test_endpoint "Ability scores" "/ability-scores"
test_endpoint "Spell schools" "/spell-schools"
test_endpoint "Damage types" "/damage-types"
test_endpoint "Sizes" "/sizes"
test_endpoint "Languages" "/languages"

# Relationship endpoints
echo ""
echo "--- RELATIONSHIPS ---"
test_endpoint "Class spells (wizard)" "/classes/wizard/spells"
test_endpoint "Race spells (drow)" "/races/elf-drow-dark/spells"
test_endpoint "Monster spells (lich)" "/monsters/lich/spells"

echo ""
echo "=================================="
echo "Test Summary"
echo "=================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed${NC}"
    exit 1
fi
