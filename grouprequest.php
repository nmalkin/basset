<?php
class GroupRequest {
    /** The session requesting a group. */
    public $session;

    public function __construct(Session $session) {
        $this->session = $session;
    }

    /** Returns the game this request is for. */
    public function game() {
        return $this->session->game;
    }

    /** Returns the step this request is for. */
    public function step() {
        return $this->session->current_step;
    }

    /** Returns the number of people requested for this group (including the requester). */
    public function size() {
        return $this->session->current_step->group_size;
    }

    /**
     * Returns true if the given GroupRequest satisfies this GroupRequest's conditions.
     * i.e., if they are a match
     */
    public function match(GroupRequest $other) {
        return $this->game() == $other->game() && $this->step() == $other->step();
    }
    
    /**
     * Returns the string representation of this GroupRequest, 
     * which is the session id.
     * 
     * This is used by the array_diff method, since it compares objects as strings.
     * 
     * @see array_diff()
     * @see GroupRequestQueue::removeRequests()
     * 
     * @return string
     */
    public function __toString() {
        return $this->session->id;
    }
}