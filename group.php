<?php
class Group {
    /** An array of sessions that this group consists of. */
    public $members;
    
    /** The ID of this group in the database. */
    protected $id;
    
    /** The data associated with this group. */
    public $data;
    
    /**
     * Group constructor
     * 
     * @param array $groupMembers an array of sessions that will be members of this group
     */
    protected function __construct($id, array $groupMembers, array $data) {
        $this->id = $id;
        $this->members = $groupMembers;
        $this->data = $data;
    }
    
    public function getID() {
        return $this->id;
    }
    
    /**
     * Creates a new group with the given sessions at the current step
     * and saves it to the database.
     * If the sessions differ with respect to what game and step they are in,
     * we use the first member's game and step.
     * 
     * @param array $groupMembers an array of sessions that will be members of this group
     * @return Group the newly created group
     */
    protected static function newGroupAtCurrentStep(array $groupMembers) {
        if(count($groupMembers) < 1) {
            throw new InvalidArgumentException("invalid group size (" . count($groupMembers) . ")");
        }
        
        $exemplar = $groupMembers[0];
                
        // new database record for this group
        $dbh = Database::handle();
        
        $sth = $dbh->prepare('INSERT INTO groups (sessions, game, step, data) VALUES (:sessions, :game, :step, :data)');
        $sth->bindParam(':sessions', $sessions);
        $sth->bindValue(':game', $exemplar->game->getID());
        $sth->bindValue(':step', $exemplar->currentStepLabel());
        $sth->bindValue(':data', serialize(array()));
        
        $sessions = serialize(array_map(function($session) {
            return $session->id;
        }, $groupMembers));
        
        $sth->execute();
        
        // create new group object
        $group = new Group($dbh->lastInsertId(), $groupMembers, array());
        
        // let the group members know they're in a group
        self::setSessionsGroup($groupMembers, $group);
        
        return $group;
    }
    
    /**
     * Sets the current group of the given sessions to the given group,
     * also setting their status to Session::group_request_fulfilled.
     * 
     * (Each session is explicitly saved.) TODO: this may not be necessary
     * 
     * @param array $sessions
     * @param Group $group 
     */
    protected static function setSessionsGroup(array $sessions, Group $group) {
        foreach($sessions as $member) {
            $member->setCurrentGroup($group);
            $member->setStatus(Session::group_request_fulfilled);
            $member->save(); //TODO: this may not be necessary
        }
    }
    
    /** @deprecated */
    public static function getGroup(Game $game, $step, Session $session) {
        $dbh = Database::handle();
        
        // find all groups with this game and step
        $sth = $dbh->prepare('SELECT * FROM groups WHERE game = :game AND step = :step');
        $sth->bindValue(':game', $game->getID());
        $sth->bindValue(':step', $step);
        $sth->execute();
        
        // find one that contains this session
        while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $session_ids = unserialize($row['sessions']);
            if(in_array($session->id, $session_ids)) {
                // use it to construct the group
                $sessions = array_map(function($session_id) {
                    return Session::fromSessionID($session_id);
                }, $session_ids);
                return new Group($row['id'], $sessions, unserialize($row['data']));
            }
        }
        
        // couldn't find anything!
        throw new Exception('could not find group with matching game, step, and session');
    }
    
    /** @deprecated */
    public static function getGroupAtCurrentStep(Session $session) {
        return self::getGroup($session->game, $session->currentStepLabel(), $session);
    }
    
    /**
     * Returns the Group from the database identified by the given ID.
     * 
     * @param mixed $id the id of the group in the database
     * @throws InvalidArgumentException if no group was found matching this ID
     * @return Group group matching given ID
     */
    public static function getGroupByID($id) {
        $dbh = Database::handle();
        $sth = $dbh->prepare('SELECT * FROM groups where id = ?');
        $sth->execute(array($id));
        
        if($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $session_ids = unserialize($row['sessions']);
            
            // convert session ids to session objects
            $sessions = array_map(function($session_id) {
                return Session::fromSessionID($session_id);
            }, $session_ids);

            return new Group($row['id'], $sessions, unserialize($row['data']));
        } else {
            throw new InvalidArgumentException('no groups matching given id');
        }
    }
    
    /**
     * Checks the group request queue for partners for the given session's current step.
     * If a group can be formed, sets it as the current group for all of its members,
     * then returns that group.
     * Otherwise, adds this session to the group request queue,
     * then returns FALSE.
     *
     * @param Session $session
     * @return Group if a group can be formed, FALSE otherwise
     */
    public static function getNewGroup(Session $session) {
        // set given group's satus as "request pending". this is for internal state checks.
        $session->setStatus(Session::group_request_pending);
        
        // get all the requests for this game and step
        $queue = new GroupRequestQueue($session->game, $session->currentRound());
        
        $request_expires = time() + $session->game->group_wait_limit;
        
        // try getting a match for this session
        $sessions = $queue->newRequest(new GroupRequest($session, $request_expires));
        
        if(is_bool($sessions) && $sessions == FALSE) { // no match
            return FALSE;
        } else { // found match
            return self::newGroupAtCurrentStep($sessions);
        }
    }
    
    /**
     * Attempts to form a group with all the members of the group from the previous round.
     * If a group can be formed, sets it as the current group for all of its members,
     * then returns that group.
     * Otherwise, sets the current status of this session to Session::group_request_pending,
     * then returns FALSE.
     * 
     * A group is considered "ready to be formed" when the status of each of its members
     * is Session::group_request_pending.
     * 
     * @param Session $session
     * @return Group if the group is formed, FALSE otherwise 
     */
    public static function getOldGroup(Session $session) {
        // set given group's satus as "request pending". this is for internal state checks.
        $session->setStatus(Session::group_request_pending);
        
        // get the group for the previous round
        $previous_group = $session->getGroup($session->currentRound()->previousRound());
        
        // are all members ready?
        $ready = array_reduce($previous_group->members, function($v, $w) {
            return $v && ($w->getStatus() == Session::group_request_pending);
        }, TRUE);
        
        if($ready) {
            // let the group members know they're in a group
            self::setSessionsGroup($previous_group->members, $previous_group);

            return $previous_group;
        } else {
            return FALSE;
        }
    }
    
    /** @deprecated */
    public function allFinished() {//var_dump($this);
        if(isset($this->data['all_finished']) && $this->data['all_finished'] == TRUE) {
            return TRUE;
        } else {
            $all_finished = array_reduce($this->members, function($v, $w) {
                return $v && ($w->getStatus() == Session::finished_step);
            }, TRUE);
            
            if($all_finished) { // remember this, since sessions will move on and no longer have this status
                $this->data['all_finished'] = TRUE;
                $this->saveData();
            }
            
            return $all_finished;
        }
    }
    
    /**
     * Checks if all members of this group have finished the given round.
     * A session has finished a given round if:
     * 1) its current round follows the given round
     * 2) OR if it is in the given round and its status is Session::finished_step
     * 
     * @param Round $round
     * @return boolean TRUE if all members of this group have finished the given round
     */
    public function finishedRound(Round $round) {
        return array_reduce($this->members, function($v, $w) use ($round) {
            $member_round = $w->currentRound();
            if($member_round->compareTo($round) == 0) {
                $finished = ($w->getStatus() == Session::finished_step);
            } elseif($member_round->compareTo($round) > 0) {
                $finished = TRUE;
            } elseif($member_round->compareTo($round) < 0) {
                $finished = FALSE;
            }
            
            return $v && $finished;
        }, TRUE);
    }
    
    public function newRound() {
        $this->data['all_finished'] = FALSE;
        $this->saveData();
    }
    
    /** Updates this group's data in the database. */
    public function saveData() {
        $dbh = Database::handle();
        $sth = $dbh->prepare('UPDATE groups SET data = :data WHERE id = :id');
        $sth->bindValue(':data', serialize(($this->data)));
        $sth->bindValue(':id', $this->id);
        $sth->execute();
    }
    
    /**
     * Returns all those sessions in this group that are not the given session.
     * @param Session $session 
     * @return array<Session>
     */
    public function partners(Session $session) {
        $my_session_id = $session->id;
        $partners = array_filter($this->members, function($member) use ($my_session_id) {
            return $member->id != $my_session_id;
        });
        return array_values($partners); // re-index the array
    }
    
    /**
     * Have no members of this group expired?
     * 
     * A session is considered expired if:
     * 1) its status is 'terminated', or
     * 2) its status is 'awaiting_user_input' but its time has expired
     * 
     * @return boolean TRUE if all members of this group have *not* expired, FALSE otherwise
     */
    public function allAlive() {
        return array_reduce($this->members, function($v, $w) {
            $expired = 
               ( $w->getStatus() == Session::terminated) ||
               (($w->getStatus() == Session::awaiting_user_input) && $w->expired());
            return $v && (! $expired);
        }, TRUE);
    }
}