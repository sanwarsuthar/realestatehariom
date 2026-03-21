<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Slab;
use App\Models\PropertyType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignDefaultSlabsToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:assign-default-slabs {--force : Force assignment even if user already has a slab}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign default slabs to all users based on property types (first available slab per property type)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting default slab assignment...');
        
        $force = $this->option('force');
        
        // Get all active property types
        $propertyTypes = PropertyType::where('is_active', true)
            ->orderBy('name')
            ->get();
        
        if ($propertyTypes->isEmpty()) {
            $this->error('❌ No active property types found. Please create property types first.');
            return 1;
        }
        
        $this->info("📋 Found {$propertyTypes->count()} property type(s): " . $propertyTypes->pluck('name')->join(', '));
        
        // Find first/lowest slab for each property type
        $defaultSlabsByPropertyType = [];
        foreach ($propertyTypes as $propertyType) {
            $firstSlab = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($propertyType) {
                    $query->where('property_types.id', $propertyType->id);
                })
                ->orderBy('sort_order')
                ->first();
            
            if ($firstSlab) {
                $defaultSlabsByPropertyType[$propertyType->name] = $firstSlab;
                $this->info("   ✓ {$propertyType->name}: {$firstSlab->name} (sort_order: {$firstSlab->sort_order})");
            } else {
                $this->warn("   ⚠️  {$propertyType->name}: No slabs found");
            }
        }
        
        if (empty($defaultSlabsByPropertyType)) {
            $this->error('❌ No slabs found for any property type. Please configure slabs first.');
            return 1;
        }
        
        // Use the first property type's first slab as the default slab for users
        // (Since users only have one slab_id, we assign the lowest slab overall)
        $firstPropertyType = $propertyTypes->first();
        $defaultSlab = $defaultSlabsByPropertyType[$firstPropertyType->name] ?? null;
        
        if (!$defaultSlab) {
            // Fallback: get the lowest slab overall
            $defaultSlab = Slab::where('is_active', true)
                ->orderBy('sort_order')
                ->first();
        }
        
        if (!$defaultSlab) {
            $this->error('❌ No active slabs found in the system.');
            return 1;
        }
        
        $this->info("\n📌 Default slab for users: {$defaultSlab->name} (from {$firstPropertyType->name})");
        
        // Get users without slabs (or all users if --force)
        $query = User::where('user_type', '!=', 'admin');
        
        if (!$force) {
            $query->whereNull('slab_id');
        }
        
        $users = $query->get();
        
        if ($users->isEmpty()) {
            $this->info('✅ All users already have slabs assigned.');
            return 0;
        }
        
        $this->info("\n👥 Found {$users->count()} user(s) to process...");
        
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();
        
        $updated = 0;
        $skipped = 0;
        
        foreach ($users as $user) {
            if (!$force && $user->slab_id) {
                $skipped++;
                $bar->advance();
                continue;
            }
            
            try {
                $user->update(['slab_id' => $defaultSlab->id]);
                $updated++;
            } catch (\Exception $e) {
                Log::error("Failed to assign slab to user {$user->id}: " . $e->getMessage());
                $this->warn("\n⚠️  Failed to assign slab to user {$user->id} ({$user->name})");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Successfully assigned default slab '{$defaultSlab->name}' to {$updated} user(s).");
        if ($skipped > 0) {
            $this->info("⏭️  Skipped {$skipped} user(s) (already have slabs).");
        }
        
        return 0;
    }
}
