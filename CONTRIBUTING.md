# Contributing

## Setup

    composer install

## Automated testing

Runs lint, PHPUnit (unit + integration), and PHPCS:

    composer test

Individual suites:

    composer lint
    composer unit
    composer cs

Fix coding standards violations automatically:

    composer cbf

The integration test (`tests/phpunit/SecurityWarningTest.php`) runs a real
`composer install` against local fixture packages in `tests/fixtures/packages/`
inside a temporary directory. It requires no network access.
