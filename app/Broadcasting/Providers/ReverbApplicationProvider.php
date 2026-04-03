<?php

namespace App\Broadcasting\Providers;

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Application;

class ReverbApplicationProvider implements ApplicationProvider
{
    /**
     * Get all of the applications.
     *
     * @return array
     */
    public function all(): array
    {
        return [
            $this->findByKey(config('broadcasting.connections.reverb.key'))
        ];
    }

    /**
     * Find an application by its ID.
     *
     * @param  string  $id
     * @return \Laravel\Reverb\Application|null
     */
    public function findById(string $id): ?Application
    {
        if ($id === config('broadcasting.connections.reverb.app_id')) {
            return $this->findByKey(config('broadcasting.connections.reverb.key'));
        }

        return null;
    }

    /**
     * Find an application by its key.
     *
     * @param  string  $key
     * @return \Laravel\Reverb\Application|null
     */
    public function findByKey(string $key): ?Application
    {
        if ($key === config('broadcasting.connections.reverb.key')) {
            return new Application(
                config('broadcasting.connections.reverb.app_id'),
                config('broadcasting.connections.reverb.key'),
                config('broadcasting.connections.reverb.secret'),
                config('broadcasting.connections.reverb.options', [])
            );
        }

        return null;
    }
}
