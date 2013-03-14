<?php

/*
 * The contents of this file are subject to the GPL License, Version 3.0.
 *
 * Copyright (C) 2013, Phillip Lord, Newcastle University
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class kcite_admin{
    
    function __construct(){
        add_action( "admin_menu", array( $this, "admin_page_init" ) );
    }

    function admin_page_init(){
        add_options_page( "Kcite Citations", "Kcite Citations",
                          "manage_options", "kcite",
                          array( $this, "plugin_options_menu" ) );
    }

    function plugin_options_menu(){
        if( !current_user_can('manage_options')){
            wp_die( __('You do not have sufficient permissions to access this page.'));
        }
        

        $nonce = wp_nonce_field( "kcite_citation_admin_save_action",
                                 "kcite_citation_admin_save_field",
                                 true, false );
        $this->table_head($nonce);

        if( wp_verify_nonce( $_POST["kcite_citation_admin_save_field"],
                             "kcite_citation_admin_save_action" )){
            echo "<i>Options Updated</i>\n";
            $this->admin_save();
        }

        

        $client_docs=<<<EOT
Rendering on the browser allows the references to be loaded asynchronously. 
However, the browser must be Javascript enabled. Kcite also contains an internal 
server-side renderer which it can use. This renderer is automatically used 
where the client in newfeeds such as RSS automatically.
EOT;

        
        $client_render_selected = get_option( "kcite_citation_render_client" ) ?
            "checked='true'":"";
        
        $this->admin_table_row
            ( "Render on client",
              $client_docs,
              "<input type='checkbox' name='kcite_citation_render_client' value='1' " .
              "$client_render_selected />"
              );


        $wait_docs=<<<EOT
For how long should kcite attempt to gather bibliographic information before
timing out. If you are using client side rendering, this will not slow page
delivery time. If you are using server side rendering it may. 
EOT;

        $timeout = get_option('kcite_citation_timeout');
        $this->admin_table_row
            ( "Reference Timeout",
              $wait_docs,
              "<input type='text' name='kcite_citation_timeout' " .
              "value='$timeout'/>" );


        $doi_selected = get_option('kcite_fallback_identifier') == 'doi' ?
            "selected='true'":"";
        $pubmed_selected = get_option('kcite_fallback_identifier') == 'pubmed' ?
            "selected='true'":"";
        
        $identifer_select=<<<EOT
<select name='kcite_fallback_identifier'>
<option value='doi' $doi_selected>DOI</option>
<option value='pubmed' $pubmed_selected>PubMed</option>
</select>
EOT;
        
        
        $this->admin_table_row
            ( "Fallback Identifier Type",
              "If it is not possible to determine in any other way, how should " .
              "an identifier be interpreted. This should be considered a legacy " .
              "option, and it is better not to depend on it.",
              $identifer_select );
        
        echo "<tr><td><strong>Greycite Options</strong></td></tr>";
        
        $greycite_permalink = get_option( "kcite_greycite_permalink" ) ?
            "checked='true'": "";
        
        $this->admin_table_row
            ( "Send Permalink to Greycite",
              "If true, permalinks are sent to Greycite. This is used for statistical " .
              "purposes, and is useful for debugging",
              "<input type='checkbox' name='kcite_greycite_permalink' value='1' $greycite_permalink>" );

        $greycite_private = get_option( "kcite_greycite_private" ) ?
            "checked='true'": "";

        $this->admin_table_row
            ( "Greycite Private",
              "By default, when asked for bibliographic information about a URL, Greycite " .
              "scans this URL. Setting this will mean Greycite only returns information it " .
              "already knows.",
              "<input type='checkbox' name='kcite_greycite_private' $greycite_private>" );
        
        echo "<tr><td><strong>Debug Options</strong></td></tr>";
        $this->admin_table_row
            ( "Invalidate Cache",
              "This is not normally necessary, as Kcite reloads references periodically.",
              "<input type='checkbox' name='kcite_invalidate' value='1'></input>" );
        
        $cache_references = get_option( "kcite_cache_references" ) ?
            "checked='true'": "";
        $this->admin_table_row
            ( "Cache References", 
              "Deselect for debugging purposes. This will have a significant impact on " .
              "performance where you have many references",
              "<input type='checkbox' name='kcite_cache_references' value='1' $cache_references/>" );
        
        $this->table_foot();
        
    }

    function table_head($nonce){
        // table head
        echo <<<EOT
<div class="wrap">
<h2>Kcite Citations by Kblog</h2>

<p>The defaults for these options should in most cases work perfectly
   well, and not need alteration. </p>

<form id="kcite_citation_admin" name="kcite_citation_admin" action="" method="POST">
$nonce
<table class="form-table">
EOT;
    }


    function table_foot(){
        //table foot
        echo <<<EOT

</table>

<input type="submit" value="Save Changes"/>
</form>

</div>
EOT;

    }

    function admin_table_row($head,$comment,$input){

        echo<<<EOT
        <tr>
<td style="width: 600px">$head<br/>
<font size="-2">$comment</font></td>
<td>$input</td>
</tr>
EOT;

    }


    function admin_save(){
        update_option('kcite_citation_render_client',
                      array_key_exists("kcite_citation_render_client",$_POST) );
        
        if(array_key_exists("kcite_citation_timeout",$_POST) &&
           is_numeric($_POST["kcite_citation_timeout"])){
            update_option('kcite_citation_timeout',$_POST["kcite_citation_timeout"]);
        }

        if(array_key_exists("kcite_fallback_identifier",$_POST)){
            update_option("kcite_fallback_identifier",$_POST["kcite_fallback_identifier"]);
        }
        
        update_option('kcite_greycite_permalink',
                      array_key_exists('kcite_greycite_permalink',$_POST));
        
        update_option('kcite_greycite_private',
                      array_key_exists('kcite_greycite_private',$_POST));

        
        if(array_key_exists('kcite_invalidate',$_POST)){
            print "<p><i>Cache Invalidated</i></p>";
            update_option("kcite_user_cache_version",time());
        }

        update_option('kcite_cache_references',
                      array_key_exists('kcite_cache_references',$_POST));

    }
    

}


function kcite_admin_init(){
    global $kcite_admin;
    $kcite_admin = new kcite_admin();
}


if( is_admin() ){
    kcite_admin_init();
}



?>