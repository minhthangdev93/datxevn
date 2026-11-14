/**
 * MTTF Lead → Google Sheets (Customer Match)
 *
 * Setup:
 * 1. Tạo Google Spreadsheet mới.
 * 2. Extensions → Apps Script → dán toàn bộ file này → Save.
 * 3. Project Settings → Script Properties:
 *      API_URL   = https://datxevietnam.vn/wp-json/mttf/v1/leads-export
 *      API_TOKEN = (token từ WP Admin → Cài đặt MTTF → Lead → Google Sheets)
 * 4. Chạy syncLeads() lần đầu (Authorize).
 * 5. Triggers: syncLeads, Day timer, 6am–7am, timezone Asia/Ho_Chi_Minh.
 *
 * Lưu ý getRange(row, column, numRows, numColumns): tham số 3–4 là SỐ HÀNG / SỐ CỘT
 * (không phải endRow / endColumn).
 */

var LEADS_SHEET = 'Leads';
var MATCH_SHEET = 'CustomerMatch';
var LEADS_HEADERS = [
  'id',
  'created_at',
  'phone_raw',
  'phone_e164',
  'nha_xe',
  'nha_xe_label',
  'route_title',
  'route_slug',
  'source_url',
  'utm'
];

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('MTTF Leads')
    .addItem('Đồng bộ ngay', 'syncLeads')
    .addItem('Rebuild CustomerMatch', 'rebuildCustomerMatch')
    .addToUi();
}

function getProp_(key) {
  var v = PropertiesService.getScriptProperties().getProperty(key);
  if (!v) {
    throw new Error('Thiếu Script Property: ' + key);
  }
  return v;
}

function ensureSheet_(name, headers) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(name);
  if (!sh) {
    sh = ss.insertSheet(name);
  }
  if (sh.getLastRow() < 1) {
    // 1 hàng, N cột
    sh.getRange(1, 1, 1, headers.length).setValues([headers]);
    sh.setFrozenRows(1);
  } else {
    var existing = sh.getRange(1, 1, 1, headers.length).getValues()[0];
    var need = false;
    for (var i = 0; i < headers.length; i++) {
      if (String(existing[i] || '') !== headers[i]) {
        need = true;
        break;
      }
    }
    if (need) {
      sh.getRange(1, 1, 1, headers.length).setValues([headers]);
    }
  }
  return sh;
}

/** Ghi mảng 2D: getRange(startRow, 1, numRows, numCols). */
function writeBlock_(sheet, startRow, values) {
  if (!values || !values.length) {
    return;
  }
  var numRows = values.length;
  var numCols = values[0].length;
  sheet.getRange(startRow, 1, numRows, numCols).setValues(values);
}

function loadExistingIds_(sheet) {
  var last = sheet.getLastRow();
  var map = {};
  if (last < 2) {
    return map;
  }
  // Hàng 2 → last = (last - 1) hàng dữ liệu
  var numRows = last - 1;
  var vals = sheet.getRange(2, 1, numRows, 1).getValues();
  for (var i = 0; i < vals.length; i++) {
    var id = String(vals[i][0] || '').trim();
    if (id) {
      map[id] = true;
    }
  }
  return map;
}

function syncLeads() {
  var apiUrl = getProp_('API_URL').replace(/\/$/, '');
  var token = getProp_('API_TOKEN');
  var props = PropertiesService.getScriptProperties();
  var since = props.getProperty('LAST_SYNC') || '';

  var url = apiUrl + '?limit=5000&dedupe=0';
  if (since) {
    url += '&since=' + encodeURIComponent(since);
  }

  var res = UrlFetchApp.fetch(url, {
    method: 'get',
    muteHttpExceptions: true,
    headers: {
      Authorization: 'Bearer ' + token,
      Accept: 'application/json'
    }
  });

  var code = res.getResponseCode();
  var body = res.getContentText();
  if (code < 200 || code >= 300) {
    throw new Error('API HTTP ' + code + ': ' + body.substring(0, 500));
  }

  var data = JSON.parse(body);
  if (!data || !data.ok) {
    throw new Error('API response not ok: ' + body.substring(0, 500));
  }

  var leads = data.leads || [];
  var sheet = ensureSheet_(LEADS_SHEET, LEADS_HEADERS);
  var existing = loadExistingIds_(sheet);
  var toAppend = [];
  var maxCreated = since;

  for (var i = 0; i < leads.length; i++) {
    var L = leads[i];
    var id = String(L.id || '');
    if (!id || existing[id]) {
      continue;
    }
    var created = String(L.created_at || '');
    if (created && (!maxCreated || created > maxCreated)) {
      maxCreated = created;
    }
    toAppend.push([
      L.id,
      created,
      L.phone_raw || '',
      L.phone_e164 || '',
      L.nha_xe || '',
      L.nha_xe_label || '',
      L.route_title || '',
      L.route_slug || '',
      L.source_url || '',
      L.utm ? JSON.stringify(L.utm) : ''
    ]);
  }

  if (toAppend.length) {
    var startRow = Math.max(sheet.getLastRow() + 1, 2);
    writeBlock_(sheet, startRow, toAppend);
  }

  if (maxCreated) {
    props.setProperty('LAST_SYNC', maxCreated);
  }

  rebuildCustomerMatch();

  SpreadsheetApp.getActiveSpreadsheet().toast(
    'Thêm ' + toAppend.length + ' lead. Tổng API: ' + leads.length,
    'MTTF sync',
    8
  );
}

function rebuildCustomerMatch() {
  var leads = ensureSheet_(LEADS_SHEET, LEADS_HEADERS);
  var match = ensureSheet_(MATCH_SHEET, ['Phone', 'nha_xe', 'last_created_at']);
  var last = leads.getLastRow();
  var phones = {};

  if (last >= 2) {
    var numRows = last - 1;
    var rows = leads.getRange(2, 1, numRows, LEADS_HEADERS.length).getValues();
    for (var i = 0; i < rows.length; i++) {
      var phone = String(rows[i][3] || '').trim();
      if (!phone || phone.indexOf('+') !== 0) {
        continue;
      }
      var nha = String(rows[i][4] || '');
      var created = String(rows[i][1] || '');
      if (!phones[phone] || created > phones[phone].created) {
        phones[phone] = { nha: nha, created: created };
      }
    }
  }

  var out = [];
  var keys = Object.keys(phones);
  keys.sort();
  for (var k = 0; k < keys.length; k++) {
    var p = keys[k];
    out.push([p, phones[p].nha, phones[p].created]);
  }

  match.clearContents();
  match.getRange(1, 1, 1, 3).setValues([['Phone', 'nha_xe', 'last_created_at']]);
  match.setFrozenRows(1);
  writeBlock_(match, 2, out);
}

function resetSyncCursor() {
  PropertiesService.getScriptProperties().deleteProperty('LAST_SYNC');
}
