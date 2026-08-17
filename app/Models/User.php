<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'status',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    // ─── Relationships ───────────────────────────────────────────

    // vendor has one profile (stall number)
    public function vendorProfile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    // vendor has many inventory entries
    public function vendorInventories()
    {
        return $this->hasMany(VendorInventory::class, 'vendor_id');
    }

    // staff has many confirmed inventory entries
    public function confirmedInventories()
    {
        return $this->hasMany(VendorInventory::class, 'confirmed_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'generated_by');
    }

    // ─── Helper Methods ──────────────────────────────────────────

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isVendor(): bool
    {
        return $this->role === 'vendor';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}