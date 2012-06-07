=== KCite ===

Contributors: philliplord, sjcockell, knowledgeblog, d_swan
Tags: references, citations, doi, crossref, pubmed, bibliography, pmid, res-comms, scholar, academic, science
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 1.6

A tool for producing citations and bibliographies in Wordpress posts.
Developed for the Knowledgeblog project (http://knowledgeblog.org).



== Description ==

Interprets the &#91;cite&#93; shortcode to produce citations from the
appropriate sources, also produces a formatted bibliography at the foot of the
post, with appropriate links to articles.

This plugin now uses multiple resources to retrieve metadata about the
references in question, including CrossRef, DataCite, arXiv, PubMed and
arbitrary URLs.


**Syntax**

DOI Example - &#91;cite source='doi'&#93;10.1021/jf904082b&#91;/cite&#93;

PMID example - &#91;cite source='pubmed'&#93;17237047&#91;/cite&#93;

Whichever 'source' is identified as the default (see Installation), will work
without the source attribute being set in the shortcode. so:

&#91;cite&#93;10.1021/jf904082b&#91;/cite&#93;

Will be interpreted correctly as long as DOI is set as the default metadata
source.

Kcite now supports DOIs from both [CrossRef](http://www.crossref.org) and
[DataCite](http://www.datacite.org). Identifiers from
[PubMed](http://www.pubmed.org) or [arXiv](http://www.arxiv.org) are directly
supported. URLs are supported via
[Greycite](http://greycite.knowledgeblog.org).


From Kcite 1.4, Citeproc-js
(https://bitbucket.org/fbennett/citeproc-js/wiki/Home) is used to render the
bibliography on the browser; the main visible change it that Author-Year
citation is used. There is now experimental support for reader switching. This
must be enabled in the settings page as it is off by default. 

Kcite is developed at http://code.google.com/p/knowledgeblog/ in Mercurial. To
contact the authors, please email knowledgeblog@googlegroups.com.

== Installation ==

1. Unzip the downloaded .zip archive to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Using the plugin settings page, set which identifier you want processed as the default.


== Upgrade Notice ==

= 1.6 =
Kcite now supports referencing of Arbitrary URLs. 

== Changelog ==

= 1.6.1 = 

1. Fixed problem with in-built render giving links of form [ITEM-8-1]
1. Improvements to presentation of in-built renderer
1. Citeproc rendering disabled in feeds, as many RSS readers cannot cope
1. Kcite can selects appropriate sources for bare URLs
1. Paged rendering to citeproc-js should allow large bibliographies in IE8,
   without timeouts. 

= 1.6 = 

1. Kcite no longer requires PHP libcurl, but will use it if present. 
1. Javascript rendering now happens asynchronously, reducing page load time. 
1. Options to control caching. 
1. Kcite can now reference URLs, using metadata from Greycite (greycite.knowledgeblog.org)

= 1.5.1 =

1. Fixed Version number in header

= 1.5 =

1. Kcite now requires the PHP libcurl support. You may need to install
   additional packages on your web server. 
1. From kcite 1.5, we have expanded the range of identifiers.
   DataCite DOIs and arXiv IDs are now supported. 
1. Crossref DOIs are now accessed via content negotiation. This should be less
   buggy, and reduce server load as it removes a parsing/data integration
   step. 
1. DataCite DOIs come via content negotiation also, although still require XML
   parsing. 
1. Bug fix to in kcite.js should fix an occasional rendering bug.
1. Both bibliography and intext citation are now linked. The underlying HTML
   is also linked, which should aid machine interpretability. 

= 1.4.4 =

1. Removed errant "w" from start of kcite.php

= 1.4.3 =

1. Proper release, after 1.4.2 release was confused.

= 1.4.2 = 

1. Javascript was being added when citeproc option was turned off.

= 1.4.1 =

1. Loads Javascript only when there is a bibliography.
1. Fixed bug in Crossref parser which treated editors as authors

= 1.4 = 

1. Introduction of citeproc rendering
1. New admin options
1. Move to GPLv3

= 1.3 = 
1. Fixed another regression caused by 1.2 fix. This should fix the error when
   there is no bibliography. 

= 1.2 = 
1. Sadly 1.1 had a regression error in it, which mean it didn't 
   fix the error in as reported. Additionally a print statement 
   was dumping a large amount of JSON to screen. Both of these errors
   should now be fixed. 
= 1.1 =
1. Fix for pages with more than one bibliography. 
   http://code.google.com/p/knowledgeblog/issues/detail?id=28
= 1.0 =
1. Full code refactoring from 0.1
1. Uses transients API
1. Support for arbitrary reference terms

== Upgrade Notice ==

= 1.5.2 =
1. Support for URLs through Greycite. 
= 1.4 =
1. Client side rendering of the bibliography. 
= 1.1 =
1. none critical bug fix release
= 1.0 =
1.0 release is fully refactored for speed and stability.

== Copyright ==

This plugin is copyright Phillip Lord, Simon Cockell and Newcastle University
and is licensed under GPLv3. Citeproc-js which is included is used under the
AGPLv3. 


