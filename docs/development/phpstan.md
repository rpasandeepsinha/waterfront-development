# PHPstan

PHPstan is one of the code quality tools used in Waterfront. This document
contains information about PHPstan that developers need to know to develop on
Waterfront.

<!-- TOC -->
* [PHPstan](#phpstan)
  * [Baseline](#baseline)
<!-- TOC -->

## Baseline

The `phpstan-baseline.neon` config file in Waterfront root contains a large
number of ignored linting errors.

This file was created when PHPstan was first introduced to Waterfront in
early 2021. The reason being: up until then the codebase had been developed,
first byWay2Web and later by Sandwave, without PHPstan linting. During that time
a very large number of linting errors had been introduced to the codebase.
Clearing all of these up before activating PHPstan would have required weeks of
work, during which new linting errors could have been introduced on the main
branch.

So instead, the decision was made to put all lint errors at the time into the
baseline. The idea being that during new development no new lint errors were
allowed to be introduced, and the existing errors would be solved through
boyscouting over time.

> [!] You are not allowed to add new exceptions to the PHPstan baseline.

If you encounter a lint error in new code that you have written, most often you
will simply need to modify your code to resolve the error. Very rarely your code
will be correct, but the lint error occurs because the framework’s typing is
incomplete or incorrect. In such cases you can add a broad exception to
`phpstan.neon`, following existing examples there.

If you modify existing code, you are highly encouraged to boyscout every file
you work with, resolving all PHPstan errors and removing the corresponding
exceptions from the baseline.

If you do large scale refactoring, like moving a dir with a large number of
files, then fixing all lint errors or manually updating the paths for all
exceptions in the baseline may be unfeasible. In such cases you are allowed to
simply re-generate the baseline file by running this command:

```shell
vendor/bin/phpstan --memory-limit=2G --generate-baseline
```

> [!] Make sure you don’t have any new PHPstan errors before running the above
command, always double check that no new errors are being ignored!
