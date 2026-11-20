// Adelina 3 HOA — script.js
// Pinagsasama ang maliliit na UI behavior na ginagamit sa mga page ng application.
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('menuBtn');
  var sidebar = document.getElementById('sidebar');

  // Nagpapalit sa password field sa pagitan ng plain text at mask.
  document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var inputId = toggle.getAttribute('data-password-toggle');
      var input = document.getElementById(inputId);
      if (!input) {
        return;
      }

      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      toggle.setAttribute('aria-pressed', String(!showing));
      toggle.setAttribute('aria-label', showing ? 'Ipakita ang password' : 'Itago ang password');
    });
  });

  // Binubuksan o isinasara ang sidebar sa mobile at ina-update ang accessibility state.
  if (btn && sidebar) {
    btn.addEventListener('click', function () {
      var isOpen = sidebar.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });
  }

  var paymentOption = document.getElementById('residentPaymentOption');
  var onlineReferenceField = document.getElementById('onlineReferenceField');
  var onlineProofField = document.getElementById('onlineProofField');
  var paymentReference = document.getElementById('paymentReference');
  var paymentProof = document.getElementById('paymentProof');

  if (paymentOption && onlineReferenceField && onlineProofField) {
    var updateOnlineFields = function () {
      var online = paymentOption.value === 'GCash' || paymentOption.value === 'Online Bank';
      onlineReferenceField.hidden = !online;
      onlineProofField.hidden = !online;
      paymentReference.required = online;
      paymentProof.required = online;
    };
    paymentOption.addEventListener('change', updateOnlineFields);
    updateOnlineFields();
  }

  // Sinasala ang kasalukuyang table rows habang nagta-type ang user.
  var search = document.getElementById('searchInput');
  var table = document.getElementById('dataTable');
  if (search && table) {
    search.addEventListener('input', function () {
      var query = search.value.toLocaleLowerCase();
      var rows = table.querySelectorAll('tbody tr');

      rows.forEach(function (row) {
        row.hidden = !row.textContent.toLocaleLowerCase().includes(query);
      });
    });
  }

  // Binabalaan ang user bago ipadala ang mga form na may confirmation message.
  document.querySelectorAll('.confirm-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var message = form.getAttribute('data-confirm') || 'Sigurado ka ba?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  // Awtomatikong itinatago ang mga alert matapos ang maikling panahon.
  document.querySelectorAll('.alert').forEach(function (alert) {
    window.setTimeout(function () {
      alert.style.display = 'none';
    }, 4000);
  });
});
