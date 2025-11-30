<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\Bill;
use App\Services\Invoice\InvoiceService;
use App\Constants\BillTypes;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use League\Csv\Reader;

// Production
$tenantId = 2;
$userId = 3;

// //Dev
// $tenantId = 1;
// $userId = 2;


// Configuration
$csvPath = $argv[1] ?? null; // Get CSV path from command line argument

if (!$csvPath) {
    die("Usage: php import_customers.php <csv_file_path>\n");
}

if (!file_exists($csvPath)) {
    die("CSV file not found: $csvPath\n");
}

try {
    // Create CSV reader
    $csv = Reader::createFromPath($csvPath, 'r');
    $csv->setHeaderOffset(0);

    $records = $csv->getRecords();
    $imported = 0;
    $failed = 0;
    $updated = 0;
    $errors = [];
    $used = [];

    echo "Starting import...\n";
    echo "Using tenant_id: $tenantId, user_id: $userId\n";

    // Process all records
    foreach ($records as $index => $record) {
        $customer = null;
        try {
            $MeterNo =  $record['MeterNo'];
            $balance = (float)$record['Tbal'];
            $primaryphone = $record['NumberPrimary'];
            $parsedDate = now();
            try {
                // Parse the date string into a Carbon date object
                // Use a transaction for each record to ensure data integrity
                DB::beginTransaction();

                // Check if telephone already exists
                if (User::where('telephone', $record['NumberPrimary'])->exists()) {
                    $updated++;
                    $errors[] = "Row {$index}: Telephone number {$primaryphone} already exists";
                    $customer = User::where('telephone', $primaryphone)->first();
                    $used[] = "Row {$index}: Customer {$customer->id}->{$customer->name} Found";
                }
                else{
                    echo "No Record Found for MeterNo {$MeterNo} and NumberPrimary {$primaryphone}\n";
                    return;
                }

                $meter = null;
                $meterAssignment = null;
                $meterReading = null;
                // Create meter if meter number is provided
                if (!empty($MeterNo)) {
                    $previousReading = (float)$record['PreviousReading'];
                    $currentReading = (float)$record['CurrentReading'];

                    $meter = Meter::where('meter_number', $MeterNo)->first();
                    if(!$meter){
                        echo "No Record Found for MeterNo {$MeterNo}\n";
                        return;
                    }
                    // Assign meter to customer
                    $meterAssignment = MeterAssignment::where('meter_id', $meter->id)->where('customer_id', $customer->id)->first();
                    if(!$meterAssignment){
                        echo "No Record Found for MeterNo {$MeterNo} and Customer {$customer->id}\n";
                        return;
                    }
                    // Record meter reading if current_reading is provided
                    if (!empty($record['CurrentReading'])) {
                        $units = (float)$record['Units'];
                        // Create meter reading record
                        $meterReading = MeterReading::create([
                            'meter_id' => $meter->id,
                            'customer_id' => $customer->id,
                            'reading_date' => $parsedDate,
                            'reading_value' => $currentReading,
                            'reading_type' => 'regular reading',
                            'consumption' => $units,
                            'status' => 'confirmed',
                            'is_confirmed' => true,
                            'is_paid' => true,
                            'reader_id' => $userId,
                            'tenant_id' => $tenantId,
                            'notes' => 'Initial reading recorded during import',
                            'created_at' => $parsedDate,
                            'updated_at' => $parsedDate,
                        ]);

                        // Update meter's last reading
                        // $meter->update([
                        //     'last_reading' => $currentReading,
                        //     'current_reading' => $currentReading,
                        //     'last_reading_date' =>  $parsedDate,
                        // ]);
                    }

                    // If customer has a positive balance, create a bill and invoice
                    if ($balance > 0 && $meterReading) {
                        $units = (float)$record['Units'];
                        $cost = (float)$record['Cost'];

                        $rate = $units > 0 ? round(($cost-100) / $units, 2) : 0;
                        echo "Creating bill for Customer #{$customer->id}, Meter #{$meterAssignment->meter_no}, Amount: {$balance}, Rate: {$rate}\n";

                        //add bill for meter reading
                        $bill = Bill::create([
                            'customer_id' => $customer->id,
                            'meter_assignment_id' => $meterAssignment->id,
                            'meter_reading_id' => $meter->id,
                            'generation_date' =>  $parsedDate,
                            'amount' => 1,
                            'total_amount' => $balance,
                            'bill_type' => BillTypes::getBillTypeAccountEquivalent('METER_READING'),
                            'status' => BillTypes::BILL_STATUS_PENDING,
                            'tenant_id' => $tenantId,
                            'rate_used' => $rate,
                            'notes' => 'Initial balance bill for imported customer',
                            'created_at' => $parsedDate,
                            'updated_at' => $parsedDate,
                        ]);

                        //add bill for service cost



                        // Generate invoice for the bill
                        $bills = collect([$bill]);
                        app(InvoiceService::class)->generateInvoiceForMeterReading($bills);
                    }
                }

                DB::commit();
                $imported++;
                echo "Imported customer: {$primaryphone} (Row {$index})\n";
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // Re-throw to be caught by outer catch
            }
        } catch (\Exception $e) {
            $failed++;
            $errors[] = "Row {$index}: " . $e->getMessage();
            echo "Error: {$errors[count($errors) - 1]}\n";
        }
    }

    echo "\nImport completed!\n";
    echo "Successfully imported: $imported\n";
    echo "Failed: $failed\n";

    echo "Updated: $updated\n";

    if (count($used) > 0) {
        echo "\Found Records:\n";
        foreach ($used as $use) {
            echo "- $use\n";
        }
    }

    if (count($errors) > 0) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }
} catch (\Exception $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}
