<?
$data = array();
$fh = fopen("http://www.cpc.ncep.noaa.gov/data/indices/sstoi.indices","r");
if(!$fh){
  print "failed to open file";
  exit(0);
}
while (!feof ($fh)) {
   $line = fgets ($fh, 1024);
   array_push($data, $line);
}
fclose($fh);

print_r($data);
?>

