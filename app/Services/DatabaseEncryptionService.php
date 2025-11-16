<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

class DatabaseEncryptionService
{
    protected $encryptionKey;
    protected $algorithm = 'AES-256-CBC';
    protected $ivLength = 16;

    protected $encryptedFields = [
        'users' => ['email', 'password'],
        'customers' => ['email', 'phone', 'address'],
        'suppliers' => ['email', 'phone', 'address', 'bank_details'],
        'invoices' => ['customer_details'],
        'orders' => ['customer_info', 'shipping_address', 'billing_address'],
        'products' => ['description', 'specifications'],
        'licenses' => ['customer_email', 'customer_name'],
    ];

    public function __construct()
    {
        $this->encryptionKey = config('license.database_encryption.key', env('DB_ENCRYPTION_KEY', env('APP_KEY')));
    }

    /**
     * Encrypt sensitive data in the database
     */
    public function encryptDatabase()
    {
        if (!config('license.database_encryption.enabled', false)) {
            return ['success' => false, 'message' => 'Database encryption is disabled'];
        }

        Log::info('Starting database encryption');

        $results = [];

        foreach ($this->encryptedFields as $table => $fields) {
            if (Schema::hasTable($table)) {
                $result = $this->encryptTable($table, $fields);
                $results[$table] = $result;
            }
        }

        Log::info('Database encryption completed', $results);
        return ['success' => true, 'results' => $results];
    }

    /**
     * Decrypt sensitive data in the database
     */
    public function decryptDatabase()
    {
        if (!config('license.database_encryption.enabled', false)) {
            return ['success' => false, 'message' => 'Database encryption is disabled'];
        }

        Log::info('Starting database decryption');

        $results = [];

        foreach ($this->encryptedFields as $table => $fields) {
            if (Schema::hasTable($table)) {
                $result = $this->decryptTable($table, $fields);
                $results[$table] = $result;
            }
        }

        Log::info('Database decryption completed', $results);
        return ['success' => true, 'results' => $results];
    }

    /**
     * Encrypt a specific table
     */
    protected function encryptTable($table, $fields)
    {
        $records = DB::table($table)->get();
        $encrypted = 0;

        foreach ($records as $record) {
            $updateData = [];

            foreach ($fields as $field) {
                if (isset($record->$field) && !$this->isEncrypted($record->$field)) {
                    $updateData[$field] = $this->encrypt($record->$field);
                    $encrypted++;
                }
            }

            if (!empty($updateData)) {
                DB::table($table)->where('id', $record->id)->update($updateData);
            }
        }

        return ['records_processed' => $records->count(), 'fields_encrypted' => $encrypted];
    }

    /**
     * Decrypt a specific table
     */
    protected function decryptTable($table, $fields)
    {
        $records = DB::table($table)->get();
        $decrypted = 0;

        foreach ($records as $record) {
            $updateData = [];

            foreach ($fields as $field) {
                if (isset($record->$field) && $this->isEncrypted($record->$field)) {
                    $updateData[$field] = $this->decrypt($record->$field);
                    $decrypted++;
                }
            }

            if (!empty($updateData)) {
                DB::table($table)->where('id', $record->id)->update($updateData);
            }
        }

        return ['records_processed' => $records->count(), 'fields_decrypted' => $decrypted];
    }

    /**
     * Encrypt a string
     */
    public function encrypt($data)
    {
        if (empty($data) || $this->isEncrypted($data)) {
            return $data;
        }

        $iv = openssl_random_pseudo_bytes($this->ivLength);
        $encrypted = openssl_encrypt($data, $this->algorithm, $this->encryptionKey, 0, $iv);

        // Store IV with encrypted data
        return 'ENC:' . base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string
     */
    public function decrypt($data)
    {
        if (empty($data) || !$this->isEncrypted($data)) {
            return $data;
        }

        $data = substr($data, 4); // Remove 'ENC:' prefix
        $data = base64_decode($data);

        $iv = substr($data, 0, $this->ivLength);
        $encrypted = substr($data, $this->ivLength);

        return openssl_decrypt($encrypted, $this->algorithm, $this->encryptionKey, 0, $iv);
    }

    /**
     * Check if data is encrypted
     */
    protected function isEncrypted($data)
    {
        return is_string($data) && str_starts_with($data, 'ENC:');
    }

    /**
     * Encrypt a model instance
     */
    public function encryptModel(Model $model)
    {
        $table = $model->getTable();

        if (!isset($this->encryptedFields[$table])) {
            return;
        }

        $fields = $this->encryptedFields[$table];

        foreach ($fields as $field) {
            if ($model->isDirty($field) && !$this->isEncrypted($model->$field)) {
                $model->$field = $this->encrypt($model->$field);
            }
        }
    }

    /**
     * Decrypt a model instance
     */
    public function decryptModel(Model $model)
    {
        $table = $model->getTable();

        if (!isset($this->encryptedFields[$table])) {
            return;
        }

        $fields = $this->encryptedFields[$table];

        foreach ($fields as $field) {
            if ($this->isEncrypted($model->$field)) {
                $model->$field = $this->decrypt($model->$field);
            }
        }
    }

    /**
     * Create encrypted backup of database
     */
    public function createEncryptedBackup($filename = null)
    {
        $filename = $filename ?: 'db_backup_' . date('Y-m-d_H-i-s') . '.enc';

        $backupPath = storage_path('backups/' . $filename);

        // Get all tables
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

        $backupData = [
            'timestamp' => now(),
            'version' => '1.0',
            'tables' => []
        ];

        foreach ($tables as $table) {
            $records = DB::table($table)->get()->toArray();
            $backupData['tables'][$table] = $records;
        }

        // Encrypt the entire backup
        $jsonData = json_encode($backupData);
        $encryptedData = $this->encrypt($jsonData);

        file_put_contents($backupPath, $encryptedData);

        Log::info('Encrypted database backup created', ['path' => $backupPath]);

        return ['success' => true, 'path' => $backupPath];
    }

    /**
     * Restore from encrypted backup
     */
    public function restoreEncryptedBackup($backupPath)
    {
        if (!file_exists($backupPath)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }

        $encryptedData = file_get_contents($backupPath);
        $jsonData = $this->decrypt($encryptedData);
        $backupData = json_decode($jsonData, true);

        if (!$backupData || !isset($backupData['tables'])) {
            return ['success' => false, 'message' => 'Invalid backup data'];
        }

        DB::beginTransaction();

        try {
            foreach ($backupData['tables'] as $table => $records) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();

                    foreach ($records as $record) {
                        DB::table($table)->insert($record);
                    }
                }
            }

            DB::commit();
            Log::info('Database restored from encrypted backup', ['path' => $backupPath]);

            return ['success' => true, 'tables_restored' => count($backupData['tables'])];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore database', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get encryption status
     */
    public function getEncryptionStatus()
    {
        $status = [];

        foreach ($this->encryptedFields as $table => $fields) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $status[$table] = [];

            foreach ($fields as $field) {
                $encryptedCount = DB::table($table)
                    ->whereNotNull($field)
                    ->where($field, 'like', 'ENC:%')
                    ->count();

                $totalCount = DB::table($table)->whereNotNull($field)->count();

                $status[$table][$field] = [
                    'encrypted' => $encryptedCount,
                    'total' => $totalCount,
                    'percentage' => $totalCount > 0 ? round(($encryptedCount / $totalCount) * 100, 2) : 0
                ];
            }
        }

        return $status;
    }
}