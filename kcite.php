<?php
  /*
   Plugin Name: KCite
   Plugin URI: http://knowledgeblog.org/kcite-plugin
   Description: Add references and bibliography to blogposts
   Version: 0.1
   Author: Simon Cockell
   Author URI: http://knowledgeblog.org
   
   Copyright 2010. Simon Cockell (s.j.cockell@newcastle.ac.uk)
   Newcastle University. 
   
  */


class KCite{

  function init(){
    register_activation_hook(__FILE__, array(__CLASS__, 'refman_install'));
    //process post content, pull out [cite]s and add bibliography
    add_filter('the_content', array(__CLASS__, 'process_refs'));
    //provide links to the bibliography in various formats
    add_action('template_redirect', array(__CLASS__, 'bibliography_output'));
    //add settings menu link to sidebar
    add_action('admin_menu', array(__CLASS__, 'refman_menu'));
    //add settings link on plugin page
    add_filter('plugin_action_links', array(__CLASS__, 'refman_settings_link'), 9, 2 );
  }

  function refman_install() {
    //registers default options
    add_option('service', 'doi');
    add_option('crossref_id', null); //this is just a placeholder
  }
  
  function debug(){
    echo "Simon's debug statement";
  }

  function process_refs($content) {
    //find citations in the_content
    $cites = self::get_cites($content);
    $replacees = $cites[0];
    $uniq_cites = $cites[1];
    if ($uniq_cites) {
        $metadata_arrays = self::get_arrays($uniq_cites);
        $i = 0;
        while ($i < count($replacees)) {
            $replacer = '<span id="cad'.strval($i+1).'" name="citation-cad">['.strval($i+1).']</span>';
            $content = preg_replace($replacees[$i], $replacer, $content);
            $i++;
        }
        $permalink = get_permalink();
        $json_link ="<a href='".$permalink."/bib.json' title='Bibliography JSON'>Bibliography in JSON format</a>"; 
    
        $json = self::metadata_to_json($metadata_arrays);
        $json_a = json_decode($json, true);
        $bibliography = self::build_bibliography($json_a);
        $bibliography .= "<p>$json_link</p>";
        $content .= $bibliography;
    }
    return $content;
  }

  private function build_bibliography($pub_array) {
    $i = 1;
    $bib_string = "<h2>References</h2>
    <ol>
    ";
    foreach ($pub_array as $pub) {
        if (!$pub['author'] && !$pub['title'] && !$pub['container-title']) { 
            
            //sufficient missing to assume no publication retrieved...
            if ($pub['DOI']) {
                $bib_string .= "<li><a href='http://dx.doi.org/".$pub['DOI']."'>DOI:".$pub['DOI']."</a> <i>(KCite cannot find metadata for this paper)</i></li>\n";
            }
            if ($pub['PMID']) {
                $bib_string .= "<li><a href='http://www.ncbi.nlm.nih.gov/pubmed/".$pub['PMID']."'>PMID:".$pub['DOI']."</a> <i>(KCite cannot find metadata for this paper)</i></li>\n";
            }
        }
        else {
        $bib_string .= "<li>
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
        if ($pub['volume']) {
            $bib_string .= ', vol. '.$pub['volume'];
        }

        if ($pub['issued']['date-parts'][0][0]) {
            $bib_string .= ', '.$pub['issued']['date-parts'][0][0];
        }
        if ($pub['page']) {
            $bib_string .= ', pp. '.$pub['page'];
        }
        if ($pub['DOI']) {
            $bib_string .= '. <a href="http://dx.doi.org/'.$pub['DOI'].'" target="_blank" title="'.$pub['title'].'">DOI</a>';
        }
        $bib_string .= ".
</li>
";
        }
    }
    $bib_string .= "</ol>
";
    return $bib_string;
  }

  private function get_arrays($cites) {
    $metadata_arrays = array();
    foreach ($cites as $cite) {
        $metadata = array();
        if ($cite[1] == 'doi') {
            $doi = $cite[0];
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
        elseif ($cite[1] == 'pubmed') {
            $pmid = $cite[0];
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
    }
    return $metadata_arrays;
  }
  
  private function get_cites($content) {
    $preg = "#\[cite( source=[\"\'](pubmed|doi)[\"\']){0,1}\](.*?)\[\/cite\]#"; //make sure this is non-greedy
    preg_match_all($preg, $content, $cites);
    //need to make sure we deal with duplicate DOIs here
    //array_values() needed to keep array indicies sequential
    $replacees = array_values($cites[0]);
    $replace_regexes = array();
    foreach ($replacees as $replacee) {
        preg_match('#\](.*)\[#', $replacee, $middle);
        $replace_regex = '#(\[cite( source=[\'\"](doi|pubmed)[\'\"]){0,1}\]'.$middle[1].'\[\/cite\]?)#';
        $replace_regexes[] = $replace_regex;
    }
    $i = 0;
    $citations = array();
    while ($i < count($cites[3])) {
        $identifier = $cites[3][$i];
        $source = $cites[2][$i];
        if (!$source) {
            //fallback to default if no option
            $source = get_option('service');
        }
        $citation = array($identifier, $source);
        $check = 0;
        foreach ($citations as $test) {
            if ($test[0] == $identifier) {
                $check = 1;
            }
        }
        if ($check == 0) {
            $citations[] = $citation;
        }
        $i++;
    }
    $returnval = array(array_unique($replace_regexes), $citations);
    return $returnval;
  }

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
  private function pubmed_id_lookup($pub_id) {
    $fetch = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml&id=".$pub_id;
    $xml = file_get_contents($fetch, 0);
    if (preg_match('/(Error|ERROR)>/', $xml)) {
        //handles fetch failure
        return null;
    }
    return $xml;
  }

  private function array_from_xml($xml) {
    $xmlarray = array();
    $x = new SimpleXMLElement($xml);
    return $x;
  }

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

  private function metadata_to_json($md) {
    $json_string = "{\n";
    $item_number = 1;
    $md_number = count($md);
    foreach ($md as $m) {
        $item_string = "ITEM-".$item_number;
        if ($m['doi-err']) {
            $json_string .= '"'.$item_string.'": {
    "DOI": "'.$m['doi-err'].'"
';
        }
        elseif ($m['pubmed-err']) {
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
  
  //add a link to settings on the plugin management page
  function refman_settings_link( $links, $file ) {
    if ($file == 'kcite/kcite.php' && function_exists('admin_url')) {
        $settings_link = '<a href="' .admin_url('options-general.php?page=kcite.php').'">'. __('Settings') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
  }

  function refman_menu() {
    add_options_page('KCite Plugin Options', 'KCite Plugin', 'manage_options', 'kcite', array(__CLASS__, 'refman_plugin_options'));
  }

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

KCite::init();

?>
