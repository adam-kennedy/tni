<?
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  common.html.php
// Use:  defines common html snippets
// Created:  Lawrence Duncan [lawrence@orionimaging.com], 2/25/2006, for Adam Kennedy [kennaster@gmail.com]

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  head()
// Use:  adds header to html pages, without opening body tag
// In:  $serverVars are environment vars, then either nothing, or named arguments [eg: head('title=Some Title','option=Some Option')]...
// Out:  nice head stuff...
//  *Note: the <head> is left open and must be closed manually...
function head(&$serverVars)
{
	$titleDefault = 'Trans-Ni&ntilde;o Index Calculator'; //set default title...
	$options = array();  $optionsCount = 0; //initialize options holder...

	if (func_num_args()===1) //no arguments are present, so set defaults...
	{ $title = $titleDefault;  $options = false; }
	else
	{ //options are present, so proceed with extraction...
		for ($i=1; $i<func_num_args(); ++$i)
		{ //extract options arguments...
			if (substr(func_get_arg($i),0,strlen('title='))=='title=') //extract title...
			{ $title = substr(func_get_arg($i),strlen('title=')); }  else $title = $titleDefault;
			if (substr(func_get_arg($i),0,strlen('option='))=='option=') //extract options...
			{ $options[$optionsCount] = substr(func_get_arg($i),strlen('option='));  ++$optionsCount; }  else $options = false;
		} //end for: extract options arguments;
	} //end else: options are present, so proceed with extraction;

	if ($options) die(error('head(): no options are yet implemented.'));
	
	//<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n"
		  .'<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">'."\n"
		  .'<head>'."\n"
		  .'  <title>&nbsp;- '.$title.' -&nbsp;</title>'."\n"
		  .    head_meta_info($serverVars, $title)."\n"
		  .'  <link rel="stylesheet" type="text/css" href="css_trans-nino_main.css"></link>'."\n"
		  .'  <script language="JavaScript" type="text/javascript" src="javascripts_trans-nino_main.js"></script>'."\n";
		  
  return;
} //end function: head_nobody();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  head_meta_info()
// Use:  adds meta information to header for html pages
// In:  $serverVars are environment vars, $title is string for document title [as passed into or set by head()]
// Out:  nice meta information for an html head...
function head_meta_info(&$serverVars, $title)
{
	$meta =
	 '  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />'."\n"
	.'  <meta name="copyright" content="Adam Kennedy (content), Lawrence Duncan (code)" />'."\n"
	.'  <meta name="description" content="Real Time Trans-Nino Index Calculator" />'."\n"
	.'  <meta name="keywords" content="Trans-Nino Index,Climate Teleconnections,Streamflow Prediction,Klamath Basin Water Supply,Climate Change Indicators,'
		 .'Environmental Index,Environmental Science,Global Climate Change,Hydrological Modeling" />'."\n"
	.'  <meta name="robots" content="all" />';

	return $meta;
} //end function: head_meta_info();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  error()
// Use:  returns standard error message as string
// In:  $text is error message to display
// Out: nice error message...
function error($text='')
{
	$error = '<p>&nbsp;</p>
			  <p align="center" style="font-size: 0.9em; font-family: Courier New, Courier, mono;">
			    <span style="color: palevioletred; font-weight: 900;">**Error</span>:&nbsp; '.$text.'</p>';

  return $error;
} //end function: error();

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  warning()
// Use:  returns standard warning message as string
// In:  $text is error message to display
// Out: nice warning message...
function warning($text='')
{
	$warning = '<p align="center" style="font-size: 0.9em; font-family: Courier New, Courier, mono;">
			    <span style="color: palevioletred; font-weight: 900;">**Warning</span>:&nbsp; '.$text.'</p>';

  return $warning;
} //end function: warning();
?>