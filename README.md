# symfony-behat-bundle
This bundle provides reusable behat contexts for symfony applications.
All steps are written in present, as suggested on https://automationpanda.com/2021/05/11/should-gherkin-steps-use-past-present-or-future-tense/

## Features
* BrowserContext for simple HTTP/DOM interactions
* FormContext to fill and test form submission
* CommandContext to test commands
* LoggingContext to verify correct logging

## Installation

Add to your composer requirements as dev dependency.
```console
$ composer require --dev elbformat/symfony-behat-bundle
```

Activate bundle in your `config/bundles.php`
```php
Elbformat\SymfonyBehatBundle\ElbformatSymfonyBehatBundle::class => ['test' => true],
```

Use contexts in your `behat.yml` as you like
```yaml
default:
  suites:
    default:
      contexts:
        Elbformat\SymfonyBehatBundle\Context\BrowserContext
        Elbformat\SymfonyBehatBundle\Context\FormContext
        Elbformat\SymfonyBehatBundle\Context\AjaxContext
        Elbformat\SymfonyBehatBundle\Context\CommandContext
        Elbformat\SymfonyBehatBundle\Context\MailerContext
        Elbformat\SymfonyBehatBundle\Context\LoggingContext
```

# Work in progress
More contexts, less dependencies.
* AbstractDoctrineContext to support building your own entity-based contexts
  * How to manage loose dependencies to doctrine/orm without forcing to use it?
* SwiftmailerContext
  * How to manage loose dependencies without forcing to use it?
* AbstractApiContext to build your own API-Mock contexts
* LoggingContext
