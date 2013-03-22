<?php
	
	class EpiCollectDatabaseConnection
	{
		private $con;
		private $resSet;
		private $numRows;
		private $username;// = $DBUSER;
		private $password;// = $DBPASS;
		private $server;// = $DBSERVER;
		private $schema;// = $DBNAME;
		private $port;// = 3306;
		private $lastId;
		private $engine;
                
		public $connected;
		public $errorCode;
		public $lastQuery;
		
                /**
                 * 
                 * @param String $engine The Database Software you want to use
                 * @param String $server The address of the server
                 * @param String $database
                 * @param String $un
                 * @param String $pwd
                 * @param String $port
                 */
		public function __construct($engine, $server, $database, $un, $pwd, $port=false)
		{
			
			ini_set('mysql.connect_timeout', 300);
			ini_set('default_socket_timeout', 300);
			
                        $this->engine = $engine;
			$this->username = $un;
			$this->password = $pwd;
			$this->server = $server;
			$this->schema = $database;
			$this->port = $port;
			
			if($this->server && $this->port && $this->schema && $this->username)
			{   
                            $cstr = sprintf('%s:dbname=%s;host=%s;', $this->engine, $this->schema , $this->server);
                            if($this->port !== false)
                            {
                               $cstr = sprintf('%sport=%s', $cstr, $this->port);  
                            }
                            
                            $this->con = new PDO($cstr, $this->username, $this->password);
                            $this->connected = true;
			}
			else
			{
				$this->connected = false;	
			}
		}
		
		public function __destruct()
		{
			//$this->resSet->closePointer();
                        //if($this->connected) $this->con->close();
		}
		
		public function boolVal($val) {return $val && $val !== "false" ? "1" : "0";}
		public function boolVal2($val) {return $val === false || $val === "false" || $val == 0 ? "0" : "1";}
		public function stringVal($val) {return $val == "" ? "NULL" : "'". trim($this->con->quote($val), '\'') . "'";}
		public function numVal($val) {return !$val && $val !== 0 && $val !== 0.0 ? "NULL" : "$val";}
		public function unescape($val) {return $this->con->quote($val); }
		
		public function beginTransaction()
		{
                    $this->con->beginTransaction();
                    return true;
		}
		
		public function commitTransaction()
		{
			$this->con->commit();
                        return true;
		}
		
		public function rollbackTransaction()
		{
			$this->rollbackTransaction();
			return true;
		}
		
		public function free_result()
		{
			unset($this->resSet);
		}
		
		public function affectedRows()
		{
			return $this->numRows;
		}
		
		public function escapeArg($arg)
		{
			return $this->con->quote($arg);
		}
		
		public function do_query($qry)
		{
			
			if($this->connected)
			{
				//if($this->resSet && !is_bool($this->resSet)) mysqli_free_result($this->resSet);
				$this->resSet = $this->con->query($qry);
				
				
				if($this->resSet)
				{
                                        $this->numRows = $this->resSet->rowCount();
					$this->lastQuery = $qry;
					$this->lastId = $this->con->lastInsertId();
					return true;
				}
				else
				{
					//echo $qry .  "\r\n" . mysqli_errno($this->con) . " : " . mysqli_error($this->con);
					return $qry .  "\r\n" . $this->con->errorCode(). " : " . implode('\r\n', $this->con->errorInfo());
				}
			}
			else
			{
				throw new Exception("Database not yet connected");
			}
			
		}
		
		public function do_multi_query($qry)
		{
			if($this->connected)
			{
				//if($this->resSet && !is_bool($this->resSet)) mysqli_free_result($this->resSet);
				$res = $this->con->query($qry);
				
				if($res) 
				{
					//$this->resSet = $this->con->use_result();
					
					return true;
				}
				else
				{
					//echo $qry .  "\r\n" . mysqli_errno($this->con) . " : " . mysqli_error($this->con);
					return $qry .  "\r\n" . $this->con->errorCode . " : " .$this->con->errorInfo();
				}
			}
			else
			{
				throw new Exception("Database not yet connected");
			}
		}
		
		public function getLastResultSet()
		{
			//$this->resSet = $this->con->store_result() ;
			while($this->resSet->nextRowSet()) {
				//$this->resSet = $this->con->store_result() ;
			}
			return true;
		}
		
		public function exec_sp($spName, $args = Array())
		{
			if($this->connected)
			{
				//if($this->resSet && !is_bool($this->resSet)) mysqli_free_result($this->resSet);
				for($i = 0; $i < count($args); $i++)
				{
					//$args[$i] = mysqli_escape_string($this->con, $args[$i]);
					
					if((is_string($args[$i]))){ $args[$i] = $this->escapeArg($args[$i]); }
					
					else if(!$args[$i]){
						if(is_int($args[$i]) || is_double($args[$i]) || is_bool($args[$i])) $args[$i] = "0";
						else $args[$i] = "NULL";
					}
				}
				
				$qry = "CALL $spName (" . implode(", ", $args) . ");";
				
				$this->resSet = $this->con->query($qry);
				if($this->resSet)
				{
					return true;
				}
				else
				{
					return $qry . "\r\n" .$this->con->errorCode() . " : " . implode('\r\n', $this->con->errorInfo());
				}
			}
			else
			{
				throw new Exception("Database not yet connected");
			}
		}
		
		public function get_row_array()
		{
			return $this->resSet->fetch();
		}
		
		public function get_row_object()
		{
			return $this->resSet->fetch(PDO::FETCH_OBJ);
		}
		
		public function last_id()
		{
			return $this->lastId;
		}
		
	}

?>