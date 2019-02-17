-- 
-- Table structure for table "tblACLs"
-- 

CREATE TABLE "tblACLs" (
  "id" SERIAL UNIQUE,
  "target" INTEGER NOT NULL default '0',
  "targetType" INTEGER NOT NULL default '0',
  "userID" INTEGER NOT NULL default '-1',
  "groupID" INTEGER NOT NULL default '-1',
  "mode" INTEGER NOT NULL default '0'
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblCategory"
-- 

CREATE TABLE "tblCategory" (
  "id" SERIAL UNIQUE,
  "name" text NOT NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblAttributeDefinitions"
-- 

CREATE TABLE "tblAttributeDefinitions" (
  "id" SERIAL UNIQUE,
  "name" varchar(100) default NULL,
  "objtype" INTEGER NOT NULL default '0',
  "type" INTEGER NOT NULL default '0',
  "multiple" INTEGER NOT NULL default '0',
  "minvalues" INTEGER NOT NULL default '0',
  "maxvalues" INTEGER NOT NULL default '0',
  "valueset" TEXT default NULL,
  "regex" TEXT DEFAULT NULL,
  UNIQUE("name")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblUsers"
-- 

CREATE TABLE "tblUsers" (
  "id" SERIAL UNIQUE,
  "login" varchar(50) default NULL,
  "pwd" varchar(50) default NULL,
  "fullName" varchar(100) default NULL,
  "email" varchar(70) default NULL,
  "language" varchar(32) NOT NULL,
  "theme" varchar(32) NOT NULL,
  "comment" text NOT NULL,
  "role" INTEGER NOT NULL default '0',
  "hidden" INTEGER NOT NULL default '0',
  "pwdExpiration" TIMESTAMP default NULL,
  "loginfailures" INTEGER NOT NULL default '0',
  "disabled" INTEGER NOT NULL default '0',
  "quota" BIGINT,
  "homefolder" INTEGER default NULL,
  UNIQUE ("login")
);

-- --------------------------------------------------------

-- 
-- Table structure for table "tblUserPasswordRequest"
-- 

CREATE TABLE "tblUserPasswordRequest" (
  "id" SERIAL UNIQUE,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" (id) ON DELETE CASCADE,
  "hash" varchar(50) default NULL,
  "date" TIMESTAMP default NULL
);

-- --------------------------------------------------------

-- 
-- Table structure for table "tblUserPasswordHistory"
-- 

CREATE TABLE "tblUserPasswordHistory" (
  "id" SERIAL UNIQUE,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "pwd" varchar(50) default NULL,
  "date" TIMESTAMP default NULL
);

-- --------------------------------------------------------

-- 
-- Table structure for table "tblUserImages"
-- 

CREATE TABLE "tblUserImages" (
  "id" SERIAL UNIQUE,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "image" TEXT NOT NULL,
  "mimeType" varchar(100) NOT NULL default ''
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblFolders"
-- 

CREATE TABLE "tblFolders" (
  "id" SERIAL UNIQUE,
  "name" varchar(70) default NULL,
  "parent" INTEGER default NULL,
  "folderList" text NOT NULL,
  "comment" text,
  "date" INTEGER default NULL,
  "owner" INTEGER default NULL REFERENCES "tblUsers" ("id"),
  "inheritAccess" INTEGER NOT NULL default '1',
  "defaultAccess" INTEGER NOT NULL default '0',
  "sequence" REAL NOT NULL default '0'
) ;

ALTER TABLE "tblUsers" ADD FOREIGN KEY("homefolder") REFERENCES "tblFolders"("id");

-- --------------------------------------------------------

-- 
-- Table structure for table "tblFolderAttributes"
-- 

CREATE TABLE "tblFolderAttributes" (
  "id" SERIAL UNIQUE,
  "folder" INTEGER default NULL REFERENCES "tblFolders" ("id") ON DELETE CASCADE,
  "attrdef" INTEGER default NULL REFERENCES "tblAttributeDefinitions" ("id"),
  "value" text default NULL,
  UNIQUE (folder, attrdef)
) ;
 
-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocuments"
-- 

CREATE TABLE "tblDocuments" (
  "id" SERIAL UNIQUE,
  "name" varchar(150) default NULL,
  "comment" text,
  "date" INTEGER default NULL,
  "expires" INTEGER default NULL,
  "owner" INTEGER default NULL REFERENCES "tblUsers" ("id"),
  "folder" INTEGER default NULL REFERENCES "tblFolders" ("id"),
  "folderList" text NOT NULL,
  "inheritAccess" INTEGER NOT NULL default '1',
  "defaultAccess" INTEGER NOT NULL default '0',
  "locked" INTEGER NOT NULL default '-1',
  "keywords" text NOT NULL,
  "sequence" REAL NOT NULL default '0'
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentAttributes"
-- 

CREATE TABLE "tblDocumentAttributes" (
  "id" SERIAL UNIQUE,
  "document" INTEGER default NULL REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "attrdef" INTEGER default NULL REFERENCES "tblAttributeDefinitions" ("id"),
  "value" text default NULL,
  UNIQUE (document, attrdef)
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentApprovers"
-- 

CREATE TABLE "tblDocumentApprovers" (
  "approveID" SERIAL UNIQUE,
  "documentID" INTEGER NOT NULL default '0' REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "version" INTEGER NOT NULL default '0',
  "type" INTEGER NOT NULL default '0',
  "required" INTEGER NOT NULL default '0',
  UNIQUE ("documentID","version","type","required")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentApproveLog"
-- 

CREATE TABLE "tblDocumentApproveLog" (
  "approveLogID" SERIAL UNIQUE,
  "approveID" INTEGER NOT NULL default '0' REFERENCES "tblDocumentApprovers" ("approveID") ON DELETE CASCADE,
  "status" INTEGER NOT NULL default '0',
  "comment" TEXT NOT NULL,
  "date" TIMESTAMP default NULL,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentContent"
-- 

CREATE TABLE "tblDocumentContent" (
  "id" SERIAL UNIQUE,
  "document" INTEGER NOT NULL default '0' REFERENCES "tblDocuments" ("id"),
  "version" INTEGER NOT NULL,
  "comment" text,
  "date" INTEGER default NULL,
  "createdBy" INTEGER default NULL,
  "dir" varchar(255) NOT NULL default '',
  "orgFileName" varchar(150) NOT NULL default '',
  "fileType" varchar(10) NOT NULL default '',
  "mimeType" varchar(100) NOT NULL default '',
  "fileSize" BIGINT,
  "checksum" char(32),
  UNIQUE ("document","version")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentContentAttributes"
-- 

CREATE TABLE "tblDocumentContentAttributes" (
  "id" SERIAL UNIQUE,
  "content" INTEGER default NULL REFERENCES "tblDocumentContent" ("id") ON DELETE CASCADE,
  "attrdef" INTEGER default NULL REFERENCES "tblAttributeDefinitions" ("id"),
  "value" text default NULL,
  UNIQUE (content, attrdef)
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentLinks"
-- 

CREATE TABLE "tblDocumentLinks" (
  "id" SERIAL UNIQUE,
  "document" INTEGER NOT NULL default 0 REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "target" INTEGER NOT NULL default 0 REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "userID" INTEGER NOT NULL default 0 REFERENCES "tblUsers" ("id"),
  "public" INTEGER NOT NULL default 0
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentFiles"
-- 

CREATE TABLE "tblDocumentFiles" (
  "id" SERIAL UNIQUE,
  "document" INTEGER NOT NULL default 0 REFERENCES "tblDocuments" ("id"),
  "userID" INTEGER NOT NULL default 0 REFERENCES "tblUsers" ("id"),
  "version" INTEGER NOT NULL default '0',
  "public" INTEGER NOT NULL default '0',
  "comment" text,
  "name" varchar(150) default NULL,
  "date" INTEGER default NULL,
  "dir" varchar(255) NOT NULL default '',
  "orgFileName" varchar(150) NOT NULL default '',
  "fileType" varchar(10) NOT NULL default '',
  "mimeType" varchar(100) NOT NULL default ''
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentLocks"
-- 

CREATE TABLE "tblDocumentLocks" (
  "document" INTEGER REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentReviewers"
-- 

CREATE TABLE "tblDocumentReviewers" (
  "reviewID" SERIAL UNIQUE,
  "documentID" INTEGER NOT NULL default '0' REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "version" INTEGER NOT NULL default '0',
  "type" INTEGER NOT NULL default '0',
  "required" INTEGER NOT NULL default '0',
  UNIQUE ("documentID","version","type","required")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentReviewLog"
-- 

CREATE TABLE "tblDocumentReviewLog" (
  "reviewLogID" SERIAL UNIQUE,
  "reviewID" INTEGER NOT NULL default 0 REFERENCES "tblDocumentReviewers" ("reviewID") ON DELETE CASCADE,
  "status" INTEGER NOT NULL default 0,
  "comment" TEXT NOT NULL,
  "date" TIMESTAMP NOT NULL,
  "userID" INTEGER NOT NULL default 0 REFERENCES "tblUsers" ("id") ON DELETE CASCADE
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentStatus"
-- 

CREATE TABLE "tblDocumentStatus" (
  "statusID" SERIAL UNIQUE,
  "documentID" INTEGER NOT NULL default '0' REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "version" INTEGER NOT NULL default '0',
  UNIQUE ("documentID","version")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentStatusLog"
-- 

CREATE TABLE "tblDocumentStatusLog" (
  "statusLogID" SERIAL UNIQUE,
  "statusID" INTEGER NOT NULL default '0' REFERENCES "tblDocumentStatus" ("statusID") ON DELETE CASCADE,
  "status" INTEGER NOT NULL default '0',
  "comment" text NOT NULL,
  "date" TIMESTAMP default NULL,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblGroups"
-- 

CREATE TABLE "tblGroups" (
  "id" SERIAL UNIQUE,
  "name" varchar(50) default NULL,
  "comment" text NOT NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblGroupMembers"
-- 

CREATE TABLE "tblGroupMembers" (
  "groupID" INTEGER NOT NULL default '0' REFERENCES "tblGroups" ("id") ON DELETE CASCADE,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "manager" INTEGER NOT NULL default '0',
  UNIQUE  ("groupID","userID")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblKeywordCategories"
-- 

CREATE TABLE "tblKeywordCategories" (
  "id" SERIAL UNIQUE,
  "name" varchar(255) NOT NULL default '',
  "owner" INTEGER NOT NULL default '0'
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblKeywords"
-- 

CREATE TABLE "tblKeywords" (
  "id" SERIAL UNIQUE,
  "category" INTEGER NOT NULL default '0' REFERENCES "tblKeywordCategories" ("id") ON DELETE CASCADE,
  "keywords" text NOT NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblDocumentCategory"
-- 

CREATE TABLE "tblDocumentCategory" (
  "categoryID" INTEGER NOT NULL default '0' REFERENCES "tblCategory" ("id") ON DELETE CASCADE,
  "documentID" INTEGER NOT NULL default '0' REFERENCES "tblDocuments" ("id") ON DELETE CASCADE
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblNotify"
-- 

CREATE TABLE "tblNotify" (
  "target" INTEGER NOT NULL default '0',
  "targetType" INTEGER NOT NULL default '0',
  "userID" INTEGER NOT NULL default '-1',
  "groupID" INTEGER NOT NULL default '-1',
  UNIQUE  ("target","targetType","userID","groupID")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for table "tblSessions"
-- 

CREATE TABLE "tblSessions" (
  "id" varchar(50) PRIMARY KEY,
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "lastAccess" INTEGER NOT NULL default '0',
  "theme" varchar(30) NOT NULL default '',
  "language" varchar(30) NOT NULL default '',
  "clipboard" text default NULL,
	"su" INTEGER DEFAULT NULL,
  "splashmsg" text default NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for mandatory reviewers
-- 

CREATE TABLE "tblMandatoryReviewers" (
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "reviewerUserID" INTEGER NOT NULL default '0',
  "reviewerGroupID" INTEGER NOT NULL default '0',
  UNIQUE ("userID","reviewerUserID","reviewerGroupID")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for mandatory approvers
-- 

CREATE TABLE "tblMandatoryApprovers" (
  "userID" INTEGER NOT NULL default '0' REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "approverUserID" INTEGER NOT NULL default '0',
  "approverGroupID" INTEGER NOT NULL default '0',
  UNIQUE ("userID","approverUserID","approverGroupID")
) ;

-- --------------------------------------------------------

-- 
-- Table structure for events (calendar)
-- 

CREATE TABLE "tblEvents" (
  "id" SERIAL UNIQUE,
  "name" varchar(150) default NULL,
  "comment" text,
  "start" INTEGER default NULL,
  "stop" INTEGER default NULL,
  "date" INTEGER default NULL,
  "userID" INTEGER NOT NULL default '0'
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow states
-- 

CREATE TABLE "tblWorkflowStates" (
  "id" SERIAL UNIQUE,
  "name" text NOT NULL,
  "visibility" INTEGER DEFAULT 0,
  "maxtime" INTEGER DEFAULT 0,
  "precondfunc" text DEFAULT NULL,
  "documentstatus" INTEGER DEFAULT NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow actions
-- 

CREATE TABLE "tblWorkflowActions" (
  "id" SERIAL UNIQUE,
  "name" text NOT NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflows
-- 

CREATE TABLE "tblWorkflows" (
  "id" SERIAL UNIQUE,
  "name" text NOT NULL,
  "initstate" INTEGER NOT NULL REFERENCES "tblWorkflowStates" ("id") ON DELETE CASCADE
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow transitions
-- 

CREATE TABLE "tblWorkflowTransitions" (
  "id" SERIAL UNIQUE,
  "workflow" INTEGER default NULL REFERENCES "tblWorkflows" ("id") ON DELETE CASCADE,
  "state" INTEGER default NULL REFERENCES "tblWorkflowStates" ("id") ON DELETE CASCADE,
  "action" INTEGER default NULL REFERENCES "tblWorkflowActions" ("id") ON DELETE CASCADE,
  "nextstate" INTEGER default NULL REFERENCES "tblWorkflowStates" ("id") ON DELETE CASCADE,
  "maxtime" INTEGER DEFAULT 0
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow transition users
-- 

CREATE TABLE "tblWorkflowTransitionUsers" (
  "id" SERIAL UNIQUE,
  "transition" INTEGER default NULL REFERENCES "tblWorkflowTransitions" ("id") ON DELETE CASCADE,
  "userid" INTEGER default NULL REFERENCES "tblUsers" ("id") ON DELETE CASCADE
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow transition groups
-- 

CREATE TABLE "tblWorkflowTransitionGroups" (
  "id" SERIAL UNIQUE,
  "transition" INTEGER default NULL REFERENCES "tblWorkflowTransitions" ("id") ON DELETE CASCADE,
  "groupid" INTEGER default NULL REFERENCES "tblGroups" ("id") ON DELETE CASCADE,
  "minusers" INTEGER default NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow log
-- 

CREATE TABLE "tblWorkflowLog" (
  "id" SERIAL UNIQUE,
  "document" INTEGER default NULL REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "version" INTEGER default NULL,
  "workflow" INTEGER default NULL REFERENCES "tblWorkflows" ("id") ON DELETE CASCADE,
  "userid" INTEGER default NULL REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "transition" INTEGER default NULL REFERENCES "tblWorkflowTransitions" ("id") ON DELETE CASCADE,
  "date" TIMESTAMP default NULL,
  "comment" text
) ;

-- --------------------------------------------------------

-- 
-- Table structure for workflow document relation
-- 

CREATE TABLE "tblWorkflowDocumentContent" (
  "parentworkflow" INTEGER DEFAULT 0,
  "workflow" INTEGER DEFAULT NULL REFERENCES "tblWorkflows" ("id") ON DELETE CASCADE,
  "document" INTEGER DEFAULT NULL REFERENCES "tblDocuments" ("id") ON DELETE CASCADE,
  "version" INTEGER DEFAULT NULL,
  "state" INTEGER DEFAULT NULL REFERENCES "tblWorkflowStates" ("id") ON DELETE CASCADE,
  "date" TIMESTAMP default NULL
) ;

-- --------------------------------------------------------

-- 
-- Table structure for mandatory workflows
-- 

CREATE TABLE "tblWorkflowMandatoryWorkflow" (
  "userid" INTEGER default NULL REFERENCES "tblUsers" ("id") ON DELETE CASCADE,
  "workflow" INTEGER default NULL REFERENCES "tblWorkflows" ("id") ON DELETE CASCADE,
  UNIQUE(userid, workflow)
) ;

-- --------------------------------------------------------

-- 
-- Table structure for version
-- 

CREATE TABLE "tblVersion" (
  "date" TIMESTAMP NOT NULL,
  "major" INTEGER,
  "minor" INTEGER,
  "subminor" INTEGER
) ;

-- --------------------------------------------------------

--
-- Initial content for database
--

INSERT INTO "tblUsers" VALUES (1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Administrator', 'address@server.com', '', '', '', 1, 0, NULL, 0, 0, 0, NULL);
SELECT nextval('"tblUsers_id_seq"');
INSERT INTO "tblUsers" VALUES (2, 'guest', NULL, 'Guest User', NULL, '', '', '', 2, 0, NULL, 0, 0, 0, NULL);
SELECT nextval('"tblUsers_id_seq"');
INSERT INTO "tblFolders" VALUES (1, 'DMS', 0, '', 'DMS root', extract(epoch from now()), 1, 0, 2, 0);
SELECT nextval('"tblFolders_id_seq"');
INSERT INTO "tblVersion" VALUES (CURRENT_TIMESTAMP, 5, 1, 0);
