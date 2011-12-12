
SVN_WORK = $(HOME)/subversion-repo/wordpress-updateable/kcite/trunk
CP=cp

all:
	$(MAKE) -C .. kcite

publish_to_svn: 
	$(CP) kcite.php readme.txt license.txt kcite-citeproc/citeproc.js \
		kcite-citeproc/kcite.js kcite-citeproc/kcite_locale_style.js \
	        kcite-citeproc/xmldom.js kcite-citeproc/xmle4x.js $(SVN_WORK)


citeproc:
	cd $(HOME)/subversion-repo/citeproc-js/ && ./test.py -B
	$(CP) $(HOME)/subversion-repo/citeproc-js/citeproc.js kcite-citeproc