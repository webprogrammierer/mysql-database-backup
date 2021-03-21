# mysql-database-backup

### Dump/Backup a mysql database to .sql.gz files on every cron call


* Dump/Backup a database to .sql.gz files (using mysqldump command) on every cron call of this main file (backup-dbX.php)
* Mysql username and password must be provided thgrough .my.cnf file (additionally port and host if needed)
* Dumps can be created once a day or more often: 4 or xx backups a day (configure cron job to call this file)
* Older backup files are automatically deleted.
* A monthly backup/dump file is generated and never deleted.
* Configure your database settings in your backup-dbX.php file. Call this file in your browser to test the backup. Regularly call it with cron job.


### Usage

For each database you want to backup, create an own 'backup-dbX.php" file.  
db1 --> create 'backup-db1.php" file  
db2 --> create 'backup-db2.php" file  

```
<?PHP
require "DBDump.php";
use DatabaseBackup\DBDump;


// Database MY_DATABASE (your database name)
$dbdump = new DBDump("your_db_name", "/home/mysqlbackup/dbX");  // set 'db_name' and 'destination_path' (outside the www root)

//$dbdump->setMyCnfFilename("/home/.my.cnf");   // use setMyCnfFilename() if your .my.cnf is not located in /home/.my.cnf
$dbdump->doBackup();

?>
```

Call this file in your browser to test the backup.  
Regularly call it with cron job.

Enjoy. Your database will now be backed up regularly.


