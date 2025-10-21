=== CF7 & WooCommerce Google Sheet Connector ===

Tác giả: maisydat
Thẻ (Tags): contact form 7, woocommerce, google sheets, integration, forms, orders
Yêu cầu WordPress: 5.0 trở lên
Đã kiểm thử với: 6.4
Yêu cầu PHP: 7.4 trở lên
Phiên bản ổn định: 1.0.0
Giấy phép: GPLv2 hoặc cao hơn
Link giấy phép: https://www.gnu.org/licenses/gpl-2.0.html

Kết nối Contact Form 7 và đơn hàng WooCommerce tới Google Sheets với khả năng ánh xạ (mapping) linh hoạt giữa các trường dữ liệu.

📘 Mô tả

CF7 & WooCommerce Google Sheet Connector cho phép bạn tự động gửi dữ liệu từ Contact Form 7 và đơn hàng WooCommerce lên Google Sheets, với khả năng cấu hình linh hoạt cho từng trường dữ liệu.

⚙️ Tính năng chính

📝 Tích hợp Contact Form 7: Tự động gửi dữ liệu form lên Google Sheets

🛒 Tích hợp WooCommerce: Đồng bộ dữ liệu đơn hàng khi hoàn tất đơn

🔁 Ánh xạ trường linh hoạt: Liên kết bất kỳ trường nào trong form tới cột tùy chọn trong Google Sheet

🌍 Cấu hình mặc định toàn cục: Đặt mapping mặc định cho tất cả form và đơn hàng

🔐 Xác thực bằng Google Service Account: Kết nối bảo mật thông qua tài khoản dịch vụ Google

🔎 Kiểm tra kết nối: Cho phép kiểm tra kết nối đến Google Sheets ngay trong admin

📄 Nút xem Sheet: Truy cập nhanh vào Google Sheet đã kết nối

🧩 Cấu hình linh hoạt: Có thể thiết lập riêng cho từng form hoặc dùng mặc định

🧰 Ghi log lỗi: Hỗ trợ ghi log chi tiết để kiểm tra sự cố

🚀 Cách hoạt động

Cài đặt: Dán thông tin xác thực (credentials) của Google Service Account

Contact Form 7: Bật tab Google Sheets trong từng form và cấu hình mapping

WooCommerce: Bật tính năng đồng bộ trong phần cài đặt WooCommerce và ánh xạ các trường

Tự động đồng bộ: Mỗi khi có form gửi hoặc đơn hàng hoàn tất, dữ liệu sẽ được đẩy lên Google Sheets

🧾 Yêu cầu hệ thống

WordPress 5.0 trở lên

Plugin Contact Form 7

Plugin WooCommerce

Google Service Account (để truy cập API Google Sheets)

PHP 7.4 trở lên

🛠️ Hướng dẫn cài đặt
Upload plugin vào thư mục /wp-content/plugins/

Nếu chưa có file composer.json thì chạy lệnh composer require google/apiclient:^2.15 trong thư mục plugin để cài đặt thư viện Google 

Nếu đã có file composer.json thì chạy lệnh composer install trong thư mục plugin để cài đặt thư viện Google 

Kích hoạt plugin trong trang Plugins → Installed Plugins

Vào Cài đặt → Sheet Connector để dán thông tin tài khoản dịch vụ Google

Cấu hình từng form trong Liên hệ → Form liên hệ → Tab Google Sheets

Cấu hình tích hợp WooCommerce tại WooCommerce → Kết nối Google Sheet

❓ Câu hỏi thường gặp

Q: Làm sao để lấy thông tin tài khoản dịch vụ (Service Account)?

Truy cập Google Cloud Console

Tạo project mới hoặc chọn project có sẵn

Bật API Google Sheets API

Vào Credentials → Create Credentials → Service Account

Tải file JSON key

Sao chép toàn bộ nội dung JSON vào phần cài đặt plugin

Q: Có thể dùng nhiều Google Sheet cho các form khác nhau không?
→ Có! Mỗi form trong Contact Form 7 có thể dùng một Google Spreadsheet riêng biệt và tên sheet riêng.

Q: Nếu kết nối tới Google Sheets bị lỗi thì sao?
→ Plugin sẽ ghi log lỗi vào file debug của WordPress.
Bạn có thể kiểm tra chi tiết nguyên nhân trong wp-content/debug.log.

Q: Có thể ánh xạ các trường tùy chỉnh không?
→ Hoàn toàn được! Bạn có thể map bất kỳ trường nào trong Contact Form 7 hoặc WooCommerce với bất kỳ cột nào trong Google Sheet.

Q: Có cần chia sẻ Google Sheet cho ai không?
→ Có, bạn phải chia sẻ Google Sheet cho email của tài khoản dịch vụ (được hiển thị trong file JSON).

🖼️ Ảnh minh họa

Trang cài đặt plugin với cấu hình tài khoản Google Service

Giao diện tab Google Sheets trong Contact Form 7

Cài đặt WooCommerce tích hợp

Tính năng kiểm tra kết nối

Nút “Xem Sheet” truy cập nhanh

🧩 Changelog
1.0.0

Phát hành bản đầu tiên

Hỗ trợ tích hợp Contact Form 7 với ánh xạ linh hoạt

Tích hợp WooCommerce, đồng bộ dữ liệu đơn hàng

Xác thực qua Google Service Account

Cấu hình ánh xạ toàn cục và theo từng form

Chức năng kiểm tra kết nối

Ghi log lỗi hỗ trợ kiểm tra sự cố

🔔 Thông báo nâng cấp
1.0.0

Bản phát hành đầu tiên của CF7 & WooCommerce Google Sheet Connector

⚙️ Chi tiết kỹ thuật
Hooks được sử dụng:

wpcf7_editor_panels – Thêm tab Google Sheets vào trình chỉnh sửa Contact Form 7

wpcf7_save_contact_form – Lưu cài đặt riêng cho từng form

wpcf7_before_send_mail – Bắt sự kiện khi người dùng gửi form

woocommerce_order_status_completed – Gửi dữ liệu khi đơn hàng hoàn tất

woocommerce_thankyou – Gửi dữ liệu sau khi thanh toán thành công

Cơ sở dữ liệu:

Không tạo thêm bảng riêng.
Tất cả cài đặt đều được lưu trong bảng options của WordPress.

Cấu trúc thư mục:
/cf7-woo-sheet-connector/
├── cf7-woo-sheet-connector.php
├── includes/
│   ├── class-cwsc-admin.php
│   ├── class-cwsc-cf7.php
│   ├── class-cwsc-woo.php
│   ├── class-cwsc-google-client.php
│   └── helpers.php
├── assets/
│   ├── admin.js
│   └── admin.css
├── composer.json
└── readme.txt
