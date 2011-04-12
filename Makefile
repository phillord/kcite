
SVN_WORK = $(HOME)/subversion-repo/wordpress-updateable/kcite/trunk
CP=cp

all:
	$(MAKE) -C .. kcite


publish_to_svn: 
	$(CP) kcite.php readme.txt license.txt $(SVN_WORK)