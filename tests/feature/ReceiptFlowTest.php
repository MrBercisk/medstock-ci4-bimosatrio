<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Menguji alur penerimaan obat lewat HTTP pada database *_test.
 *
 * @internal
 */
final class ReceiptFlowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    // Tanggal tetap supaya hasil test tidak bergantung pada hari ini
    private const ON_DATE = '2026-10-03';

    private const OFFICER    = 'petugas@farmasi.test';
    private const SUPERVISOR = 'supervisor@farmasi.test';

    private const PARACETAMOL = 101;
    private const AMOXICILLIN = 102;
    private const SALBUTAMOL  = 103;
    private const IBUPROFEN   = 104;
    private const INACTIVE    = 105;
    private const CETIRIZINE  = 106;
    private const LORATADINE  = 107;

    /** @var array<string,int> email => id pengguna */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $db   = db_connect();
        $name = $db->getDatabase();

        // Test menghapus data penerimaan, jadi tidak boleh jalan di database asli
        if (! str_ends_with($name, '_test')) {
            $this->fail("Dihentikan: database '{$name}' bukan database test (nama harus berakhiran _test).");
        }

        $db->table('receipt_logs')->emptyTable();
        $db->table('receipt_items')->emptyTable();
        $db->table('receipts')->emptyTable();

        $this->ensureDemoUsers();
    }

    // Laporan stok

    public function testSeedStockMatchesProblemExamples(): void
    {
        $expectedAvailable = [
            self::PARACETAMOL => 134,
            self::AMOXICILLIN => 16,
            self::SALBUTAMOL  => 15,
            self::IBUPROFEN   => 3,
            self::CETIRIZINE  => 0,
            self::LORATADINE  => 0,
        ];

        foreach ($expectedAvailable as $medicineId => $available) {
            $this->assertSame($available, $this->stockOf($medicineId)['available_quantity'], "obat {$medicineId}");
        }

        $paracetamol = $this->stockOf(self::PARACETAMOL);
        $this->assertSame(142, $paracetamol['physical_quantity']);
        $this->assertSame(8, $paracetamol['expired_quantity']);
        $this->assertSame('PCT-2501', $paracetamol['expired_batches'][0]['batch_no']);

        $loratadine = $this->stockOf(self::LORATADINE);
        $this->assertSame(6, $loratadine['physical_quantity']);
        $this->assertSame(6, $loratadine['expired_quantity']);

        // Obat tanpa batch tetap tampil
        $this->assertSame([], $this->stockOf(self::CETIRIZINE)['available_batches']);
    }

    public function testInactiveMedicinesAreNotListed(): void
    {
        $medicineIds = array_column($this->fetchStockReport(), 'medicine_id');

        foreach ([self::INACTIVE, 124, 125] as $inactiveId) {
            $this->assertNotContains($inactiveId, $medicineIds);
        }

        $this->assertCount(22, $medicineIds);
    }

    public function testBatchIsStillAvailableOnItsExpiryDate(): void
    {
        $onExpiryDay = $this->stockOf(self::PARACETAMOL, '2026-09-30');
        $this->assertSame(142, $onExpiryDay['available_quantity']);
        $this->assertSame(0, $onExpiryDay['expired_quantity']);

        $dayAfter = $this->stockOf(self::PARACETAMOL, '2026-10-01');
        $this->assertSame(134, $dayAfter['available_quantity']);
        $this->assertSame(8, $dayAfter['expired_quantity']);
    }

    public function testInvalidOnDateIsRejected(): void
    {
        foreach (['abc', '2026-02-31'] as $badDate) {
            $response = $this->sendRequest('GET', 'api/stocks', self::OFFICER, ['on_date' => $badDate]);
            $this->assertStatus($response, 422);
        }
    }

    // Wajib login

    public function testRequestsWithoutLoginAreRejected(): void
    {
        $before = $this->tableCounts();

        $this->assertStatus($this->sendRequest('GET', 'api/stocks'), 401);
        $this->assertStatus($this->sendRequest('GET', 'api/receipts'), 401);
        $this->assertStatus($this->sendRequest('GET', 'api/receipts/1'), 401);
        $this->assertStatus($this->sendRequest('POST', 'api/receipts', null, $this->receiptPayload()), 401);
        $this->assertStatus($this->sendRequest('PUT', 'api/receipts/1', null, $this->receiptPayload()), 401);

        $this->assertSame($before, $this->tableCounts());
    }

    // Skenario 1-3: buat, ubah, ubah lagi

    public function testCreateUpdateAndRepeatedUpdateKeepStockCorrect(): void
    {
        $id = $this->createReceipt(self::OFFICER);

        $this->assertAvailableStock([
            self::PARACETAMOL => 144,
            self::IBUPROFEN   => 8,
        ]);
        $this->assertSame(8, $this->stockOf(self::PARACETAMOL)['expired_quantity']);

        $update = $this->receiptPayload(['items' => [
            self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 7),
            self::item(self::SALBUTAMOL, 'SAL-2601', '2027-11-30', 3),
        ]]);
        $expectedStock = [
            self::PARACETAMOL => 141,
            self::SALBUTAMOL  => 18,
            self::IBUPROFEN   => 3,
        ];

        $this->assertStatus($this->sendRequest('PUT', "api/receipts/{$id}", self::OFFICER, $update), 200);
        $this->assertAvailableStock($expectedStock);

        // PUT yang sama tidak boleh menggandakan stok
        $this->assertStatus($this->sendRequest('PUT', "api/receipts/{$id}", self::OFFICER, $update), 200);
        $this->assertAvailableStock($expectedStock);

        $this->assertCount(2, $this->receiptDetail($id, self::OFFICER)['items']);
    }

    // Skenario 4: pembuat dan pengubah terakhir

    public function testSupervisorEditKeepsCreatorAndRecordsLastEditor(): void
    {
        $id = $this->createReceipt(self::OFFICER);

        $this->assertNull($this->receiptDetail($id, self::OFFICER)['updated_by']);

        $edit = $this->receiptPayload(['items' => [self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 12)]]);
        $this->assertStatus($this->sendRequest('PUT', "api/receipts/{$id}", self::SUPERVISOR, $edit), 200);

        $detail = $this->receiptDetail($id, self::OFFICER);

        $this->assertSame($this->userIds[self::OFFICER], $detail['created_by']['id']);
        $this->assertSame($this->userIds[self::SUPERVISOR], $detail['updated_by']['id']);
        $this->assertNotNull($detail['updated_at']);

        $this->assertCount(2, $detail['history']);
        $this->assertSame('create', $detail['history'][0]['action']);
        $this->assertSame($this->userIds[self::OFFICER], $detail['history'][0]['user']['id']);
        $this->assertSame('update', $detail['history'][1]['action']);
        $this->assertSame($this->userIds[self::SUPERVISOR], $detail['history'][1]['user']['id']);
    }

    // Skenario 5: petugas tidak boleh mengubah milik supervisor

    public function testOfficerCanViewButNotEditSupervisorReceipt(): void
    {
        $id = $this->createReceipt(self::SUPERVISOR);

        $list = $this->sendRequest('GET', 'api/receipts', self::OFFICER);
        $this->assertStatus($list, 200);
        $this->assertContains($id, array_column($this->json($list)['data'], 'id'));

        $detail = $this->sendRequest('GET', "api/receipts/{$id}", self::OFFICER);
        $this->assertStatus($detail, 200);
        $this->assertFalse($this->json($detail)['data']['can_update']);

        $countsBefore = $this->tableCounts();
        $stockBefore  = $this->stockOf(self::PARACETAMOL)['available_quantity'];

        // Peran dan pembuat di body tidak boleh memengaruhi hak akses
        $attack = $this->receiptPayload([
            'items'      => [self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 99)],
            'created_by' => $this->userIds[self::OFFICER],
            'role'       => 'pharmacy_supervisor',
        ]);
        $this->assertStatus($this->sendRequest('PUT', "api/receipts/{$id}", self::OFFICER, $attack), 403);

        $this->assertSame($countsBefore, $this->tableCounts());
        $this->assertSame($stockBefore, $this->stockOf(self::PARACETAMOL)['available_quantity']);

        $after = $this->receiptDetail($id, self::SUPERVISOR);
        $this->assertNull($after['updated_by']);
        $this->assertCount(1, $after['history']);
        $this->assertTrue($after['can_update']);
    }

    public function testUnknownReceiptReturnsNotFound(): void
    {
        $this->assertStatus($this->sendRequest('GET', 'api/receipts/999999', self::OFFICER), 404);
        $this->assertStatus($this->sendRequest('PUT', 'api/receipts/999999', self::OFFICER, $this->receiptPayload()), 404);
    }

    // Validasi

    /** @param array<string,mixed> $override */
    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadIsRejected(array $override): void
    {
        $response = $this->sendRequest('POST', 'api/receipts', self::OFFICER, $this->receiptPayload($override));
        $this->assertStatus($response, 422);

        $this->assertSame(['receipts' => 0, 'items' => 0, 'logs' => 0], $this->tableCounts());
        $this->assertSame(134, $this->stockOf(self::PARACETAMOL)['available_quantity']);
    }

    /** @return array<string, array{0: array<string,mixed>}> */
    public static function invalidPayloads(): array
    {
        return [
            'quantity nol'     => [['items' => [self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 0)]]],
            'quantity negatif' => [['items' => [self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', -3)]]],
            'quantity desimal' => [['items' => [self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 1.5)]]],

            'items kosong'     => [['items' => []]],
            'item tanpa field' => [['items' => [[]]]],

            'pemasok nonaktif'  => [['supplier_id' => 3]],
            'pemasok tidak ada' => [['supplier_id' => 99]],

            'obat nonaktif'  => [['items' => [self::item(self::INACTIVE, 'NON-001', '2028-01-01', 5)]]],
            'obat tidak ada' => [['items' => [self::item(999, 'XXX-001', '2028-01-01', 5)]]],

            'batch ganda dalam payload' => [['items' => [
                self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 1),
                self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 2),
            ]]],
            'kedaluwarsa beda dari stok awal'  => [['items' => [self::item(self::PARACETAMOL, 'PCT-2601', '2028-01-01', 5)]]],
            'kedaluwarsa pada hari penerimaan' => [['items' => [self::item(self::CETIRIZINE, 'CET-2601', '2026-10-03', 5)]]],
            'batch yang sudah kedaluwarsa'     => [['items' => [self::item(self::PARACETAMOL, 'PCT-2501', '2026-09-30', 5)]]],

            // -05:00 sudah jadi 4 Oktober di Jakarta
            'tanggal Jakarta sudah 4 Oktober' => [[
                'received_at' => '2026-10-03T23:30:00-05:00',
                'items'       => [self::item(self::CETIRIZINE, 'CET-2601', '2026-10-04', 5)],
            ]],
            'received_at tanpa zona waktu' => [['received_at' => '2026-10-03 10:00:00']],

            'satu item benar, satu salah' => [['items' => [
                self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 10),
                self::item(self::INACTIVE, 'NON-001', '2028-01-01', 5),
            ]]],
        ];
    }

    public function testDuplicateReferenceIsRejected(): void
    {
        $this->createReceipt(self::OFFICER);
        $before = $this->tableCounts();

        $other = $this->receiptPayload(['items' => [self::item(self::AMOXICILLIN, 'AMX-2601', '2027-05-31', 5)]]);
        $this->assertStatus($this->sendRequest('POST', 'api/receipts', self::OFFICER, $other), 422);

        $this->assertSame($before, $this->tableCounts());
    }

    public function testUpdateRejectsReferenceOfAnotherReceipt(): void
    {
        $this->createReceipt(self::OFFICER);

        $secondPayload = $this->receiptPayload([
            'reference_no' => 'PB-002',
            'items'        => [self::item(self::AMOXICILLIN, 'AMX-2601', '2027-05-31', 5)],
        ]);
        $secondId = $this->createReceipt(self::OFFICER, $secondPayload);

        $clash = array_replace($secondPayload, ['reference_no' => 'PB-001']);

        $this->assertStatus($this->sendRequest('PUT', "api/receipts/{$secondId}", self::OFFICER, $clash), 422);
    }

    public function testFailedUpdateLeavesReceiptAndStockUntouched(): void
    {
        $id     = $this->createReceipt(self::OFFICER);
        $before = $this->tableCounts();

        $bad = $this->receiptPayload(['items' => [
            self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 50),
            self::item(self::INACTIVE, 'NON-001', '2028-01-01', 5),
        ]]);
        $this->assertStatus($this->sendRequest('PUT', "api/receipts/{$id}", self::OFFICER, $bad), 422);

        $this->assertSame($before, $this->tableCounts());
        $this->assertAvailableStock([
            self::PARACETAMOL => 144,
            self::SALBUTAMOL  => 15,
            self::IBUPROFEN   => 8,
        ]);
    }

    // Helper

    private function ensureDemoUsers(): void
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $accounts = [
            self::OFFICER    => ['Petugas Penerimaan', 'receiving_officer'],
            self::SUPERVISOR => ['Supervisor Farmasi', 'pharmacy_supervisor'],
        ];

        foreach ($accounts as $email => [$name, $role]) {
            $row = $db->table('users')->where('email', $email)->get()->getRowArray();

            if ($row === null) {
                $db->table('users')->insert([
                    'name'          => $name,
                    'email'         => $email,
                    'password_hash' => password_hash('test-only', PASSWORD_DEFAULT),
                    'role'          => $role,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $this->userIds[$email] = (int) $db->insertID();
            } else {
                $this->userIds[$email] = (int) $row['id'];
            }
        }
    }

    /** @param array<string,mixed> $override */
    private function receiptPayload(array $override = []): array
    {
        return array_replace([
            'reference_no' => 'PB-001',
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03T10:00:00+07:00',
            'items'        => [
                self::item(self::PARACETAMOL, 'PCT-2601', '2027-12-31', 10),
                self::item(self::IBUPROFEN, 'IBU-2602', '2028-06-30', 5),
            ],
        ], $override);
    }

    private static function item(int $medicineId, string $batchNo, string $expiresOn, int|float $quantity): array
    {
        return [
            'medicine_id' => $medicineId,
            'batch_no'    => $batchNo,
            'expires_on'  => $expiresOn,
            'quantity'    => $quantity,
        ];
    }

    /**
     * Identitas hanya lewat sesi ($as = null berarti tanpa login).
     * Untuk GET, $data jadi query; untuk POST/PUT jadi body JSON.
     *
     * @param array<string,mixed> $data
     */
    private function sendRequest(string $method, string $path, ?string $as = null, array $data = []): TestResponse
    {
        $request = $this->withSession($as === null ? [] : ['user_id' => $this->userIds[$as]]);

        if ($method === 'GET') {
            return $request->get($path, $data);
        }

        return $request->withBodyFormat('json')->call(strtolower($method), $path, $data);
    }

    /** @param array<string,mixed>|null $payload */
    private function createReceipt(string $as, ?array $payload = null): int
    {
        $response = $this->sendRequest('POST', 'api/receipts', $as, $payload ?? $this->receiptPayload());
        $this->assertStatus($response, 201);

        return $this->json($response)['data']['id'];
    }

    /** @return array<string,mixed> */
    private function receiptDetail(int $id, string $as): array
    {
        return $this->json($this->sendRequest('GET', "api/receipts/{$id}", $as))['data'];
    }

    /** @return array<int, array<string,mixed>> */
    private function fetchStockReport(string $onDate = self::ON_DATE): array
    {
        $response = $this->sendRequest('GET', 'api/stocks', self::OFFICER, ['on_date' => $onDate]);
        $this->assertStatus($response, 200);

        return $this->json($response)['data']['medicines'];
    }

    /** @return array<string,mixed> */
    private function stockOf(int $medicineId, string $onDate = self::ON_DATE): array
    {
        foreach ($this->fetchStockReport($onDate) as $medicine) {
            if ($medicine['medicine_id'] === $medicineId) {
                return $medicine;
            }
        }

        $this->fail("Obat {$medicineId} tidak ada di laporan stok.");
    }

    /** @param array<int,int> $expected medicine_id => jumlah tersedia */
    private function assertAvailableStock(array $expected): void
    {
        foreach ($expected as $medicineId => $quantity) {
            $this->assertSame(
                $quantity,
                $this->stockOf($medicineId)['available_quantity'],
                "stok tersedia obat {$medicineId}",
            );
        }
    }

    private function assertStatus(TestResponse $response, int $status): void
    {
        $this->assertSame($status, $response->response()->getStatusCode(), (string) $response->getJSON());
    }

    /** @return array<string,mixed> */
    private function json(TestResponse $response): array
    {
        return json_decode((string) $response->getJSON(), true) ?? [];
    }

    /** @return array{receipts:int, items:int, logs:int} */
    private function tableCounts(): array
    {
        $db = db_connect();

        return [
            'receipts' => $db->table('receipts')->countAllResults(),
            'items'    => $db->table('receipt_items')->countAllResults(),
            'logs'     => $db->table('receipt_logs')->countAllResults(),
        ];
    }
}
