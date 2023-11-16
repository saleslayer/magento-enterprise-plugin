<a href="https://support.saleslayer.com"><p align="center"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Magento Enterprise plugin" width="230"></p></a>

# Sales Layer Magento Enterprise plugin

[![PHP Version](https://img.shields.io/badge/php-8.1%2C%208.2-8892BF.svg?style=flat-square&logo=php)](https://php.net/) [![Magento Version](https://img.shields.io/badge/Magento-%3E%3D2.4.4%2C%20%3C%3D2.4.6-AA92BF.svg?style=flat-square&logo=magento)](https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/composer.html?lang=en) [![GitHub release](https://img.shields.io/badge/release-2.7.0-green.svg?&color=%238cdb90&logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAGgSURBVHgB7ZdvTsIwGIfbd4sxAcluANzAz8bMOi/gTYwncNxgV+ECswFj/AY3UG9gIksMsM2+05llafenK8IHni/Q0b6/JyNr31GiyZnnpsXxZzijRAMge+YwBXrs4nzguQ/EEIMb9w5rkiYCOBHAfhR/sG9CAmukKQmwpkwCZOHiq4PjrhJZuKjxO3RkEqU7YLE8PEdXohSe41Cwb5UCEZ8HYtGkXKythCIc60xW4cxXCiA4oYtEm3CpQBeJtuGIRRSsX9/5yXhIxfbGSj+x0/Ew+yxexGttw5Ha7bPvub6YpPUk1IUjFqmh4k50Dm8koCPRNByh5VPtvzmehnsXaNzFqDaZnISk91E4D8guBOrCkZ21ZE3CuwA64bJzoozD2KhX0QnVCugcLMXwGJIF/HRCi6oDDEyHIxuIselwCuuUpyiYDkdoki4l66USYDocWfEnruon+uySKQXiZDsVsz66hP9JqJoaoCOlQMSfl0m6vc4ldMOVEqJunGx4cY5088geHbBY1c7W5t0wq2fZV3G8nn7xl7dagSYcX05N8Q1kOvEudFJMkgAAAABJRU5ErkJggg==)](https://github.com/saleslayer/magento-enterprise-plugin)

This is the official Sales Layer's plugin for Magento Enterprise (Adobe Commerce).

## Description

This plugin allows you to easily synchronize your Sales Layer catalogue information with your [Magento Enterprise]. And you can find more detailed documentation at our [website].

## Latest changes 

Read our [Changelog].

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
  * Avoid stock update. (stock will be updated only at creation of new items)
  * Variant configurable attributes.

## How To Start

### 1. Module package install process

#### 1.1 Download the package in your Magento

> **Warning**.
> Install the plugin only automatically or manually. If you try to install it with both methods at the same time, in step 1.2 Magento will return an error.

##### 1.1.1 Via Composer. In your Magento root folder, execute command:
```
composer require saleslayer/magento-enterprise-plugin
```
See [manage extensions on Adobe Commerce][magento-manage-extensions] for more info.

> **Info**
> If you have a previous version of the plugin installed in root folder 'app/code', please delete the complete folder of the plugin before moving forward with the plugin's installation via Composer.

Once executed and installed, the plugin will be found in your Magento installation root path, inside the folder 'vendor/saleslayer/magento-enterprise-plugin/

##### 1.1.1.1 Composer command examples
To install the latest valid version of the main branch: 
```
composer require saleslayer/magento-enterprise-plugin
```

To install the latest valid version having the 2.7.* tag (the number of version can be changed to any tag number that includes Composer): 
```
composer require saleslayer/magento-enterprise-plugin "2.7.*" 
```

Branch feature-295 will be installed (the branch name can be changed to any branch that includes Composer):
```
composer require saleslayer/magento-enterprise-plugin:dev-feature_295
```

##### 1.1.1.2 Composer requirements
Following Version guidance, check the plugin version to check which PHP and Magento version are required to install the plugin.

In case a requirement is not met, an error will be given by Composer.

> **Info**
> This will only happen if your Magento installation doesn’t met the requirements of the specific branch or tag version being installed, or by default, none of the main branch releases.
##### 1.1.2 Manually. Download the latest version zip and uncompress it into your Magento root folder 'app/code'

> **Warning**.
> Since plugin 2.7.0 version, this method will be deprecated. We strongly recommend to install the plugin via Composer. See step 1.1.1.

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

| Version       | Status | Magento Version (Adobe Commerce) | PHP       | Recommended Configuration  |
|---------------|--------|----------------------------------|-----------|----------------------------|
| 2.5.x         | EOL    | >= 2.4.1, <= 2.4.3               | 7.3       | Magento 2.4.3    / PHP 7.4 |
| [2.6.x]       | EOL    | >= 2.4.3, <= 2.4.5               | 7.3 - 8.1 | Magento 2.4.5-p1 / PHP 8.1 |
| [2.7.0]       | Latest | >= 2.4.4, <= 2.4.6               | 8.1 - 8.2 | Magento 2.4.6-p3 / PHP 8.1 |

> **Warning**.
> Adobe releases frequently new Magento Open Source versions, fixing bugs and/or adding new functionallity. Some of this versions could be in conflict with this plugin. We highly encourage you to set up the configuration recommended in the guidance table for running correctly this extension.

> **Note**. 
> See also [Magento system requirements][magento-system-requirements] for the right environment choice.


[Magento Enterprise]: https://business.adobe.com/products/magento/magento-commerce.html
[website]: https://support.saleslayer.com/category/magento
[Changelog]: ./CHANGELOG.md
[important notes]: https://support.saleslayer.com/magento/important-notes-about-magento-connector
[magento-system-requirements]: https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/system-requirements.html
[magento-manage-extensions]: https://experienceleague.adobe.com/docs/commerce-cloud-service/user-guide/configure-store/extensions.html
[2.6.x]:https://github.com/saleslayer/magento-enterprise-plugin/tree/2.6.x
[2.7.0]:https://github.com/saleslayer/magento-enterprise-plugin/releases/tag/2.7.0
