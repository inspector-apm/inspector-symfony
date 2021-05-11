# Simple Code Execution monitoring for Symfony applications

- [Requirements](#requirements)
- [Install](#install)
- [Configure the INGESTION key](#ingestion_key)
- [See official Documentation](https://docs.inspector.dev)

<a name="requirements"></a>

## Requirements

- PHP >= 7.2.5
- Symfony >= 5.2

<a name="install"></a>

## Install

Install the latest version of our package by:

```
composer require inspector-apm/inspector-symfony
```

<a name="ingestion_key"></a>

### Configure the INGESTION Key

Create the `inspector.yaml` configuration file in your `config/packages` directory, and put the `ingestion_key` field inside:

```yaml
inspector:
    ingestion_key: [your-ingestion-key]
```

You can obtain the `ingestion_key` creating a new project in your [Inspector](https://www.inspector.dev) dashboard.


## Official documentation

**[See official documentation](https://docs.inspector.dev)**

## LICENSE

This package are licensed under the [MIT](LICENSE) license.
