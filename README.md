# SimpleSAMLphp Composer module attributeaddexternal

![Build Status](https://github.com/jmoral/simplesamlphp-module-attributeaddexternal/actions/workflows/php.yml/badge.svg?branch=master)
[![Coverage Status](https://codecov.io/gh/jmoral/simplesamlphp-module-attributeaddexternal/branch/master/graph/badge.svg)](https://codecov.io/gh/jmoral/simplesamlphp-module-attributeaddexternal)
[![Type Coverage](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal/coverage.svg)](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal)
[![Psalm Level](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal/level.svg)](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal)

This module is a SimpleSAMLphp `authproc` filter that adds an attribute to the
response by fetching it from an external URL that returns JSON.

## Requirements

- PHP `^8.3`
- `simplesamlphp/simplesamlphp` `^2.5.2`
- `symfony/http-foundation` `^7.4`

## Configuration

First, enable the module.

In `config.php`, search for the `module.enable` key and set `attributeaddexternal` to true:

```php
'module.enable' => [ 'attributeaddexternal' => true, … ],
```

### Adding external attributes

Then configure the filter in the `authproc` list of your SP/IdP config
(`config.php` or `saml20-sp-remote.php`/`saml20-idp-hosted.php`). The array key
(`70` in the examples below) is the standard SimpleSAMLphp authproc filter
priority, not something specific to this module — it just determines the
order in which filters run.

Basic example: add an external attribute `test`, where `url` is the endpoint
to query and `jsonpath` is the dotted path to the value inside the JSON
response:

```php
70 => [
    'class' => 'attributeaddexternal:AttributeAddExternal',
    'test' => [
        'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_seed=1&_locale=es_ES',
        'jsonpath' => 'data.0.username',
    ],
],
```

### `replace`

By default, if the attribute already exists, the fetched value is appended to
the existing value(s) instead of replacing them. Set `replace` to `true` to
overwrite the existing attribute instead:

```php
70 => [
    'class' => 'attributeaddexternal:AttributeAddExternal',
    'test' => [
        'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_seed=1&_locale=es_ES',
        'jsonpath' => 'data.0.username',
        'replace' => true,
    ],
],
```

### `parameters`

Use `parameters` to build the request URL from the user's own attributes.
Each key is the name of the URL parameter to send, and each value is the name
of the user attribute whose value should be used:

```php
70 => [
    'class' => 'attributeaddexternal:AttributeAddExternal',
    'test' => [
        'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_locale=es_ES',
        'jsonpath' => 'data.0.username',
        'parameters' => [
            '_seed' => 'userSeed',
        ],
    ],
],
```

Here `userSeed` is the name of the user's attribute, and its value is sent as
the `_seed` query parameter.

If a parameter fails to resolve because the named user attribute does not
exist, the filter throws a `SimpleSAML\Error\Exception` and processing stops.

#### Path parameters

An empty string key (`''`) is a special case: instead of being added as a
query string parameter, the corresponding attribute's value is appended
directly to the URL. This is useful when the external API expects the value
as part of the path rather than as a query parameter:

```php
70 => [
    'class' => 'attributeaddexternal:AttributeAddExternal',
    'test' => [
        'url' => 'https://fakerapi.it/api/v1/users/',
        'jsonpath' => 'data.0.username',
        'parameters' => [
            '' => 'field',
            '_seed' => 'userSeed',
        ],
    ],
],
```

Here the value of the user's `field` attribute is appended straight to the
`url`, before the remaining `parameters` are added as query string parameters.

### `context`

Use `context` to pass extra HTTP client options to the request, such as
custom headers (for example, an API key or bearer token required by the
external service):

```php
70 => [
    'class' => 'attributeaddexternal:AttributeAddExternal',
    'test' => [
        'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_seed=1&_locale=es_ES',
        'jsonpath' => 'data.0.username',
        'context' => [
            'headers' => [
                'Authorization' => 'Bearer yourApiKey',
            ],
        ],
    ],
],
```

`context` is passed through as-is to SimpleSAMLphp's `Utils\HTTP::fetch()`,
so it accepts any option supported by PHP's [stream context](https://www.php.net/manual/en/context.php).

## Error handling

The filter throws `SimpleSAML\Error\Exception` (which aborts authentication
processing) in the following cases:

- The URL cannot be fetched (network error, non-2xx response, etc.).
- The response body is not valid JSON.
- The `jsonpath` does not match any value in the decoded response.
- A `parameters` entry references a user attribute that does not exist.

## Logging

The filter logs the URL and `jsonpath` used for each lookup, as well as the
resolved value, at debug level via `SimpleSAML\Logger`.

When `context.headers` is set, the filter also logs, per header, its name,
raw and trimmed length, and a short SHA-256 fingerprint of its value — never
the value itself. This is meant to help diagnose empty or misconfigured
secrets (e.g. an API key coming from `getenv()`) without leaking them to the
logs.

## Relevant files

This module consists of the following files:

- `composer.json`: The composer configuration file for this module.
- `src/Auth/Process/AttributeAddExternal.php`: The `authproc` filter implementation.
- `tests/`: PHPUnit tests covering the filter's configuration parsing and behavior.
- `README.md`: This document describing the module.
