<?php
class Session {
    public $id;
    protected $assignment_id;
    protected $worker_id;
    public $game;
    public $current_step;
    public $repetition;
    public $data;
    /** 
     * The time when this session terminates, as a Unix timestamp.
     * NULL when never.
     */
    public $expires;
    /** The status of the current session (see codes below). */
    protected $status;

    
    // session status codes:
    const awaiting_user_input = 10;
    const group_request_pending = 20;
    const group_request_fulfilled = 28;
    const finished_step = 30;
    const finished = 90;
    const terminated = 91;

    protected static $status_codes = array(
        self::awaiting_user_input,
        self::group_request_pending,
        self::group_request_fulfilled,
        self::finished_step,
        self::finished,
        self::terminated);
    
    
    /** Map session_id-->session for factory pattern. */
    protected static $sessions;
    
    protected function __construct() {
        // save this session before finishing
        register_shutdown_function(array($this, 'save'));
    }
    
    /** Creates a new session. */
    public static function newSession($assignment_id, $worker_id, Game $game) {
        $new_session = new Session();
        
        $new_session->id = uniqid('', true);
        $new_session->assignment_id = $assignment_id;
        $new_session->worker_id = $worker_id;
        $new_session->game = $game;
        $new_session->current_step = $game->steps[0];
        $new_session->repetition = 0;
        $new_session->data = array();
        $new_session->status = self::awaiting_user_input;
        
        $new_session->setExpiration($new_session->current_step->time_limit);
        
        $dbh = Database::handle();
        
        $sth = $dbh->prepare(
                'INSERT INTO sessions ' .
                '(session_id, assignment_id, worker_id, game, data, status, expires) ' .
                'VALUES ' .
                '(:session, :assignment, :worker, :game, :data, :status, :expires)');
        $sth->bindValue(':session', $new_session->id);
        $sth->bindValue(':assignment', $new_session->assignment_id);
        $sth->bindValue(':worker', $new_session->worker_id);
        $sth->bindValue(':game', $new_session->game->getID());
        $sth->bindValue(':data', serialize($new_session->data));
        $sth->bindValue(':status', $new_session->status);
        $sth->bindValue(':expires', $new_session->expires);
        
        $sth->execute();
        
        self::$sessions[$new_session->id] = $new_session;
        return self::$sessions[$new_session->id];
    }
    
    /** 
     * Returns the session identified by the given session ID.
     * 
     * @param string $session_id the ID of the given session
     * @throws DoesNotExistException if there is no session with this session ID
     */
    public static function fromSessionID($session_id) {
        if(! isset(self::$sessions[$session_id])) { // instance for this session_id NOT already initialized
            // try getting it from the database
            $dbh = Database::handle();
            $sth = $dbh->prepare('SELECT * FROM sessions WHERE session_id = ?');
            $sth->execute(array($session_id));
            if($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $new_session = new Session();
                
                $new_session->id = $row['session_id'];
                $new_session->assignment_id = $row['assignment_id'];
                $new_session->worker_id = $row['worker_id'];
                $new_session->game = Game::fromGameID($row['game']);
                $new_session->current_step = $new_session->game->steps[intval($row['step'])];
                $new_session->repetition = intval($row['repetition']);
                $new_session->data = unserialize($row['data']);
                $new_session->status = intval($row['status']);
                
                $new_session->expires = Util::sql2unixtime($row['expires']);
                if($new_session->expires == 0) $new_session->expires = NULL;
                
                self::$sessions[$session_id] = $new_session;
            } else {
                throw new DoesNotExistException('unknown session id');
            }
        }

        return self::$sessions[$session_id];
    }
    
    /**
     * Returns the session identified by the given assignment id.
     * 
     * @param string $assignment_id the AMT assignment ID, a unique ID given by Amazon to each assignment
     * @param Game $game the game associated with this assignment (should be known because HitId is also passed)
     * @throws DoesNotExistException if there is no session with this assignment ID and game
     */
    public static function fromAssignmentID($assignment_id, Game $game) {
        $game_id = $game->getID();
        $dbh = Database::handle();
        $sth = $dbh->prepare('SELECT session_id FROM sessions WHERE assignment_id = ? AND game = ?');
        $sth->execute(array($assignment_id, $game_id));
        if($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $session_id = $row['session_id'];
            return self::fromSessionID($session_id);
        } else {
            throw new DoesNotExistException('unknown assignment id');
        }
    }
    
    /**
     * Writes the current state of the session to the database.
     */
    public function save() {
        $dbh = Database::handle();
        
        $sth = $dbh->prepare('UPDATE sessions SET step = :step, repetition = :repetition, data = :data, expires = :expires WHERE session_id = :session');
        $sth->bindValue(':step', $this->current_step->order());
        $sth->bindValue(':repetition', $this->repetition);
        $sth->bindValue(':data', serialize($this->data));
        $sth->bindParam(':expires', $expiration_datetime);
        $sth->bindValue(':session', $this->id);
        
        $expiration_datetime = Util::unix2sqltime($this->expires); // convert unix time to SQL datetime
            // NULL is treated as zero (=> datetime = 1970:...) and will be decoded as such
        
        $sth->execute();
    }
    
    /**
     * Returns the current status for this session.
     *
     * @see the session status codes
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     * Sets this session to the given status.
     *
     * @param int $status the status code for this session
     */
    public function setStatus($status) {
        if(! in_array($status, self::$status_codes)) {
            throw new InvalidArgumentException('invalid status code');
        } else {
            // set status
            $this->status = $status;

            // save status in db
            $dbh = Database::handle();
            $sth = $dbh->prepare('UPDATE sessions SET status = ? WHERE session_id = ?');
            $sth->execute(array($this->status, $this->id));
        }
    }
    
    /** Flags this session as finished in the database. */
    public function endSession() {
        $this->setStatus(self::finished);
    }
    
    /** Flags this session as terminated in the database. */
    public function terminateSession() {
        $this->setStatus(self::terminated);
    }

    /** Returns true if this session has ended (finished or terminated). */
    public function ended() {
        return ($this->status == self::finished) || ($this->status == self::terminated);
    }
    
    /** 
     * Returns a reference to the array data holding the data for the given round.
     * 
     * This array may be empty or already populated; in any case, it will exist.
     */
    public function &getRoundData(Round $round) {
        $data_label = self::roundDataLabel($round);
        if(! isset($this->data[$data_label])) {
            $this->data[$data_label] = array();
        }
        
        return $this->data[$data_label];
    }
    
    /** Returns a label for the current round's data. */
    protected static function roundDataLabel(Round $round) {
        return $round->label() . '_data';
    }
        
    /** 
     * Returns a reference to the array data holding the data for the current step.
     * 
     * @see Session::getRoundData()
     */
    public function &currentRoundData() {
        return $this->getRoundData($this->currentRound());
    }
    
    /**
     * Returns a unique (to this game) label identifying the current iteration of the current step.
     * 
     * @see Step->stepLabel
     * @return string
     */
    public function currentStepLabel() {
        return $this->currentRound()->label();
    }
    
    /**
     * Advances the session to the next repetition of the current step;
     * if this step is not repeated, or if this is the last repetition,
     * advances to the next step.
     * 
     * @throws DoesNotExistException if there is no next step
     */
    public function advance() {
        // Does this step need to be repeated?
        if($this->repetition < $this->current_step->repeat) { // yes. repeat.
            $this->repetition++;
        } else { // no. move on to the next one.
            $this->current_step = $this->current_step->nextStep();
            $this->repetition = $this->current_step->isRepeated() ? 0 : NULL;
        }
    }
    
    /**
     * Starts the current step.
     * Effectively, it:
     * 1) sets the current status to awaiting_user_input.
     * 2) sets the expiration of this session to the time limit of the current step.
     */
    public function startStep() {
        $this->setStatus(Session::awaiting_user_input);
        $this->setExpiration($this->current_step->time_limit);
    }
    
    /**
     * Sets the expiration of this session to now + $seconds seconds.
     * If $seconds is 0, then expiration is set to NULL (no expiration).
     * 
     * @param int $seconds 
     */
    public function setExpiration($seconds = 0) {
        if($seconds == 0) {
            $this->expires = NULL;
        } else {
            $this->expires = time() + intval($seconds);
        }
    }
    
    /**
     * Returns TRUE if this session has expired.
     * 
     * If there is no expiration (it is NULL), then the session is not expired,
     * and so returns FALSE.
     * 
     * @return boolean TRUE if this session has expired
     */
    public function expired() {
        return (! is_null($this->expires)) && (time() > $this->expires);
    }
    
    /**
     * Returns the group this session was in for the given step.
     * @param Step $step
     * @param int $repetition
     * @throws DoesNotExistException if the session doesn't have a groups saved for the given step
     * @return Group 
     */
    public function getGroup(Round $round) {
        $key = self::groupKey($round);
        if(array_key_exists($key, $this->data)) {
            $group_id = $this->data[$key];
            return Group::getGroupByID($group_id); // TODO: maybe cache the Group somewhere, so we don't have to query the database and construct it every time?
        } else {
            throw new DoesNotExistException('no group for given step');
        }
    }
    
    /**
     * Returns the group at the current step.
     * Like {@link getGroup() the getGroup method}, 
     * this throws an exception if there is no group currently set.
     * 
     * @see getGroup()
     * @return Group
     */
    public function getCurrentGroup() {
        return $this->getGroup($this->currentRound());
    }
    
    /**
     * Sets the group at the current step.
     * 
     * @param Group $group the group to be set
     */
    public function setCurrentGroup(Group $group) {
        if($this->getStatus() != self::group_request_pending) {
            throw new Exception('cannot set group when session is not waiting for a group');
        }
        
        $this->data[self::groupKey($this->currentRound())] = $group->getID();
    }
    
    /**
     * Returns a key identifying where in $this->data table to find
     * group data for the given step and repetition.
     * 
     * @see Step::stepLabel()
     * @param Step $step
     * @param int $repetition
     * @return string
     */
    protected static function groupKey(Round $round) {
        return $round->label() . '_group';
    }
    
    /**
     * Returns the current round for this session.
     * 
     * @see Round
     * @return Round current round
     */
    public function currentRound() {
        return new Round($this->current_step, $this->repetition);
    }
    
}