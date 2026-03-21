<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'broker_id',
        'phone_number',
        'referral_code',
        'referred_by_code',
        'referred_by_user_id',
        'slab_id',
        'user_type',
        'status',
        'kyc_verified',
        'total_business_volume',
        'total_commission_earned',
        'total_downline_count',
        'last_login_at',
        'profile_image_path',
        'address',
        'city',
        'state',
        'pincode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'kyc_verified' => 'boolean',
            'total_business_volume' => 'decimal:2',
            'total_commission_earned' => 'decimal:2',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships
    public function slab()
    {
        return $this->belongsTo(Slab::class);
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class)->where('status', '!=', 'pending');
    }

    public function kycDocument()
    {
        return $this->hasOne(KycDocument::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'sold_by_user_id');
    }

    public function customerSales()
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function slabUpgrades()
    {
        return $this->hasMany(SlabUpgrade::class);
    }

    public function userSlabs()
    {
        return $this->hasMany(UserSlab::class);
    }

    /**
     * Get downline user IDs (users referred by this user, and their referrals, recursively).
     * Used for team sales count in graph etc.
     *
     * @param array $visited Prevent infinite loops in case of bad data
     * @return array<int>
     */
    public function getDownlineUserIds(array $visited = []): array
    {
        if (in_array($this->id, $visited)) {
            return [];
        }
        $visited[] = $this->id;
        $ids = [];
        $directIds = self::where('referred_by_user_id', $this->id)->whereNull('deleted_at')->pluck('id')->toArray();
        foreach ($directIds as $id) {
            $ids[] = $id;
            $downlineUser = self::find($id);
            if ($downlineUser) {
                $ids = array_merge($ids, $downlineUser->getDownlineUserIds($visited));
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Assign all initial slabs to this user (one per property type)
     */
    public function assignAllInitialSlabs(): void
    {
        $propertyTypes = PropertyType::where('is_active', true)->get();
        
        foreach ($propertyTypes as $propertyType) {
            // Get the initial slab (lowest sort_order) for this property type
            $initialSlab = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($propertyType) {
                    $query->where('property_types.id', $propertyType->id);
                })
                ->orderBy('sort_order')
                ->first();
            
            if ($initialSlab) {
                // Check if user already has a slab for this property type
                $existing = \DB::table('user_slabs')
                    ->where('user_id', $this->id)
                    ->where('property_type_id', $propertyType->id)
                    ->first();
                
                if (!$existing) {
                    \DB::table('user_slabs')->insert([
                        'user_id' => $this->id,
                        'property_type_id' => $propertyType->id,
                        'slab_id' => $initialSlab->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        
        // Also update the primary slab_id to the first property type's initial slab
        $firstPropertyType = PropertyType::where('is_active', true)->orderBy('name')->first();
        if ($firstPropertyType) {
            $firstSlab = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($firstPropertyType) {
                    $query->where('property_types.id', $firstPropertyType->id);
                })
                ->orderBy('sort_order')
                ->first();
            
            if ($firstSlab && !$this->slab_id) {
                $this->slab_id = $firstSlab->id;
                $this->save();
            }
        }
    }

    /**
     * Get default slab for new users
     * Returns the first available slab from the first property type (alphabetically)
     * Falls back to lowest sort_order slab if no property type slabs found
     * 
     * @return \App\Models\Slab|null
     */
    public static function getDefaultSlab(): ?Slab
    {
        // Get first active property type (alphabetically)
        $firstPropertyType = \App\Models\PropertyType::where('is_active', true)
            ->orderBy('name')
            ->first();
        
        if ($firstPropertyType) {
            // Get first slab for this property type
            $defaultSlab = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($firstPropertyType) {
                    $query->where('property_types.id', $firstPropertyType->id);
                })
                ->orderBy('sort_order')
                ->first();
            
            if ($defaultSlab) {
                return $defaultSlab;
            }
        }
        
        // Fallback: get the slab with lowest sort_order (entry-level)
        return Slab::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}