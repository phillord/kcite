=== KCite ===

Contributors: philliplord, sjcockell, knowledgeblog, d_swan
Tags: references, citations, doi, crossref, pubmed, bibliography, pmid
Requires at least: 3.0
Tested up to: 3.0.4
Stable tag: 0.1

A tool for producing citations and bibliographies in Wordpress posts. Developed for the Knowledgeblog project (http://knowledgeblog.org).

== Description ==

Interprets the &#91;cite&#93; shortcode to produce citations from the appropriate sources, also produces a formatted bibliography at the foot of the post, with appropriate links to articles.

The plugin uses the [CrossRef API](http://www.crossref.org/help/CrossRef_Help.htm) to retrieve metadata for Digital Object Identifiers (DOIs) and [NCBI eUtils](http://eutils.ncbi.nlm.nih.gov/) to retrieve metadata for PubMed Identifiers (PMIDs).

**Syntax**

DOI Example - &#91;cite source='doi'&#93;10.1021/jf904082b&#91;/cite&#93;

PMID example - &#91;cite source='pubmed'&#93;17237047&#91;/cite&#93;

Whichever 'source' is identified as the default (see Installation), will work without the source attribute being set in the shortcode. so:

&#91;cite&#93;10.1021/jf904082b&#91;/cite&#93;

Will be interpreted correctly as long as DOI is set as the default metadata source.

== Installation ==

1. Unzip the downloaded .zip archive to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Using the plugin settings page, set which identifier you want processed as the default (DOI or PMID).

== TODO ==

This is the first release of KCite, and it is far from feature complete. Below are a list of planned improvements, further feature requests are welcome. 

1. Annotate bibliography with COiNS metadata.
1. It is planned to implement citeproc-js to format the bibliography.
1. Provide reader tools for reformatting bilbio.
1. It should also be possible to export RIS/BibTeX from individial posts.

== Copyright ==

This plugin is copyright Simon Cockell, Newcastle University and is licensed
under GPLv2. 
