<?php
class GroupRequestQueue {
    /** The requests that have been enqueued. */
    protected $requests;
    
    
    /**
     * Constructs a GroupRequestQueue with entries matching the specified game and step.
     * 
     * @param Game $game the desired game
     * @param Round $round the desired round
     */
    public function __construct(Game $game, Round $round) {
        $this->requests = array();
        
        $dbh = Database::handle();
        
        $sth = $dbh->prepare('SELECT * FROM group_requests WHERE game = :game AND step = :step');
        $sth->bindValue(':game', $game->getID());
        $sth->bindValue(':step', strval($round));
        $sth->execute();
        
        while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $expires = Util::sql2unixtime($row['expires']);
            if($expires == 0) $expires = NULL;
            $this->requests[] = new GroupRequest(Session::fromSessionID($row['session']), $expires);
        }
    }

    /**
     * Checks the queue for matches to this request.
     * If a group can be formed, returns an array with the sessions that will form this group.
     * Otherwise, returns FALSE.
     *
     * @param GroupRequest $request
     * @return array<Session> if a group can be formed, FALSE otherwise
     */
    public function newRequest(GroupRequest $request) {
        // filter all requests that match this one
        $matches = array_filter($this->requests, function($potential_match) use ($request) {
            return (! $potential_match->expired()) && $request->match($potential_match);
        });
        
        if(count($matches) + 1 >= $request->size()) { // we have enough people to form a group!
            // get as many of the matches as we need for the group
            $selected_requests = array_slice($matches, 0, $request->size() - 1);

            // remove the fulfilled grouprequests from the queue
            $this->removeRequests($selected_requests);
            
            // get the sessions that will make up this group
            $sessions = array_map(function($selected_request) {
                return $selected_request->session;
            }, $selected_requests);
            $sessions[] = $request->session; // don't forget the new request
            
            return $sessions;
        } else { // not enough requests matching this one
            // add this request to the queue
            $this->addRequest($request);

            return FALSE;
        }
    }
    
    /**
     * Removes the given requests from the queue and from the database. 
     * 
     * @param array $requests an array of GroupRequests that will be removed from the queue
     */
    protected function removeRequests(array $requests) {
        // remove from queue
        $this->requests = array_diff($this->requests, $requests);
        
        self::deleteRequests($requests);
    }
    
    /**
     * Deletes the given requests from the database.
     * @param array $requests an array of GroupRequests
     */
    public static function deleteRequests(array $requests) {
        // remove from database
        $dbh = Database::handle();
        $sth = $dbh->prepare('DELETE FROM group_requests WHERE session = :session');
        $sth->bindParam(':session', $session_id);
        
        foreach($requests as $removed_request) {
            $session_id = $removed_request->session->id;
            $sth->execute();
        }
    }
    
    /** Adds the given request to the queue and to the database. */
    protected function addRequest(GroupRequest $request) {
        // add to queue
        $this->requests[] = $request;
        
        // add to database
        $dbh = Database::handle();
        
        $sth = $dbh->prepare('INSERT INTO group_requests (session, game, step, expires) VALUES (:session, :game, :step, :expires)');
        $sth->bindValue(':session', $request->session->id);
        $sth->bindValue(':game', $request->game()->getID());
        $sth->bindValue(':step', strval($request->session->currentRound()));
        $sth->bindValue(':expires', Util::unix2sqltime($request->expires));
        $sth->execute();
    }
    
    /**
     * Retrieves pending group request for given session.
     * If no group request is pending, returns FALSE.
     * 
     * If multiple group requests are pending (though this shouldn't happen),
     * returns the first request returned by the database
     * (in all likelihood, the oldest one).
     * 
     * @param Session $session
     * @return GroupRequest pending group request or FALSE if none is pending
     */
    public static function retrieveRequest(Session $session) {
        $dbh = Database::handle();
        $sth = $dbh->prepare('SELECT * FROM group_requests WHERE session = ?');
        $sth->execute(array($session->id));
        
        if($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $expires = Util::sql2unixtime($row['expires']);
            if($expires == 0) $expires = NULL;
            return new GroupRequest($session, $expires);
        } else {
            return FALSE;
        }
    }
}