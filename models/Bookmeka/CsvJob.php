<?php
set_time_limit(0);
/**
 * Bookmeka_CsvJob class
 *
 * @copyright Copyright 2015 LABEX OBVIL & frederic.glorieux@fictif.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package Bookmeka
 */
class Bookmeka_CsvJob extends Omeka_Job_AbstractJob
{
  /** Collection id for inserted items */
  private $_collection;
  /** Defaults column separator */
  private $_colsep = "\t";
  /** Full filepath of csv file to parse */
  private $_csvpath;
  /** Original filename of csv file */
  private $_csvname;
  /** When a file already exists, default behavior is to compare dates.
  If force, replace all existing item. */
  private $_force;
  /** Type for iserted item */
  private $_itemtype;
  /**
   * Load a CSV file in Bookmeka
   * 
   * (TODO) test if another file is running
   * Loop on each line
   * test if source file already registred for an item
   *   update item with new file
   *   or create an item
   * update item type
   * update collections
   */
  public function perform()
  {
    _log("Bookmeka CsvJob import ".$this->_csvname.' ('.$this->_itemtype.' '.$this->_csvpath.')');
    $db = $this->_db;
    $handle = fopen($this->_csvpath, 'r');
    // first line should be column names, do something ?
    $row = fgetcsv($handle, 0, $this->_colsep);
    $l = 0;
    $metadata = array();
    if ($this->_itemtype) $metadata['item_type_id'] = $this->_itemtype;
    if ($this->_collection) $metadata['collection'] = $this->_collection;
    while (($row = fgetcsv($handle, 0, $this->_colsep)) !== FALSE) {
      $l++;
      if (empty($row[0])) {
        continue; // do something ?
      }
      $filename = pathinfo($row[0], PATHINFO_FILENAME);
      if (strpos($row[0], 'http') === 0) $file_transfer_type = "Url";
      else if(file_exists($row[0]))  $file_transfer_type = "Filesystem";
      else {
        _log('Bookmeka_CsvJob, not URL and file not found: '.$row[0]);
        continue;
      }
      // TODO, prepare statement
      $sql = "SELECT item, filemtime FROM ".$db->prefix.BookmekaPlugin::TABLE." WHERE filename = ".$db->quote($filename)." AND filemtime > 1 LIMIT 1 ";
      
      $res = $db->query($sql);
      $fetch = $res->fetch();
      // update item
      // TODO compare date if possible for update
      if (isset($fetch['item'])) {
        _log('Bookmeka_CsvJob, update #'.$fetch['item'].': '.$row[0]);
        $item = get_record_by_id('Item', $fetch['item']);
        update_item($item, $metadata);
        // loop on the files of item and delete the ones we will generate here
        foreach($item->Files as $key=>$loopfile) {
          $pathinfo = pathinfo($loopfile->original_filename);
          // if($pathinfo['filename'] != $filename) continue; // only one file by extension
          if(!isset(BookmekaPlugin::$extensions[$pathinfo['extension']])) continue;
          $loopfile->delete();
        }
      }
      // create an item
      else {
        // TODO, tags
        $metadata['public'] = true;
        $item = insert_item($metadata);
        // file hook do no seem to fire from insert item
        _log('Bookmeka_CsvJob, create: '.$row[0]);
      }
      try {
         $file = insert_files_for_item($item, $file_transfer_type, $row[0], array('ignore_invalid_files' => false));
      } 
      catch (Omeka_File_Ingest_InvalidException $e) {
        $msg = "Invalid file URL '".$row[0]."':".$e->getMessage();
        $this->_log($msg, Zend_Log::ERR);
        $item->delete();
        release_object($item);
        continue;
      } 
      catch (Omeka_File_Ingest_Exception $e) {
        $msg = "Could not import file '".$row[0]."': ".$e->getMessage();
        $this->_log($msg, Zend_Log::ERR);
        $item->delete();
        release_object($item);
        continue;
      }
      release_object($file);
      release_object($item);
    }
    // how to alert if finish ?
  }
  public function setCollection($collection) {
    $this->_collection = $collection;
  }
  public function setColsep($colsep) {
    $this->_colsep = $colsep;
  }
  public function setCsvpath($csvpath) {
    $this->_csvpath = $csvpath;
  }
  public function setCsvname($csvname) {
    $this->_csvname = $csvname;
  }
  public function setForce($force) {
    $this->_force = $force;
  }
  public function setItemtype($itemtype) {
    $this->_itemtype = $itemtype;
  }
}