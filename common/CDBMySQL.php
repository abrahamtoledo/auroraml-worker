<?php
/*
##    Simple Database Abstraction Layer 1.2.1 [lib.sdba.php]
##    by Gabe Bauman <gabeb@canada.com>
##    Wednesday, April 05, 2000
##    extended by Michael Howitz <icemac@gmx.net>
##    Thuesday, Jun 15, 2000
##
##    extended by Dirk Howard <dirk@idksoftware.com>
##    Tuesday, August 28, 2001
##
##    Easy way to read and write to any (!)  database.
##    Subclasses for MySQL (CDBMySQL), Oracle (CDB_OCI8) and
##    PostgreSQL (CDB_pgsql) have been written.
##
##    Usage:
##
##    $sql = new CDB_OCI8 ($DB_HOST, $DB_USER, $DB_PASS);
##    $sql -> Query("SELECT Lastname, Firstname FROM people");
##    while ($sql -> ReadRow()) {
##      print $sql -> RowData["Lastname"] . "," . $sql -> RowData["Firstname"] . "<br>\n";
##    }
##    $sql -> Close();
##
##    If you use this software, please leave this header intact.
##    Please send any modifications/additions to the author for
##    merging into the distribution (like other DB subclasses!)
*/

class CDBAbstract {
  var $_db_linkid = 0;
  var $_db_qresult = 0;
  var $_auto_commit = false;
  var $RowData = array();
  var $NextRowNumber = 0;
  var $RowCount = 0;
  function CDBAbstract () {
    die ("CDBAbstract: Do not create instances of CDBAbstract! Use a subclass.");
  }
  function Open ($host, $user, $pass, $db = "", $autocommit = true) {
  }
  function Close () {
  }
  function SelectDB ($dbname) {
  }
  function Query ($querystr) {
  }
  function SeekRow ($row = 0) {
  }
  function ReadRow () {
  }
  function Commit () {
  }
  function Rollback () {
  }
  function SetAutoCommit ($autocommit) {
    $this->_auto_commit = $autocommit;
  }
  function _ident () {
    return "CDBAbstract/1.2";
  }
}

class CDBMySQL extends CDBAbstract {
  /**
   * @var mysqli
   */
  var $mysqli;

  /**
   * @var mysqli_result
   */
  var $_db_qresult;

  function CDBMySQL ($host, $user, $pass, $db = "") {
    $this->Open ($host, $user, $pass);
    if ($db != "")
      $this->SelectDB($db);
  }
  function Open ($host, $user, $pass, $autocommit = true) {
    $this->mysqli= new mysqli($host, $user, $pass);
  }
  function Close () {
    @$this->_db_qresult->free_result();
    return $this->mysqli->close();
  }
  function SelectDB ($dbname) {
    if ($this->mysqli->select_db($dbname) == true) {
      return 1;
    }
    else {
      return 0;
    }
  }
  function Query ($querystr) {
    $result = $this->mysqli->query($querystr);
    if ($result === false) {
      return false;
    }
    elseif($result === true)
    {
    	return true;
    }
    else{
      if ($this->_db_qresult)
        $this->_db_qresult->free_result();
      $this->RowData = array();
      $this->_db_qresult = $result;
      $this->RowCount = $this->_db_qresult->num_rows;
      $this->NextRowNumber = 0;
      if (!$this->RowCount) {
      	// The query was probably an INSERT/REPLACE etc.
      	$this->RowCount = 0;
      }
      return 1;
    }
  }
  function SeekRow ($row = 0) {
    if ((!$this->_db_qresult->data_seek($row)) or ($row > $this->RowCount-1)) {

      printf ("SeekRow: Cannot seek to row %d\n", $row);
      return 0;
    }
    else {
      return 1;
    }
  }
  function ReadRow () {
    if($this->RowData = $this->_db_qresult->fetch_assoc()) {
      $this->NextRowNumber++;
      return 1;
    }
    else {
      return 0;
    }
  }
  function Commit () {
    return $this->mysqli->commit();
  }
  function SetAutoCommit($autocommit)
  {
    parent::SetAutoCommit($autocommit);
    $this->mysqli->autocommit($autocommit);
  }

  function Rollback () {
    return $this->mysqli->rollback();
  }
  function _ident () {
    return "CDBMySQL/1.2";
  }

  function BeginTransaction(){
    $this->mysqli->begin_transaction();
  }


}