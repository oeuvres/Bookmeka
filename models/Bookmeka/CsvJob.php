<?php
/**
 * Bookmeka_CsvJob class
 *
 * @copyright Copyright 2015 frederic.glorieux@fictif.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package Bookmeka
 */
class Bookmeka_CsvJob extends Omeka_Job_AbstractJob
{
  private $_csvname;
  private $_csvpath;
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
    
    // how to alert if finish ?
  }
  public function setCsvpath($csvpath) {
    $this->_csvpath = $csvpath;
  }
  public function setCsvname($csvname) {
    $this->_csvname = $csvname;
  }
}