<a href="https://support.saleslayer.com"><p align="center"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Magento Enterprise plugin" width="460"></p></a>

# Sales Layer Magento Enterprise plugin

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.2-8892BF.svg?style=flat-square)](https://php.net/) [![Minimum Magento Version](https://img.shields.io/badge/Magento-%3E%3D%202.4.1-AA92BF.svg?style=flat-square)](https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/composer.html?lang=en) [![GitHub release](https://img.shields.io/github/v/release/saleslayer/magento-enterprise-plugin)](https://github.com/saleslayer/magento-enterprise-plugin)

This is the official Sales Layer's plugin for Magento Enterprise.

## Description

This plugin allows you to easily synchronize your Sales Layer catalogue information with your [Magento Enterprise]. And you can find more detailed documentation at our [website].

## Download 

[Download latest plugin version][latest-release-download].
Check out the latest changes at our [changelog][changelog-md].

## Important Notes

Please check the [important notes] for the installation. (In some cases, a Sales Layer account might be needed to access the documentation).

## Requirements

* cUrl extension installed; In order to call and obtain the information from Sales Layer.
* Define the fields relationship in the Sales Layer Magento connector:
  * Most Magento fields are already defined in each section, extra fields for products or variants will be Stores -> Attributes -> Product and they must have been created in Magento in order to synchronize.
  * When synchronizing a product with variants, Magento attributes that are synchronized will be marked as Used for variations, then, attribute values from the product and variants will be combined and assigned to the parnet product. Variations must have only one value for each attribute.
* Inside categories, products and variants there will be attributes; Sales Layer Product Identification, Sales Layer Product Company Identification and Sales Layer Format Identification, don't modify or delete this attributes or its values, otherwise, the products will be created again as new ones in the next synchronization.
* Inside the connector configuration you can set different values before the synchronization in the different tabs, such as:
  * Auto-synchronization and preferred hour for it.
  * The stores where the information will be updated.
  * The root category where the incoming category branch will be set.
  * Avoid stock update (stock will be updated only at creation of new items)
  * Variant configurable attributes

## How To Start

### 1. Install the package your Magento 

#### 1.1 Uncompress module into Magento root folder 'app/code'

#### 1.2 From Magento root folder execute commands:

```php

php bin/magento setup:upgrade
php bin/magento setup:di:compile (if there's an error with 'var/di/' folder just delete it and execute this command again)
php bin/magento setup:static-content:deploy
php bin/magento cache:clean

```

#### 1.3 After executing the commands, Sales Layer module will be installed

### 2. Create a Sales Layer Magento connector and map the fields

#### 2.1 The plugin needs the connector ID code and the private key, you will find them in the connector details of Sales Layer.

### 3. Add the connector credencials in Magento

#### 3.1 Go to Sales Layer -> Import -> Add connector and add the connector id and secret key.
#### 3.2 Finally, In Sales Layer -> Import -> The connector you created, push Synchronize Connector to import categories, products and variants automatically.

## Version Guidance

| Version         | Status         | Magento Version (Adobe Commerce) | PHP Version    |
|-----------------|----------------|----------------------------------|----------------|
| 2.5.x           | EOL            | >= 2.4.1, <= 2.4.3               | 7.1, 7.2, 7.3  |
| 2.6.0           |                | >= 2.4.1, <= 2.4.5-p1            | >= 7.2, <= 8.1 |
| 2.6.1           | Latest         | >= 2.4.1, <= 2.4.5-p1            | >= 7.2, <= 8.1 |

[Magento Enterprise]: https://business.adobe.com/products/magento/magento-commerce.html
[website]: https://support.saleslayer.com/category/magento
[latest-release-download]: https://github.com/saleslayer/magento-enterprise-plugin/releases/latest/download/magento-enterprise-plugin.zip
[changelog-md]: https://github.com/saleslayer/magento-enterprise-plugin/blob/master/CHANGELOG.md
[important notes]: https://support.saleslayer.com/magento/important-notes-about-magento-connector
