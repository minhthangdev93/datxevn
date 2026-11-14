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

  function submitForm() {
    var form = document.getElementById("mttf-leads-form");
    if (form) {
      form.submit();
    }
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

    // Nếu form chưa có ngày (không nên xảy ra vì PHP default hôm nay) — điền hôm nay.
    if (!defaults.length) {
      var t0 = todayYmd();
      fromInput.value = t0;
      toInput.value = t0;
      defaults = [t0, t0];
    }

    var lastApplied = fromInput.value + "|" + toInput.value;
    var skipNextCloseSubmit = false;

    function applyKey() {
      return fromInput.value + "|" + toInput.value;
    }

    function submitIfChanged() {
      var key = applyKey();
      if (key === lastApplied) {
        return;
      }
      lastApplied = key;
      submitForm();
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
      defaultDate: defaults,
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
          skipNextCloseSubmit = true;
          instance.setDate([t, t], true);
          fromInput.value = t;
          toInput.value = t;
          instance.close();
          submitIfChanged();
        });

        var btnClear = document.createElement("button");
        btnClear.type = "button";
        btnClear.className = "button button-small";
        btnClear.textContent = "Xóa";
        btnClear.addEventListener("click", function (e) {
          e.preventDefault();
          skipNextCloseSubmit = true;
          instance.clear();
          fromInput.value = "";
          toInput.value = "";
          instance.close();
          submitIfChanged();
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
        if (skipNextCloseSubmit) {
          skipNextCloseSubmit = false;
          return;
        }
        // Chọn 1 ngày rồi đóng = lọc đúng ngày đó (không bắt buộc chọn đủ 2).
        if (selectedDates.length === 1) {
          var one = toYmd(selectedDates[0]);
          fromInput.value = one;
          toInput.value = one;
          instance.setDate([selectedDates[0], selectedDates[0]], false);
        } else {
          syncHidden(fromInput, toInput, selectedDates);
        }
        submitIfChanged();
      },
    });

    rangeInput._mttfFlatpickr = fp;
  });
})();
