# Real-Time monitoring package for Symfony

- [Requirements](#requirements)
- [Install](#install)
- [Configure the API key](#api-key)
- [See official Documentation](https://docs.inspector.dev)

<a name="requirements"></a>

## Requirements

- PHP >= 7.1.3
- Symfony >= 5

<a name="install"></a>

## Install

Install the latest version of our package by:

```
composer require inspector-apm/inspector-symfony
```

<a name="api-key"></a>

### Configure the API Key

Create the `inspector.yaml` configuration file in your `config/packages` directory, and put the `api_key` field inside:

```yaml
inspector:
    api_key: [your-application-api-key]
```

You can obtain the `api_key` creating a new project in your [Inspector](https://www.inspector.dev) dashboard.


## Official documentation

**[See official documentation](https://docs.inspector.dev)**

## LICENSE

This package are licensed under the [MIT](LICENSE) license.
