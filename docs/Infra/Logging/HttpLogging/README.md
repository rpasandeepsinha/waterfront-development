# Logging Middleware Integration Documentation

This documentation provides an overview of the Http logging middleware. It securely logs HTTP transactions—capturing requests, responses, and error states—by applying configurable masking rules. The Http logging middleware can be integrated with third-party API clients that support middleware or handler stacks (e.g. Guzzle).

---

## Overview
The logging middleware intercepts the HTTP request/response cycle, logging metadata such as HTTP methods, URIs, headers, and bodies. To protect sensitive data, the middleware leverages a series of maskers and configurable key lists to filter/mask fields containing sensitive data before logging.

---

## Architecture and Components

### 1. HttpLogMiddleware
Acts as the central component to intercept and log HTTP interactions. It performs the following:

- Reads the request and response bodies (when the streams are seekable).
-
- Applies data masking using the provided masker implementations.

- Logs both the start of a request and its corresponding response or error outcome.


**Key Methods:**
- **`logWithContextKeys()`**
    Configures the middleware by accepting:

    - A `LoggerInterface` instance for PSR-3 compliant logging.

    - A client identifier (`clientName`).

    - Implementations of `MaskerInterface`, `MaskKeysInterface` and `HeaderMaskerInterface`.

    - An optional log level (default: `'info'`).


    This method returns a callable that wraps an HTTP handler, enabling integration into any HTTP client's middleware pipeline.

- **`handleResponse()`**
    Processes a successful HTTP response:

    - Reads and rewinds the response body.

    - Applies the appropriate masking rules.

    - Logs response details including status code and masked payload.

- **`handleError()`**
    Manages error cases (e.g., `RequestException`):

    - Captures any available response content.

    - Applies masking and logs the error context.

    - Returns a rejected promise to propagate the error.


**Additional Details:**
- **Truncation:**
    A helper method `truncate()` limits logged data to a configurable maximum length to avoid excessive log sizes.


---

### 2. ClientFactory
Provides a central method for constructing HTTP clients with the logging middleware pre-attached.

- Uses a `HandlerStack` (with `CurlHandler` as the base) to manage the HTTP request lifecycle.

- The logging middleware is pushed onto the handler stack before client instantiation.

- Consumers can pass in additional configuration (e.g., `base_uri`, custom headers) via an associative array.


**Example:**

```php
$client = ClientFactory::create(
	['base_uri' => 'https://api.example.com'],
	'AnApiClient',
	$logger,
	$masker,
	$maskKeys,
	$headerMasker,
	'info'
	);
```

---

### 3. Service Providers

#### HttpLogServiceProvider
Registers default implementations for all logging-related interfaces into the IoC container. This service provider ensures that the middleware can resolve necessary dependencies even when custom implementations are not provided.

**Default Bindings Include:**

- **`MaskerInterface`**
    Binds to a default JSON masker (typically `JsonLogMasker`)

- **`MaskKeysInterface`**
    Binds to `MaskKeys` that return default arrays of keys to mask (e.g., `['password', 'token']` for responses).

- **`HeaderMaskerInterface`**
    Binds to `HeaderLogMasker`, which handles the masking of sensitive header values.

- **Middleware Factory Binding:**
    Exposes a factory that creates an instance of the logging middleware given the required parameters. Consumers are required to supply keys such as `clientName`, `masker`, and the key provider interfaces.


---

### 4. Concrete Maskers
The concrete masker implementations ensure sensitive information is properly removed from log data before it is recorded. Currently there is a concrete implementation for JSON bodies. The removed sensitive values are replaced with a safe placeholder ([Filtered]) based on a configurable list of keys.

#### JsonLogMasker
Implements `MaskerInterface` for JSON payloads.

- Validates JSON structure and logs warnings if the body is invalid.

-  Iterates through key/value pairs to replace sensitive values (as determined by `MaskKeysInterface`) with a `[Filtered]` placeholder.

-  Returns a JSON-encoded string after applying masking.

---

### 5. Interfaces

**MaskerInterface**
- **Methods:**
    - `mask(string $body, MaskKeys): string`


**MaskKeysInterface**
- **Method:**
    - `getMaskKeys(): array`
        Returns an array of keys (strings) that should be masked in bodies.

---

## Implementing the HttpLogMiddleware

Use the provided `ClientFactory` to create your HTTP client with the logging middleware attached. The factory method accepts the base configuration for your client, a client identifier for log messages, and all necessary dependencies. You can also directly push the middleware on an existing handler stack

### Example for a JSON-Based API Client

```php
use GuzzleHttp\Client;
use Waterfront\Infra\Logging\Factory\ClientFactory;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Psr\Log\LoggerInterface;
use Namespace\ConcreteMaskJsonKeys;

// Acquire or instantiate dependencies:
$logger = /* instance of a PSR-3 LoggerInterface */;
$masker = new JsonLogMasker($logger);
$maskJsonKeys = new ConcreteMaskJsonKeys();

// Define any additional client configuration (e.g. base URI, headers)
$baseConfig = [
	'base_uri' => 'https://api.thirdparty-json.com',
	'headers'  => ['Accept' => 'application/json'],
];

// Create the client with logging middleware:
$client = ClientFactory::create(
	baseConfig: $baseConfig,
	clientName: 'ThirdPartyJsonClient',
	logger: $logger,
	masker: $masker,
	maskJsonKeys: $maskJsonKeys,
	logLevel: 'info' // Optional log level
);
```

---

## 3. Using the Logging‑Enabled Client

Once instantiated, use your client as you normally would or inject it into a third party api client as a parameter. The logging middleware will automatically:

- Intercept and log each outgoing request and incoming response.

- Apply the masking rules to the JSON payloads.

- Truncate large request/response bodies beyond a specified limit (default is 1000 characters).


## Extending the Implementation

- **Custom Maskers:**
    Implement your own version of `MaskerInterface` if you need to support different data formats or specialized masking rules. For example, you could create a masker for YAML or other custom payload formats.

- **Custom Masking Keys:**
    Override `MaskKeysInterface` to adjust the list of sensitive keys.

- **Integration with Other HTTP Clients:**
    While the provided `ClientFactory` uses Guzzle, the middleware logic can be adapted for any HTTP client that supports request/response interception. Ensure that the middleware is invoked in the correct sequence and that the client’s configuration supports stream handling as required by the masking implementations.
