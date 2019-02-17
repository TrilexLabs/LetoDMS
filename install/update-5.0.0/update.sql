START TRANSACTION;

ALTER TABLE `tblUsers` ADD COLUMN `homefolder` INTEGER DEFAULT NULL;
ALTER TABLE `tblUsers` ADD CONSTRAINT `tblUsers_homefolder` FOREIGN KEY (`homefolder`) REFERENCES `tblFolders` (`id`);

UPDATE tblVersion set major=5, minor=0, subminor=0;

COMMIT;

