<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sends an uploaded document that lives outside the public directory.
 *
 * Callers authorize access to the owning entity first and pass the filename
 * stored on that entity; nothing from the request is used to locate the file.
 */
final class StoredFileResponder
{
    private const INLINE_SAFE_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ];

    public function attachment(string $directory, ?string $filename): BinaryFileResponse
    {
        if ($filename === null || $filename === '' || $filename !== basename($filename) || str_starts_with($filename, '.')) {
            throw new NotFoundHttpException();
        }

        $root = realpath($directory);
        $path = realpath($directory.DIRECTORY_SEPARATOR.$filename);
        if ($root === false || $path === false || !is_file($path) || dirname($path) !== $root) {
            throw new NotFoundHttpException();
        }

        $mimeType = (new File($path))->getMimeType();
        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => \in_array($mimeType, self::INLINE_SAFE_TYPES, true) ? $mimeType : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download',
        );

        return $response;
    }
}
