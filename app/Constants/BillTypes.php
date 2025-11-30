<?php

namespace App\Constants;

class BillTypes
{
    // Meter Reading Related
    public const REGULAR_READING = 'regular_reading';
    public const SPECIAL_READING = 'special_reading';
    public const ESTIMATED_READING = 'estimated_reading';
    public const CORRECTED_READING = 'corrected_reading';
    public const BACKDATED_READING = 'backdated_reading';

    // Connection Related
    public const NEW_CONNECTION = 'new_connection';
    public const RECONNECTION = 'reconnection';
    public const CONNECTION_UPGRADE = 'connection_upgrade';
    public const CONNECTION_TRANSFER = 'connection_transfer';
    public const CONNECTION_EXTENSION = 'connection_extension';

    // Disconnection Related
    public const VOLUNTARY_DISCONNECTION = 'voluntary_disconnection';
    public const NON_PAYMENT_DISCONNECTION = 'non_payment_disconnection';
    public const SAFETY_DISCONNECTION = 'safety_disconnection';
    public const TEMPORARY_DISCONNECTION = 'temporary_disconnection';
    public const PERMANENT_DISCONNECTION = 'permanent_disconnection';
    public const NORMAL_DISCONNECTION = 'normal_disconnection';

    // Other Charges
    public const LATE_PAYMENT_PENALTY = 'late_payment_penalty';
    public const ADMINISTRATIVE_FEE = 'administrative_fee';
    public const INSPECTION_FEE = 'inspection_fee';
    public const MAINTENANCE_FEE = 'maintenance_fee';
    public const DAMAGE_CHARGES = 'damage_charges';
    public const TAMPERING_CHARGES = 'tampering_charges';
    public const SERVICE_FEE = 'service_fee';
    public const PROCESSING_FEE = 'processing_fee';

    // Future-Proofing
    public const CUSTOM_CHARGE = 'custom_charge';
    public const SUBSCRIPTION_FEE = 'subscription_fee';
    public const USAGE_BASED = 'usage_based';
    public const PEAK_USAGE = 'peak_usage';
    public const OFF_PEAK_DISCOUNT = 'off_peak_discount';
    public const GREEN_ENERGY_SURCHARGE = 'green_energy_surcharge';
    public const CARBON_OFFSET = 'carbon_offset';
    public const COMMUNITY_PROGRAM = 'community_program';


    public const BILL_STATUS_PENDING = 'pending';
    public const BILL_STATUS_PAID = 'paid';
    public const BILL_STATUS_OVERDUE = 'overdue';
    public const BILL_STATUS_CANCELLED = 'cancelled';

    /**
     * Get all bill types as an array
     *
     * @return array
     */
    public static function getAll(): array
    {
        return [
            self::REGULAR_READING,
            self::SPECIAL_READING,
            self::ESTIMATED_READING,
            self::CORRECTED_READING,
            self::BACKDATED_READING,
            self::NEW_CONNECTION,
            self::RECONNECTION,
            self::CONNECTION_UPGRADE,
            self::CONNECTION_TRANSFER,
            self::CONNECTION_EXTENSION,
            self::VOLUNTARY_DISCONNECTION,
            self::NON_PAYMENT_DISCONNECTION,
            self::SAFETY_DISCONNECTION,
            self::TEMPORARY_DISCONNECTION,
            self::PERMANENT_DISCONNECTION,
            self::NORMAL_DISCONNECTION,
            self::LATE_PAYMENT_PENALTY,
            self::ADMINISTRATIVE_FEE,
            self::INSPECTION_FEE,
            self::MAINTENANCE_FEE,
            self::DAMAGE_CHARGES,
            self::TAMPERING_CHARGES,
            self::SERVICE_FEE,
            self::PROCESSING_FEE,
            self::CUSTOM_CHARGE,
            self::SUBSCRIPTION_FEE,
            self::USAGE_BASED,
            self::PEAK_USAGE,
            self::OFF_PEAK_DISCOUNT,
            self::GREEN_ENERGY_SURCHARGE,
            self::CARBON_OFFSET,
            self::COMMUNITY_PROGRAM,
            self::BILL_STATUS_PENDING,
            self::BILL_STATUS_PAID,
            self::BILL_STATUS_OVERDUE,
            self::BILL_STATUS_CANCELLED,
        ];
    }

    /**
     * Get bill types grouped by category
     *
     * @return array
     */
    public static function getGrouped(): array
    {
        return [
            'Meter Reading' => [
                self::REGULAR_READING => 'Regular Reading',
                self::SPECIAL_READING => 'Special Reading',
                self::ESTIMATED_READING => 'Estimated Reading',
                self::CORRECTED_READING => 'Corrected Reading',
                self::BACKDATED_READING => 'Backdated Reading',
            ],
            'Connection' => [
                self::NEW_CONNECTION => 'New Connection',
                self::RECONNECTION => 'Reconnection',
                self::CONNECTION_UPGRADE => 'Connection Upgrade',
                self::CONNECTION_TRANSFER => 'Connection Transfer',
                self::CONNECTION_EXTENSION => 'Connection Extension',
            ],
            'Disconnection' => [
                self::VOLUNTARY_DISCONNECTION => 'Voluntary Disconnection',
                self::NON_PAYMENT_DISCONNECTION => 'Non-Payment Disconnection',
                self::SAFETY_DISCONNECTION => 'Safety Disconnection',
                self::TEMPORARY_DISCONNECTION => 'Temporary Disconnection',
                self::PERMANENT_DISCONNECTION => 'Permanent Disconnection',
                self::NORMAL_DISCONNECTION => 'Normal Disconnection',
            ],
            'Other Charges' => [
                self::LATE_PAYMENT_PENALTY => 'Late Payment Penalty',
                self::ADMINISTRATIVE_FEE => 'Administrative Fee',
                self::INSPECTION_FEE => 'Inspection Fee',
                self::MAINTENANCE_FEE => 'Maintenance Fee',
                self::DAMAGE_CHARGES => 'Damage Charges',
                self::TAMPERING_CHARGES => 'Tampering Charges',
                self::SERVICE_FEE => 'Service Fee',
                self::PROCESSING_FEE => 'Processing Fee',
            ],
            'Special Charges' => [
                self::CUSTOM_CHARGE => 'Custom Charge',
                self::SUBSCRIPTION_FEE => 'Subscription Fee',
                self::USAGE_BASED => 'Usage Based',
                self::PEAK_USAGE => 'Peak Usage',
                self::OFF_PEAK_DISCOUNT => 'Off-Peak Discount',
                self::GREEN_ENERGY_SURCHARGE => 'Green Energy Surcharge',
                self::CARBON_OFFSET => 'Carbon Offset',
                self::COMMUNITY_PROGRAM => 'Community Program',
            ],
            'Status' => [
                self::BILL_STATUS_PENDING => 'Pending',
                self::BILL_STATUS_PAID => 'Paid',
                self::BILL_STATUS_OVERDUE => 'Overdue',
                self::BILL_STATUS_CANCELLED => 'Cancelled',
            ],
        ];
    }

    /**
     * Get display name for a bill type
     *
     * @param string $type
     * @return string
     */
    public static function getDisplayName(string $type): string
    {
        $grouped = self::getGrouped();
        foreach ($grouped as $category) {
            if (isset($category[$type])) {
                return $category[$type];
            }
        }
        return ucwords(str_replace('_', ' ', $type));
    }

    public static function getBillTypeAccountEquivalent(string $type): string
    {
      return strtoupper($type);
    }
}
