<?php
/**
 * A statement expressing a group's need for partners.
 * The group request is assumed to be associated with the session's current step;
 * once the session moves on, it is no longer relevant.
 * 
 * A group request can have an expiration time, after which point it should be ignored.
 */
class GroupRequest {
    /** The session requesting a group. */
    public $session;
    
    /** 
     * The time when this GroupRequest expires, as a Unix timestamp.
     * NULL when never.
     */
    public $expires;

    /**
     * Constructs a new group request.
     * 
     * @param Session $session the session requesting a group
     * @param int $expires a Unix timestamp of the time when the session expires, or NULL if it never expires
     */
    public function __construct(Session $session, $expires = NULL) {
        $this->session = $session;
        $this->expires = $expires;
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
     * Has this group request expired?
     * @return boolean TRUE if this request has expired, FALSE if it hasn't expired or never expires
     */
    public function expired() {
        return is_null($this->expires) ? FALSE : (time() > $this->expires);
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