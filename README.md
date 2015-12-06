Enable rapid configuration of Symfony2 project dependencies
===========================================================

[![Build Status](https://travis-ci.org/vworldat/C33sConstructionKitBundle.svg)](https://travis-ci.org/vworldat/C33sConstructionKitBundle)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/afd9bbaa-3109-4fba-bf9b-d6b498bc62f6/mini.png)](https://insight.sensiolabs.com/projects/afd9bbaa-3109-4fba-bf9b-d6b498bc62f6)
[![codecov.io](http://codecov.io/github/vworldat/C33sConstructionKitBundle/coverage.svg?branch=master)](http://codecov.io/github/vworldat/C33sConstructionKitBundle?branch=master)

Is this for me?
---------------

Answer "yes" if those criteria match your requirements:

* developing/maintaining several Symfony projects
* using the same (or subsets of the same) bundles in most projects
* using the same (or subsets of the same) assets in the same places in most projects
* tired of enabling the same bundles and adding the same bundle configuration over and over again for each project
* necessity to keep parts of your projects in sync/up to date: make new bundles or assets available in ALL projects without having to do it manually

This is intended both for rapid development and long-term maintenance of reusable components in your own projects.

What does it do?
----------------

ConstructionKitBundle intends to ease project dependency maintenance by providing so-called *building blocks* that are used inside your projects.

A building block is a single class that provides information about:

* One or more Symfony bundles that are needed by this building block
* Optional YAML default configurations to include
* Optional (pre-filled) YAML configuration files to provide easily editable default configuration
* Optional assets (think js, css, ...), divided into groups that are available as assetic subsets
* Optional parameters to add to parameters.yml and parameters.yml.dist

Building blocks can be auto-installed upon recognition (configurable).

Composer integration
--------------------

Since ConstructionKit is set out to reduce the amount of manual tampering it allows to register building blocks in composer packages.
Packages found by composer will be auto-discovered, so you only have to do 2 things:

* Add composer dependencies and run `composer update`
* Run `app/console construction-kit:refresh` to auto-install bundles, configuration and assets


Installation
============

construction-kit relies on [`c33s/symfony-config-manipulator-bundle`](https://github.com/vworldat/C33sSymfonyConfigManipulatorBundle) to split and manage configuration files.
This will probably refactor most of your configuration structure inside `app/config/`.
Please head over to [`c33s/symfony-config-manipulator-bundle`](https://github.com/vworldat/C33sSymfonyConfigManipulatorBundle) to learn more and **DO NOT FORGET TO COMMIT YOUR CONFIGURATION BEFORE PROCEEDING**.

Require [`c33s/construction-kit-bundle`](https://packagist.org/packages/c33s/construction-kit-bundle) in your `composer.json` file:

```js
{
    "require": {
        "c33s/construction-kit-bundle": "@stable",
    }
}
```

or, if you are using ['composer-yaml'](https://packagist.org/packages/igorw/composer-yaml):

```yml
require:
    c33s/construction-kit-bundle:     '@stable'
```

Register both needed bundles in `app/AppKernel.php`:

```php

    // app/AppKernel.php

    public function registerBundles()
    {
        return array(
            // ... existing bundles
            new C33s\ConstructionKitBundle\C33sConstructionKitBundle(),
            new C33s\SymfonyConfigManipulatorBundle\C33sSymfonyConfigManipulatorBundle(),
        );
    }

```


Usage
=====

After enabling both bundles, an initial file setup is needed. Just run the following command: `app/console construction-kit:refresh`

You will see something like this:

```
    ######################################################
    #                                                    #
    # The symfony configuration has been changed.        #
    #                                                    #
    # Please re-run the construction-kit:refresh command #
    #                                                    #
    ######################################################
```

Do as told and run `app/console construction-kit:refresh` again. Then check the file `app/config/config/c33s_construction_kit.map.yml`, which should contain the following configuration:

```yml
# This file is auto-updated each time construction-kit:refresh is called.
# This may happen automatically during various composer events (install, update)
#
# Follow these rules for your maximum building experience:
#
# [*] Only edit existing block classes in this file. If you need to add another custom building block class use the
#     composer extra 'c33s-building-blocks' or register your block as a tagged service (tag 'c33s_building_block').
#     Make sure your block implements C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockInterface
#
# [*] You can enable or disable a full building block by simply setting the "enabled" flag to true or false, e.g.:
#     C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock:
#         enabled: true
#
#     If you enable a block for the first time, make sure the "force_init" flag is also set
#     C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock:
#         enabled: true
#         force_init: true
#
# [*] "use_config" and "use_assets" flags will only be used if block is enabled. They do not affect disabled blocks.
#
# [*] Asset lists will automatically be filled by all assets of asset-enabled blocks. To exclude specific assets, move them to their
#     respective "disabled" sections. You may also reorder assets - the order will be preserved.
#
# [*] Assets are made available through assetic using the @asset_group notation.
#
# [*] Custom YAML comments in this file will be lost!
#
c33s_construction_kit:
    mapping:
        building_blocks:
            C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock:
                enabled: true
                force_init: false
                use_config: true
                use_assets: true
            C33s\SymfonyConfigManipulatorBundle\BuildingBlock\ConfigManipulatorBuildingBlock:
                enabled: true
                force_init: false
                use_config: true
                use_assets: true
        assets: {  }

```

more to come.
