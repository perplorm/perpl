# Perpl ORM

Perpl is a fork of the unmaintained [Propel2](https://github.com/propelorm/Propel2), an open-source Object-Relational Mapping (ORM) for PHP. It adds several improvements and fixes, including proper versioning.

[![Github actions Status](https://github.com/mringler/perpl/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/propelorm/Propel2/actions/workflows/ci.yml?query=branch%3Amaster)
[![codecov](https://codecov.io/gh/propelorm/Propel2/branch/master/graph/badge.svg?token=L1thFB9nOG)](https://codecov.io/gh/propelorm/Propel2)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%207-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Psalm](https://img.shields.io/badge/Psalm-level%205-darkgreen.svg?style=flat)](https://psalm.dev/docs/running_psalm/error_levels/)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/propel/propel/license.svg)](https://packagist.org/packages/perplorm/perpl)


# Installation

- Replace the `require` declaration for Propel with Perpl:
```diff
  "require": {
+    "perplorm/perpl": ">=2.0",
-    "propel/propel": "dev-main as 2.0.x-dev",
  },
```

- Remove the `vcs` entry for Propel2 dev in composer.json:
```diff
  "repositories": [
-    {
-      "type": "vcs",
-      "url": "git@github.com:propelorm/Propel2.git"
-    }
  ],
```

- Update libraries:
```bash
$ composer update
```
- Rebuild models:
```bash
$ vendor/bin/propel --config-dir <path/to/config> model:build
$ composer dump-autoload
```
- Open a file where you call `Query::find()` and replace it with `Query::findObjects()`. If everything worked, you get return type `ObjectCollection<YourModelName>`. Yay!  


| :zap:        Don't forget to analyze your project with [PHPStan](https://phpstan.org/) (or similar) to get notice where updates are necessary due to improved types in Perpl.  |
|------------------------------------------|

# Features

Motivation for Perpl was to make features available around code-style, typing, performance and usability.

## Ready for PHP 8.5

Code is tested to run in PHP 8.5 without deprecation notices or warnings.

## Type-preserving queries

Improved types allow for code completion and statistic analysis.
- preserves types between calls to `useXXXQuery()`/`endUse()`
- adds methods `findObjects()`/`findTuples()`, which return typed Collection objects

```php
// keep query class over calls to useQuery()
$q = BookQuery::create();                              // BookQuery<null>
$q = $q->useAuthorQuery();                             // AuthorQuery<BookQuery<null>>
$q = $q->useEssayRelatedByFirstAuthorIdExistsQuery();  // EssayQuery<AuthorQuery<BookQuery<null>>>
$q = $q->endUse();                                     // AuthorQuery<BookQuery<null>>
$q = $q->endUse();                                     // BookQuery<null>

// keep query class over conditional chain
$q->_if($condition)
  ->filterBy...()
  ->_else()
  ->filterBy...()
  ->_endif();                                          // all BookQuery<null>

// findObjects() returns object collection
$o = $q->findObjects();                                // BookCollection
$a = $o->populateAuthor()                              // AuthorCollection
$a = $a->getFirst();                                   // Author|null

// findTuples() returns arrays
$a = $q->findTuples();                                 // ArrayCollection
$r = $q->getFirst();                                   // array<string, mixed>|null
```

Note that type propagation in `endUse()` requires child query classes to declare and pass on the generic parameter from their parent/base class:
```php
/*
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BaseBookQuery<ParentQuery>
 */
class BookQuery extends BaseBookQuery
```
This cannot be added automatically for existing classes. While IDEs seem to figure it out without the declaration, phpstan or psalm will (correctly) see the return type as `null` and report errors. Add the declaration in the child class to fix it.

Generating collection classes for models can be configured in schema.xml, (see [#47](https://github.com/mringler/perpl/pull/47) for details)

## Code cleanup and improved performance

These changes mostly improve internal readability/soundness of core Propel code. They mostly allow for easier and safe maintenance, but occasionally lead to performance improvements, for example when repetitive operations on strings are replaced by proper data structures.

Some notable changes:
- columns in queries are turned to objects, which leads to more readable code and makes tons of string operations obsolete (~30-50% faster query build time, see [#24](https://github.com/mringler/perpl/pull/24))
- fixes some confusing names (Criteria vs Criterion)
-  spreads out some "one size fits none" overloads, i.e. `Criteria::map` becomes `Criteria::columnFilters` and `Criteria::updateValues`

## Nested filters through operators

Introduces `Criteria::combineFilters()`/`Criteria::endCombineFilters()` which build nested filter conditions:
```php
// A=1 AND (B=2 OR C=3) AND (D=4 OR E=5)
(new Criteria())
  ->addFilter('A', 1)
  ->combineFilters()
    ->addFilter('B', 2)
    ->addOr('C', 3)
  ->endCombineFilters()
  ->combineFilters()
    ->addFilter('D', 4)
    ->addOr('E', 5)
  ->endCombineFilters()
```
Previously, this required to register the individual parts under an arbitrary name using `Criteria::addCond()` and then combining them with `Criteria::combine()`, possibly under another arbitrary name for further processing.

## Read multiple behaviors from same repository

Propel restricts reading behaviors from repositories to one per repo. This allows to read multiple behaviors (see [#25](https://github.com/mringler/perpl/pull/25) for details).

## Fixed cross-relations

Creates methods for all elements of ternary relation (Propel only uses first foreign key).
Fixes naming issues and detects duplicates in model method names.

# Disclaimer

Built with care, tested, provided as is. Test before deployment. Let me know how it goes!

Feedback and PRs are welcome.

Thanks to Propel2!

# License

MIT. See the `LICENSE` file for details.
