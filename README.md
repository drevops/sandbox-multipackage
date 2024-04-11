# sandbox-multipackage
Sandbox for working with multiple packages. Used for dev only.

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/DrevOps/sandbox-multipackage.svg)](https://github.com/DrevOps/sandbox-multipackage/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/DrevOps/sandbox-multipackage.svg)](https://github.com/DrevOps/sandbox-multipackage/pulls)
[![Test PHP](https://github.com/DrevOps/sandbox-multipackage/actions/workflows/test-php.yml/badge.svg)](https://github.com/DrevOps/sandbox-multipackage/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/DrevOps/sandbox-multipackage/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/DrevOps/sandbox-multipackage)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/DrevOps/sandbox-multipackage)
![LICENSE](https://img.shields.io/github/license/DrevOps/sandbox-multipackage)

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



