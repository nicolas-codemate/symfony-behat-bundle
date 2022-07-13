# symfony-behat-bundle
This bundle provides reusable behat contexts for symfony applications.
All steps are written in present tense, as suggested on https://automationpanda.com/2021/05/11/should-gherkin-steps-use-past-present-or-future-tense/

## Features
* BrowserContext for simple HTTP/DOM/Form interactions
* CommandContext to test symfony commands
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
  extensions:
    FriendsOfBehat\SymfonyExtension:
      bootstrap: tests/bootstrap.php
      kernel:
        path: src/Kernel.php
        class: App\Kernel
        environment: behat
        debug: false
  suites:
    default:
      contexts:
        - Elbformat\SymfonyBehatBundle\Context\BrowserContext
        - Elbformat\SymfonyBehatBundle\Context\CommandContext
        - Elbformat\SymfonyBehatBundle\Context\LoggingContext
```

## Examples
Examples can be found in [dock/examples.md](doc/examples.md).

## What's next?
The next release will likely contain more contexts like
* AbstractDoctrineContext - to support building your own entity-based contexts
* AbstractApiContext - to build your own API-Mock contexts
* SwiftmailerContext / MailerContext - testing sent mails

## Development
If you want to develop on the bundle you will find useful information in [doc/development.md](doc/development.md)