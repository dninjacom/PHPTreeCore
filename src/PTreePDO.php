<?php
namespace PHPTree\Core;

use PDO;
use PDOException;

/*
    PHPTreePDO is a lightweight php/SQL class 
    require PHP PDO to be installed and enabled 
    more info about PDO : https://www.php.net/manual/en/book.pdo.php
  
    How to use ? 
  
    simply just set name space "use system\lib\PTreePDO as DB;" and start using PHPTreePDO as DB
 
	Example : 
	 
	 //Set error logs file
	 DB::$debug_file = DIR . "/var/logs/sql.text";
	 
	 //establish a connection with database , now you can use $db to retrieve and insert data.
	 $db =	new DB( array('host' => 'mysql:host=localhost;dbname=phptree' , 'username' => 'root' , 'password' => 'root') );
	 
    Errors and logs :
  
    You can track and debug SQL errors by printing out the recorded errors 
    for example : echo "<pre>"; print_r(PTreePDO::$errors);
    or you can set a text file to trace all errors without printing them out .
    if you are using PHPTree project you can set the file like this : 
    DB::$debug_file = DIR . "/var/logs/sql.text";
  
 */
class PTreePDO {
	
	//current active connection PDO class
    public $pdo = null;
	  
	//Debug File
	public static $debug_file = null;
	
	
	public static $errors = array();
	/**
	 *  Establish database connection.
	 *  - Parameter host: Full connection host string with the database name for example (mysql:host=localhost;dbname=PHPTree)
	 *  - Parameter username: Database username.
	 *  - Parameter password: Database password
	 *  - Parameter options: PDO Connection options "array" , (optional)
	 */
	public function __construct( $database = array() ) {
		
		try{
				$this->pdo = new PDO( $database['host'], 
							 		  $database['username'], 
							 		  $database['password'],
							 		( !isset($database['options']) ? array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'utf8mb4\'',
								    										PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) : $database['options'] )
						 			);
		} catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return null;
		}
	}

	public function __destruct(){
		$this->writeLogs();
		$this->pdo = null;
	}
	
	/**
	 *  Log all recorded session errors to a predefined file .
	 */
	private function writeLogs(){
		
		//Log errors to file has been disabled 
		if ( PTreePDO::$debug_file == null || PTreePDO::$debug_file == "" || PTreePDO::$debug_file == false  )
		{
			return;
		}
		
		//No errors to log!
		if ( sizeof( PTreePDO::$errors ) == 0 )
		{
			return;
		}
		
		$string = "";
		
		foreach( PTreePDO::$errors AS $log )
		{
			$string .= $log . "\n";
			$string .= "----------------------------------------------------------------";
		}
		
		file_put_contents(PTreePDO::$debug_file, $string.PHP_EOL , FILE_APPEND | LOCK_EX);
		
		PTreePDO::$errors = array();
			
		unset($string);
	}
	
	/**
	 *  Prepare database query
	 *  - Parameter $query : MYSQL query.
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function prepare( $query , $params = array()  ) : \PDOStatement|false {
			 
		try{
			$prepare = $this->pdo->prepare($query);
		 
			 if ( isset($params) AND sizeof($params) > 0 )
			 {
				foreach($params AS $param )
				 {
					 if ( isset($param[2]) )
					 {
						$prepare->bindParam($param[0], $param[1] , $param[2] );	 
					 }else{
						$prepare->bindParam($param[0], $param[1] );	 
					 }
				 } 
			 }
			
			return $prepare;
		 
		}catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return false;
		}	 
	}
	
	/**
	 *  Query database.
	 *  - Parameter $query .
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function query( $query , $params = array()  ) : bool{
			 
		try{
			
			  if ( $prepare = $this->prepare($query , $params ) ){
				  
				  $prepare->execute();
				  
				  unset($prepare);
				  
				  return true;
			  }else{
				  return false;
			  }
		
		}catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return false;
		}	 
	}
	
	/**
	 *  Query Delete record or records from database.
	 *  - Parameter $query .
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function delete( $query , $params = array()  ) : bool{
		return $this-> query( $query , $params );
	}
	
	/**
	 *  Count number of rows or query.
	 *  - Parameter $query .
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function count( $query , $params = array() ) : int{
		
		try{
			
			if ( $prepare = $this->prepare($query , $params ) )
			{
				$prepare->execute();
				
				return $prepare->rowCount();
				
			}else{
				return 0;
			}
		
		}catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return 0;
		}	 
	}
	
	/**
	 *  Select multiple records from Database.
	 *  - Parameter $query .
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function select( $query , $params = array() ) : array |false{
		
		try{
			
			if ( $prepare = $this->prepare($query , $params ) )
			{
				$prepare->execute();
				
				return $prepare->fetchAll(\PDO::FETCH_ASSOC);
				
			}else{
				return false;
			}
		
		}catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return false;
		}	 
	}
	
	/**
	 *  Select one records from Database.
	 *  - Parameter $query .
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function selectOne( $query , $params = array() ) : array |false{
		
		try{
			
			if ( $prepare = $this->prepare($query , $params ) )
			{
				$prepare->execute();
				
				return $prepare->fetch(\PDO::FETCH_ASSOC);	
				
			}else{
				return false;
			}
		
		}catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return false;
		}	 
	}
	
	/**
	 *  Update database record.
	 *  - Parameter $query : MYSQL query for example " UPDATE  table SET `username` = :username ".
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	public function update( $query , $params = array() ): bool{
			 
		try{
			
			if ( $prepare = $this->prepare($query , $params ) )
			{
				$prepare->execute();
				
				unset($prepare);
				
				return true;
			}else{
				return false;
			}
		
		}catch (PDOException $e) {
			PTreePDO::$errors[] = $e->getMessage();
			return false;
		}	 
	}
	
	/**
	 *  Insert To Database.
	 *  - Parameter $query : MYSQL query for example " INSERT INTO table (`username`) VALUES (:username )".
	 *  - Parameter $params : refer to https://www.php.net/manual/en/pdo.constants.php 
		  example of $params : array( array(':username' , 'Jack' , \PDO::PARAM_STR ) )
	 */
	 public function insert( $query , $params = array()  ) : int|false{
		 
		 try{
				if ( $prepare = $this->prepare($query , $params ) )
				{
					
					$prepare->execute();
					
					unset($prepare);
					
					return $this->pdo->lastInsertId();
				}else{
					return false;
				}
			 
			}catch (PDOException $e) {
				PTreePDO::$errors[] = $e->getMessage();
				return false;
			}
	 }
}

?>