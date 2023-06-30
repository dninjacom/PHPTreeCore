<?php
namespace PHPTree\Core;


class PHPTreeRouteModifier {
	
	/*
	
		Route path
		@readonly
	
	*/	
	public readonly string $path;
	/*
		
		Mapping key
		@readonly
		
	*/
	public readonly string | null $mappingKey;
	/*
		
	    Route matching regex
		
	*/
	public  string | null $regex;
	/*
		
		Callable method
		
	*/
	public \Closure | string | array | null $method = null;
	/*
		
		Regex keys
		
	*/
	public array  $keys = array() ;
	/*
		
		Route valid request
		
	*/
	public string | null $request;
	/*
		
		Captured values from route regex
		
	*/
	public array $values = array();
	/*
		
		Key value params from route regex
		
	*/
	public array $params = array();
	/*
		
		Enable or disable route
		
	*/
	public bool  $isEnabled = true;
	/*
		
		Route redirect code and path
		@param to To path
		@param code redirection code by default 302
		
	*/
	public array $redirection = array();
	/*
	
		Route Group
		
	*/
	public string | null  $group = null;
	/*
	
		If route is return from cached version 
		
	*/	
	public bool $isCached = false;
	/*
	
		If route is loaded
		
	*/	
	public bool $loaded = false;
	
	
	public function __construct(string $path){
		
		$this->path  	  = $path;
		$this->regex 	  = $this->path;
		$this->mappingKey = static::mapKey($this->path);
		
	}
	/*
	
		Set Group
		
	*/
	public function setGroup( null | string $group ) : self  {
		
		$this->group = $group;
		
		return $this;
	}
	/*
	
		Set dynamic key
		
	*/
	public function Set( string $key , $val ) : self  {
		
		$this->$key = $val;
		
		return $this;
	}
	/*
	
		Redirect route
		
	*/	
	public function redirect( string $to , int $code = 302)  : self {
	
		$this->redirection['to']   = $to;
		$this->redirection['code'] = $code;
	
		return $this;	
	}
	/*
	
		Enable or disable route
		
	*/	
	public function enabled( $isEnabled ) : self  {
		$this->isEnabled = $isEnabled;
		return $this;
	}
	/*
	
		Set route callable method
		
	*/
	public function setMethod( \Closure | array | string $method): self  {
		$this->method = $method;
		return $this;
	}
	/*
	
		Setup route regex by those keys
		
	*/
	public function where($keys) : self {
		
		$this->keys  = $keys;
		$this->regex = $this->path;
		
		$key_name = array();
		
		if ( !@empty( $keys ) )
		{
		  foreach( $keys AS $key => $pat )
		  {
			  $key_name[] 	= $key;
			  $this->regex 	= str_replace( "{".$key."}" ,  "($pat)" , $this->regex );
		  }
		}
		/*
					 
			ReSort the keys ordering based on url path sorting
		  
		*/
		if ( sizeof( $key_name ) > 0 )
		{
			if ( preg_match_all('#\b(' . join("|",$key_name) . ')\b#', $this->path, $matches)) 
			{
				$this->keys = $matches[0];
			}  
		 } 
		return $this;
	}
	/*
	
		Make route mapping key
		
	*/
	public static function mapKey($path) : int {
		$mapKey = explode("/", $path);
		$mapKey = array_splice($mapKey, 1);
		return sizeof($mapKey);
	}
	
}