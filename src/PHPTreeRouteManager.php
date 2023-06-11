<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeControllerManager AS ControllerManager;
use PHPTree\Core\PHPTreeCacheMemcached AS PTMEMCACHED;
use PHPTree\Core\PHPTreeRoute AS Route;
use PHPTree\Core\PHPTreeCacheRedis AS PTREDIS;
use PHPTree\Core\PHPTreeCache AS PTCACHE;
use PHPTree\Core\PHPTreeCore AS CORE;


class PHPTreeRouteManager 
{	
	
	/*
		Routes list
	*/
	protected static array $list = array();
	/*
		Current requested URL filtered with only Path
	*/	
	static string | null $URI = null;

	private static string | route $route_404;

	private static array $classes = array();
	
	public static function build(){
		
		static::$URI = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
		
		static::loadRoutes();
	}
	/*
	
		List of all registered routes 
		
	*/
	public static function getList() : array {
		return static::$list;
	}

	/*
	
		Add dynamic route
		
	*/
	public static function add(	string $path, ?array $keys_ = null, $method = null ,string $request = Route::GET) : void {
		
		
		$route_regex = $path;
		$keys	     = array();
		
		 /*
		 
			 Replace keys with its regex 
			  
		 */
		  if ( @is_array($keys_) AND !@empty($keys_) )
		  {
			  foreach( $keys_ AS $key => $pat )
			  {
				  $keys[] = $key;
				  $route_regex = str_replace( "{".$key."}" ,  "($pat)" , $route_regex);
			  }
		  }
		/*
				   
		  ReSort the keys ordering based on url path sorting
	   
	    */
	    if ( sizeof($keys) > 0 )
	    {
		   if ( preg_match_all('#\b(' . join("|",$keys) . ')\b#', $path, $matches)) 
		   {
			   $keys = $matches[0];
		   }  
	    } 
		
		//Register all Route information  
		$mapping_key = static::mapKey($path);
		
		static::$list[$mapping_key][$route_regex] = array('url_regex'   => $route_regex,
													  	  'url_path' 	=> $path,
													  	  'method'	    => null,
													  	  'request'	    => $request,
														  'closure'		=> ( is_callable($method)) ? $method : null ,
													  	  'keys' 	 	=> $keys);
	}
	
	
	/*
	
		Fetch and load routes 
		
	*/
	private static function loadRoutes() : void {
		
		/*
	
			CACHE By Memcached
		
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_MEM AND 
			CORE::$env['cache']['memcached']['enabled']  )
		{
			
			if ( PTMEMCACHED::exists('PTRoutes')  )
			{
				static::$list = PTMEMCACHED::get('PTRoutes');
				
			}else{
				static::$list = static::routesByControllers( ControllerManager::$controllers );
				PTMEMCACHED::set('PTRoutes',static::$list,null);
			}
			
		}else	
		/*
	
			CACHE By Redis
	
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_REDIS AND 
			CORE::$env['cache']['redis']['enabled'] )
		{
			
			if ( PTREDIS::exists('PTRoutes')  )
			{
				static::$list =PTREDIS::get('PTRoutes');
				
			}else{
				static::$list = static::routesByControllers( ControllerManager::$controllers );
				PTREDIS::set('PTRoutes',static::$list,null);
			}
			
		}else		
		/*
	
			CACHE By Files
		
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_FILE AND 
			CORE::$env['cache']['file']['enabled']  )
		{
			
			if ( PTCACHE::exists('PTRoutes')  )
			{
			
				static::$list = PTCACHE::get('PTRoutes');
				
			}else{
				static::$list = static::routesByControllers( ControllerManager::$controllers );
				PTCACHE::set('PTRoutes',static::$list,null);
			}
			
		}else
		/*
		
			NO CACHE
		
		*/
		{
			static::$list = static::routesByControllers( ControllerManager::$controllers );
		}
	
	}
	/*
	
		Execute Route
		@param $route , the main route information array
		@param $params , this params will be passed to the route method
		@param $instance , class instance to be passed to route parent class on __construct

	*/
	public static function load( array $route ,  $params = array() , $instance = null )  {
		
		
		//Route request method Checkpoint
		if ($_SERVER['REQUEST_METHOD'] == 'GET' AND 
			$route['request'] == Route::POST ) {
			http_response_code(400);
			die();
		}
		
		//Get current route params
		if ( is_array($route['keys']) AND 
			 sizeof($route['keys']) > 0  )
		{
			foreach( $route['keys'] AS $i => $key )
			{
				$params[$key] = $route['values'][$i];
			}
		}
		
		//Closure
		if ( isset($route['closure']) AND $route['closure'] != null ){
			
			return $route['closure']($params);
			
		}else
		//Class instance already loaded
		if ( !empty(static::$classes) AND static::$classes[$route['class']]  )
		{
			return call_user_func( array( static::$classes[$route['class']]  , 
										  $route['method'] ), 
								   $params );
		}else
		//Load class instance to get the route  
		if ( file_exists($route['path']) )
		{
			include_once($route['path']);
			
			//pass instance to route main class 
			static::$classes[$route['class']] = new $route['class']($instance);
			
			return call_user_func( array(static::$classes[$route['class']] , 
										 $route['method'] ), 
								   $params );
		}
		
		return false;
	}
	/*
	
		Parse selected route 
		
	*/
	private static array $loadded = array();
	
	public static function fetch( string | null $uri = null ) : array | null {
	
		//Get route mapping key
		$route_uri 	  = ( $uri == null OR $uri == "" ) ? static::$URI : $uri;
		$mapping_key  = static::mapKey( $route_uri );
		$route		  = null;
	
		/*
			Route already loaded 
		*/
		if ( static::$loadded[$route_uri] )
		{	
			$route = static::$loadded[$route_uri];
		}else
		/*
		
			Find route by mapped regex 
			
		*/
		if ( static::$list != null AND
		 	isset(static::$list[$mapping_key]) AND
		 	$filter_route = array_filter(array_keys(static::$list[$mapping_key]),
				 									fn ($route) => preg_match( "@^" . $route . "(/|)$@" , $route_uri ) )  )
		{
			
			$route_key	  = $filter_route[array_keys($filter_route)[0]];
			$route 		  = static::$list[$mapping_key][$route_key];
			/*
				Confirm route and get Params
			*/
		    if( @preg_match( "@^" . $route_key . "(/|)$@" ,$route_uri , $m) )
			{
				$route['values']  = array_splice($m, 1);//Remove full url matching
			}
		
			static::$loadded[$route_uri] = $route;
		
			unset($filter_route);
		}
		
		unset($route_uri,$mapping_key);
		
		return $route;
	}
	/*
		Fetch all routes from all provided controllers 
		No cache
	*/
	private static function routesByControllers( $controllers ) : array
	{
		
		$routes = array();
		
		if ( sizeof($controllers) == 0 ){
			return $routes;
		}
		
		foreach( $controllers AS $controller )
		{
			   
		   if ( !file_exists($controller['path'])){
			   continue ;
		   }	
		   
		   include_once($controller['path']);
			
		   //Get class	
		   $reflectionClass = new \ReflectionClass($controller['class']);
		   $methods		    = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
	
		   foreach ($methods as $method) {
				 
				 $reflectionMethod = new \ReflectionMethod($controller['class'], $method->getName());
					 
				 $attributes = $reflectionMethod->getAttributes('PHPTree\Core\PHPTreeRoute');
	
				//Method is route supported
				if ( sizeof($attributes) > 0 )
				{
					  foreach ($attributes as $attribute) {
						  
						$attr 		 = $attribute->newInstance();
						$route_regex = $attr->path;
						$keys	     = array();
						
						 /*
						 
							 Replace keys with its regex if path has keys
							  
						 */
						  if ( @is_array($attr->keys) AND !@empty($attr->keys) )
						  {
							  foreach( $attr->keys AS $key => $pat )
							  {
								  $keys[] = $key;
								  $route_regex = str_replace( "{".$key."}" ,  "($pat)" , $route_regex);
							  }
						  }
						  /*
								  
							 ReSort the keys ordering based on url path sorting
						  
						  */
						  if ( sizeof($keys) > 0 )
						  {
							if ( preg_match_all('#\b(' . join("|",$keys) . ')\b#', $attr->path, $matches)) 
							  {
								  $keys = $matches[0];
							  }  
						  }
						 
						  
						//Register all Route information  
						$mapping_key = static::mapKey($attr->path);
						
						$routes[$mapping_key][$route_regex] = array(  'url_regex'   => $route_regex,
																	  'url_path' 	  => $attr->path,
																	  'method'	  => $method->name,
																	  'request'	  => $attr->request,
																	  'keys' 	 	  => $keys);
															
						$routes[$mapping_key][$route_regex] = array_merge($routes[$mapping_key][$route_regex], $controller);
						
						unset($attr,$attributes,$reflectionMethod,$keys,$mapping_key);
					  }
				 }
			 }//End loop
			 
			 unset($reflectionClass,$methods); 
			 
		}//end loop
		
		unset($controllers); 
		
		return $routes;
	}
	
	/*
	
		Make route mapping key
		
	*/
	public static function mapKey($path) : int {
		
		$mapping_key = explode("/", $path);
		$mapping_key = array_splice($mapping_key, 1);
		return sizeof($mapping_key);
		
	}
}
