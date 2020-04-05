<?
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  common.php 
// Use:  common scripts & such...
// Created:  Lawrence Duncan [lawrence@orionimaging.com], 3/5/2006, for Adam Kennedy [kennaster@gmail.com]

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  local()
// Use:  checks to see if running locally
// In:  $scriptName is $serverVar for $HTTP_SERVER_VARS['SCRIPT_FILENAME']   ie: $serverVars[8]
// Out:  boolean...   [**Note: this looks like it works with either $scriptName OR $rootDir... fancy that!]
function local($scriptName)
{
	return array_value(explode('/',$scriptName),0)=='C:'; //first check if local [boolean true if root starts with 'C:']...
} //end function: local();

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  array_value()
// Use:  to get an element's value from an array
// In:  $array is the array to get from, $value is the element number to get (0-based)
//	*Note: if value is -1, then returns the last element...
// Out: nice array element value...
function array_value(&$array, $value)
{
	if ($value==-1) return $array[count($array)-1];
	else return $array[$value];

} //end function: array_value();

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  stdev()
// Use:  calculates standard deviation for a sample
// In:  $values is an array of values to calculate upon, $mean is mean of values [optional]
// Out: nice [numerically stable] standard deviation...
//  *Note:  s := sqrt( sum(x_i - x_ave)^2 / (n-1) )
function stdev(&$values, $mean=false)
{
	die(error('stdev() is not yet implemented.'));	

  return;
} //end function: array_value();

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Title:  multi_get()
// Use:  to extract multiple get vars from a query string [ie: same get var name, but multiple values]
// In:  $serverVars are serverVars [*Note: $serverVars[5] is query string], $getName is name of get var, $getNum is 1-based index of multiple get var
// Out: nice particular get var [out of many same-name-ones]...
function multi_get(&$serverVars, $getName, $getNum)
{
	$queryParts = explode('&', $serverVars[5]);
	$getNumPresent = 0; //initialize [reflects 1-based count of presently-processing same-name-get]...
	
	for ($i=0; $i<count($queryParts); ++$i)
	{ //search through $queryPars for the parts we're interested in...
		$querySubParts = explode('=',$queryParts[$i]);
		if ($querySubParts[0]==$getName)
		{ //found a same-name-get-var that we're interested in, so update $getNumPresent and see if $getNum is also a match...
			++$getNumPresent; //reflects 1-based count of presently-processing same-name-get...
			if ($getNumPresent==$getNum)
			{ //found particular get var of interest, so return value or return false if not set...
				//echo '<p>$getName == '.$getName.' &nbsp; &nbsp; &nbsp; $getNum == '.$getNum.'</p>'; //**debug...
				if (!empty($querySubParts[1])) return $querySubParts[1];
				else return false;
			} //end if: found particular get var of interest, so return value or return false if not set;
			elseif ($i==count($queryParts)) return false;
		} //found a same-name-get-var that we're interested in, so update $getNumPresent and see if $getNum is also a match;
	} //end for: search through $queryPars for the parts we're interested in;

  return false;
} //end function: multi_get();
?>