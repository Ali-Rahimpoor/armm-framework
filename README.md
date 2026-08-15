# ARMM — Agile REST Micro Module

A lightweight PHP framework designed for building REST APIs. `ARMM` provides routing, dependency injection, middleware, authentication, and database connectivity, allowing you to focus directly on your application's business logic.

## Features

* **Regex-based Routing** with support for parameters (`/projects/{id}`) and route groups
* **Dependency Injection Container** with auto-wiring through Reflection — no need to manually construct dependency chains
* **Middleware Pipeline** for authentication, CORS, and other shared application logic
* **Request/Response Objects** that provide unified handling for JSON and traditional form requests
* **JsonResponse** with a consistent response format for success and error responses
* **HttpException** for throwing meaningful HTTP errors from any layer of the application
* **Session-based Authentication**, suitable for applications with a limited number of users, such as personal admin panels
* **Image Upload Handling** with real MIME-type validation, unique file naming, configurable storage path, and automatic thumbnail generation via GD
* **Explicit Config Errors** when accessing missing configuration keys instead of silently returning `null`
* **Simple Logger** for recording errors and application events

## Installation

```bash
composer require ali-rahimpoor/armm-framework
```

Or, until the package is published on Packagist, install it directly from GitHub:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/your-username/armm-framework" }
    ],
    "require": {
        "armm/framework": "dev-main"
    }
}
```

## Quick Start

```php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

use ARMM\Application;

$app = new Application(basePath: dirname(__DIR__));
$app->loadRoutes(__DIR__ . '/../routes/api.php');
$app->run();
```

```php
// routes/api.php
use App\Controllers\ProjectController;
use ARMM\Middleware\AuthMiddleware;

$router->get('/projects', [ProjectController::class, 'index']);
$router->get('/projects/{id}', [ProjectController::class, 'show']);

$router->group([AuthMiddleware::class], function ($admin) {
    $admin->post('/projects', [ProjectController::class, 'store']);
    $admin->put('/projects/{id}', [ProjectController::class, 'update']);
    $admin->delete('/projects/{id}', [ProjectController::class, 'destroy']);
});
```

```php
// app/Controllers/ProjectController.php
use ARMM\Exceptions\HttpException;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;

final class ProjectController
{
    public function __construct(private ProjectService $service) {} // The Container resolves it automatically

    public function index(Request $request): JsonResponse
    {
        return JsonResponse::success($this->service->getAll());
    }

    public function show(Request $request): JsonResponse
    {
        $project = $this->service->find((int) $request->routeParam('id'));

        if (!$project) {
            throw HttpException::notFound('Project not found');
        }

        return JsonResponse::success($project);
    }
}
```

A complete, runnable example is available in `examples/mini-api`.

## Configuration

Files under `config/*.php` must return an associative array. The filename becomes the configuration group name:

```php
// config/app.php
return [
    'timezone' => 'Asia/Tehran',
    'cors_allowed_origins' => ['http://localhost:3000'],
];
```

```php
$app->config()->get('app', 'timezone');       // 'Asia/Tehran', or throws an Exception if the key is missing
$app->config()->getOr('app', 'debug', false);  // Returns the default value if the key is missing
```

### CORS

If you define the `cors_allowed_origins` key in `config/app.php`, `Application::boot()` automatically wires the `CorsMiddleware` with the configured origins — no manual binding is required.

You only need to add this middleware to the routes that should be accessible from your frontend:

```php
$router->get('/projects', [ProjectController::class, 'index'])
    ->middleware(CorsMiddleware::class);
```

If you want to control the default behavior yourself, such as `allowedMethods` or `allowedHeaders`, you can explicitly override the binding before `$app->run()`:

```php
$app->container()->bind(CorsMiddleware::class, function ($c) {
    return new CorsMiddleware(
        allowedOrigins: ['https://example.com'],
        allowedMethods: ['GET', 'POST'],
    );
});
```

### File Uploads

ARMM ships with an `ARMM\Storage` namespace for handling image uploads: `UploadedFile` (a typed wrapper around a raw `$_FILES` entry), `FileValidator` (real MIME-type and size validation), `ImageProcessor` (resize/thumbnail via GD), and `FileStorage` (unique naming, configurable disk path, and public URL resolution). `FileValidator` and `FileStorage` are auto-wired by the Container, so you only need to type-hint them in your controller's constructor.

Images must always be sent as `multipart/form-data`, separate from any JSON body:

```js
const form = new FormData();
form.append('image', fileInput.files[0]);
fetch('/products/images', { method: 'POST', body: form });
```

```php
// app/Controllers/ImageUploadController.php
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;
use ARMM\Storage\FileStorage;
use ARMM\Storage\FileValidator;

final class ImageUploadController
{
    public function __construct(
        private FileValidator $validator,
        private FileStorage $storage
    ) {}

    public function upload(Request $request): JsonResponse
    {
        $image = $request->uploadedFile('image');

        $this->validator->validate($image); // throws HttpException::validation(422) if invalid

        $result = $this->storage->store($image, subdirectory: 'products', thumbnailSize: 200);

        return JsonResponse::created([
            'path' => $result['path'],
            'url' => $result['url'],
            'thumbnail_url' => $result['thumbnail_url'],
        ]);
    }
}
```

Storage rules are configurable per project via `config/storage.php`; any key you omit falls back to a sensible default:

```php
// config/storage.php
return [
    'upload_path' => __DIR__ . '/../public/uploads', // default: "public/uploads"
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    'max_size_bytes' => 5 * 1024 * 1024, // 5MB
];
```

By default, files are stored under `public/uploads`, so `store()` returns a `url` you can use directly (e.g. `<img src="...">`). Setting `upload_path` outside of `public/` returns `null` for `url` instead, since those files should be served through a dedicated route with your own access control.

A complete working example is available in `examples/mini-api/app/Controllers/ImageUploadController.php`.

## Core Architecture

| Path                  | Responsibility                                                |
| --------------------- | ------------------------------------------------------------- |
| `src/Routing/`        | Route definition, registration, and matching                  |
| `src/Http/`           | Request, Response, and JsonResponse                           |
| `src/Middleware/`     | Middleware contract and Auth/CORS implementations             |
| `src/Container/`      | Dependency Injection Container with auto-wiring               |
| `src/Database/`       | Singleton PDO connection                                      |
| `src/Config/`         | Configuration loading and access                              |
| `src/Auth/`           | Session-based authentication                                  |
| `src/Storage/`        | Image upload validation, storage, and thumbnail generation    |
| `src/Logging/`        | File-based error and event logging                            |
| `src/Exceptions/`     | HttpException for meaningful HTTP errors                      |
| `src/Application.php` | Central application entry point that ties everything together |

For an explanation of the architectural decisions — such as why the Container, Middleware, and explicit configuration errors are used — refer to the comments at the top of each class. Each class documents the reasoning behind its design.

## Testing

```bash
php tests/manual_e2e_test.php
```

This script tests the complete Router → Middleware → Container → Response lifecycle using 20 real-world scenarios, without requiring a separate testing framework.

## Versioning and Publishing to Packagist

1. Finalize `composer.json` (name, description, license, etc.)

2. Create a version tag:

   ```bash
   git tag v1.0.0 && git push --tags
   ```

3. Sign up at [packagist.org](https://packagist.org) and submit your GitHub repository

4. For future releases, simply create a new version tag; Packagist will detect the new release automatically

## License

MIT
