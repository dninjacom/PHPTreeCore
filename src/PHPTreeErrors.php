<?php
namespace PHPTree\Core;

class PHPTreeErrors extends \Exception
{	

	public static $dev 			 = true;
	public static $error_message = "We're sorry, our system is temporarily unavailable due to technical issue.";
	public static $logs_file	 = false;
	public static $exceptions 	 = array();
	public static $errors 		 = array();
	public static $silence		 = array(E_USER_WARNING,E_WARNING,E_DEPRECATED);
	
	
	/*
			Handle and print out errors 
			in case of production will print out friendly message .
	*/
	function handleError(): void
	{
		
		$args = func_get_args();
		
	
		if ( in_array($args[0], PHPTreeErrors::$silence ))
		{
			return;
		}
		
		if ( sizeof($args) == 0 )
		{
			return;
		}
		
		$list = array('title'=> isset($args[1]) ? $args[1] : "",
					  'type' => $this->FriendlyErrorType($args[0]), 
		   			  'trace' => '', 
					  'string' => '',
					  'line' => isset($args[3]) ? $args[3] : 0,
					  'file'=>  isset($args[2]) ? $args[2] : "", 
		   			  'message' =>  isset($args[1]) ? $args[1] : "",
					  'ts' => time(),
				 	  'date' => date("Y-m-d H:i:s", time() ));
		
		//append current error to trace 
		 $list['trace'] .= '<tr class="this">
								   <td>
									  ' . ( isset($list['file'])  ? '<div class="detail hard-text"><label>File</label> '. $list['file'] . ' <span>Line '. $list['line'] . '</span></div>' : '') .'
										' . ( isset($list['function'])  ? '<div class="detail light-text"><label>Func</label>'. $list['function'] . '</div>' : '') .'
										' . ( isset($list['class'])  ? '<div class="detail light-text"><label>Class</label>'. $list['class'] . '</div>' : '') .'
									 ' . ( isset($list['args']) AND sizeof($list['args']) > 0 ? '<div class="detail light-text"><label>Args</label>'. json_encode($list['args']) . '</div>' : '') .'
								  </td>
							 </tr>';
		 
		 
		//System in production we do not show full trace error 
	 	if ( !PHPTreeErrors::$dev ) {
		
	 	$list = array( 'title'=> "Error",
						'type' => 'Error', 
						'trace' => '', 
						'file'=> "", 
						'line' => 0,
						'message' => PHPTreeErrors::$error_message,
						'string' => "",
						'ts' => time(),
						'date' => date("Y-m-d H:i:s", time() ));
						
	  		echo $this->compress_html( $this->repalce_arges($list, $this->prepareTemplate() ) );
		
		}else
		//Print out full trace
		{
	   		echo $this->compress_html( $this->repalce_arges($list, $this->prepareTemplate() ) );
		}
		
		//Register exception
		$list['trace'] = array();
		PHPTreeErrors::$errors[] = $list;
	
		exit();
	}
	
	/*
			Handle and print out Exception 
			in case of production will print out friendly message .
	*/
	public function handleException($e) : void{
	  

	  	//Start with previous if exists 
	  	if ( $e->getPrevious() ) {
			  $this->handleException($e->getPrevious());
			  return;
		}
	  
		//Setup Error   
 		$list = array(	'title'=> ($e->getMessage() != null) ? $e->getMessage() : "",
 			   			'type' => 'Exception', 
			   			'trace' => '', 
						'line' => ($e->getLine()) ? $e->getLine() : 0,
 			   			'file'=> ($e->getFile()) ? $e->getFile() : "", 
			   			'message' => ($e->getMessage() != null) ? $e->getMessage() : "",
		  	   			'string' => isset($e->string) ? $e->string : "",
						'ts' => time(),
						'date' => date("Y-m-d H:i:s", time() ));
			 
	 	//append current error to trace 
	 	$list['trace'] .= '<tr class="this">
	   							<td>
		  							' . ( isset($list['file'])  ? '<div class="detail hard-text"><label>File</label> '. $list['file'] . ' <span>Line '. $list['line'] . '</span></div>' : '') .'
										' . ( isset($list['function'])  ? '<div class="detail light-text"><label>Func</label>'. $list['function'] . '</div>' : '') .'
										' . ( isset($list['class'])  ? '<div class="detail light-text"><label>Class</label>'. $list['class'] . '</div>' : '') .'
		 							' . ( isset($list['args']) AND sizeof($list['args']) > 0 ? '<div class="detail light-text"><label>Args</label>'. json_encode($list['args']) . '</div>' : '') .'
	  							</td>
 							</tr>';
	 	
 		//Trace 
 		if ( is_array($e->getTrace()) AND sizeof($e->getTrace()) > 0 )
 		{
	 		foreach( $e->getTrace() AS $trace )
	 		{
				 
		 		$list['trace'] .= '<tr>
 					 					<td>
			 								' . ( isset($trace['file'])  ? '<div class="detail hard-text"><label>File</label> '. $trace['file'] . ' <span> Line '. $trace['line'] . '</span></div>' : '') .'
		   			    					' . ( isset($trace['function'])  ? '<div class="detail light-text"><label>Func</label>'. $trace['function'] . '</div>' : '') .'
		   									' . ( isset($trace['class'])  ? '<div class="detail light-text"><label>Class</label>'. $trace['class'] . '</div>' : '') .'
											' . ( (isset($trace['args']) AND sizeof($trace['args']) > 0) ? '<div class="detail light-text"><label>Args</label>'. json_encode($trace['args']) . '</div>' : '') .'
		 								</td>
									</tr>';
		 		
	 		}
 		}
	 
	    //System in production we do not show full trace error 
	 	if ( !PHPTreeErrors::$dev ) {
	 
		 $list = array( 'title'=> "Exceptions",
					    'type' => 'Exceptions', 
						'trace' => '', 
					    'file'=> "", 
					    'line' => 0,
						'message' => PHPTreeErrors::$error_message,
						'string' => "",
						'ts' => time(),
						'date' => date("Y-m-d H:i:s", time() ) );
						
		  echo $this->compress_html( $this->repalce_arges($list, $this->prepareTemplate() ) );
	 
		}else
		//Print out full trace
		{
		  echo $this->compress_html( $this->repalce_arges($list, $this->prepareTemplate() ) );
		}
		
		//Register exception
		$list['trace'] = $e->getTrace();
		PHPTreeErrors::$exceptions[] = $list;
	 
	 	unset($list);
	}
	
	private function repalce_arges($list,$template) : string {
		
		$pattern	  = array_keys($list);
		$replacement  = array_values($list);
		 
	  	foreach ($pattern AS $key => $value) {
		 	$pattern[$key] = "#{{(?!{)\s*$value\s*}}(?!})#";
	  	}
		 
		return preg_replace( $pattern ,$replacement,  $template);
	}
	
	/*
		Compress HTML output
	*/
	private function compress_html($html)
	{
	   $search = array( '/(\n|^)(\x20+|\t)/',
						'/(\n|^)\/\/(.*?)(\n|$)/',
						'/\n/',
						'/\<\!--.*?-->/',
						'/(\x20+|\t)/', # Delete multi-space (Without \n)
						'/\>\s+\</', # strip whitespaces between tags
						'/(\"|\')\s+\>/', # strip whitespaces between quotation ("') and end tags
						'/=\s+(\"|\')/'); # strip whitespaces between = "'
	
	   $replace = array("\n",
						"\n",
						" ",
						"",
						" ",
						"><",
						"$1>",
						"=$1");
	
		return preg_replace($search,$replace,$html);
	}
	
	//Full Template 
	private function prepareTemplate() : string{
		
		return '<!DOCTYPE html>
					<html lang="en">
	  					<head>
						  <meta charset="utf-8">
						  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
						  <title>{{title}}</title>
						  <style>
						  *{
							  box-sizing: border-box;
							  -webkit-box-sizing: border-box;
							  -moz-box-sizing: border-box;
						  }
						  body{
							  -webkit-font-smoothing: antialiased;
							  background-color: #faf8ee; 
							  background-attachment: fixed;
							  margin : 0px;
							  padding : 0px;
							  font-family : "Verdana"
						  }
						  /* Table Styles */
						  table {
							  border-radius: 5px;
							  font-size: 12px;
							  font-weight: normal;
							  border: none;
							  border-collapse: collapse;
							  width : 100%;
							  margin : 0 auto; 
							  background-color: white;
							  table-layout: fixed;
						  }
						  table td {
							  width : 100%;
							  border-right: 1px solid #f8f8f8;
							  font-size: 12px;
							  word-wrap:break-word;
							  padding : 10px 5px;
						  }
						  table tr:nth-child(even) {
							  background: #F8F8F8;
						  }
						  .header-titles {
							background-attachment: fixed;
							color : #646856;
							padding : 15px;
							font-weight: 600;
						  }
						  .header-content {
						  	background : white; 
						  	background-attachment: fixed;
						  	color : #4b513a;
						  	padding : 25px;
							font-size: 30px;
							font-weight: 600;
						  }
						  .inner {
							width : 750px;
							margin : 0 auto; 
							table-layout: fixed; 
							word-wrap:break-word;
						  }
						  .table-container {
							background : white; 
						  }
						  table td .detail {
							margin-bottom : 5px;
						    word-break: break-all
						
						  }
						  table td .detail span {
							display: inline-block;
							border-radius: 2px;
							padding : 3px 5px;
							margin-right : 5px;
							text-align: center;
							color : #de9c70;
							font-weight: 100;
							font-size: 11px;
						  }
						  table td .detail label{
							display: inline-block;
							min-width : 40px;  
							background : #d0deac;
							border-radius: 3px;
							padding : 2px;
							margin-right : 5px;
							text-align: center;
							color : #696f56;
							font-size: 11px;
							font-weight: 100;
						  }
						  .light-text {
							color : #808080;  
						  }
						  .hard-text {
							  font-weight: bold;
							  color : black;  
							  font-size: 13px;
						   }
						   .this {
							   background : #faf5c4; 
						   }
						  </style>
						</head>
						  <body>
						  	<div class="header-titles">
						 	 	<div class="inner">
							  	{{type}}
							  	</div>
						  	</div>
						  	<div class="header-content">
						 	 	<div class="inner">
							 	{{message}}
							  	<small>{{string}}</small>
							  	</div>
						  	</div>
						  	<div class="table-container">
						 	 	<div class="inner">
							  	<table class="fl-table">
								  	<tbody>
								  		{{trace}}
								  	<tbody>
							  	</table>
							  	</div>
						  	</div>
						  </body>
					</html>';
		
	}
	
	private function FriendlyErrorType($type)
	{
	
		switch($type)
		{
			case E_ERROR: // 1 //
				return 'E_ERROR';
			case E_WARNING: // 2 //
				return 'E_WARNING';
			case E_PARSE: // 4 //
				return 'E_PARSE';
			case E_NOTICE: // 8 //
				return 'E_NOTICE';
			case E_CORE_ERROR: // 16 //
				return 'E_CORE_ERROR';
			case E_CORE_WARNING: // 32 //
				return 'E_CORE_WARNING';
			case E_COMPILE_ERROR: // 64 //
				return 'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: // 128 //
				return 'E_COMPILE_WARNING';
			case E_USER_ERROR: // 256 //
				return 'E_USER_ERROR';
			case E_USER_WARNING: // 512 //
				return 'E_USER_WARNING';
			case E_USER_NOTICE: // 1024 //
				return 'E_USER_NOTICE';
			case E_STRICT: // 2048 //
				return 'E_STRICT';
			case E_RECOVERABLE_ERROR: // 4096 //
				return 'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: // 8192 //
				return 'E_DEPRECATED';
			case E_USER_DEPRECATED: // 16384 //
				return 'E_USER_DEPRECATED';
		}
	
		return "";
	}
	
}