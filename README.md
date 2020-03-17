# PHP Coding Standards for EthicalJobs Projects

Exists to:
- Provide a source of truth for our code-styling and rules
- Provide an easy interface for developers to locally run the standards suite
- Provide a Drone pipeline template generation

Does not exist to:
- Wrap the execution on the build server

## Usage
```
composer require --dev ethicaljobs/standards
vendor/bin/ej-standards run
```

## The Standards


### PHP Mess Detector
Enforces good code design

### PHP Code Sniffer
Useful for keeping consistent code style

### PHP Stan
Useful for determining edge-cases, un-usable code paths, ... 

## Configuration

## Runners
This standards package comes with two runners for running the standards suite.

### Assumptions
- drone-cli is installed
- the project has a pipeline called 'standards'