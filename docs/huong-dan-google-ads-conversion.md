# Hướng dẫn tách chuyển đổi Google Ads theo landing

Áp dụng cho 2 link Ads:

- HK Buslines: https://datxevietnam.vn/nha-xe/hk-buslines/
- G8 Open Tour: https://datxevietnam.vn/nha-xe/g8-open-tour/

GTM đang dùng trên site: `GTM-TTDPG8XV`

---

## 1. Plugin đã làm gì?

Khi khách **gửi form thành công** (popup “Gửi yêu cầu tư vấn”), plugin đẩy vào `dataLayer`:

### Event chung

```
mttf_lead_submit
```

### Event riêng theo landing (dùng để tách Ads)

| Landing | Event name (Custom Event) |
|---|---|
| `/nha-xe/hk-buslines/` | `mttf_lead_nha_xe_hk_buslines` |
| `/nha-xe/g8-open-tour/` | `mttf_lead_nha_xe_g8_open_tour` |

### Biến kèm theo

- `route_id` — ID dịch vụ trên card khách chọn
- `route_slug` — slug dịch vụ trên card
- `page_path` — đường dẫn trang (vd. `/nha-xe/hk-buslines`)
- `landing_type` — `nha_xe` hoặc `tuyen`
- `landing_slug` — vd. `hk_buslines`, `g8_open_tour`

Nhiều card trên cùng 1 trang vẫn dùng **chung 1 event landing**. Card chỉ ghi thêm `route_id` / `route_slug`.

---

## 2. Tạo 2 Conversion Action trên Google Ads

Vào [Google Ads](https://ads.google.com/) → **Goals / Mục tiêu** → **Conversions** → **+ New conversion action**.

### Conversion 1 — HK Buslines

1. Chọn **Website**
2. Tên: `Lead HK Buslines`
3. Category: **Submit lead form**
4. Value: Không dùng / hoặc giá trị cố định (tuỳ bạn)
5. Count: **One**
6. Click-through window: 30 ngày (hoặc mặc định)
7. Tạo xong → chọn **Use Google Tag Manager**
8. Copy:
   - **Conversion ID**: `AW-xxxxxxxxxx`
   - **Conversion Label**: (chuỗi Label của conversion HK)

### Conversion 2 — G8 Open Tour

Làm tương tự:

1. Tên: `Lead G8 Open Tour`
2. Category: **Submit lead form**
3. Count: **One**
4. Copy Conversion ID + Label của conversion G8

> Cùng 1 tài khoản Ads thường dùng chung **Conversion ID** (`AW-...`), khác nhau ở **Conversion Label**.

---

## 3. Cấu hình GTM (`GTM-TTDPG8XV`)

Mở [tagmanager.google.com](https://tagmanager.google.com/) → container `GTM-TTDPG8XV`.

### 3.1 Tag bắt buộc: Conversion Linker

- Tags → New
- Type: **Conversion Linker**
- Trigger: **All Pages**
- Tên: `CL - Conversion Linker`
- Save

Nếu đã có sẵn thì bỏ qua.

### 3.2 Trigger HK

- Triggers → New
- Type: **Custom Event**
- Event name: `mttf_lead_nha_xe_hk_buslines`
- This trigger fires on: **All Custom Events**
- Tên: `CE - Lead HK Buslines`
- Save

### 3.3 Trigger G8

- Triggers → New
- Type: **Custom Event**
- Event name: `mttf_lead_nha_xe_g8_open_tour`
- Tên: `CE - Lead G8 Open Tour`
- Save

### 3.4 Tag Ads Conversion — HK

- Tags → New
- Type: **Google Ads Conversion Tracking**
- Conversion ID: `AW-...` (của bạn)
- Conversion Label: Label của **Lead HK Buslines**
- Triggering: `CE - Lead HK Buslines`
- Tên: `ADS - Lead HK Buslines`
- Save

### 3.5 Tag Ads Conversion — G8

- Tags → New
- Type: **Google Ads Conversion Tracking**
- Conversion ID: `AW-...`
- Conversion Label: Label của **Lead G8 Open Tour**
- Triggering: `CE - Lead G8 Open Tour`
- Tên: `ADS - Lead G8 Open Tour`
- Save

### 3.6 (Tuỳ chọn) Conversion tổng site

Nếu muốn thêm conversion “tất cả lead”:

- Trigger Custom Event: `mttf_lead_submit`
- Tag Ads Conversion riêng (conversion action thứ 3)

Không bắt buộc nếu bạn chỉ tối ưu theo 2 campaign HK / G8.

### 3.7 Publish

Bấm **Submit → Publish**.

---

## 4. Gắn conversion vào campaign Ads

### Campaign HK

- Final URL: `https://datxevietnam.vn/nha-xe/hk-buslines/`
- Goal / Conversion: chọn **Lead HK Buslines** (primary)
- Không chọn Lead G8 làm primary của campaign này

### Campaign G8

- Final URL: `https://datxevietnam.vn/nha-xe/g8-open-tour/`
- Goal / Conversion: chọn **Lead G8 Open Tour** (primary)

Khuyến nghị thêm UTM:

```
https://datxevietnam.vn/nha-xe/hk-buslines/?utm_source=google&utm_medium=cpc&utm_campaign=hk-buslines
https://datxevietnam.vn/nha-xe/g8-open-tour/?utm_source=google&utm_medium=cpc&utm_campaign=g8-open-tour
```

---

## 5. Kiểm tra trước khi chạy Ads thật

1. GTM → **Preview**
2. Mở URL: `https://datxevietnam.vn/nha-xe/hk-buslines/`
3. Bấm card bất kỳ → điền SĐT test → **Gửi yêu cầu**
4. Trong Preview phải thấy:
   - Event `mttf_lead_submit`
   - Event `mttf_lead_nha_xe_hk_buslines`
   - Tag `ADS - Lead HK Buslines` = **Fired**
   - Tag G8 = **Not Fired**
5. Lặp lại với `/nha-xe/g8-open-tour/` — chỉ tag G8 Fired

Console (F12) có thể chạy:

```js
window.dataLayer.filter(function (e) {
  return e && typeof e.event === "string" && e.event.indexOf("mttf_lead") === 0;
});
```

---

## 6. Checklist nhanh

- [ ] WP: đo lường dataLayer đang **bật** (MTTF → Measurement)
- [ ] Plugin version ≥ 1.3.1 (có event tách landing)
- [ ] Google Ads: 2 conversion action (HK + G8)
- [ ] GTM: Conversion Linker
- [ ] GTM: 2 trigger + 2 tag Ads Conversion
- [ ] GTM đã **Publish**
- [ ] Preview: HK chỉ fire tag HK, G8 chỉ fire tag G8
- [ ] Campaign HK / G8 gắn đúng conversion primary

---

## 7. Lưu ý

- Không gắn conversion vào **click nút Gửi** — chỉ fire khi form **thành công**.
- Không gắn lại GTM thứ 2 trong MTTF Settings nếu theme đã có `GTM-TTDPG8XV`.
- Sau deploy code mới lên VPS, hard refresh / xoá cache để load `frontend.js` mới.
- Conversion Ads có thể mất vài giờ mới hiện “Recording conversions”.

---

## 8. Thêm landing Ads sau này

Không cần sửa code. Ví dụ landing mới `/nha-xe/sao-viet/`:

- Event tự sinh: `mttf_lead_nha_xe_sao_viet`
- Tạo conversion Ads mới + trigger/tag GTM tương ứng là đủ.
