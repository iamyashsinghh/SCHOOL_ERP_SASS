--
-- Database: `InstiKit`
--

-- --------------------------------------------------------

--
-- InstiKit 5.2.0 post update queries
--

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `book_copies` CHANGE `rack_number` `rack_number` VARCHAR(100) NULL DEFAULT NULL, CHANGE `shelf_number` `shelf_number` VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE `vehicles` ADD `type_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `team_id`, ADD INDEX `type_id` (`type_id`);
ALTER TABLE `vehicles` ADD CONSTRAINT `vehicles_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `options`(`id`) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE `documents` ADD `number` VARCHAR(100) NULL DEFAULT NULL AFTER `uuid`;
ALTER TABLE `documents` ADD `issue_date` DATE NULL DEFAULT NULL AFTER `type_id`;

ALTER TABLE `stock_items` ADD `type` VARCHAR(50) NULL DEFAULT NULL AFTER `code`;
ALTER TABLE `stock_items` ADD `tracking_type` VARCHAR(50) NULL DEFAULT NULL AFTER `type`;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;