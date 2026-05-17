<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Services\ActivityLogService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogPageVisits
{
    private const DEDUPE_HOURS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request, $response)) {
            return $response;
        }

        if ($this->wasRecentlyLogged($request)) {
            return $response;
        }

        app(ActivityLogService::class)->log(
            'pages',
            'page_opened',
            'Page opened: '.$this->pageLabel($request),
            $this->studentId($request),
            $request->user()?->id,
            null,
            [
                'route' => $request->route()?->getName(),
                'path' => '/'.ltrim($request->path(), '/'),
                'query' => $request->query(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => str((string) $request->userAgent())->limit(255, '')->toString(),
            ],
        );

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! $request->user() || ! $request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return false;
        }

        $routeName = (string) $request->route()?->getName();

        if ($routeName === '' || str_contains($routeName, 'attachment') || str_contains($routeName, 'download')) {
            return false;
        }

        $contentType = (string) $response->headers->get('content-type', '');

        return str_contains($contentType, 'text/html') || $request->headers->has('X-Inertia');
    }

    private function wasRecentlyLogged(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();
        $path = '/'.ltrim($request->path(), '/');
        $query = $request->query();

        return ActivityLog::query()
            ->where('category', 'pages')
            ->where('action', 'page_opened')
            ->where('actor_user_id', $request->user()?->id)
            ->where('occurred_at', '>=', now()->subHours(self::DEDUPE_HOURS))
            ->where('metadata->route', $routeName)
            ->where('metadata->path', $path)
            ->get()
            ->contains(function (ActivityLog $log) use ($query) {
                return ($log->metadata['query'] ?? []) == $query;
            });
    }

    private function studentId(Request $request): ?int
    {
        if ($request->user()?->student?->id) {
            return $request->user()->student->id;
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Student) {
                return $parameter->id;
            }

            if ($parameter instanceof Model && isset($parameter->student_id)) {
                return (int) $parameter->student_id;
            }
        }

        return null;
    }

    private function pageLabel(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();

        return $routeName !== ''
            ? str($routeName)->replace('.', ' ')->title()->toString()
            : '/'.ltrim($request->path(), '/');
    }
}
