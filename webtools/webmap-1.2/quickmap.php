<?php
require_once("Database.class.php");
if (!isset($_GET["coords"]))
	exit;

$coords = $_GET["coords"];

$c = explode("x", $coords);
$c[1] |= 0;
$c[2] |= 0;

$ini = parse_ini_file("configs/app.cfg", true);
$db = new Database($ini["Grid"]);

if (!isset($_GET["size"]))
{
	$x = $c[1] * 256;
	$y = $c[2] * 256;

	$result = $db->query_one("select regionName from regions where locX='$x' and locY='$y'" );
	echo $result["regionName"];
	exit;
}

$height = $_GET["size"] | 0;

$resolution=8;
$xmin = $c[1] - ($height/$resolution/2);
$ymin = $c[2] - ($height/$resolution/2);
$xmax = $xmin + ($height/$resolution);
$ymax = $ymin + ($height/$resolution);
$xlow = $xmin*256;
$ylow = $ymin*256;
$xhigh = $xmax*256;
$yhigh = $ymax*256;
$result = $db->query("select regionName,cast(locX/256 as unsigned),cast(locY/256 as unsigned) from regions where locX >= $xlow and locY >= $ylow and locX <= $xhigh and locY <= $yhigh order by RegionName" );
if (!$result) exit;
$lines = mysql_num_rows( $result );

$out = imagecreate( $height, $height );
$black = imagecolorallocate( $out, 0, 32, 32 );
$ocean = imagecolorallocate( $out, 0, 0, 128 );
$plaza = imagecolorallocate( $out, 0, 255, 0 );
$region = imagecolorallocate( $out, 0, 128, 0 );
$block = imagecolorallocate( $out, 255, 0, 0 );
$oceanblock = imagecolorallocate( $out, 128, 0, 0 );
imagefilledrectangle( $out, 0, 0, $height, $height, $black );
for ($x = 0; $x < $height; $x += $resolution )
	for ($y = 0; $y < $height; $y += $resolution )
	{
        $xr = ($xmin + $x / $resolution) * 256;
        $yr = ($ymax - $y / $resolution) * 256;

        $thecolor = $ocean;

		$x2 = $x + $resolution - 2;
		$y2 = $y + $resolution - 2;
		$gridx = $xmin + ($x / $resolution);
		$gridy = $ymin + ($y / $resolution);
		imagefilledrectangle( $out, $x, $y, $x2, $y2, $thecolor );
	}

for ($i = 0; $i < $lines; ++$i)
{
	list( $RegionName, $x, $y ) = mysql_fetch_row( $result );

    $thecolor = preg_match( "/plaza\d+\.osgrid\.org\:/", $host ) ? $plaza : $region;
  # $thecolor = $region;

	$x1 = ($x - $xmin) * $resolution;
	$y1 = $height - (($y - $ymin) * $resolution);
	$x2 = $x1 + $resolution - 2;
	$y2 = $y1 + $resolution - 2;
	imagefilledrectangle( $out, $x1, $y1, $x2, $y2, $thecolor );
}
header( "Content-type: image/png" );
imagepng( $out );
imagedestroy( $out );
?> 

