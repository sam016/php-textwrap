# php-textwrap

- [Introduction](#Introduction)
- [Requirements](#Requirements)
- [Installation](#Installation)
- [Development](#Development)
  - [Setup](Setup)
  - [Docker shell](#Docker-shell)
- [Tests](#Tests)
- [Issues](#Issues)

## Introduction

**php-textwrap** is the library inspired by [Python](https://docs.python.org/)'s [argparse](https://docs.python.org/3.1/library/textwrap.html)

## Requirements

Only requirement:

    php >= 7.2

## Installation

    composer require sam016/php-textwrap

## Development

### Setup

1. Build the docker container

    `docker-compose build`

2. Start the docker container

    `docker-compose up`

### Docker shell

Use the following command to execute inside the docker container

`docker exec -it $(docker ps -aqf 'name=textwrap') bash`

## Tests

Use the following command inside the container to run the test cases

`composer test`

Use the following command from host machine to run the test cases

`docker exec -it $(docker ps -aqf 'name=textwrap') composer test`

## Issues

Guidelines for raising the bugs:

1. Please check the existing issues in the **Issues** section before creating a new one.
2. State
    - reproduction steps
    - library version
    - php version
    - crystal clear description
    - did it just get too much?
