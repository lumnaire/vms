<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Batch numbers used to restart at 1 every day, while batches stay on the stall
 * across days. A vendor with yesterday's Batch 1 still on sale who submitted the
 * same fish today got a second Batch 1, and the board listed both.
 *
 * Numbering now continues past every batch still on the stall or waiting for
 * staff (VendorInventory::nextBatchNo). This renumbers the live batches that
 * already collide: the newer one moves to the next free number.
 */
return new class extends Migration
{
    public function up(): void
    {
        $live = DB::table('vendor_inventories')
            ->where(fn ($q) => $q->where('status', 'pending')->orWhere(
                fn ($q) => $q->where('status', 'confirmed')
                    ->whereNull('disposed_at')
                    ->whereNull('carried_out_at')
                    ->whereColumn('sold_kg', '<', 'released_kg')
            ))
            ->orderBy('entry_date')
            ->orderBy('batch_no')
            ->orderBy('id')
            ->get(['id', 'vendor_id', 'fish_type_id', 'quality_class', 'batch_no']);

        $live->groupBy(fn ($row) => $row->vendor_id.'|'.$row->fish_type_id.'|'.$row->quality_class)
            ->each(function ($rows) {
                $taken = [];

                foreach ($rows as $row) {
                    $no = (int) $row->batch_no;

                    if (isset($taken[$no])) {
                        $no = max(array_keys($taken)) + 1;
                        DB::table('vendor_inventories')->where('id', $row->id)->update(['batch_no' => $no]);
                    }

                    $taken[$no] = true;
                }
            });
    }

    public function down(): void
    {
        // Renumbering is not reversible, and the old numbers were the bug.
    }
};
