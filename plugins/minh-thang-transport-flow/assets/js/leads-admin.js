(function () {
  "use strict";

  function pad(n) {
    return n < 10 ? "0" + n : String(n);
  }

  function toYmd(date) {
    return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
  }

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
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
    if (toInput.value && toInput.value !== fromInput.value) {
      defaults.push(toInput.value);
    } else if (toInput.value && defaults.length === 1) {
      defaults.push(toInput.value);
    }

    var locale = window.flatpickr && flatpickr.l10ns && flatpickr.l10ns.vn ? flatpickr.l10ns.vn : "default";
    if (locale && typeof locale === "object") {
      locale = Object.assign({}, locale, { rangeSeparator: " → " });
    }

    flatpickr(rangeInput, {
      mode: "range",
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d/m/Y",
      allowInput: false,
      locale: locale,
      defaultDate: defaults.length ? defaults : null,
      onChange: function (selectedDates) {
        if (!selectedDates.length) {
          fromInput.value = "";
          toInput.value = "";
          return;
        }
        fromInput.value = toYmd(selectedDates[0]);
        toInput.value = selectedDates.length > 1 ? toYmd(selectedDates[1]) : toYmd(selectedDates[0]);
      },
      onClose: function (selectedDates) {
        if (selectedDates.length === 1) {
          fromInput.value = toYmd(selectedDates[0]);
          toInput.value = toYmd(selectedDates[0]);
        }
      },
    });
  });
})();
