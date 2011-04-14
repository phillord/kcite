<?php
  /*
   Plugin Name: KCite
   Plugin URI: http://knowledgeblog.org/kcite-plugin
   Description: Add references and bibliography to blogposts
   Version: 1.3
   Author: Simon Cockell, Phillip Lord
   Author URI: http://knowledgeblog.org
   Email: knowledgeblog-discuss@knowledgeblog.org
   
   Copyright 2010. Simon Cockell (s.j.cockell@newcastle.ac.uk)
   Phillip Lord (phillip.lord@newcastle.ac.uk)
   Newcastle University. 
   
  */



class KCite{
    
  static $entrez_slug="&email=knowledgeblog-discuss%40knowledgeblog.org&tool=kcite";

  static $bibliography;
  
  // debug option -- ignore transients
  static $ignore_transients = false;
  // delete any transients as we are going
  static $clear_transients = false;
  
  // the maximum number of seconds we will attempting to resolve the bib after
  // which kcite times out. The resolution should advance as time goes on, if
  // transients is switched on.
  static $timeout = 6;

  
  // render on the server (true) or on the client using citeproc (false)
  static $render_locally = true;

  /**
   * Adds filters and hooks necessary initializiation. 
   */
  function init(){
    register_activation_hook(__FILE__, array(__CLASS__, 'refman_install'));
    
    //add bibliography to post content
    // priority 12 is lower than shortcode (11), so can assure that this runs
    // after the shortcode filter does, otherwise, it is all going to work
    // very badly. 
    
    add_filter('the_content', array(__CLASS__, 'bibliography_filter'), 
               12);

    add_shortcode( "cite", 
                   array( __CLASS__, "cite_shortcode" ));

    
    //provide links to the bibliography in various formats
    add_action('template_redirect', array(__CLASS__, 'bibliography_output'));
    //add settings menu link to sidebar
    add_action('admin_menu', array(__CLASS__, 'refman_menu'));
    //add settings link on plugin page
    add_filter('plugin_action_links', array(__CLASS__, 'refman_settings_link'), 9, 2 );
  }

  /**
   * Adds options into data. Called on plugin activation. 
   */

  function refman_install() {
    //registers default options
    add_option('service', 'doi');
    add_option('crossref_id', null); //this is just a placeholder
  }

  /**
   * citation short code
   */

  function cite_shortcode($atts,$content)
  {
      // extract attributes as local vars
      extract( shortcode_atts
               ( 
                array(
                      "source" => get_option( "service" ) 
                      ), $atts ) );
    
      // lazy instantiate bib
      if( !isset( self::$bibliography ) ){
          self::$bibliography = new Bibliography();
      }
    
      // store citation in bibliography. Replace anchor. 
      $cite = new Citation();
    
      $cite->identifier=$content;
      if( !isset( $source ) ){
          $source = get_option("service");
      }
      $cite->source=$source;
      
      $anchor = self::$bibliography->add_cite( $cite );
      return "<span id=\"cite_$anchor\" name=\"citation\">" .
          "<a href=\"#bib_$anchor\">[$anchor]</a></span>";
  }

  function bibliography_filter($content) {
      $bib_html = self::get_html_bibliography();
      // delete the bib -- or it will appear on subsequent posts
      self::$bibliography = null;

      return $content . $bib_html;
  }

  function get_html_bibliography(){
      
      // check the bib has been set, otherwise there have been no cites. 
      if( !isset( self::$bibliography ) ){ 
          return "<!-- kcite active, but no citations found -->";
      }
      
      $cites = self::$bibliography->get_cites();
      
      // // get the metadata which we are going to use for the bibliography.
      $cites = self::get_arrays($cites);
      
      if( self::$render_locally ){
          
          // synthesize the "get the bib" link
          $permalink = get_permalink();
 
          // it would make more sense to operate this over the citation
          // objects rather than the JSON, but this was coded as a quick way
          // of rendering after citeproc-js turned out to be a lot of work.
          // Now this is meant to be a fall back, and it will need recoding at
          // some point, but it's not urgent.
          $bibliography = 
              self::build_bibliography
              ( self::citation_to_citeproc( $cites ) );
          
          return $bibliography;
      }
      

      // citeproc rendering...

      return "<strong>Kcite is configured to render with citeproc.". 
          "This bit hasn't been written yet</strong>\n" .
          "<script>" . self::citation_to_citeproc_json( $cites ) . "</script>";
      
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
          $anchor = "<a name='bib_$i'></a>";
          
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
          
          // we haven't been able to resolve anything
          if( array_key_exists( "identifier", $pub ) && 
              array_key_exists( "source", $pub ) ){
              $bib_string .= 
                  "<li>$anchor" . $pub["source"] . ": " 
                  . $pub["identifier"] . "</li>";

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
              $author_count = 1;
              $author_total = count($pub['author']);
              foreach ($pub['author'] as $author) {
                  //get author initials
                  $firsts = $author['given'];
                  $words = explode(' ', $firsts);
                  $initials = "";
                  foreach ($words as $word) {
                      $initials .= strtoupper(substr($word,0,1)).".";
                  }
                  $initials;
                  $bib_string .= $initials." ".$author['family'].", ";
                  if ($author_count == ($author_total - 1)) {
                      $bib_string .= "and ";
                  }
                  $author_count++;
              }
              if ($pub['title']) {
                  $bib_string .= '"'.$pub['title'].'"';
              }
              if ($pub['container-title']) {
                  $bib_string .= ', <i>'.$pub['container-title'].'</i>';
              }
              if (array_key_exists("volume", $pub)){
                  $bib_string .= ', vol. '.$pub['volume'];
              }
              
              if (array_key_exists("issued", $pub)){
                  if(array_key_exists("date-parts", $pub["issued"])){
                      if(array_key_exists( 0, $pub["issued"]["date-parts"])){
                          if(array_key_exists( 0, $pub["issued"]["date-parts"][0])){
                              $bib_string .= ', '.$pub['issued']['date-parts'][0][0];
                          }
                      }
                  }
              }
              if (array_key_exists("page", $pub) ) {
                  $bib_string .= ', pp. '.$pub['page'];
              }
              if (array_key_exists("DOI", $pub) ) {
                  $bib_string .= '. <a href="http://dx.doi.org/'.
                      $pub['DOI'].'" target="_blank" title="'
                      .$pub['title'].'">DOI</a>';
              }
              $bib_string .= ".
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
  private function get_arrays($cites) {
      
      $start_time = time();
      
      foreach ($cites as $cite) {
          
          // print( "Testing time: " . (time() - $start_time) . "\n" );
          
          // check whether this is all taking too long
          if( time() - $start_time > self::$timeout ){
              $cite->error = true;
              $cite->timeout = true;
              self::$bibliography->contains_timeout = true;
              continue;
          }
          
          if ($cite->source == 'doi') {
              $cite = self::crossref_doi_lookup($cite);
              //failover to pubmed
              if (!$cite->resolved) {
                  $cite = self::pubmed_doi_lookup($cite);
                  
                  if (!$cite->resolved) {
                      $cite->error = true;
                      continue;
                  }
                  

                  $cite = self::array_from_xml($cite);
                  $cite = self::get_pubmed_metadata($cite);
                  continue;
              }
              
              $cite = self::array_from_xml($cite);
              $cite = self::get_crossref_metadata($cite);
              continue;
          }
          
          if ($cite->source == 'pubmed') {
              $cite = self::pubmed_id_lookup($cite);
              
                  if (!$cite->resolved) {
                  $cite->error = true;
                  continue;
              }
              
              
              $cite = self::array_from_xml($cite);
              $cite = self::get_pubmed_metadata($cite);
              continue;
          }
          
          // if we don't recognise the type if will remain unresolved. 
          // This is okay and will be dealt with later
      }

      
      return $cites;
  }
  

  /**
   * Attempt to resolve metadata for a citation object
   * @param string $pub_doi A doi representing a reference
   * @return
   */
  private function crossref_doi_lookup($cite) {
    //use CrossRef ID provided on the options page
    $crossref = get_option('crossref_id');
    if (!$crossref) {
        //automatically failover to pubmed without trying to connect to crossref
        return $cite;
    }
    
    $trans_slug = "crossref-doi" . $cite->identifier;

    // debug code -- blitz transients in the database
    if( self::$clear_transients ){
        delete_transient( $trans_slug );
    }
    
    // check for transients
    if (false === (!self::$ignore_transients && $xml = get_transient( $trans_slug ))) {

        // print( "crossref lookup:$trans_slug: " . date( "H:i:s", time() ) ."\n" );
        $url = "http://www.crossref.org/openurl/?noredirect=true&pid="
            .$crossref."&format=unixref&id=doi:".$cite->identifier;
        $xml = file_get_contents($url, 0);
    
        if (preg_match('/not found in CrossRef/', $xml)) {
            //null will cause failover to PubMed (if no metadata in crossref)
            return $cite;
        }
        

        if (preg_match('/login you supplied is not recognized/', $xml)) {
            //null will cause failover to PubMed (if no valid login supplied)
            return $cite;
        }
    
        // transient for 1 week -- need to option this. 
        set_transient( $trans_slug, $xml, 60*60*24*7 );
    }
    


    $cite->resolved = true;
    $cite->resolution_source=$xml;
    $cite->resolved_from="crossref";

    return $cite;
  }

  /**
   * Look up DOI on pubmed
   * @param string $cite A doi representing a reference
   * @return resolved (or not) citation object
   */

  private function pubmed_doi_lookup($cite) {
      
      $trans_slug = "pubmed-doi-to-pubmed" . $cite->identifier;

      // debug code -- blitz transients in the database
      if( self::$clear_transients ){
          delete_transient( $trans_slug );
      }
    
      if (false === (!self::$ignore_transients && $id = get_transient( $trans_slug ))) {

          // print( "pubmed_doi lookup:$trans_slug " . date( "H:i:s", time() ) . "\n" );
          // a free text search for the DOI!
          $search = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?" . 
              self::$entrez_slug . "&db=pubmed&retmax=1&term="
              .$cite->identifier;

          $search_xml = file_get_contents($search, 0);
          
          if (preg_match('/PhraseNotFound/', $search_xml)) {
              //handles DOI lookup failures
              $cite->error = true;
              return $cite;
          }
      
          // now parse out the DOI
          $search_obj =  new SimpleXMLElement($search_xml);
          $idlist = $search_obj->IdList;
          $id = $idlist->Id;
          
          set_transient( $trans_slug, strval( $id ), 60*60*24*7 );
      }
      
      // now do the pubmed_id_lookup!
      // this is not ideal as we are dumping the original 
      $cite->identifier = $id;
      $cite->source = "pubmed";
      
      return self::pubmed_id_lookup($cite);
  }

  
  /**
   * Look up pubmed ID on pubmed
   * @param Citation the citation to resolve
   * @return null if DOI does not resolve, or raw pubmed XML
   */
  private function pubmed_id_lookup($cite) {

      $trans_slug = "pubmed-id" . $cite->identifier;
      

      // debug code -- blitz transients in the database
      if( self::$clear_transients ){
          delete_transient( $trans_slug );
      }
    
      if (false === (!self::$ignore_transients && $xml = get_transient( $trans_slug ))) {

          // print( "pubmed_id lookup: $trans_slug" . date( "H:i:s", time() ) . "\n" );

          $fetch = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?" 
              . self::$entrez_slug . "&db=pubmed&retmode=xml&id="
              .$cite->identifier;
          $xml = file_get_contents($fetch, 0);
          if (preg_match('/(Error|ERROR)>/', $xml)) {
              //handles fetch failure
              return $cite;
          }
          set_transient( $trans_slug, $xml, 60*60*24*7 );
      }
      
      $cite->resolved = true;
      $cite->resolution_source = $xml;
      $cite->resolved_from = "pubmed";
      return $cite;
  }

  /**
   * Parses XML in a citation object into a PhP array
   * @param Citation object
   * @return Citation with parsedXML now containing SimpleXMLElement object
   */
  private function array_from_xml($cite) {
      $cite->parsedXML = new SimpleXMLElement( $cite->resolution_source );
      return $cite;
  }
  
  /**
   * Badly named method, restful API showing just the JSON object for the reference list. 
   * Not fully functional at the moment; works if there are no rewrite rules. 
   * 
   */
  function bibliography_output() {
    global $post;
    $uri = self::get_requested_uri();
    if ($uri[0] == 'json') {
        //render the json here
        $this_post = get_post($post->ID, ARRAY_A);
        $post_content = $this_post['post_content'];
        $dois = self::get_cites($post_content);
        $metadata = array();
        $metadata = self::get_arrays($dois[1]);
        $json = self::metadata_to_json($metadata);
        echo $json;
        exit;
    }
    elseif ($uri[0] == 'bib') {
        //render bibtex here
        exit;
    }
    elseif ($uri[0] == 'ris') {
        //render ris here
        exit; //prevents rest of page rendering
    }
  }

  
  /**
   * @param array of Citation objects
   * @return php array ready for JSON encoding
   */
  private function citation_to_citeproc($cites){
      
      $citep = array();
      
      //$citep[ "AAA" ] = "4";
      
      $item_number = 1;
      $cite_length = count($cites);
      
      foreach ($cites as $cite) {
          $item_string = "ITEM-".$item_number++;
          
          $item = array();

          // timed out overall, so don't have the metadata
          if( $cite->timeout ){
              $item["source"] = $cite->source;
              $item["identifier"] = $cite->identifier;
              $item["timeout"] = true;
              
              $citep[ $item_string ] = $item;
              continue;
          }
          
          // there was an error of some sort (normally no metadata)
          if( $cite->source == "doi" && $cite->error ){
              $item["DOI"] = $cite->identifier;
              $item["error"] = true;

              $citep[ $item_string ] = $item;
              continue;
          }
          
          if( $cite->source == "pubmed" && $cite->error ){
              $item[ "PMID" ] = $cite->identifier;
              $item[ "error" ] = true;

              $citep[ $item_string ] = $item;
              continue;
          }

          // just didn't resolve
          if( !$cite->resolved ){
              
              print( $cite->identifier . "\n" );
              $item[ "source" ] = $cite->source;
              $item[ "identifier" ] = $cite->identifier;
              
              $citep[ $item_string ] = $item;
              continue;
          }
          
          // normal condition
          // stick this on both sides for the hell of it
          $item[ "id" ] = "$item_string";
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
          
          // dates!
          $issued = array();
          $date_parts = array();
          $date_parts[] = (int)$cite->pub_date[ 'year' ];
          $date_parts[] = (int)$cite->pub_date[ 'month' ];
          $date_parts[] = (int)$cite->pub_date[ 'day' ];
          $issued[ "date-parts" ] = $date_parts;
          $item[ "issued" ] = $date_parts;

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
          

          $citep[ $item_string ] = $item;

      }

      return $citep;
  }
  
  /**
   * @param array of Citation objects
   * @return JSON encoded string for citeproc
   */
  private function citation_to_citeproc_json( $cites ){
      
      
      $citep = self::citation_to_citeproc( $cites );

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
   private function get_crossref_metadata($cite) {
    
      // shorted the method a little!
      $article = $cite->parsedXML;
      
      $journal = $article->children()->children()->children();
      
      foreach ($journal->children() as $child) {
          if ($child->getName() == 'journal_metadata') {
              $cite->journal_title = (string)$child->full_title;
              $cite->abbrv_title = (string)$child->abbrev_title;
              continue;
          }
          
          if ($child->getName() == 'journal_issue') {
              $cite->issue = (string)$child->issue;
              foreach ($child->children() as $issue_info) {
                  if ($issue_info->getName() == 'publication_date') {
                      $cite->pub_date['month'] = (string)$issue_info->month;
                      $cite->pub_date['day'] = (string)$issue_info->day;
                      $cite->pub_date['year'] = (string)$issue_info->year;
                      continue;
                  }
                  
                  if ($issue_info->getName() == 'journal_volume') {
                      $cite->volume = (string)$issue_info->volume;
                      continue;
                  }
              }
              continue;
          }
          


          if ($child->getName() == 'journal_article') {
              foreach ($child->children() as $details) {
                  if ($details->getName() == 'titles') {
                      $cite->title = (string)$details->children();
                      continue;
                  }
                  
                  if ($details->getName() == 'contributors') {
                      $people = $details->children();
                      foreach ($people as $person) {
                          $author = array();
                          $author['given_name'] = (string)$person->given_name;
                          $author['surname'] = (string)$person->surname;
                          $cite->authors[] = $author;
                      }
                      continue;
                  }
                  
                  
                  if ($details->getName() == 'pages') {
                      $cite->first_page = (string)$details->first_page;
                      $cite->last_page = (string)$details->last_page;
                      continue;
                  }
                  
                  if ($details->getName() == 'doi_data') {
                      $cite->reported_doi = (string)$details->doi;
                      $cite->resource = (string)$details->resource;
                      continue;
                  }
              }
              continue;
          }
      }
      return $cite;
  }

  /**
   * @param string $article returns metadata object from SimpleXMLElement
   * @return metadata associative array
   */
  private function get_pubmed_metadata($cite) {

      $article = $cite->parsedXML;
      $meta = $article->children()->children()->children();
      foreach ($meta as $child) {
          if ($child->getName() == 'Article') {
              foreach ($child->children() as $subchild) {
                //Journal -> JournalIssue -> Volume, Issue, PubDate
                //Journal -> Title
                //Journal -> ISOAbbreviation
                  if ($subchild->getName() == 'Journal') {
                      $jissue = $subchild->JournalIssue;
                      $cite->volume = (string)$jissue->Volume;
                      $cite->issue = (string)$jissue->Issue;
                      $cite->journal_title = (string)$subchild->Title;
                      $cite->abbrv_title = (string)$subchild->ISOAbbreviation;
                      continue;
                  }

                  //ArticleTitle
                  if ($subchild->getName() == 'ArticleTitle') {
                      $cite->title = (string)$subchild;
                      continue;
                  }
                  
                  //AuthorList -> Author[]
                  if ($subchild->getName() == 'AuthorList') {
                      foreach ($subchild->Author as $author) {
                          $newauthor = array();
                          $newauthor['given_name'] = (string)$author->ForeName;
                          $newauthor['surname'] = (string)$author->LastName;
                          
                          $cite->authors[] = $newauthor;
                      }
                      continue;
                  }
                  
                  //ArticleDate
                  if ($subchild->getName() == 'ArticleDate') {
                      $cite->pub_date['month'] = (string)$subchild->Month;
                      $cite->pub_date['day'] = (string)$subchild->Day;
                      $cite->pub_date['year'] = (string)$subchild->Year;
                      continue;
                  }
                  //ELocationID (DOI)
                  if ($subchild->getName() == 'ELocationID') {
                      $cite->reported_doi = (string)$subchild;
                      continue;
                  }
              }
          }
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

  /** 
   * Link from Settings menu widget to options page. 
   */
  function refman_menu() {
    add_options_page('KCite Plugin Options', 'KCite Plugin', 'manage_options', 'kcite', array(__CLASS__, 'refman_plugin_options'));
  }
  
  /**
   * Prints options form and process it. 
   */
  function refman_plugin_options() {
      if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
      }
      echo '<div class="wrap" id="refman-options">
<h2>KCite Plugin Options</h2>
';
    if ($_POST['refman_hidden'] == 'Y') {
        //process form
        if ($_POST['service'] != get_option('service')) {
            update_option('service', $_POST['service']);
        }
        if ($_POST['crossref_id']) {
            if(eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $_POST['crossref_id'])) {
                if ($_POST['crossref_id'] != get_option('crossref_id')) {
                    update_option('crossref_id', $_POST['crossref_id']);
                }
            }
            else {
                echo "<div style='background-color:rgb(255,168,206);width:80%;padding:4px;border-style:solid;border-width:1px;' id='kcite-options-error'>
                Warning - The CrossRef user ID should be a valid email address.
                </div>
                ";
            }
        }
        echo '<p><i>Options updated</i></p>';   
    }
?>   
      <form id="refman" name="refman" action="" method='POST'>
      <input type="hidden" name="refman_hidden" value="Y">
      <table class="form-table">
      <tr valign="middle">
      <th scope="row">Default Identifier Type<br/><font size='-2'>Which type of identifier would you like to use as the default?</font></th>
      <td><select name='service'>
        <option value='doi' <?php if (get_option('service') == 'doi') echo 'SELECTED'; ?>>DOI</option>
        <option value='pubmed' <?php if (get_option('service') == 'pubmed') echo 'SELECTED'; ?>>PubMed</option>
      </select>
      </td>
      </tr>
      <th scope="row">CrossRef User ID<br/><font size='-2'>Enter an e-mail address that has been <a href='http://www.crossref.org/requestaccount/'>registered with the CrossRef API</a>.</th>
      <td><input type='text' name='crossref_id' class='regular-text code' value='<?php echo get_option('crossref_id'); ?>'></td>
      </tr>
      <!--tr valign="middle">
      </tr-->
      </table>
      <p class="submit">
      <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
      </p>
      </form>
      </div>
<?php
   }

}

class Bibliography{
    // array of Citation objects
    private $cites = array();

    // did at least one reference time out during the production of this bibliography. 
    public $contains_timeout = false;

    function add_cite($citation){
        // unique check
        for( $i = 0;$i < count($this->cites);$i++ ){
            if( $this->cites[ $i ]->equals( $citation ) ){
                return $i + 1;
            }
        }
        
        // add new citation
        $this->cites[] = $citation;
        return count( $this->cites );
    }
    
    function get_cites(){
        return $this->cites;
    }
}


class Citation{
    
    // generic properties from the citation
    public $identifier;
    public $source;
    
    // have we translate the identifier into something more, the best we can.
    public $resolved = false;

    // has the translation resulted in an error
    public $error = false;
    
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
    

    function equals($citation){
        return $this->identifier == $citation->identifier and
            $this->source == $citation->source;
    }
}

KCite::init();

?>
