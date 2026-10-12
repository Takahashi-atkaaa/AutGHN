# Ban giao AutGHN cho Cursor

## Muc tieu san pham

Bot KHONG quan ly san pham, khach hang, ton kho, hay cau hinh kich thuoc.

Luong nghiep vu mong muon:

```text
Zalo
-> forward thu cong sang Telegram Bot
-> AI doc tin nhan tu do
-> nguoi dung kiem tra/xac nhan
-> chon shop GHN
-> nhap kich thuoc kien
-> tinh phi GHN
-> tao don GHN
-> in phieu
-> webhook GHN cap nhat trang thai
```

Da HOAN THANH den buoc AI doc don. DUNG tai day: chua tao `don_hang` tu ket qua AI, chua lam GHN.

## Moi truong va cau truc

- Framework: Laravel 12, PHP 8.2+.
- Thu muc Laravel: `AutGhn/`.
- MySQL local XAMPP.
- Database local: `AutGhn`.
- Telegram webhook public qua ngrok.
- Khong commit `.env`.

## Database da co

Migration nghiep vu:

`database/migrations/2026_10_07_000000_create_nghiep_vu_giao_hang_tables.php`

Bang da tao, ten tieng Viet khong dau:

- `tin_nhan_telegram`
- `shop_ghn`
- `don_hang`
- `kien_hang`
- `lan_tinh_phi`
- `van_don`
- `lich_su_trang_thai_van_don`
- `webhook_ghn`

Quy uoc:

- Kich thuoc kien: cm.
- Can nang: gram.
- Tien: `decimal(14,2)`.
- Mot `don_hang` co mot `kien_hang` va mot `van_don`.
- KHONG tao bang products, customers, inventory, package size configs.

### Bang dang dung o giai doan hien tai

`tin_nhan_telegram` co cac cot:

```text
ma_tin_nhan, ma_kenh, ten_nguoi_gui, noi_dung_goc,
du_lieu_ai (JSON), trang_thai, created_at, updated_at
```

Model da co: `app/Models/TinNhanTelegram.php`.

- Dung dung bang `tin_nhan_telegram`.
- `du_lieu_ai` cast thanh `array`.
- `ma_tin_nhan` la `{chat_id}_{message_id}`, dung de chong xu ly trung.

## Telegram da hoat dong

### Route

```text
POST /api/telegram/webhook
```

Files lien quan:

- `routes/api.php`
- `bootstrap/app.php` da dang ky `api: routes/api.php`
- `app/Http/Controllers/TelegramWebhookController.php`
- `app/Services/TelegramService.php`
- `routes/console.php`

Controller hien tai lam dung vai tro dieu phoi:

1. Kiem tra header `X-Telegram-Bot-Api-Secret-Token` voi `TELEGRAM_WEBHOOK_SECRET`.
2. Nhan message/edited_message cua Telegram.
3. Luu `tin_nhan_telegram`.
4. Goi `AIOrderParserService` neu la tin nhan moi va co noi dung.
5. Luu JSON vao `du_lieu_ai` va doi `trang_thai` thanh `da_phan_tich`.
6. Gui ket qua tom tat tro lai Telegram kem inline keyboard.
7. Neu loi: cap nhat `trang_thai = loi`, ghi log khong co secret va gui thong bao loi.

### Luong xac nhan nguoi dung

Sau khi AI phan tich xong, bot gui ket qua kem inline keyboard:

```
[Xac nhan] [Sua ten] [Sua SDT] [Sua dia chi]
[Sua DC]   [Sua hang] [Sua COD]  [Sua ghi chu]
[Sua ghi chu]                              [Huy]
```

**Cac action callback:**
- `xac_nhan`: cap nhat `trang_thai = da_xac_nhan`
- `huy`: cap nhat `trang_thai = da_huy`
- `sua:ten`, `sua:sdt`, `sua:dc`, `sua:hang`, `sua:cod`, `sua:ghichu`: bat dau cho sua

**Khi bat dau sua:**
1. Cap nhat `trang_thai = cho_sua`
2. Luu field dang sua trong `_trang_thai_bot.dang_sua_field`
3. Bot hoi nguoi dung nhap gia tri moi

**Khi nguoi dung tra loi tin nhan moi:**
1. Doc field dang sua tu `_trang_thai_bot.dang_sua_field`
2. Chuan hoa gia tri:
   - SDT: chi giu chuoi so, bo khoang trang
   - COD: parse "350k", "1tr2", "350.000" thanh so VND
   - Hang: parse thanh mang `[{name, quantity}]`
   - Ten/Dia chi/Ghi chu: string hoac null
3. Cap nhat `du_lieu_ai[field]` va reset `trang_thai = da_phan_tich`
4. Gui lai ket qua phan tich voi inline keyboard

**Trang thai `tin_nhan_telegram.trang_thai`:**
- `moi`: tin nhan moi nhan
- `da_phan_tich`: AI da phan tich, hien thi keyboard
- `cho_sua`: dang cho nguoi dung nhap gia tri sua
- `da_xac_nhan`: nguoi dung da xac nhan (chua tao don_hang)
- `da_huy`: nguoi dung da huy
- `loi`: loi khi phan tich AI

**Metadata UI trong du_lieu_ai:**
`_trang_thai_bot.dang_sua_field` luu field dang sua, khong anh huong cac field nghiep vu.

Dang ky lai webhook khi URL ngrok thay doi:

```bash
php artisan telegram:webhook
```

Lenh tu dong dung `APP_URL/api/telegram/webhook`.

## AI doc don da hoat dong

Service:

`app/Services/AIOrderParserService.php`

No KHONG nam trong controller va KHONG tao `don_hang`.

### Input

Noi dung tin nhan tu do cua khach, co the viet tat, khong dau, xuong dong lon xon.

### Output bat buoc

```json
{
  "customer_name": null,
  "phone": null,
  "address": null,
  "items": [
    {"name": "", "quantity": 1}
  ],
  "cod": null,
  "note": null
}
```

Nguyen tac da cai dat:

- Khong tu doan ten, dia chi, so dien thoai, COD, ghi chu. Thieu thi `null`.
- Phone chi giu chu so, bo dau cach/dau cham/dau gach.
- COD chuan hoa thanh VND dang so: `350k` -> `350000`, `1tr2` -> `1200000`.
- Khong co COD thi `null`.
- `items` la mang; khong co hang thi `[]`.
- Khong kiem tra SKU, san pham hay ton kho.
- Note chi cho yeu cau giao hang: giao chieu, goi truoc, ship nhanh, de hang truoc cua...
- Laravel Validator kiem tra JSON AI truoc khi luu.
- Retry AI 2 lan, cach nhau 500ms.

### Provider AI

Service ho tro ca hai:

- Model bat dau bang `gemini-`: goi Gemini `generateContent` voi structured JSON schema.
- Cac model khac: goi OpenAI Responses API voi JSON schema.

AI local dang dung Gemini. Cau hinh THAT chi nam trong `.env`; khong ghi key vao code/log/database.

```env
AI_API_KEY=...
AI_MODEL=gemini-flash-lite-latest
```

Luu y quan trong: `gemini-2.5-flash` tra 404 voi key nay vi Google thong bao model nay khong con cap cho user moi. `gemini-3.8-flash` da tung tra 503 do qua tai. `gemini-flash-lite-latest` da duoc test thanh cong voi key hien tai.

Khi thay `.env`:

```bash
php artisan config:clear
```

Neu server PHP dang chay lau, restart server sau khi clear config.

## Bien .env can co

```env
APP_URL=https://<ngrok-domain>.ngrok-free.dev

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=AutGhn
DB_USERNAME=root
DB_PASSWORD=

TELEGRAM_BOT_TOKEN=...
TELEGRAM_WEBHOOK_SECRET=...

AI_API_KEY=...
AI_MODEL=gemini-flash-lite-latest
```

`.env.example` chi la mau, khong chay app va khong chua secret.

## Cac lenh can thiet

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/BotGHN247/AutGhn
php artisan migrate
php artisan config:clear
php artisan serve
php artisan telegram:webhook
php artisan route:list --path=telegram
php artisan test
```

Kiem tra tin nhan AI gan nhat:

```bash
php artisan tinker
```

```php
App\Models\TinNhanTelegram::latest()->first()->du_lieu_ai;
```

## Da test thanh cong

AI da tra ket qua hop le tu tin nhan:

```text
Dt:0906076222 a dong
Sn:200 duong mang nuoc phuong an hai hai phong
1 thung 32140 thanh ly
```

Ket qua da kiem tra:

```json
{
  "customer_name": "a dong",
  "phone": "0906076222",
  "address": "200 duong mang nuoc phuong an hai hai phong",
  "items": [{"name": "1 thung 32140 thanh ly", "quantity": 1}],
  "cod": null,
  "note": null
}
```

## Trang thai source hien tai

Thay doi code da commit:

### Files da tao moi

- `app/Services/TaoDonHangService.php` - Service tao don_hang tu tin nhan Telegram
- `app/Services/GhnService.php` - Service tich hop GHN (da cap nhat)
- `app/Models/TinNhanTelegram.php` - Them quan he `donHang()`
- `tests/Feature/TelegramConfirmationTest.php` - Test Telegram webhook

### Files da sua

- `app/Http/Controllers/TelegramWebhookController.php` - Flow callback moi, tu dong tao don_hang, nhap kien, chon shop
- `app/Services/TelegramService.php` - Cap nhat
- `config/services.php` - Cau hinh GHN
- `.env.example` - Them GHN config

`.env` co cau hinh local va bi gitignore; khong them vao commit.

### GHN Webhook (Giai đoạn 7 - DA LAM)

Files da tao:
- `app/Models/WebhookGhn.php` - Model luu webhook GHN
- `app/Http/Controllers/GhnWebhookController.php` - Controller xu ly webhook

Files da sua:
- `routes/api.php` - Them route `POST /api/ghn/webhook`
- `config/services.php` - Them `ghn.webhook_secret`
- `.env.example` - Them `GHN_WEBHOOK_SECRET=`

Route moi:
- `POST /api/ghn/webhook`

Chuc nang:
1. Luu webhook goc vao bang `webhook_ghn`
2. Chong xu ly trung bang `wasRecentlyCreated`
3. Map trang thai GHN -> trang thai noi bo
4. Cap nhat `van_don.trang_thai`
5. Tao `lich_su_trang_thai_van_don`
6. Gui thong bao Telegram

Trang thai GHN map:
| GHN | Noi bo |
|-----|--------|
| order_placed | da_dat |
| picking/pickup | dang_lay_hang |
| picked | da_lay_hang |
| storing/store | dang_luu_kho |
| transport/transporting | dang_van_chuyen |
| delivery/delivering | dang_giao |
| delivered | da_giao |
| return/returning | dang_tra_ve |
| returned | da_tra_ve |
| cancelled/cancel | da_huy |

## Viec tiep theo (CHUA lam)

**Tat ca cac chuc nang core da hoan thanh!**

1. ~~Them nut Telegram de xem/sua/xac nhan ket qua AI.~~ **DA XONG**
2. ~~Sau khi xac nhan, chuyen JSON AI thanh record `don_hang`.~~ **DA XONG**
3. ~~Nhap dai/rong/cao/can_nang cho `kien_hang`.~~ **DA XONG**
4. ~~Chon `shop_ghn`.~~ **DA XONG**
5. ~~Goi GHN tinh phi.~~ **DA XONG**
6. ~~Tao van don GHN, chong tao trung, in phieu.~~ **DA XONG**
7. ~~GHN webhook cap nhat trang thai.~~ **DA XONG**

Cac viec co the lam them:
- Them chuc nang in phieu
- Them lich su trang thai don hang
- Them thong ke/bao cao
- Viet them unit tests

Khong duoc tu y them quan ly san pham, khach hang, ton kho hoac cau hinh size.
