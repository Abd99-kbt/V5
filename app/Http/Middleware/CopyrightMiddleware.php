<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\WatermarkService;
use Symfony\Component\HttpFoundation\Response;

class CopyrightMiddleware
{
    protected $watermarkService;

    public function __construct(WatermarkService $watermarkService)
    {
        $this->watermarkService = $watermarkService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $response = $next($request);

        // Only add watermarks to HTML responses
        if ($response instanceof \Illuminate\Http\Response &&
            $response->headers->get('Content-Type') &&
            str_contains($response->headers->get('Content-Type'), 'text/html')) {

            $content = $response->getContent();

            // Add license-based watermark
            $licenseType = $this->getCurrentLicenseType();
            $content = $this->watermarkService->addLicenseWatermark($content, $licenseType);

            // Add copyright headers
            $response->headers->set('X-Copyright', config('license.company_name', 'Your Company') . ' © ' . date('Y'));
            $response->headers->set('X-License-Type', $licenseType);
            $response->headers->set('X-Protected-By', 'Advanced License Protection System');

            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Get current license type
     */
    protected function getCurrentLicenseType()
    {
        $licenseInfo = request()->get('license_info');

        if ($licenseInfo && isset($licenseInfo['license'])) {
            return $licenseInfo['license']['license_type'] ?? 'trial';
        }

        return 'trial'; // Default to trial
    }
}