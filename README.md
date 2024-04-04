# sandbox-multipackage
Sandbox for working with multiple packages. Used for dev only.

Create project `t1` in `consumer` directory:

```bash
cd consumer
composer create-project --prefer-dist drevops/scaffold="@dev" --repository '{"type": "path", "url": "../scaffold", "options": {"symlink": false}}' t1
```
