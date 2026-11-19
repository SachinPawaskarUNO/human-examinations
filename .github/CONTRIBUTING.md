# Contribution Guide

Thank you for considering contributing to Human Examinations! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
npm ci
```

See the [development guide](../docs/development.md) for the local workbench,
validation commands, and region-map maintenance. User-facing guides are indexed
in [docs/README.md](../docs/README.md).

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run PHP validation and the frontend catalogue/rendering tests:

```bash
composer test
npm test
npm run build
```
