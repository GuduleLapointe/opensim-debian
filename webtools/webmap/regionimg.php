<?php
ini_set("auto_prepend_file", "phpinclude/prepend.php");
require_once("phpinclude/prepend.php");

if (!isset($_GET["uuid"]) || !isset($_GET["size"]))
	exit;

$uuid = escapeshellcmd(mysql_escape_string($_GET["uuid"]));
$s = $_GET["size"] | 0;
$geom = $s."x".$s;
$size = escapeshellarg($geom);

$tmpdir="/home/magic/tmp/backoffice.speculoos.world";
// $url = "http://speculoos.world:8003/assets/$uuid/data";
$url = "http://backoffice.speculoos.world/assets/asset.php?id=$uuid";

if (!file_exists("$tmpdir/$uuid-$geom.jpg"))
{
//	copy ($url, "$tmpdir/$uuid.j2k");
//	exec ("j2k_to_image -i $tmpdir/$uuid.j2k -o $tmpdir/$uuid.tga");
//	exec ("convert -scale $size $tmpdir/$uuid.tga $tmpdir/$uuid-$geom.jpg");

	copy ($url, "$tmpdir/$uuid.jpg");
//	exec ("curl http://backoffice.speculoos.world/assets/asset.php?id=$uuid -o $tmpdir/$uuid.jpg");
	exec ("convert -scale $size $tmpdir/$uuid.jpg $tmpdir/$uuid-$geom.jpg");
//	unlink ("$tmpdir/$uuid.j2k");
	unlink ("$tmpdir/$uuid.tga");
}

if (!file_exists("$tmpdir/$uuid-$geom.jpg"))
{
	exit;
}

header("Content-type: image/jpeg");

$fd = fopen("$tmpdir/$uuid-$geom.jpg", "r");
fpassthru($fd);
fclose($fd);
