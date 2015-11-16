 <?php

/**
 * Bookmeka plugin, display full texte books in Omeka (odt > tei > html > epub)
 * 
 * Code commented in English
 * Initial messages in French, localized by gettext
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
  const MIME_MD = "text/markdown";
  const MIME_ODT = "application/vnd.oasis.opendocument.text";
  const MIME_TEI = "application/tei+xml";
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
    'text/xml'=>true,
    'application/xml'=>true,
    'application/tei+xml'=>true,
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
    add_translation_source(dirname(__FILE__) . '/languages');
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
    // default values for options
    if (is_null(get_option('bookmeka_epub'))) set_option('bookmeka_epub', true);
    if (is_null(get_option('bookmeka_html'))) set_option('bookmeka_html', true);
    if (is_null(get_option('bookmeka_iramuteq'))) set_option('bookmeka_iramuteq', true);
    if (is_null(get_option('bookmeka_md'))) set_option('bookmeka_md', true);
    if (is_null(get_option('bookmeka_site'))) set_option('bookmeka_tei', true);
    if (is_null(get_option('bookmeka_tei'))) set_option('bookmeka_tei', true);
  }
  /**
   * Uninstall
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
    $this->_tmpdir = get_option('bookmeka_tmpdir');
    if (!$this->_tmpdir) $this->_tmpdir = sys_get_temp_dir().'/bookmeka/';
    $this->_tmpdir = rtrim($this->_tmpdir, '/\\').'/';
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
    $url = file_display_url($file, $format='original');
    $label = $file->getExtension();
    if ($file->mime_type == self::MIME_TEI) $label = "tei";
    if ($file->mime_type == self::MIME_IRAMUTEQ) $label = "iramuteq";
    echo ' <a class="bookmeka-file " target="_new" href="'.$url.'" title="'.$file->original_filename.'">'.$label.'</a> ';
  }
  
  /**
   * Most of the logic is a reaction to file ingestion.
   * When a file is submitted by an administrator,
   * this hook fires. If the file is a supported import format
   * the text is transformed in the pivot format (xml/tei).
   * According to the configuration of the plugin, different export formats
   * can be generated as file attached to item.
   * 
   * Import formats : odt, tei
   * Pivot format : tei
   * Export format : epub, html, iramuteq, markdown, site
   *
   * odt > create an xml TEI 
   * Work on inserted TEI file
   * Extract metadata from the first xml/tei file to populate properties of item
   * Generate TEI from ODT
   * Generate HTML to display and populate the cache table
   * Generate other products (epub, txt, docx?)
   * Add full text to the search index.
   */
  public function hookBeforeSaveFile($args)
  {
    if (!$args['insert']) return; // hook can fire with no file
    
    // populate some variables
    $file = $args['record'];
    $filename = pathinfo($file->original_filename, PATHINFO_FILENAME); // filename without extension
    $extension = $file->getExtension();
    $mime_type = $file->mime_type;
    $item = $file->getItem();
    if (!$item->getItemType()) $type = null;
    else $type = $item->getItemType()->name;
    $db = $this->_db;

    // if a Markdown file ingested, set mime/type on the fly
    if ($extension == 'md') {
      $file->mime_type = self::MIME_MD;
      return;
    }
    // if an Iramuteq file ingested, set mime/type on the fly
    if ($extension == 'txt') {
      $magic = file_get_contents($file->getPath(), false, null, -1, 4096);
      if (strpos($magic, '****')===false) return; // not Iramuteq, nothing todo here
      $file->mime_type = self::MIME_IRAMUTEQ;
      return;
    }
    
    
    // an odt file submitted, create xml/tei version
    if ( self::MIME_ODT == $file->mime_type || $extension == 'odt') {
      $destfile = $this->_tmpdir . $filename . '.xml';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      // delete all odt files for item
      // allow more than one odt file by item is probably not a good idea
      // deletion is handled in BeforeSaveItem hook, here we only populate a list
      foreach($item->Files as $key=>$loopfile) {
        if (self::MIME_ODT == $loopfile->mime_type ) $this->_files2delete[] = $loopfile;
        // probably unuseful
        else if ("odt" == $loopfile->getExtension()) $this->_files2delete[] = $loopfile;
      }
      $odt=new Odette_Odt2tei($file->getPath());
      if (file_exists($destfile)) unlink($destfile); // if tmp file incorrectly deleted before, retry now
      $odt->save($destfile, "tei");
      insert_files_for_item($item, 'Filesystem', $destfile); // the xml file will recall this hook
      unlink($destfile); // delete tmp xml file after ingestion
      // do not keep the source odt file
      $this->_files2delete[] = $file;
      return;
    }
    
    // test if xml/tei file
    if ( ($file->mime_type && !isset($this->_mimetei[$file->mime_type])) && 'xml' != $extension) return;
    $magic = file_get_contents($file->getPath(), false, null, -1, 4096);
    if (strpos($magic, '<TEI')===false) return; // not tei, nothing todo here
    // seems an xml/tei file, let’s work 

    $file->mime_type = self::MIME_TEI;
    // loop on the files of item and delete the ones we will generate here
    $exts = array("epub"=>true, "html"=>true, "md"=>true, "txt"=>true, "xml"=>true);
    foreach($item->Files as $key=>$loopfile) {
      $pathinfo = pathinfo($loopfile->original_filename);
      // if($pathinfo['filename'] != $filename) continue; // only one file by extension
      if(!isset($exts[$pathinfo['extension']])) continue;
      // do not delete here, delete after save
      $this->_files2delete[] = $loopfile;
    }
      
    // delete everything in base about this TEI file
    _log('Bookmeka, item #'.$item->id.' '.$filename.' store in base', Zend_Log::INFO);
    $db->query("DELETE FROM {$this->_table} WHERE item = {$item->id}");
    // can’t store TEI xml in base without changing the my.ini : wait_timeout, max_allowed_packet  
    // do not keep TEI file for item if not desired from conf
    if (!get_option('bookmeka_tei')) $this->_files2delete[] = $file;
      
    // load tei as dom
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->load($file->getPath(), LIBXML_NOENT | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NSCLEAN);
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true; // allow correct indentation

    // epub
    $epub = get_option('bookmeka_epub');
    if ($type == self::LETTER_NAME) $epub = false; // no epub for letters
    if ($epub) {
      $destfile = $this->_tmpdir . $filename . '.epub';
      $livre = new Livrable_Tei2epub($doc, '_log');
      $livre->epub($destfile);
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      insert_files_for_item($item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp epub file
    }

    // transformations with the bookmeka pilot
    $this->_xsl->load(dirname(__FILE__).'/bookmeka.xsl');
    $this->_trans->importStyleSheet($this->_xsl);
    
    // load Dublin Core metadata
    $this->_trans->setParameter(null, "mode", "dc");
    $this->_trans->setParameter(null, "dc-value", "html");
    $dc=$this->_trans->transformToDoc($doc);
    // loop on properties and record theme for afterSaveItem
    foreach ($dc->documentElement->childNodes as $el) {
      $html = $el->ownerDocument->saveXML($el);
      $html = trim(preg_replace('@^<[^>]+>(.*)</[^>]+>$@s', "$1", trim($html))); // innerHTML
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
      // first time encounter property, open an array in the recorder
      if (!isset($this->_metas[$element['id']])) $this->_metas[$element['id']] = array();
      // add html
      $this->_metas[$element['id']][] = $html;
    }
    
    // transform to html monopage
    if (get_option('bookmeka_html')) {
      $this->_trans->setParameter(null, "mode", "html");
      $destfile = $this->_tmpdir . $filename . '.html';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      $this->_trans->transformToUri($doc, $destfile);
      insert_files_for_item($item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp html file
    }
    
    // feed database with desired html fragments
    if (!get_option('bookmeka_site'));
    else if ($type == SELF::LETTER_NAME || $type == SELF::ARTICLE_NAME) { // item in one file
      $this->_trans->setParameter(null, "mode", "html");
      $this->_trans->setParameter(null, "root", "article"); // html fragment
      $html = $this->_trans->transformToXML($doc);
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
    else { // generic multi-page
      $this->_trans->setParameter(null, "mode", "site");
      $destdir = $this->_tmpdir . $filename . '/';
      if (!file_exists($destdir)) mkdir($destdir);
      $this->_trans->setParameter(null, "destdir", $destdir);
      $this->_trans->setParameter(null, "root", "article"); // html fragment
      // TODO, change according to route policy
      $this->_trans->setParameter(null, "base", "?section="); // links, as an uri parameter
      $this->_trans->setParameter(null, "_html", ""); // links, no extension
      $this->_trans->transformToXML($doc);
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
      
    // markdown
    if (get_option('bookmeka_md')) {
      $destfile = $this->_tmpdir . $filename . '.md';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      $this->_xsl->load(dirname(__FILE__).'/libraries/Teinte/tei2md.xsl');
      $this->_trans->importStyleSheet($this->_xsl);
      $this->_trans->transformToUri($doc, $destfile);
      insert_files_for_item($item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp file
    }

    // iramuteq
    if (get_option('bookmeka_iramuteq')) {
      $destfile = $this->_tmpdir . $filename . '.txt';
      _log('Bookmeka, item #'.$item->id.' '.$file->getPath().' > '.$destfile, Zend_Log::INFO);
      $this->_xsl->load(dirname(__FILE__).'/libraries/Teinte/tei2iramuteq.xsl');
      $this->_trans->importStyleSheet($this->_xsl);
      $this->_trans->setParameter(null, 'mode', 'iramuteq');
      $this->_trans->transformToUri($doc, $destfile);
      insert_files_for_item( $item, 'Filesystem', $destfile);
      unlink($destfile); // delete tmp file
    }

  }
  /**
   * When item is deleted, all files will be deleted
   * If one file is deleted, do nothing, user may want to keep only 
   * some formats (for example, no public xml/tei)
   */
  public function hookBeforeDeleteFile($args) {
    
    
  }
  /**
   * Nothing to do for now
   */
  public function hookBeforeSaveItem($args)
  {
    // $item = $args['record'];
  }
  /**
   * After save item
   * The hooks on files have generate different datas concerning the item.
   * 
   * — $this->_files2delete
   * Add an xml/tei file will replace the one with the same name, and all the old generated files.
   * Detach old files has to be done at the end of the transaction process.
   * The array $this->_files2delete has been recorded by file hooks.
   * It’s now time to do the work.
   *
   * — $this->_metas
   * Add an xml/tei file should also replace the metadatas from the previous file.
   * Because user may want to add metas from the Omeka form, xml/tei
   * affect only the Dublin Core fields with a value.
   *
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
    // delete here
    foreach ($this->_files2delete as $f) $f->delete();
    $this->_files2delete = array();
  }
  /**
   * On item deletion delete the generated HTML subitems
   */
  function hookBeforeDeleteItem($args)
  {
    $item = $args['record'];
    $this->_db->query("DELETE FROM {$this->_table} WHERE item = {$item->id}");
  }
  /**
   * Insert configuration form
   */
  function hookConfigForm()
  {
    echo get_view()->partial('bookmeka-config-form.php');
  }

  /**
   * Configuration
   * Catch parameters 
   */
  function hookConfig($args)
  {
    $message = array();
    // test if tmpdir writable
    if (!isset($_POST['bookmeka_tmpdir']));
    else if (!$_POST['bookmeka_tmpdir']) { // empty value, delete prop
      delete_option('bookmeka_tmpdir');
    }
    else {
      set_option('bookmeka_tmpdir', rtrim(trim($_POST['bookmeka_tmpdir']), "/\\").'/');
    }
    if (isset($_POST['bookmeka_epub'])) set_option('bookmeka_epub', (boolean)$_POST['bookmeka_epub']);
    if (isset($_POST['bookmeka_html'])) set_option('bookmeka_html', (boolean)$_POST['bookmeka_html']);
    if (isset($_POST['bookmeka_iramuteq'])) set_option('bookmeka_iramuteq', (boolean)$_POST['bookmeka_iramuteq']);
    if (isset($_POST['bookmeka_md'])) set_option('bookmeka_md', (boolean)$_POST['bookmeka_md']);
    if (isset($_POST['bookmeka_site'])) set_option('bookmeka_site', (boolean)$_POST['bookmeka_site']);
    if (isset($_POST['bookmeka_tei'])) set_option('bookmeka_tei', (boolean)$_POST['bookmeka_tei']);

    // will only fire after the javascript hack in config form to set form/@enctype="multipart/form-data"
    while (!empty($_FILES)) {
      // for debug, to see syntax error on the screen
      include(dirname(__FILE__).'/models/Bookmeka/CsvJob.php');
      if (!isset($_FILES['bookmeka_csv'])) {
        $message[] = __('Problème dans le formulaire, il manque le champ bookmeka_csv.');
        break;
      }
      $file = $_FILES['bookmeka_csv'];
      if ($file['error']) {
        $message[] = __('Erreur de téléchargement du fichier CSV.');
        break;
      }
      /*
        (
            [name] => oeuvres.csv
            [type] => application/vnd.ms-excel
            [tmp_name] => C:\wamp\tmp\php670E.tmp
            [error] => 0
            [size] => 114
        )
       */
      Zend_Registry::get('bootstrap')->getResource('jobs')->sendLongRunning(
        'Bookmeka_CsvJob', 
        array('csvpath' => $file['tmp_name'], 'csvname' =>  $file['name'])
      );
      $message[] = __("%s, traitement lancé.", $file['name']);
      break; // dont’t forget it or infinite loop)
    }
    $message[] = __("Bookmeka est configuré.");
    throw new Omeka_Validate_Exception($message);
  }

  function hookAdminHead($request)
  {
    queue_css_file('bookmeka');
  }
  
  function hookPublicHead($args)
  {
    // TODO, test if a Bookmeka content
    // get resources from a submodule
    queue_css_url(WEB_PLUGIN . '/Bookmeka/libraries/Teinte/tei2html.css');
    queue_js_url(WEB_PLUGIN . '/Bookmeka/libraries/Teinte/Tree.js');
    // path in plugin default structure
    queue_css_file('bookmeka');
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
  /**
   * For specific theme, deliver Bookmeka object
   */
  static function toc($args) {
    // echo '<pre>'.json_encode($args, JSON_FORCE_OBJECT|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).'</pre>';
  }
}