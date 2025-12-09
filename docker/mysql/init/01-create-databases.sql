-- =============================================================================
-- Create databases for main environment and worktree environments
-- =============================================================================
-- This script runs on first MySQL container startup (when volume is empty).
-- It creates all databases upfront so worktrees can use them immediately.
-- =============================================================================

-- Main environment database
CREATE DATABASE IF NOT EXISTS `dnd_compendium`;

-- Worktree databases (1-5)
CREATE DATABASE IF NOT EXISTS `dnd_compendium_1`;
CREATE DATABASE IF NOT EXISTS `dnd_compendium_2`;
CREATE DATABASE IF NOT EXISTS `dnd_compendium_3`;
CREATE DATABASE IF NOT EXISTS `dnd_compendium_4`;
CREATE DATABASE IF NOT EXISTS `dnd_compendium_5`;

-- Grant privileges to the application user for all databases
GRANT ALL PRIVILEGES ON `dnd_compendium`.* TO 'dnd_user'@'%';
GRANT ALL PRIVILEGES ON `dnd_compendium_1`.* TO 'dnd_user'@'%';
GRANT ALL PRIVILEGES ON `dnd_compendium_2`.* TO 'dnd_user'@'%';
GRANT ALL PRIVILEGES ON `dnd_compendium_3`.* TO 'dnd_user'@'%';
GRANT ALL PRIVILEGES ON `dnd_compendium_4`.* TO 'dnd_user'@'%';
GRANT ALL PRIVILEGES ON `dnd_compendium_5`.* TO 'dnd_user'@'%';

FLUSH PRIVILEGES;
