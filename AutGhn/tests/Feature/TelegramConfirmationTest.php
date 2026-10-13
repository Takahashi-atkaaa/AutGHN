<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KienHang;
use App\Models\TinNhanTelegram;
use App\Services\AIOrderParserService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private int $chatId = 999888777;
    private int $messageId = 123;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock TelegramService
        $mockTelegram = new class extends TelegramService {
            public function __construct()
            {
            }

            public function guiTinNhan(string|int $chatId, string $noiDung): void
            {
            }

            public function guiTinNhanCoPhim(string|int $chatId, string $noiDung, array $phim): int
            {
                return 123;
            }

            public function traLoiCallback(string $callbackId, string $text = '', bool $hienThiAlert = false): void
            {
            }

            private function url(string $method): string
            {
                return "https://api.telegram.org/botMOCK/{$method}";
            }
        };
        $this->app->instance(TelegramService::class, $mockTelegram);

        // Mock AIOrderParserService
        $mockAI = new class extends AIOrderParserService {
            public function phanTich(string $noiDung): array
            {
                if (preg_match('/^\d+\s*[xX]/', $noiDung)) {
                    throw new \RuntimeException('Khong duoc goi AI de parse kich thuoc kien.');
                }

                return [
                    'customer_name' => 'a dong',
                    'phone' => '0906076222',
                    'address' => '123 duong ABC',
                    'items' => [['name' => '1 thung 32140', 'quantity' => 1]],
                    'cod' => null,
                    'note' => null,
                ];
            }
        };
        $this->app->instance(AIOrderParserService::class, $mockAI);
    }

    private function guiMessage(string $text = 'Dt:0906076222 a dong', ?int $messageId = null): void
    {
        $mid = $messageId ?? $this->messageId;
        $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => $mid,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => $text,
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);
    }

    private function guiCallback(string $data, ?int $messageId = null): \Illuminate\Testing\TestResponse
    {
        $mid = $messageId ?? $this->messageId;
        return $this->postJson('/api/telegram/webhook', [
            'callback_query' => [
                'id' => 'callback_' . uniqid(),
                'data' => $data,
                'message' => [
                    'message_id' => $mid,
                    'chat' => ['id' => $this->chatId],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);
    }

    public function test_xac_nhan_cap_nhat_trang_thai(): void
    {
        // Gui message de tao ban ghi
        $this->guiMessage();

        // Lay ban ghi vua tao
        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $this->assertNotNull($tinNhan);
        $this->assertEquals('da_phan_tich', $tinNhan->trang_thai);

        // Gui callback xac nhan
        $response = $this->guiCallback('xac_nhan');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $tinNhan->refresh();
        $this->assertEquals('da_xac_nhan', $tinNhan->trang_thai);
    }

    public function test_huy_cap_nhat_trang_thai(): void
    {
        // Gui message de tao ban ghi
        $this->guiMessage();

        // Lay ban ghi vua tao
        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $this->assertNotNull($tinNhan);
        $this->assertEquals('da_phan_tich', $tinNhan->trang_thai);

        // Gui callback huy
        $response = $this->guiCallback('huy');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $tinNhan->refresh();
        $this->assertEquals('da_huy', $tinNhan->trang_thai);
    }

    public function test_callback_sai_chat_khong_xu_ly(): void
    {
        // Tao ban ghi voi chat_id khac
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '999888777_123',
            'ma_kenh' => '999888777',
            'ten_nguoi_gui' => 'testuser',
            'noi_dung_goc' => 'test',
            'du_lieu_ai' => ['items' => []],
            'trang_thai' => 'da_phan_tich',
        ]);

        // Gui callback tu chat khac
        $response = $this->postJson('/api/telegram/webhook', [
            'callback_query' => [
                'id' => 'callback_test',
                'data' => 'xac_nhan',
                'message' => [
                    'message_id' => 123,
                    'chat' => ['id' => 999999999], // Chat khac
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertStatus(200);

        // Trang thai khong thay doi
        $tinNhan->refresh();
        $this->assertEquals('da_phan_tich', $tinNhan->trang_thai);
    }

    public function test_callback_sai_trang_thai_khong_xu_ly(): void
    {
        // Tao ban ghi da xac nhan
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '999888777_123',
            'ma_kenh' => '999888777',
            'ten_nguoi_gui' => 'testuser',
            'noi_dung_goc' => 'test',
            'du_lieu_ai' => ['items' => []],
            'trang_thai' => 'da_xac_nhan',
        ]);

        // Gui callback huy (khong lam gi vi da xac nhan roi)
        $response = $this->guiCallback('huy');

        $response->assertStatus(200);

        // Trang thai van la da_xac_nhan
        $tinNhan->refresh();
        $this->assertEquals('da_xac_nhan', $tinNhan->trang_thai);
    }

    public function test_sua_sdt_chuan_hoa_so(): void
    {
        // Tao ban ghi dang cho sua
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '999888777_123',
            'ma_kenh' => '999888777',
            'ten_nguoi_gui' => 'testuser',
            'noi_dung_goc' => 'test',
            'du_lieu_ai' => [
                'customer_name' => 'a dong',
                'phone' => '0906076222',
                'address' => '123 duong ABC',
                'items' => [['name' => '1 thung 32140', 'quantity' => 1]],
                'cod' => null,
                'note' => null,
                '_trang_thai_bot' => ['dang_sua_field' => 'sdt'],
            ],
            'trang_thai' => 'cho_sua',
        ]);

        // Gui tin nhan tra loi voi SDT co khoang trang
        $this->guiMessage('0901 234 567', 124);

        $tinNhan->refresh();
        $this->assertEquals('da_phan_tich', $tinNhan->trang_thai);
        $this->assertEquals('0901234567', $tinNhan->du_lieu_ai['phone']);
    }

    public function test_sua_cod_chuan_hoa(): void
    {
        // Tao ban ghi dang cho sua
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '999888777_123',
            'ma_kenh' => '999888777',
            'ten_nguoi_gui' => 'testuser',
            'noi_dung_goc' => 'test',
            'du_lieu_ai' => [
                'customer_name' => 'a dong',
                'phone' => '0906076222',
                'address' => '123 duong ABC',
                'items' => [['name' => '1 thung 32140', 'quantity' => 1]],
                'cod' => null,
                'note' => null,
                '_trang_thai_bot' => ['dang_sua_field' => 'cod'],
            ],
            'trang_thai' => 'cho_sua',
        ]);

        // Gui tin nhan tra loi voi COD 350k
        $this->guiMessage('350k', 124);

        $tinNhan->refresh();
        $this->assertEquals(350000.0, $tinNhan->du_lieu_ai['cod']);
    }

    public function test_sua_hang_chuan_hoa(): void
    {
        // Tao ban ghi dang cho sua
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '999888777_123',
            'ma_kenh' => '999888777',
            'ten_nguoi_gui' => 'testuser',
            'noi_dung_goc' => 'test',
            'du_lieu_ai' => [
                'customer_name' => 'a dong',
                'phone' => '0906076222',
                'address' => '123 duong ABC',
                'items' => [['name' => '1 thung 32140', 'quantity' => 1]],
                'cod' => null,
                'note' => null,
                '_trang_thai_bot' => ['dang_sua_field' => 'hang'],
            ],
            'trang_thai' => 'cho_sua',
        ]);

        // Gui tin nhan tra loi voi nhieu mat hang
        $this->guiMessage("2 x ao phong\n1 x quan jean", 124);

        $tinNhan->refresh();
        $this->assertEquals([
            ['name' => 'ao phong', 'quantity' => 2],
            ['name' => 'quan jean', 'quantity' => 1],
        ], $tinNhan->du_lieu_ai['items']);
    }

    public function test_webhook_sai_secret_tra_403(): void
    {
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 123,
                'chat' => ['id' => 999888777],
                'from' => ['id' => 111222333],
                'date' => time(),
                'text' => 'test',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'sai_secret',
        ]);

        $response->assertStatus(403);
    }

    public function test_callback_field_khong_hop_le(): void
    {
        // Gui message de tao ban ghi
        $this->guiMessage();

        // Lay ban ghi
        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();

        // Gui callback voi field khong hop le
        $response = $this->guiCallback('sua:field_khong_ton_tai');

        $response->assertStatus(200);

        // Trang thai khong doi
        $tinNhan->refresh();
        $this->assertEquals('da_phan_tich', $tinNhan->trang_thai);
    }

    public function test_sau_xac_nhan_nhap_kien_khong_goi_ai(): void
    {
        $this->guiMessage();
        $this->guiCallback('xac_nhan');

        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $this->assertEquals('da_xac_nhan', $tinNhan->trang_thai);

        $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();
        $this->assertNotNull($donHang);
        $this->assertEquals('cho_kien_hang', $donHang->trang_thai);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 200,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '20x30x10 500g',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertEquals('da_nhap_kien', $donHang->trang_thai);

        $kienHang = KienHang::where('don_hang_id', $donHang->id)->first();
        $this->assertNotNull($kienHang);
        $this->assertEquals(20, $kienHang->dai);
        $this->assertEquals(30, $kienHang->rong);
        $this->assertEquals(10, $kienHang->cao);
        $this->assertEquals(500, $kienHang->can_nang);
    }

    /**
     * Khi user chi nhap kich thuoc ma khong co can nang, bot phai doi trang thai don_hang
     * sang cho_can_nang va luu tam kich thuoc vao du_lieu_ai.
     * Khong tao KienHang ngay.
     */
    public function test_thieu_can_nang_hoi_lai(): void
    {
        $this->guiMessage();
        $this->guiCallback('xac_nhan');

        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();
        $this->assertEquals('cho_kien_hang', $donHang->trang_thai);

        // User chi nhap kich thuoc, khong co can nang
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 201,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '20x30x10',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertEquals('cho_can_nang', $donHang->trang_thai);

        // KienHang chua duoc tao
        $kienHang = KienHang::where('don_hang_id', $donHang->id)->first();
        $this->assertNull($kienHang);

        // Kich thuoc duoc luu tam trong du_lieu_ai
        $tinNhan->refresh();
        $this->assertArrayHasKey('_kich_thuoc_cho_can_nang', $tinNhan->du_lieu_ai ?? []);
        $this->assertEquals(20, $tinNhan->du_lieu_ai['_kich_thuoc_cho_can_nang']['dai']);
        $this->assertEquals(30, $tinNhan->du_lieu_ai['_kich_thuoc_cho_can_nang']['rong']);
        $this->assertEquals(10, $tinNhan->du_lieu_ai['_kich_thuoc_cho_can_nang']['cao']);
    }

    /**
     * Sau khi duoc hoi can nang, user nhap "1.2kg" -> parse thanh 1200g, KienHang duoc tao.
     */
    public function test_can_nang_don_vi_kg(): void
    {
        $this->guiMessage();
        $this->guiCallback('xac_nhan');

        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();

        // Buoc 1: nhap kich thuoc (khong can nang)
        $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 201,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '20x30x10',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $donHang->refresh();
        $this->assertEquals('cho_can_nang', $donHang->trang_thai);

        // Buoc 2: nhap can nang bang kg
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 202,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '1.2kg',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertEquals('da_nhap_kien', $donHang->trang_thai);

        $kienHang = KienHang::where('don_hang_id', $donHang->id)->first();
        $this->assertNotNull($kienHang);
        $this->assertEquals(20, $kienHang->dai);
        $this->assertEquals(30, $kienHang->rong);
        $this->assertEquals(10, $kienHang->cao);
        $this->assertEquals(1200, $kienHang->can_nang);

        // Co tam trong du_lieu_ai phai duoc xoa
        $tinNhan->refresh();
        $this->assertArrayNotHasKey('_kich_thuoc_cho_can_nang', $tinNhan->du_lieu_ai ?? []);
    }

    /**
     * Khi user nhap chuoi khong phai kich thuoc (VD: "abc"), bot phai bao sai dinh dang
     * va khong thay doi trang thai.
     */
    public function test_kich_thuoc_sai_dinh_dang_khong_tao_kien(): void
    {
        $this->guiMessage();
        $this->guiCallback('xac_nhan');

        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();
        $this->assertEquals('cho_kien_hang', $donHang->trang_thai);

        // Nhap sai dinh dang
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 201,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => 'abc xyz',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Trang thai van nguyen (cho_kien_hang)
        $donHang->refresh();
        $this->assertEquals('cho_kien_hang', $donHang->trang_thai);

        // KienHang khong duoc tao
        $this->assertNull(KienHang::where('don_hang_id', $donHang->id)->first());

        // Co tam khong xuat hien
        $tinNhan->refresh();
        $this->assertArrayNotHasKey('_kich_thuoc_cho_can_nang', $tinNhan->du_lieu_ai ?? []);
    }

    /**
     * Lenh /cancel khi chua co thao tac nao dang thuc hien -> thong bao khong co gi de huy.
     */
    public function test_cancel_khi_khong_co_thao_tac(): void
    {
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 300,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '/cancel',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Khong co ban ghi tin_nhan nao duoc tao o trang thai da_phan_tich/da_xac_nhan
        $this->assertNull(
            TinNhanTelegram::where('ma_kenh', (string) $this->chatId)
                ->whereIn('trang_thai', ['da_phan_tich', 'da_xac_nhan', 'cho_sua'])
                ->first()
        );
    }

    /**
     * Lenh /cancel o trang thai da_xac_nhan (cho nhap kich thuoc) phai:
     * - Danh dau tin_nhan.trang_thai = 'da_huy'
     * - Danh dau don_hang.trang_thai = 'da_huy'
     * - Xoa KienHang lien ket neu co
     */
    public function test_cancel_huy_don_hang_va_xoa_kien(): void
    {
        // Setup: xac nhan don + nhap kich thuoc de co KienHang
        $this->guiMessage();
        $this->guiCallback('xac_nhan');

        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();
        $this->assertEquals('da_xac_nhan', $tinNhan->trang_thai);
        $this->assertEquals('cho_kien_hang', $donHang->trang_thai);

        // Nhap kich thuoc de tao KienHang
        $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 201,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '20x30x10 500g',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $donHang->refresh();
        $this->assertEquals('da_nhap_kien', $donHang->trang_thai);
        $this->assertNotNull(KienHang::where('don_hang_id', $donHang->id)->first());

        // Gui /cancel
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 301,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '/cancel',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // tin_nhan da huy
        $tinNhan->refresh();
        $this->assertEquals('da_huy', $tinNhan->trang_thai);

        // don_hang da huy
        $donHang->refresh();
        $this->assertEquals('da_huy', $donHang->trang_thai);

        // KienHang da bi xoa
        $this->assertNull(KienHang::where('don_hang_id', $donHang->id)->first());
    }

    /**
     * Lenh /cancel o trang thai cho_can_nang phai:
     * - Danh dau tin_nhan.trang_thai = 'da_huy'
     * - Danh dau don_hang.trang_thai = 'da_huy'
     * - Xoa co tam _kich_thuoc_cho_can_nang trong du_lieu_ai
     */
    public function test_cancel_trang_thai_cho_can_nang(): void
    {
        $this->guiMessage();
        $this->guiCallback('xac_nhan');

        $tinNhan = TinNhanTelegram::where('ma_kenh', (string) $this->chatId)->first();
        $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();

        // Nhap kich thuoc (khong co can nang) -> cho_can_nang
        $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 201,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '20x30x10',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $donHang->refresh();
        $this->assertEquals('cho_can_nang', $donHang->trang_thai);

        // Kich thuoc tam da duoc luu
        $tinNhan->refresh();
        $this->assertArrayHasKey('_kich_thuoc_cho_can_nang', $tinNhan->du_lieu_ai ?? []);

        // Gui /cancel
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 301,
                'chat' => ['id' => $this->chatId],
                'from' => ['id' => 111222333, 'first_name' => 'Test', 'username' => 'testuser'],
                'date' => time(),
                'text' => '/cancel',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_secret',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $tinNhan->refresh();
        $this->assertEquals('da_huy', $tinNhan->trang_thai);

        $donHang->refresh();
        $this->assertEquals('da_huy', $donHang->trang_thai);

        // Co tam da bi xoa
        $this->assertArrayNotHasKey('_kich_thuoc_cho_can_nang', $tinNhan->du_lieu_ai ?? []);

        // KienHang chua tung duoc tao
        $this->assertNull(KienHang::where('don_hang_id', $donHang->id)->first());
    }
}
