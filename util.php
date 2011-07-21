<?php
/**
 * Static class with utility functions
 */
class Util {
    
    /*** TIME CONVERSION FUNCTIONS ***/
    /*
     * Note that MySQL has functions to do this (FROM_UNIXTIME, UNIX_TIMESTAMP),
     * but we're using PDO, which makes it more portable,
     * but also doesn't support these functions.
     */    
    
    /**
     * Converts Unix (epoch) time to an SQL datetime string.
     * 
     * Note that NULL is treated as zero, producing datetime = 1970:...
     * 
     * @param int $epoch the unix time
     * @return string an SQL datetime, in the format YYYY-MM-DD HH:MM:SS
     */
    public static function unix2sqltime($epoch) {
        date_default_timezone_set('UTC'); // for internal consistency, we use the timezone UTC for all operations
        return date('Y-m-d H:i:s', $epoch);
    }
    
    /**
     * Converts an SQL datetime to Unix (epoch) time.
     * 
     * @param string $datetime an SQL datetime (theoretically, in the format YYYY-MM-DD HH:MM:SS)
     * @return int the unix time
     */
    public static function sql2unixtime($datetime) {
        date_default_timezone_set('UTC'); // for internal consistency, we use the timezone UTC for all operations
        return strtotime($datetime);
    }
}