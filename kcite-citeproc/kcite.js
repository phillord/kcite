/*
  Copyright (c) 2011.
  Phillip Lord (phillip.lord@newcastle.ac.uk) and
  Newcastle University. 

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


// modify the HTML output format so that the bibliography hyperlinks
CSL.Output.Formats.kcite = CSL.Output.Formats.html;
CSL.Output.Formats.kcite[ "@bibliography/entry" ] = function (state, str) {
    return "  <div class=\"csl-entry\">" +
        "<a name=\"" + this.item_id + "\"></a>" +
        str + "</div>\n";
};

// kcite output is not hyperlinked or any such. These functions apply filters
// to make it better. As these are style specific they don't need to be
// clever, and can depend on the style details
var kcite_style_cleaner = {};
kcite_style_cleaner[ "author" ] = function(bib_item){
    // URL linkify here
    var http = bib_item.lastIndexOf("http");
    var url = bib_item.substring
    ( http,bib_item.lastIndexOf(".") );
    if( http == -1 ){
        return bib_item;
    }
    // we chopped off the close div, so need to add it back
    return bib_item.substring( 0, http ) +
        "<a href=\"" + url + "\">"
        + url + "</a>.</div>";
}


kcite_style_cleaner[ "numeric2" ] = function(bib_item){
    
    //return bib_item;
    var start_url = bib_item.lastIndexOf( "&#60;");
    var end_url = bib_item.lastIndexOf( "&#62;");
    if( start_url == -1 || end_url == -1 ){
        return bib_item;
    }
    // skip entity
    var start_url = start_url + 5;
    var url = bib_item.substring( start_url, end_url );
    return bib_item.substring( 0, start_url ) + 
        '<a href="' + url + '">' + url
        + '</a>' + bib_item.substring( end_url );
}

jQuery.noConflict();
jQuery(document).ready(function($){
    var kcite_controls_shown = false;

    var current_style = function(style){
        if( style ){
            kcite_default_style = style;
        }
        else{
            style = kcite_default_style;
        }
        
        return style;
    }

    var get_style = function(){
        return kcite_styles[ current_style() ];
    }



    var render = function(citation_data,kcite_section,kcite_section_id){

        var task_queue = [];
        
        var section_contains_unresolved = false;
        var section_contains_timeout = false;
        
        var sys = {
            retrieveItem: function(id){
                return citation_data[ id ];
            },
            
            retrieveLocale: function(lang){
                return kcite_locale[lang];
            }
        };
        
        
        // instantiate the citeproc object
        var citeproc = new CSL.Engine( sys, get_style() );
        
        // set the modified output format
        citeproc.setOutputFormat( "kcite" );
        
        // store all the ids that we are going to use. We register these with
        // citeproc, which should mean that references which would otherwise
        // be identical, can be disambiguated ("2011a, 2011b").
        var cite_ids = [];

        // select all of the kcite citations
        kcite_section.find(".kcite").each( function(index){

            var cite_id = $(this).attr( "kcite-id" );
            var cite = sys.retrieveItem( cite_id );
            // not sure about closure semantics with jquery -- this might not be necessary
            var kcite_element = $(this);
            

            if( cite["resolved"] ){
                cite_ids.shift( cite_id );
                //console.log( "push cite_id" + cite_id );
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
                        var cite_id = kcite_element.attr( "kcite-id" );
                        var cite = sys.retrieveItem( cite_id );

                        // the true here should mean that citeproc always
                        // returns only a single element array. It doesn't
                        // seem to work, as ambiguous cases still return more. 
                        //console.log( "appending cite" );
                        var citation = citeproc.
                            appendCitationCluster( citation_object, true );
                        //console.log( citation );
                        // citeproc's wierd return values. Last element is citation we want. 
                        // last element again is the HTML. 
                        var citation_string = citation.pop().pop();
                        
                        var citation =  "<a href=\"#" + 
                            cite_id + "\">" + 
                            citation_string + "</a>"
                            + "<a href=\"" + cite["URL"] + "\">*</a>";
                        
                        kcite_element.html( citation );
                    });

            }
            // so we have an unresolved element
            else{
                var cite = sys.retrieveItem( cite_id );
                var url = cite["URL"];
                var link = "(<a href=\"" + url + "\">" + url + "</a>)";
                
                // if this is a simple timeout
                if( cite[ "timeout" ] ){
                    task_queue.push(
                        function(){
                            kcite_element.html(
                                link + 
                                    "<a href=\"#kcite-timeout\">*</a>" );
                        });                    
                    section_contains_timeout = true;
                }
                // there is some other error
                else{
                    task_queue.push(
                        function(){
                            kcite_element.html(
                                link
                                    + "<a href=\"#kcite-unresolved\">*</a>" );
                        });
                    section_contains_unresolved = true;
                }
            }

        });
        
        // we have all the IDs now, but haven't calculated the in text
        // citations. So, we need to update citeproc to get the disambiguation
        // correct.
        task_queue.unshift( function(){
            // update citeproc with all the ids we will use (which will happen
            // when we tail recurse). this method call is a little problematic and
            // can cause timeout with large numbers of references

            //console.log( "update items with true" );
            citeproc.updateItems( cite_ids, true );
        });
        
        var kcite_bib_element = kcite_section;

        task_queue.push( function(){
            // make the bibliography, and add all the items in.
            var bib_string = "";

            $.each( citeproc.makeBibliography()[ 1 ], 
                    function(index,item){
                        if( kcite_style_cleaner[ current_style() ] ){
                            bib_string = bib_string +
                                kcite_style_cleaner[ current_style() ](item);
                        }
                        else{
                            bib_string = bib_string + item;
                        }
                    });
            
            
            if( section_contains_timeout ){
                bib_string = bib_string + 
                    '<p><a name="kcite-timeout"></a>' +
                    '<a href="http://knowledgeblog.org/kcite-plugin/">Kcite</a> was unable to ' +
                    'retrieve citation information for all the references, due to a timeout. This ' +
                    'is done to prevent an excessive number of requests to the services providing ' +
                    'this information. More references should appear on subsequent page views.</p>';
            }
            if( section_contains_unresolved ){
                bib_string = bib_string +
                    '<p><a name="kcite-unresolved"></a>' +
                    '<a href="http://knowledgeblog.org/kcite-plugin/">Kcite</a> was unable to ' +
                    'retrieve citation information for all the references. This could be because ' +
                    'the wrong identifier has been used, or it is not present in the remote ' +
                    'databases.</p>';

            }

            // dump the bibliography into the document
            kcite_bib_element.find(".kcite-bibliography").html( bib_string );
            var section_id;
            
            
            // switch on or off from kcite.php
            if( citeproc_controls ){
                // set up main div elements
                var control_outer = $('<div class="kcite-bibliography-control-outer"></div>');
                var control_inner = $('<div class="kcite-bibliography-control-inner"></div>' );
                
                control_inner.toggle( kcite_controls_shown );
                control_inner.appendTo( control_outer );
                
                var control = $("<button>Control</button>");
                control.button();
                control.click
                (function() 
                 { 
                     kcite_controls_shown = !kcite_controls_shown;
                     control_inner.toggle( kcite_controls_shown );
                 });
                control.prependTo( control_outer );
                
                var reload = $('<button>Reload</button>');
                reload.button();
                reload.click
                (function()
                 { load_bibliography(); });
                reload.appendTo( control_inner );

                var style = $('<div class="kcite-style">\
<input type="radio" name="kcite-style' + kcite_section_id + '">Author</input>\
<input type="radio" name="kcite-style' + kcite_section_id + '">Numeric</input>\
<input type="radio" name="kcite-style' + kcite_section_id + '">Numeric 2</input>\
</div>');
                style.buttonset();
                
                style.find(":radio").eq( 0 ).click(function(){
                    current_style("author");
                });
                
                if( current_style() == "author" ){
                    style.find(":radio").eq( 0 ).prop( "checked", "true" );
                }
                
                style.find(":radio").eq( 1 ).click(function(){
                    current_style( "numeric" );
                });
                
                if( current_style() == "numeric" ){
                    style.find(":radio").eq( 1 ).prop( "checked", "true" );
                }
                
                style.find(":radio").eq( 2 ).click(function(){
                    current_style( "numeric2" );
                });
                
                if( current_style() == "numeric2" ){
                    style.find(":radio").eq( 2 ).prop( "checked", "true" );
                }
                
                
                style.appendTo( control_inner );
                
                
                // insert into page
                control_outer.prependTo( kcite_bib_element.find(".kcite-bibliography") );
            }// end citeproc controls
        });

        
        // now we have all the work in place, just need to run everything.
        var iter = function(){

            if( task_queue.length == 0 ){
                return;
            }
            
            // run next event
            task_queue.shift()();
            
            // tail-end recurse with timeout 100 gap is a compromise. If
            // this is set higher rendering takes longer on all machines, too
            // low, and we get unresponsive script errors.
            setTimeout( iter, 200 );
            
        };

        iter();
    };

    var broken = function(kcite_section){
        // dump the bibliography into the document
        kcite_section.find(".kcite-bibliography").html( 
            '<p><a href="http://knowledgeblog.org/kcite-plugin/">Kcite</a> is unable \
to generate the references due to an internal error.\</p>'
        );

    };

    var load_bibliography = function(){
        $(".kcite-section").has( ".kcite-bibliography").each(function(){
            var kcite_section = $(this);
            var kcite_section_id = $(this).attr("kcite-section-id");
            $.ajax({
                // hmm, security trap here if we serve from localhost
                url:blog_home_url,
                data:{"kcite-p":kcite_section_id,
                      "kcite-format":"json"},
                type:'GET',
                dataType:'json',
                success:function(data){
                    render(data,kcite_section,kcite_section_id);
                },
                error:function(xhr,status){
                    broken(kcite_section);
                }
            });
        });
    }


    load_bibliography();
});


