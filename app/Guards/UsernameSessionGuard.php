<?php

namespace App\Guards;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Log;

class UsernameSessionGuard extends SessionGuard
{
    /**
     * Attempt to authenticate a user using the given name and password.
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        // If only username is provided, convert it to credentials format
        if (isset($credentials['username']) && !isset($credentials['password'])) {
            return false;
        }

        // Check if username field exists
        if (!isset($credentials['username'])) {
            return false;
        }

        // Find user by username
        $user = $this->provider->retrieveByCredentials([
            'username' => $credentials['username']
        ]);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = [])
    {
        if (empty($credentials['username']) || empty($credentials['password'])) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials([
            'username' => $credentials['username']
        ]);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            return true;
        }

        return false;
    }

    /**
     * Attempt to authenticate using username or email.
     */
    public function attemptWithUsernameOrEmail(array $credentials = [], $remember = false)
    {
        if (empty($credentials['identifier']) || empty($credentials['password'])) {
            return false;
        }

        $identifier = $credentials['identifier'];
        
        // Try username first
        $user = $this->provider->retrieveByCredentials([
            'username' => $identifier
        ]);

        // If not found by username, try email
        if (!$user) {
            $user = $this->provider->retrieveByCredentials([
                'email' => $identifier
            ]);
        }

        if ($user && $this->provider->validateCredentials($user, ['password' => $credentials['password']])) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Get the last user we attempted to authenticate.
     */
    public function getLastAttempted()
    {
        return $this->lastAttempted;
    }
}