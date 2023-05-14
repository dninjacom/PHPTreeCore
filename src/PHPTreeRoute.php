<?php

namespace PHPTree\Core;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class PHPTreeRoute 
{	
	const GET  = 'get';
	const POST = 'post';
	
	public static $params;
	
	
	public function __construct(
		public string $path,
		public ?array $keys = null,
		public string $request = PHPTreeRoute::GET
	){
		
	}
	
	/*
		Fetch all routes from all provided controllers 
		No cache
	*/
	public static function routesByControllers( $controllers ) : array
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
						  		
							 ReSort the  keys ordering based on url path sorting
						  
						  */
						  if ( sizeof($keys) > 0 )
						  {
							if ( preg_match_all('#\b(' . join("|",$keys) . ')\b#', $attr->path, $matches)) 
							  {
								  $keys = $matches[0];
							  }  
						  }
						 
						  
						//Register all Route information  
						$mapping_key = explode("/", $attr->path);
						$mapping_key = array_splice($mapping_key, 1);
						$mapping_key = sizeof($mapping_key);
						
						$routes[$mapping_key][$route_regex] = array('url_regex'   => $route_regex,
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
	
}
