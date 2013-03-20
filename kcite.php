<?php
  /*
   Plugin Name: Kcite
   Plugin URI: http://knowledgeblog.org/kcite-plugin
   Description: Add references and bibliography to blogposts
   Version: 1.6.3
   Author: Simon Cockell, Phillip Lord
   Author URI: http://knowledgeblog.org
   Email: knowledgeblog@googlegroups.com
   
   Copyright (c) 2010-13. Simon Cockell (s.j.cockell@newcastle.ac.uk)
   Phillip Lord (phillip.lord@newcastle.ac.uk)
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

require_once( dirname( __FILE__ ) . "/kcite-admin.php");

class KCite{
    
  static $entrez_slug="&email=knowledgeblog%40googlegroups.com&tool=kcite";

  static $bibliography;
  
  // store a cached version. In case of a mismatch, we ignore cache.
  //(progn (forward-line)(end-of-line)(zap-to-char -1 ?=)(insert "= " (number-to-string (float-time)))(insert ";"))
  static $kcite_cache_version = 1348841955.255003;

  // have we met any shortcodes, else block
  static $add_script = false;
  
  // block javascript and use fallback
  static $block_javascript = false;
  static $stubs = array
      (
       "doi" => "http://dx.doi.org/",
       "pubmed" => "http://www.ncbi.nlm.nih.gov/pubmed/",
       "arxiv" => "http://arxiv.org/abs/",
       "url" => "",
       );
  
  static $id_matchers = array
      (
       "doi" => array( "#http://dx\.doi\.org/(\S+)#", "#doi:(\S+)#" ),
       "pubmed" => array( "#http://www\.ncbi\.nlm\.nih\.gov/pubmed/(\S+)#", "#pubmed:(\S+)#" ),
       "arxiv" => array( "#http://arxiv\.org/abs/(\S+)#", "#arxiv:(\S+)#" ),
       "url" => array( "#(http\S+)#" ),
       );
  

  /**
   * Adds filters and hooks necessary initializiation. 
   */
  function init(){
    //add bibliography to post content
    // priority 12 is lower than shortcode (11), so can assure that this runs
    // after the shortcode filter does, otherwise, it is all going to work
    // very badly. 
    add_filter('the_content', array(__CLASS__,'bibliography_filter'),12 );
    
    // add a filter to specify the sections
    add_filter('the_content', 
               array(__CLASS__, 'bibliography_section_filter'),14 );
      

    add_shortcode( "cite",
                   array( __CLASS__, "cite_shortcode" ));

    add_shortcode( "fullcite",
                   array( __CLASS__, "fullcite_shortcode" ));

    add_action( 'wp_footer', 
                array( __CLASS__, 'add_script' ) );

    add_option( 'kcite_citation_render_client', true );
    add_option( 'kcite_citation_timeout', 60 );
    add_option( 'kcite_fallback_identifier', 'doi' );
    add_option( 'kcite_greycite_permalink', true );
    add_option( 'kcite_greycite_private', false );
    add_option( 'kcite_cache_references', true );
    add_option( 'kcite_user_cache_version', time() );

    add_filter('plugin_action_links', array(__CLASS__, 'refman_settings_link'), 9, 2 );

    // json download bib
    add_filter( "query_vars", 
                array( __CLASS__, "kcite_query_vars" ) );
    add_action( "template_redirect", 
                array( __CLASS__, "kcite_template_redirect" ) );
  }

  
  function kcite_query_vars( $query_vars ){
      $query_vars[] = "kcite-format";
      $query_vars[] = "kcite-p";
      return $query_vars;
  }

  function kcite_template_redirect(){
      global $wp_query;
      
      if( $wp_query->query_vars["kcite-format"] == "json"
          && $wp_query->query_vars[ "kcite-p" ] > 0 
          ){    

          $cites_array = self::cites_as_post_metadata
              ( (int)$wp_query->query_vars[ "kcite-p" ] );
          
          // no references
          if( count( $cites_array ) == 0 ){
              exit;
          }
          
          self::$bibliography = new Bibliography();
          self::$bibliography->section = $wp_query->query_vars[ "kcite-p" ];
          
          self::$bibliography->add_cites_array( $cites_array );
          $cites = self::resolve_metadata( self::$bibliography->get_cites() );
          $cite_json = self::citation_combine_json( $cites );
          print( $cite_json );

          exit;
      }
  }
  

  /**
   * Section filter -- defines a section header
   */
  function bibliography_section_filter($content){
      $postid = get_the_ID();
      return 
          "<div class=\"kcite-section\" kcite-section-id=\"$postid\">
$content
</div> <!-- kcite-section $postid -->";
  }

  function javascript_render_p(){
      return get_option( "kcite_citation_render_client" ) && !self::$block_javascript;
  }

  function get_timeout(){
      // if we have blocked javascript, we probably waiting for a human
      if( self::$block_javascript ){
          return 2;
      }

      if( is_feed() ){
          return 30;
      }
      
      return get_option( 'kcite_citation_timeout' );
  }


  function add_script(){
      echo "<!-- Kcite Plugin Installed";

      if( !self::$add_script ){
          echo ": Disabled as there are no shortcodes-->\n";
          return;
      }
      echo "-->\n";
      
      if( self::javascript_render_p() ){
      
          // load enqueue the scripts
          //          wp_enqueue_script( "xmle4x", plugins_url( "kcite-citeproc/xmle4x.js", __FILE__ ), false, null, true );
          wp_enqueue_script( "xmldom", plugins_url( "kcite-citeproc/xmldom.js",__FILE__  ), false, null, true );
          wp_enqueue_script( "citeproc", plugins_url( "kcite-citeproc/citeproc.js",__FILE__  ), false, null, true );
          wp_enqueue_script( "jquery" );
          wp_enqueue_script( "jquery-ui-core" );
          wp_enqueue_script( "jquery-ui-widget" );
          wp_enqueue_script( "jquery-ui-button");
          //wp_enqueue_script( "jquery.cookie", plugins_url( "kcite-citeproc/jquery.cookie.js",__FILE__  ), false, null, true );
          wp_enqueue_script( "kcite_locale_style", 
                             plugins_url( "kcite-citeproc/kcite_locale_style.js", __FILE__  ), false, null, true );
          wp_enqueue_script( "kcite", plugins_url( "kcite-citeproc/kcite.js",__FILE__  ), false, null, true );
          
          // and print them or they won't be printed because the footers already done
          // not sure that we need this any more
          //wp_print_scripts( "xmle4x" );
          wp_print_scripts( "xmldom" );
          wp_print_scripts( "citeproc" );
          wp_print_scripts( "jquery" );
          wp_print_scripts( "jquery-ui-core" );
          wp_print_scripts( "jquery-ui-widget");
          wp_print_scripts( "jquery-ui-button" );
          wp_print_scripts( "jquery.cookie" );
          wp_print_scripts( "kcite_locale_style" );
          wp_print_scripts( "kcite" );
      }
  }

  function instantiate_bibliography(){
      // lazy instantiate bib
      if( !isset( self::$bibliography ) ){
          self::$bibliography = new Bibliography();
          self::$bibliography->section = get_the_ID();
      }
  }


  function fullcite_shortcode($atts,$content){
      self::$add_script = true;
      self::instantiate_bibliography();

      // store citation in bibliography. Replace anchor. 
      $cite = new Citation();
      $cite->source="inline";
      // need a fake identifier which enables us to test if anything has changed. 
      $cite->identifier= md5( $atts["author"] . $atts["title"] . $atts["date"] . $atts["location"] );
      $cite->resolution_source = $atts;

      return self::add_citation_to_bibliography( $cite );
  }

  /**
   * citation short code
   */

  function cite_shortcode($atts,$content)
  {

      // we have a short code, so remember this for later
      self::$add_script = true;

      // extract attributes as local vars
      extract( shortcode_atts
               ( 
                array(
                      "source" => false,
                      ), $atts ) );

      self::instantiate_bibliography();

      // store citation in bibliography. Replace anchor. 
      $cite = new Citation();
    
      $cite->identifier=$content;

      // TODO -- really need to fix this bit to recognise certain sources,
      // in particular all the URL based ones. 
      if( !$source ){
          // let's try guessing
          foreach( self::$id_matchers as $id_type => $regexps ){
              foreach( $regexps as $regexp ){
                  $i = preg_match( $regexp, $cite->identifier, $matches );
                  if( $i > 0 ){
                      $source = $id_type;
                      $cite->identifier = $matches[ 1 ];
                      break 2;
                 }
              }
          }
      }
      
      // still not set? take default and hope.
      if( !$source ){
          $source = get_option("kcite_fallback_identifier");
      }

      $cite->source=$source;
      $cite->tagatts=$atts;
      
      return self::add_citation_to_bibliography( $cite );
  }


  function add_citation_to_bibliography( $cite ){

      if( !self::javascript_render_p() || is_feed() ){
          $citation = self::$bibliography->add_cite( $cite );
          $anchor = $citation->anchor;
          $bibindex = $citation->bibindex;
          return 
              "<span id=\"cite_$anchor\" name=\"citation\">" .
              "<a href=\"#$anchor\">[$bibindex]</a></span>";
      }
      else{
          $stubs = self::$stubs;
          $url = "$stubs[$source]{$cite->identifier}";
          $in_text = "<a href=\"$url\">$url</a>";
          $anchor = self::$bibliography->add_cite( $cite )->anchor;
          return "<span class=\"kcite\" kcite-id=\"$anchor\">($in_text)</span>";
      }
  }

  function bibliography_filter($content) {
      $bib_html = self::get_html_bibliography();
      // delete the bib -- or it will appear on subsequent posts
      self::$bibliography = null;

      return $content . $bib_html;
  }

  function cites_as_post_metadata( $postid, $bibliography=false ){

      // last parameter means "single" -- that is get the single value
      // that is a serialized array. We have added multiple post-metadata
      // elements, but the order isn't consistent in my hands, and that 
      // is important here. 
      $metadata_cites = get_post_meta( $postid, "_kcite-cites", true );
      
      // get_post_meta returns an empty string if there is no metadata, which
      // will happen if the post has no references. We are expecting an array (wp normally
      // deserializes for us), so replace with an array. 
      if( !is_array( $metadata_cites ) ){
          $metadata_cites = array();
      }

      // there is no bibliography, so we can return what should be an empty array at this point. 
      if( !$bibliography ){
          return $metadata_cites;
      }

      $cites_changed = false;
      // get the real bibliography
      $cites_array = $bibliography->get_cites_array();

      // if the number of references have changed then so has the bibliography
      if( count($cites_array) != $metadata_cites ){
          $cites_changed = true;
      }
      else{
          for( $i = 0;$i < count($cites_array);$i++ ){  
              if( (! array_key_exists( $i, $metadata_cites ) ) || 
                  $cites_array[ $i ][ 0 ] != $metadata_cites[ $i ][ 0 ] || 
                  $cites_array[ $i ][ 1 ] != $metadata_cites[ $i ][ 1 ] ){
                  $cites_changed = true;
                  break;
              }
          }
      }

      if( $cites_changed ){
          delete_post_meta( $postid, "_kcite-cites" );
          add_post_meta( $postid, "_kcite-cites",
                         $cites_array );
          return get_post_meta( $postid, "_kcite-cites", true );
      }
      
      return $metadata_cites;
  }

  function get_html_bibliography(){
      
      // check the bib has been set, otherwise there have been no cites. 
      if( !isset( self::$bibliography ) ){ 
          return "<!-- kcite active, but no citations found -->";
      }
      
      $cites = self::$bibliography->get_cites();
      
      $postid = get_the_ID();
      $temp = self::cites_as_post_metadata( $postid, self::$bibliography );
      

      if( !self::javascript_render_p() || is_feed() ){
          
          // get the metadata which we are going to use for the bibliography.
          $cites = self::resolve_metadata($cites);
          
          // it would make more sense to operate this over the citation
          // objects rather than the JSON, but this was coded as a quick way
          // of rendering after citeproc-js turned out to be a lot of work.
          // Now this is meant to be a fall back, and it will need recoding at
          // some point, but it's not urgent.
          $bibliography = 
              self::build_bibliography
              ( self::citation_combine( $cites ) );
          
          return $bibliography;
      }
      
      $home_url = home_url();
      
      $script = <<<EOT

<h2>Bibliography</h2>
<div class="kcite-bibliography"></div>
<script type="text/javascript">var citeproc_controls=false;
var blog_home_url="$home_url"
</script>

EOT;

      return $script;
  }

  /**
   * Builds the HTML for the bibliography. 
   *
   * Array contains the citation objects as JSON translated into a PhP array.
   *
   */
  private function build_bibliography($pub_array) {

      $i = 1;
      $bib_string = "<h2>References</h2>
    <ol>
    ";
      $temp = strval( $pub_array );
      
      foreach ($pub_array as $pub) {
          
          $anchor = "<a name='". $pub['id'] . "'></a>";
          
          if( array_key_exists( "timeout", $pub ) ){
              if( array_key_exists( "source", $pub ) ){
                      $source = $pub[ "source" ] . ":";
              }
              else{
                  $source = "";
              }
                          
              $bib_string .= 
                  "<li>$anchor" . $source . $pub["identifier"] .
                  " <i>(Timed out)</i></li>\n";
              $i++;
              continue;                 
          }

          if (array_key_exists( "error", $pub ) && $pub['error']){
              
              //sufficient missing to assume no publication retrieved...
              if (array_key_exists( "DOI", $pub ) && $pub['DOI']) {
                  $bib_string .= "<li>$anchor<a href='http://dx.doi.org/".
                      $pub['DOI']."'>DOI:".$pub['DOI'].
                      "</a> <i>(KCite cannot find metadata for this paper)</i></li>\n";
              }
              if (array_key_exists( "PMID", $pub ) && $pub['PMID']) {
                  $bib_string .= "<li>$anchor<a href='http://www.ncbi.nlm.nih.gov/pubmed/"
                      .$pub['PMID']."'>PMID:".$pub['DOI'].
                      "</a> <i>(KCite cannot find metadata for this paper)</i></li>\n";
              }
          }
          else {
              
              $bib_string .= "<li>$anchor
";

              //
              // author
              // 
              if( array_key_exists( "author", $pub ) ){
                  $author_count = 1;
                  $author_total = count($pub['author']);
                  foreach ($pub['author'] as $author) {
                      
                      $author_string = "";
                      // this is how citeproc from data cite comes
                      if( array_key_exists( "literal", $author ) ){
                          $author_string = $author["literal"] . "., ";
                      }
                      else{
                          //get author initials
                          $firsts = $author['given']; 
                          $words = explode(' ', $firsts);
                          $initials = "";
                          foreach ($words as $word) {
                              $initials .= strtoupper(substr($word,0,1)).".";
                          }
                          
                          $author_string = $initials." ".$author['family'].", ";
                      }
                      
                      $bib_string .= $author_string;
                      
                      if ($author_count == ($author_total - 1)) {
                          $bib_string .= "and ";
                      }
                      $author_count++;
                  }
              }

              // 
              // title
              //
              if (array_key_exists( "title", $pub) ){
                  $bib_string .= '"'.$pub['title'].'"';
              }
              if ($pub['container-title']) {
                  $bib_string .= ', <i>'.$pub['container-title'].'</i>';
              }
              if (array_key_exists("volume", $pub)){
                  $bib_string .= ', vol. '.$pub['volume'];
              }
              
              if (array_key_exists("page", $pub) ) {
                  $bib_string .= ', pp. '.$pub['page'];
              }


              if (array_key_exists("issued", $pub)){
                  if(array_key_exists("date-parts", $pub["issued"])){
                      if(array_key_exists( 0, $pub["issued"]["date-parts"])){
                          if(array_key_exists( 0, $pub["issued"]["date-parts"][0])){
                              $bib_string .= ', '.$pub['issued']['date-parts'][0][0] . ". ";
                          }
                      }
                  }
                  
                  if(array_key_exists("raw", $pub["issued"] ) ){
                      $bib_string .= ", " . $pub["issued"]["raw"] . ". ";
                  }
              }
               $bib_string .= '<a href="' . $pub["URL"] . '">' . $pub["URL"] . '</a>';
              $bib_string .= "


</li>
";
          }
          $i++;
      }
      $bib_string .= "</ol>
";

      if( self::$bibliography->contains_timeout ){
          $bib_string .= <<<EOT
<p><a href="http://knowledgeblog.org/kcite-plugin/">Kcite</a> was unable to 
retrieve citation information for all the references, due to a timeout. This
is done to prevent an excessive number of requests to the services providing
this information. More references should appear on subsequent page views</p>

EOT;
      }

      return $bib_string;
  }
  
  
  /**
   * Expands citation objects to include full details. 
   * This can be used to build the JSON. 
   */
  private function resolve_metadata($cites) {
      
      $start_time = time();
      
      // short timeout for feed, option for page
      $timeout = self::get_timeout();
      

      foreach ($cites as $cite) {
          
          //print( "resolve metadata {$cite->source}:{$cite->identifier}\n" );
          
          // check whether this is all taking too long
          if( time() - $start_time > $timeout ){
              $cite->error = true;
              $cite->timeout = true;
              self::$bibliography->contains_timeout = true;
              //print( "resolve timeout {$cite->source}:{$cite->identifier}\n" );
              continue;          
          }
          
          // check whether we have a cached version
          // if so we are sorted
          $slug = self::transient_slug( $cite );
          $cache = get_option( $slug );

          if( get_option( "kcite_cache_references" ) && $cache ){
              if( array_key_exists( "kcite_cache_version", $cache ) &&
                  $cache[ "kcite_cache_version" ] == self::$kcite_cache_version &&
                  array_key_exists( "kcite_cache_user_version", $cache ) &&
                  $cache[ "kcite_cache_user_version" ] == 
                  get_option( "kcite_user_cache_version" )
                  ){
                  //print( "Accepted cache\n" );
                  $cite->json = $cache;
              }
          }

          // take the internal cached version from the json which might be nil
          $cache = $cite->json;
          // the cache exists and has not expired
          if( $cache && 
              array_key_exists( "kcite_cache_expiretime", $cache ) &&
              (intval( $cache[ "kcite_cache_expiretime" ] ) > time()) ){
              
              $cite->resolved = true;
              $time = time();
              continue;
          }
       
          if($cite->source=="inline"){
              // all of this is fake and needs to parse the vars above.
              // $cite->resolution_source data
              $atts=$cite->resolution_source;
              $authors_explode = explode( ";", $atts["author"] );

              foreach( $authors_explode as $auth ){
                  $author_comps = explode( ".", $auth );
                  $author_array = array();
                  $author_array['surname'] = $author_comps[ 0 ];
                  $author_array['given_name'] = $author_comps[ 1 ];
                  $cite->authors[] = $author_array;
              }

              $cite->journal_title = $atts["location"];
              $cite->title = $atts["title"];
              $date_array = array();
              $cite->pub_date['year'] = $atts["date"];



              $cite = self::citation_generate_json( $cite );
              $cite->resolved = true;
          }

          if ($cite->source == 'doi') {
              // should work on datacite or crossref lookup
              $cite = self::dx_doi_lookup($cite);
              
              if($cite->resolved && $cite->resolved_from=="dx-doi" ){
                  $cite = self::get_crossref_metadata($cite);
              }
          }
          
          if ($cite->source == 'pubmed') {
              $cite = self::pubmed_id_lookup($cite);
              if ($cite->resolved) {
                  $cite = self::get_pubmed_metadata($cite);
              }
          }

          if( $cite->source == "arxiv"){
              $cite = self::arxiv_id_lookup($cite);
              if($cite->resolved){
                  $cite = self::get_arxiv_metadata($cite);
              }
          }

          // resolve these from metadata given in cite tag
          if( $cite->source == "url" ){
              $cite = self::greycite_uri_lookup($cite);
              if($cite->resolved){
                  $cite = self::get_greycite_metadata($cite, $fh);
              }
          }
          
          if( !$cite->resolved ){
              if ( $cite->json ){
                  // we have not managed to resolve from the sources
                  // but we do have a cache, albeit one that is old
                  // so use this, but the metadata as stale
                  $cite->resolved = true;
                  $cite->stable = true;
                  continue;
              }
              // if we don't recognise the type then we have an error
              $cite->error = true;
          }
      }
      
      return $cites;
  }
 
  private function transient_slug($cite)
  {
      // slug has to be 45 chars or less
      return "kcite" . crc32( $cite->source . $cite->identifier );
  }
 

  /**
   * Attempt to resolve metadata for a citation object
   * @param string $pub_doi A doi representing a reference
   * @return 
   */
  private function dx_doi_lookup($cite) {

      $url = "http://dx.doi.org/{$cite->identifier}";
      
      $params = array(
                      // the order here is important, as both datacite and crossrefs content negotiation is broken. 
                      // crossref only return the highest match, but do check other content
                      // types. So, should return json. Datacite is broken, so only return the first
                      // content type, which should be XML.
                      
                      // datacite now returns JSON, so this should be much simpler

                      'headers' => 
                      array( 'Accept' => 
                             "application/citeproc+json"),
                      );
      
      
      $wpresponse = wp_remote_get( $url, $params );

      //print( $url );
      //print( "wp response" );
      //print_r( $wpresponse );

      if( is_wp_error( $wpresponse ) ){
          return $cite;
      }
      
      $response = wp_remote_retrieve_body( $wpresponse );
      $status = wp_remote_retrieve_response_code( $wpresponse );
      $headers = wp_remote_retrieve_headers( $wpresponse );
      $contenttype = $headers["content-type"];
      
      $cite->response_code = $status;
      
      // it's probably not a DOI at all. Need to check some more here. 
      if( $status == 404 ){
          return $cite;
      }            

      if( $contenttype == "application/citeproc+json" ){
          // crossref DOI
          $cite->resolved = true;
          $cite->resolution_source=$response;
          $cite->resolved_from="dx-doi";
          
          return $cite;
      }
              
//       if( $contenttype == "application/x-datacite+xml" ){
//           //datacite DOI
//           $cite->resolved = true;
//           $cite->resolution_source=$response;
//           $cite->resolved_from="datacite";
// 
//           return $cite;
//       }

      // so it's a DOI which is neither datacite nor crossref -- we should turn this into a URL, therefore. 
      return $cite;
  }

  
  /**
   * Look up pubmed ID on pubmed
   * @param Citation the citation to resolve
   * @return null if DOI does not resolve, or raw pubmed XML
   */
  private function pubmed_id_lookup($cite) {

      $url = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?"
          . self::$entrez_slug . "&db=pubmed&retmode=xml&id="
          .$cite->identifier;
      
      
      $wpresponse = wp_remote_get( $url );

      if( is_wp_error( $wpresponse ) ){
          return $cite;
      }
      
      $status = wp_remote_retrieve_response_code( $wpresponse );
      
      $cite->response_code = $status;
      if( $status != 200 ){ 
          return $cite;
      }
      
      $xml = wp_remote_retrieve_body( $wpresponse );
      

      if (preg_match('/(Error|ERROR)>/', $xml)) {
          //handles fetch failure
          return $cite;
      }
      
      $cite->resolved = true;
      $cite->resolution_source = $xml;
      $cite->resolved_from = "pubmed";
      
      return $cite;
  }


  private function arxiv_id_lookup($cite){
      
      //print( "fetching arxiv" );
      
      $url = "http://export.arxiv.org/oai2?verb=GetRecord&identifier=oai:arXiv.org:" 
          . $cite->identifier . "&metadataPrefix=arXiv";
      
      $wpresponse = wp_remote_get( $url, $params );
      
      //print( $url . "\n" );
      //print_r( $wpresponse );

      if( is_wp_error( $wpresponse ) ){
          return $cite;
      }
      
      $status = wp_remote_retrieve_response_code( $wpresponse );
      
      $cite->response_code = $status;
      
      if( $status != 200 ){ 
          return $cite;
      }
      
      $xml = wp_remote_retrieve_body( $wpresponse );
      
      $cite->resolved = true;
      $cite->resolution_source = $xml;
      $cite->resolved_from = "arxiv";
      
      return $cite;
  }
  private function greycite_uri_lookup($cite){

      $url = "http://greycite.knowledgeblog.org/json?uri=" . $cite->identifier;
      
      if( get_option( "kcite_greycite_private" ) ){
          $params = array
              (
               'headers' => 
               array( 'X-greycite-no-store' => 'true' )
               );
      }
      else{
          if( get_option( "kcite_greycite_permalink" ) ){
              $params = array
                  (
                   'headers' => 
                   array( 'X-greycite-permalink' => get_permalink( $cite->bibliography->section ) )
                   );
          }
      }
      
      $wpresponse = wp_remote_get( $url, $params );

      if( is_wp_error( $wpresponse ) ){
          return $cite;
      }
      
      $status = wp_remote_retrieve_response_code( $wpresponse );
      
      $cite->response_code = $status;

      if( $status != 200 ){ 
          return $cite;
      }
      
      $response = wp_remote_retrieve_body( $wpresponse );

      // greycite worked
      $cite->resolved = true;
      $cite->resolution_source=$response;
      $cite->resolved_from="greycite";
      
      return $cite;
  }
  

  /**
   * Parses XML in a citation object into a PhP array
   * @param Citation object
   * @return Citation with parsedXML now containing SimpleXMLElement object
   */
  private function parse_xml($cite) {
      $cite->parsedXML = new SimpleXMLElement( $cite->resolution_source );
      return $cite;
  }
  
  
  /**
   * @param array of Citation objects
   * @return php array ready for JSON encoding
   */
  private function citation_combine($cites){
      
      $citep = array();
      
      $item_number = 1;
      $cite_length = count($cites);
      
      foreach ($cites as $cite) {
          $item_string = $cite->anchor;
          
          // take the json and combine it
          if( $cite->json ){
              
              $item = $cite->json;
              
              // add in the ID string
              $item["id"] = "$item_string";
              $item["resolved"] = true;
              
              // we finished!
              $citep[ $item_string ] = $item;
              
              continue;
          }

          // we should now be in an error condition, so generate some temporary JSON with no metadata. 
          $item = array();
          
          $item["source"] = $cite->source;
          $item["identifier"] = $cite->identifier;
          $item["resolved"] = $cite->resolved;
          $item["id" ] = "$item_string";
          $item["URL"] = self::$stubs[$cite->source] . $cite->identifier;

          // timed out overall, so don't have the metadata
          if( $cite->timeout ){
              $item["timeout"] = true;
          }
          
          // there was an error of some sort (normally no metadata)
          if( $cite->error ){
              $item["error"] = true;
              $item["response-code"] = $cite->response_code;
          }
          
          // just didn't resolve
          $citep[ $item_string ] = $item;
      }

      return $citep;
  }

  private function citation_generate_json( $cite )
  {
      
      $item = array();
      $item[ "title" ] = $cite->title;
          
      $authors = array();
          
      foreach ($cite->authors as $author) {
              
          $auth = array();
          $auth["family"] = $author[ "surname" ];
          $auth["given"]  = $author[ "given_name" ];
          $authors[] = $auth;
      }
      $item[ "author" ] = $authors;
      
      $item[ "container-title" ] = $cite->journal_title;
      
      // dates -- only if we have year
      if( $cite->pub_date[ 'year' ] ){
          $issued = array();
          $date_parts = array();
          $date_parts[] = (int)$cite->pub_date[ 'year' ];
          // month and day if existing or nothing
         
          if(array_key_exists( "month", $cite->pub_date)){
              $date_parts[] = (int)$cite->pub_date[ 'month' ];
          }
          if(array_key_exists( "day", $cite->pub_date)){
              $date_parts[] = (int)$cite->pub_date[ 'day' ];
          }
          
          $issued[ "date-parts" ] = array( $date_parts );
          $item[ "issued" ] = $issued;
      }
      
      if($cite->first_page){
          $item[ "page" ] = 
              $cite->first_page . "-" . $cite->last_page;
      }
      
      if( $cite->volume ){
          $item[ "volume" ] = $cite->volume;
      }
      
      if( $cite->issue ){
          $item[ "issue" ] = $cite->issue;
      }
      
      if( $cite->reported_doi ){
          $item[ "DOI" ] = $cite->reported_doi;
      }
      
      $item[ "type" ] = "article-journal";
      
      if( $cite->url ){
          $item["URL"] = $cite->url;
      }

      $cite->json = $item;
      
      // now that we have made this JSON, we should cache it. 
      self::cache_json( $cite );
      
      return $cite;
  }
      
  private function cache_json( $cite, $expiretime=-1 ){
      
      // cache if we need to 
      if( get_option( "kcite_cache_references" ) ){
          $cite->json[ "kcite_cache_version" ] = self::$kcite_cache_version;
          $cite->json[ "kcite_cache_user_version" ] = 
              get_option( "kcite_user_cache_version" );
          $slug = self::transient_slug( $cite );
          //print( "caching" . $cite->source . ":" . $cite->identifier . "\n");
          
          // if no expire time is set, make it a month with one day random 
          // variation to stop everything expiring at once. 
          if( $expiretime == -1 ){
              //$expiretime = 30;
              $expiretime = 60*60*24*28 + rand( 0, 60*60*24 );
          }
          
          $cite->json[ "kcite_cache_expiretime" ] = time() + $expiretime;

          // we do not want to set this to autoload cause that will be slow
          delete_option( $slug );
          add_option( $slug, $cite->json, '', 'no' );
      }
  }
  
  /**
   * @param array of Citation objects
   * @return JSON encoded string for citeproc
   */
  private function citation_combine_json( $cites ){
      $citep = self::citation_combine( $cites );

      // crude hack --- http://bugs.php.net/bug.php?id=49366 PHP escapes all /
      // which I think is going to stop things later on (although I haven't
      // checked this yet. JSON_UNESCAPED_SLASHES is the way forward when it
      // gets into PHP.
      return str_replace('\\/', '/', json_encode( $citep ) );
  }



  /**
   * @param Citation $cite crossref resolved citation
   * @return Citation with metadata extracted
   */

  // now misnamed as it works with datacite also
   private function get_crossref_metadata($cite) {
       
       // we get back JSON from crossref. Unfortunately, we need to combine it
       // with other json from other sources, and fiddle with it a bit, so we need to
       // decode it here, then re-encode it later.
       $json_decoded = json_decode( $cite->resolution_source, true );

       // crossref returns both url and raw DOI. We don't need the later, so delete it. 
       unset( $json_decoded[ "DOI" ] );
       
       $json_decoded["source"] = $cite->source;
       $json_decoded["identifier"] = $cite->identifier;
       $json_decoded["resolved"] = $cite->resolved;
       
       $cite->json = $json_decoded;

       self::cache_json( $cite );
       return $cite;
   }

   /**
    * @param string $article returns metadata object from SimpleXMLElement
    * @return metadata associative array
    */
   private function get_pubmed_metadata($cite) {

       $cite = self::parse_xml( $cite );

      // actually an article set -- so this code should generalize okay
      $article = $cite->parsedXML;
      

      $issueN = $article->xpath( "//Article/Journal/JournalIssue/Issue" );
      if( count( $issueN ) > 0 ){
          $cite->issue = (string)$issueN[ 0 ];
      }
      
      $journal_titleN = $article->xpath( "//Journal/Title" );
      if( count( $journal_titleN ) > 0 ){
          $cite->journal_title = (string)$journal_titleN[ 0 ];
      }
      
      $volN = $article->xpath( "//Journal/Volume" );
      if( count( $volN ) > 0 ){
          $cite->volume = (string)$volN[ 0 ];
      }

      $abbrN = $article->xpath( "//Journal/ISOAbbreviation" );
      if( count( $abbrN ) > 0 ){
          $cite->abbrv_title = (string)$abbrN[ 0 ];
      }
      
      $artN = $article->xpath( "//ArticleTitle" );
      if( count( $artN ) > 0 ){
          $cite->title = (string)$artN[ 0 ];
      }

      $authN = $article->xpath( "//AuthorList/Author" );
      foreach($authN as $author){
            $newauthor = array();
            $newauthor['given_name'] = (string)$author->ForeName;
            $newauthor['surname'] = (string)$author->LastName;
            
            $cite->authors[] = $newauthor;
      }
      
      $artDN = $article->xpath( "//ArticleDate" );

      // Untested -- handle missing date parts later. 
      if( count( $artDN ) == 0 ){
          $artDN = $article->xpath( "//JournalIssue/PubDate" );
      }
      
      if( count( $artDN ) > 0 ){
          $cite->pub_date[ 'month' ] = (string)$artDN[ 0 ]->Month;
          $cite->pub_date[ 'day' ] = (string)$artDN[ 0 ]->Day;
          $cite->pub_date[ 'year' ] = (string)$artDN[ 0 ]->Year;
      }
      
      $elocN = $article->xpath( "//ELocationID" );
      if( count( $elocN ) > 0 ){
          $cite->reported_doi = (string)$elocN[ 0 ];
      }
      
      $cite->url = "http://www.ncbi.nlm.nih.gov/pubmed/{$cite->identifier}";

      return self::citation_generate_json( $cite );
  }
  

   private function get_arxiv_metadata($cite){
       
       $cite = self::parse_xml( $cite );

       $article = $cite->parsedXML;
       $article->registerXpathNamespace( "ar", "http://arxiv.org/OAI/arXiv/" );
       $article->registerXpathNamespace( "oai", "http://www.openarchives.org/OAI/2.0/" );
       $cite->journal_title = "arXiv";
       
       $titleN = $article->xpath( "//ar:title" );
       $cite->title = (string)$titleN[ 0 ];

       $dateN = $article->xpath( "//ar:created" );
       $rawdate = (string)$dateN[ 0 ];
       
       $cite->pub_date['month'] = substr( $rawdate, 5, 2 );
       $cite->pub_date['day'] = substr( $rawdate, 8, 2 );
       $cite->pub_date['year'] = substr( $rawdate, 0, 4 );
       
       $authorN = $article->xpath( "//ar:author" );
       
       foreach( $authorN as $author ){
           $newauthor = array();
           $newauthor['surname'] = (string)$author->keyname;
           $newauthor['given_name'] = (string)$author->forenames;
           $cite->authors[] = $newauthor;
       }

       $cite->url = "http://arxiv.org/abs/" . $cite->identifier;
         
       return self::citation_generate_json( $cite );
   }


   private function get_greycite_metadata($cite){
              
       // We get JSON back from greycite, but we need to fiddle, so decode it first
       $json_decoded = json_decode( $cite->resolution_source, true );
       // all a disaster, so report resolution failure
       $last_error = json_last_error();
       if( $last_error != JSON_ERROR_NONE ){
           $cite->resolved = false;
           return $cite;
       }
       
       $json_decoded["source"] = $cite->source;
       $json_decoded["identifier"] = $cite->identifier;
       $json_decoded["resolved"] = $cite->resolved;
       
       //if( !array_key_exists( "author", $json_decoded ) ){
       //$auth = array();
       //    $auth["family"] = "URL";
           
       //    $authors = array();
       //    $authors[] = $auth;
       //    $json_decoded["author"] = $authors;
       //}
           
       $cite->json = $json_decoded;

       // cache explicitly
       if( array_key_exists( "greycite-expire", $json_decoded ) ){
           self::cache_json( $cite, $json_decoded["greycite-expire"]);
       }
       else{
           self::cache_json( $cite );
       }
       
       return $cite;
   }

  /**
   * Fetches the URI that the user requested to work out output format. 
   */
  private function get_requested_uri() {
    $requesturi = $_SERVER['REQUEST_URI'];
    preg_match('#\/(.*)\/bib\.(bib|ris|json)$#', $requesturi, $matches);
    //matches[1] is post (with extraneous paths maybe)
    $uri = null;
    if ($matches) { 
        $uri = array();
        $uri[0] = $matches[2];
    }
    return $uri;
  }
  
  /**
   * add a link to settings on the plugin management page
   */ 
  function refman_settings_link( $links, $file ) {
    if ($file == 'kcite/kcite.php' && function_exists('admin_url')) {
        $settings_link = '<a href="' .admin_url('options-general.php?page=kcite.php').'">'. __('Settings') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
  }


}

class Bibliography{
    // array of Citation objects
    private $cites = array();

    // did at least one reference time out during the production of this bibliography. 
    public $contains_timeout = false;
    public $section = 0;
    
    function add_cite($citation){
        // unique check
        for( $i = 0;$i < count($this->cites);$i++ ){
            if( $this->cites[ $i ]->equals( $citation ) ){
                return $this->cites[$i];
            }
        }
        
        $citation->anchor = "ITEM-" . $this->section . "-" . count( $this->cites );
        // number to show users -- 1 indexed!
        $citation->bibindex = count( $this->cites ) + 1;
        $this->cites[] = $citation;
        
        $citation->bibliography = $this;

        return $citation;
    }
    
    function get_cites(){
        return $this->cites;
    }
    
    function get_cites_array(){
        $cites_array = array();
        for( $i = 0;$i < count($this->cites);$i++ ){
            if( $this->cites[ $i ]->source == "inline" ){
                $cites_array[] =
                    array(
                          $this->cites[ $i ]->source,
                          $this->cites[ $i ]->identifier,
                          $this->cites[ $i ]->resolution_source
                          );
            }
            else{
                $cites_array[] =
                    array(
                          $this->cites[ $i ]->source,
                          $this->cites[ $i ]->identifier );
            }
        }
        return $cites_array;
    }

    function add_cites_array( $cites_array ){
        for( $i = 0;$i < count($cites_array);$i++ ){
            $cite = new Citation();
            $cite->bibliography = $this;
            $cite->source = $cites_array[ $i ][ 0 ];
            $cite->identifier = $cites_array[ $i ][ 1 ];
            if( $cite->source == "inline" ){
                $cite->resolution_source = $cites_array[ $i ][ 2 ];
            }
            $this->add_cite( $cite );
          }
    }

}


class Citation{
    
    // generic properties from the citation
    public $identifier;
    public $source;
    
    // attributes of cite tag
    public $tagatts;
    
    // have we translate the identifier into something more, the best we can.
    public $resolved = false;

    // has the translation resulted in an error
    public $error = false;
    
    // http response code from metadata request
    public $response_code = 0;
    
    
    // is the metadata old and not updatable
    public $stale = false;
    
    // have we failed to retrieve this of time out or request limit
    public $timeout = false;
    
    // raw resolved data, in whatever format it comes where ever!
    public $resolution_source;
    // the where ever in the last line
    public $resolved_from;
    
    // parsed XML data as SimpleXMLElement
    public $parsedXML;
    
    // metadata represented as JSON
    public $json;
    
    // citation metadata
    public $authors = array();
    public $journal_title;
    public $abbrv_title;
    // three up array, month, day, year
    public $pub_date;
    public $volume;
    public $title;
    public $first_page;
    public $last_page;
    public $reported_doi;
    public $resource;
    public $issue;
    public $url;

    // internal anchor to be used for linking
    public $anchor;
    // number for visible linking
    public $bibindex;
    // Bibliography
    public $bibliography;


    function equals($citation){
        return $this->identifier == $citation->identifier &&
            $this->source == $citation->source;
    }
}

KCite::init();

function kcite_no_javascript(){
    KCite::$block_javascript = true;
}

?>
