<?php
class Game {
    /** The short code that this game is referred by. Also the name of the game file (minus the extension). DEPRECATED??? */
    public $code;
    /** A descriptive title for this game. (Didsplayed to users, but not players.) */
    public $name;
    /** The directory where the game files are located. */
    public $directory;
    /**  An ordinal array of steps making up this game (as Step objects). */
    public $steps;
    /** Behavior when a user times out. */
    public $timeout_behavior;
    
    /**
     * The number of seconds after which group requests expire.
     * 
     * TODO: have setting in steps that can override this
     * 
     * @var int limit (seconds)
     */
    public $group_wait_limit;
    
    // possible values for timeout behavior:
    const terminate = 'terminate';
    const skip = 'skip';
    protected static $timeout_behaviors = array(self::terminate, self::skip);

    /** The file with user-defined functions. Will be included. */
    const function_file = 'functions.php';
    
    /** Map game_code-->game for factory pattern. */
    protected static $games;
    
    protected function __construct() {}
    
    public static function fromHIT($hit_id) {
        $dbh = Database::handle();
        
        $stmt = $dbh->prepare('SELECT game FROM games WHERE hit_id = ?');
        if($stmt->execute(array($hit_id))) {
            if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return self::fromGameCode($row['game']);
            } else {
                throw new DoesNotExistException('invalid HIT ID');
            }
        }
    }
    
    public static function fromGameID($game_id) {
        $dbh = Database::handle();
        
        $stmt = $dbh->prepare('SELECT game FROM games WHERE id = ?');
        if($stmt->execute(array($game_id))) {
            if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return self::fromGameCode($row['game']);
            } else {
                throw new DoesNotExistException('invalid game ID');
            }
        }
    }
    
    public static function fromGameCode($game_code) {
        if(! isset(self::$games[$game_code])) { // instance for this code NOT already initialized
            $new_game = new Game();
            
            $new_game->code = $game_code;
            
            $game_file_name = GAMES_DIRECTORY . $new_game->code . GAMES_FILE_EXTENSION;
            if(! file_exists($game_file_name)) { throw new Exception('game file missing: ' . $game_file_name); }
            
            $game_data = json_decode(file_get_contents($game_file_name));
            if(is_null($game_data)) { throw new ConfigurationSyntaxException("game configuration in $game_file_name is not properly formatted JSON"); }
            
            $new_game->name = $game_data->{'name'};
            $new_game->directory = GAMES_DIRECTORY . $game_data->{'directory'} . DIRECTORY_SEPARATOR;
            
            // assign timeout behavior
            if(isset($game_data->{'on_timeout'}) && in_array($game_data->{'on_timeout'}, self::$timeout_behaviors)) {
                $new_game->timeout_behavior = $game_data->{'on_timeout'};
            } else {
                $new_game->timeout_behavior = self::terminate;
            }
            
            // group wait limit
            $new_game->group_wait_limit = 
                    isset($game_data->{'group_wait_limit'}) && is_int($game_data->{'group_wait_limit'}) ?
                    intval($game_data->{'group_wait_limit'}) : NULL;
            
            // parse step data
            $new_game->steps = array();
            
            foreach($game_data->{'steps'} as $step_code) {
                $step_file_name = $new_game->directory . $step_code . STEP_FILE_EXTENSION;

                // get step config file
                $step_file = file_get_contents($step_file_name);
                if(! $step_file) {
                    throw new Exception("could not find step file ($step_file_name)");
                }

                // parse the json in the config file as an object
                $step_data = json_decode($step_file);
                if(is_null($step_data)) { throw new ConfigurationSyntaxException("step configuration in $step_file_name is not properly formatted JSON"); }

                $current_step = new Step($step_data, $new_game);
                
                $new_game->steps[] = $current_step;
            }
            
            self::$games[$game_code] = $new_game;
        }
        
        return self::$games[$game_code];
    }

    /** Returns the ID of this game in the database. */
    public function getID() {
        $dbh = Database::handle();
        $sth = $dbh->prepare('SELECT id FROM games WHERE game = ?');
        $sth->execute(array($this->code));
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        return $row['id'];
    }
    
    /** Returns the number of steps in this game. */
    public function numberOfSteps() {
        return count($this->steps);
    }
}
?>