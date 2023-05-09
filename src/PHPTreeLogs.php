<?php
namespace PHPTree\Core;

class PHPTreeLogs 
{	
	
	/*
	   Write logs
	*/
	public static function writeLogs( $file = null , $logs = array() ) : void{
		
		if ( $file == null OR 
			 is_dir($file) OR 
			 !is_array($logs) OR 
			 sizeof( $logs ) == 0 )
		{
			return;
		}
		
		$string = "";
		
		foreach( $logs AS $log )
		{
			if ( $log['date'] != null )
			{
				$string .= "\n" . $log['date'] ." :: ";
			}else{
				$string .= "\n";
			}
			
			$string .= trim( ( is_array($log) ? json_encode($log) : $log) );
			$string .= "\n----------------------------------------------------------------";
		}
		
		file_put_contents($file, $string.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
	
	
}