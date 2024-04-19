# sandbox-multipackage
Sandbox for working with multiple packages. Used for dev only.

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/DrevOps/sandbox-multipackage.svg)](https://github.com/DrevOps/sandbox-multipackage/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/DrevOps/sandbox-multipackage.svg)](https://github.com/DrevOps/sandbox-multipackage/pulls)
[![Test PHP](https://github.com/DrevOps/sandbox-multipackage/actions/workflows/test-php.yml/badge.svg)](https://github.com/DrevOps/sandbox-multipackage/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/drevops/sandbox-multipackage/graph/badge.svg?token=URITLLZKK5)](https://codecov.io/gh/drevops/sandbox-multipackage)

</div>

---

<p align="center"> Few lines describing your project.
    <br>
</p>


## Usage

```bash
cd consumer
composer create-project --prefer-dist drevops/scaffold="@dev" --repository '{"type": "path", "url": "../scaffold", "options": {"symlink": false}}' t1
```


## Maintenance

    composer install
    composer lint
    composer test


### Debugging

Run once:
```bash
cd scaffold
composer install

export COMPOSER_ALLOW_XDEBUG=1
export COMPOSER_DEBUG_EVENTS=1
export DREVOPS_SCAFFOLD_VERSION=@dev

cd ../consumer 
```

Run as needed (from `consumer`):
```bash
# No-install
rm -Rf t1 >/dev/null && php ../scaffold/vendor/composer/composer/bin/composer create-project --prefer-dist drevops/scaffold="@dev" --repository '{"type": "path", "url": "../scaffold", "options": {"symlink": false}}' --no-install t1
```   

or

```bash
# Full
rm -Rf t1 >/dev/null && php ../scaffold/vendor/composer/composer/bin/composer create-project --prefer-dist drevops/scaffold="@dev" --repository '{"type": "path", "url": "../scaffold", "options": {"symlink": false}}' t1
```   
