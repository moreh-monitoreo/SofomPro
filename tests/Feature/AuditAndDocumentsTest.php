<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Documentation;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class AuditAndDocumentsTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.documents' => 'local']);
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->role('Customer');
    }

    private function pendingLoan(): Loan
    {
        $customer = Customer::create([
            'status_id' => $this->customerStatus('LOAN CREATED')->id,
            'name' => 'Laura',
            'last_name' => 'Gómez',
            'gender' => 'Femenino',
            'email' => 'laura@example.com',
            'curp' => 'GOML900101MSLMRR01',
            'rfc' => 'GOML900101AB1',
        ]);

        return $this->readyForApproval(Loan::create(['customer_id' => $customer->id, 'type' => 'NORMAL', 'amount' => 3000, 'term' => 6, 'purpose' => 'Negocio']));
    }

    private function upload(Loan $loan, $user)
    {
        return $this->actingAs($user)->post("/loans/{$loan->id}/documents", [
            'documents' => [UploadedFile::fake()->create('ine.pdf', 100, 'application/pdf')],
            'documentsname' => ['INE'],
            'documentstype' => ['ine_front'],
        ], ['Accept' => 'application/json']);
    }

    // ---- Documentos ----------------------------------------------------------------------------

    public function test_a_document_can_be_deleted_while_the_request_is_not_approved(): void
    {
        $loan = $this->pendingLoan();
        $admin = $this->userWithRole('Administrator');
        $this->upload($loan, $admin)->assertOk();
        $document = $loan->documentations()->where('name', 'INE')->first();

        $this->actingAs($admin)->deleteJson("/loans/{$loan->id}/documents/{$document->id}")->assertOk();

        $this->assertDatabaseMissing('documentations', ['id' => $document->id]);
    }

    public function test_documents_cannot_be_deleted_after_approval_but_new_versions_can_be_added(): void
    {
        $loan = $this->pendingLoan();
        $admin = $this->userWithRole('Administrator');
        $this->upload($loan, $admin)->assertOk();
        $document = $loan->documentations()->where('name', 'INE')->first();

        $this->actingAs($admin)->putJson("/loans/{$loan->id}", ['status' => 'accepted'])->assertOk();

        $this->actingAs($admin)->deleteJson("/loans/{$loan->id}/documents/{$document->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El préstamo ya fue aprobado: sus documentos no se pueden eliminar. Sube una versión nueva si necesitas reemplazarlo.']);
        $this->assertDatabaseHas('documentations', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->path);

        $this->upload($loan, $admin)->assertOk();
        $this->assertSame(2, Documentation::where('loan_id', $loan->id)->where('name', 'INE')->count());
    }

    public function test_an_uploaded_document_records_who_uploaded_it(): void
    {
        $loan = $this->pendingLoan();
        $advisor = $this->userWithRole('Advisor');

        $this->upload($loan, $advisor)->assertOk();

        $this->assertSame($advisor->id, $loan->documentations()->where('name', 'INE')->first()->uploaded_by);
    }

    // ---- Bitácora ------------------------------------------------------------------------------

    public function test_approving_and_changing_conditions_leave_an_audit_trail(): void
    {
        $loan = $this->pendingLoan();
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->putJson("/loans/{$loan->id}", ['status' => 'accepted', 'amount' => 2000, 'term' => 9])->assertOk();

        $approved = AuditLog::where('action', 'loan.approved')->firstOrFail();
        $this->assertSame($admin->id, $approved->user_id);
        $this->assertSame($loan->id, $approved->loan_id);
        $this->assertEquals(['amount' => 2000, 'term' => 9], $approved->after);

        $changed = AuditLog::where('action', 'loan.conditions_changed')->firstOrFail();
        $this->assertEquals(['amount' => 3000, 'term' => 6], $changed->before);
        $this->assertEquals(['amount' => 2000, 'term' => 9], $changed->after);
    }

    public function test_a_rejection_records_the_reason(): void
    {
        $loan = $this->pendingLoan();

        $this->actingAs($this->userWithRole('Administrator'))->putJson("/loans/{$loan->id}", ['status' => 'rejected', 'reason' => 'Sin ingresos'])->assertOk();

        $this->assertSame('Sin ingresos', AuditLog::where('action', 'loan.rejected')->firstOrFail()->note);
    }

    public function test_payments_and_reversals_are_audited(): void
    {
        $built = $this->approvedLoan();
        $detail = $built['details'][0];
        $advisor = $this->userWithRole('Advisor');

        $this->actingAs($advisor)->postJson("/loans/{$built['loan']->id}/payments/{$detail->id}/register", [
            'method' => 'TRANSFERENCIA', 'reference' => 'SPEI-123',
        ])->assertOk();

        $paid = AuditLog::where('action', 'payment.registered')->firstOrFail();
        $this->assertSame($advisor->id, $paid->user_id);
        $this->assertSame('SPEI-123', $paid->after['reference']);

        $this->actingAs($this->userWithRole('Administrator'))->postJson("/admin/payments/{$detail->id}/revert")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.reverted', 'loan_id' => $built['loan']->id]);
    }

    public function test_the_audit_log_cannot_be_modified_or_deleted(): void
    {
        $log = AuditLog::create(['action' => 'loan.approved']);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_only_administrators_can_read_the_audit_log(): void
    {
        AuditLog::create(['action' => 'loan.approved', 'loan_id' => 1]);

        $this->actingAs($this->userWithRole('Advisor'))->get('/admin/audit')->assertForbidden();
        $this->actingAs($this->userWithRole('Administrator'))->get('/admin/audit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/AuditLog')->has('logs.data', 1));
    }
}
