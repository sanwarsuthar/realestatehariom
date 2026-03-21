<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportBackupDataCommand extends Command
{
    protected $signature = 'db:import-backup {--backup-path=}';
    protected $description = 'Import all data from backup database (users, projects, etc.)';
    private $userMapping = []; // old_id => new_id
    private $measurementUnitMapping = []; // old_id => new_id
    private $propertyTypeMapping = []; // old_id => new_id
    private $slabMapping = []; // old_id => new_id
    private $projectMapping = []; // old_id => new_id

    public function handle()
    {
        $backupPath = $this->option('backup-path') ?: '/Users/mac/Documents/hari om fron mac /shree hari om/BACKUPS/superadmin/database/database.sqlite';
        
        if (!file_exists($backupPath)) {
            $this->error("❌ Backup database not found at: {$backupPath}");
            return 1;
        }

        $this->info("🔄 Starting data import from backup...");
        $this->info("📁 Backup path: {$backupPath}");

        try {
            config(['database.connections.backup' => [
                'driver' => 'sqlite',
                'database' => $backupPath,
                'prefix' => '',
            ]]);

            DB::purge('backup');
            $backupDB = DB::connection('backup');
            $currentDB = DB::connection();

            $adminIds = $currentDB->table('users')->where('user_type', 'admin')->pluck('id')->toArray();
            $this->info("✅ Found " . count($adminIds) . " admin user(s) to preserve");

            // Step 1: Import base tables (no foreign keys to users)
            $this->importBaseTables($backupDB, $currentDB);

            // Step 2: Import projects and plots
            $this->importProjects($backupDB, $currentDB);
            $this->importPlots($backupDB, $currentDB);

            // Step 3: Import users and create mapping
            $this->importUsers($backupDB, $currentDB);

            // Step 4: Import user-related tables with ID mapping
            $this->importUserRelatedTables($backupDB, $currentDB);

            // Step 5: Import remaining tables
            $this->importRemainingTables($backupDB, $currentDB);

            // Copy files
            $this->copyStorageFiles();
            $this->copySliderImages();

            $this->info("");
            $this->info("✅ SUCCESS! Data imported from backup.");
            $this->info("✅ Preserved admin users and their data.");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    private function importBaseTables($backupDB, $currentDB)
    {
        // Step 1: Import measurement_units first (no dependencies)
        $this->importMeasurementUnits($backupDB, $currentDB);
        
        // Step 2: Import property_types (depends on measurement_units)
        $this->importPropertyTypes($backupDB, $currentDB);
        
        // Step 3: Import slabs (depends on measurement_units)
        $this->importSlabs($backupDB, $currentDB);
        
        // Step 4: Import property_type_slab (depends on property_types and slabs)
        $this->importPropertyTypeSlab($backupDB, $currentDB);
        
        // Step 5: Import payment_methods (no dependencies)
        $this->importTable($backupDB, $currentDB, 'payment_methods');
    }

    private function importMeasurementUnits($backupDB, $currentDB)
    {
        $this->info("📥 Importing measurement_units...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('measurement_units')) {
            return;
        }

        $backupData = $backupDB->table('measurement_units')->get();
        if ($backupData->isEmpty()) {
            $this->info("   ℹ️  No data");
            return;
        }

        $imported = 0;
        foreach ($backupData as $row) {
            $oldId = $row->id;
            $data = (array) $row;
            unset($data['id'], $data['created_at'], $data['updated_at']);

            // Check if exists by name
            $existing = $currentDB->table('measurement_units')->where('name', $data['name'])->first();
            if ($existing) {
                $this->measurementUnitMapping[$oldId] = $existing->id;
                continue;
            }

            try {
                $newId = $currentDB->table('measurement_units')->insertGetId($data);
                $this->measurementUnitMapping[$oldId] = $newId;
                $imported++;
            } catch (\Exception $e) {
                // Skip
            }
        }
        $this->info("   ✅ Imported {$imported} measurement units");
    }

    private function importPropertyTypes($backupDB, $currentDB)
    {
        $this->info("📥 Importing property_types...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('property_types')) {
            return;
        }

        $backupData = $backupDB->table('property_types')->get();
        if ($backupData->isEmpty()) {
            $this->info("   ℹ️  No data");
            return;
        }

        $imported = 0;
        foreach ($backupData as $row) {
            $oldId = $row->id;
            $data = (array) $row;
            
            // Map measurement_unit_id
            if (isset($data['measurement_unit_id']) && isset($this->measurementUnitMapping[$data['measurement_unit_id']])) {
                $data['measurement_unit_id'] = $this->measurementUnitMapping[$data['measurement_unit_id']];
            } else {
                continue; // Skip if measurement unit doesn't exist
            }

            unset($data['id'], $data['created_at'], $data['updated_at']);

            // Check if exists by name
            $existing = $currentDB->table('property_types')->where('name', $data['name'])->first();
            if ($existing) {
                $this->propertyTypeMapping[$oldId] = $existing->id;
                continue;
            }

            try {
                $newId = $currentDB->table('property_types')->insertGetId($data);
                $this->propertyTypeMapping[$oldId] = $newId;
                $imported++;
            } catch (\Exception $e) {
                // Skip
            }
        }
        $this->info("   ✅ Imported {$imported} property types");
    }

    private function importSlabs($backupDB, $currentDB)
    {
        $this->info("📥 Importing slabs...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('slabs')) {
            return;
        }

        $backupData = $backupDB->table('slabs')->get();
        if ($backupData->isEmpty()) {
            $this->info("   ℹ️  No data");
            return;
        }

        $imported = 0;
        foreach ($backupData as $row) {
            $oldId = $row->id;
            $data = (array) $row;
            
            // Map measurement_unit_id
            if (isset($data['measurement_unit_id']) && isset($this->measurementUnitMapping[$data['measurement_unit_id']])) {
                $data['measurement_unit_id'] = $this->measurementUnitMapping[$data['measurement_unit_id']];
            } else {
                continue; // Skip if measurement unit doesn't exist
            }

            unset($data['id'], $data['created_at'], $data['updated_at']);

            // Check if exists by name
            $existing = $currentDB->table('slabs')->where('name', $data['name'])->first();
            if ($existing) {
                $this->slabMapping[$oldId] = $existing->id;
                continue;
            }

            try {
                $newId = $currentDB->table('slabs')->insertGetId($data);
                $this->slabMapping[$oldId] = $newId;
                $imported++;
            } catch (\Exception $e) {
                // Skip
            }
        }
        $this->info("   ✅ Imported {$imported} slabs");
    }

    private function importPropertyTypeSlab($backupDB, $currentDB)
    {
        $this->info("📥 Importing property_type_slab...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('property_type_slab')) {
            return;
        }

        $backupData = $backupDB->table('property_type_slab')->get();
        if ($backupData->isEmpty()) {
            $this->info("   ℹ️  No data");
            return;
        }

        $imported = 0;
        $skipped = 0;
        foreach ($backupData as $row) {
            $data = (array) $row;
            
            // Map IDs
            if (isset($data['property_type_id']) && isset($this->propertyTypeMapping[$data['property_type_id']])) {
                $data['property_type_id'] = $this->propertyTypeMapping[$data['property_type_id']];
            } else {
                $skipped++;
                continue;
            }

            if (isset($data['slab_id']) && isset($this->slabMapping[$data['slab_id']])) {
                $data['slab_id'] = $this->slabMapping[$data['slab_id']];
            } else {
                $skipped++;
                continue;
            }

            // Check if already exists
            $existing = $currentDB->table('property_type_slab')
                ->where('property_type_id', $data['property_type_id'])
                ->where('slab_id', $data['slab_id'])
                ->first();
            if ($existing) {
                $skipped++;
                continue;
            }

            try {
                $currentDB->table('property_type_slab')->insert($data);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }
        $this->info("   ✅ Imported {$imported} records, skipped {$skipped}");
    }

    private function importUsers($backupDB, $currentDB)
    {
        $this->info("📥 Importing users...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('users')) {
            $this->warn("   ⚠️  Users table not found");
            return;
        }

        // Check if deleted_at column exists
        $hasDeletedAt = false;
        try {
            $columns = $backupDB->select("PRAGMA table_info(users)");
            foreach ($columns as $column) {
                if ($column->name === 'deleted_at') {
                    $hasDeletedAt = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // Column check failed, assume it doesn't exist
        }

        // Import ALL users including admin
        $query = $backupDB->table('users');
        if ($hasDeletedAt) {
            $query->whereNull('deleted_at');
        }
        $backupUsers = $query->get();

        if ($backupUsers->isEmpty()) {
            $this->info("   ℹ️  No users to import");
            return;
        }

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($backupUsers as $user) {
            $oldId = $user->id;
            $data = (array) $user;
            unset($data['id'], $data['created_at'], $data['updated_at']);
            if (isset($data['deleted_at'])) {
                unset($data['deleted_at']);
            }

            // Map referred_by_user_id if it exists
            if (isset($data['referred_by_user_id']) && $data['referred_by_user_id']) {
                if (isset($this->userMapping[$data['referred_by_user_id']])) {
                    $data['referred_by_user_id'] = $this->userMapping[$data['referred_by_user_id']];
                } else {
                    // If referrer not imported yet, set to null (will update later)
                    $data['referred_by_user_id'] = null;
                }
            }

            // Map slab_id if it exists
            if (isset($data['slab_id']) && $data['slab_id']) {
                if (isset($this->slabMapping[$data['slab_id']])) {
                    $data['slab_id'] = $this->slabMapping[$data['slab_id']];
                } else {
                    // If slab not found, set to null or first available slab
                    $firstSlab = $currentDB->table('slabs')->orderBy('id')->first();
                    $data['slab_id'] = $firstSlab ? $firstSlab->id : null;
                }
            }

            // Check if user already exists by email or broker_id
            $existing = null;
            if (isset($data['email']) && $data['email']) {
                $existing = $currentDB->table('users')->where('email', $data['email'])->first();
            }
            if (!$existing && isset($data['broker_id']) && $data['broker_id']) {
                $existing = $currentDB->table('users')->where('broker_id', $data['broker_id'])->first();
            }

            if ($existing) {
                // User already exists, use existing ID for mapping
                $this->userMapping[$oldId] = $existing->id;
                $skipped++;
                continue;
            }

            try {
                $newId = $currentDB->table('users')->insertGetId($data);
                $this->userMapping[$oldId] = $newId;
                $imported++;
            } catch (\Exception $e) {
                // Try to find existing user if insert failed due to unique constraint
                if (isset($data['email']) && $data['email']) {
                    $existing = $currentDB->table('users')->where('email', $data['email'])->first();
                    if ($existing) {
                        $this->userMapping[$oldId] = $existing->id;
                        $skipped++;
                        continue;
                    }
                }
                $this->warn("   ⚠️  Failed to import user {$oldId}: " . $e->getMessage());
                $skipped++;
            }
        }

        $this->info("   ✅ Imported {$imported} users, skipped {$skipped}");

        // Update referred_by_user_id relationships
        $this->info("   🔄 Updating user referral relationships...");
        $updated = 0;
        foreach ($backupUsers as $user) {
            if ($user->referred_by_user_id && isset($this->userMapping[$user->referred_by_user_id])) {
                $newUserId = $this->userMapping[$user->id];
                $newReferrerId = $this->userMapping[$user->referred_by_user_id];
                $currentDB->table('users')
                    ->where('id', $newUserId)
                    ->update(['referred_by_user_id' => $newReferrerId]);
                $updated++;
            }
        }
        $this->info("   ✅ Updated {$updated} referral relationships");
    }

    private function importUserRelatedTables($backupDB, $currentDB)
    {
        $tables = [
            'wallets' => ['user_id'],
            'kyc_documents' => ['user_id'],
            'transactions' => ['user_id'],
            'sales' => ['sold_by_user_id', 'customer_id'],
            'payment_requests' => ['user_id'],
            'slab_upgrades' => ['user_id'],
            'referrals' => ['referrer_id', 'referred_id'],
        ];

        foreach ($tables as $table => $userIdColumns) {
            $this->importTableWithUserMapping($backupDB, $currentDB, $table, $userIdColumns);
        }
    }

    private function importRemainingTables($backupDB, $currentDB)
    {
        $tables = ['settings', 'otp_verifications', 'contact_inquiries', 'error_logs'];
        foreach ($tables as $table) {
            $this->importTable($backupDB, $currentDB, $table);
        }
    }

    private function importTable($backupDB, $currentDB, $table)
    {
        if (!$backupDB->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $this->info("📥 Importing {$table}...");

        try {
            $backupData = $backupDB->table($table)->get();

            if ($backupData->isEmpty()) {
                $this->info("   ℹ️  No data in {$table}");
                return;
            }

            $imported = 0;
            $skipped = 0;
            $updated = 0;

            foreach ($backupData as $row) {
                $data = (array) $row;
                $oldId = $data['id'] ?? null;
                unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

                try {
                    // For tables with unique constraints, try update first, then insert
                    if (in_array($table, ['slabs', 'property_types', 'measurement_units', 'payment_methods'])) {
                        // Check for unique fields
                        $uniqueField = null;
                        if ($table === 'slabs' && isset($data['name'])) {
                            $uniqueField = 'name';
                        } elseif ($table === 'property_types' && isset($data['name'])) {
                            $uniqueField = 'name';
                        } elseif ($table === 'measurement_units' && isset($data['name'])) {
                            $uniqueField = 'name';
                        } elseif ($table === 'payment_methods' && isset($data['name'])) {
                            $uniqueField = 'name';
                        }

                        if ($uniqueField) {
                            $existing = $currentDB->table($table)->where($uniqueField, $data[$uniqueField])->first();
                            if ($existing) {
                                $currentDB->table($table)->where($uniqueField, $data[$uniqueField])->update($data);
                                $updated++;
                                continue;
                            }
                        }
                    }

                    // For plots, check by project_id and plot_number if exists
                    if ($table === 'plots' && isset($data['project_id']) && isset($data['plot_number'])) {
                        $existing = $currentDB->table($table)
                            ->where('project_id', $data['project_id'])
                            ->where('plot_number', $data['plot_number'])
                            ->first();
                        if ($existing) {
                            $skipped++;
                            continue;
                        }
                    }

                    $currentDB->table($table)->insert($data);
                    $imported++;
                } catch (\Exception $e) {
                    // Try update if insert fails (for unique constraints)
                    if (strpos($e->getMessage(), 'UNIQUE constraint') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $skipped++;
                    } else {
                        $this->warn("   ⚠️  Error: " . $e->getMessage());
                        $skipped++;
                    }
                }
            }

            $status = "✅ Imported {$imported} records";
            if ($updated > 0) {
                $status .= ", updated {$updated} records";
            }
            if ($skipped > 0) {
                $status .= ", skipped {$skipped}";
            }
            $this->info("   {$status}");

        } catch (\Exception $e) {
            $this->error("   ❌ Error importing {$table}: " . $e->getMessage());
        }
    }

    private function importTableWithUserMapping($backupDB, $currentDB, $table, $userIdColumns)
    {
        if (!$backupDB->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $this->info("📥 Importing {$table}...");

        try {
            $backupData = $backupDB->table($table)->get();

            if ($backupData->isEmpty()) {
                $this->info("   ℹ️  No data in {$table}");
                return;
            }

            $imported = 0;
            $skipped = 0;

            foreach ($backupData as $row) {
                $data = (array) $row;

                // Check if all user IDs exist in mapping
                $skip = false;
                foreach ($userIdColumns as $column) {
                    if (isset($data[$column]) && $data[$column]) {
                        if (!isset($this->userMapping[$data[$column]])) {
                            $skip = true;
                            break;
                        }
                        $data[$column] = $this->userMapping[$data[$column]];
                    }
                }

                if ($skip) {
                    $skipped++;
                    continue;
                }

                unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

                try {
                    $currentDB->table($table)->insert($data);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                }
            }

            $this->info("   ✅ Imported {$imported} records, skipped {$skipped}");

        } catch (\Exception $e) {
            $this->error("   ❌ Error importing {$table}: " . $e->getMessage());
        }
    }

    private function copyStorageFiles()
    {
        $this->info("");
        $this->info("📁 Copying storage files...");
        $backupPath = '/Users/mac/Documents/hari om fron mac /shree hari om/BACKUPS/superadmin/storage/app/public';
        $currentPath = storage_path('app/public');

        if (!is_dir($backupPath)) {
            $this->warn("   ⚠️  Backup storage directory not found");
            return;
        }

        try {
            $this->copyDirectory($backupPath, $currentPath);
            $this->info("   ✅ Storage files copied");
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Error copying storage: " . $e->getMessage());
        }
    }

    private function copySliderImages()
    {
        $this->info("🖼️  Copying slider images...");
        $backupPath = '/Users/mac/Documents/hari om fron mac /shree hari om/BACKUPS/superadmin/public/sliders';
        $currentPath = public_path('sliders');

        if (!is_dir($backupPath)) {
            $this->warn("   ⚠️  Backup sliders directory not found");
            return;
        }

        try {
            if (!is_dir($currentPath)) {
                mkdir($currentPath, 0755, true);
            }
            $this->copyDirectory($backupPath, $currentPath);
            $this->info("   ✅ Slider images copied");
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Error copying sliders: " . $e->getMessage());
        }
    }

    private function importProjects($backupDB, $currentDB)
    {
        $this->info("📥 Importing projects...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('projects')) {
            return;
        }

        $backupData = $backupDB->table('projects')->get();
        if ($backupData->isEmpty()) {
            $this->info("   ℹ️  No data");
            return;
        }

        $imported = 0;
        foreach ($backupData as $row) {
            $oldId = $row->id;
            $data = (array) $row;
            unset($data['id'], $data['created_at'], $data['updated_at']);

            try {
                $newId = $currentDB->table('projects')->insertGetId($data);
                $this->projectMapping[$oldId] = $newId;
                $imported++;
            } catch (\Exception $e) {
                // Skip
            }
        }
        $this->info("   ✅ Imported {$imported} projects");
    }

    private function importPlots($backupDB, $currentDB)
    {
        $this->info("📥 Importing plots...");
        
        if (!$backupDB->getSchemaBuilder()->hasTable('plots')) {
            return;
        }

        $backupData = $backupDB->table('plots')->get();
        if ($backupData->isEmpty()) {
            $this->info("   ℹ️  No data");
            return;
        }

        $imported = 0;
        $skipped = 0;
        foreach ($backupData as $row) {
            $data = (array) $row;
            
            // Map project_id
            if (isset($data['project_id']) && isset($this->projectMapping[$data['project_id']])) {
                $data['project_id'] = $this->projectMapping[$data['project_id']];
            } else {
                $skipped++;
                continue;
            }

            unset($data['id'], $data['created_at'], $data['updated_at']);

            // Check if exists
            if (isset($data['plot_number']) && isset($data['project_id'])) {
                $existing = $currentDB->table('plots')
                    ->where('project_id', $data['project_id'])
                    ->where('plot_number', $data['plot_number'])
                    ->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }
            }

            try {
                $currentDB->table('plots')->insert($data);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }
        $this->info("   ✅ Imported {$imported} plots, skipped {$skipped}");
    }

    private function copyDirectory($src, $dst)
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                if (is_dir($srcFile)) {
                    $this->copyDirectory($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }
}
