<?php
/* Nemo PHP ILog Interface
 * 
 * 2014 namonaki14
 * 
 * [insert licensing trash here]
 * 
 * Changelog:
 * 
 * '14-xx-xx Created
 * 
 */

namespace N14;

/*------------------------*\
          N14\ILog
\*------------------------*/

interface ILog{
	const LOG_OUT=1;
	const LOG_ERROR=2;
	const LOG_DEBUG=4;
	const LOG_ALL=0xFFFF;
	
	function write($message, $stream=LOG_OUT);
	function newLine();
}

 
?>