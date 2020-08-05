# 📝 Bolt Article Extension

This extension allows you to use fields of `type: article` in your
ContentTypes, as defined in `contenttypes.yaml`

```bash
composer require bolt/article
```


-------

The part below is only for _developing_ the extension. Not required for general
usage of the extension in your Bolt Project

## Running PHPStan and Easy Codings Standard

First, make sure dependencies are installed:

```
COMPOSER_MEMORY_LIMIT=-1 composer update
```

And then run ECS:

```
vendor/bin/ecs check src --fix
```
