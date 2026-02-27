--
-- Database: `InstiKit`
--

-- --------------------------------------------------------

--
-- InstiKit 5.3.0 post update queries
--

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `galleries` ADD `audience` JSON NULL DEFAULT NULL AFTER `date`;
ALTER TABLE `site_blocks` ADD `type` VARCHAR(50) NULL DEFAULT NULL AFTER `menu_id`;

UPDATE migrations SET migration = '2023_07_31_130312_create_stock_item_copies_table' WHERE migration = '2025_07_03_100052_create_stock_item_copies_table';

ALTER TABLE `approval_types` ADD `event` VARCHAR(100) NULL DEFAULT NULL AFTER `category`;
ALTER TABLE `approval_types` ADD `priority_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `team_id`, ADD INDEX `priority_id` (`priority_id`);
ALTER TABLE `approval_types` ADD CONSTRAINT `approval_types_priority_id_foreign` FOREIGN KEY (`priority_id`) REFERENCES `options`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;