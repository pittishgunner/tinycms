<?php
/*private constants*/
define("DATA_FOLDER","__d4ta/");
define("ADMIN","4dm1n");
/*public constants*/
define("BRAND",false);
define("MULTI",false);
define("MAKE_ABS_URLS", true);
define("URL_SEPARATOR","/");
define("URL_ENDING",".html");
define("SR","/");
/*DO NOT PUT this to "true" on a live site, it will reveal important information to your visitors*/
define("DEBUG",false);
/*this is a MD5 Hash of the actual password, use online tools like http://www.md5hashgenerator.com to find it out.*/
define("PASS","21232f297a57a5a743894a0e4a801fc3");
define("LANG","en");
define("THEME_FOLDER","mad");
define("IMAGE_FOLDER",'themes/'.THEME_FOLDER.'/images/pages'); // relative to SR
$plugins=array("forms","gallery","sliders");
$languages=array("en");
require "a55ets/tcClas5.php"; 
?>
