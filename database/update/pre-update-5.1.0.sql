--
-- Database: `InstiKit`
--

-- --------------------------------------------------------

--
-- InstiKit 5.1.0 post update queries
--

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `request_records` ADD `received_at` DATETIME NULL DEFAULT NULL AFTER `status`, ADD `processed_at` DATETIME NULL DEFAULT NULL AFTER `received_at`;

ALTER TABLE `templates` DROP INDEX `templates_code_unique`;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;