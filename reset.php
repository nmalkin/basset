<?php
require('database.php');
$dbh = Database::handle();

//$dbh->exec('TRUNCATE TABLE sessions');
$dbh->exec('DELETE FROM sessions');
$dbh->exec('DELETE FROM group_requests');
$dbh->exec('DELETE FROM groups');

echo "<html><pre>Done. \nPDO::errorInfo():\n";
print_r($dbh->errorInfo());
echo '</pre>';
?>
