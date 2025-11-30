<?php

namespace App\Utilities;

use App\Jobs\SendSmsJob;
use App\Models\Configuration;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\MeterConfiguration;
use App\Services\Messages\MessageResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Utils
{


    public static function saveGeneratedMessagesToCsv($records, $title)
    {
        try {
            // Create a single CSV file with timestamp
            $filename = $title . '_' . now()->format('Y-m-d') . '.csv';
            $filepath = 'messages/' . $filename;
            $fullpath = storage_path('app/public/' . $filepath);

            // Ensure directory exists
            if (!file_exists(storage_path('app/public/messages'))) {
                mkdir(storage_path('app/public/messages'), 0755, true);
            }

            // Check if file exists to determine if we need to write header
            $fileExists = file_exists($fullpath);

            // Open file for appending
            $file = fopen($fullpath, 'a');

            // Write CSV header only if file is new
            if (!$fileExists) {
                fputcsv($file, ['Number', 'Message']);
            }

            // Write messages
            foreach ($records as $record) {
                $phone = $record['phone'];
                $message = $record['message'];
                // Write to CSV with invoice number
                fputcsv($file, [$phone, $message]);
            }

            // Close file
            fclose($file);

            \Log::info("GenerateMessageJob Messages saved to CSV file", [
                'file' => $fullpath,
                'message_count' => count($records)
            ]);

            // Return the path that can be used to generate a URL
            return $filepath;
        } catch (\Exception $e) {
            \Log::error("GenerateMessageJob: Error saving messages to CSV: " . $e->getMessage());
            throw $e;
        }
    }

    public static function notifyCustomer($customer, $meterAssignment, $invoice, $bills, $template = null): void
    {
        try {
            // Get fresh invoice data
            $invoice = Invoice::find($invoice->id);
            if (!$invoice) {
                Log::warning('Utils: Invoice not found', ['invoice_id' => $invoice->id]);
                return;
            }

            // Fetch INVOICE template from database
            $messageTemplate = MessageTemplate::where('tenant_id', $customer->tenant_id)
                ->where('context', 'INVOICE')
                ->where('is_system', true)
                ->where('is_active', true)
                ->first();

            if (!$messageTemplate) {
                Log::warning('Utils: No INVOICE template found', [
                    'tenant_id' => $customer->tenant_id,
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Use MessageResolver to populate template tags
            $messageResolver = app(MessageResolver::class);
            $message = $messageResolver->resolveInvoiceMessage($invoice, $messageTemplate);

            Log::info('Utils: Invoice notification queued', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            // Dispatch SendSmsJob to queue (async)
            SendSmsJob::dispatch(
                customer: $customer,
                message: $message,
                context: 'INVOICE',
                relatedEntity: $invoice,
                metadata: [
                    'is_system' => true,
                    'invoice_id' => $invoice->id,
                    'sent_by' => Auth::id(),
                ],
                appendFooter: true
            );

        } catch (\Exception $e) {
            Log::error('Utils: Failed to send invoice notification', [
                'customer_id' => $customer->id ?? null,
                'invoice_id' => $invoice->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
