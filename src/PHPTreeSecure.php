<?php
namespace PHPTree\Core;

use PHPTree\Core\Entities\PHPTreeJWT as JWT;
use PHPTree\Core\PHPTreeCore AS CORE;

/*

	File caching inside cache directory

*/
class PHPTreeSecure  { 
	

	/*
	
		Safe $_REQUEST array 
	
	*/
	public static function safeRequest(  $encoding = "UTF-8"  ) : array {
		return static::safeArray( $_REQUEST , $encoding );
	}	
	/*
		
		Safe $_GET array 
		
	*/
	public static function safeGet(  $encoding = "UTF-8"  ) : array {
		return static::safeArray( $_GET , $encoding );
	}
	/*
		
		Safe $_POST array 
		
	*/
	public static function safePost(  $encoding = "UTF-8"  ) : array {
		return static::safeArray( $_POST , $encoding );
	}
	/*
	
		make array values clean from XSS
		
	*/
	public static function safeArray( array $array , $encoding = "UTF-8"  ) : array {
		
		$safe_array = array();
		
		if ( sizeof($array) > 0 ) {
			
			foreach( $array AS $k => $v )
			{
				if ( is_array($v) )
				{
					$safe_array[$k] = static::safeArray($v, $encoding);
				}else{
					$safe_array[$k]  = static::safeInput(  $v, $encoding );
				}
			
			}
			
		}
		
		return $safe_array;
	}
	/*

		Safe input 
		escape XSS
		
	*/	
	public static function safeInput( string $string , $encoding = "UTF-8"  ) : string {
		return htmlspecialchars($string, ENT_QUOTES ,$encoding);
	}
	/*
	
		Generate JWT token
		
		@param headers array
		@param payload array 
		@param secret secret phrase key to sign the token 
		
	*/
	public static function generateToken(array $payload, ?string $secret = null , array $headers = array('alg'=>'HS256','typ'=>'JWT')) : string {
		
		$payload['ttl']			 	 = ( isset($payload['ttl']) ) ? $payload['ttl'] : time() + 60;
		$secret						 = ( $secret != null ) ? $secret : CORE::$env['security']['JWT_SECRET'];
		$token 						 = array();
		$token["headers_encoded"]	 = self::base64url_encode( json_encode($headers) );
		$token["payload_encoded"]	 = self::base64url_encode( json_encode($payload) );
		$token["signature"] 		 = hash_hmac('SHA256', "$token[headers_encoded].$token[payload_encoded]", $secret, true);
		$token["signature_encoded"]  = self::base64url_encode( $token["signature"] );
		
		return  "$token[headers_encoded].$token[payload_encoded].$token[signature_encoded]";
	}
	/*
	
		Validate JWT token
		
		@param token string
		@param secret secret phrase key to sign the token 
		
		@return JWT object
		
	*/
	public static function validateToken( string $token , ?string $secret = null) : JWT {
		
		$secret				 = ( $secret != null ) ? $secret : CORE::$env['security']['JWT_SECRET'];
			
		$token 				 = new JWT( $token );

		$token->header 		 = self::base64url_encode( $token->header );
		$token->payload 	 = self::base64url_encode( $token->payload );
		$token->isValid 	 = ( self::base64url_encode( hash_hmac('SHA256', $token->header . "." . $token->payload, $secret, true) ) === $token->signature );
		
		$token->payload		 = base64_decode($token->payload);
		$token->header		 = base64_decode($token->header);
	
	    $token->payload		 = json_decode($token->payload,true);
	    $token->header		 = json_decode($token->header,true);
		
		return $token;
	}
	
    private static function base64url_encode( string $string ) : string {
		return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }
	
}


