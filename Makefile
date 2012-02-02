
SVN_WORK = $(HOME)/subversion-repo/wordpress-updateable/kcite/trunk
CP=cp

all:
	$(MAKE) -C .. kcite

publish_to_svn: 
	$(CP) kcite.php readme.txt license.txt $(SVN_WORK)
	$(CP) kcite-citeproc/citeproc.js \
		kcite-citeproc/kcite.js kcite-citeproc/kcite_locale_style.js \
	        kcite-citeproc/xmldom.js kcite-citeproc/xmle4x.js \
		$(SVN_WORK)/kcite-citeproc


citeproc:
	cd $(HOME)/subversion-repo/citeproc-js/ && ./test.py -B
	$(CP) $(HOME)/subversion-repo/citeproc-js/citeproc.js kcite-citeproc

test:  all
	wget -O test.html http://localhost/?p=52

test1:
	wget -O oai-arxiv.html http://export.arxiv.org/oai2?verb=GetRecord&identifier=oai:arXiv.org:0804.2273&metadataPrefix=arXiv