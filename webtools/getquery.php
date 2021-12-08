<?php
date_default_timezone_set ("Europe/Brussels");
$data=$_GET;

echo "<form action='query.php' method='POST'>";

while(list($key, $value)=each($data)) {
   echo "<input name='$key' value='$value'><br>";
}
echo "<input type='submit' name='submit'>";
echo "</form>";
exit;


$data_url = http_build_query ($data);
$data_len = strlen ($data_url);

$url="http://backoffice.speculoos.world/query.php";


echo file_get_contents (
    $url,
    false,
    stream_context_create (
        array (
            'http'=>array (
                'method'=>'POST',
	    	'header'=>"Connection: close\r\nContent-Length: $data_len\r\n",
	    	'content'=>$data_url
            )
    	)
    )
);

?>

