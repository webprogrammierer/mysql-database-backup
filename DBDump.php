<?PHP

namespace DatabaseBackup;

/**
 * DBDump
 *
 * Dump/Backup a database to .sql.gz files (using mysqldump command) on every cron call of main file (backup-dbX.php)
 * Mysql username and password must be provided thgrough .my.cnf file (additionally port and host if needed)
 * Dumps can be created once a day or more often: 4 or xx backups a day (configure cron job)
 * Older backup files are automatically deleted.
 * A monthly backup/dump file is genserated and never deleted.
 *
 */
class DBDump {
  
  private $dbname;
  private $dest_path;
  private $dest_fname;
  private $dump_file;
  private $mycnf_file = "/home/.my.cnf";
  
  
  /**
   * Constructor
   */
  public function __construct($database_name, $destination_path) {
    $this->dbname = $database_name;
    $this->dest_path = $destination_path;
    
    if (!file_exists($this->dest_path)) {
      mkdir($this->dest_path);
    }
    
    $nextFileNr = $this->getNextFileNumberOfThisDay();
    $this->dest_fname = $this->getDestFileName($nextFileNr, $this->dbname);
    
    $this->dump_file = "{$this->dest_path}/{$this->dest_fname}";
  }
  
  
  /**
   * Set path and filename to your my.cnf file
   */
  public function setMyCnfFilename($my_cnf_path_and_filename) {
    $this->mycnf_file = $my_cnf_path_and_filename;
  }
  
  
  /**
   * doBackup()
   * Do the Backup of the database and dump it to the specified file
   */
  public function doBackup() {
    
    echo "\n<p><br>Backup Database $this->dbname:</p>";
    
    // mysqldump command 

    //$sh_cmd = "mysqldump --defaults-file=/home/.my.cnf  wa6281_db6|gzip > $dump_file";

    // do not zip immediately with pipe |gzip, otherwise the return value ($return_var) is always 0, since it comes from gzip
    // therefore only zip afterwards
    //$sh_cmd = "mysqldump --defaults-file=/home/.my.cnf  wa6281_db6  > $dump_file";  

    // Use this solution to output error messages
    // https://serverfault.com/questions/757462/mysqldump-using-php-exec-not-dumping-file-but-no-error
    // The solution is to run the command in a sub-shell and then output the stderr to stdout. This way, the $output is well populated.

    $sh_cmd = "(mysqldump --defaults-file=$this->mycnf_file --skip-add-drop-table  $this->dbname  > $this->dump_file) 2>&1";  // ohne |gzip (siehe oben)

    echo "\n<br>Dumping Database to '$this->dump_file'";
    $return_var = NULL;
    $output = NULL;
    exec($sh_cmd, $output, $return_var);  // do a mysqldump with exec command
    //$this->show_exec_output($return_var, $output);

    if ($return_var == 0) { // mysqldump call was free of errors
      exec("(gzip  $this->dump_file) 2>&1", $output, $return_var);  // zip the dump with gzip 
      //$this->show_exec_output($return_var, $output);
      if ($return_var == 0) {
        echo "\n<br>Backup File '$this->dump_file.gz' successfully created!";
      } else {
        echo "\n<br>Error creating gzip file '$this->dump_file.gz'!";
      }
    } else {  // 
      echo "\n<br>Error creating database dump file $this->dump_file";
    }
    
    // call the monthly Backup
    $this->doMonthlyBackup();
    
    // delete old Backup files
    $this->deleteOldBackupFiles();
  }

  private function show_exec_output($return_var, $output) {
    echo "\n\n<p>output = ".$output;
    echo "\n<pre>"; print_r($output); echo "\n</pre>";
    echo "\n\n<p>return_var = ".$return_var;
  }

  
  
  /**
   * doMonthlyBackup()
   */
  private function doMonthlyBackup() {
    // create a monthly backup/dump file which is never deleted:
    // 2021-03-01-month02-wa6281_db6.sql.gz
    
    echo "\n\n<p>Create monthly Backup, if it still not exists ...";
    
    $monthly_fname = $this->getMonthlyFileName($this->dbname);
    
    $search_fname = date("Y")."*-month".date("m", strtotime("-1 months"))."-".$this->dbname.".sql.gz";
    chdir($this->dest_path);
    $existing_files_arr = glob($search_fname);
    
    if (count($existing_files_arr) == 0) {  // the monthly backup does not exist, we create it now
      $sh_cmd = "(mysqldump --defaults-file=/home/.my.cnf  $this->dbname  > $monthly_fname) 2>&1";
      
      echo "\n<br>Dumping Database to '$monthly_fname'";
      $return_var = NULL;
      $output = NULL;
      exec($sh_cmd, $output, $return_var);  // do a mysqldump with exec command

      if ($return_var == 0) { 
        exec("(gzip  $monthly_fname) 2>&1", $output, $return_var);  // zip the dump with gzip 
        if ($return_var == 0) {
          echo "\n<br>Monthly Backup File '$monthly_fname.gz' successfully created!";
        } else {
          echo "\n<br>Error creating gzip file '$monthly_fname.gz'!";
        }
      } else {  // 
        echo "\n<br>Error creating database dump file $monthly_fname";
      }
    } else {
      echo "\n<br>Monthly Backup '".$search_fname."' already exists!";
    } 
  }
  
  
  /**
   * deleteOldBackupFiles()
   */
  public function deleteOldBackupFiles() {
    echo "\n\n<p><br>Delete old Backup files:</p>";
    echo "\nweek -0 (current week): delete backup files with even file number, if older than 4 days (delete f2, f4, f6,..)";
    echo "\n<br>week -1: delete every 2nd file with an odd file number (delete f3, f7, f11, ..), if older than 7 days";
    echo "\n<br>week -2: delete all files of even days";
    echo "\n<br>week -3: delete every file with an odd file number except file number 1";
    echo "\n<br>week -4: delete all remaining backup files";
    echo "\n<br>Monthly Backup files are never deleted!";

    chdir($this->dest_path);    
    
    // week-0:
    echo "\n\n<p>week -0:";
    $week = date("W");
    $search_files = date("Y", strtotime("-0 weeks"))."*-w".$week."*".$this->dbname;
    $existing_oldfiles_arr = glob("$search_files*");
    
    $today_minus4 = date("Y-m-d", strtotime("-4 days"));
    $del_cnt = 0;
    foreach($existing_oldfiles_arr AS $oldfn) {
      preg_match("/-f(\d+)-/", $oldfn, $tmp_arr);
      $oldfnumber = intval($tmp_arr[1]);
      if ($oldfnumber % 2 == 0) {  // only even files, if older than 4 days
        $oldfile_day = substr($oldfn, 0, 10);
        if (($oldfile_day < $today_minus4) && file_exists($oldfn)) {
          unlink("$this->dest_path/$oldfn");
          echo "\n<br>Delete $oldfn";
          $del_cnt++;
        }    
      }
    }
    if ($del_cnt) {
      echo "\n<br>$del_cnt file(s) deleted";
    } else {
      echo "\n<br>No file deleted.";
    }
    
    // week-1:
    echo "\n\n<p>week -1:";
    $week = date("W", strtotime("-1 weeks"));
    $search_files = date("Y", strtotime("-2 weeks"))."*-w".$week."*".$this->dbname;
    $existing_oldfiles_arr = glob("$search_files*");
   
    $today_minus7 = date("Y-m-d", strtotime("-7 days"));    
    $del_cnt = 0;
    foreach($existing_oldfiles_arr AS $oldfn) {
      preg_match("/-f(\d+)-/", $oldfn, $tmp_arr);
      $oldfnumber = intval($tmp_arr[1]);
      if (($oldfnumber+1) % 4 == 0) {  // only every second odd file
        $oldfile_day = substr($oldfn, 0, 10);
        if (($oldfile_day < $today_minus7) && file_exists($oldfn)) {
          unlink("$this->dest_path/$oldfn");
          echo "\n<br>Delete $oldfn";
          $del_cnt++;
        }    
      }
    }
    if ($del_cnt) {
      echo "\n<br>$del_cnt file(s) deleted";
    } else {
      echo "\n<br>No file deleted.";
    }
    
    // week-2:
    echo "\n\n<p>week -2:";
    $week = date("W", strtotime("-2 weeks"));
    $search_files = date("Y", strtotime("-2 weeks"))."*-w".$week."*".$this->dbname;
    $existing_oldfiles_arr = glob("$search_files*");
    
    $del_cnt = 0;
    foreach($existing_oldfiles_arr AS $oldfn) {
      $oldfile_day_only = substr($oldfn, 8, 10);
      if ($oldfile_day_only % 2 == 0) {
        unlink("$this->dest_path/$oldfn");
        echo "\n<br>Delete $oldfn";
        $del_cnt++;
      }
    }
    if ($del_cnt) {
      echo "\n<br>$del_cnt file(s) deleted";
    } else {
      echo "\n<br>No file deleted.";
    }
    
    // week-3:
    echo "\n\n<p>week -3:";
    $week = date("W", strtotime("-3 weeks"));
    $search_files = date("Y", strtotime("-3 weeks"))."*-w".$week."*".$this->dbname;
    $existing_oldfiles_arr = glob("$search_files*");
    
    $del_cnt = 0;
    foreach($existing_oldfiles_arr AS $oldfn) {
      preg_match("/-f(\d+)-/", $oldfn, $tmp_arr);
      $oldfnumber = intval($tmp_arr[1]);
      if ($oldfnumber % 2 != 0) {  // every odd file except file number 1
        if ($oldfnumber != 1 && file_exists($oldfn)) {
          unlink("$this->dest_path/$oldfn");
          echo "\n<br>Delete $oldfn";
          $del_cnt++;
        }    
      }
    }
    if ($del_cnt) {
      echo "\n<br>$del_cnt file(s) deleted";
    } else {
      echo "\n<br>No file deleted.";
    }
    
    // week-4:
    echo "\n\n<p>week -4:";
    $week = date("W", strtotime("-4 weeks"));
    $search_files = date("Y", strtotime("-4 weeks"))."*-w".$week."*".$this->dbname;
    $existing_oldfiles_arr = glob("$search_files*");
    
    $del_cnt = 0;
    foreach($existing_oldfiles_arr AS $oldfn) {
      preg_match("/-f(\d+)-/", $oldfn, $tmp_arr);
      $oldfnumber = intval($tmp_arr[1]);
      if (file_exists($oldfn)) {
        unlink("$this->dest_path/$oldfn");
        echo "\n<br>Delete $oldfn";
        $del_cnt++;
      }    
    }
    if ($del_cnt) {
      echo "\n<br>$del_cnt file(s) deleted";
    } else {
      echo "\n<br>No file deleted.";
    }
    
  }
  
  
  /**
   * getNextFileNumberOfThisDay()
   */
  private function getNextFileNumberOfThisDay() {
    $date = date("Y-m-d");
    $week = date("W");
    
    $search_fname = $date."-w".$week."*".$this->dbname;

    chdir($this->dest_path);
    $existing_files_arr = glob("$search_fname*");
    //$fnum = count($existing_files_arr) + 1;
    
    $existing_files_filenumber_arr = array();
    foreach($existing_files_arr AS $fnval) {
      preg_match("/-f(\d+)-/", $fnval, $tmp_arr);
      $existing_files_filenumber_arr[] = intval($tmp_arr[1]);
    }
    rsort($existing_files_filenumber_arr);
    $fnum = $existing_files_filenumber_arr[0] + 1;
    
    return($fnum);
  }
  
  
  /**
   * getDestFileName()
   */
  private function getDestFileName($fileNr, $dbname) {
    $date = date("Y-m-d");
    $week = date("W");
    
    $dest = $date."-w".$week."-f".$fileNr."-".$dbname.".sql";
    return ($dest);
  }
  
  
  /**
   * getMonthlyFileName()
   */
  private function getMonthlyFileName($dbname) {
    // 2021-03-01-month02-wa6281_db6.sql.gz
    $date = date("Y-m-d");    
    $backup_month = date("m", strtotime("-1 months"));
    
    $dest = $date."-month".$backup_month."-".$dbname.".sql";    
    return($dest);
  }
  
}  




?>