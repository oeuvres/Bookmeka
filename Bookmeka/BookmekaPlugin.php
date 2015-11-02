 <?php

/**
 * Bookmeka plugin, display full texte books in Omeka (odt > tei > html > epub)
 * 
 *
 * @license    LGPL http://www.gnu.org/licenses/lgpl-3.0.en.html
 * @version    $Id:$
 * @package    Bookmeka
 * @author     Frederic.Glorieux@fictif.org
 **/

class BookmekaPlugin extends Omeka_Plugin_AbstractPlugin {
  const TABLE = 'bookmekas'; // Omeka force table name to have an 's', or you can't use their layer
  protected $_table;
  const MIME_EPUB = "application/epub+zip";
  const MIME_HTML = "text/html";
  const MIME_IRAMUTEQ = "text/vnd.iramuteq";
  const MIME_ODT = "application/vnd.oasis.opendocument.text";
  const MIME_TEI = "application/tei+xml";
  const MIME_MD = "text/markdown";
  const LETTER_NAME = "Letter";
  const LETTER_DESCRIPTION = "Text item in a correspondance";
  const ARTICLE_NAME = "Article";
  const ARTICLE_DESCRIPTION = "Text item in one page";
  
  const DC = "Dublin Core";
  protected $_tmpdir;
  protected $_hooks = array(
    'admin_head', 
    'after_save_item', 
    'before_delete_file',
    'before_delete_item',
    'before_save_file', 
    'before_save_item', 
    'config',
    'config_form',
    'initialize', 
    'install', 
    'public_head',
    'public_items_show',
    'uninstall',
  );
  protected $_options = array(
    'bookmeka' => true,
  );
  protected $_mimetei = array(
    'text/xml'=>'',
    'application/xml'=>'',
    'application/tei+xml'=>'',
  );
  /** XSLT transformer */
  protected $_trans;
  /** DOM of an XSLT sheet */
  protected $_xsl;
  /** an array to add metadatas from files */
  protected $_metas = array();
  /** an array of Omeka files to delete after save item */
  protected $_files2delete = array();
  /** an array of tm file to unlink */
  protected $_tmp2unlink = array();

  // protected $_filters = array('admin_navigation_main');

  function hookInstall()
  {
    $this->_table = $this->_db->prefix.self::TABLE;
    $db = $this->_db;
    if (!class_exists('XSLTProcessor')) {
      throw new Exception('Unable to access XSLTProcessor class.  Make sure the php-xsl package is installed.');
      return;
    }
    // Insert different types of Items (to adjust output)
    if(!get_record('ItemType', self::LETTER_NAME)){
      insert_item_type(
        array(
          'name'=> self::LETTER_NAME,
          'description' => self::LETTER_DESCRIPTION,
        )
      );
    }
    if(!get_record('ItemType', self::ARTICLE_NAME)){
      insert_item_type(
        array(
          'name'=> self::ARTICLE_NAME,
          'description' => self::ARTICLE_DESCRIPTION,
        )
      );
    }
    $db->query("
CREATE TABLE IF NOT EXISTS `{$this->_table}` (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, -- auto id for subitems
  item INT(10) UNSIGNED,  -- omeka id of item
  file INT(10) UNSIGNED,  -- omeka id of source file in item
  filename VARCHAR(200),  -- filename of book, should be unique for collection
  type VARCHAR(200),      -- type of resource
  section VARCHAR(200),   -- id of section in file
  html LONGBLOB,          -- html to display for section
  PRIMARY KEY  (id),
  INDEX (item, section),
  INDEX (filename)
) ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
    );
  }
  /**
   * Suppress all generated html chapters
   */
  function hookUninstall()
  {
    $this->_table = $this->_db->prefix.self::TABLE;
    $db  = get_db();
    $db->query("
DROP TABLE IF EXISTS `{$this->_table}`
    ");
  }
  function hookInitialize()
  {
    $this->_table = $this->_db->prefix.self::TABLE;
    $this->_tmpdir = rtrim(get_option('bookmeka_tmpdir'), '/\\').'/';
    if (!$this->_tmpdir) $this->_tmpdir = sys_get_temp_dir().'/bookmeka/';
    if(!file_exists($this->_tmpdir)) {
      mkdir($this->_tmpdir, 0775, true);
      @chmod($this->_tmpdir, 0775);
    }
    // register some icons for file type
    add_file_fallback_image(self::MIME_ODT, "fallback-odt.png");
    add_file_fallback_image(self::MIME_TEI, "fallback-tei.png");
    add_file_fallback_image(self::MIME_EPUB, "fallback-epub.png");
    add_file_fallback_image(self::MIME_HTML, "fallback-html.png");
    add_file_fallback_image(self::MIME_MD, "fallback-md.png");
    add_file_fallback_image(self::MIME_IRAMUTEQ, "fallback-iramuteq.png");
    add_file_display_callback(array(self::MIME_ODT, self::MIME_TEI, self::MIME_EPUB, self::MIME_HTML, self::MIME_MD, self::MIME_IRAMUTEQ), array($this, 'fileDisplay')); 
    // inialize an XSLTProcessor
    $this->_trans = new XSLTProcessor();
    $this->_trans->registerPHPFunctions();
    // allow generation of <xsl:document>
    if (defined('XSL_SECPREFS_NONE')) $prefs = XSL_SECPREFS_NONE;
    else if (defined('XSL_SECPREF_NONE')) $prefs = XSL_SECPREF_NONE;
    else $prefs = 0;
    if(method_exists($this->_trans, 'setSecurityPreferences')) $oldval = $this->_trans->setSecurityPreferences( $prefs);
    else if(method_exists($this->_trans, 'setSecurityPrefs')) $oldval = $this->_trans->setSecurityPrefs( $prefs);
    else ini_set("xsl.security_prefs",  $prefs);
    $this->_xsl = new DOMDocument();
  }
  public function fileDisplay($file, $options=array(), $wrapperAttributes = array()) {
    // _log('fileDisplay');
    // echo( '<pre>'.json_encode($file, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).'</pre>');
    $url = file_display_url($file, $format='original');
    $extension = $file->getExtension();
    if ($file->mime_type == self::MIME_TEI) $extension = "tei";
    echo ' <a class="bookmeka-file " target="_new" href="'.$url.'" title="'.$file->original_filename.'">'.$file->getExtension().'</a> ';
  }
  
  /**
   * Work on inserted TEI file
   * Extract metadata from the first XML/TEI file to populate properties of item
   * TOTEST, if file is visible only when a file is uploaded
   * TOTEST, start generations as a job
   * TOTHINK a clean split method, probably XSL driven, to add html subitems
   * Generate TEI from ODT
   * Generate HTML to display and populate the cache table
   * Generate other products (epub, txt, docx?)
   * Add full text to the search index.
   */
  public function hookBeforeSaveFile($args)
  {
    if (!$args['insert']) return; // hook can fire with no file
    $file = $args['record'];
    $item = $file->getItem();
    $extension = $file->getExtension();
    $type = $item->getItemType()->name;
    $db = $this->_db;
    // _log(json_encode($item->getItemType(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    // catch here some extension to change mime/type, impossible before when file is added

    if ($extension == 'md') {
      $file->mime_type = self::MIME_MD;
      return;
    }
    if ($extension == 'txt') {
      $magic = file_get_contents($file->getPath(), false, null, -1, 4096);
      if (strpos($magic, '****')===false) return; // not Iramuteq, nothing todo here
      $file->mime_type = self::MIME_IRAMUTEQ;
      return;
    }
    
    $filename = pathinfo($file->original_filename, PATHINFO_FILENAME); // filename without extension
    
    // an odt file submitted, create XML/TEI version
    if ($file->mime_type == self::MIME_ODT || $extension == 'odt') {
      $odt=new Odette_Odt2tei($file->getPath());
      $destfile = $this->_tmpdir . $filename . '.xml';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      // loop on file of item, replace ones with the same name and extension
      $exts = array("odt"=>true);
      foreach($item->Files as $key=>$f) {
        $pathinfo = pathinfo($f['original_filename']);
        if($pathinfo['filename'] != $filename) continue;
        if(!isset($exts[$pathinfo['extension']])) continue;
        // do not delete here, delete after save
        $this->_files2delete[] = $f;
      }

      
      if (file_exists($destfile)) unlink($destfile); // if repost, delete now
      $odt->save($destfile, "tei");
      insert_files_for_item($item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp xml file
      // the xml file will recall this hook
      return;
    }
    
    // an xml file, if TEI, work
    if ( ($file->mime_type && isset($this->_mimetei[$file->mime_type])) || 'xml' == $extension) {
      
      $magic = file_get_contents($file->getPath(), false, null, -1, 4096);
      if (strpos($magic, '<TEI')===false) return; // not TEI, nothing todo here
      $file->mime_type = self::MIME_TEI;
      
      
      // loop on the file of item and delete the ones we will generate here
      $exts = array("epub"=>true, "html"=>true, "md"=>true, "txt"=>true, "xml"=>true);
      foreach($item->Files as $key=>$f) {
        $pathinfo = pathinfo($f->original_filename);
        if($pathinfo['filename'] != $filename) continue;
        if(!isset($exts[$pathinfo['extension']])) continue;
        // do not delete here, delete after save
        $this->_files2delete[] = $f;
      }
      
      // load TEI as dom
      $doc = new DOMDocument("1.0", "UTF-8");
      $doc->load($file->getPath(), LIBXML_NOENT | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NSCLEAN);
      $doc->preserveWhiteSpace = false;
      $doc->formatOutput = true; // allow correct indentation

      
      // load metadata
      $this->_xsl->load(dirname(__FILE__).'/libraries/Transtei/tei2dc.xsl');
      $this->_trans->importStyleSheet($this->_xsl);
      $dc=$this->_trans->transformToDoc($doc);
      // loop on properties and record theme for afterSaveItem
      foreach ($dc->documentElement->childNodes as $el) {
        $html = $el->ownerDocument->saveXML($el);
        $html = trim(preg_replace('@^<[^>]+>(.*)</[^>]+>$@s', "$1", trim($html)));
        // bug, text node
        if (!$el->localName) {
          _log('Bookmeka, item #'.$item->id.' '.$file->original_filename. ' <???> '.$html, Zend_Log::INFO);
          continue;
        }
        // get Element id
        $element = $item->getElement(self::DC, ucfirst($el->localName));
        // unknown property for Omeka, be nice, log it (which level ? DEBUG ?)
        if (!$element) {
          _log('Bookmeka, item #'.$item->id.' '.$file->original_filename.' '.$el->tagName.' '.$html, Zend_Log::INFO);
          continue;
        }
        if (!isset($this->_metas[$element['id']])) $this->_metas[$element['id']] = array();
        $this->_metas[$element['id']][] = $html;
      }
      
      // epub
      $epub = true;
      if ($type == self::LETTER_NAME) $epub = false; // no epub for letters
      if ($epub) {
        $destfile = $this->_tmpdir . $filename . '.epub';
        $livre = new Livrable_Tei2epub($doc); 
        $livre->epub($destfile);
        _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
        insert_files_for_item($item, 'Filesystem', $destfile);
        unlink($destfile); // delete tmp epub file
      }
      

      // transform to html one file
      $destfile = $this->_tmpdir . $filename . '.html';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      $this->_xsl->load(dirname(__FILE__).'/libraries/Transtei/tei2html.xsl');
      $this->_trans->importStyleSheet($this->_xsl);
      $this->_trans->transformToUri($doc, $destfile);
      insert_files_for_item($item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp html file

      // markdown
      $destfile = $this->_tmpdir . $filename . '.md';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      $this->_xsl->load(dirname(__FILE__).'/libraries/Transtei/tei2txt.xsl');
      $this->_trans->importStyleSheet($this->_xsl);
      $this->_trans->transformToUri($doc, $destfile);
      insert_files_for_item($item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp file

      // iramuteq
      $destfile = $this->_tmpdir . $filename . '.txt';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      $this->_xsl->load(dirname(__FILE__).'/libraries/Transtei/tei2txt.xsl');
      $this->_trans->importStyleSheet($this->_xsl);
      $this->_trans->setParameter(null, 'mode', 'iramuteq');
      $this->_trans->transformToUri($doc, $destfile);
      insert_files_for_item( $item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp file
      

      if ($type == SELF::LETTER_NAME) {
        $this->_xsl->load(dirname(__FILE__).'/libraries/Transtei/tei2html.xsl');
        $this->_trans->importStyleSheet($this->_xsl);
        $this->_trans->setParameter(null, "root", "article"); // html fragment
        // TODO links ?
        $html = $this->_trans->transformToXML($doc);
        $db->query("DELETE FROM {$this->_table} WHERE item = {$item->id}");
        $db->insert(
          self::TABLE, 
          array(
            "item" => $item->id,
            "file" => $file->id,
            "filename" => $filename,
            "type" => $type,
            "section" => 'index',
            "html" => $html,
          )
        );
      }
      else { // generic 
        $destdir = $this->_tmpdir . $filename . '/';
        if (!file_exists($destdir)) mkdir($destdir);
        $this->_xsl->load(dirname(__FILE__).'/libraries/Transtei/tei2site.xsl');
        $this->_trans->importStyleSheet($this->_xsl);
        $this->_trans->setParameter(null, "destdir", $destdir);
        $this->_trans->setParameter(null, "root", "article"); // html fragment
        $this->_trans->setParameter(null, "base", "?section="); // links, as an uri parameter
        $this->_trans->setParameter(null, "_html", ""); // links, no extension
        $this->_trans->transformToXML($doc);
        $db->query("DELETE FROM {$this->_table} WHERE item = {$item->id}");
        foreach(scandir($destdir) as $f) {
          if ($f[0] == '.') continue;
          $section = pathinfo($f, PATHINFO_FILENAME);
          $html = file_get_contents($destdir.$f);
          $db->insert(
            self::TABLE, 
            array(
              "item" => $item->id,
              "file" => $file->id,
              "filename" => $filename,
              "type" => $type,
              "section" => $section,
              "html" => $html,
            )
          );
          unlink($destdir.$f); // delete file now
        }
        rmdir($destdir);
      }
      
      return;
    }
  }
  /**
   * If a file deleted
   * When item is deleted, all files will be deleted
   * 
   */
  public function hookBeforeDeleteFile($args) {
    
    
  }
  /**
   */
  public function hookBeforeSaveItem($args)
  {
    // $item = $args['record'];
  }
  /**
   * After save
   *
   * Seems the best place to deal with old posted metadatas, and new from file 
   */
  function hookAfterSaveItem($args)
  {
    $item = $args['record'];
    if (count($this->_metas)) {
      $item->deleteElementTextsByElementId(array_keys($this->_metas));
      foreach($this->_metas as $key => $values) {
        $element = $item->getElementById($key);
        if (!is_array($values)) continue;
        foreach ($values as $val) {
          $prop = new ElementText;
          $prop->record_id = $item->id;
          $prop->record_type = 'Item';
          $prop->element_id = $element->id;
          $prop->text = $val;
          $prop->html = true;
          $prop->save();
        }
      }
    }
    $this->_metas = array();
    foreach ($this->_files2delete as $f) $f->delete();
    $this->_files2delete = array();
  }
  /**
   * On item deletion (or on TEI file deletion ?), delete the generated HTML subitems
   */
  function hookBeforeDeleteItem($args)
  {
    $item = $args['record'];
    $this->_db->query("DELETE FROM {$this->_table} WHERE item = {$item->id}");
  }

  function hookConfigForm()
  {
    echo '<div class="field">'."\n";
    echo '<p class="explanation">'.__(
      'Give an alternate tmp dir for generated contents.'
    )."</p>\n";
    echo '<label for="bookmeka_tmpdir">'.__('Temp directory')."</label>\n";
    echo get_view()->formText('bookmeka_tmpdir', get_option('bookmeka_tmpdir'));
    echo "</div>\n";
    /*
    echo '<div class="field">'."\n";
    echo '<p class="explanation">'.__(
      'For short items like letters or articles (not books), change default policy of multi-pages.'
    )."</p>\n";
    echo '<label for="bookmeka_singlepage">'.__('Single page')."</label>\n";
    echo get_view()->formCheckbox('bookmeka_singlepage', true, array('checked'=>(boolean)get_option('bookmeka_singlepage')));
    echo "</div>\n";
    */
  }

  /**
   * Configuration
   * TODO, a job to regenerate file on odt or TEI
   */
  function hookConfig()
  {
    // Run the text extraction process if directed to do so.
    /*
    if ($_POST['tei_job'] ) {
      Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning('TeiJob');
    }
    */
    if (!isset($_POST['bookmeka_tmpdir']));
    // empty value, delete prop
    else if (!$_POST['bookmeka_tmpdir']) {
      delete_option('bookmeka_tmpdir');
    }
    else {
      set_option('bookmeka_tmpdir', rtrim(trim($_POST['bookmeka_tmpdir']), "/\\").'/');
    }
    /*
    if (!isset($_POST['bookmeka_singlepage']));
    // empty value, delete prop
    else if (!$_POST['bookmeka_singlepage']) {
      delete_option('bookmeka_singlepage');
    }
    else {
      set_option('bookmeka_singlepage', (boolean)$_POST['bookmeka_singlepage']);
    }
    */
  }

  function hookAdminHead($request)
  {
    queue_css_file('omeka');
  }

  function hookPublicHead($request)
  {
    queue_css_file('html');
    queue_css_file('bookmeka');
    queue_js_file('Tree');
    queue_js_file('bookmeka');
  }
  /**
   * Show item, table of contents, and subitems
   */
  function hookPublicItemsShow($args) {
    $db = $this->_db;
    $item = $args['item'];
    $itemid = $db->quote($item->id);
    $section = @$_REQUEST['section'];
    if (!$section) $section = 'index';
    $section = $db->quote($section);
    $toc = $db->quote('toc');
    $sql = "SELECT * FROM {$this->_table} WHERE item = $itemid AND (section = $section OR section = $toc)";
    $result = $db->query($sql);
    while ($row = $result->fetch()) {
      echo $row['html'];
    }
     
  }
}