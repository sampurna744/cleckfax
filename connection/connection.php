<?php
$conn = oci_connect('TEST', 'test', '//localhost/xe');

if (!$conn) {
   $m = oci_error();
   echo $m['message'], "\n";
   exit;
} 
?>
