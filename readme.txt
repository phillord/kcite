=== Knowledgeblog Reference Management ===

Contributors: philliplord, sjcockell, knowledgeblog, d_swan
Tags: references, citations, doi, crossref, citeproc
Requires at least: 3.0
Tested up to: 3.0.1
Stable tag: 0.1

A reference management tool for Wordpress. Developed for the Knowledgeblog project (http://knowledgeblog.org)

== Description ==

Interprets the &#91;doi&#93; and &#91;pmid&#93; shortcodes to produce citations from the appropriate sources, also produces a formatted bibliography at the foot of the post, with appropriate links to articles.

It is planned to implement citeproc-js to format the bibliography, and provide user-facing tools to reformat on the fly.

It should also be possible to export RIS/BibTeX from individial posts.

== Installation ==

1. Unzip the downloaded .zip archive to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== TODO ==

1. Process [doi] and [pmid] shortcodes to provide inline references & bibliography. 
1. Produce citation XML for the above
1. Have that XML accessible from a URL (http://wordpress/post/bib.xml)
1. Produce CSL compatible JSON too.
1. Use citeproc-js to format that JSON into the bibliography.
1. Provide reader tools for reformatting bilbio.

== Copyright ==

This plugin is copyright Simon Cockell, Newcastle University and is licensed
under GPLv2. 
