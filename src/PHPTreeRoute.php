<?php
namespace PHPTree\Core;

use PHPTree\Core\PHPTreeRouteModifier AS RouteModifier;
use PHPTree\Core\PHPTreeCacheMemcached AS PTMEMCACHED;
use PHPTree\Core\PHPTreeCacheRedis AS PTREDIS;
use PHPTree\Core\PHPTreeCache AS PTCACHE;
use PHPTree\Core\PHPTreeCore AS CORE;


class PHPTreeRoute 
{	
	
	/*
		Route request method
	*/
	const GET  	 = 'GET';
	const POST	 = 'POST';
	
	/*
		Routes list
	*/
	protected static array $list = array();
	/*
		Current requested URL filtered with only Path
	*/	
	static string | null $URI = null;
	/*
		
		Loaded and cached classes 
	
	*/
	private static array $classes = array();

	/*
		loaded classes , route class should be called 1 time
	*/
	private static array $loadded = array();
	
	/*
	
		List of all registered routes 
		
	*/
	public static function getList() : array {
		return static::$list;
	}
	/*
	
		Add dynamic route
		
	*/
	private static function add( string $path , string $request ) : RouteModifier {
		
		//Check if already cached!
		if ( isset( static::routes()['keys'] ) AND in_array( $path , static::routes()['keys'] )  ){
			$mapping_key  = RouteModifier::mapKey( $path );
				
			static::routes()['routes'][$mapping_key][$path]->isCached = true;
				
			return  static::routes()['routes'][$mapping_key][$path];
		}
		
		$route = new RouteModifier($path);	
		$route->request = $request;
			
		static::cacheRoute($route);
			
		return $route;
	}
	/*
	
		[GET] Register route 
		
	*/
	public static function get(	string $path ) : RouteModifier {
		return static::add( $path, static::GET );
	}
	/*
	
		[POST] Register route 
		
	*/
	public static function post( string $path ) : RouteModifier {
		return static::add( $path, static::POST );
	}
	/*
	
		Execute Route
		@param $route , the main route information array
		@param $params , this params will be passed to the route method
		@param $instance , class instance to be passed to route parent class on __construct

	*/
	public static function load( RouteModifier $route ,  $params = array() , $instance = null )  {
		
		
		//Route redirect check
		if ( sizeof($route->redirection) > 0  ){
			header('Location: ' . $route->redirection['to'] , true, $route->redirection['code']);
			exit;
		} 
		
		//Route request method Checkpoint
		if ($_SERVER['REQUEST_METHOD'] == static::GET AND 
			$route->request == static::POST ) {
			http_response_code(400);
			die();
		}
		
		//Get current route params
		if ( is_array($route->keys) AND 
			 sizeof($route->keys) > 0  )
		{
			foreach( $route->keys AS $i => $key )
			{
				$route->params[$key] = $route->values[$i];
			}
		}
		
		$route->loaded = true;
		
		//Merge params
		if ( !empty($params) )
		{
			$route->params = array_merge($route->params, $params);
		}
		
		$method = $route->method;
		
		//Callable by class
		if ( is_string($method) )
		{
			if (str_contains($method, '@')) {
				$method = explode("@", $method);
			}
		}
		
		//Closure method
		if ( $method instanceof \Closure )
		{
		
			return $method( $route->params );
			
		}else
		//other callable
		if ( $method != null AND sizeof($method) > 0 ){
			
			//Static 
			if ( isset($method[2]) AND $method[2] == true )
			{
				return $method[0]::{$method[1]}($route->params);
			}else
			//Class instance already loaded
			if ( !empty(static::$classes) AND static::$classes[$method[0]])
			{
				return call_user_func(  array( static::$classes[$method[0]], 
											 	$method[1] ), 
									   	$route->params  );
			}else
			{
			 	//pass instance to route main class 
			 	static::$classes[$method[0]] = new $method[0]($instance);
			
				return call_user_func(  array(static::$classes[$method[0]], 
										   	$method[1] ), 
									 	$route->params );
		 	}
		 }

		 unset($method);
		 
		return false;
	}
	/*
	
		Parse selected route 
		
	*/
	public static function fetch( string | null $uri = null ) : RouteModifier | null {
	
		static::$URI = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

		//Get route mapping key
		$route_uri 	  = ( $uri == null OR $uri == "" ) ? static::$URI : $uri;
		$mapping_key  = RouteModifier::mapKey( $route_uri );
		$route		  = null;
		$list		  = static::routes()['routes'];
	
		/*
			Route already loaded 
		*/
		if ( isset(static::$loadded[$route_uri]) )
		{	
			$route = static::$loadded[$route_uri];
				
		}else
		/*
		
			Find route by mapped regex 
			
		*/
		if (isset($list[$mapping_key]) AND
		 	$filter_route = array_filter(array_keys($list[$mapping_key]),
				 									fn ($route) =>  preg_match( "@^" . $list[$mapping_key][$route]->regex . "(/|)$@" , $route_uri ) )  )
		{
			
			$route_key	  = $filter_route[array_keys($filter_route)[0]];
			$route 		  = $list[$mapping_key][$route_key];
		
			/*
				Confirm route and get Params
			*/
		    if( @preg_match( "@^" . $route->regex . "(/|)$@" ,$route_uri , $m) )
			{
				$route->values  = array_splice($m, 1);//Remove full url matching
			}
		
			static::$loadded[$route_uri] = $route;
		
			unset($filter_route);
		}
		
		unset($route_uri,$mapping_key,$list);
		
		if ( $route != null AND $route->isEnabled )
		{
			return $route;
		}else{
			return null;
		}
		
	}
	/*
	
		Bring cached version of routes
		
	*/
	private static function routes() : array {
		
			
		//Prevent duplicate request	
		if ( !empty(static::$list) )
		{
			return static::$list;
		}	
			
		/*
		
			CACHE By Memcached
		
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_MEM AND 
			CORE::$env['cache']['memcached']['enabled']  )
		{
			
			if ( PTMEMCACHED::exists('PTRoutes')  )
			{
				static::$list = unserialize(PTMEMCACHED::get('PTRoutes'));
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
				static::$list = unserialize(PTREDIS::get('PTRoutes'));
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
				static::$list = unserialize(PTCACHE::get('PTRoutes'));
			}
			
		}
						
				
		return static::$list;
	}
	/*
	
		Cache and append new route
		
	*/
	private static function cacheRoute($route) : void {
		
		static::$list['keys'][]								      = $route->path;
		static::$list['routes'][$route->mappingKey][$route->path] = $route;
	
		/*
		
			CACHE By Memcached
		
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_MEM AND 
			CORE::$env['cache']['memcached']['enabled']  )
		{
	
			PTMEMCACHED::set('PTRoutes',serialize(static::$list));
				
		}else	
		/*
		
			CACHE By Redis
		
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_REDIS AND 
			CORE::$env['cache']['redis']['enabled'] )
		{
			PTREDIS::set('PTRoutes',serialize(static::$list),null);
		}else		
		/*
		
			CACHE By Files
		
		*/	
		if (CORE::$env['route']['cacheType'] == CACHE_TYPE_FILE AND 
			CORE::$env['cache']['file']['enabled']  )
		{
			PTCACHE::set('PTRoutes',serialize(static::$list),null);
		}
		
	}

}


