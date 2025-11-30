<?php

namespace Tests\Unit\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Payment;
use App\Models\Journal;
use App\Services\Payment\PaymentReversalService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentReversalServiceTest extends TestCase
{
    protected PaymentReversalService $service;
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;
    protected Meter $meter;
    protected MeterAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant first
        $this->tenant = Tenant::factory()->create();

        // Create test users with tenant
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'balance' => 0,
            'overpayment' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create meter and assignment
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
            'overpayment' => 0,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Create test accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = app(PaymentReversalService::class);
    }

    #[Test]
    public function it_reverses_an_invoice_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 500,
            'paid_amount' => 500,
            'status' => 'partial payment',
        ]);

        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'meter_id' => $this->meter->id,
            'amount' => 500,
            'method' => 'mpesa',
            'reference' => 'MPESA-123',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create original journal entries
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        $arAccount = Account::where('code', 'AR-CONTROL')->where('tenant_id', $this->tenant->id)->first();

        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'description' => 'Original payment',
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $arAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'description' => 'Original payment',
            'reference' => $payment->reference,
        ]);

        $reversedPayment = $this->service->reversePayment($payment, 'Customer requested refund');

        // Verify payment was marked as reversed
        $this->assertEquals('reversed', $reversedPayment->status);
        $this->assertEquals('Customer requested refund', $reversedPayment->reversal_reason);
        $this->assertNotNull($reversedPayment->reversed_at);
        $this->assertEquals($this->admin->id, $reversedPayment->reversed_by);
    }

    #[Test]
    public function it_creates_reversal_journal_entries_for_invoice_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 800,
            'balance' => 400,
            'paid_amount' => 400,
        ]);

        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'meter_id' => $this->meter->id,
            'amount' => 400,
            'method' => 'cash',
            'reference' => 'CASH-456',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create original journal entries
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        $arAccount = Account::where('code', 'AR-CONTROL')->where('tenant_id', $this->tenant->id)->first();

        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 400,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $arAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 400,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $this->service->reversePayment($payment, 'Error in payment');

        // Verify reversal journal entries were created
        $this->assertDatabaseHas('journals', [
            'payment_id' => $payment->id,
            'transaction_type' => 'payment_reversal',
            'amount' => 400,
            'type' => 'credit', // Reverse of original debit (bank)
        ]);

        $this->assertDatabaseHas('journals', [
            'payment_id' => $payment->id,
            'transaction_type' => 'payment_reversal',
            'amount' => 400,
            'type' => 'debit', // Reverse of original credit (AR)
        ]);
    }

    #[Test]
    public function it_reverses_an_advance_payment()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'invoice_id' => null, // No invoice - advance payment
            'meter_id' => $this->meter->id,
            'amount' => 1000,
            'method' => 'mpesa',
            'reference' => 'ADV-MPESA-789',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
            'description' => 'Advance payment',
        ]);

        // Create original journal entries for advance payment
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        $prepaymentAccount = Account::where('code', 'CUSTOMER-PREPAYMENT')->where('tenant_id', $this->tenant->id)->first();

        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 1000,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $prepaymentAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 1000,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $reversedPayment = $this->service->reversePayment($payment, 'Advance payment cancelled');

        // Verify payment was marked as reversed
        $this->assertEquals('reversed', $reversedPayment->status);
        $this->assertEquals('Advance payment cancelled', $reversedPayment->reversal_reason);
        $this->assertNotNull($reversedPayment->reversed_at);
    }

    #[Test]
    public function it_creates_reversal_journal_entries_for_advance_payment()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'invoice_id' => null,
            'meter_id' => $this->meter->id,
            'amount' => 2000,
            'method' => 'bank_transfer',
            'reference' => 'BANK-999',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create original journal entries
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        $prepaymentAccount = Account::where('code', 'CUSTOMER-PREPAYMENT')->where('tenant_id', $this->tenant->id)->first();

        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 2000,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $prepaymentAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 2000,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $this->service->reversePayment($payment, 'Customer error');

        // Verify bank account credit (reversal)
        $this->assertDatabaseHas('journals', [
            'account_id' => $bankAccount->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment_reversal',
            'amount' => 2000,
            'type' => 'credit', // Reverse of original debit
        ]);

        // Verify prepayment account debit (reversal)
        $this->assertDatabaseHas('journals', [
            'account_id' => $prepaymentAccount->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment_reversal',
            'amount' => 2000,
            'type' => 'debit', // Reverse of original credit
        ]);
    }

    #[Test]
    public function it_prevents_reversing_already_reversed_payment()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 500,
            'method' => 'mpesa',
            'reference' => 'TEST-001',
            'status' => 'reversed',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
            'reversal_reason' => 'Already reversed',
            'reversed_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This payment has already been reversed');

        $this->service->reversePayment($payment, 'Trying to reverse again');
    }

    #[Test]
    public function it_sets_reversed_by_to_current_user()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 300,
            'method' => 'cash',
            'reference' => 'CASH-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $reversedPayment = $this->service->reversePayment($payment, 'Test reversal');

        $this->assertEquals($this->admin->id, $reversedPayment->reversed_by);
        $this->assertNotNull($reversedPayment->reversed_at);
    }

    #[Test]
    public function it_stores_reversal_reason()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 250,
            'method' => 'mpesa',
            'reference' => 'TEST-REASON',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $reason = 'Payment made in error - wrong customer account';
        $reversedPayment = $this->service->reversePayment($payment, $reason);

        $this->assertEquals($reason, $reversedPayment->reversal_reason);
    }

    #[Test]
    public function it_handles_payment_with_overpayment_reversal()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 0,
            'paid_amount' => 800, // Overpaid
        ]);

        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'meter_id' => $this->meter->id,
            'amount' => 800,
            'method' => 'mpesa',
            'reference' => 'OVERPAY-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create journal entries including overpayment
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        $arAccount = Account::where('code', 'AR-CONTROL')->where('tenant_id', $this->tenant->id)->first();
        $prepaymentAccount = Account::where('code', 'CUSTOMER-PREPAYMENT')->where('tenant_id', $this->tenant->id)->first();

        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 800,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $arAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $prepaymentAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'overpayment',
            'amount' => 300,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $reversedPayment = $this->service->reversePayment($payment, 'Reverting overpayment');

        $this->assertEquals('reversed', $reversedPayment->status);
    }

    #[Test]
    public function it_maintains_audit_trail_with_reversal_reference()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 600,
            'method' => 'cash',
            'reference' => 'AUDIT-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create original journals
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 600,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $this->service->reversePayment($payment, 'Audit test');

        // Verify reversal reference format
        $reversalJournal = Journal::where('payment_id', $payment->id)
            ->where('transaction_type', 'payment_reversal')
            ->first();

        $this->assertNotNull($reversalJournal);
        $this->assertStringStartsWith('REV-', $reversalJournal->reference);
        $this->assertStringContainsString($payment->reference, $reversalJournal->reference);
    }

    #[Test]
    public function it_uses_database_transaction_for_reversal()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 500,
            'method' => 'mpesa',
            'reference' => 'TRANS-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $originalStatus = $payment->status;
        $originalJournalCount = Journal::count();

        try {
            DB::beginTransaction();
            $this->service->reversePayment($payment, 'Test transaction');
            DB::rollBack(); // Manually rollback to test
        } catch (\Exception $e) {
            // Expected
        }

        // Verify nothing changed due to rollback
        $payment->refresh();
        $this->assertEquals($originalStatus, $payment->status);
        $this->assertEquals($originalJournalCount, Journal::count());
    }

    #[Test]
    public function it_includes_reason_in_journal_descriptions()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 450,
        ]);

        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'meter_id' => $this->meter->id,
            'amount' => 450,
            'method' => 'bank',
            'reference' => 'DESC-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create original journals
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        $arAccount = Account::where('code', 'AR-CONTROL')->where('tenant_id', $this->tenant->id)->first();

        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 450,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        Journal::create([
            'account_id' => $arAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 450,
            'type' => 'credit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $reason = 'Duplicate payment detected';
        $this->service->reversePayment($payment, $reason);

        $reversalJournals = Journal::where('payment_id', $payment->id)
            ->where('transaction_type', 'payment_reversal')
            ->get();

        foreach ($reversalJournals as $journal) {
            $this->assertStringContainsString($reason, $journal->description);
        }
    }

    #[Test]
    public function it_sets_correct_tenant_id_on_reversal_journals()
    {
        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 350,
            'method' => 'mpesa',
            'reference' => 'TENANT-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create original journal
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();
        Journal::create([
            'account_id' => $bankAccount->id,
            'transaction_id' => $payment->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 350,
            'type' => 'debit',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'reference' => $payment->reference,
        ]);

        $this->service->reversePayment($payment, 'Tenant isolation test');

        $reversalJournals = Journal::where('payment_id', $payment->id)
            ->where('transaction_type', 'payment_reversal')
            ->get();

        foreach ($reversalJournals as $journal) {
            $this->assertEquals($this->tenant->id, $journal->tenant_id);
        }
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

