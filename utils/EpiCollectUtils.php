<?php

class EpiCollectUtils
{
    static function genStr($length = 22)
    {   
        $source_str = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        while(strlen($source_str) < $length)
        {
            $source_str .= $source_str;
        }
	
	$rand_str = str_shuffle($source_str);
	$str = substr($rand_str, -$length);

        unset($source_str, $rand_str);
        
        return $str;
    }
    
    static function array_get_if_exists($array, $key)
    {
            if(array_key_exists($key, $array))
            {
                    return $array[$key];
            }
            else
            {
                    return null;
            }
    }
    
    static function escapeTSV($string)
    {
	$string = str_replace("\n", '\n', $string);
	$string = str_replace("\r", "\\r", $string);
	$string = str_replace("\t", "\\t", $string);
	return $string;
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
            if( $fmt === false ) return $date->getTimestamp() * 1000;
            else return $date->format($fmt);
    }

    static function regexEscape($s)
    {
            $s = str_replace("/" , "\/" , $s);
            return $s;
    }


}
?>
