<html>
<body>
<p>Testing Trans-Nino</p>
<?
	@ $serverVars = array($_COOKIE['unicorn'], $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT'], $_SERVER['HTTP_HOST'], $_SERVER['HTTP_USER_AGENT'],
					   $_SERVER['QUERY_STRING'], $_SERVER['HTTP_REFERER'], $_SERVER['DOCUMENT_ROOT'], $_SERVER['SCRIPT_FILENAME']);

	echo '<p>$_SERVER[\'REMOTE_ADDR\'] == '.$_SERVER['REMOTE_ADDR'];
?>