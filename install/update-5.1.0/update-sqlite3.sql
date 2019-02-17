BEGIN;

ALTER TABLE `tblVersion` RENAME TO `__tblVersion`;

CREATE TABLE `tblVersion` (
  `date` TEXT default NULL,
  `major` INTEGER,
  `minor` INTEGER,
  `subminor` INTEGER
);

INSERT INTO `tblVersion` SELECT * FROM `__tblVersion`;

DROP TABLE `__tblVersion`;

ALTER TABLE `tblUserImages` RENAME TO `__tblUserImages`;

CREATE TABLE `tblUserImages` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `userID` INTEGER NOT NULL default '0' REFERENCES `tblUsers` (`id`) ON DELETE CASCADE,
  `image` blob NOT NULL,
  `mimeType` varchar(100) NOT NULL default ''
);

INSERT INTO `tblUserImages` SELECT * FROM `__tblUserImages`;

DROP TABLE `__tblUserImages`;

ALTER TABLE `tblDocumentContent` RENAME TO `__tblDocumentContent`;

CREATE TABLE `tblDocumentContent` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `document` INTEGER NOT NULL default '0' REFERENCES `tblDocuments` (`id`),
  `version` INTEGER unsigned NOT NULL,
  `comment` text,
  `date` INTEGER default NULL,
  `createdBy` INTEGER default NULL,
  `dir` varchar(255) NOT NULL default '',
  `orgFileName` varchar(150) NOT NULL default '',
  `fileType` varchar(10) NOT NULL default '',
  `mimeType` varchar(100) NOT NULL default '',
  `fileSize` INTEGER,
  `checksum` char(32),
  UNIQUE (`document`,`version`)
);

INSERT INTO `tblDocumentContent` SELECT * FROM `__tblDocumentContent`;

DROP TABLE `__tblDocumentContent`;

ALTER TABLE `tblDocumentFiles` RENAME TO `__tblDocumentFiles`;

CREATE TABLE `tblDocumentFiles` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `document` INTEGER NOT NULL default 0 REFERENCES `tblDocuments` (`id`),
  `userID` INTEGER NOT NULL default 0 REFERENCES `tblUsers` (`id`),
  `comment` text,
  `name` varchar(150) default NULL,
  `date` INTEGER default NULL,
  `dir` varchar(255) NOT NULL default '',
  `orgFileName` varchar(150) NOT NULL default '',
  `fileType` varchar(10) NOT NULL default '',
  `mimeType` varchar(100) NOT NULL default ''
) ;

INSERT INTO `tblDocumentFiles` SELECT * FROM `__tblDocumentFiles`;

DROP TABLE `__tblDocumentFiles`;

ALTER TABLE `tblDocumentFiles` ADD COLUMN `version` INTEGER unsigned NOT NULL DEFAULT '0';

ALTER TABLE `tblDocumentFiles` ADD COLUMN `public` INTEGER unsigned NOT NULL DEFAULT '0';

ALTER TABLE `tblUsers` RENAME TO `__tblUsers`;

CREATE TABLE `tblUsers` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `login` varchar(50) default NULL,
  `pwd` varchar(50) default NULL,
  `fullName` varchar(100) default NULL,
  `email` varchar(70) default NULL,
  `language` varchar(32) NOT NULL,
  `theme` varchar(32) NOT NULL,
  `comment` text NOT NULL,
  `role` INTEGER NOT NULL default '0',
  `hidden` INTEGER NOT NULL default '0',
  `pwdExpiration` TEXT default NULL,
  `loginfailures` INTEGER NOT NULL default '0',
  `disabled` INTEGER NOT NULL default '0',
  `quota` INTEGER,
  `homefolder` INTEGER default NULL REFERENCES `tblFolders` (`id`),
  UNIQUE (`login`)
);

INSERT INTO `tblUsers` SELECT * FROM `__tblUsers`;

DROP TABLE `__tblUsers`;

ALTER TABLE `tblUserPasswordRequest` RENAME TO `__tblUserPasswordRequest`;

CREATE TABLE `tblUserPasswordRequest` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `userID` INTEGER NOT NULL default '0' REFERENCES `tblUsers` (`id`) ON DELETE CASCADE,
  `hash` varchar(50) default NULL,
  `date` TEXT NOT NULL
);

INSERT INTO `tblUserPasswordRequest` SELECT * FROM `__tblUserPasswordRequest`;

DROP TABLE `__tblUserPasswordRequest`;

ALTER TABLE `tblUserPasswordHistory` RENAME TO `__tblUserPasswordHistory`;

CREATE TABLE `tblUserPasswordHistory` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `userID` INTEGER NOT NULL default '0' REFERENCES `tblUsers` (`id`) ON DELETE CASCADE,
  `pwd` varchar(50) default NULL,
  `date` TEXT NOT NULL
);

INSERT INTO `tblUserPasswordHistory` SELECT * FROM `__tblUserPasswordHistory`;

DROP TABLE `__tblUserPasswordHistory`;

ALTER TABLE `tblDocumentReviewLog` RENAME TO `__tblDocumentReviewLog`;

CREATE TABLE `tblDocumentReviewLog` (
  `reviewLogID` INTEGER PRIMARY KEY AUTOINCREMENT,
  `reviewID` INTEGER NOT NULL default 0 REFERENCES `tblDocumentReviewers` (`reviewID`) ON DELETE CASCADE,
  `status` INTEGER NOT NULL default 0,
  `comment` TEXT NOT NULL,
  `date` TEXT NOT NULL,
  `userID` INTEGER NOT NULL default 0 REFERENCES `tblUsers` (`id`) ON DELETE CASCADE
);

INSERT INTO `tblDocumentReviewLog` SELECT * FROM `__tblDocumentReviewLog`;

DROP TABLE `__tblDocumentReviewLog`;

ALTER TABLE `tblDocumentStatusLog` RENAME TO `__tblDocumentStatusLog`;

CREATE TABLE `tblDocumentStatusLog` (
  `statusLogID` INTEGER PRIMARY KEY AUTOINCREMENT,
  `statusID` INTEGER NOT NULL default '0' REFERENCES `tblDocumentStatus` (`statusID`) ON DELETE CASCADE,
  `status` INTEGER NOT NULL default '0',
  `comment` text NOT NULL,
  `date` TEXT NOT NULL,
  `userID` INTEGER NOT NULL default '0' REFERENCES `tblUsers` (`id`) ON DELETE CASCADE
) ;

INSERT INTO `tblDocumentStatusLog` SELECT * FROM `__tblDocumentStatusLog`;

DROP TABLE `__tblDocumentStatusLog`;

ALTER TABLE `tblDocumentApproveLog` RENAME TO `__tblDocumentApproveLog`;

CREATE TABLE `tblDocumentApproveLog` (
  `approveLogID` INTEGER PRIMARY KEY AUTOINCREMENT,
  `approveID` INTEGER NOT NULL default '0' REFERENCES `tblDocumentApprovers` (`approveID`) ON DELETE CASCADE,
  `status` INTEGER NOT NULL default '0',
  `comment` TEXT NOT NULL,
  `date` TEXT NOT NULL,
  `userID` INTEGER NOT NULL default '0' REFERENCES `tblUsers` (`id`) ON DELETE CASCADE
);

INSERT INTO `tblDocumentApproveLog` SELECT * FROM `__tblDocumentApproveLog`;

DROP TABLE `__tblDocumentApproveLog`;

ALTER TABLE `tblWorkflowLog` RENAME TO `__tblWorkflowLog`;

CREATE TABLE `tblWorkflowLog` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `document` INTEGER default NULL REFERENCES `tblDocuments` (`id`) ON DELETE CASCADE,
  `version` INTEGER default NULL,
  `workflow` INTEGER default NULL REFERENCES `tblWorkflows` (`id`) ON DELETE CASCADE,
  `userid` INTEGER default NULL REFERENCES `tblUsers` (`id`) ON DELETE CASCADE,
  `transition` INTEGER default NULL REFERENCES `tblWorkflowTransitions` (`id`) ON DELETE CASCADE,
  `date` datetime NOT NULL,
  `comment` text
);

INSERT INTO `tblWorkflowLog` SELECT * FROM `__tblWorkflowLog`;

DROP TABLE `__tblWorkflowLog`;

ALTER TABLE `tblWorkflowDocumentContent` RENAME TO `__tblWorkflowDocumentContent`;

CREATE TABLE `tblWorkflowDocumentContent` (
  `parentworkflow` INTEGER DEFAULT 0,
  `workflow` INTEGER DEFAULT NULL REFERENCES `tblWorkflows` (`id`) ON DELETE CASCADE,
  `document` INTEGER DEFAULT NULL REFERENCES `tblDocuments` (`id`) ON DELETE CASCADE,
  `version` INTEGER DEFAULT NULL,
  `state` INTEGER DEFAULT NULL REFERENCES `tblWorkflowStates` (`id`) ON DELETE CASCADE,
  `date` datetime NOT NULL
);

INSERT INTO `tblWorkflowDocumentContent` SELECT * FROM `__tblWorkflowDocumentContent`;

DROP TABLE `__tblWorkflowDocumentContent`;

UPDATE tblVersion set major=5, minor=1, subminor=0;

COMMIT;

