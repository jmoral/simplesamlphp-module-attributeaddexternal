# SimpleSAMLphp Composer module attributeaddexternal

![Build Status](https://github.com/jmoral/simplesamlphp-module-attributeaddexternal/actions/workflows/php.yml/badge.svg?branch=master)
[![Coverage Status](https://codecov.io/gh/jmoral/simplesamlphp-module-attributeaddexternal/branch/master/graph/badge.svg)](https://codecov.io/gh/jmoral/simplesamlphp-module-attributeaddexternal)
[![Type Coverage](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal/coverage.svg)](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal)
[![Psalm Level](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal/level.svg)](https://shepherd.dev/github/jmoral/simplesamlphp-module-attributeaddexternal)

This module allows you to obtain an attribute from an external URL which returns a json
## Install

This package is a SimpleSAMLphp module installable through
[Composer](https://getcomposer.org/). Installation can be as easy as executing:

```bash
vendor/bin/composer require simplesamlphp/simplesamlphp-module-attributeaddexternal
```

## Configuration

Next thing you need to do is to enable the module:

in `config.php`, search for the `module.enable` key and set `attributeaddexternal` to true:

```php
'module.enable' => [ 'attributeaddexternal' => true, … ],
```

See the [SimpleSAMLphp Composer module installer](https://github.com/simplesamlphp/composer-module-installer)
documentation for more information about creating modules installable through Composer.

### Adding external attributes

Then you need to set filter parameters in your config.php file.

Example, add an external attribute 'test' from an external website:
url is the url of the external website
jsonpath is where the information is in the response json

```
    70 => [
        'test' => [
            'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_seed=1&_locale=es_ES',
            'jsonpath' => 'data.0.username',
        ]
    ];

```

To send a parameter from user attributes

```
    70 = [
        'test' => [
            'url' => 'https://fakerapi.it/api/v1/users?_quantity=1&_locale=es_ES',
            'jsonpath' => 'data.0.username',
            'parameters' => [
                '_seed' => 'userSeed'
            ]
        ]
    ];
```
where userSeed is the name of the user's attribute

## Relevant files

This module consists of the following files:

- `composer.json`: The composer configuration file for this module.
- `README.md`: This document describing the module.
