--
-- Database: `InstiKit`
--

-- --------------------------------------------------------

--
-- InstiKit 5.5.0 pre update queries
--

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `subject_wise_students` ADD `position` INT NOT NULL DEFAULT '0' AFTER `student_id`;
ALTER TABLE `options` ADD `position` INT NOT NULL DEFAULT '0' AFTER `type`;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;