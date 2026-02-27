--
-- Database: `InstiKit`
--

-- --------------------------------------------------------

--
-- InstiKit 5.4.0 pre update queries
--

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `vehicle_fuel_records` ADD `previous_log` INT NULL DEFAULT NULL AFTER `date`;

ALTER TABLE `communications` ADD `template_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `audience`, ADD INDEX `template_id` (`template_id`);
ALTER TABLE `communications` ADD CONSTRAINT `communication_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE SET NULL ON UPDATE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;