*********************************************
How to set up a Synology NAS to run LetoDMS
*********************************************

**This guide has been updated and tested to work on Synology DSM 6.0. It should as well work with older DMS versions, however some steps or paths may be different.**

Introduction
############
LetoDMS is a feature rich and lightweight document management system. Unfortunately, some of the tools which are part of many Linux distros, have not been made available by
Synology and therefore require additional steps to bring them to your Synology.

This guide covers the installation of the required tools to have all features of LetoDMS available. It does not cover the installation of 3rd party programs (like OPKG). It
does not cover the installation of LetoDMS as well, please refer to the separate README.Install.md file.

Prerequisites
#############
In order to complete the steps outlined below, you must be able to carry out the following tasks:

* Use the command line and know essential commands
* Install a 3rd party package system and install packages using this system

To complete the installation, the following prerequisites on your Synology must be met:

* IPKG or OPKG (OPKG preferred) installed
* PEAR installed from the Synology Package Center

Installation and configuration
##############################

In the following steps, you will first install the required packages, followed by doing the neccesary configurations. These steps
must be done on the terminal.

Install Ghostscript
***************************

The first step is to install Ghostscript to make ImageMagick capable of converting PDF files to images which are then used for previews.
Use IPKG or OPKG to complete this step.

Make Ghostscript available to PHP
*****************************************

To check where Ghostscript is installed run *which gs* to get the installation path. Now check if this path is visible to PHP. To check this,
use phpinfo and find **_SERVER["PATH"]**. If you can't find /opt inside, PHP can't see applications installed there. You can now either try to
update the paths or just make a symlink.
To create the symlink, cd to /usr/bin and type *ln -s /opt/bin/gs gs*. Verify the created symlink.

Fix ImageMagick
********************

Not only Ghostscript is affected by bugs, the default configuration files for ImageMagick are missing. Unfortunately some work is required here as well.

To check where ImageMagick looks for it's files, invoke the command *convert -debug configure logo: null:*. You will see some paths shown, these
are the paths where ImageMagic tries to locate it's configuration files. The first path shown will point to */usr/share/ImageMagick-6* followed by the
name of an XML file. At the very end of the output you will see which configuration file has been loaded, in the default setting there will be an error.

Point to */usr/share* and check if you can find the **ImageMagick-6** directory. If is not present, create it. Cd into the directory.

Next step is to fill the directory with files. Use the following list to download the files (credit goes to Thibault, http://blog.taillandier.name/2010/08/04/mediawiki-sur-son-nas-synology/).

* wget http://www.imagemagick.org/source/coder.xml
* wget http://www.imagemagick.org/source/colors.xml
* wget http://www.imagemagick.org/source/configure.xml
* wget http://www.imagemagick.org/source/delegates.xml
* wget http://www.imagemagick.org/source/english.xml
* wget http://www.imagemagick.org/source/francais.xml
* wget http://www.imagemagick.org/source/locale.xml
* wget http://www.imagemagick.org/source/log.xml
* wget http://www.imagemagick.org/source/magic.xml
* wget http://www.imagemagick.org/source/mime.xml
* wget http://www.imagemagick.org/source/policy.xml
* wget http://www.imagemagick.org/source/thresholds.xml
* wget http://www.imagemagick.org/source/type-ghostscript.xml
* wget http://www.imagemagick.org/source/type-windows.xml
* wget http://www.imagemagick.org/source/type.xml

Testing
*************

Now you should be ready to test. Put a PDF file in a directory, cd into this directory.

To test convert directly, invoke the following command (replace file.pdf with your filename, replace output.png with your desired name):

**convert file.pdf output.png**

If everything goes well you should now receive a png file which can be opened. There may be a warning message about iCCP which can be ignored.

If you want to test Ghostcript as well, invoke the follwing command:

**gs -sDEVICE=pngalpha -sOutputFile=output.png -r144 file.pdf**

This command should go through without any errors and as well output a png file.

If the tests above are successful, you are ready to use LetoDMS Preview.

Install PEAR packages
*********************

This step is similar to the installation on other Linux distros. Once you installed PEAR from the Package Center you can call it from the command line.

The following packages are required by LetoDMS:

* Auth_SASL
* HTTP_WebDAV_Server
* Log
* Mail
* Net_SMTP

Install these packages, then go to the next step.

Install additional packages
***************************

LetoDMS uses other small tools (for example the Slim Framework) to add some additional functionality. At the moment (Version 5.0.x) the list contains the following
tools:

* FeedWriter
* Slim
* parsedown

Copy the tools to a folder on your Synology. Using the console, copy the tools to **/volume1/@appstore/PEAR**.
Copy the whole folders as they are and do not change the structure. As the PEAR directory is already within
the PHP include path, no further configuration is required to get them working.

Fulltext Index
***************

If you do not intend to use the fulltext index, please skip this section and continue with the readme file to
install LetoDMS.

To create the fulltext index, LetoDMS needs to be able to convert the documents to text files to read the terms
out. Pdftotext is already available by default, so we just need to take care of the Microsoft Office formats.

For this guide, the following two tools have been selected:

docx2txt available from http://docx2txt.sourceforge.net/

xlsx2csv available from http://github.com/dilshod/xlsx2csv

Copy both files to your Synology.

**docx2txt**

This program runs without any kind of installation. Create a folder on your Synology and extract the contents of the archive.

In LetoDMS you can now configure the setting for Word documents to the path where you extracted the files in the step before. Point
to the docx2txt.sh file and you are done.

To make the configuration more simple you can add a symlink in **/usr/bin**. This will allow you to call docx2txt from any location of your Synology.
The symlink must point to docx2txt.sh to get it working. In LetoDMS you can now just configure docx2txt followed by any additional commands.

**xlsx2csv**

This one must be installed to get it working. The installation script is written in Python, so you need to get Python installed on your Synology.
As the version available from Synology does not properly work (you can't install PIP) it is strongly recommended to use OPKG or IPKG to install Python.

Install Python and PIP. Once completed, point to the directory where you copied xlsx2csv. Unpack the archive, then execute the installer (pip install xlsx2csv).

Once completed, xlsx2csv is available and can be configured within LetoDMS.

Complete the installation
*************************

Now you are ready to install LetoDMS and configure the database. Follow the README file to install LetoDMS.