# Contributing

Pull requests are welcome. For anything bigger than a bug fix, open an issue first so we can talk it through.

```bash
composer install
composer test
```

`composer test` runs Pint, PHPStan and Pest. `composer lint` fixes the code style for you. CI also requires 100% code coverage, which you can check locally with `composer test:coverage` if you have Xdebug or PCOV installed.

Add a line to `CHANGELOG.md` under "Unreleased" when your change is something users will notice.
