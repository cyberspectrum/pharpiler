[![Build Status](https://travis-ci.org/cyberspectrum/pharpiler.png)](https://travis-ci.org/cyberspectrum/pharpiler)
[![Latest Version tagged](http://img.shields.io/github/tag/cyberspectrum/pharpiler.svg)](https://github.com/cyberspectrum/pharpiler/tags)
[![Latest Version on Packagist](http://img.shields.io/packagist/v/cyberspectrum/pharpiler.svg)](https://packagist.org/packages/cyberspectrum/pharpiler)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cyberspectrum/pharpiler.svg)](https://packagist.org/packages/cyberspectrum/pharpiler)

What is Pharpiler?
==================

Pharpiler is a simple, yet highly configurable, command line tool to compile
composer based php projects into a phar file.

Why Pharpiler?
==============

I (@discordier) got tired of writing custom compile scripts for each and every
phar project I was developing over and over again which mostly consisted of
copy&paste code from each project.
Hence there definately was need for reduction of code duplication and so
Pharpiler was born.
 
How to use?
===========

In your project run `composer require --dev cyberspectrum/pharpiler` and create
a `.pharpiler.yml` in your project root.

Configuration
=============

The `.pharpiler.yml` allows for many configuration options, have a look at the
[configuration](https://github.com/cyberspectrum/pharpiler/blob/master/.pharpiler.yml)
of Pharpiler itself in this repository (yes, Pharpiler compiles itself).

Within the configuration you may use certain parameters.

You may define own parameters in the root level to reduce recurrences.
Example:
```yml
parameters:
  - my-parameter: "Hello World"
```

However, Pharpiler registers some parameters for each package to ease configuration.
Package root dir: `%package:[vendor]/[package-name]%`
Package version: `%version:[vendor]/[package-name]%`
Package release date: `%date:[vendor]/[package-name]%`

```yml
# First off, tell what the desired phar name should be.
phar: pharpiler.phar

# Now we need to declare some compile tasks.
tasks:
  # We want to add a composer package.
  - type: add-package

    # The name of the package.
    # NOTE: you most likely. want to add your root package here as first.
    name: cyberspectrum/pharpiler

    # Optional flag to include all requirements of the package
    # default: true
    # You may specify a list of package names instead of true or false.
    # include_require: true

    # Optional flag to include all development requirements of the package.
    # default: false
    # You may specify a list of package names instead of true or false.
    # include_requiredev: false

    # Optionally list package names that shall get omitted.
    exclude_dependencies:
    #  - phpcq/all-tasks

    # Define file name pattern for files to get included.
    # You can specify glob and regex patterns here.
    # NOTE: this example makes use of YAML references.
    include_files: &default_include_files
      # Include all php files.
      - "*.php"
      # Include all the license files.
      - "(LICENSE\\.?.*)"
    # Define file name patterns of files to exclude.
    # You can specify glob and regex patterns here.
    # NOTE: this example makes use of YAML references.
    exclude_files: &default_exclude_files
      # We do not want to include unit tests.
      - "(.*[Tt]ests?\/)"
      - "*tests/*.php"

    # You may override the "include_files", "exclude_files" on a per package 
    # basis and can even move files around by specifying "rewrite_paths".
    package_override:
      # Override settings for package "cyberspectrum/pharpiler"
      cyberspectrum/pharpiler:
        include_files:
          # Import the previously defined default includes.
          - *default_include_files
          # Additionally add the file "bin/pharpiler" from said package.
          - "%package:cyberspectrum/pharpiler%/bin/pharpiler"
        exclude_files:
          # Import the previously defined default excludes.
          - *default_exclude_files
          # Additionally omit the files within phar directories.
          - "*/phar/*"
        rewrite_paths:
          # Here you can rewrite path portions.
          src: /src

      # Symfony has some tester classes we do not want to add - so let's skip them.
      symfony/console:
        exclude_files:
          - *default_exclude_files
          - "(Tester\/)"

  # A real phar file needs a stub, here you tell pharpiler to set it.
  - type: set-stub
    # The stub file must be accessible from anywhere within the composer project.
    stub_file: %package:cyberspectrum/pharpiler%/phar/stub.php

  # We most of the times also need to add the autoload information.
  - type: composer-autoload
    # Pharpiler can optimize the autoload information to remove anything which
    # got excluded by any other setting.
    # Default: true
    optimize: true

# For some files it is necessary to alter the file contents.
# To do so, you may specify a list of filters which alter file contents.
# Currently there are the following types of filters registered:
# - replace      Which performs simple search and replace on the file contents.
# - php-strip    Which removes all comments and trailing whitespace while
#                maintaining line numbers.
# - warning-time Creates a timestamp in the future - useful for selfupdate
#                notices etc.
rewrites:
    # Define the file names to be matched by this rewrite.
  - files:
      - %package:cyberspectrum/pharpiler%/phar/stub.php
      - %package:cyberspectrum/pharpiler%/bin/pharpiler
    # Define a list of filters to apply on these files.
    filter:
      # Search for "@@MIN_PHP_VERSION@@" and replace it with "5.5"
      - type: replace

        @@MIN_PHP_VERSION@@: "5.5"
      # Search for "@@PHARPILER_VERSION@@" and replace it with the installed version.
      - type: replace
        @@PHARPILER_VERSION@@: %version:cyberspectrum/pharpiler%

      # Search for "// @@DEV_WARNING_TIME@@" and replace it with the given define.
      # The magic value "@@warning_time@@" will get replaced with the calculated timestamp.
      - type: warning-time
        search: // @@DEV_WARNING_TIME@@
        format: define('DEV_WARNING_TIME', @@warning_time@@);
        # The ahead value is: 30 * 24 * 3600 = 2592000, hence 30 days.
        ahead: 2592000

  # This removes the shebang line from the passed script.
  - files:
      - %package:cyberspectrum/pharpiler%/bin/pharpiler
    filter:
      - type: replace
        "#!/usr/bin/env php\n": ""

  # You most likely want to keep the php-strip filter at the end as otherwise 
  # comments will get stripped that should get replaced by other filters, like
  # the "warning-time".
  - files: "*.php"
    filter:
      - type: php-strip

```
