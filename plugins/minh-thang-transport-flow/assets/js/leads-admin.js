(function () {
  "use strict";

  function pad(n) {
    return n < 10 ? "0" + n : String(n);
  }

  function toYmd(date) {
    return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
  }

  function todayYmd() {
    return toYmd(new Date());
  }

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function syncHidden(fromInput, toInput, selectedDates) {
    if (!selectedDates.length) {
      fromInput.value = "";
      toInput.value = "";
      return;
    }
    fromInput.value = toYmd(selectedDates[0]);
    toInput.value =
      selectedDates.length > 1 ? toYmd(selectedDates[1]) : toYmd(selectedDates[0]);
  }

  ready(function () {
    var rangeInput = document.getElementById("mttf-leads-date-range");
    var fromInput = document.getElementById("mttf-leads-date-from");
    var toInput = document.getElementById("mttf-leads-date-to");
    if (!rangeInput || !fromInput || !toInput || typeof flatpickr === "undefined") {
      return;
    }

    var defaults = [];
    if (fromInput.value) {
      defaults.push(fromInput.value);
    }
    if (toInput.value) {
      if (!defaults.length || toInput.value !== fromInput.value) {
        defaults.push(toInput.value);
      } else if (defaults.length === 1) {
        defaults.push(toInput.value);
      }
    }

    var locale =
      window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn
        ? flatpickr.l10ns.vn
        : "default";
    if (locale && typeof locale === "object") {
      locale = Object.assign({}, locale, { rangeSeparator: " → " });
    }

    var fp = flatpickr(rangeInput, {
      mode: "range",
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d/m/Y",
      allowInput: false,
      clickOpens: true,
      locale: locale,
      // Cách 1: lịch mở đúng quanh hôm nay; chọn 1 ngày = lọc ngày đó.
      // Cách 2: chọn tiếp ngày thứ 2 (hoặc rê chọn khoảng) = khoảng ngày.
      defaultDate: defaults.length ? defaults : null,
      onReady: function (selectedDates, dateStr, instance) {
        var wrap = document.createElement("div");
        wrap.className = "mttf-leads-date-shortcuts";
        wrap.style.cssText =
          "display:flex;gap:8px;justify-content:flex-end;padding:8px 10px 10px;border-top:1px solid #e2e4e7;";

        var btnToday = document.createElement("button");
        btnToday.type = "button";
        btnToday.className = "button button-small";
        btnToday.textContent = "Hôm nay";
        btnToday.addEventListener("click", function (e) {
          e.preventDefault();
          var t = todayYmd();
          instance.setDate([t, t], true);
          fromInput.value = t;
          toInput.value = t;
          instance.close();
        });

        var btnClear = document.createElement("button");
        btnClear.type = "button";
        btnClear.className = "button button-small";
        btnClear.textContent = "Xóa";
        btnClear.addEventListener("click", function (e) {
          e.preventDefault();
          instance.clear();
          fromInput.value = "";
          toInput.value = "";
        });

        wrap.appendChild(btnClear);
        wrap.appendChild(btnToday);
        instance.calendarContainer.appendChild(wrap);
      },
      onOpen: function (selectedDates, dateStr, instance) {
        if (!selectedDates.length) {
          instance.jumpToDate(new Date(), false);
        }
      },
      onChange: function (selectedDates) {
        syncHidden(fromInput, toInput, selectedDates);
      },
      onClose: function (selectedDates, dateStr, instance) {
        // Chọn 1 ngày rồi đóng = mặc định đúng ngày đó (không bắt buộc chọn đủ 2).
        if (selectedDates.length === 1) {
          var one = toYmd(selectedDates[0]);
          fromInput.value = one;
          toInput.value = one;
          instance.setDate([selectedDates[0], selectedDates[0]], false);
        } else {
          syncHidden(fromInput, toInput, selectedDates);
        }
      },
    });

    // Expose for debugging if needed.
    rangeInput._mttfFlatpickr = fp;
  });
})();
