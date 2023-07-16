<?php
namespace PHPTree\Core\Entities;

/*

	File caching inside cache directory

*/
class PHPTreeJWT  { 
	
	/*
	
		Token Header
		
	*/
	public  string | array $header;
	/*
	
		Token Payload
		
	*/
	public  string  | array $payload;
	/*
	
		Token Signature
		
	*/
	public string $signature;
	/*
	
		Token Signature validation key
		
	*/
	public bool $isValid = false;
	
	public function __construct( string $token ){
			
		$token 		 	 = explode('.', $token);
		$this->header 	 = base64_decode($token[0]);
		$this->payload   = base64_decode($token[1]);
		$this->signature = $token[2];
		
	}
	
	/*
	
		Return based on validation and TTL 
		
	*/
	public function isValid() : bool {
		
		if ( isset( $this->payload['ttl'] ) )
		{
			return ( $this->isValid AND $this->payload['ttl'] > time() );
		}
		
		return $this->isValid;
	}
	
}

