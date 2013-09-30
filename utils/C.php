<?php 
//A library of utility functions that can be used across projects
class C
{

	static function array_get_val_if_exists($array, $key, $default = null)
	{
		if(array_key_exists($key, $array))
		{
			return $array[$key];
		}
		else
		{
			return $default;
		}
	}

	static function escape_xml($str)
	{
		return str_replace('>', '&gt;', str_replace('<', '&lt;', str_replace('&', '&amp;', $str)));
	}
	

	static function escapeTSV($string)
	{
		$string = str_replace("\n", "\\n", $string);
		$string = str_replace("\r", "\\r", $string);
		$string = str_replace("\t", "\\t", $string);
		return $string;
	}
	
	
	/**
	 * Generate a random string of length $len
	 * 
	 * @param number $len string length
	 * @return string
	 */
	static function genStr($len = 22)
	{
		$source_str = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$new_source_str = '';
		
		while(len($new_source_str) < $len){
			$new_source_str = $new_source_str . $source_str; 
		}
		
		$rand_str = str_shuffle($new_source_str);
		$str = substr($rand_str, -$len);
	
		unset($new_source_str, $rand_str);
	
		return $str;
	}
	
	static function assocToDelimStr($arr, $delim)
	{
		$str = implode($delim, array_keys($arr[0])) . "\r\n";
		for($i = 0; $i < count($arr); $i++)
		{
			$str .= implode($delim, array_values($arr[$i])) . "\r\n";
		}
		return $str;
	}
	
	static function getTimestamp($fmt = false)
	{
		$date = new DateTime("now", new DateTimeZone("UTC"));
		if( !$fmt ) return $date->getTimestamp() * 1000;
		else return $date->format($fmt);
	}
	
	static function regexEscape($s)
	{
		$s = str_replace("/" , "\/" , $s);
		return $s;
	}
	
}

?>