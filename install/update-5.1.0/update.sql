START TRANSACTION;

ALTER TABLE `tblDocumentContent` CHANGE `mimeType` `mimeType` varchar(100) NOT NULL DEFAULT '';

ALTER TABLE `tblDocumentFiles` CHANGE `mimeType` `mimeType` varchar(100) NOT NULL DEFAULT '';

ALTER TABLE `tblUserImages` CHANGE `mimeType` `mimeType` varchar(100) NOT NULL DEFAULT '';

ALTER TABLE `tblDocumentFiles` ADD COLUMN `public` tinyint(1) NOT NULL DEFAULT '0' AFTER `document`;

ALTER TABLE `tblDocumentFiles` ADD COLUMN `version` smallint(5) unsigned NOT NULL DEFAULT '0' AFTER `document`;

ALTER TABLE `tblUsers` CHANGE `pwdExpiration` `pwdExpiration` datetime DEFAULT NULL;

ALTER TABLE `tblUserPasswordRequest` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblUserPasswordHistory` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblDocumentApproveLog` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblDocumentReviewLog` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblDocumentStatusLog` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblWorkflowLog` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblWorkflowDocumentContent` CHANGE `date` `date` datetime NOT NULL;

ALTER TABLE `tblVersion` CHANGE `date` `date` datetime NOT NULL;

UPDATE tblVersion set major=5, minor=1, subminor=0;

COMMIT;

