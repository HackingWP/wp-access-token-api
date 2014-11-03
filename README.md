WP Access Token API
===================

*Simple access tokens using WordPress transients.*

## Installation

1. Download [zip](https://github.com/HackingWP/wp-access-token-api/archive/master.zip) and upload to your `wp-content/plugins` or install via `wp-admin` Plugins interface;
1. Activate

## Docs

Each generated `$token`:

- is tied to a specific `$action` for which it is requested for;
- can have specified time-to-live (`$ttl`), where `0` means it never expires;
- can be used just specified number of times (`$retries`), while `0` retries means
  unlimited times to use token.

#### Requesting a new token

```php
$tokenAPI = WP_AccessTokenAPI::getInstance();

$action  = 'post:123:delete'; // Action you want later to test against
$ttl     = 5; // minutes
$retries = 2; // can be triggered max 2x

try {
    $token = $tokenAPI->set($action, $ttl, $retries);
} catch (Exception $e) {
    // Handle error
}

// Use token, e.g: send token to user by mail...
```

#### Validating token

```php
$tokenAPI = WP_AccessTokenAPI::getInstance();

if (isset($_GET['token']) && strlen(trim($_GET['token'])) > 0 && isset($_GET['action']) && $_GET['action'] === 'post:123:delete') {
    $authenticated = false;
    $action = $_GET['action'];
    $token  = $_GET['token'];

    try {
        $authenticated = $tokenAPI->validate($action, $token);
    } catch (Exception $e) {
        // Handle error
    }

    if ($authenticated) {
        // Delete post...
    }
}
```

#### Removing token

```php
try {
    $removed = $tokenAPI->remove($action, $token);
} catch (Exception $e) {
    // Handle error
}
```

---

Enjoy!

[@martin_adamko](http://twitter.com/martin_adamko)
