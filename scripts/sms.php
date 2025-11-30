<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

$invoice_numbers = ['INV-464', 'INV-463', 'INV-461', 'INV-462', 'INV-459', 'INV-460', 'INV-296', 'INV-412', ''];

if (count($invoice_numbers) > 0) {
    $invoices  = Invoice::whereIn('invoice_number', $invoice_numbers)->where('state', 'open')->get();
} else {
    $invoices  = Invoice::where('state', 'open')->get();
}

foreach ($invoices as $invoice) {
    $customer = $invoice->customer;
    $totalAmount = $invoice->total_amount;

    if ($customer && $customer->telephone) {
        // Define the message template
        $messageTemplate = "Dear {customer_name}, your water bill invoice {invoice_number} for meter number {meter_number}, dated {invoice_date}, amounts to KES {invoice_amount}. Please pay before 10th to avoid Kshs. 500 penalty Via our Paybill No. 247247 Acc No. 366934 (Ayiro Springs). Please forward the Message to 0721275264. No Cash will be accepted for now";

        // Replace placeholders with actual values
        $formattedMessage = str_replace('{customer_name}', $customer->name, $messageTemplate);
        $formattedMessage = str_replace('{invoice_number}', $invoice->invoice_number, $formattedMessage);
        $formattedMessage = str_replace('{meter_number}', $invoice->meter->meter_number, $formattedMessage);
        $formattedMessage = str_replace('{invoice_date}', Carbon::parse($invoice->invoice_date)->format('d/m/Y'), $formattedMessage);
        $formattedMessage = str_replace('{invoice_amount}', number_format($totalAmount, 2), $formattedMessage);

        Log::info("InvoiceService: Generated message", [
            'customer' => $customer->name,
            'invoice_number' => $invoice->invoice_number,
            'message' => $formattedMessage
        ]);

        $messages = [];

        // Add primary phone number
        if ($customer->telephone) {
            $messages[] = [
                'phone' => $customer->telephone,
                'message' => $formattedMessage
            ];
        }

        // Add contact phone numbers
        $contacts = Contact::where('user_id', $customer->id)->get();
        foreach ($contacts as $contact) {
            $messages[] = [
                'phone' => $contact->phone,
                'message' => $formattedMessage
            ];
        }

        // Save messages to both CSV and database
        $filename = saveMessagesToCsv($invoice, $messages);

        Log::info("InvoiceService: Messages processed", [
            'customer' => $customer->name,
            'message_count' => count($messages),
            'file_path' => $filename
        ]);
    }
}
function saveMessagesToCsv($invoice, $messages)
{
    try {
        // Create a single CSV file with timestamp
        $filename = 'invoice_messages_' . now()->format('Y-m-d') . '.csv';
        $filepath = storage_path('app/public/messages/' . $filename);

        // Ensure directory exists
        if (!file_exists(storage_path('app/public/messages'))) {
            mkdir(storage_path('app/public/messages'), 0755, true);
        }

        // Check if file exists to determine if we need to write header
        $fileExists = file_exists($filepath);

        // Open file for appending
        $file = fopen($filepath, 'a');

        // Write CSV header only if file is new
        if (!$fileExists) {
            fputcsv($file, ['Invoice Number', 'Number', 'Message']);
        }

        // Write messages
        foreach ($messages as $msg) {
            $phone = $msg['phone'];
            $message = $msg['message'];
            // Write to CSV with invoice number
            fputcsv($file, [$invoice->invoice_number, $phone, $message]);

            Log::info("InvoiceService: Message saved to CSV", [
                'phone' => $phone,
                'file' => $filepath
            ]);
        }

        // Close file
        fclose($file);

        Log::info("InvoiceService: Messages saved to CSV file", [
            'file' => $filepath,
            'message_count' => count($messages)
        ]);

        return $filename;
    } catch (\Exception $e) {
        Log::error("InvoiceService: Error saving messages to CSV: " . $e->getMessage());
        throw $e;
    }
}
