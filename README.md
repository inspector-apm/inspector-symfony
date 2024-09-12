# Inspector | Code Execution Monitoring tool

[![Total Downloads](https://poser.pugx.org/inspector-apm/inspector-symfony/downloads)](//packagist.org/packages/inspector-apm/inspector-symfony)
[![Latest Stable Version](https://poser.pugx.org/inspector-apm/inspector-symfony/v/stable)](https://packagist.org/packages/inspector-apm/inspector-symfony)
[![License](https://poser.pugx.org/inspector-apm/inspector-symfony/license)](//packagist.org/packages/inspector-apm/inspector-symfony)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.1-4baaaa.svg)](code_of_conduct.md)

> Before moving on, please consider giving us a GitHub star ⭐️. Thank you!

Code Execution Monitoring for Symfony applications.

- [Requirements](#requirements)
- [Install](#install)
- [Configure the INGESTION key](#key)
- [Official Documentation](https://docs.inspector.dev/symfony)
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

## Recipe (recommended)

You will be prompted to run the recipe. It will autoconfigure the package for you
creating the necessary environment variable and `inspector.yaml` configuration file.

<a name="key"></a>

## Configure the INGESTION Key

You can obtain the `ingestion key` creating a new project in your [Inspector](https://app.inspector.dev) dashboard.

```dotenv
###> inspector-apm/inspector-symfony ###
INSPECTOR_INGESTION_KEY=895d9e6dxxxxxxxxxxxxxxxxx
###< inspector-apm/inspector-symfony ###
```

## Official documentation

**[Go to the official documentation](https://docs.inspector.dev/symfony)**

<a name="contribution"></a>

## Contributing

We encourage you to contribute to the development of the Inspector bundle!
Please check out the [Contribution Guidelines](CONTRIBUTING.md) about how to proceed. Join us!

## LICENSE

This bundle is licensed under the [MIT](LICENSE) license.
