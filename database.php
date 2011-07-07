<?php
class Database {
    // Database is a singleton
    protected static $instance;
    
    protected $database_handle;
    
    protected function __construct($host, $username, $password, $database) {
        $this->database_handle = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    }
    
    public function __destruct() {
        $database_handle = NULL;
    }
    
    /** Get a handle to the database. */
    public static function handle() {
        if (! isset(self::$instance)) {
            self::$instance = new Database('127.0.0.1', 'basset', 'basset', 'basset'); // TODO: load these from settings file
        }

        return self::$instance->database_handle;
    }
}
?>