=== KCite ===

Contributors: philliplord, sjcockell, knowledgeblog, d_swan
Tags: references, citations, doi, crossref, pubmed, bibliography, pmid, res-comms, scholar, academic, science
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 1.4

A tool for producing citations and bibliographies in Wordpress posts. Developed for the Knowledgeblog project (http://knowledgeblog.org).

== Description ==

Interprets the &#91;cite&#93; shortcode to produce citations from the appropriate sources, also produces a formatted bibliography at the foot of the post, with appropriate links to articles.

The plugin uses the [CrossRef API](http://www.crossref.org/help/CrossRef_Help.htm) to retrieve metadata for Digital Object Identifiers (DOIs) and [NCBI eUtils](http://eutils.ncbi.nlm.nih.gov/) to retrieve metadata for PubMed Identifiers (PMIDs).

**Syntax**

DOI Example - &#91;cite source='doi'&#93;10.1021/jf904082b&#91;/cite&#93;

PMID example - &#91;cite source='pubmed'&#93;17237047&#91;/cite&#93;

Whichever 'source' is identified as the default (see Installation), will work without the source attribute being set in the shortcode. so:

&#91;cite&#93;10.1021/jf904082b&#91;/cite&#93;

Will be interpreted correctly as long as DOI is set as the default metadata
source.

From Kcite 1.4, Citeproc-js
(https://bitbucket.org/fbennett/citeproc-js/wiki/Home) is used to render the
bibliography on the browser; the main visible change it that Author-Year
citation is used. However, we hope that in later versions we will enable to
the reader to choose.

Kcite is developed at http://code.google.com/p/knowledgeblog/ in Mercurial. 


== Installation ==

1. Unzip the downloaded .zip archive to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Using the plugin settings page, set which identifier you want processed as the default (DOI or PMID).

== Changelog ==

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
