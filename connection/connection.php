<?php 
$conn = oci_connect('swostik', 'swostik', '//localhost/xe'); 
if (!$conn) {
    $m = oci_error();
    echo $m['message'], "\n";
    exit; 
} 
?>