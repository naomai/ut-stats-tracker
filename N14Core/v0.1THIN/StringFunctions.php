<?php
/* String Manipulation Functions
 *
 * 2015 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '15-12-09 Created
 * 
 */

function getVariableValueFromPHPCode($varName, $code){
	if(!preg_match('#\$'.$varName.'\s*=\s*("|\')([^"\']*)("|\')\s*;#s',$code,$mat) || $mat[1]!=$mat[3]){
		return false;
	}
	return $mat[2];
}

?>