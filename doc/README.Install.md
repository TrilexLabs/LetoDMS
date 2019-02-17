LetoDMS Installation Instructions
==================================

REQUIREMENTS
============

LetoDMS is a web-based application written in PHP. It uses MySQL,
sqlite3 or postgresql to manage the documents that were uploaded into
the application. Be aware that postgresql is not very well tested.

Make sure you have PHP 5.4 and MySQL 5 or higher installed. LetoDMS
will work with PHP running in CGI-mode as well as running as a module under
apache.

Here is a detailed list of requirements:

1. A web server with at least php 5.4
2. A mysql database, unless you use sqlite
3. The php installation must have support for `pdo_mysql` or `pdo_sqlite`,
   `php_gd2`, `php_mbstring`
4. Various command line programms to convert files into text for indexing
   pdftotext, catdoc, xls2csv or scconvert, cat, id3 (optional, only needed
   for fulltext search)
5. ImageMagic (the convert program) is needed for creating preview images 
6. The Zend Framework (version 1) (optional, only needed for fulltext search)
7. The pear Log and Mail package
8. The pear HTTP_WebDAV_Server package (optional, only need for webdav)
9. SLIM RestApi
10. FeedWriter from https://github.com/mibe/FeedWriter

It is highly recommended to use the quickstart archive (LetoDMS-quickstart-x.y.z.tar.gz)
because it includes all software packages for running LetoDMS, though you still need
a working web server with PHP and a mysql database unless you intend to use sqlite.

QUICKSTART
===========

The fastes way to get LetoDMS running is by unpacking the archive
`LetoDMS-quickstart-x.y.z.tar.gz` into your webservers document root.
It will create a new directory `LetoDMS51x` containing everything you
need to run LetoDMS with sqlite3. Make sure that the subdіrectory
`LetoDMS51x/data`
and the configuration file `LetoDMS51/www/conf/settings.xml` is writeable
by your web server. All other directories must just be readable by your
web server. In the next step you need to adjust
the configuration file in `LetoDMS51/www/conf/settings.xml`. If you
are not afraid of xml files, then open it in your favorite text editor
and search for `/home/wwww-data`. Replace that part in any path found
with your document root. Alternatively, you can open the installer
with a browser at http://your-domain/LetoDMS51x/install/
It will first ask to unlock the installer by creating a file
`ENABLE_INSTALL_TOOL` in the diretory `LetoDMS51/www/conf/`. Change all
paths by replacing `/home/wwww-data` with your document root. Once done,
save it, remove the file `ENABLE_INSTALL_TOOL` and point your browser to
http://your-domain/LetoDMS51x/.


UPDATING FROM A PREVIOUS VERSION OR LetoDMS
=============================================

As LetoDMS is a smooth continuation of LetoDMS there is no difference
in updating from LetoDMS or LetoDMS

You have basically two choices to update LetoDMS

- you install a fresh version of LetoDMS and copy over your data and configuration
- you replace the software in your current installation with a new version

The first option is less interuptive but requires to be able to set up a second
temporary LetoDMS installation.

In both cases make sure to have a backup of your data directory, configuration
and database.

Fresh installation and take over of data
-----------------------------------------

- just do a fresh installation somewhere on your web server and make sure it
  works. It is fine to use
  sqlite for it, even if your final installation uses mysql.
- replace the data directory in your new installation with the data directory
  from your current installation. Depending on the size of that directory you
  may either copy, move or place a symbolic link. The content of the data directory
  will not be changed unless you modify your documents. Its perfectly save to
  browse through your documents and download them.
- copy over the configuration settings.xml into your new installation
- if you use mysql you could as well make a copy of the database to make sure
  your current database remains unchanged. As long as you do not do any modification,
  you could even use your current database.
- modify the settings.xml to fit the fresh install. This will mostly be the
  httpRoot, the paths to the installation directory and possibly the database
  connection.
- create a file `ENABLE_INSTALL_TOOL` in the conf directory and point
  your browser at http://hostname/LetoDMS/install
  The install tool will detect the version of your current LetoDMS installation
  and run the required database updates.
  If you update just within the last version number (e.g. from 5.1.6 to 5.1.9),
  this step
  will not be required because such a subminor version update will never
  contain database updates.
- test your new installation. 

Updating your current installation
-----------------------------------

- make a backup of your data folder and the configuration file settings.xml
- in case you use mysql then dump your current database
- get the LetoDMS archive LetoDMS-x.y.z.tar.gz and all pear packages
  LetoDMS_Core, LetoDMS_Lucene, LetoDMS_Preview and extract them over your
  current instalation. As they do not contain a data directory nor a settings.xml
  file, you will not overwrite your existing data and configuration.
- you may compare your conf/settings.xml file with the shipped version
  conf/settings.xml.template for new parameters. If you don't do it, the next
  time you save the configuration the default values will be used.
- create a file `ENABLE_INSTALL_TOOL` in the conf directory and point
  your browser at http://hostname/LetoDMS/install
  The install tool will detect the version of your current LetoDMS installation
  and run the required database updates.
  If you update just within the last version number (e.g. from 5.1.6 to 5.1.9),
  this step
  will not be required because such a subminor version update will never
  contain database updates.


THE LONG STORY
================

If you intend to run a single instance of LetoDMS, you are most likely
better off by using the quickstart archive as described above. This
section is mostly for users who wants to know more about the internals
of LetoDMS or do packaging for a software distribution, which already
ships some of the additional software LetoDMS requires.

LetoDMS has changed its installation process with version 3.0.0. This gives
you many more options in how to install LetoDMS. First of all, LetoDMS was
split into a core package (`LetoDMS_Core-<version>.tar.gz`) and the web
application itself (`LetoDMS-<version>.tar.gz`). The core is a pear package
which could be installed as one. It is responsible for all the database
operations. The web application contains the ui not knowing anything about
the database layout. Second, one LetoDMS installation can be used for
various customer instances by sharing a common source. Starting with
version 3.2.0 a full text search engine has been added. This requires
the zend framework and another pear package `LetoDMS_Lucene-<version>.tar.gz`
which can be downloaded from the LetoDMS web page. Version 4.0.0 show
preview images of documents which requires `LetoDMS_Preview-<version>.tar.gz`.
Finally, LetoDMS has
got a web based installation, which takes care of most of the installation
process.

Before you proceed you have to decide how to install LetoDMS:
1. with multiple instances
2. as a single instance

Both have its pros and cons, but
1. setting up a single instance is easier if you have no shell access to
   the web server
2. the installation script is only tested for single instances

Installation for multiple instances shares the same source by many
instances but requires to create links which is not in any case possible
on your web server.

0. Some preparation
-------------------

A common source of problems in the past have been the additional software
packages needed by LetoDMS. Those are the PEAR packages `Log`, `Mail` and
`HTTP_WebDAV_Server` as well as the `Zend_Framework`.
If you have full access to the server running a Linux distribution it is
recommended to install those with your package manager if they are provided
by your Linux distribution. If you cannot install it this way then choose
a directory (preferable not below your web document root), unpack the
software into it and extend the php include path with your newly created
directory. Extending the php include can be either done by modifying
php.ini or adding a line like

> php_value include_path '/home/mypath:.:/usr/share/php'

to your apache configuration or setting the `extraPath` configuration
variable of LetoDMS.

For historical reasons the path to the LetoDMS_Core and LetoDMS_Lucene package
can still be set
in the configuration, which is not recommend anymore. Just leave those
parameters empty.

On Linux/Unix your web server should be run with the environment variable
LANG set to your system default. If LANG=C, then the original filename
of an uploaded document will not be preserved if the filename contains
non ascii characters.

Turn off magic_quotes_gpc in your php.ini, if you are using a php version
below 5.4.

1. Using the installation tool
------------------------------

Unpack LetoDMS-<version>.tar.gz below the document root of
your web server.
Install `LetoDMS_Preview-<version>.tar.gz` and
`LetoDMS_Core-<version>.tar.gz` either as a regular pear package or
set up a file system structure like pear did somewhere on you server.
For the full text search engine support, you will also
need to install `LetoDMS_Lucene-<version>.tar.gz`.

For the following instructions we will assume a structure like above
and LetoDMS-<version> being accessible through
http://localhost/LetoDMS/

* Point you web browser towards http://hostname/LetoDMS/install/

* Follow the instructions on the page and create a file `ENABLE_INSTALL_TOOL`
  in the conf directory.

* Create a data directory with the thre sub directories staging, cache
  and lucene.
  Make sure the data directory is either *not* below your document root
	or is protected with a .htaccess file against web access. The data directory
  needs to be writable by the web server.

* Clicking on 'Start installation' will show a form with all necessary
  settings for a basic installation.

* After saving your settings succesfully you are ready to log in as admin and
  continue customizing your installation with the 'Admin Tools'

2. Detailed installation instructions (single instance)
-------------------------------------------------------

You need a working web server with MySQL/PHP5 support and the files
`LetoDMS-<version>.tar.gz`, `LetoDMS_Preview-<version>.tar.gz` and
`LetoDMS_Core-<version>.tgz`. For the 
full text search engine support, you will also need to unpack
`LetoDMS_Lucene-<version>.tgz`.

* Unpack all the files in a public web server folder. If you're working on
  a host machine your provider will tell you where to upload the files.
  If possible, do not unpack the pear packages `LetoDMS_Core-<version>.tgz`,
	`LetoDMS_Preview-<version>.tgz` and
  `LetoDMS_Lucene-<version>.tgz` below the document root of your web server.
	Choose a temporary folder, as the files will be moved in a second.

  Create a directory e.g. `pear` in the same directory where you unpacked
  LetoDMS and create a sub directory LetoDMS. Move the content except for the
  `tests` directory of all LetoDMS pear
  packages into that directory. Please note that `pear/LetoDMS` may not 
  (and for security reasons should not) be below your document root.
  
  You will end up with a directory structure like the following

  > LetoDMS-<version>
  > pear
  >   LetoDMS
  >     Core.php
  >     Core
  >     Lucene.php
  >     Lucene
  >     Preview
  >     Preview.php

  Since they are pear packages they can also be installed with

	> pear install LetoDMS_Core-<version>.tgz
	> pear install LetoDMS_Lucene-<version>.tgz
	> pear install LetoDMS_Preview-<version>.tgz

* The PEAR packages Log and Mail are also needed. They can be downloaded from
  http://pear.php.net/package/Log and http://pear.php.net/package/Mail.
	Either install it as a pear package
	or place it under your new directory 'pear'

  > pear
	>   Log
	>   Log.php
	>   Mail
	>   Mail.php

* The package HTTP_WebDAV_Server is also needed. It can be downloaded from
  http://pear.php.net/package/HTTP_WebDAV_Server. Either install it as a
	pear package or place it under your new directory 'pear'

  > pear
  >   HTTP
	>     WebDAV
	>       Server
	>       Server.php

  If you run PHP in CGI mode, you also need to place a .htaccess file
	in the webdav directory with the following content.

	RewriteEngine on
	RewriteRule .* - [env=HTTP_AUTHORIZATION:%{HTTP:Authorization},last]

* Create a data folder somewhere on your web server including the subdirectories
  staging, cache and lucene and make sure they are writable by your web server,
  but not accessible through the web.

For security reason the data folder should not be inside the public folders
or should be protected by a .htaccess file. The folder containing the
configuration (settings.xml) must be protected by an .htaccess file like the
following.

	> <Files ~ "^settings\.xml">
	> Order allow,deny
	> Deny from all
	> </Files>


If you install LetoDMS for the first time continue with the database setup.

* Create a new database on your web server
  e.g. for mysql:
	create database LetoDMS;
* Create a new user for the database with all permissions on the new database
  e.g. for mysql:
	grant all privileges on LetoDMS.* to LetoDMS@localhost identified by 'secret';
	(replace 'secret' with you own password)
* Optionally import `create_tables-innodb.sql` in the new database
  e.g. for mysql:
	> cat create_tables-innodb.sql | mysql -uLetoDMS -p LetoDMS
  This step can also be done by the install tool.
* create a file `ENABLE_INSTALL_TOOL` in the conf directory and point
  your browser at http://hostname/LetoDMS/install


3. Email Notification
---------------------

A notification system allows users to receive an email when a
document or folder is changed. This is an event-based mechanism that
notifies the user as soon as the change has been made and replaces the
cron mechanism originally developed. Any user that has read access to a
document or folder can subscribe to be notified of changes. Users that
have been assigned as reviewers or approvers for a document are
automatically added to the notification system for that document.

A new page has been created for users to assist with the management of
their notification subscriptions. This can be found in the "My Account"
section under "Notification List".


4. Nearly finished
------------------

Now point your browser to http://hostname/LetoDMS/index.php
and login with "admin" both as username and password.
After having logged in you should first choose "My Account" and
change the Administrator's password and email-address.


CONFIGURING MULTIPLE INSTANCES
==============================

Since version 3.0.0, LetoDMS can be set up to run several parallel instances
sharing the same source but each instance has its own configuration. This is
quite useful if you intend to host LetoDMS for several customers. This
approach still allows to have diffenrent version of LetoDMS installed
and will not force you to upgrade a customer instance, because other
instances are upgraded. A customer instance consists of
1. a directory containing mostly links to the LetoDMS source and a
   configuration file
2. a directory containing the document content files
3. a database

1. Unpack the LetoDMS distribution
----------------------------------

Actually there is no need to set up the database at this point but it won't
hurt since you'll need one in the next step anyway. The sources of LetoDMS
can be anywhere you like. The do not have to be in you www-root. If you just
have access to your www-root directory, then put them there.

2. Setup the instance
---------------------

Unpack the files as described in the quick installation.

Create a directory in your www-root or use www-root for your instance. In the
second case, you will not be able to create a second instance, because each
instance needs its own directory.

Go into that directory create the following links (<LetoDMS-source> is the
directory of your initial LetoDMS intallation).

> src -> <LetoDMS-source>
> inc -> src/inc
> op -> src/op
> out -> src/out
> js -> src/js
> views -> src/views
> languages -> src/languages
> styles -> src/styles
> themes -> src/themes
> install -> src/install
> index.php -> src/index.php

> ln -s ../LetoDMS-<version> src
> ln -s src/inc inc
> ln -s src/op op
> ln -s src/out out
> ln -s src/js js
> ln -s src/views views
> ln -s src/languages languages
> ln -s src/styles styles
> ln -s src/themes themes
> ln -s src/install install
> ln -s src/index.php index.php

Create a new directory named conf and run the installation tool.

Creating the links as above has the advantage that you can easily switch
to a new version and go back if it is necessary. You could even run various
instances of LetoDMS using different versions.

3. Create a database and data store for each instance
-----------------------------------------------------

Create a database and data store for each instance and adjust the database
settings in conf/settings.xml or run the installation tool.

Point your web browser towards the index.php file in your new instance.

NOTE FOR VERSION 4.0.0
======================

Since version 4.0.0 of LetoDMS installation has been simplified. 
ADOdb is no longer needed because the database access is done by
PDO.

IMPORTANT NOTE ABOUT TRANSLATIONS
=================================

As you can see LetoDMS provides a lot of languages but we are not professional 
translators and therefore rely on user contributions.

If your language is not present in the login panel:
- copy the language/English/ folder and rename it appropriately for your
  language
- open the file `languages/your_lang/lang.inc` and translate it
- open the help file `languages/your_lang/help.htm` and translate it too

If you see some wrong or not translated messages:
- open the file `languages/your_lang/lang.inc`
- search the wrong messages and translate them

if you have some "error getting text":
- search the string in the english file `languages/english/lang.inc`
- copy to your language file `languages/your_lang/lang.inc`
- translate it

If there is no help in your language:
- Copy the English help `english/help.htm` file to your language folder
- translate it

If you apply any changes to the language files please send them to the
LetoDMS developers <info@LetoDMS.org>.

http://www.iana.org/assignments/language-subtag-registry has a list of
all language and country codes.

LICENSING
=========

LetoDMS is licensed unter GPLv2

Jumploader is licensed as stated by the author on th web site
<http://jumploader.com/>

-- Taken from web site of jumploader  ---
You may use this software for free, however, you should not:

- Decompile binaries.
- Alter or replace class and/or resource files.
- Redistribute this software under different name or authority.

If you would like a customized version, I can do this for a fee. Don't hesitate to contact me with questions or comments.

Uwe Steinmann <info@LetoDMS.org>
