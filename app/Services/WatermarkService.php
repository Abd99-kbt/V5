<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

class WatermarkService
{
    protected $watermarkText;
    protected $fontSize;
    protected $opacity;
    protected $color;
    protected $position;

    public function __construct()
    {
        $this->watermarkText = config('license.watermark.text', 'UNLICENSED COPY');
        $this->fontSize = config('license.watermark.font_size', 48);
        $this->opacity = config('license.watermark.opacity', 0.1);
        $this->color = config('license.watermark.color', '#FF0000');
        $this->position = config('license.watermark.position', 'center');
    }

    /**
     * Add watermark to images
     */
    public function addWatermarkToImages($directory = null)
    {
        if (!config('license.watermark.enabled', true)) {
            return ['success' => false, 'message' => 'Watermarking is disabled'];
        }

        $directory = $directory ?: public_path('storage');

        if (!File::exists($directory)) {
            return ['success' => false, 'message' => 'Directory not found'];
        }

        $images = $this->getImages($directory);
        $processed = 0;

        Log::info('Starting image watermarking', ['images_count' => count($images)]);

        foreach ($images as $image) {
            if ($this->addWatermarkToImage($image)) {
                $processed++;
            }
        }

        Log::info('Image watermarking completed', ['processed' => $processed]);
        return ['success' => true, 'images_processed' => $processed];
    }

    /**
     * Add watermark to a single image
     */
    public function addWatermarkToImage($imagePath)
    {
        try {
            $image = Image::make($imagePath);

            // Skip if already watermarked
            if ($this->hasWatermark($image)) {
                return false;
            }

            // Add text watermark
            $image->text($this->watermarkText, $this->getPositionX($image), $this->getPositionY($image), function ($font) {
                $font->size($this->fontSize);
                $font->color($this->color);
                $font->opacity($this->opacity);
                $font->align('center');
                $font->valign('center');
            });

            // Save the image
            $image->save($imagePath, 90); // 90% quality

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to watermark image', [
                'image' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if image already has watermark
     */
    protected function hasWatermark($image)
    {
        // Simple check - look for watermark text in image
        // This is a basic implementation
        try {
            // Convert to string and check for watermark text
            $imageString = (string) $image->encode('data-url');
            return str_contains($imageString, $this->watermarkText);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get X position for watermark
     */
    protected function getPositionX($image)
    {
        $width = $image->width();

        switch ($this->position) {
            case 'center':
            case 'tile':
                return $width / 2;
            case 'top-left':
            case 'bottom-left':
                return $this->fontSize;
            case 'top-right':
            case 'bottom-right':
                return $width - $this->fontSize;
            default:
                return $width / 2;
        }
    }

    /**
     * Get Y position for watermark
     */
    protected function getPositionY($image)
    {
        $height = $image->height();

        switch ($this->position) {
            case 'center':
                return $height / 2;
            case 'top-left':
            case 'top-right':
                return $this->fontSize;
            case 'bottom-left':
            case 'bottom-right':
                return $height - $this->fontSize;
            case 'tile':
                return $height / 2;
            default:
                return $height / 2;
        }
    }

    /**
     * Get all images in directory
     */
    protected function getImages($directory)
    {
        $images = [];
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, $extensions)) {
                $images[] = $file->getPathname();
            }
        }

        return $images;
    }

    /**
     * Add watermark to HTML content
     */
    public function addWatermarkToHtml($content)
    {
        if (!config('license.watermark.enabled', true)) {
            return $content;
        }

        $watermarkHtml = $this->generateHtmlWatermark();

        // Insert watermark before closing body tag
        if (preg_match('/<\/body>/i', $content)) {
            $content = preg_replace('/<\/body>/i', $watermarkHtml . '</body>', $content);
        } else {
            $content .= $watermarkHtml;
        }

        return $content;
    }

    /**
     * Generate HTML watermark
     */
    protected function generateHtmlWatermark()
    {
        $styles = "
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: {$this->fontSize}px;
            color: {$this->color};
            opacity: {$this->opacity};
            font-weight: bold;
            z-index: 9999;
            pointer-events: none;
            user-select: none;
            font-family: Arial, sans-serif;
        ";

        return "<div style=\"{$styles}\">{$this->watermarkText}</div>";
    }

    /**
     * Add watermark to PDF (placeholder - would need PDF library)
     */
    public function addWatermarkToPdf($pdfPath)
    {
        // This would require a PDF manipulation library like TCPDF or FPDI
        // For now, just log that PDF watermarking is not implemented
        Log::info('PDF watermarking requested but not implemented', ['pdf' => $pdfPath]);
        return false;
    }

    /**
     * Remove watermarks from images
     */
    public function removeWatermarks($directory = null)
    {
        $directory = $directory ?: public_path('storage');

        if (!File::exists($directory)) {
            return ['success' => false, 'message' => 'Directory not found'];
        }

        // This is complex - would need original images to restore
        // For now, just log the request
        Log::info('Watermark removal requested - not implemented', ['directory' => $directory]);

        return ['success' => false, 'message' => 'Watermark removal not implemented'];
    }

    /**
     * Add license-based watermark
     */
    public function addLicenseWatermark($content, $licenseType = 'trial')
    {
        $watermarkText = $this->getLicenseWatermarkText($licenseType);

        if ($licenseType === 'trial') {
            return $this->addTrialWatermark($content, $watermarkText);
        }

        return $this->addLicensedWatermark($content, $watermarkText);
    }

    /**
     * Get watermark text based on license type
     */
    protected function getLicenseWatermarkText($licenseType)
    {
        switch ($licenseType) {
            case 'trial':
                return 'TRIAL VERSION - ' . config('app.name');
            case 'basic':
                return 'BASIC LICENSE - ' . config('app.name');
            case 'professional':
                return 'PROFESSIONAL LICENSE - ' . config('app.name');
            case 'enterprise':
                return 'ENTERPRISE LICENSE - ' . config('app.name');
            default:
                return 'UNLICENSED - ' . config('app.name');
        }
    }

    /**
     * Add trial watermark
     */
    protected function addTrialWatermark($content, $text)
    {
        // Add more prominent trial watermark
        $styles = "
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 10000;
            font-size: 14px;
        ";

        $watermark = "<div style=\"{$styles}\">{$text}</div>";

        if (preg_match('/<\/body>/i', $content)) {
            $content = preg_replace('/<\/body>/i', $watermark . '</body>', $content);
        } else {
            $content .= $watermark;
        }

        return $content;
    }

    /**
     * Add licensed watermark
     */
    protected function addLicensedWatermark($content, $text)
    {
        // Add subtle licensed watermark
        $styles = "
            position: fixed;
            bottom: 10px;
            right: 10px;
            color: rgba(0, 0, 0, 0.3);
            font-size: 10px;
            z-index: 10000;
            pointer-events: none;
        ";

        $watermark = "<div style=\"{$styles}\">{$text}</div>";

        if (preg_match('/<\/body>/i', $content)) {
            $content = preg_replace('/<\/body>/i', $watermark . '</body>', $content);
        } else {
            $content .= $watermark;
        }

        return $content;
    }
}