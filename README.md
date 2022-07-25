# Inspector | Code Execution Monitoring tool

[![Total Downloads](https://poser.pugx.org/inspector-apm/inspector-symfony/downloads)](//packagist.org/packages/inspector-apm/inspector-symfony)
[![Latest Stable Version](https://poser.pugx.org/inspector-apm/inspector-symfony/v/stable)](https://packagist.org/packages/inspector-apm/inspector-symfony)
[![License](https://poser.pugx.org/inspector-apm/inspector-symfony/license)](//packagist.org/packages/inspector-apm/inspector-symfony)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.1-4baaaa.svg)](code_of_conduct.md)

Simple code execution monitoring for Symfony applications.

- [Requirements](#requirements)
- [Install](#install)
- [Configure the INGESTION key](#key)
- [Official Documentation](https://docs.inspector.dev)
- [Contribution Guidelines](#contribution)

<a name="requirements"></a>

## Requirements

- PHP >= 7.2
- Symfony ^4.4|^5.2|^6.0

<a name="install"></a>

## Install

Install the latest version of the bundle:

```
composer require inspector-apm/inspector-symfony
```

<a name="key"></a>

### Configure the INGESTION Key

Create the `inspector.yaml` configuration file in your `config/packages` directory, and put the `ingestion_key` field inside:

```yaml
inspector:
    ingestion_key: [your-ingestion-key]
```

You can obtain the `ingestion_key` creating a new project in your [Inspector](https://www.inspector.dev) dashboard.

## Official documentation

**[See official documentation](https://docs.inspector.dev)**

<a name="contribution"></a>

## Contributing

We encourage you to contribute to Inspector! Please check out the [Contribution Guidelines](CONTRIBUTING.md) about how to proceed. Join us!

## LICENSE

This bundle is licensed under the [MIT](LICENSE) license.
