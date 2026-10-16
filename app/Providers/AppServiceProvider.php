<?php

namespace App\Providers;

use Google\Cloud\Storage\StorageClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Storage::extend('gcs', function ($app, array $config) {
            $client = new StorageClient(array_filter([
                'projectId' => $config['project_id'] ?? null,
                'keyFile' => self::gcsCredentials($config['credentials'] ?? null),
            ]));

            // Los buckets de Firebase usan acceso uniforme: no se permiten ACL por archivo
            $adapter = new GoogleCloudStorageAdapter(
                $client->bucket($config['bucket']),
                $config['prefix'] ?? '',
                new UniformBucketLevelAccessVisibility(),
            );

            $disk = new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
            $disk->buildTemporaryUrlsUsing(
                fn ($path, $expiration, $options) => $adapter->temporaryUrl($path, $expiration, new Config($options))
            );

            return $disk;
        });
    }

    /** Acepta el JSON de la cuenta de servicio, el JSON en base64 o la ruta al archivo. */
    private static function gcsCredentials(?string $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value);
        $json = match (true) {
            str_starts_with($value, '{') => $value,
            is_file($value) => file_get_contents($value),
            default => base64_decode($value, true),
        };

        $credentials = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($credentials)) {
            throw new RuntimeException('FIREBASE_CREDENTIALS no contiene un JSON de cuenta de servicio válido.');
        }

        return $credentials;
    }
}
