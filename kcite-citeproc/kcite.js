// instantiate the citeproc object
var citeproc = new CSL.Engine( sys, get_style() );

// TODO 

// First thing is that elementbyid clearly doesn't work and needs to be
// replaced by a class lookup. This should be easy but, apparently, isn't. 
// 
// Citation objects need to built on the fly, based on, erm, something. 
//
// bib string, we have. But I don't know how to hyperlink into this -- I think
// I am going to have to change the output format.
// 
// I may need to do this all asyncrhonously, but try to avoid doing that if we can. 

// modify the output format with, 
CSL.Output.Formats.kcite = CSL.Output.Formats.html;
CSL.Output.Formats.kcite[ "@bibliography/entry" ] = function (state, str) {
		return "  <div class=\"csl-entry\">" +
        "<a name=\"" + this.item_id + "\"></a>" +
        str + "</div>\n";
};

citeproc.setOutputFormat( "kcite" );

// we need to split this up a bit -- in the end, we will need to build the
// bibliography, and replace the citations separated, I think. 
var citation_count = 1;
while( citation_count <= kcite_intext_citation_count ){
    // get the relevant elements from the DOM of this document. 
    var citation_id = "kcite-citation-" + citation_count;
    var citation = document.getElementById( citation_id );
    
    var citation_object = {
        "citationItems": [
            { 
                "id": citation.getAttribute( "kcite-id" )
            }
        ],
        "properties":{
            "noteIndex": (citation_count - 1)
        }
    };
    
    // add in the citation and bibliography
    // fetch the citation. In this case, the citation to be included is hard 
    // coded.
    var citation_string = citeproc.
        appendCitationCluster( citation_object )[ 0 ][ 1 ];

    var tmp =  "<a href=\"#" + 
        citation.getAttribute( "kcite-id" ) + "\">" + 
        citation_string + "</a>";
    
    citation.innerHTML = tmp;

    citation_count++;
}


// fetch the formatting bib. The last call also populated the bib. 
var bib_items_list = citeproc.makeBibliography()[ 1 ];
var bib_string = "";
for( var i = 0; i < bib_items_list.length; i++ ){
    bib_string = bib_string + bib_items_list[ i ];
}
var bibliography = document.getElementById( "kcite-bibliography" );
bibliography.innerHTML = bib_string;


