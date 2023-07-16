<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeErrors;
use PHPTree\Core\PHPTreeRoute as Route;
use PHPTree\Core\PHPTreeCore AS CORE;
use PHPTree\Core\PHPTreeCacheMemcached AS PTMEMCACHED;
use PHPTree\Core\PHPTreeSecure as Secure;
use PHPTree\Core\PHPTreeCacheRedis AS PTREDIS;
use PHPTree\Core\PHPTreeCache AS PTCACHE;


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
	
		System execution time 
	
	*/	
	public static function executionTime() : float {
		
		return round( microtime(true) - START_TIME_DEBUG , 3);
		
	}
	/*
	
		Debugger 
	
	*/
	public static function debugger(){
		 
		
		/*
		
			FLUSH TOKEN VALIDATION
		
		*/
		
		$data = Secure::safePost(); 
		
	
		if ( isset($data['flush_token']) )
		{
			$token = Secure::validateToken( $data['flush_token'] );
			
			if ( $token->isValid() AND
				 isset($token->payload['flush'])  )
			{
				
				switch( $token->payload['flush']) {
					
					case "memcached":
						PTMEMCACHED::flush();
					break;
					case "redis":
						PTREDIS::flush();
					break;
					case "fc":
						PTCACHE::flush(true);
					break;
				}
			
			}
		}	
		

		$tabs = static::$customTabs;
		
		//Make default tabs
		$tab_performance 			= array();
		$tab_performance['title']   = "System"; 
		$tab_performance['content'] = array();
		
		if ( defined('START_TIME_DEBUG') )
		{
			$tab_performance['content'][] =  array('Execution time',  static::executionTime() . "/ms" );
		}
		
		//CPU load
		if ( function_exists("sys_getloadavg") )
		{
			$tab_performance['content'][] =  array('Load average',  implode(",", sys_getloadavg()) );
		}
		
		//Memory usage
		if ( function_exists("memory_get_usage") )
		{
			$tab_performance['content'][] =  array('Memory usage', (memory_get_usage(false)/1024/1024) . "/MB" );
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
		
		//Errors / Warnings
		if ( sizeof(PHPTreeErrors::$errors) > 0 )
		{
			$tab_errors				 = array();
			$tab_errors['title']     = "Errors & Warnings"; 
			$tab_errors['content'][] = array();
			$tab_errors['badge']	 = count(PHPTreeErrors::$errors);
			
			foreach( PHPTreeErrors::$errors AS $error )
			{
				$tab_errors['content'][] = array( $error['file'],$error['line'],$error['message'] );	
			}
		
			$tabs[] =  $tab_errors;
		}
		
		//Routes
		$tab_routes				 = array();
		$tab_routes['title']     = "Routes"; 
		$tab_routes['content'][] = array();
		$tab_routes['content'][] = array( "Path" , "Regex" , "Method" , "Loaded" , "Cached" , "Enabled");	
			
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
													  ( $route->isCached ? "<b>Yes</b>" : "No"),
													  ( $route->isEnabled ? "<b>Yes</b>" : "No") );	
				}
				
			}
		}
		
		$tabs[] =  $tab_routes;
		
			
		//MEMCACHED 
		if ( CORE::$env['cache']['memcached']['enabled']  )
		{
			PTMEMCACHED::init();
			
			//Flush token
			$tab_memcached_ft 			= Secure::generateToken( ['flush'=>'memcached'] );
			
			$tab_memcached				= array();
			$tab_memcached['title']     = "Memcached"; 
			$tab_memcached['content'][] = array();
			$tab_memcached['content'][] = array( "Enabled" , "Yes"  );
			$tab_memcached['content'][] = array( "Flush all caches" , 
											 	 "<form method='post'><button type=\"submit\">Flush</button><input type=\"hidden\" name=\"flush_token\" value=\"$tab_memcached_ft\"></form>"  );	
			$tab_memcached['content'][] = array( "All Keys" , implode(",<br />", PTMEMCACHED::$instance->mem->getAllKeys() ) );
			$tabs[] =  $tab_memcached;	
		}	
		
		//REDIS
		if ( CORE::$env['cache']['redis']['enabled']  )
		{
			PTREDIS::init();
			
			//Flush token
			$tab_redis_ft 			= Secure::generateToken( ['flush'=>'redis'] );
			
			$tab_redis				= array();
			$tab_redis['title']     = "Redis"; 
			$tab_redis['content'][] = array();
			$tab_redis['content'][] = array( "Enabled" , "Yes"  );
			$tab_redis['content'][] = array( "Flush all caches" , 
												  "<form method='post'><button type=\"submit\">Flush</button><input type=\"hidden\" name=\"flush_token\" value=\"$tab_redis_ft\"></form>"  );	
			$tab_redis['content'][] = array( "All Keys" , implode(",<br />", PTREDIS::$instance->redis->keys('*') ) );
			$tabs[] =  $tab_redis;	
		}	
		
		//FILES
		if ( CORE::$env['cache']['file']['enabled']  )
		{
			PTCACHE::init();
			
			//Flush token
			$tab_fc_ft 			 = Secure::generateToken( ['flush'=>'fc'] );
			
			$tab_fc				 = array();
			$tab_fc['title']     = "Cache file"; 
			$tab_fc['content'][] = array();
			$tab_fc['content'][] = array( "Enabled" , "Yes"  );
			$tab_fc['content'][] = array( "Flush all caches" , 
												  "<form method='post'><button type=\"submit\">Flush</button><input type=\"hidden\" name=\"flush_token\" value=\"$tab_fc_ft\"></form>"  );	
			$tab_fc['content'][] = array( "All Keys" , implode(",<br />", PTCACHE::allKeys() ) );
			$tabs[] =  $tab_fc;	
		}	
		
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