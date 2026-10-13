# Báo cáo tiến độ Bot Telegram -> AI -> GHN

## Giai đoạn 1 - Kiểm tra hiện trạng

Ngày kiểm tra: 2026-10-12

### Lệnh đã chạy

- `php artisan route:list`: chạy thành công, có 13 route.
- `php artisan migrate:status`: chưa kiểm tra được vì PHP không kết nối được MySQL local (`SQLSTATE[HY000] [2002] Operation not permitted`). Không chạy migration và không thay đổi dữ liệu.
- `composer validate --no-check-publish`: đạt.
- `php artisan test`: đạt, 27 tests / 57 assertions.
- `git status` và diff: working tree có nhiều thay đổi chưa commit; giữ nguyên các thay đổi đó.

### Bảng chức năng

| Chức năng | Trạng thái | Bằng chứng | Việc cần làm |
|---|---|---|---|
| Telegram Webhook | Có, cần gia cố | `routes/api.php`, `TelegramWebhookController` | Bắt buộc secret, liên kết callback đúng message |
| Lưu tin nhắn | Có | `TinNhanTelegram`, migration, test | Bổ sung kiểm thử update/cạnh tranh |
| Gemini AI | Có | `AIOrderParserService` | Bổ sung mock API và kiểm tra lỗi cấu hình |
| Kiểm tra dữ liệu thiếu | Có một phần | `TaoDonHangService::validateDuLieu` | Hoàn thiện quy tắc bắt buộc theo GHN |
| Xác nhận đơn | Có | callback `xac_nhan`, test | Bọc transaction/idempotency |
| Cấu hình shop GHN | Có giao diện, thiếu bảo vệ | `ShopGhnController`, `routes/web.php` | Thêm xác thực/phân quyền |
| Tính phí GHN | Chưa chạy được | `GhnService::tinhPhiShip` | Sửa khóa ngoại, địa chỉ và payload |
| Tạo vận đơn | Chưa xác minh end-to-end | `GhnService::taoVanDon` | Hoàn thiện lưu `van_don`, mock test |
| In phiếu | Có service | `GhnService::layPhieuIn` | Kiểm tra URL môi trường và quyền truy cập |
| Webhook GHN | Có nhưng lỗi logic | `GhnWebhookController` | Sửa định danh sự kiện, secret, idempotency |

### Lỗi ưu tiên cao phát hiện

1. `don_hang` không có các cột mà controller cập nhật: `shop_ghn_id`, `_phi_ship`, `_dich_vu`.
2. `lan_tinh_phi.don_hang_id` là bắt buộc nhưng `GhnService` lưu với `null`.
3. Webhook GHN dùng `order_code` làm khóa unique sự kiện, làm mất các cập nhật trạng thái tiếp theo.
4. Webhook GHN chưa kiểm tra `GHN_WEBHOOK_SECRET`.
5. CRUD `shop-ghn` đang public, chưa có auth/phân quyền.
6. Callback Telegram chỉ lấy bản ghi mới nhất theo chat, có thể thao tác nhầm đơn khi có nhiều đơn.

### Phạm vi chưa thực hiện

- Chưa gọi API GHN thật và chưa tạo vận đơn thật.
- Chưa chạy được kiểm tra schema thực tế vì MySQL bị chặn từ môi trường này.
- Chưa xác minh webhook production/ngrok.

## Giai đoạn tiếp theo

Sửa schema/code để luồng tính phí có khóa ngoại hợp lệ, thêm xác thực webhook GHN và kiểm thử idempotency trước khi tiếp tục tích hợp API thật.

## Giai đoạn 2 - Sửa lỗi nền tảng GHN

### Đã thực hiện

- Tạo migration `AutGhn/database/migrations/2026_10_12_000001_add_ghn_selection_to_don_hang_table.php` thêm `shop_ghn_id`, `phi_ship`, `dich_vu_ghn` tương thích với luồng hiện tại.
- Cập nhật `DonHang` `$fillable`, `$casts` và quan hệ `shopGhn`.
- Đổi controller từ các thuộc tính không tồn tại `_phi_ship`, `_dich_vu` sang cột database `phi_ship`, `dich_vu_ghn`.
- Sửa nhận diện webhook GHN: cùng `order_code` nhưng trạng thái khác được lưu và xử lý; cùng payload được chống trùng bằng event key/hash.
- Không ghi toàn bộ payload GHN vào log ứng dụng, chỉ ghi mã đơn và trạng thái.
- Cập nhật test webhook theo yêu cầu idempotency thực tế.

### Kiểm chứng

- `php artisan test`: **28 tests, 69 assertions passed**.
- `php -l` cho model và migration: đạt.
- Chưa chạy migration MySQL thật vì kết nối local vẫn bị môi trường chặn.

### Còn tồn tại

- `GHN_WEBHOOK_SECRET` vẫn cần được bắt buộc kiểm tra ở production.
- `GhnService::chuanHoaDiaChi()` hiện vẫn trả dữ liệu rỗng giả lập; chưa thể tính phí/tạo vận đơn thật.
- CRUD shop chưa có auth/phân quyền.

## Nhiệm vụ tiếp theo

Hoàn thiện xác thực webhook và bảo vệ CRUD shop, sau đó viết test mock cho địa chỉ/tính phí GHN. Chưa gọi API GHN thật hoặc tạo đơn có phát sinh phí.

## Giai đoạn 3 - Xác thực webhook GHN

### Đã thực hiện

- `GhnWebhookController` từ chối request nếu `GHN_WEBHOOK_SECRET` chưa cấu hình hoặc header `X-GHN-Webhook-Secret` không khớp.
- Test suite được cấu hình secret riêng và dùng helper gửi webhook có xác thực.

### Kiểm chứng

- `php artisan test`: **28 tests, 69 assertions passed**.

### Còn tồn tại

- Tên header cần được đối chiếu với cơ chế xác thực webhook mà tài khoản GHN thực tế cung cấp trước khi triển khai production.
- CRUD shop vẫn chưa có đăng nhập/phân quyền; chưa tự ý thêm auth vì project hiện chưa có flow đăng nhập hoàn chỉnh.

## Nhiệm vụ tiếp theo

Kiểm tra và hoàn thiện parser kích thước kiện hàng, sau đó mock API GHN cho tính phí. Cần giữ mọi request API ngoài ở chế độ mock cho đến khi có phê duyệt gọi GHN thật.

## Giai đoạn 4 - Chặn tính phí khi địa chỉ chưa hợp lệ

### Đã thực hiện

- `GhnService::tinhPhiShip()` không gọi API GHN nếu chưa có `district_id` và `ward_code` hợp lệ.
- Lỗi được lưu vào `lan_tinh_phi` gắn với đúng đơn hàng để có lịch sử và có thể xử lý lại sau.
- Thêm test đảm bảo request GHN không được gửi với địa chỉ chưa map.

### Kiểm chứng

- `php artisan test`: **29 tests, 73 assertions passed**.
- API ngoài được mock; không phát sinh request GHN thật.

### Blocker thực tế

`chuanHoaDiaChi()` vẫn cần cơ chế map địa chỉ tự do sang mã quận/huyện và phường/xã GHN. Không được tự đoán mã. Cần chọn một trong hai hướng: lưu mã GHN khi cấu hình shop/đơn, hoặc triển khai tra cứu danh mục GHN theo tài liệu và yêu cầu người dùng xác nhận kết quả.

## Nhiệm vụ tiếp theo

Thiết kế dữ liệu/luồng nhập mã địa chỉ GHN rõ ràng, sau đó thêm test mock thành công cho tính phí và tạo vận đơn. Chưa gọi GHN thật.
