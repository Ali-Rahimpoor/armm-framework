<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use ARMM\Auth\Auth;
use ARMM\Container\Container;
use ARMM\Http\JsonResponse;
use ARMM\Http\Request;
use ARMM\Middleware\AuthMiddleware;
use ARMM\Middleware\CorsMiddleware;
use ARMM\Routing\Router;

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [OK] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

// --- Test double controller (simulates a real API controller) ---
final class FakeProjectController
{
    public function index(Request $request)
    {
        return JsonResponse::success(['projects' => ['A', 'B']], 'لیست پروژه‌ها');
    }

    public function show(Request $request)
    {
        $id = $request->routeParam('id');
        return JsonResponse::success(['id' => $id], 'جزئیات پروژه');
    }

    public function store(Request $request)
    {
        $title = $request->input('title');
        return JsonResponse::created(['title' => $title]);
    }
}

echo "== Test group 1: Public GET route (no middleware) ==\n";
{
    $container = new Container();
    $router = new Router($container);
    $router->get('/projects', [FakeProjectController::class, 'index']);

    $request = Request::create('GET', '/projects');
    $response = $router->dispatch($request);

    $data = json_decode($response->body(), true);

    check('status code is 200', $response->statusCode() === 200);
    check('success is true', $data['success'] === true);
    check('data contains projects', $data['data']['projects'] === ['A', 'B']);
}

echo "\n== Test group 2: Route parameter extraction ({id}) ==\n";
{
    $container = new Container();
    $router = new Router($container);
    $router->get('/projects/{id}', [FakeProjectController::class, 'show']);

    $request = Request::create('GET', '/projects/42');
    $response = $router->dispatch($request);
    $data = json_decode($response->body(), true);

    check('status code is 200', $response->statusCode() === 200);
    check('extracted id param is "42"', $data['data']['id'] === '42');
}

echo "\n== Test group 3: JSON body parsing via input() ==\n";
{
    $container = new Container();
    $router = new Router($container);
    $router->post('/projects', [FakeProjectController::class, 'store']);

    $request = Request::create('POST', '/projects', body: ['title' => 'ARMM Framework']);
    $response = $router->dispatch($request);
    $data = json_decode($response->body(), true);

    check('status code is 201 (created)', $response->statusCode() === 201);
    check('title echoed back correctly', $data['data']['title'] === 'ARMM Framework');
}

echo "\n== Test group 4: AuthMiddleware blocks unauthenticated requests ==\n";
{
    // Ensure no session state leaks from a previous test
    $_SESSION = [];

    $container = new Container();
    $router = new Router($container);
    $router->post('/projects', [FakeProjectController::class, 'store'])
        ->middleware(AuthMiddleware::class);

    $request = Request::create('POST', '/projects', body: ['title' => 'Should not pass']);
    $response = $router->dispatch($request);
    $data = json_decode($response->body(), true);

    check('status code is 401', $response->statusCode() === 401);
    check('success is false', $data['success'] === false);
}

echo "\n== Test group 5: AuthMiddleware allows authenticated requests ==\n";
{
    $_SESSION = ['user_id' => 7];

    $container = new Container();
    $router = new Router($container);
    $router->post('/projects', [FakeProjectController::class, 'store'])
        ->middleware(AuthMiddleware::class);

    $request = Request::create('POST', '/projects', body: ['title' => 'Now it passes']);
    $response = $router->dispatch($request);
    $data = json_decode($response->body(), true);

    check('status code is 201', $response->statusCode() === 201);
    check('title passed through', $data['data']['title'] === 'Now it passes');

    $_SESSION = [];
}

echo "\n== Test group 6: 404 for unmatched route ==\n";
{
    $container = new Container();
    $router = new Router($container);
    $router->get('/projects', [FakeProjectController::class, 'index']);

    $request = Request::create('GET', '/does-not-exist');
    $response = $router->dispatch($request);

    check('status code is 404', $response->statusCode() === 404);
}

echo "\n== Test group 7: Container auto-wiring resolves nested dependencies ==\n";
{
    interface FakeRepoInterface {}

    final class FakeRepo implements FakeRepoInterface {
        public string $marker = 'repo-instance';
    }

    final class FakeService {
        public function __construct(public FakeRepo $repo) {}
    }

    final class FakeController {
        public function __construct(public FakeService $service) {}
    }

    $container = new Container();
    $controller = $container->make(FakeController::class);

    check('auto-wired FakeController built', $controller instanceof FakeController);
    check('nested FakeService resolved', $controller->service instanceof FakeService);
    check('deeply nested FakeRepo resolved', $controller->service->repo->marker === 'repo-instance');
}

echo "\n== Test group 8: Container singleton returns same instance ==\n";
{
    final class CounterService {
        public static int $constructedCount = 0;
        public function __construct() { self::$constructedCount++; }
    }

    $container = new Container();
    $container->singleton(CounterService::class, fn () => new CounterService());

    $first = $container->make(CounterService::class);
    $second = $container->make(CounterService::class);

    check('singleton returns identical instance', $first === $second);
    check('constructor only ran once', CounterService::$constructedCount === 1);
}

echo "\n== Test group 9: Route groups apply shared middleware ==\n";
{
    $_SESSION = [];

    $container = new Container();
    $router = new Router($container);

    $router->group([AuthMiddleware::class], function ($r) {
        $r->post('/admin/projects', [FakeProjectController::class, 'store']);
    });

    $request = Request::create('POST', '/admin/projects', body: ['title' => 'blocked']);
    $response = $router->dispatch($request);

    check('grouped route inherits middleware (401 without login)', $response->statusCode() === 401);
}

echo "\n== Test group 10: CorsMiddleware adds headers only for whitelisted origin ==\n";
{
    $container = new Container();
    $router = new Router($container);
    $container->instance(CorsMiddleware::class, new CorsMiddleware(['https://example.com']));

    $router->get('/projects', [FakeProjectController::class, 'index'])
        ->middleware(CorsMiddleware::class);

    $allowedRequest = Request::create('GET', '/projects', headers: ['ORIGIN' => 'https://example.com']);
    $allowedResponse = $router->dispatch($allowedRequest);

    $blockedRequest = Request::create('GET', '/projects', headers: ['ORIGIN' => 'https://evil.com']);
    $blockedResponse = $router->dispatch($blockedRequest);

    check('allowed origin gets CORS header', ($allowedResponse->headers()['Access-Control-Allow-Origin'] ?? null) === 'https://example.com');
    check('disallowed origin gets no CORS header', !isset($blockedResponse->headers()['Access-Control-Allow-Origin']));
}

echo "\n== Test group 11: Container::has() reflects explicit bindings only ==\n";
{
    $container = new Container();

    check('has() is false for an unregistered abstract', $container->has(CorsMiddleware::class) === false);

    $container->bind(CorsMiddleware::class, fn () => new CorsMiddleware(['https://example.com']));

    check('has() is true after bind()', $container->has(CorsMiddleware::class) === true);
}

echo "\n== Test group 12: Request::header() lookup is case-insensitive both ways ==\n";
{
    // شبیه‌سازی هدری که با حروف کوچک ساخته شده (مثلاً در یک تست دستی)
    $request = Request::create('GET', '/projects', headers: ['origin' => 'https://example.com']);

    check('header() finds a lowercase-registered header via uppercase lookup', $request->header('ORIGIN') === 'https://example.com');
    check('header() also finds it via lowercase lookup', $request->header('origin') === 'https://example.com');
}

echo "\n== Test group 13: Route::compile() caches its result (same regex on repeated calls) ==\n";
{
    $route = new \ARMM\Routing\Route('GET', '/projects/{id}', fn () => null);

    [$regexFirst, $paramsFirst] = $route->compile();
    [$regexSecond, $paramsSecond] = $route->compile();

    check('compiled regex is identical across calls', $regexFirst === $regexSecond);
    check('compiled param names are identical across calls', $paramsFirst === $paramsSecond);
}

echo "\n=====================================\n";
echo "Passed: {$passed}, Failed: {$failed}\n";
echo "=====================================\n";

exit($failed > 0 ? 1 : 0);
