<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeErrors;
use PHPTree\Core\PHPTreeRoute as Route;


class PHPTreeLogs 
{	
	
	
	private static array $customTabs = array();
	
	/*
	
	   Write logs
	   
	*/
	public static function writeLogs( $file = null , $logs = array() ) : void
	{
		
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
	/*
	
	   Add Custom Tab
	   
	*/
	public static function addTab( string $title , string | array $content , int $badge = 0 ){
		static::$customTabs[] = array('title' => $title , 'content' => $content , 'badge' => $badge);
	}
	
	/*
	
		Debugger 
	
	*/
	public static function debugger(){
		

		//Args is debug bites each bite has title and its array of information
		$args = func_get_args();
		$tabs = static::$customTabs;
		
		//Make default tabs
		$tab_performance 			= array();
		$tab_performance['title']   = "System"; 
		$tab_performance['content'] = array();
		
		if ( defined('START_TIME_DEBUG') )
		{
			$tab_performance['content'][] =  array('Execution time',  round( microtime(true) - START_TIME_DEBUG , 3) . "/ms" );
		}
		
		foreach ( $_SERVER AS $key => $val ) {
			$tab_performance['content'][] = array($key, $val);
		}
		
		$tabs[] =  $tab_performance;
		
		//Loaded classes
		$tab_classes 			  =  array();
		$tab_classes['title']     =  "Loaded classes"; 
		$tab_classes['content'][] =  array( implode(",<br />", get_declared_classes()) );
		$tabs[] 				  =  $tab_classes;
		
		//Errors
		$tab_errors				 = array();
		$tab_errors['title']     = "Errors & Warnings"; 
		$tab_errors['content'][] = array();
		$tab_errors['badge']	 = count(PHPTreeErrors::$errors);
			
		if ( sizeof(PHPTreeErrors::$errors) > 0 )
		{
			foreach( PHPTreeErrors::$errors AS $error )
			{
				$tab_errors['content'][] = array( $error['file'],$error['line'],$error['message'] );	
			}
		}
	
		$tabs[] =  $tab_errors;
		
		//Routes
		$tab_routes				 = array();
		$tab_routes['title']     = "Routes"; 
		$tab_routes['content'][] = array();
			
		$tab_routes['content'][] = array( "Path" , "Regex" , "Method" , "Loaded" , "Cached" );	
			
		//	echo "<pre>"; print_r( Route::getList());
		if ( sizeof(Route::getList()) > 0 )
		{
			foreach( Route::getList()['routes'] AS $mapKey => $routes )
			{
				
				foreach( $routes AS $route )
				{
					$method = $route->method ;
					
					if (  $route->method instanceof \Closure  )
					{
						$method = "Closure";
					}else
					if (  is_array($route->method)  )
					{
						$method = json_encode($route->method,true);
					}else
					if ( $route->method == null   )
					{
						$method = "Null";
					}
					
					$tab_routes['content'][] = array( $route->loaded ? "<b>".$route->path."</b>" : $route->path, 
													  $route->loaded ? "<b>".$route->regex."</b>": $route->regex, 
													  $route->loaded ? "<b>".$method."</b>": $method,  
													  $route->loaded ? "<b>Yes</b>" : "No",
													  ( $route->isCached ? "<b>Yes</b>" : "No") );	
				}
				
			}
		}
		
		$tabs[] =  $tab_routes;
		
		
		//Go Json format version 
		if( isset($_REQUEST['debug_json']) )
		{
			echo json_encode($tabs , true );
		    exit();
		}
		
		//Printout debugging content 
		echo '<style>
				#debugger .tabs {
		  			display: flex;
		  			flex-wrap: wrap;
		  			width: 100%;
					padding-top : 100px;
					font-family : "Verdana"
				}
				#debugger .tabs label {
		  			order: 1;
		  			justify-content: center;
		  			align-items: center;
		  			padding: 1rem 2rem;
		  			margin-right: 0.2rem;
		  			cursor: pointer;
		  			background-color: #faf8ee;
		  			transition: background ease 0.5s;
					border-radius: 15px 15px 0px 0px;
					font-size: 14px;
					font-weight: normal;
					position : relative;
				}
				#debugger .tabs label span {
					display: table-cell;
					font-weight: bold;
					font-size: 12px;
					color : white;
					position : absolute;
					padding: 3px 6px;
					background-color: #ed532d;
					border-radius: 50%;
					vertical-align: middle;
					text-align: center;
					top : 4px;
					right : 4px;
				}
				#debugger .tabs .tab {
		  			order: 9;
		  			flex-grow: 1;
		  			width: 100%;
		  			height: 100%;
		  			display: none;
		  			padding: 1rem;
		  			background: #d0deac;
		  			padding: 20px;
				}
				#debugger .tabs input[type="radio"] {
		  			display: none;
				}
				#debugger .tabs input[type="radio"]:checked + label {
		  			background: #d0deac;
					color : #4b513a;
				}
				#debugger .tabs input[type="radio"]:checked + label + .tab {
		  			display: block;
				}
				#debugger table {
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
				  #debugger table td {
					  width : 100%;
					  border-right: 1px solid #f8f8f8;
					  font-size: 12px;
					  word-wrap:break-word;
					  padding : 10px 5px;
				  }
				  #debugger table tr:nth-child(even) {
					  background: #F8F8F8;
				  }
		</style>
		<div id="debugger">
		  <div class="tabs">';
			
			//Print Tabs
			foreach( $tabs AS  $i1 => $tab  ){
				
				echo "<input type=\"radio\" name=\"tabs\" id=\"tab_$i1\" " . ( $i1 == 0 ? "checked=\"checked\"" : "" ) . ">
					  <label for=\"tab_$i1\">$tab[title]  " . ( $tab['badge'] ? "<span>$tab[badge]</span>" : "" ) . "</label>
					  <div class=\"tab\">
						";
				
				if ( is_array($tab['content']) )	
				{
					echo "<table class=\"fl-table\">
							<tbody>";
							
					foreach( $tab['content'] AS $i2 => $content )
					{
						if ( is_array($content) )
						{
							echo "<tr>";
								foreach( $content AS $contentBite)
								{
									echo "<td>
											$contentBite
								   	  	  </td>";
								}
							echo "</tr>";
							
						}else{
							echo "<tr>
							   		<td>
										$content
							  		</td>
								</tr>";
						}
					}
					
					echo "<tbody>
						 </table>";
					
				}else{
					echo $tab['content'];
				}
					
				echo "</div>";
				
			}
			
		echo '</div></div>';
	}
	
	
}