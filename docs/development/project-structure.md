# Project structure

This documents the standards for organizing code within the Waterfront codebase.

<!-- TOC -->
* [Project structure](#project-structure)
  * [Src](#src)
    * [src/Apps](#srcapps)
    * [src/Domain](#srcdomain)
    * [src/Infra](#srcinfra)
    * [src/Support](#srcsupport)
  * [Tests](#tests)
  * [Subdirectory structure](#subdirectory-structure)
  * [FAQ](#faq)
    * [I’m seeing lots of code that doesn’t match these standards?](#im-seeing-lots-of-code-that-doesnt-match-these-standards)
    * [My code doesn’t fit anywhere in this structure, where should it go?](#my-code-doesnt-fit-anywhere-in-this-structure-where-should-it-go)
<!-- TOC -->

## Src

The `src/` top-level dir is the main directory for all production code. This dir
should contain no test or dev code. The src dir is divided into four subdirs
based on the main application layers.

### src/Apps

This layer contains all and only entrypoint code. I.e. if it’s coming into the
application from outside it should be picked up by code within Apps first. The
directory is subdivided for every group of entrypoints that Waterfront supports,
e.g. console commands, HTTP requests from various API endpoints and webhooks,
and the integrated Nova application.

Calls from outside are unreliable: it’s the responsibility of this layer to
completely authenticate, authorize and validate incoming requests and then map
incoming data structures to Domain structures. Once this is done, further
handling of the request is delegated to the Domain layer. Finally, the result of
the operation is mapped back to a Response representation and returned to the
caller.

### src/Domain

This layer contains all application and business logic. It is divided into
business domains (we use the plural form, e.g. `src/Domain/Payments` rather than
`src/Domain/Payment`). What group of code constitutes a domain is a judgement
call from developers. Each domain should be fully isolated from other layers
(Apps / Infra) and from every other domain, communicating to the outside only
through interfaces and DTO's. All application use cases should be implemented in
this layer.

### src/Infra

This layer handles communication from Waterfront to the outside world. The Infra
dir is subdivided by each external service to communicate with. Since Domain
code needs to make calls to the outside but isn’t allowed to depend on the Infra
layer, all clients in the Infra layer code should implement abstract interfaces
from the Domain layer so that they can be Dependency Injected into Domain code.

### src/Support

This layer contains support code that affects the entire application or is used
across multiple groups. Support code that is only used with a single module can
be kept inside that module. So e.g. a utility function for working with some
data structure that is used within src/Domain/Hosting and src/Domain/DNS can go
in the Support layer, since these two domains are not allowed to depend directly
on each other. And code that is required to work with the framework but doesn’t
fit in one of the other layers, like e.g. some service providers, can go into
this layer as well.

## Tests
The `tests/` dir contains all automated tests for the production code, including
unit and integration tests. We used to subdivide the unit and integration tests,
but this proved inconsistent and not worth our trouble. The dir structure within
`tests/` should mirror the structure within `src/` exactly.

## Subdirectory structure
The structure of the subdirs within each application in `src/Apps`, domain in
`src/Domain`, client in `src/Infra` or group in `src/Support` should be the
same. Classes within each of these subdirs are further subdivided by their role,
like Provider, Repository or Action. Often this role is used in the class name
as well, to ensure the class is easy to find directly without browsing and to
prevent class name clashes. For the directory name we use the plural form of the
role.

> E.g. the Products domain may contain a subdir Repositories which contains a
> class ProductRepository as well as a ProductPriceRepository.

There is no pre-defined list of roles for classes, but developers are advised to
look at existing roles rather than trying to come up with a new role themselves.

Every class within a module should be organized by role. I.e. the root of the
directory should contain no classes.

## FAQ
### I’m seeing lots of code that doesn’t match these standards?
Correct: this standard was decided on after much of Waterfront was already built
and lots of code still needs to refactored or moved to conform to these
standards. When working on code that doesn’t meet the standard you are
encouraged to first refactor the code in a separate MR before proceeding with
new changes, however this is not mandatory.

### My code doesn’t fit anywhere in this structure, where should it go?
There’s four layers: going from the outside in, internal processing, going from
the inside out, and everything else. There shouldn’t be any code that cannot fit
within this structure. If you believe your code can’t fit, please consult your
colleagues on the #waterfront channel.
