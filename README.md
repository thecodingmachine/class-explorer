Class Explorer
===============

[![Latest Stable Version](https://poser.pugx.org/thecodingmachine/class-explorer/v/stable)](https://packagist.org/packages/thecodingmachine/class-explorer)
[![Total Downloads](https://poser.pugx.org/thecodingmachine/class-explorer/downloads)](https://packagist.org/packages/thecodingmachine/class-explorer)
[![Latest Unstable Version](https://poser.pugx.org/thecodingmachine/class-explorer/v/unstable)](https://packagist.org/packages/thecodingmachine/class-explorer)
[![License](https://poser.pugx.org/thecodingmachine/class-explorer/license)](https://packagist.org/packages/thecodingmachine/class-explorer)
[![Build Status](https://travis-ci.org/thecodingmachine/class-explorer.svg?branch=master)](https://travis-ci.org/thecodingmachine/class-explorer)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/class-explorer/badge.svg?branch=master&service=github)](https://coveralls.io/github/thecodingmachine/class-explorer?branch=master)

Discover PHP classes in your project.

This project aim is to offer a set of classes enabling classes/interface/trait discovery in your own project.

Currently, the project contains only one implementation based on scanning files.

GlobClassExplorer
-----------------

The `GlobClassExplorer` will look for all classes in a given namespace.

## Usage

```php
$explorer = new GlobClassExplorer('\\Some\\Namespace\\', $psr16Cache, $cacheTtl);
$classes = $explorer->getClasses();
// Will return: ['Some\Namespace\Foo', 'Some\Namespace\Bar', ...]
```

This explorer:

- looks only for classes in YOUR project (not in the vendor directory)
- assumes that if a file exists in a PSR-0 or PSR-4 directory, the class is available (assumes the file respects PSR-1)
- makes no attempt at autoloading the class
- is pretty fast, even when no cache is involved

By default, `GlobClassExplorer` will load classes recursively in sub-namespaces. You can prevent it to load classes
recursively by passing `false` to the 5th parameter:

```php
$explorer = new GlobClassExplorer('\\This\\Namespace\\Only\\', $psr16Cache, $cacheTtl, null, false);
```

You can also get a class map using the `getClassMap` method.
A class map is an array associating the name of the classes found (in key), to the file they are 
linked to (the real path of the file).

```php
$classMap = $explorer->getClassMap();
foreach ($classMap as $class => $file) {
    echo 'Class '.$class.' found in file '.$file;
}
```
