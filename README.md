[![PHP Version Require](http://poser.pugx.org/waffle-commons/runtime/require/php)](https://packagist.org/packages/waffle-commons/runtime)
[![PHP CI](https://github.com/waffle-commons/runtime/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/runtime/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/runtime/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/runtime)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/runtime/v)](https://packagist.org/packages/waffle-commons/runtime)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/runtime/v/unstable)](https://packagist.org/packages/waffle-commons/runtime)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/runtime.svg)](https://packagist.org/packages/waffle-commons/runtime)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/runtime)](https://github.com/waffle-commons/runtime/blob/main/LICENSE.md)

Waffle Runtime Component
========================

The **Waffle Runtime** is the agnostic orchestration layer of the Waffle framework. It is responsible for gluing the **Kernel**, **Request**, and **Response Emitter** together to execute the application lifecycle.

## 📦 Installation

```bash
composer require waffle-commons/runtime
```

## 🚀 Usage

The Runtime is typically used in your application's entry point (`public/index.php`).

It requires a fully configured `KernelInterface`, a `ServerRequestInterface`, and a `ResponseEmitterInterface`.

### Example (`public/index.php`)

```php
<?php

declare(strict_types=1);

use Waffle\Commons\Config\Config;
use Waffle\Commons\Container\Container;
use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\GlobalsFactory;
use Waffle\Commons\Runtime\WaffleRuntime;
use Waffle\Commons\Security\Security;
use App\Kernel; // Your application Kernel

require_once __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT', dirname(__DIR__));

// 1. Setup Infrastructure Dependencies
// ------------------------------------
// Create the Config (pointing to your config directory)
$config = new Config(
    configDir: APP_ROOT . '/config',
    environment: getenv('APP_ENV') ?: 'prod'
);

// Create the Security implementation
$security = new Security($config);

// Create the DI Container
$container = new Container();

// 2. Setup the Kernel
// -------------------
$kernel = new Kernel();

// Inject dependencies into the Kernel
// (The Kernel needs these to boot and configure the system)
$kernel->setConfiguration($config);
$kernel->setSecurity($security);
$kernel->setContainerImplementation($container);

// 3. Create Request & Emitter
// ---------------------------
$requestFactory = new GlobalsFactory();
$request = $requestFactory->createServerRequestFromGlobals();

$emitter = new ResponseEmitter();

// 4. Instantiate the Runtime
// --------------------------
$runtime = new WaffleRuntime();

// 5. Run the Application
// ----------------------
// The Runtime orchestrates the flow:
// Request -> Kernel -> Response -> Emitter
$runtime->run($kernel, $request, $emitter);
```

## Features

*   **Agnostic Execution**: The Runtime doesn't know about your controllers or business logic. It only deals with PSR interfaces.
*   **Decoupled Architecture**: Forces a clean separation between the Application (Kernel), the Input (Request), and the Output (Emitter).
*   **PSR-7 & PSR-15 Compliant**: Built on top of standard HTTP message interfaces.

## Testing

To run the tests, use the following command:

```bash
composer tests
```

## Contributing

Contributions are welcome! Please refer to [CONTRIBUTING.md](./CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License. See the [LICENSE.md](./LICENSE.md) file for details.
