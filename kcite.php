<?php
  /*
   Plugin Name: KCite
   Plugin URI: http://knowledgeblog.org/kcite-plugin
   Description: Add references and bibliography to blogposts
   Version: 0.1
   Author: Simon Cockell, Phillip Lord
   Author URI: http://knowledgeblog.org
   
   Copyright 2010. Simon Cockell (s.j.cockell@newcastle.ac.uk)
   Phillip Lord (phillip.lord@newcastle.ac.uk)
   Newcastle University. 
   
  */



class KCite{
    
  static $bibliography;

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
  
  function debug(){
    echo "Simon's debug statement";
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
      return $content . self::get_html_bibliography();
  }

  function get_html_bibliography(){

      // check the bib has been set, otherwise there have been no cites. 
      if( !isset( self::$bibliography ) ){ 
          return $content = $content . "<!-- kcite active, but no citations found -->";
      }
      
      $cites = self::$bibliography->get_cites();
      
      // // get the metadata which we are going to use for the bibliography.
      $metadata_arrays = self::get_arrays($cites);
      $i = 0;
      
        
      // synthesize the "get the bib" link
      $permalink = get_permalink();
      $json_link ="<a href=\"$permalink/bib.json\"".
          "title=\"Bibliography JSON\">Bibliography in JSON format</a>"; 
    
      // translate the metadata array of bib data into the equivalent JSON
      // representation. 
      $json = self::metadata_to_json($metadata_arrays);
      $json_a = json_decode($json, true);
        
      // build the bib, insert reference, insert bib
      $bibliography = self::build_bibliography($json_a);
      $bibliography .= "<p>$json_link</p>";
      return $bibliography;
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
    foreach ($pub_array as $pub) {
        $anchor = "<a name='bib_$i'></a>";
        if (!$pub['author'] && !$pub['title'] && !$pub['container-title']) { 
            
            //sufficient missing to assume no publication retrieved...
            if ($pub['DOI']) {
                $bib_string .= "<li>$anchor<a href='http://dx.doi.org/".$pub['DOI']."'>DOI:".$pub['DOI']."</a> <i>(KCite cannot find metadata for this paper)</i></li>\n";
            }
            if ($pub['PMID']) {
                $bib_string .= "<li>$anchor<a href='http://www.ncbi.nlm.nih.gov/pubmed/".$pub['PMID']."'>PMID:".$pub['DOI']."</a> <i>(KCite cannot find metadata for this paper)</i></li>\n";
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
        if (array_key_exists("volume", $pub) ){
            $bib_string .= ', vol. '.$pub['volume'];
        }

        if ($pub['issued']['date-parts'][0][0]) {
            $bib_string .= ', '.$pub['issued']['date-parts'][0][0];
        }
        if (array_key_exists("page", $pub) ) {
            $bib_string .= ', pp. '.$pub['page'];
        }
        if (array_key_exists("DOI", $pub) ) {
            $bib_string .= '. <a href="http://dx.doi.org/'.$pub['DOI'].'" target="_blank" title="'.$pub['title'].'">DOI</a>';
        }
        $bib_string .= ".
</li>
";
        }
        $i++;
    }
    $bib_string .= "</ol>
";
    return $bib_string;
  }

  /**
   * Translates citation objects into a metadata array
   * This can be used to build the JSON. 
   */
  private function get_arrays($cites) {
    $metadata_arrays = array();
    foreach ($cites as $cite) {
        $metadata = array();
        
        if ($cite->source == 'doi') {
            $doi = $cite->identifier;
            $article = self::crossref_doi_lookup($doi);
            //failover to pubmed
            if ($article == null) {
                $article = self::pubmed_doi_lookup($doi);
                if (!$article) {
                    //make sure DOI recorded if both lookups fail
                    $metadata = array('doi-err'=>$doi); 
                }
                else {
                    $article_array = self::array_from_xml($article);
                    $metadata = self::get_pubmed_metadata($article_array);
                }
            }
            else {
                $article_array = self::array_from_xml($article);
                $metadata = self::get_crossref_metadata($article_array);
            }
            $metadata_arrays[] = $metadata;
        }
        elseif ($cite->source == 'pubmed') {
            $pmid = $cite->identifier;
            $article = self::pubmed_id_lookup($pmid);
            if (!$article) {
                //make sure PMID recorded if lookup fails
                $metadata = array('pubmed-err'=>$pmid); 
            }
            else {
                $article_array = self::array_from_xml($article);
                $metadata = self::get_pubmed_metadata($article_array);
            }
            $metadata_arrays[] = $metadata;
        }
        else{
            // TODO
            print("UNKNOWN REFERENCE TYPE");
        }
    }
    return $metadata_arrays;
  }
  

  /**
   * Look up DOI on cross ref. 
   * @param string $pub_doi A doi representing a reference
   * @return null if DOI does not resolve, or raw crossref XML
   */
  private function crossref_doi_lookup($pub_doi) {
    //use CrossRef ID provided on the options page
    $crossref = get_option('crossref_id');
    if (!$crossref) {
        //automatically failover to pubmed without trying to connect to crossref
        return null;
    }
    else {
        $url = "http://www.crossref.org/openurl/?noredirect=true&pid=".$crossref."&format=unixref&id=doi:".$pub_doi;
        $xml = file_get_contents($url, 0);
        if (preg_match('/not found in CrossRef/', $xml)) {
            //null will cause failover to PubMed (if no metadata in crossref)
            return null;
        }
        if (preg_match('/login you supplied is not recognized/', $xml)) {
            //null will cause failover to PubMed (if no valid login supplied)
            return null;
        }
        else {
            return $xml;
        }
    }
  }

  /**
   * Look up DOI on pubmed
   * @param string $pub_doi A doi representing a reference
   * @return null if DOI does not resolve, or raw pubmed XML
   */
  private function pubmed_doi_lookup($pub_doi) {
    $search = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&retmax=1&term=".$pub_doi;
    $search_xml = file_get_contents($search, 0);
    if (preg_match('/PhraseNotFound/', $search_xml)) {
        //handles DOI lookup failures
        return null;
    }
    $search_obj = self::array_from_xml($search_xml);
    $idlist = $search_obj->IdList;
    $id = $idlist->Id;
    $fetch_xml = self::pubmed_id_lookup($id);
    return $fetch_xml;
  }

  
  /**
   * Look up pubmed ID on pubmed
   * @param string $pub_id A pubmed ID
   * @return null if DOI does not resolve, or raw pubmed XML
   */
  private function pubmed_id_lookup($pub_id) {
    $fetch = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml&id=".$pub_id;
    $xml = file_get_contents($fetch, 0);
    if (preg_match('/(Error|ERROR)>/', $xml)) {
        //handles fetch failure
        return null;
    }
    return $xml;
  }

  /**
   * Parses XML into an native PhP object
   * @param string $xml containing the XML
   * @return SimpleXMLElement object
   */
  private function array_from_xml($xml) {
    $xmlarray = array();
    $x = new SimpleXMLElement($xml);
    return $x;
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
   * @param $md Associative array, agnostic to the original source data. 
   * @return string JSON version of the above
   */
  private function metadata_to_json($md) {
    $json_string = "{\n";
    $item_number = 1;
    $md_number = count($md);
    foreach ($md as $m) {
        $item_string = "ITEM-".$item_number;

        if (array_key_exists("doi-err", $m )) {
            $json_string .= '"'.$item_string.'": {
    "DOI": "'.$m['doi-err'].'"
';
        }
        
        elseif (array_key_exists('pubmed-err',$m)) {
            $json_string .= '"'.$item_string.'": {
    "PMID": "'.$m['pubmed-err'].'"
';
        }
        else {
        $json_string .= '"'.$item_string.'": {
    "id": "'.$item_string.'",
    "title": "'.$m[6].'",
    "author": [
    ';
        $author_length = count($m[0]);
        $track = 1;
        foreach ($m[0] as $author) {
            $json_string .= '{
        "family": "'.$author['surname'].'",
        "given": "'.$author['given_name'].'"
    ';
    if ($track != $author_length) {
    $json_string .= '},
    ';
    }
    else {
        $json_string .= '}
    ';
        }
        $track++;
        }
        $json_string .= '],
    "container-title": "'.$m[1].'",
    "issued":{
        "date-parts":[
            [';
        $date_string = $m[3]['year'];
        if ($m[3]['month']) {
            $date_string .= ", ".(int)$m[3]['month'];
        }
        if ($m[3]['day']) {
            $date_string .= ", ".(int)$m[3]['day'];
        }
        $json_string .= $date_string.']
        ]
    },
    ';
        if ($m[7]) {
            $json_string .= '"page": "'.$m[7].'-'.$m[8].'",
    ';
        }
        //volume
        if ($m[4]) {
            $json_string .= '"volume": "'.$m[4].'",
    ';
        }
        //issue
        if ($m[5]) {
            $json_string .= '"issue": "'.$m[5].'",
    ';
        }
        //doi
        if ($m[9]) {
            $json_string .= '"DOI": "'.$m[9].'",
    ';
        }
        //url
        //type
        $json_string .= '"type": "article-journal"
';
        }
        if ($item_number != $md_number) {
            $json_string .= '},
';
        }
        else {
            $json_string .= '}
';
        }
        $item_number++;
    }
    $json_string .= '}';
    return $json_string;
  }
  
  /**
   * @param string $article returns metadata object from SimpleXMLElement
   * @return metadata associative array
   */
  private function get_crossref_metadata($article) {
    $authors = array();
    $journal_title = "";
    $abbrv_title = "";
    $pub_date = array();
    $volume = "";
    $title = "";
    $first_page = "";
    $last_page = "";
    $reported_doi = "";
    $resource = "";
    $issue = "";
    
    $journal = $article->children()->children()->children();
    foreach ($journal->children() as $child) {
        if ($child->getName() == 'journal_metadata') {
            $journal_title = $child->full_title;
            $abbrv_title = $child->abbrev_title;
        }
        elseif ($child->getName() == 'journal_issue') {
            $issue = $child->issue;
            foreach ($child->children() as $issue_info) {
                if ($issue_info->getName() == 'publication_date') {
                    $pub_date['month'] = $issue_info->month;
                    $pub_date['day'] = $issue_info->day;
                    $pub_date['year'] = $issue_info->year;

                }
                elseif ($issue_info->getName() == 'journal_volume') {
                    $volume = $issue_info->volume;
                }
            }
        }
        elseif ($child->getName() == 'journal_article') {
            foreach ($child->children() as $details) {
                if ($details->getName() == 'titles') {
                    $title = $details->children();
                }
                elseif ($details->getName() == 'contributors') {
                    $people = $details->children();
                    $author_count = 0;
                    foreach ($people as $person) {
                        $authors[$author_count] = array();
                        $authors[$author_count]['given_name'] = $person->given_name;
                        $authors[$author_count]['surname'] = $person->surname;
                        $author_count++;
                    }
                }
                elseif ($details->getName() == 'pages') {
                    $first_page = $details->first_page;
                    $last_page = $details->last_page;
                }
                elseif ($details->getName() == 'doi_data') {
                    $reported_doi = $details->doi;
                    $resource = $details->resource;
                }
            }
        }
    }
    return array($authors,$journal_title,$abbrv_title,$pub_date,$volume,$issue,$title,$first_page,$last_page,$reported_doi,$resource);
  }

  /**
   * @param string $article returns metadata object from SimpleXMLElement
   * @return metadata associative array
   */
  private function get_pubmed_metadata($article) {
    $authors = array();
    $journal_title = "";
    $abbrv_title = "";
    $pub_date = array();
    $volume = "";
    $title = "";
    $first_page = "";
    $last_page = "";
    $reported_doi = "";
    $resource = "";
    $issue = "";
    $meta = $article->children()->children()->children();
    foreach ($meta as $child) {
        if ($child->getName() == 'Article') {
            foreach ($child->children() as $subchild) {
                //Journal -> JournalIssue -> Volume, Issue, PubDate
                //Journal -> Title
                //Journal -> ISOAbbreviation
                if ($subchild->getName() == 'Journal') {
                    $jissue = $subchild->JournalIssue;
                    $volume = $jissue->Volume;
                    $issue = $jissue->Issue;
                    $journal_title = $subchild->Title;
                    $abbrv_title = $subchild->ISOAbbreviation;
                }
                //ArticleTitle
                elseif ($subchild->getName() == 'ArticleTitle') {
                    $title = $subchild;
                }
                //AuthorList -> Author[]
                elseif ($subchild->getName() == 'AuthorList') {
                    $author_count = 0;
                    foreach ($subchild->Author as $author) {
                        $authors[$author_count] = array();
                        $authors[$author_count]['given_name'] = $author->ForeName;
                        $authors[$author_count]['surname'] = $author->LastName;
                        $author_count++;
                    }
                }
                //ArticleDate
                elseif ($subchild->getName() == 'ArticleDate') {
                    $pub_date['month'] = $subchild->Month;
                    $pub_date['day'] = $subchild->Day;
                    $pub_date['year'] = $subchild->Year;
                }
                //ELocationID (DOI)
                elseif ($subchild->getName() == 'ELocationID') {
                    $reported_doi = $subchild;
                }
            }
        }
    }
    return array($authors,$journal_title,$abbrv_title,$pub_date,$volume,$issue,$title,$first_page,$last_page,$reported_doi,$resource);
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
  private $cites = array();
  
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
  
    public $authors;
    public $journal_title;
    public $abbrv_title;
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
