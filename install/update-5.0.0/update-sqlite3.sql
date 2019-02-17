BEGIN;

ALTER TABLE tblUsers ADD COLUMN `homefolder` INTEGER DEFAULT NULL REFERENCES `tblFolders` (`id`);

UPDATE tblVersion set major=5, minor=0, subminor=0;

COMMIT;


