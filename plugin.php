<?php
/**
 * Plugin Name: WP Access Token API
 * Plugin URI: https://github.com/attitude/wp-access-token-api
 * Description: API to generate, validate and invalidate access tokens
 * Version:  v0.1.0
 * Author: @martin_adamko
 * Author URI: http://twitter.com/martin_adamko
 * License: MIT
 */

class WP_AccessTokenAPI
{
    private static $instance;

    /**
     * @var string $algo Hashing algorithm for generating tokens
     *
     * Please note this plugin uses native WP Transient API and therefor length of the
     * action + token string must be less than 45 characters.
     *
     * SHA 256 is 32 bytes long, which provides decent degree of uniqueness while within
     * maximum length.
     *
     */
    public static $algo = 'md5';

    /**
     * Returns instance of this class
     *
     * @param void
     * @returns object Instance
     *
     */
    public static function getInstance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof WP_AccessTokenAPI)) {
            self::$instance = new WP_AccessTokenAPI;
        }

        return self::$instance;
    }

    /**
     * Constructor method
     */
    private function __construct() {}

    /**
     * Returns a token hash for an action
     *
     * @param string $action Action for which token is being registered.
     * @returns string Generated token using specific algo.
     *
     */
    protected function getTokenString($action)
    {
        return hash(static::$algo, microtime());
    }

    /**
     * Generate hash for transient key value
     *
     * @param string $action Action for which token is being registered for.
     * @param string $token  Token to validate.
     * @return string        Transient key.
     *
     */
    protected function getTransient($action, $token)
    {
        $transient = hash(static::$algo, $action.':'.$token);

        if (strlen($transient) > 45) {
            throw new Exception('Algorithm selected produces hash that is too long ('.strlen($transient).'). Consider using MD5.', 500);
        }

        return $transient;
    }

    /**
     * Sets a new token for an action
     *
     * @param string $action       Action for which token is being registered for must be a string, at least 4 chars long.
     * @param int    $ttlInMinutes Time to live in minutes must be an integer and cannot be a negative value.
     * @param int    $retries      Number of retries must be an integer and cannot be a negative value. Value of ZERO means token can be used until expires.
     * @return string              Generated token.
     *
     */
    public function set($action = null, $ttlInMinutes = 5, $retries = 0)
    {
        if (!isset($action) || !is_string($action) || strlen(trim($action)) < 4) {
            throw new Exception('Action must be a string, at least 4 chars long. You passed `'.$action.'`', 400);
        }

        if (!isset($ttlInMinutes) || !is_int($ttlInMinutes) || (int) $ttlInMinutes <= 0) {
            throw new Exception('Time to live in minutes must be an integer and must be a non-negative value. You passed `'.$ttlInMinutes.'`', 400);
        }

        if (!isset($retries) || !is_int($retries) || (int) $retries <= 0) {
            throw new Exception('Number of retries must be an integer and cannot be a negative value. You passed `'.$retries.'`', 400);
        }

        $token = $this->getTokenString($action);

        set_transient($this->getTransient($action, $token), (0 - $retries), ((int) $ttlInMinutes * MINUTE_IN_SECONDS));

        return $token;
    }

    /**
     * Removes token
     *
     * @param string $action   Action for which token is being registered for must be a string, at least 4 chars long.
     * @param string $token    Token to validate must be a string and cannot be a zero-length string. Token might be invalidated when number of retries or expired.
     * @returns bool           Invalidity check of the token.
     *
     */
    public function remove($action = null, $token = null)
    {
        if (!isset($action) || !is_string($action) || strlen(trim($action)) < 4) {
            throw new Exception('Action must be a string, at least 4 chars long. You passed `'.$action.'`', 400);
        }

        if (!isset($token) || !is_string($token) || strlen(trim($token)) < 1) {
            throw new Exception('Token to validate must be a string and cannot be a zero-length string. You passed `'.$token.'`', 400);
        }

        delete_transient($this->getTransient($action, $token));

        // Should return false, since the transient was deleted
        if (!$this->validate($action, $token)) {
            return true; // Successfully removed
        }

        throw new Exception('Access Token transient failed to remove.', 500);
    }

    /**
     * Validate token against action it was created for
     *
     * @param string $action   Action for which token is being registered for must be a string, at least 4 chars long.
     * @param string $token    Token to validate must be a string and cannot be a zero-length string. Token might be invalidated when number of retries or expired.
     * @returns bool           Validity of the token.
     *
     */
    public function validate($action = null, $token = null)
    {
        if (!isset($action) || !is_string($action) || strlen(trim($action)) < 4) {
            throw new Exception('Action must be a string, at least 4 chars long. You passed `'.$action.'`', 400);
        }

        if (!isset($token) || !is_string($token) || strlen(trim($token)) < 1) {
            throw new Exception('Token to validate must be a string and cannot be a zero-length string. You passed `'.$token.'`', 400);
        }

        // Retrieve token transient value
        $v = get_transient($this->getTransient($action, $token));

        // Nothing else to do
        if ($v === false) {
            return false;
        }

        // Cast
        $v = (int) $v;

        // Could be triggered just for limited times
        if ($v < 0) {
             // Last use...
            if ($v === -1) {
                // ... invalidate token
                delete_transient($this->getTransient($action, $token));
            } else {
                // Update token's value
                $v++;
                update_option('_transient_'.$this->getTransient($action, $token), $v);
            }
        }

        return true;
    }
}

// Init API service
add_action('plugins_loaded', array('WP_AccessTokenAPI', 'getInstance'));

