-- Fix ASI Duplicate Modifiers
-- Run: docker compose exec mysql mysql -uroot -ppassword dnd_compendium < docs/fix-asi-duplicates.sql
-- Or: docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "$(cat docs/fix-asi-duplicates.sql)"

-- Show current duplicate status
SELECT
    c.name AS class_name,
    m.level,
    COUNT(*) AS duplicate_count
FROM entity_modifiers m
JOIN classes c ON c.id = m.reference_id AND m.reference_type = 'App\\Models\\CharacterClass'
WHERE c.parent_class_id IS NULL
  AND m.modifier_category = 'ability_score'
GROUP BY c.id, c.name, m.level
HAVING COUNT(*) > 1
ORDER BY c.name, m.level;

-- Backup affected records (optional - for safety)
-- CREATE TABLE entity_modifiers_backup_20251125 AS
-- SELECT * FROM entity_modifiers
-- WHERE reference_type = 'App\\Models\\CharacterClass'
--   AND modifier_category = 'ability_score';

-- Fix: Remove all duplicate ASI modifiers, keeping oldest (lowest ID)
DELETE m1
FROM entity_modifiers m1
INNER JOIN entity_modifiers m2
WHERE m1.id > m2.id
  AND m1.reference_type = 'App\\Models\\CharacterClass'
  AND m1.reference_id = m2.reference_id
  AND m1.modifier_category = 'ability_score'
  AND m1.level = m2.level;

-- Verify fix
SELECT
    c.name AS class_name,
    COUNT(m.id) AS asi_count,
    GROUP_CONCAT(m.level ORDER BY m.level SEPARATOR ', ') AS levels
FROM classes c
LEFT JOIN entity_modifiers m
    ON m.reference_type = 'App\\Models\\CharacterClass'
    AND m.reference_id = c.id
    AND m.modifier_category = 'ability_score'
WHERE c.parent_class_id IS NULL
GROUP BY c.id, c.name
ORDER BY c.name;
