<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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

    // ─── Account Deletion ────────────────────────────────────────

    // Records that would block a plain delete() on this account.
    public function blockingRecordCount(): int
    {
        return $this->vendorInventories()->count() + $this->reports()->count();
    }

    /*
     * Permanently delete this account together with the rows whose foreign key
     * restricts the delete (vendor_inventories.vendor_id, reports.generated_by).
     * Without this the database rejects the delete with SQLSTATE 23000 as soon
     * as the account has any market records.
     *
     * Rows that reference the account through a nullable foreign key
     * (activity_logs.user_id, vendor_inventories.confirmed_by) are set to NULL
     * by the database and vendor_profiles cascades, so confirmation and
     * activity history is preserved.
     */
    public function deleteWithRecords(): void
    {
        DB::transaction(function () {
            $this->vendorInventories()->delete();
            $this->reports()->delete();
            $this->delete();
        });
    }
}