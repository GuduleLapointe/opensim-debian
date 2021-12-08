<?
ini_set("auto_prepend_file", "phpinclude/prepend.php");
require_once("phpinclude/prepend.php");

if (!isset($_GET["uuid"]) || !isset($_GET["size"]))
	exit;

$uuid = escapeshellcmd(mysql_escape_string($_GET["uuid"]));
$s = $_GET["size"] | 0;
$geom = $s."x".$s;
$size = escapeshellarg($geom);

$url = "http://myassetserver.com:8003/assets/$uuid/data";

if (!file_exists("/tmp/$uuid-$geom.jpg"))
{
	copy ($url, "/tmp/$uuid.j2k");
	exec ("j2k_to_image -i /tmp/$uuid.j2k -o /tmp/$uuid.tga");
	exec ("convert -scale $size /tmp/$uuid.tga /tmp/$uuid-$geom.jpg");
	unlink ("/tmp/$uuid.j2k");
	unlink ("/tmp/$uuid.tga");
}

if (!file_exists("/tmp/$uuid-$geom.jpg"))
{
	exit;
}

header("Content-type: image/jpeg");

$fd = fopen("/tmp/$uuid-$geom.jpg", "r");
fpassthru($fd);
fclose($fd);
