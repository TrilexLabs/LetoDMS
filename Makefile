VERSION=5.1.9
SRC=CHANGELOG inc conf utils index.php languages views op out controllers doc styles TODO LICENSE webdav install restapi pdfviewer
# webapp

NODISTFILES=utils/importmail.php utils/seedddms-importmail utils/remote-email-upload utils/remote-upload utils/da-bv-reminder.php utils/LetoDMS-da-bv-reminder .svn .gitignore styles/blue styles/hc styles/clean views/blue views/hc views/clean views/pca

EXTENSIONS := \
	dynamic_content.tar.gz\
	login_action.tar.gz\
	example.tar.gz

PHPDOC=~/Downloads/phpDocumentor-2.8.1/bin/phpdoc

dist:
	mkdir -p tmp/LetoDMS-$(VERSION)
	cp -a $(SRC) tmp/LetoDMS-$(VERSION)
	(cd tmp/LetoDMS-$(VERSION); rm -rf $(NODISTFILES); mv conf conf.template)
	(cd tmp;  tar --exclude=.svn --exclude=.gitignore --exclude=views/blue --exclude=views/hc --exclude=views/clean --exclude=styles/blue --exclude=styles/hc --exclude=styles/clean -czvf ../LetoDMS-$(VERSION).tar.gz LetoDMS-$(VERSION))
	rm -rf tmp

pear:
	(cd LetoDMS_Core/; pear package)
	(cd LetoDMS_Lucene/; pear package)
	(cd LetoDMS_Preview/; pear package)
	(cd LetoDMS_SQLiteFTS/; pear package)

webdav:
	mkdir -p tmp/LetoDMS-webdav-$(VERSION)
	cp webdav/* tmp/LetoDMS-webdav-$(VERSION)
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-webdav-$(VERSION).tar.gz LetoDMS-webdav-$(VERSION))
	rm -rf tmp

webapp:
	mkdir -p tmp/LetoDMS-webapp-$(VERSION)
	cp -a restapi webapp tmp/LetoDMS-webapp-$(VERSION)
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-webapp-$(VERSION).tar.gz LetoDMS-webapp-$(VERSION))
	rm -rf tmp

repository:
	mkdir -p tmp/LetoDMS-repository-$(VERSION)
	cp -a repository/www repository/utils repository/doc tmp/LetoDMS-repository-$(VERSION)
	mkdir -p tmp/LetoDMS-repository-$(VERSION)/files
	mkdir -p tmp/LetoDMS-repository-$(VERSION)/accounts
	cp -a repository/files/.htaccess tmp/LetoDMS-repository-$(VERSION)/files
	cp -a repository/accounts/.htaccess tmp/LetoDMS-repository-$(VERSION)/accounts
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-repository-$(VERSION).tar.gz LetoDMS-repository-$(VERSION))
	rm -rf tmp

dynamic_content.tar.gz: ext/dynamic_content
	tar czvf dynamic_content.tar.gz ext/dynamic_content

example.tar.gz: ext/example
	tar czvf example.tar.gz ext/example

login_action.tar.gz: ext/login_action
	tar czvf login_action.tar.gz ext/login_action

extensions: $(EXTENSIONS)

doc:
	$(PHPDOC) -d LetoDMS_Core --ignore 'getusers.php,getfoldertree.php,config.php,reverselookup.php' --force -t html

apidoc:
	apigen  generate -s LetoDMS_Core --exclude tests -d html

.PHONY: webdav webapp repository
