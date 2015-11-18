<?php
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
    _log("Bookmeka import ".$this->_csvname.' ('.$this->_csvpath.')');
    $db = $this->_db;
    $handle = fopen($this->_csvpath, 'r');
    // first line is column names, do something ?
    $row = fgetcsv($handle, 0, $this->_colsep);
    $l = 0;
    while (($row = fgetcsv($handle, 0, $this->_colsep)) !== FALSE) {
      $l++;
      if (empty($row[0])) { // first row, should be labels
        continue; // do something ?
      }
      $filename = basename($row[0]);
      if (strpos($row[0], 'http') === 0) $file_transfer_type = "Url";
      else if(file_exists($row[0]))  $file_transfer_type = "Filesystem";
      else {
        _log('Bookmeka_CsvJob, not URL and file not found: '.$row[0]);
        continue;
      }
      $res = $db->getTable('file')->findBy(array("original_filename" => $filename));
      // update item
      if (!empty($res)) {
        $item = $res[0]->item_id;
        insert_files_for_item($item, $file_transfer_type, $row[0]);
        _log('Bookmeka_CsvJob, update: '.$row[0]);
      }
      // create an item
      else {
        // TODO, tags
        $pars = array('public' => true);
        if ($this->_itemtype) $pars['item_type_id'] = $this->_itemtype;
        if ($this->_collection) $pars['item_type_id'] = $this->_collection;
        $item = insert_item($pars);
        // file hook do no seem to fire 
        insert_files_for_item($item, $file_transfer_type, $row[0]);
        _log('Bookmeka CsvJob, create: '.$row[0]);
      }
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