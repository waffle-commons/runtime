[![PHP Version Require](http://poser.pugx.org/waffle-commons/runtime/require/php)](https://packagist.org/packages/waffle-commons/runtime)
[![PHP CI](https://github.com/waffle-commons/runtime/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/runtime/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/runtime/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/runtime)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/runtime/v)](https://packagist.org/packages/waffle-commons/runtime)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/runtime/v/unstable)](https://packagist.org/packages/waffle-commons/runtime)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/runtime.svg)](https://packagist.org/packages/waffle-commons/runtime)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/runtime)](https://github.com/waffle-commons/runtime/blob/main/LICENSE.md)

Waffle Runtime Component
========================

The "Glue" of the Waffle Framework. It bootstraps the application by connecting the HTTP layer, the Container, and the Core Kernel.

## 📦 Installation

```bash
composer require waffle-commons/runtime
```

## 🚀 Usage

The Runtime is typically used in your application's entry point (`public/index.php`).

```php
use Waffle\Commons\Runtime\WaffleRuntime;
use App\Kernel;

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Instantiate your Application Kernel
$kernel = new Kernel();

// 2. Instantiate the Runtime
$runtime = new WaffleRuntime();

// 3. Run the application
// This handles the request, executes the kernel, and emits the response.
$runtime->run($kernel);
```

The **Runtime** component is the orchestrator of the Waffle Framework. It acts as the "glue" between the low-level components (HTTP, Container) and the framework Core (Kernel).

Its primary responsibility is to manage the request lifecycle: creating the request from global state, booting the kernel with the necessary dependencies, handling the request, and emitting the response to the client.

Features
--------

*   **Lifecycle Orchestration:** Manages the full flow from Request creation to Response emission.

*   **Dependency Injection Integration:** Automatically instantiates and injects the PSR-11 Container implementation into the Kernel.

*   **PSR-7 / PSR-17 Integration:** Uses the HTTP component factories to create standard ServerRequest objects.

*   **Response Emission:** Uses the ResponseEmitter to send headers and body content to the output buffer.


Installation
------------

You can install the package via Composer. Note that this package typically requires the core and other components.

```shell
composer require waffle-commons/runtime
```

Usage
-----

The Runtime is designed to be used in your application's entry point (usually `public/index.php`).

### 1\. Standard Usage

In your `index.php` file, you simply need to instantiate your Kernel and the Runtime, then call `run()`.

```php
<?php

declare(strict_types=1);

use Waffle\Commons\Runtime\WaffleRuntime;
use App\Kernel; // Your application's Kernel extending Waffle\Kernel

require_once __DIR__ . '/../vendor/autoload.php';

// Define the application root constant
define('APP_ROOT', dirname(__DIR__));

// Instantiate the Kernel
$kernel = new Kernel();

// Instantiate the Runtime
$runtime = new WaffleRuntime();

// Run the application
// This will:
// 1. Initialize the DI Container
// 2. Create a PSR-7 Request from globals
// 3. Boot and Configure the Kernel
// 4. Dispatch the Request to the Router/Controller
// 5. Emit the final Response to the browser
$runtime->run($kernel);
```

Architecture
------------

The Runtime component decouples the Core from specific implementations of "infrastructure" concerns.

*   **Input:** It uses `Waffle\Commons\Http\Factory\GlobalsFactory` to create a ServerRequestInterface.

*   **Processing:** It delegates the business logic to `KernelInterface::handle($request)`.

*   **Dependencies:** It provides `Waffle\Commons\Container\Container` as the PSR-11 implementation for the Kernel.

*   **Output:** It uses `Waffle\Commons\Http\Emitter\ResponseEmitter` to send the ResponseInterface.


This design allows the Core Kernel to remain agnostic of how requests are created or sent, making it easier to test or run in different contexts (like CLI or long-running processes).

Testing
-------

This component is tested via integration tests within the Waffle Workspace.

Contributing
------------

Contributions are welcome! Please refer to [CONTRIBUTING.md](./CONTRIBUTING.md) for details.

License
-------

This project is licensed under the MIT License. See the [LICENSE.md](./LICENSE.md) file for details.
