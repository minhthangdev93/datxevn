# Lead → Google Sheets → Customer Match (Google Ads)

## Mục đích

Đổ SĐT lead từ datxevietnam.vn sang Google Sheet (chuẩn `+84…`) để lọc và upload **Customer Match** trên Google Ads.

## Phía WordPress (đã có trong plugin MTTF)

1. Vào **Tuyến xe → Cài đặt → Lead & liên lạc → Google Sheets / Customer Match**.
2. Bật **API đổ lead ra Sheet**.
3. Lưu (token tự tạo nếu trống) hoặc tick **Tạo token mới khi lưu**.
4. Copy:
   - Endpoint: `https://datxevietnam.vn/wp-json/mttf/v1/leads-export`
   - Token

## Phía Google Sheet

1. Tạo Spreadsheet mới.
2. **Extensions → Apps Script** → dán nội dung file Apps Script (tải từ **WP Admin → Cài đặt MTTF → Lead → nút Apps Script (.js)**, hoặc file trong plugin `docs/lead-sheets-apps-script.js`) → Save.
3. **Project Settings → Script properties**:
   - `API_URL` = `https://datxevietnam.vn/wp-json/mttf/v1/leads-export`
   - `API_TOKEN` = token từ bước WP
4. Chạy hàm `syncLeads` lần đầu → Approve quyền.
5. **Triggers**: `syncLeads`, Day timer, khoảng **6am–7am**, timezone **Asia/Ho_Chi_Minh**.
6. Menu spreadsheet **MTTF Leads → Đồng bộ ngay** để kéo thủ công.

### Tabs

| Tab | Dùng để |
|-----|---------|
| **Leads** | Log đầy đủ (id, ngày, phone, nhà xe, tuyến, UTM…) |
| **CustomerMatch** | Cột `Phone` unique — upload Ads |

## Upload Google Ads

1. Google Ads → **Tools → Audience manager → Customer lists**.
2. Tạo / cập nhật list → upload dữ liệu từ **Google Sheet** (tab CustomerMatch) hoặc export CSV cột `Phone`.
3. Chỉ dùng số dạng `+8490…` (Sheet đã chuẩn hóa). Không cần hash thủ công nếu upload đúng format Phone.

## Lọc theo nhà xe (HK / G8)

- Filter trên Sheet cột `nha_xe` (`hk-buslines`, `g8-open-tour`), hoặc
- Gọi API kèm `?nha_xe=hk-buslines` (có thể chỉnh URL trong Script Properties / thêm property sau).

## Bảo mật

- Không share token công khai.
- Ưu tiên header `Authorization: Bearer …` (script mặc định dùng cách này).
- Tắt API trên WP nếu không dùng.
