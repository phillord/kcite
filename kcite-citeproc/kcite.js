
// modify the HTML output format so that the bibliography hyperlinks
CSL.Output.Formats.kcite = CSL.Output.Formats.html;
CSL.Output.Formats.kcite[ "@bibliography/entry" ] = function (state, str) {
    return "  <div class=\"csl-entry\">" +
        "<a name=\"" + this.item_id + "\"></a>" +
        str + "</div>\n";
};

jQuery.noConflict();
jQuery(document).ready(function($){

    var task_queue = [];
    
    $(".kcite-section").each(function(){
        
        var section_contains_unresolved = false;
        var section_contains_timeout = false;
        
        // hoping that I understand javascripts closure semantics
        var section_id = $(this).attr( "kcite-section-id" );
        var citation_data = kcite_citation_data[ section_id ];
        var sys = {
            retrieveItem: function(id){
                return citation_data[ id ];
            },
            
            retrieveLocale: function(lang){
                return locale[lang];
            }
        };
        
        // instantiate the citeproc object
        var citeproc = new CSL.Engine( sys, get_style() );
        
        // set the modified output format
        citeproc.setOutputFormat( "kcite" );
        
        // select all of the kcite citations
        $(this).find(".kcite").each( function(index){
            
            var cite = sys.retrieveItem( $(this).attr( "kcite-id" ) )

            // not sure about closure semantics with jquery -- this might not be necessary
            var kcite_element = $(this);

            if( cite["resolved"] ){
            
                // check here whether resolved == true before proceeding. 
                var citation_object = {
                    "citationItems": [
                        { 
                            "id": $(this).attr( "kcite-id" )
                        }
                    ],
                    "properties":{
                        "noteIndex": (index - 1)
                    }
                };
                
                // add in the citation and bibliography fetch the citation. In
                // this case, the citation to be included is hard coded.
                
                // TODO the citation object returned may include errors which we
                // haven't checked for here.
                
                
                task_queue.push( 
                    function(){
                        var citation_string = citeproc.
                            appendCitationCluster( citation_object )[ 0 ][ 1 ];
                                        
                        var citation =  "<a href=\"#" + 
                            kcite_element.attr( "kcite-id" ) + "\">" + 
                            citation_string + "</a>";
                        
                        kcite_element.html( citation );
                    });

            }
            // so we have an unresolved element
            else{
                var id = cite["source"] + ":" + cite["identifier"];
                // if this is a simple timeout
                if( cite[ "timeout" ] ){
                    task_queue.push(
                        function(){
                            var citation = "(" + id + " Timed Out)";
                            kcite_element.html( citation );
                        });                    
                    section_contains_timeout = true;
                }
                // there is some other error
                else{
                    task_queue.push(
                        function(){
                            var citation = "(" + id + " Unresolved)";                       
                            kcite_element.html( citation );
                        });
                    section_contains_unresolved = true;
                }
                
                
            }
        });
        
                
        var kcite_bib_element = $(this);
        
        task_queue.push( function(){
            // make the bibliography, and add all the items in.
            var bib_string = "";
            $.each( citeproc.makeBibliography()[ 1 ], 
                    function(index,item){
                        bib_string = bib_string + item
                    });
        
            
            if( section_contains_timeout ){
                bib_string = bib_string + '\
<p><a href="http://knowledgeblog.org/kcite-plugin/">Kcite</a> was unable to \
retrieve citation information for all the references, due to a timeout. This \
is done to prevent an excessive number of requests to the services providing \
this information. More references should appear on subsequent page views.</p>';
            }
            if( section_contains_unresolved ){
                bib_string = bib_string + '\
<p><a href="http://knowledgeblog.org/kcite-plugin/">Kcite</a> was unable to \
retrieve citation information for all the references. This could be because \
the identifier used is wrong, or not present in the remote databases.</p>';

            }

            // dump the bibliography into the document
            kcite_bib_element.find(".kcite-bibliography").html( bib_string );
        });

        

    });
    
    // now we have all the work in place, just need to run everything.
    var iter = function(){
        if( task_queue.length == 0 ){
            return;
        }
        
        // run next event
        task_queue.shift()();
        
        // tail-end recurse with timeout
        setTimeout( iter, 2 );
    };
    
    // and go.
    iter();
                             
});
