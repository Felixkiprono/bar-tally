<?php

namespace Tests\Unit\Messaging;

use App\Models\Configuration;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Messages\MessagingService;
use App\Services\Messages\TemplateService;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagingServiceTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;
    protected User $customer;
    protected Meter $meter;
    protected MeterAssignment $assignment;
    protected MessagingService $messagingService;
    protected TemplateService $templateService;
    protected $mockSmsManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant
        $this->tenant = Tenant::factory()->create();

        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telephone' => '712345678',
        ]);

        // Create meter and assignment
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 1500.00,
            'overpayment' => 200.00,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
            'meter_id' => $this->meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Mock Auth
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Mock SmsManager
        $this->mockSmsManager = \Mockery::mock(SmsManager::class);
        
        // Initialize services with mocked SmsManager
        $this->messagingService = new MessagingService($this->mockSmsManager, app(TemplateService::class));
        $this->templateService = app(TemplateService::class);
    }

    #[Test]
    public function it_sends_message_to_customer_successfully()
    {
        // Mock SMS sending - phone is stored without country code in DB
        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->with('712345678', \Mockery::type('string'))
            ->andReturn(true);

        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: ['test' => true],
            appendFooter: false
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('messages', [
            'user_id' => $this->customer->id,
            'phone' => '712345678',
            'message' => 'Test message',
            'context' => 'GENERAL',
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function it_appends_footer_when_requested()
    {
        // Create footer configuration
        Configuration::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'sms_footer',
            'value' => 'Thank you!',
            'context' => 'messaging',
            'scope' => 'tenant',
            'is_active' => true,
        ]);

        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->withArgs(function ($phone, $message) {
                return $phone === '712345678' && 
                       str_contains($message, 'Test message') && 
                       str_contains($message, 'Thank you!');
            })
            ->andReturn(true);

        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: true
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_sends_to_customer_and_contacts()
    {
        // Add contacts
        Contact::factory()->create([
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'phone' => '798765432',
        ]);

        Contact::factory()->create([
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'phone' => '723456789',
        ]);

        // Expect 3 SMS to be sent (customer + 2 contacts)
        $this->mockSmsManager->shouldReceive('send')
            ->times(3)
            ->andReturn(true);

        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );

        $this->assertTrue($result);
        $this->assertEquals(3, Message::where('user_id', $this->customer->id)->count());
    }

    #[Test]
    public function it_deduplicates_phone_numbers()
    {
        // Add contact with same phone as customer
        Contact::factory()->create([
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'phone' => '712345678',  // Same as customer
        ]);

        // Should only send once
        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );

        $this->assertTrue($result);
        $this->assertEquals(1, Message::where('user_id', $this->customer->id)->count());
    }

    #[Test]
    public function it_prevents_sending_messages_with_unresolved_placeholders()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot send message with unresolved placeholders');

        $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Hello [CUSTOMER_NAME], your balance is [AMOUNT]',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );
    }

    #[Test]
    public function it_skips_duplicate_messages_within_24_hours()
    {
        // Send first message
        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );

        // Try to send same message again
        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );

        // Should skip duplicate
        $this->assertTrue($result);
        $this->assertEquals(1, Message::where('user_id', $this->customer->id)->count());
    }

    #[Test]
    public function it_handles_customer_without_phone_number()
    {
        $customerNoPhone = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'telephone' => null,
        ]);

        $this->mockSmsManager->shouldReceive('send')->never();

        $result = $this->messagingService->sendToCustomer(
            customer: $customerNoPhone,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );

        $this->assertFalse($result);
        $this->assertEquals(0, Message::where('user_id', $customerNoPhone->id)->count());
    }

    #[Test]
    public function it_creates_message_record_with_related_entity()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
        ]);

        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Invoice notification',
            context: 'INVOICE',
            relatedEntity: $invoice,
            metadata: ['invoice_id' => $invoice->id],
            appendFooter: false
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('messages', [
            'user_id' => $this->customer->id,
            'context' => 'INVOICE',
            'related_type' => Invoice::class,
            'related_id' => $invoice->id,
        ]);
    }

    #[Test]
    public function it_tracks_batch_id_for_related_messages()
    {
        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $batchId = 'test-batch-123';

        $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: ['batch_id' => $batchId],
            appendFooter: false
        );

        $this->assertDatabaseHas('messages', [
            'batch_id' => $batchId,
        ]);
    }

    #[Test]
    public function it_records_failed_sms_when_provider_fails()
    {
        $this->mockSmsManager->shouldReceive('send')
            ->once()
            ->andReturn(false);

        $result = $this->messagingService->sendToCustomer(
            customer: $this->customer,
            message: 'Test message',
            context: 'GENERAL',
            relatedEntity: null,
            metadata: [],
            appendFooter: false
        );

        $this->assertFalse($result);
        $this->assertDatabaseHas('messages', [
            'user_id' => $this->customer->id,
            'status' => 'failed',
        ]);
    }
}

