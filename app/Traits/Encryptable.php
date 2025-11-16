<?php

namespace App\Traits;

use App\Services\DatabaseEncryptionService;

trait Encryptable
{
    /**
     * The attributes that should be encrypted.
     */
    protected $encryptable = [];

    /**
     * Boot the encryptable trait for a model.
     */
    public static function bootEncryptable()
    {
        static::retrieved(function ($model) {
            $model->decryptAttributes();
        });

        static::saving(function ($model) {
            $model->encryptAttributes();
        });
    }

    /**
     * Get the encryptable attributes for the model.
     */
    public function getEncryptableAttributes()
    {
        return $this->encryptable ?? [];
    }

    /**
     * Encrypt the encryptable attributes.
     */
    public function encryptAttributes()
    {
        if (!config('license.database_encryption.enabled', false)) {
            return;
        }

        $encryptable = $this->getEncryptableAttributes();
        $encryptionService = app(DatabaseEncryptionService::class);

        foreach ($encryptable as $attribute) {
            if ($this->isDirty($attribute) && $this->getOriginal($attribute) !== $this->$attribute) {
                $this->$attribute = $encryptionService->encrypt($this->$attribute);
            }
        }
    }

    /**
     * Decrypt the encryptable attributes.
     */
    public function decryptAttributes()
    {
        if (!config('license.database_encryption.enabled', false)) {
            return;
        }

        $encryptable = $this->getEncryptableAttributes();
        $encryptionService = app(DatabaseEncryptionService::class);

        foreach ($encryptable as $attribute) {
            if (!empty($this->$attribute)) {
                $this->$attribute = $encryptionService->decrypt($this->$attribute);
            }
        }
    }

    /**
     * Get an attribute from the model, decrypting if necessary.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->getEncryptableAttributes()) && !empty($value)) {
            $encryptionService = app(DatabaseEncryptionService::class);
            $value = $encryptionService->decrypt($value);
        }

        return $value;
    }

    /**
     * Set a given attribute on the model, encrypting if necessary.
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->getEncryptableAttributes()) && !empty($value)) {
            $encryptionService = app(DatabaseEncryptionService::class);
            $value = $encryptionService->encrypt($value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get the original attribute value, decrypting if necessary.
     */
    public function getOriginal($key = null, $default = null)
    {
        $original = parent::getOriginal($key, $default);

        if ($key && in_array($key, $this->getEncryptableAttributes()) && !empty($original)) {
            $encryptionService = app(DatabaseEncryptionService::class);
            $original = $encryptionService->decrypt($original);
        }

        return $original;
    }
}