<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogJsonTraffic
{
    public function handle(Request $request, Closure $next)
    {
        // Only log API routes (adjust if your API prefix differs)
        $isApi = Str::startsWith($request->path(), 'api/');

        // Optional: only log JSON requests OR requests expecting JSON
        $wantsJson = $request->expectsJson() || $request->isJson();

        if ($isApi && $wantsJson) {
            \Log::info('➡️ API Request', [
                'method'  => $request->method(),
                'url'     => $request->fullUrl(),
                'ip'      => $request->ip(),
                'user_id' => optional($request->user())->id,
                'headers' => $this->safeHeaders($request->headers->all()),
                'payload' => $request->all(), // parsed JSON/body
            ]);
        }

        $response = $next($request);

        if ($isApi && $wantsJson) {
            $contentType = $response->headers->get('Content-Type', '');

            // Only log JSON responses
            if (str_contains($contentType, 'application/json')) {
                $raw = $response->getContent();

                \Log::info('⬅️ API Response', [
                    'method'  => $request->method(),
                    'url'     => $request->fullUrl(),
                    'status'  => $response->getStatusCode(),
                    // Try decode; if not JSON, store first part to avoid huge spam
                    'json'    => $this->tryJsonDecode($raw),
                ]);
            } else {
                \Log::info('⬅️ API Response (non-JSON)', [
                    'method' => $request->method(),
                    'url'    => $request->fullUrl(),
                    'status' => $response->getStatusCode(),
                    'type'   => $contentType,
                ]);
            }
        }

        return $response;
    }

    private function tryJsonDecode(?string $raw)
    {
        if ($raw === null || $raw === '') return null;

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // If it's not valid JSON, log a snippet (helps catch HTML/errors)
        return [
            '__not_json__' => true,
            'error'        => json_last_error_msg(),
            'snippet'      => mb_substr($raw, 0, 2000),
        ];
    }

    private function safeHeaders(array $headers): array
    {
        // Remove secrets from logs
        $deny = ['authorization', 'cookie', 'x-csrf-token'];

        foreach ($deny as $key) {
            if (isset($headers[$key])) unset($headers[$key]);
        }

        return $headers;
    }
}
